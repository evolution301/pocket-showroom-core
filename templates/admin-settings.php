<?php
defined('ABSPATH') || exit;
?>
<div class="ps-settings-page wrap">
    <div class="ps-header">
        <div class="ps-title-section">
            <h1 class="wp-heading-inline" style="font-size: 24px; font-weight: 600;">
                <?php _e('Pocket Showroom Settings', 'pocket-showroom'); ?>
            </h1>
        </div>
    </div>

    <form method="post" action="options.php" id="ps-settings-form">
        <?php settings_fields('ps_settings_group'); ?>
        <?php do_settings_sections('ps_settings_group'); ?>

        <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <a href="#ps-tab-watermark" class="nav-tab nav-tab-active">
                <?php _e('Watermark', 'pocket-showroom'); ?>
            </a>
            <a href="#ps-tab-banner" class="nav-tab">
                <?php _e('Frontend Banner', 'pocket-showroom'); ?>
            </a>
            <a href="#ps-tab-shortcodes" class="nav-tab">
                <?php _e('Shortcodes', 'pocket-showroom'); ?>
            </a>
            <a href="#ps-tab-tools" class="nav-tab">
                <?php _e('Troubleshooting', 'pocket-showroom'); ?>
            </a>
        </h2>

        <div class="ps-tab-content" id="ps-tab-tools" style="display:none;">
            <div class="ps-card" style="padding: 30px; max-width: 800px;">
                <h3>
                    <?php _e('API Diagnostics', 'pocket-showroom'); ?>
                </h3>
                <p>
                    <?php _e('If your Mini Program shows "Network Error", check the following:', 'pocket-showroom'); ?>
                </p>

                <div
                    style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:4px; margin-bottom:20px;">
                    <p><strong>1. API Endpoint URL:</strong></p>
                    <code style="background:#f0f0f1; padding:5px; display:block; margin:5px 0; word-break:break-all;">
                                                <?php echo esc_url(rest_url(PS_REST_API::API_NAMESPACE . '/ping')); ?>
                                            </code>
                    <p class="description">
                        <?php _e('Copy this URL and try to open it in your browser. If it returns JSON data, the API is working.', 'pocket-showroom'); ?>
                    </p>
                </div>

                <div
                    style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:4px; margin-bottom:20px;">
                    <p><strong>2. Test Connection (JS):</strong></p>
                    <button type="button" class="button" id="ps-test-api-btn">
                        <?php _e('Test API Connectivity', 'pocket-showroom'); ?>
                    </button>
                    <div id="ps-api-result" style="margin-top:10px; padding:10px; display:none;"></div>
                    <script>
                        jQuery(document).ready(function ($) {
                            $('#ps-test-api-btn').click(function () {
                                var $res = $('#ps-api-result');
                                $res.show().html('Checking...');
                                $.ajax({
                                    url: '<?php echo esc_url_raw(rest_url(PS_REST_API::API_NAMESPACE . '/ping')); ?>',
                                    method: 'GET',
                                    success: function (data) {
                                        $res.html('<div style="color:green; font-weight:bold;">✅ Success! API is reachable.</div><pre style="background:#eee; padding:5px; margin-top:5px;">' + JSON.stringify(data) + '</pre>');
                                    },
                                    error: function (xhr, status, error) {
                                        $res.html('<div style="color:red; font-weight:bold;">❌ Error: ' + status + ' ' + error + '</div><p>HTTP Status: ' + xhr.status + '</p><p>Response: ' + xhr.responseText + '</p>');
                                    }
                                });
                            });
                        });
                    </script>
                </div>

                <div
                    style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:4px; margin-bottom:20px;">
                    <p><strong>3. Flush Permalinks:</strong></p>
                    <p class="description">
                        <?php _e('If you see a 404 error above, try flushing the permalinks.', 'pocket-showroom'); ?>
                    </p>
                    <div style="margin-top:10px;">
                        <button type="button" class="button" id="ps-flush-btn">
                            <?php _e('Flush Permalinks', 'pocket-showroom'); ?>
                        </button>
                        <span id="ps-flush-msg" style="margin-left:10px; font-weight:600;"></span>
                    </div>
                    <script>
                        jQuery(document).ready(function ($) {
                            $('#ps-flush-btn').click(function () {
                                var $btn = $(this);
                                $btn.prop('disabled', true).text('<?php _e('Flushing...', 'pocket-showroom'); ?>');
                                $.post(ajaxurl, {
                                    action: 'ps_flush_permalinks',
                                    nonce: '<?php echo wp_create_nonce('ps_flush_nonce'); ?>'
                                }, function (res) {
                                    if (res.success) {
                                        $('#ps-flush-msg').css('color', 'green').text(res.data);
                                    } else {
                                        $('#ps-flush-msg').css('color', 'red').text('Error');
                                    }
                                    $btn.prop('disabled', false).text('<?php _e('Flush Permalinks', 'pocket-showroom'); ?>');
                                });
                            });
                        });
                    </script>
                </div>
            </div>
        </div>

        <div class="ps-tab-content" id="ps-tab-shortcodes" style="display:none;">
            <div class="ps-card" style="padding: 30px; max-width: 800px;">
                <h3>
                    <?php _e('How to display the Showroom', 'pocket-showroom'); ?>
                </h3>
                <p>
                    <?php _e('To display your product catalog on any page, simply copy and paste the following shortcode:', 'pocket-showroom'); ?>
                </p>
                <code
                    style="background:#f0f0f1; padding:10px; display:block; margin:10px 0; font-size:14px; border-radius: 4px;">[pocket_showroom]</code>
                <p><strong>
                        <?php _e('Options:', 'pocket-showroom'); ?>
                    </strong></p>
                <ul style="list-style:disc; margin-left:20px; color: #646970;">
                    <li><code>posts_per_page="12"</code> -
                        <?php _e('Control how many items show per page.', 'pocket-showroom'); ?>
                    </li>
                </ul>

                <hr style="margin:30px 0; border: 0; border-top: 1px solid #eee;">

                <h3>
                    <?php _e('How to display Single Data Fields', 'pocket-showroom'); ?>
                </h3>
                <p>
                    <?php _e('If you want to display specific product data (like Model No. or Price) in a custom layout or Elementor/Divi text module, use:', 'pocket-showroom'); ?>
                </p>
                <code
                    style="background:#f0f0f1; padding:10px; display:block; margin:10px 0; font-size:14px; border-radius: 4px;">[ps_data key="model"]</code>
                <p><strong>
                        <?php _e('Available Keys:', 'pocket-showroom'); ?>
                    </strong></p>
                <ul style="list-style:disc; margin-left:20px; color: #646970;">
                    <li><code>model</code> -
                        <?php _e('Model Number', 'pocket-showroom'); ?>
                    </li>
                    <li><code>material</code> -
                        <?php _e('Material', 'pocket-showroom'); ?>
                    </li>
                    <li><code>moq</code> -
                        <?php _e('Minimum Order Quantity', 'pocket-showroom'); ?>
                    </li>
                    <li><code>loading</code> -
                        <?php _e('Loading Quantity', 'pocket-showroom'); ?>
                    </li>
                    <li><code>lead_time</code> -
                        <?php _e('Delivery Time', 'pocket-showroom'); ?>
                    </li>
                    <li><code>list_price</code> -
                        <?php _e('EXW Price', 'pocket-showroom'); ?>
                    </li>
                </ul>
            </div>
        </div>

        <div class="ps-tab-content" id="ps-tab-banner" style="display:none;">

            <!-- Zone 1: Main settings — 2-column card grid -->
            <div class="ps-banner-zone-top">

                <!-- Card A: Banner Image -->
                <div class="ps-banner-card ps-banner-card--image">
                    <div class="ps-banner-card-label">
                        <span class="ps-banner-card-icon dashicons dashicons-format-image"></span>
                        <?php _e('Banner Image', 'pocket-showroom'); ?>
                    </div>
                    <input type="hidden" name="ps_banner_image_id" id="ps_banner_image_id"
                        value="<?php echo esc_attr($banner_image_id); ?>">

                    <!-- Preview Slot -->
                    <div class="ps-banner-img-preview-slot ps-image-preview-wrapper">
                        <?php if ($banner_image_url): ?>
                            <img src="<?php echo esc_url($banner_image_url); ?>" alt="Banner">
                        <?php else: ?>
                            <div class="ps-banner-img-empty">
                                <span class="dashicons dashicons-camera-alt"></span>
                                <p><?php _e('No image selected', 'pocket-showroom'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="ps-banner-img-actions">
                        <button type="button" class="ps-banner-img-btn" id="ps_banner_image_btn">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Select Image', 'pocket-showroom'); ?>
                        </button>
                        <button type="button" class="ps-banner-img-btn ps-banner-img-btn--remove"
                            id="ps_remove_banner_image" style="<?php echo $banner_image_id ? '' : 'display:none;'; ?>">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Remove', 'pocket-showroom'); ?>
                        </button>
                    </div>
                </div>

                <!-- Card B: Text Content -->
                <div class="ps-banner-card ps-banner-card--text">
                    <div class="ps-banner-card-label">
                        <span class="ps-banner-card-icon dashicons dashicons-editor-textcolor"></span>
                        <?php _e('Text Content', 'pocket-showroom'); ?>
                    </div>

                    <div class="ps-banner-field">
                        <label
                            class="ps-banner-field-label"><?php _e('Banner Title (H1)', 'pocket-showroom'); ?></label>
                        <input type="text" name="ps_banner_title" id="ps_banner_title"
                            value="<?php echo esc_attr($banner_title); ?>"
                            class="ps-banner-input ps-banner-input--title"
                            placeholder="<?php _e('Your Brand Showroom', 'pocket-showroom'); ?>" />
                    </div>

                    <div class="ps-banner-field ps-banner-field--grow">
                        <label
                            class="ps-banner-field-label"><?php _e('Banner Description', 'pocket-showroom'); ?></label>
                        <textarea name="ps_banner_desc" id="ps_banner_desc" class="ps-banner-input ps-banner-textarea"
                            rows="5"
                            placeholder="<?php _e('Describe your collection in a sentence...', 'pocket-showroom'); ?>"><?php echo esc_textarea($banner_desc); ?></textarea>
                    </div>

                    <div class="ps-banner-field-row">
                        <div class="ps-banner-field">
                            <label class="ps-banner-field-label"><?php _e('Button Text', 'pocket-showroom'); ?></label>
                            <input type="text" name="ps_banner_button_text" id="ps_banner_button_text"
                                value="<?php echo esc_attr(get_option('ps_banner_button_text', 'Explore Now')); ?>"
                                class="ps-banner-input" />
                        </div>
                        <div class="ps-banner-field">
                            <label class="ps-banner-field-label"><?php _e('Button URL', 'pocket-showroom'); ?></label>
                            <input type="url" name="ps_banner_button_url" id="ps_banner_button_url"
                                value="<?php echo esc_attr(get_option('ps_banner_button_url', '')); ?>"
                                class="ps-banner-input" placeholder="https://..." />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zone 2: Colors + Product Card Display -->
            <div class="ps-banner-zone-middle">

                <!-- Theme Colors -->
                <div class="ps-banner-zone-middle-section ps-banner-zone-middle-section--colors">
                    <div class="ps-banner-section-title">
                        <span class="dashicons dashicons-art"></span>
                        <?php _e('Theme Colors', 'pocket-showroom'); ?>
                    </div>
                    <div class="ps-banner-colors-row">
                        <div class="ps-banner-color-chip">
                            <input type="hidden" name="ps_banner_overlay_color" id="ps_banner_overlay_color"
                                value="<?php echo esc_attr($banner_overlay_color); ?>">
                            <div class="ps-divi-picker" id="ps-picker-overlay"></div>
                            <span class="ps-banner-chip-label"><?php _e('Overlay', 'pocket-showroom'); ?></span>
                        </div>
                        <div class="ps-banner-color-chip">
                            <input type="hidden" name="ps_primary_color" id="ps_primary_color"
                                value="<?php echo esc_attr($primary_color); ?>">
                            <div class="ps-divi-picker" id="ps-picker-primary"></div>
                            <span class="ps-banner-chip-label"><?php _e('Primary', 'pocket-showroom'); ?></span>
                        </div>
                        <div class="ps-banner-color-chip">
                            <input type="hidden" name="ps_button_text_color" id="ps_button_text_color"
                                value="<?php echo esc_attr($button_text_color); ?>">
                            <div class="ps-divi-picker" id="ps-picker-button-text"></div>
                            <span class="ps-banner-chip-label"><?php _e('Btn Text', 'pocket-showroom'); ?></span>
                        </div>
                        <div class="ps-banner-color-chip">
                            <input type="hidden" name="ps_banner_title_color" id="ps_banner_title_color"
                                value="<?php echo esc_attr($banner_title_color); ?>">
                            <div class="ps-divi-picker" id="ps-picker-title"></div>
                            <span class="ps-banner-chip-label"><?php _e('Title', 'pocket-showroom'); ?></span>
                        </div>
                        <div class="ps-banner-color-chip">
                            <input type="hidden" name="ps_banner_desc_color" id="ps_banner_desc_color"
                                value="<?php echo esc_attr($banner_desc_color); ?>">
                            <div class="ps-divi-picker" id="ps-picker-desc"></div>
                            <span class="ps-banner-chip-label"><?php _e('Desc', 'pocket-showroom'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="ps-banner-zone-divider"></div>

                <!-- Product Card Display -->
                <div class="ps-banner-zone-middle-section">
                    <div class="ps-banner-section-title">
                        <span class="dashicons dashicons-grid-view"></span>
                        <?php _e('Product Card', 'pocket-showroom'); ?>
                    </div>
                    <div class="ps-banner-field">
                        <label
                            class="ps-banner-field-label"><?php _e('Image Aspect Ratio', 'pocket-showroom'); ?></label>
                        <select name="ps_card_aspect_ratio" id="ps_card_aspect_ratio"
                            class="ps-banner-input ps-banner-select">
                            <?php
                            $card_ratio = get_option('ps_card_aspect_ratio', $defaults['card_aspect_ratio']);
                            $ratio_options = [
                                '1/1' => __('1:1 — Square', 'pocket-showroom'),
                                '3/4' => __('3:4 — Portrait (Recommended)', 'pocket-showroom'),
                                '4/3' => __('4:3 — Landscape', 'pocket-showroom'),
                                '16/9' => __('16:9 — Widescreen', 'pocket-showroom'),
                                '9/16' => __('9:16 — Tall Portrait', 'pocket-showroom'),
                                'auto' => __('Auto — Original Ratio', 'pocket-showroom'),
                            ];
                            foreach ($ratio_options as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($card_ratio, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                        <p class="ps-banner-field-hint">
                            <?php _e('Images are always fully visible — no cropping.', 'pocket-showroom'); ?>
                        </p>
                    </div>

                    <div class="ps-banner-field" style="margin-top: 20px;">
                        <label class="ps-banner-field-label">
                            <?php _e('CTA Button Size', 'pocket-showroom'); ?>
                            (<span
                                id="ps-cta-scale-val"><?php echo esc_html(floatval($banner_cta_scale) * 100); ?></span>%)
                        </label>
                        <input type="range" name="ps_banner_cta_scale" id="ps_banner_cta_scale" class="ps-banner-input"
                            min="0.8" max="2.0" step="0.1" value="<?php echo esc_attr($banner_cta_scale); ?>"
                            style="width: 100%;">
                    </div>

                    <div class="ps-banner-field" style="margin-top: 20px;">
                        <label class="ps-banner-field-label">
                            <?php _e('Share Button Size', 'pocket-showroom'); ?>
                            (<span
                                id="ps-share-scale-val"><?php echo esc_html(floatval($banner_share_scale) * 100); ?></span>%)
                        </label>
                        <input type="range" name="ps_banner_share_scale" id="ps_banner_share_scale"
                            class="ps-banner-input" min="0.8" max="2.0" step="0.1"
                            value="<?php echo esc_attr($banner_share_scale); ?>" style="width: 100%;">
                    </div>
                </div>

            </div>

            <!-- Zone 3: Live Preview (Full Width) -->
            <div class="ps-banner-zone-preview">
                <div class="ps-banner-preview-header">
                    <span class="ps-banner-preview-dot"></span>
                    <span class="ps-banner-preview-dot"></span>
                    <span class="ps-banner-preview-dot"></span>
                    <span class="ps-banner-preview-label"><?php _e('Live Preview', 'pocket-showroom'); ?></span>
                </div>
                <div id="ps-live-preview-container"
                    style="position:relative; width:100%; height:320px; background-size:cover; background-position:center; display:flex; align-items:center; justify-content:center; text-align:center; color:#fff; <?php echo $banner_image_url ? 'background-image:url(' . esc_url($banner_image_url) . ');' : 'background-image:url(' . PS_CORE_URL . 'assets/placeholder-furniture.jpg);'; ?>">
                    <div id="ps-banner-overlay-layer"
                        style="position:absolute; top:0; left:0; right:0; bottom:0; background-color: <?php echo esc_attr($banner_overlay_color); ?>;">
                    </div>
                    <div style="position:relative; z-index:2; padding:20px; max-width:600px;">
                        <h1 id="ps-preview-banner-title"
                            style="margin:0 0 16px; font-size:32px; font-weight:700; letter-spacing:-0.5px; text-shadow:0 2px 4px rgba(0,0,0,0.3); color:<?php echo esc_attr($banner_title_color); ?>;">
                            <?php echo esc_html($banner_title); ?>
                        </h1>
                        <div id="ps-preview-banner-desc"
                            style="font-size:16px; opacity:0.9; color:<?php echo esc_attr($banner_desc_color); ?>; max-width: 500px; margin: 0 auto 24px auto; white-space: pre-wrap; line-height: 1.6;">
                            <?php echo wpautop(esc_html($banner_desc)); ?>
                        </div>
                        <div
                            style="display:flex; align-items:center; justify-content:center; gap:16px; flex-wrap:wrap;">
                            <span class="ps-preview-btn" id="ps-preview-banner-btn"
                                style="display:inline-flex; align-items:center; justify-content:center; padding: calc(12px * var(--ps-cta-scale, 1)) calc(28px * var(--ps-cta-scale, 1)); background-color: <?php echo esc_attr($primary_color); ?>; color:<?php echo esc_attr($button_text_color); ?>; border-radius:4px; font-size:calc(14px * var(--ps-cta-scale, 1)); font-weight:600; letter-spacing:0.3px; --ps-cta-scale: <?php echo esc_attr($banner_cta_scale); ?>;">
                                <?php echo esc_html(get_option('ps_banner_button_text', 'Explore Now')); ?>
                            </span>

                            <span class="ps-preview-btn-share" id="ps-preview-share-btn"
                                style="display:inline-flex; align-items:center; justify-content:center; gap:8px; padding: calc(10px * var(--ps-share-scale, 1)) calc(22px * var(--ps-share-scale, 1)); background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius:30px; color:#fff; font-size:calc(13px * var(--ps-share-scale, 1)); font-weight:500; vertical-align:middle; --ps-share-scale: <?php echo esc_attr($banner_share_scale); ?>;">
                                <svg viewBox="0 0 24 24"
                                    style="width:calc(16px * var(--ps-share-scale, 1)); height:calc(16px * var(--ps-share-scale, 1)); fill:#fff;">
                                    <path
                                        d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z" />
                                </svg>
                                Share
                            </span>
                        </div>
                    </div>
                    <div
                        style="position:absolute; bottom:20px; right:20px; background:rgba(255,255,255,0.12); backdrop-filter:blur(10px); padding:12px 16px; border-radius:8px; border:1px solid rgba(255,255,255,0.2);">
                        <span style="display:block; font-size:24px; font-weight:800; line-height:1;">12</span>
                        <span
                            style="font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.8;"><?php _e('Products Ready', 'pocket-showroom'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="ps-banner-save-row">
                <?php submit_button(__('Save Changes', 'pocket-showroom'), 'primary ps-btn ps-btn-primary', 'submit', false); ?>
            </div>
        </div>

        <div class="ps-tab-content" id="ps-tab-watermark" style="">
            <div class="ps-settings-layout-3col">
                <!-- Col 1: Controls -->
                <div class="ps-col-inputs">
                    <h3>
                        <?php _e('Watermark Configuration', 'pocket-showroom'); ?>
                    </h3>

                    <div class="ps-form-group">
                        <label>
                            <?php _e('Enable Watermarking', 'pocket-showroom'); ?>
                        </label>
                        <label class="ps-switch">
                            <input type="checkbox" name="ps_enable_watermark" value="1" <?php checked(1, get_option('ps_enable_watermark'), true); ?> />
                            <span class="ps-slider round"></span>
                        </label>
                        <p class="description">
                            <?php _e('If enabled, the watermark will be permanently merged into new uploads.', 'pocket-showroom'); ?>
                        </p>
                    </div>

                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">

                    <div class="ps-form-group">
                        <label>
                            <?php _e('Watermark Type', 'pocket-showroom'); ?>
                        </label>
                        <div class="ps-radio-group">
                            <label><input type="radio" name="ps_watermark_type" value="text" <?php checked('text', $type); ?>>
                                <?php _e('Text', 'pocket-showroom'); ?>
                            </label>
                            <label><input type="radio" name="ps_watermark_type" value="image" <?php checked('image', $type); ?>>
                                <?php _e('Image', 'pocket-showroom'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="ps-form-group ps-type-text"
                        style="<?php echo $type === 'image' ? 'display:none;' : ''; ?>">
                        <label>
                            <?php _e('Watermark Text', 'pocket-showroom'); ?>
                        </label>
                        <input type="text" name="ps_watermark_text" id="ps_watermark_text"
                            value="<?php echo esc_attr($text); ?>" class="regular-text ps-input" />
                    </div>

                    <div class="ps-form-group ps-type-image"
                        style="<?php echo $type === 'text' ? 'display:none;' : ''; ?>">
                        <label>
                            <?php _e('Watermark Image', 'pocket-showroom'); ?>
                        </label>
                        <div class="ps-image-upload">
                            <input type="hidden" name="ps_watermark_image_id" id="ps_watermark_image_id"
                                value="<?php echo esc_attr($image_id); ?>">
                            <div id="ps-watermark-image-preview" style="margin-bottom: 10px;">
                                <?php if ($image_url): ?>
                                    <img src="<?php echo esc_url($image_url); ?>"
                                        style="max-width: 100px; border-radius: 4px;">
                                <?php endif; ?>
                            </div>
                            <div class="ps-actions">
                                <button type="button" class="ps-btn ps-btn-secondary" id="ps-upload-watermark">
                                    <?php _e('Select Image', 'pocket-showroom'); ?>
                                </button>
                                <button type="button" class="ps-btn ps-btn-secondary" id="ps-remove-watermark"
                                    style="<?php echo $image_id ? '' : 'display:none;'; ?> color: #d63638; border-color: #d63638;">
                                    <?php _e('Remove', 'pocket-showroom'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="ps-form-group">
                        <label>
                            <?php _e('Opacity', 'pocket-showroom'); ?> (<span id="ps-opacity-val">
                                <?php echo $opacity; ?>
                            </span>%)
                        </label>
                        <input type="range" name="ps_watermark_opacity" id="ps_watermark_opacity" min="0" max="100"
                            value="<?php echo esc_attr($opacity); ?>" style="width: 100%;">
                    </div>

                    <div class="ps-form-group">
                        <label>
                            <?php _e('Size', 'pocket-showroom'); ?> (<span id="ps-size-val">
                                <?php echo $size; ?>
                            </span>%)
                        </label>
                        <input type="range" name="ps_watermark_size" id="ps_watermark_size" min="5" max="100"
                            value="<?php echo esc_attr($size); ?>" style="width: 100%;">
                    </div>
                </div>

                <!-- Col 2: Visual Preview -->
                <div class="ps-col-preview">
                    <h3>
                        <?php _e('Visual Preview', 'pocket-showroom'); ?>
                    </h3>
                    <div class="ps-canvas-wrapper"
                        style="border-radius: 8px; overflow: hidden; border: 1px solid #ddd;">
                        <div id="ps-preview-canvas"
                            style="background: linear-gradient(135deg, #e8e8e8 0%, #d0d0d0 50%, #c8c8c8 100%); height: 65px; position: relative;">
                            <div id="ps-watermark-layer">
                                <span class="ps-wm-text"></span>
                                <img src="" class="ps-wm-image" style="display:none;">
                            </div>
                        </div>
                        <p class="description"
                            style="padding: 10px; background: #f9f9f9; margin: 0; font-size: 12px; color: #888; border-top: 1px solid #eee;">
                            <?php _e('This is a simulation. Actual result depends on uploaded image resolution.', 'pocket-showroom'); ?>
                        </p>
                    </div>
                </div>

                <!-- Col 3: Position & Rotation & SAVE -->
                <div class="ps-col-colors">
                    <h3 style="visibility:hidden">Pos</h3>

                    <div class="ps-form-group">
                        <label>
                            <?php _e('Position', 'pocket-showroom'); ?>
                        </label>
                        <div class="ps-position-grid">
                            <?php
                            $positions = [
                                'tl' => 'Top Left',
                                'tc' => 'Top Center',
                                'tr' => 'Top Right',
                                'ml' => 'Middle Left',
                                'c' => 'Center',
                                'mr' => 'Middle Right',
                                'bl' => 'Bottom Left',
                                'bc' => 'Bottom Center',
                                'br' => 'Bottom Right'
                            ];
                            foreach ($positions as $key => $label) {
                                $checked = ($position === $key) ? 'checked' : '';
                                echo '<label title="' . $label . '"><input type="radio" name="ps_watermark_position" value="' . $key . '" ' . $checked . '><span></span></label>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="ps-form-group">
                        <label>
                            <?php _e('Rotation', 'pocket-showroom'); ?>
                        </label>
                        <select name="ps_watermark_rotation" id="ps_watermark_rotation" class="ps-input">
                            <option value="0" <?php selected('0', $rotation); ?>>
                                <?php _e('None (0°)', 'pocket-showroom'); ?>
                            </option>
                            <option value="90" <?php selected('90', $rotation); ?>>
                                <?php _e('90° Vertical', 'pocket-showroom'); ?>
                            </option>
                            <option value="-90" <?php selected('-90', $rotation); ?>>
                                <?php _e('-90° Vertical', 'pocket-showroom'); ?>
                            </option>
                            <option value="45" <?php selected('45', $rotation); ?>>
                                <?php _e('45° Diagonal', 'pocket-showroom'); ?>
                            </option>
                            <option value="-45" <?php selected('-45', $rotation); ?>>
                                <?php _e('-45° Diagonal', 'pocket-showroom'); ?>
                            </option>
                        </select>
                    </div>

                    <!-- Move Submit Button Here -->
                    <div class="ps-form-group" style="margin-top: 30px;">
                        <button type="submit" name="submit" id="submit" class="ps-btn ps-btn-primary"
                            style="width: 100%; justify-content: center; padding: 12px 0; font-size: 14px; letter-spacing: 0.5px;">
                            <span class="dashicons dashicons-saved" style="margin-right: 5px; margin-top: 2px;"></span>
                            <?php _e('Save Changes', 'pocket-showroom'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>