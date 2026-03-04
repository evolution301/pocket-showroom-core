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

        // 5. Quick edit
        add_action('manage_ps_item_posts_columns', [$this, 'add_media_column']);
        add_action('manage_ps_item_posts_custom_column', [$this, 'render_media_column'], 10, 2);
        add_action('quick_edit_custom_box', [$this, 'render_quick_edit_box'], 10, 2);
    }

    public function enqueue_admin_assets($hook)
    {
        $screen = get_current_screen();
        if (!$screen || 'ps_item' !== $screen->post_type) {
            return;
        }
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'], true)) {
            return;
        }

        // 加载共用基础样式 + 产品编辑页专属样式（两者分离，修改互不影响）
        wp_enqueue_style('ps-admin-shared', PS_CORE_URL . 'assets/admin-shared.css', [], PS_CORE_VERSION);
        wp_enqueue_style('ps-admin-edit', PS_CORE_URL . 'assets/admin-edit.css', ['ps-admin-shared'], PS_CORE_VERSION);

        // Required for WP Media Uploader
        wp_enqueue_media();

        wp_enqueue_script('ps-admin-script', PS_CORE_URL . 'assets/admin-script.js', ['jquery', 'jquery-ui-sortable'], PS_CORE_VERSION, true);

        // 只注入产品编辑页需要的变量（Settings 页的变量由 class-settings.php 单独注入）
        wp_localize_script('ps-admin-script', 'ps_admin_vars', [
            'select_images' => __('Select Product Images', 'pocket-showroom'),
            'add_to_gallery' => __('Add to Gallery', 'pocket-showroom'),
            'variant_name_placeholder' => __('Variant Name', 'pocket-showroom'),
            'dimensions_placeholder' => __('Dimensions', 'pocket-showroom'),
            'field_name_placeholder' => __('Field Name', 'pocket-showroom'),
            'value_placeholder' => __('Value', 'pocket-showroom'),
        ]);
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

        // Load Visibility Toggles — use helper to default to '1' for backwards compatibility.
        $show_model = $this->get_toggle_meta($post->ID, '_ps_show_model');
        $show_list_price = $this->get_toggle_meta($post->ID, '_ps_show_list_price');
        $show_material = $this->get_toggle_meta($post->ID, '_ps_show_material');
        $show_moq = $this->get_toggle_meta($post->ID, '_ps_show_moq');
        $show_loading = $this->get_toggle_meta($post->ID, '_ps_show_loading');
        $show_lead_time = $this->get_toggle_meta($post->ID, '_ps_show_lead_time');
        $show_sizes = $this->get_toggle_meta($post->ID, '_ps_show_sizes');
        $show_custom_fields = $this->get_toggle_meta($post->ID, '_ps_show_custom_fields');

        // Status Label
        $status_label = ucfirst($post->post_status);
        if ($status_label === 'Publish')
            $status_label = 'Published';

        // Helper to locate template
        $template_path = PS_CORE_PATH . 'templates/meta-boxes.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error"><p>' . __('Meta box template not found.', 'pocket-showroom') . '</p></div>';
        }
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

        // If it's inline-save (Quick Edit), ONLY update gallery and return early to prevent data loss
        if (isset($_POST['action']) && $_POST['action'] === 'inline-save') {
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
            return;
        }

        // Save Basic Fields & Toggles
        $fields = ['_ps_model', '_ps_material', '_ps_moq', '_ps_loading', '_ps_lead_time', '_ps_list_price'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // NOTE: _ps_show_sizes and _ps_show_custom_fields were previously missing here—
        // they were rendered in the template but never actually saved, so toggling them had no effect.
        $toggle_fields = [
            '_ps_show_model',
            '_ps_show_list_price',
            '_ps_show_material',
            '_ps_show_moq',
            '_ps_show_loading',
            '_ps_show_lead_time',
            '_ps_show_sizes',        // FIX: was missing
            '_ps_show_custom_fields', // FIX: was missing
        ];
        foreach ($toggle_fields as $toggle) {
            $value = isset($_POST[$toggle]) ? '1' : '0';
            update_post_meta($post_id, $toggle, $value);
        }

        // Save Size Variants
        if (isset($_POST['_ps_size_variants']) && is_array($_POST['_ps_size_variants'])) {
            $size_variants = [];
            foreach ($_POST['_ps_size_variants'] as $variant) {
                if (!empty($variant['label']) || !empty($variant['value'])) {
                    $size_variants[] = [
                        'label' => sanitize_text_field($variant['label']),
                        'value' => sanitize_text_field($variant['value']),
                        'show' => isset($variant['show']) ? '1' : '0'
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
            $shows = isset($_POST['_ps_dynamic_specs']['show']) && is_array($_POST['_ps_dynamic_specs']['show']) ? $_POST['_ps_dynamic_specs']['show'] : [];

            $dynamic_specs = [];

            $count = min(count($keys), count($vals));
            for ($i = 0; $i < $count; $i++) {
                if (!empty($keys[$i])) {
                    $dynamic_specs[] = [
                        'key' => sanitize_text_field($keys[$i]),
                        'val' => sanitize_text_field($vals[$i]),
                        'show' => isset($shows[$i]) ? sanitize_text_field($shows[$i]) : '1'
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

    public function add_media_column($columns)
    {
        // Add a new column directly after the title column if possible, otherwise at the end
        $new_columns = [];
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            if ($key === 'title') {
                $new_columns['ps_media'] = __('Media', 'pocket-showroom');
            }
        }
        if (!isset($new_columns['ps_media'])) {
            $new_columns['ps_media'] = __('Media', 'pocket-showroom');
        }
        return $new_columns;
    }

    public function render_media_column($column_name, $post_id)
    {
        if ($column_name === 'ps_media') {
            $gallery_ids = get_post_meta($post_id, '_ps_gallery_images', true);
            $count = empty($gallery_ids) ? 0 : count(explode(',', $gallery_ids));
            echo '<span class="dashicons dashicons-format-gallery" style="vertical-align: middle;"></span> <span style="vertical-align: middle;">' . $count . '</span>';
            echo '<div class="ps-qe-gallery-data" style="display:none;">' . esc_attr($gallery_ids) . '</div>';
        }
    }

    public function render_quick_edit_box($column_name, $post_type)
    {
        // Quick Edit box fires for every column, only render ours once when hitting our column
        if ($column_name !== 'ps_media' || $post_type !== 'ps_item') {
            return;
        }

        wp_nonce_field('ps_save_meta_box_data', 'ps_meta_box_nonce');
        ?>
        <fieldset class="inline-edit-col-left" style="margin-top: 10px; width: 100%; float: none; clear: both;">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php _e('Product Media', 'pocket-showroom'); ?></span>
                    <span class="input-text-wrap" style="display: flex; gap: 10px; align-items: center;">
                        <input type="hidden" name="_ps_gallery_images" id="ps_qe_gallery_images" value="">
                        <button type="button" class="button ps-qe-add-media" style="margin-top: 2px;">
                            <span class="dashicons dashicons-format-gallery"
                                style="vertical-align: middle; margin-top: 3px;"></span>
                            <?php _e('Manage Gallery', 'pocket-showroom'); ?>
                        </button>
                        <span class="ps-qe-image-preview" style="color: #666; font-style: italic;"></span>
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * 快捷读取可见性开关元数据。
     * 如果该产品从未保存过这个字段（老产品迁移场景），默认返回 '1'（显示）。
     *
     * @param int    $post_id  产品 ID。
     * @param string $meta_key 元数据键名，例如 '_ps_show_model'。
     * @return string '1' 表示显示，'0' 表示隐藏。
     */
    private function get_toggle_meta(int $post_id, string $meta_key): string
    {
        $value = get_post_meta($post_id, $meta_key, true);
        return ($value !== '') ? $value : '1';
    }
}
