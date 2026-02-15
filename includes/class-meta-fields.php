<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PS_Meta_Fields
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
        // Implement the "Full UI Takeover" based on the user's request to match full-admin-mock.html

        // 1. Remove standard meta boxes we don't want (we'll render them manually in our layout)
        add_action('add_meta_boxes', [$this, 'remove_standard_meta_boxes'], 99);

        // 2. Hook after the title to render our custom full-page wrapper
        add_action('edit_form_after_title', [$this, 'render_modern_ui_wrapper']);

        // 3. Save logic remains the same
        add_action('save_post', [$this, 'save_meta_box']);
        add_shortcode('ps_data', [$this, 'render_shortcode']);

        // 4. Assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook)
    {
        global $post;
        if ('post.php' !== $hook && 'post-new.php' !== $hook)
            return;
        if ('ps_item' !== get_post_type($post))
            return;

        wp_enqueue_media();
        wp_enqueue_style('ps-admin-css', PS_CORE_URL . 'assets/admin-style.css', [], PS_CORE_VERSION);
        wp_enqueue_script('ps-admin-js', PS_CORE_URL . 'assets/admin-script.js', ['jquery', 'jquery-ui-sortable'], PS_CORE_VERSION, true);
    }

    public function remove_standard_meta_boxes()
    {
        // Remove standard featured image box as we use our Gallery
        remove_meta_box('postimagediv', 'ps_item', 'side');
        // We will also hide the editor and publish box via CSS, as removing them via PHP can break WP saving logic.
    }

    // The Main Render Function matching full-admin-mock.html
    public function render_modern_ui_wrapper($post)
    {
        if ('ps_item' !== get_post_type($post))
            return;

        wp_nonce_field('ps_save_meta_box_data', 'ps_meta_box_nonce');

        // Fetch Data
        $model = get_post_meta($post->ID, '_ps_model', true);
        $material = get_post_meta($post->ID, '_ps_material', true);
        $legacy_size = get_post_meta($post->ID, '_ps_size', true);
        $size_variants = get_post_meta($post->ID, '_ps_size_variants', true);
        if (empty($size_variants) && !empty($legacy_size)) {
            $size_variants = [['label' => 'Standard', 'value' => $legacy_size]];
        } elseif (empty($size_variants)) {
            $size_variants = [['label' => 'Standard', 'value' => '']];
        }
        $dynamic_specs = get_post_meta($post->ID, '_ps_dynamic_specs', true);
        if (empty($dynamic_specs) || !is_array($dynamic_specs))
            $dynamic_specs = [];

        $moq = get_post_meta($post->ID, '_ps_moq', true);
        $loading = get_post_meta($post->ID, '_ps_loading', true);
        $lead_time = get_post_meta($post->ID, '_ps_lead_time', true);
        $list_price = get_post_meta($post->ID, '_ps_list_price', true);
        $gallery_ids = get_post_meta($post->ID, '_ps_gallery_images', true);

        // Status Label
        $status_label = ucfirst($post->post_status);
        if ($status_label === 'Publish')
            $status_label = 'Published';

        ?>
        // Helper to locate template
        $template_path = PS_CORE_PATH . 'templates/meta-boxes.php';
        if (file_exists($template_path)) {
        include $template_path;
        } else {
        echo '<div class="error">
            <p>' . __('Meta box template not found.', 'pocket-showroom') . '</p>
        </div>';
        }
        }
        <?php
    }

    public function save_meta_box($post_id)
    {
        // Security & permissions checks
        if (!isset($_POST['ps_meta_box_nonce']))
            return;
        if (!wp_verify_nonce($_POST['ps_meta_box_nonce'], 'ps_save_meta_box_data'))
            return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return;
        if (!current_user_can('edit_post', $post_id))
            return;

        // Save Basic Fields
        $fields = ['_ps_model', '_ps_material', '_ps_moq', '_ps_loading', '_ps_lead_time', '_ps_list_price'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Save Size Variants
        if (isset($_POST['_ps_size_variants']) && is_array($_POST['_ps_size_variants'])) {
            $size_variants = [];
            foreach ($_POST['_ps_size_variants'] as $variant) {
                if (!empty($variant['label']) || !empty($variant['value'])) {
                    $size_variants[] = [
                        'label' => sanitize_text_field($variant['label']),
                        'value' => sanitize_text_field($variant['value'])
                    ];
                }
            }
            update_post_meta($post_id, '_ps_size_variants', $size_variants);
        } else {
            delete_post_meta($post_id, '_ps_size_variants');
        }

        // Save Dynamic Specs
        if (isset($_POST['_ps_dynamic_specs']) && is_array($_POST['_ps_dynamic_specs'])) {
            $keys = isset($_POST['_ps_dynamic_specs']['key']) && is_array($_POST['_ps_dynamic_specs']['key']) ? $_POST['_ps_dynamic_specs']['key'] : [];
            $vals = isset($_POST['_ps_dynamic_specs']['val']) && is_array($_POST['_ps_dynamic_specs']['val']) ? $_POST['_ps_dynamic_specs']['val'] : [];
            $dynamic_specs = [];

            $count = min(count($keys), count($vals));
            for ($i = 0; $i < $count; $i++) {
                if (!empty($keys[$i])) {
                    $dynamic_specs[] = [
                        'key' => sanitize_text_field($keys[$i]),
                        'val' => sanitize_text_field($vals[$i])
                    ];
                }
            }
            update_post_meta($post_id, '_ps_dynamic_specs', $dynamic_specs);
        } else {
            update_post_meta($post_id, '_ps_dynamic_specs', []);
        }

        // Save Gallery
        if (isset($_POST['_ps_gallery_images'])) {
            $gallery_ids = sanitize_text_field($_POST['_ps_gallery_images']);
            update_post_meta($post_id, '_ps_gallery_images', $gallery_ids);

            if (!empty($gallery_ids)) {
                $ids_array = explode(',', $gallery_ids);
                if (!empty($ids_array[0])) {
                    set_post_thumbnail($post_id, $ids_array[0]);
                }
            }
        }
    }

    public function render_shortcode($atts)
    {
        $atts = shortcode_atts(['key' => 'model'], $atts, 'ps_data');
        global $post;
        if (!$post)
            return '';

        // Whitelist allowed keys to prevent arbitrary meta access
        $allowed_keys = ['model', 'material', 'moq', 'loading', 'lead_time', 'list_price'];
        if (!in_array($atts['key'], $allowed_keys, true)) {
            return '';
        }

        $meta_key = '_ps_' . $atts['key'];
        $value = get_post_meta($post->ID, $meta_key, true);
        return esc_html($value);
    }
}
