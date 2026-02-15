<?php
declare(strict_types=1);

if (!defined('ABSPATH'))
    exit;

class PS_Settings
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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        add_action('wp_ajax_ps_flush_permalinks', [$this, 'ajax_flush_permalinks']);
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('ps_item_page_ps-settings' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('ps-admin-style', PS_CORE_URL . 'assets/admin-style.css', [], PS_CORE_VERSION);
        wp_enqueue_script('ps-admin-script', PS_CORE_URL . 'assets/admin-script.js', ['jquery'], PS_CORE_VERSION, true);
        wp_localize_script('ps-admin-script', 'ps_admin_vars', [
            'select_images' => __('Select Product Images', 'pocket-showroom'),
            'add_to_gallery' => __('Add to Gallery', 'pocket-showroom'),
            'variant_name_placeholder' => __('Variant Name', 'pocket-showroom'),
            'dimensions_placeholder' => __('Dimensions', 'pocket-showroom'),
            'field_name_placeholder' => __('Field Name', 'pocket-showroom'),
            'value_placeholder' => __('Value', 'pocket-showroom'),
            'explore_now' => __('Explore Now', 'pocket-showroom'),
            'banner_title_fallback' => __('Banner Title', 'pocket-showroom'),
            'banner_desc_fallback' => __('Banner description goes here.', 'pocket-showroom'),
            'select_banner_image' => __('Select Banner Image', 'pocket-showroom'),
            'use_this_image' => __('Use this image', 'pocket-showroom'),
            'no_image' => __('No Image', 'pocket-showroom'),
            'select_watermark_image' => __('Select Watermark Image', 'pocket-showroom'),
            'no_image_parens' => __('(No Image)', 'pocket-showroom'),
        ]);
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'edit.php?post_type=ps_item',
            'Pocket Showroom Settings',
            'Settings',
            'manage_options',
            'ps-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('ps_settings_group', 'ps_enable_watermark');
        register_setting('ps_settings_group', 'ps_watermark_type'); // text, image
        register_setting('ps_settings_group', 'ps_watermark_text');
        register_setting('ps_settings_group', 'ps_watermark_image_id');
        register_setting('ps_settings_group', 'ps_watermark_opacity'); // 0-100
        register_setting('ps_settings_group', 'ps_watermark_size'); // 0-100
        register_setting('ps_settings_group', 'ps_watermark_position'); // tl, tc, tr, ...
        register_setting('ps_settings_group', 'ps_watermark_rotation'); // 0, 90, 45, -45

        // Banner Settings
        register_setting('ps_settings_group', 'ps_banner_image_id');
        register_setting('ps_settings_group', 'ps_banner_title');
        register_setting('ps_settings_group', 'ps_banner_desc');
        register_setting('ps_settings_group', 'ps_banner_button_text');
        register_setting('ps_settings_group', 'ps_banner_button_url');
        register_setting('ps_settings_group', 'ps_banner_overlay_color');
        register_setting('ps_settings_group', 'ps_primary_color');
        register_setting('ps_settings_group', 'ps_button_text_color');
        register_setting('ps_settings_group', 'ps_banner_title_color');
        register_setting('ps_settings_group', 'ps_banner_desc_color');

        // Card Display Settings
        register_setting('ps_settings_group', 'ps_card_aspect_ratio');
    }

    public function ajax_flush_permalinks()
    {
        check_ajax_referer('ps_flush_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        flush_rewrite_rules();
        wp_send_json_success(__('Permalinks flushed successfully.', 'pocket-showroom'));
    }

    public function render_settings_page()
    {
        $defaults = [
            'type' => 'text',
            'text' => 'Pocket Showroom',
            'image_id' => '',
            'opacity' => 60,
            'size' => 20,
            'position' => 'br',
            'rotation' => '0',
            // Banner defaults
            'banner_image_id' => '',
            'banner_title' => 'Pocket Showroom',
            'banner_desc' => 'Discover our latest collection of premium furniture.',
            'banner_overlay_color' => 'rgba(46, 125, 50, 0.4)',
            'primary_color' => 'rgba(0, 124, 186, 1)', // Default Blue
            'button_text_color' => '#ffffff', // Default White
            'banner_title_color' => '#ffffff',
            'banner_desc_color' => 'rgba(255, 255, 255, 0.95)',
            // Card display
            'card_aspect_ratio' => '3/4',
        ];

        $type = get_option('ps_watermark_type', $defaults['type']);
        $text = get_option('ps_watermark_text', $defaults['text']);
        $image_id = get_option('ps_watermark_image_id', $defaults['image_id']);
        $opacity = get_option('ps_watermark_opacity', $defaults['opacity']);
        $size = get_option('ps_watermark_size', $defaults['size']);
        $position = get_option('ps_watermark_position', $defaults['position']);
        $rotation = get_option('ps_watermark_rotation', $defaults['rotation']);

        $image_url = '';
        if ($image_id) {
            $img = wp_get_attachment_image_src($image_id, 'medium');
            if ($img)
                $image_url = $img[0];
        }

        // Banner Data
        $banner_image_id = get_option('ps_banner_image_id', $defaults['banner_image_id']);
        $banner_title = get_option('ps_banner_title', $defaults['banner_title']);
        $banner_desc = get_option('ps_banner_desc', $defaults['banner_desc']);
        $banner_overlay_color = get_option('ps_banner_overlay_color', $defaults['banner_overlay_color']);
        $primary_color = get_option('ps_primary_color', $defaults['primary_color']);
        $button_text_color = get_option('ps_button_text_color', $defaults['button_text_color']);
        $banner_title_color = get_option('ps_banner_title_color', $defaults['banner_title_color']);
        $banner_desc_color = get_option('ps_banner_desc_color', $defaults['banner_desc_color']);

        $banner_image_url = '';
        if ($banner_image_id) {
            $b_img = wp_get_attachment_image_src($banner_image_id, 'large');
            if ($b_img)
                $banner_image_url = $b_img[0];
        }
        // Helper to locate template
        $template_path = PS_CORE_PATH . 'templates/admin-settings.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>' . __('Settings template not found.', 'pocket-showroom') . '</p></div>';
        }
    }
}
