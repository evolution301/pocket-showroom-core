<?php
defined('ABSPATH') || exit;
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

    /* Breathing room for the edit page wrapper */
    .ps-edit-page {
        padding: 0 20px 0 40px !important;
        box-sizing: border-box !important;
    }

    /* Force our 2-col grid to work (overrides WP admin leftover float CSS) */
    .ps-edit-page .ps-grid {
        display: grid !important;
        grid-template-columns: 1fr 350px !important;
        gap: 25px !important;
    }

    @media (max-width: 782px) {
        .ps-edit-page .ps-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<div class="ps-edit-page">

    <div class="ps-header">
        <div style="flex:1;">
            <!-- Custom Title Input (Synced via JS) -->
            <input type="text" class="ps-title-input" id="ps-custom-title"
                placeholder="Product Name (e.g. Modern Leather Sofa)"
                value="<?php echo esc_attr($post->post_title); ?>">
        </div>
    </div>

    <div class="ps-grid">
        <!-- Left Column: Visuals & Text -->
        <div class="ps-col-main">



            <!-- Gallery Card -->
            <div class="ps-card">
                <div class="ps-card-header">
                    <h3>
                        <?php _e('Media Gallery', 'pocket-showroom'); ?>
                    </h3>
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
                        <div class="ps-gallery-add ps-add-gallery-btn" id="ps-add-images"
                            style="cursor:pointer; display:flex; flex-direction:column; justify-content:center; align-items:center; border:2px dashed #ccc; border-radius:8px; padding:20px 0; color:#888;">
                            <span class="dashicons dashicons-camera"
                                style="font-size:32px; width:32px; height:32px; margin-bottom:10px;"></span>
                            <span style="font-size:14px; font-weight:600;">Add Media</span>
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
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label class="ps-label" style="margin-bottom:0;">Model No.</label>
                                <label class="ps-switch" style="transform: scale(0.7); margin: 0;">
                                    <input type="checkbox" name="_ps_show_model" value="1" <?php checked('1', $show_model); ?>>
                                    <span class="ps-slider round"></span>
                                </label>
                            </div>
                            <input type="text" class="ps-input" name="_ps_model" value="<?php echo esc_attr($model); ?>"
                                placeholder="e.g. A-101">
                        </div>

                        <div class="ps-form-row">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label class="ps-label" style="margin-bottom:0;">Price (EXW)</label>
                                <label class="ps-switch" style="transform: scale(0.7); margin: 0;">
                                    <input type="checkbox" name="_ps_show_list_price" value="1" <?php checked('1', $show_list_price); ?>>
                                    <span class="ps-slider round"></span>
                                </label>
                            </div>
                            <input type="text" class="ps-input" name="_ps_list_price"
                                value="<?php echo esc_attr($list_price); ?>" placeholder="$">
                        </div>

                        <div class="ps-form-row">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label class="ps-label" style="margin-bottom:0;">Material</label>
                                <label class="ps-switch" style="transform: scale(0.7); margin: 0;">
                                    <input type="checkbox" name="_ps_show_material" value="1" <?php checked('1', $show_material); ?>>
                                    <span class="ps-slider round"></span>
                                </label>
                            </div>
                            <input type="text" class="ps-input" name="_ps_material"
                                value="<?php echo esc_attr($material); ?>" placeholder="e.g. Oak, Fabric, Metal">
                        </div>

                        <div class="ps-form-row">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label class="ps-label" style="margin-bottom:0;">MOQ</label>
                                <label class="ps-switch" style="transform: scale(0.7); margin: 0;">
                                    <input type="checkbox" name="_ps_show_moq" value="1" <?php checked('1', $show_moq); ?>>
                                    <span class="ps-slider round"></span>
                                </label>
                            </div>
                            <input type="text" class="ps-input" name="_ps_moq" value="<?php echo esc_attr($moq); ?>"
                                placeholder="e.g. 10 Sets">
                        </div>

                        <div class="ps-form-row">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label class="ps-label" style="margin-bottom:0;">Loading (40HQ)</label>
                                <label class="ps-switch" style="transform: scale(0.7); margin: 0;">
                                    <input type="checkbox" name="_ps_show_loading" value="1" <?php checked('1', $show_loading); ?>>
                                    <span class="ps-slider round"></span>
                                </label>
                            </div>
                            <input type="text" class="ps-input" name="_ps_loading"
                                value="<?php echo esc_attr($loading); ?>" placeholder="e.g. 45 Sets">
                        </div>

                        <div class="ps-form-row">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                <label class="ps-label" style="margin-bottom:0;">Delivery Time</label>
                                <label class="ps-switch" style="transform: scale(0.7); margin: 0;">
                                    <input type="checkbox" name="_ps_show_lead_time" value="1" <?php checked('1', $show_lead_time); ?>>
                                    <span class="ps-slider round"></span>
                                </label>
                            </div>
                            <input type="text" class="ps-input" name="_ps_lead_time"
                                value="<?php echo esc_attr($lead_time); ?>" placeholder="e.g. 30-45 Days">
                        </div>
                    </div>

                    <!-- Available Sizes: full width -->
                    <div class="ps-form-row" style="margin-top:15px;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                            <label class="ps-label" style="margin-bottom:0;">Available Sizes</label>
                        </div>
                        <div id="ps-size-variants">
                            <?php foreach ($size_variants as $index => $variant): ?>
                                <?php $item_show = isset($variant['show']) ? $variant['show'] : '1'; ?>
                                <div class="ps-size-row" style="display:flex; gap:10px; margin-bottom:10px;">
                                    <input type="text" class="ps-input"
                                        name="_ps_size_variants[<?php echo $index; ?>][label]"
                                        value="<?php echo esc_attr($variant['label']); ?>" placeholder="Variant Name"
                                        style="flex:1;">
                                    <input type="text" class="ps-input"
                                        name="_ps_size_variants[<?php echo $index; ?>][value]"
                                        value="<?php echo esc_attr($variant['value']); ?>" placeholder="Dimensions"
                                        style="flex:2;">
                                    <label class="ps-switch"
                                        style="transform: scale(0.6); margin: 0 5px; align-self:center;">
                                        <input type="checkbox" name="_ps_size_variants[<?php echo $index; ?>][show]"
                                            value="1" <?php checked('1', $item_show); ?>>
                                        <span class="ps-slider round"></span>
                                    </label>
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

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h4 style="margin:0; font-size:14px; color:#1d2327;">Custom Fields</h4>
                    </div>

                    <!-- Dynamic Specs: Full Width Stack -->
                    <div id="ps-dynamic-specs">
                        <?php foreach ($dynamic_specs as $spec): ?>
                            <?php $item_show = isset($spec['show']) ? $spec['show'] : '1'; ?>
                            <div class="ps-spec-row">
                                <input type="text" name="_ps_dynamic_specs[key][]" class="ps-spec-key"
                                    placeholder="Field Name" value="<?php echo esc_attr($spec['key']); ?>">
                                <input type="text" name="_ps_dynamic_specs[val][]" class="ps-spec-val" placeholder="Value"
                                    value="<?php echo esc_attr($spec['val']); ?>">

                                <input type="hidden" name="_ps_dynamic_specs[show][]" class="ps-spec-show-val"
                                    value="<?php echo esc_attr($item_show); ?>">
                                <label class="ps-switch" style="transform: scale(0.6); margin: 0 5px; align-self:center;">
                                    <input type="checkbox" class="ps-spec-show-cb" <?php checked('1', $item_show); ?>>
                                    <span class="ps-slider round"></span>
                                </label>

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
                    wp_editor($post->post_content, 'ps_description_editor', [
                        'media_buttons' => false,
                        'textarea_name' => 'content',
                        'editor_height' => 200,
                        'teeny' => true,
                        'quicktags' => false
                    ]);
                    ?>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="ps-card">
                <div class="ps-card-header">
                    <h3>Publish</h3>
                </div>
                <div class="ps-card-body">
                    <div style="margin-bottom: 15px; font-size: 13px;">
                        <span class="dashicons dashicons-flag" style="color: #888;"></span> Status:
                        <strong><?php echo esc_html($status_label); ?></strong>
                    </div>

                    <div
                        style="display:flex; justify-content:space-between; align-items:center; border-top: 1px solid #eee; padding-top: 15px;">
                        <button type="button" class="ps-btn ps-btn-secondary ps-preview-trigger">Preview</button>
                        <!-- Triggers hidden WP update button -->
                        <button type="button" class="ps-btn ps-btn-primary ps-save-trigger">
                            <?php echo ($post->post_status == 'publish') ? 'Update' : 'Save'; ?>
                        </button>
                    </div>
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
                            $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                            $post_terms = wp_get_post_terms($post->ID, $taxonomy, ['fields' => 'ids']);

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

<!-- Success Toast Notification -->
<?php if (isset($_GET['message']) && in_array($_GET['message'], ['1', '4', '6', '8', '10'])): ?>
    <div id="ps-success-toast"
        style="position:fixed; top:40px; right:40px; background:#4caf50; color:#fff; padding:15px 25px; border-radius:4px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:99999; font-size:16px; font-weight:bold; display:flex; align-items:center; gap:10px;">
        <span class="dashicons dashicons-yes-alt"></span> <?php _e('Update Success!', 'pocket-showroom'); ?>
    </div>
    <script>
        jQuery(document).ready(function ($) {
            setTimeout(function () {
                $('#ps-success-toast').fadeOut(500, function () { $(this).remove(); });
            }, 3000);
        });
    </script>
<?php endif; ?>

<script>
    jQuery(document).ready(function ($) {
        // Sync Title
        $('#ps-custom-title').on('input', function () {
            $('#title').val($(this).val());
        });

        // Sync Publish Button & Add Loading State
        $('.ps-save-trigger').on('click', function (e) {
            e.preventDefault();
            var $btn = $(this);
            // Change text and disable temporarily to prevent double clicks and show loading state
            $btn.html('<span class="dashicons dashicons-update" style="animation: spin 2s linear infinite;"></span> Updating...').prop('disabled', true).css('opacity', '0.8');
            $('#publish').click();
        });

        // Add pure CSS for the spinner animation directly here since it's only needed here
        $('<style>@keyframes spin { 100% { transform: rotate(360deg); } }</style>').appendTo('head');

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