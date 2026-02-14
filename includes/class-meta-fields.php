<?php

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
        add_action('add_meta_boxes', array($this, 'remove_standard_meta_boxes'), 99);

        // 2. Hook after the title to render our custom full-page wrapper
        add_action('edit_form_after_title', array($this, 'render_modern_ui_wrapper'));

        // 3. Save logic remains the same
        add_action('save_post', array($this, 'save_meta_box'));
        add_shortcode('ps_data', array($this, 'render_shortcode'));

        // 4. Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    public function enqueue_admin_assets($hook)
    {
        global $post;
        if ('post.php' !== $hook && 'post-new.php' !== $hook)
            return;
        if ('ps_item' !== get_post_type($post))
            return;

        wp_enqueue_media();
        wp_enqueue_style('ps-admin-css', PS_CORE_URL . 'assets/admin-style.css', array(), PS_CORE_VERSION);
        wp_enqueue_script('ps-admin-js', PS_CORE_URL . 'assets/admin-script.js', array('jquery', 'jquery-ui-sortable'), PS_CORE_VERSION, true);
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
            $size_variants = array(array('label' => 'Standard', 'value' => $legacy_size));
        } elseif (empty($size_variants)) {
            $size_variants = array(array('label' => 'Standard', 'value' => ''));
        }
        $dynamic_specs = get_post_meta($post->ID, '_ps_dynamic_specs', true);
        if (empty($dynamic_specs) || !is_array($dynamic_specs))
            $dynamic_specs = array();

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
        <!-- Custom Style to Hide WP Elements & Force Full-Width Layout -->
        <style>
            /* ========== HIDE STANDARD WP ELEMENTS ========== */
            #titlediv {
                display: none !important;
            }

            #postdivrich {
                display: none !important;
            }

            #submitdiv {
                display: none !important;
            }

            #postbox-container-1 {
                display: none !important;
            }

            .page-title-action {
                display: none !important;
            }

            h1.wp-heading-inline {
                display: none !important;
            }

            .block-editor-page {
                display: none !important;
            }

            /* ========== FORCE FULL-WIDTH LAYOUT (Override WP Admin CSS) ========== */
            /* Reset the main WP content wrapper */
            #wpbody-content {
                max-width: 100% !important;
                padding-right: 20px !important;
            }

            #poststuff {
                min-width: 0 !important;
                padding-top: 10px !important;
            }

            /* Kill WP's column margin system */
            #post-body {
                margin-right: 0 !important;
                width: 100% !important;
            }

            #post-body.columns-2 {
                margin-right: 0 !important;
            }

            /* CRITICAL: Remove float and width constraints on content area */
            #post-body-content {
                margin-right: 0 !important;
                width: 100% !important;
                float: none !important;
            }

            /* Kill WP postbox container widths */
            #postbox-container-2 {
                float: none !important;
                width: 100% !important;
                margin-right: 0 !important;
            }

            /* Breathing room for the whole editor */
            .ps-modern-ui {
                padding: 0 20px 0 40px !important;
                box-sizing: border-box !important;
            }

            /* Force our grid to work */
            .ps-grid {
                display: grid !important;
                grid-template-columns: 1fr 350px !important;
                gap: 25px !important;
            }

            @media (max-width: 782px) {
                .ps-grid {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>

        <div class="ps-modern-ui">

            <!-- Top Header -->
            <div class="ps-header">
                <div style="flex:1;">
                    <!-- Custom Title Input (Synced via JS) -->
                    <input type="text" class="ps-title-input" id="ps-custom-title"
                        placeholder="Product Name (e.g. Modern Leather Sofa)"
                        value="<?php echo esc_attr($post->post_title); ?>">
                </div>
                <div class="ps-actions">
                    <span style="font-size:12px; color:#888; margin-right:10px;">Status:
                        <?php echo esc_html($status_label); ?></span>
                    <button type="button" class="ps-btn ps-btn-secondary ps-preview-trigger">Preview</button>
                    <!-- Triggers hidden WP update button -->
                    <button type="button" class="ps-btn ps-btn-primary ps-save-trigger">
                        <?php echo ($post->post_status == 'publish') ? 'Update Product' : 'Save Product'; ?>
                    </button>
                </div>
            </div>

            <div class="ps-grid">
                <!-- Left Column: Visuals & Text -->
                <div class="ps-col-main">

                    <!-- Gallery Card -->
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3><?php _e('Media Gallery', 'pocket-showroom'); ?></h3>
                            <button type="button" class="ps-btn ps-btn-secondary" id="ps-bulk-upload-btn"
                                style="padding:4px 10px; font-size:11px;">Bulk Upload</button>
                        </div>
                        <div class="ps-card-body">
                            <div id="ps-gallery-wrapper" class="ps-gallery-grid">
                                <?php
                                if (!empty($gallery_ids)) {
                                    $ids = explode(',', $gallery_ids);
                                    foreach ($ids as $id) {
                                        $img = wp_get_attachment_image_src($id, 'thumbnail');
                                        if ($img) {
                                            echo '<div class="ps-gallery-item" data-id="' . esc_attr($id) . '"><img src="' . esc_url($img[0]) . '"><span class="remove">×</span></div>';
                                        }
                                    }
                                }
                                ?>
                                <div class="ps-gallery-add" id="ps-add-images">
                                    <span class="dashicons dashicons-camera"
                                        style="font-size:24px; width:24px; height:24px; margin-bottom:5px;"></span>
                                    <span style="font-size:12px;">Add Media</span>
                                </div>
                            </div>
                            <input type="hidden" name="_ps_gallery_images" id="_ps_gallery_images"
                                value="<?php echo esc_attr($gallery_ids); ?>">
                            <p style="font-size:12px; color:#888; margin-top:15px;">
                                * Drag images to reorder. The first image will be the Cover Image.
                            </p>
                        </div>
                    </div>

                    <!-- Item Specifications Card (moved to left column, 2-col grid layout) -->
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3>Item Specifications</h3>
                        </div>
                        <div class="ps-card-body">

                            <div class="ps-notice-tip">
                                <span class="dashicons dashicons-info" style="color:#0085ba"></span>
                                <?php _e('Empty fields will not be displayed on the frontend.', 'pocket-showroom'); ?>
                            </div>

                            <!-- Standard Fields: 2-column grid -->
                            <div class="ps-specs-grid">
                                <div class="ps-form-row">
                                    <label class="ps-label">Model No.</label>
                                    <input type="text" class="ps-input" name="_ps_model" value="<?php echo esc_attr($model); ?>"
                                        placeholder="e.g. A-101">
                                </div>

                                <div class="ps-form-row">
                                    <label class="ps-label">Price (EXW)</label>
                                    <input type="text" class="ps-input" name="_ps_list_price"
                                        value="<?php echo esc_attr($list_price); ?>" placeholder="$">
                                </div>

                                <div class="ps-form-row">
                                    <label class="ps-label">Material</label>
                                    <input type="text" class="ps-input" name="_ps_material"
                                        value="<?php echo esc_attr($material); ?>" placeholder="e.g. Oak, Fabric, Metal">
                                </div>

                                <div class="ps-form-row">
                                    <label class="ps-label">MOQ</label>
                                    <input type="text" class="ps-input" name="_ps_moq" value="<?php echo esc_attr($moq); ?>"
                                        placeholder="e.g. 10 Sets">
                                </div>

                                <div class="ps-form-row">
                                    <label class="ps-label">Loading (40HQ)</label>
                                    <input type="text" class="ps-input" name="_ps_loading"
                                        value="<?php echo esc_attr($loading); ?>" placeholder="e.g. 45 Sets">
                                </div>

                                <div class="ps-form-row">
                                    <label class="ps-label">Delivery Time</label>
                                    <input type="text" class="ps-input" name="_ps_lead_time"
                                        value="<?php echo esc_attr($lead_time); ?>" placeholder="e.g. 30-45 Days">
                                </div>
                            </div>

                            <!-- Available Sizes: full width -->
                            <div class="ps-form-row" style="margin-top:15px;">
                                <label class="ps-label">Available Sizes</label>
                                <div id="ps-size-variants">
                                    <?php foreach ($size_variants as $index => $variant): ?>
                                        <div class="ps-size-row" style="display:flex; gap:10px; margin-bottom:10px;">
                                            <input type="text" class="ps-input"
                                                name="_ps_size_variants[<?php echo $index; ?>][label]"
                                                value="<?php echo esc_attr($variant['label']); ?>" placeholder="Variant Name"
                                                style="flex:1;">
                                            <input type="text" class="ps-input"
                                                name="_ps_size_variants[<?php echo $index; ?>][value]"
                                                value="<?php echo esc_attr($variant['value']); ?>" placeholder="Dimensions"
                                                style="flex:2;">
                                            <span class="dashicons dashicons-move ps-sort-handle"
                                                style="cursor:move; color:#ccc; align-self:center;"></span>
                                            <button type="button" class="ps-remove-btn"
                                                style="color:red; background:none; border:none; cursor:pointer;">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" id="ps-add-size" class="ps-btn ps-btn-secondary"
                                    style="margin-top:5px; font-size:12px;">+ Add Size Variant</button>
                            </div>

                            <hr style="border:0; border-top:1px solid #eee; margin: 15px 0;">

                            <!-- Dynamic Specs: 2-column grid -->
                            <div id="ps-dynamic-specs" class="ps-specs-grid">
                                <?php foreach ($dynamic_specs as $spec): ?>
                                    <div class="ps-spec-row">
                                        <input type="text" name="_ps_dynamic_specs[key][]" class="ps-spec-key"
                                            placeholder="Field Name" value="<?php echo esc_attr($spec['key']); ?>">
                                        <input type="text" name="_ps_dynamic_specs[val][]" class="ps-spec-val" placeholder="Value"
                                            value="<?php echo esc_attr($spec['val']); ?>">
                                        <span class="dashicons dashicons-move ps-sort-handle"
                                            style="cursor:move; color:#ccc; align-self:center;"></span>
                                        <button type="button" class="ps-remove-btn">×</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" id="ps-add-spec" class="ps-btn ps-btn-secondary"
                                style="width:100%; margin-top:10px;">
                                + Add Custom Field
                            </button>

                        </div>
                    </div>

                </div>

                <!-- Right Column: Description & Organization -->
                <div class="ps-col-side">

                    <!-- Description Card (moved to right column, compact) -->
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3>Description</h3>
                        </div>
                        <div class="ps-card-body">
                            <?php
                            wp_editor($post->post_content, 'ps_description_editor', array(
                                'media_buttons' => false,
                                'textarea_name' => 'content',
                                'editor_height' => 200,
                                'teeny' => true,
                                'quicktags' => false
                            ));
                            ?>
                        </div>
                    </div>

                    <!-- Organization Card -->
                    <div class="ps-card">
                        <div class="ps-card-header">
                            <h3>Organization</h3>
                        </div>
                        <div class="ps-card-body">
                            <!-- Collections Taxonomy -->
                            <div class="ps-form-row">
                                <label class="ps-label">Collection</label>
                                <div
                                    style="max-height: 150px; overflow-y: auto; border: 1px solid #dcdcde; padding: 10px; border-radius: 4px;">
                                    <?php
                                    // Custom Taxonomy Checklist
                                    $taxonomy = 'ps_category';
                                    $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
                                    $post_terms = wp_get_post_terms($post->ID, $taxonomy, array('fields' => 'ids'));

                                    if (!empty($terms) && !is_wp_error($terms)) {
                                        foreach ($terms as $term) {
                                            $checked = in_array($term->term_id, $post_terms) ? 'checked' : '';
                                            echo '<label style="display:block; margin-bottom:5px;">';
                                            echo '<input type="checkbox" name="tax_input[' . $taxonomy . '][]" value="' . esc_attr($term->term_id) . '" ' . $checked . '> ' . esc_html($term->name);
                                            echo '</label>';
                                        }
                                    } else {
                                        echo '<p style="font-size:12px; color:#888;">No collections found.</p>';
                                    }
                                    ?>
                                </div>
                                <input type="hidden" name="tax_input[<?php echo $taxonomy; ?>][]" value="0">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Sync Title
                $('#ps-custom-title').on('input', function () {
                    $('#title').val($(this).val());
                });

                // Sync Publish Button
                $('.ps-save-trigger').on('click', function () {
                    $('#publish').click();
                });

                // Sync Preview Button
                $('.ps-preview-trigger').on('click', function () {
                    $('#post-preview').click();
                });

                // Bulk Upload Trigger
                $('#ps-bulk-upload-btn').on('click', function () {
                    $('#ps-add-images').click();
                });
            });
        </script>
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
        $fields = array('_ps_model', '_ps_material', '_ps_moq', '_ps_loading', '_ps_lead_time', '_ps_list_price');
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        // Save Size Variants
        if (isset($_POST['_ps_size_variants']) && is_array($_POST['_ps_size_variants'])) {
            $size_variants = array();
            foreach ($_POST['_ps_size_variants'] as $variant) {
                if (!empty($variant['label']) || !empty($variant['value'])) {
                    $size_variants[] = array(
                        'label' => sanitize_text_field($variant['label']),
                        'value' => sanitize_text_field($variant['value'])
                    );
                }
            }
            update_post_meta($post_id, '_ps_size_variants', $size_variants);
        } else {
            delete_post_meta($post_id, '_ps_size_variants');
        }

        // Save Dynamic Specs
        if (isset($_POST['_ps_dynamic_specs']) && is_array($_POST['_ps_dynamic_specs'])) {
            $keys = isset($_POST['_ps_dynamic_specs']['key']) && is_array($_POST['_ps_dynamic_specs']['key']) ? $_POST['_ps_dynamic_specs']['key'] : array();
            $vals = isset($_POST['_ps_dynamic_specs']['val']) && is_array($_POST['_ps_dynamic_specs']['val']) ? $_POST['_ps_dynamic_specs']['val'] : array();
            $dynamic_specs = array();

            $count = min(count($keys), count($vals));
            for ($i = 0; $i < $count; $i++) {
                if (!empty($keys[$i])) {
                    $dynamic_specs[] = array(
                        'key' => sanitize_text_field($keys[$i]),
                        'val' => sanitize_text_field($vals[$i])
                    );
                }
            }
            update_post_meta($post_id, '_ps_dynamic_specs', $dynamic_specs);
        } else {
            update_post_meta($post_id, '_ps_dynamic_specs', array());
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
        $atts = shortcode_atts(array('key' => 'model'), $atts, 'ps_data');
        global $post;
        if (!$post)
            return '';

        // Whitelist allowed keys to prevent arbitrary meta access
        $allowed_keys = array('model', 'material', 'moq', 'loading', 'lead_time', 'list_price');
        if (!in_array($atts['key'], $allowed_keys, true)) {
            return '';
        }

        $meta_key = '_ps_' . $atts['key'];
        $value = get_post_meta($post->ID, $meta_key, true);
        return esc_html($value);
    }
}
