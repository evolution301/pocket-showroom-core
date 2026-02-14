<?php
if (!defined('ABSPATH'))
    exit;

class PS_Image_Watermarker
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('wp_generate_attachment_metadata', array($this, 'process_image_watermark'), 10, 2);
    }

    /**
     * Apply watermark to uploaded images
     */
    public function process_image_watermark($metadata, $attachment_id)
    {
        // Check if enabled
        if (!get_option('ps_enable_watermark')) {
            return $metadata;
        }

        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            error_log('Pocket Showroom: GD extension not loaded, skipping watermark.');
            return $metadata;
        }

        $type = get_option('ps_watermark_type', 'text');

        // Validation
        if ($type === 'text') {
            $text = get_option('ps_watermark_text', 'Pocket Showroom');
            if (empty($text))
                return $metadata;
        } else {
            $image_id = get_option('ps_watermark_image_id');
            if (empty($image_id))
                return $metadata;
            $wm_image_path = get_attached_file($image_id);
            if (!$wm_image_path || !file_exists($wm_image_path))
                return $metadata;
        }

        // 缓存所有设置，避免在循环中重复调用 get_option（修复 #11）
        $settings = array(
            'type' => get_option('ps_watermark_type', 'text'),
            'opacity' => (int) get_option('ps_watermark_opacity', 37),
            'size_pct' => (int) get_option('ps_watermark_size', 6),
            'position' => get_option('ps_watermark_position', 'br'),
            'rotation' => (int) get_option('ps_watermark_rotation', 0),
        );

        if ($settings['type'] === 'text') {
            $settings['text'] = get_option('ps_watermark_text', 'Pocket Showroom');
        } else {
            $settings['wm_image_path'] = $wm_image_path;
        }

        // Get upload directory
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];

        // 1. Watermark Original File
        if (isset($metadata['file'])) {
            $original_path = $base_dir . '/' . $metadata['file'];
            $this->apply_watermark_logic($original_path, $settings);
        }

        // 2. Watermark Sizes
        if (isset($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_info) {
                if (isset($size_info['file'])) {
                    $dirname = dirname($metadata['file']);
                    $path = $base_dir . '/' . $dirname . '/' . $size_info['file'];
                    $this->apply_watermark_logic($path, $settings);
                }
            }
        }

        return $metadata;
    }

    /**
     * Dispatch Logic
     */
    private function apply_watermark_logic($target_path, $settings)
    {
        if (!file_exists($target_path))
            return;

        $type = $settings['type'];
        $opacity = $settings['opacity'];
        $size_pct = $settings['size_pct'];
        $position = $settings['position'];
        $rotation = $settings['rotation'];

        // Load Target Image
        $target_info = getimagesize($target_path);
        if (!$target_info)
            return; // Guard against non-image files

        $mime = $target_info['mime'];

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($target_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($target_path);
                break;
            default:
                return;
        }

        if (!$image)
            return;

        // Enable alpha blending for target image
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $target_w = imagesx($image);
        $target_h = imagesy($image);

        // --- Create Watermark Layer (GD Resource) ---
        $wm_layer = null;

        if ($type === 'image') {
            $wm_path = isset($settings['wm_image_path']) ? $settings['wm_image_path'] : '';
            if (!$wm_path || !file_exists($wm_path)) {
                imagedestroy($image);
                return;
            }

            $wm_info = getimagesize($wm_path);
            if (!$wm_info) {
                imagedestroy($image);
                return;
            }

            if ($wm_info['mime'] == 'image/png')
                $wm_src = imagecreatefrompng($wm_path);
            elseif ($wm_info['mime'] == 'image/jpeg')
                $wm_src = imagecreatefromjpeg($wm_path);
            else {
                imagedestroy($image);
                return;
            }

            // Resize Watermark Image based on Size % of Target Width
            $target_wm_w = max(1, $target_w * ($size_pct / 100)); // Ensure non-zero width
            $aspect = imagesy($wm_src) / imagesx($wm_src);
            $target_wm_h = max(1, $target_wm_w * $aspect); // Ensure non-zero height

            $wm_layer = imagecreatetruecolor($target_wm_w, $target_wm_h);
            imagealphablending($wm_layer, false);
            imagesavealpha($wm_layer, true);
            $transparent = imagecolorallocatealpha($wm_layer, 0, 0, 0, 127);
            imagefill($wm_layer, 0, 0, $transparent);

            imagecopyresampled($wm_layer, $wm_src, 0, 0, 0, 0, $target_wm_w, $target_wm_h, imagesx($wm_src), imagesy($wm_src));
            imagedestroy($wm_src);

        } else {
            // Text Watermark
            $text = isset($settings['text']) ? $settings['text'] : 'Pocket Showroom';
            $font = 5;
            $fw = imagefontwidth($font) * strlen($text);
            $fh = imagefontheight($font);

            // Create a temporary transparent image for the text
            $wm_layer = imagecreatetruecolor(max(1, $fw + 10), max(1, $fh + 10));
            imagealphablending($wm_layer, false);
            imagesavealpha($wm_layer, true);
            $transparent = imagecolorallocatealpha($wm_layer, 0, 0, 0, 127);
            imagefill($wm_layer, 0, 0, $transparent);

            // White Text
            $white = imagecolorallocate($wm_layer, 255, 255, 255);
            imagestring($wm_layer, $font, 5, 5, $text, $white);
        }

        // --- Apply Rotation ---
        if ($rotation != 0 && $wm_layer) {
            $rotated = imagerotate($wm_layer, $rotation, imagecolorallocatealpha($wm_layer, 0, 0, 0, 127));
            if ($rotated) {
                imagedestroy($wm_layer);
                $wm_layer = $rotated;
                imagesavealpha($wm_layer, true);
            }
        }

        if (!$wm_layer) {
            imagedestroy($image);
            return;
        }

        $wm_w = imagesx($wm_layer);
        $wm_h = imagesy($wm_layer);

        // --- Calculate Position ---
        $dest_x = 0;
        $dest_y = 0;
        $padding = 20;

        // Horizontal
        if (strpos($position, 'l') !== false)
            $dest_x = $padding; // Left
        elseif (strpos($position, 'r') !== false)
            $dest_x = $target_w - $wm_w - $padding; // Right
        else
            $dest_x = ($target_w / 2) - ($wm_w / 2); // Center

        // Vertical
        if (strpos($position, 't') !== false)
            $dest_y = $padding; // Top
        elseif (strpos($position, 'b') !== false)
            $dest_y = $target_h - $wm_h - $padding; // Bottom
        else
            $dest_y = ($target_h / 2) - ($wm_h / 2); // Middle

        // --- Merge with Opacity ---
        $this->imagecopymerge_alpha($image, $wm_layer, $dest_x, $dest_y, 0, 0, $wm_w, $wm_h, $opacity);

        // Save
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($image, $target_path, 90);
                break;
            case 'image/png':
                imagepng($image, $target_path);
                break;
        }

        imagedestroy($image);
        imagedestroy($wm_layer);
    }

    /**
     * Helper to merge images while preserving alpha transparency
     */
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
    {
        // 安全检查: 确保宽高为正数，防止 GD 库异常
        if ($src_w <= 0 || $src_h <= 0 || $pct <= 0) {
            return;
        }

        // Create a cut resource
        $cut = imagecreatetruecolor($src_w, $src_h);

        // Copy that section of the background to the cut
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);

        // Place the watermark on top of the cut
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);

        // Merge the cut back into the destination with opacity
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);

        imagedestroy($cut);
    }
}
