<?php
if (!defined('ABSPATH'))
    exit;

class PS_Core_Settings
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
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ('ps_item_page_ps-settings' !== $hook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('ps-admin-style', PS_V2_URL . 'assets/admin-style.css', array(), PS_V2_VERSION);
        wp_enqueue_script('ps-admin-script', PS_V2_URL . 'assets/admin-script.js', array('jquery'), PS_V2_VERSION, true);
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'edit.php?post_type=ps_item',
            'Pocket Showroom Settings',
            'Settings',
            'manage_options',
            'ps-settings',
            array($this, 'render_settings_page')
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

    public function render_settings_page()
    {
        $defaults = array(
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
        );

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
        ?>
        <div class="ps-modern-ui wrap">
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
                    <a href="#ps-tab-watermark" class="nav-tab nav-tab-active"><?php _e('Watermark', 'pocket-showroom'); ?></a>
                    <a href="#ps-tab-banner" class="nav-tab"><?php _e('Frontend Banner', 'pocket-showroom'); ?></a>
                    <a href="#ps-tab-shortcodes" class="nav-tab"><?php _e('Shortcodes', 'pocket-showroom'); ?></a>
                </h2>

                <div class="ps-tab-content" id="ps-tab-shortcodes" style="display:none;">
                    <div class="ps-card" style="padding: 30px; max-width: 800px;">
                        <h3><?php _e('How to display the Showroom', 'pocket-showroom'); ?></h3>
                        <p><?php _e('To display your product catalog on any page, simply copy and paste the following shortcode:', 'pocket-showroom'); ?>
                        </p>
                        <code
                            style="background:#f0f0f1; padding:10px; display:block; margin:10px 0; font-size:14px; border-radius: 4px;">[pocket_showroom]</code>
                        <p><strong><?php _e('Options:', 'pocket-showroom'); ?></strong></p>
                        <ul style="list-style:disc; margin-left:20px; color: #646970;">
                            <li><code>posts_per_page="12"</code> -
                                <?php _e('Control how many items show per page.', 'pocket-showroom'); ?>
                            </li>
                        </ul>

                        <hr style="margin:30px 0; border: 0; border-top: 1px solid #eee;">

                        <h3><?php _e('How to display Single Data Fields', 'pocket-showroom'); ?></h3>
                        <p><?php _e('If you want to display specific product data (like Model No. or Price) in a custom layout or Elementor/Divi text module, use:', 'pocket-showroom'); ?>
                        </p>
                        <code
                            style="background:#f0f0f1; padding:10px; display:block; margin:10px 0; font-size:14px; border-radius: 4px;">[ps_data key="model"]</code>
                        <p><strong><?php _e('Available Keys:', 'pocket-showroom'); ?></strong></p>
                        <ul style="list-style:disc; margin-left:20px; color: #646970;">
                            <li><code>model</code> - <?php _e('Model Number', 'pocket-showroom'); ?></li>
                            <li><code>material</code> - <?php _e('Material', 'pocket-showroom'); ?></li>
                            <li><code>moq</code> - <?php _e('Minimum Order Quantity', 'pocket-showroom'); ?></li>
                            <li><code>loading</code> - <?php _e('Loading Quantity', 'pocket-showroom'); ?></li>
                            <li><code>lead_time</code> - <?php _e('Delivery Time', 'pocket-showroom'); ?></li>
                            <li><code>list_price</code> - <?php _e('EXW Price', 'pocket-showroom'); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="ps-tab-content" id="ps-tab-banner" style="display:none;">
                    <!-- Row 1: All Settings (horizontal) -->
                    <div class="ps-banner-settings-row">
                        <!-- Image Settings -->
                        <div class="ps-banner-settings-col">
                            <h3><?php _e('Banner Image', 'pocket-showroom'); ?></h3>
                            <div class="ps-form-group">
                                <div class="ps-image-upload">
                                    <input type="hidden" name="ps_banner_image_id" id="ps_banner_image_id"
                                        value="<?php echo esc_attr($banner_image_id); ?>">
                                    <div class="ps-image-preview-wrapper" style="margin-bottom: 15px;">
                                        <?php if ($banner_image_url): ?>
                                            <img src="<?php echo esc_url($banner_image_url); ?>"
                                                style="width: 100%; max-width: 100%; height: auto; border-radius: 6px; border: 1px solid #ddd;">
                                        <?php else: ?>
                                            <div class="ps-no-image"
                                                style="background:#f0f0f1; padding: 40px 20px; text-align:center; color:#888; border-radius: 6px; border: 2px dashed #ccc;">
                                                <?php _e('No Image', 'pocket-showroom'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ps-actions">
                                        <button type="button" class="ps-btn ps-btn-secondary"
                                            id="ps_banner_image_btn"><?php _e('Select Image', 'pocket-showroom'); ?></button>
                                        <button type="button" class="ps-btn ps-btn-secondary" id="ps_remove_banner_image"
                                            style="<?php echo $banner_image_id ? '' : 'display:none;'; ?> color: #d63638; border-color: #d63638;"><?php _e('Remove', 'pocket-showroom'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Text Settings -->
                        <div class="ps-banner-settings-col" style="flex: 1;">
                            <h3><?php _e('Text Content', 'pocket-showroom'); ?></h3>
                            <div class="ps-form-group">
                                <label><?php _e('Banner Title (H1)', 'pocket-showroom'); ?></label>
                                <input type="text" name="ps_banner_title" id="ps_banner_title"
                                    value="<?php echo esc_attr($banner_title); ?>" class="large-text ps-input" />
                            </div>
                            <div class="ps-form-group">
                                <label><?php _e('Banner Description', 'pocket-showroom'); ?></label>
                                <textarea name="ps_banner_desc" id="ps_banner_desc" class="large-text ps-input"
                                    rows="4"><?php echo esc_textarea($banner_desc); ?></textarea>
                            </div>
                            <div style="display: flex; gap: 20px;">
                                <div class="ps-form-group" style="flex: 1;">
                                    <label><?php _e('Button Text', 'pocket-showroom'); ?></label>
                                    <input type="text" name="ps_banner_button_text" id="ps_banner_button_text"
                                        value="<?php echo esc_attr(get_option('ps_banner_button_text', 'Explore Now')); ?>"
                                        class="regular-text ps-input" />
                                </div>
                                <div class="ps-form-group" style="flex: 1;">
                                    <label><?php _e('Button URL', 'pocket-showroom'); ?></label>
                                    <input type="url" name="ps_banner_button_url" id="ps_banner_button_url"
                                        value="<?php echo esc_attr(get_option('ps_banner_button_url', '')); ?>"
                                        class="large-text ps-input" placeholder="https://..." />
                                </div>
                            </div>
                        </div>

                        <!-- Product Card Display -->
                        <div class="ps-banner-settings-col" style="flex: 0.8;">
                            <h3><?php _e('Product Card Display', 'pocket-showroom'); ?></h3>
                            <div class="ps-form-group">
                                <label><?php _e('Image Aspect Ratio', 'pocket-showroom'); ?></label>
                                <select name="ps_card_aspect_ratio" id="ps_card_aspect_ratio" class="ps-input"
                                    style="width: 100%;">
                                    <?php
                                    $card_ratio = get_option('ps_card_aspect_ratio', $defaults['card_aspect_ratio']);
                                    $ratio_options = array(
                                        '1/1' => __('1:1 — Square', 'pocket-showroom'),
                                        '3/4' => __('3:4 — Portrait (Recommended)', 'pocket-showroom'),
                                        '4/3' => __('4:3 — Landscape', 'pocket-showroom'),
                                        '16/9' => __('16:9 — Widescreen', 'pocket-showroom'),
                                        '9/16' => __('9:16 — Tall Portrait', 'pocket-showroom'),
                                        'auto' => __('Auto — Original Ratio', 'pocket-showroom'),
                                    );
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
                                <p class="description" style="margin-top: 8px; color: #888;">
                                    <?php _e('Controls how product images are displayed in the gallery grid. Images will always be fully visible (no cropping).', 'pocket-showroom'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Color Settings -->
                        <div class="ps-banner-settings-col">
                            <h3><?php _e('Colors', 'pocket-showroom'); ?></h3>
                            <div class="ps-colors-row" style="flex-wrap: wrap;">
                                <div class="ps-color-item">
                                    <label><?php _e('Overlay', 'pocket-showroom'); ?></label>
                                    <input type="hidden" name="ps_banner_overlay_color" id="ps_banner_overlay_color"
                                        value="<?php echo esc_attr($banner_overlay_color); ?>">
                                    <div class="ps-divi-picker" id="ps-picker-overlay"></div>
                                </div>
                                <div class="ps-color-item">
                                    <label><?php _e('Primary', 'pocket-showroom'); ?></label>
                                    <input type="hidden" name="ps_primary_color" id="ps_primary_color"
                                        value="<?php echo esc_attr($primary_color); ?>">
                                    <div class="ps-divi-picker" id="ps-picker-primary"></div>
                                </div>
                                <div class="ps-color-item">
                                    <label><?php _e('Btn Text', 'pocket-showroom'); ?></label>
                                    <input type="hidden" name="ps_button_text_color" id="ps_button_text_color"
                                        value="<?php echo esc_attr($button_text_color); ?>">
                                    <div class="ps-divi-picker" id="ps-picker-button-text"></div>
                                </div>
                                <div class="ps-color-item">
                                    <label><?php _e('Title', 'pocket-showroom'); ?></label>
                                    <input type="hidden" name="ps_banner_title_color" id="ps_banner_title_color"
                                        value="<?php echo esc_attr($banner_title_color); ?>">
                                    <div class="ps-divi-picker" id="ps-picker-title"></div>
                                </div>
                                <div class="ps-color-item">
                                    <label><?php _e('Desc', 'pocket-showroom'); ?></label>
                                    <input type="hidden" name="ps_banner_desc_color" id="ps_banner_desc_color"
                                        value="<?php echo esc_attr($banner_desc_color); ?>">
                                    <div class="ps-divi-picker" id="ps-picker-desc"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Live Preview (full width) -->
                    <div class="ps-banner-preview-row">
                        <h3><?php _e('Live Preview', 'pocket-showroom'); ?></h3>
                        <div class="ps-canvas-wrapper"
                            style="background:#fff; border:none; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 8px; overflow:hidden;">
                            <div id="ps-live-preview-container"
                                style="position:relative; width:100%; height:300px; background-size:cover; background-position:center; display:flex; align-items:center; justify-content:center; text-align:center; color:#fff; <?php echo $banner_image_url ? 'background-image:url(' . esc_url($banner_image_url) . ');' : 'background-image:url(' . PS_V2_URL . 'assets/placeholder-furniture.jpg);'; ?>">
                                <div id="ps-banner-overlay-layer"
                                    style="position:absolute; top:0; left:0; right:0; bottom:0; background-color: <?php echo esc_attr($banner_overlay_color); ?>;">
                                </div>
                                <div style="position:relative; z-index:2; padding:20px;">
                                    <h1 id="ps-preview-banner-title"
                                        style="margin:0 0 0; font-size:28px; font-weight:700; text-shadow:0 2px 4px rgba(0,0,0,0.3); color:<?php echo esc_attr($banner_title_color); ?>;">
                                        <?php echo esc_html($banner_title); ?>
                                    </h1>
                                    <div id="ps-preview-banner-desc"
                                        style="font-size:16px; opacity:0.9; color:<?php echo esc_attr($banner_desc_color); ?>; max-width: 500px; margin: 5px auto 10px auto; white-space: pre-wrap; line-height: 1.4;">
                                        <?php echo wpautop(esc_html($banner_desc)); ?>
                                    </div>
                                    <div style="margin-top: 0;">
                                        <span class="ps-preview-btn" id="ps-preview-banner-btn"
                                            style="display:inline-block; padding: 10px 24px; background-color: <?php echo esc_attr($primary_color); ?>; color:<?php echo esc_attr($button_text_color); ?>; border-radius:4px; font-size:14px; font-weight:600;"><?php echo esc_html(get_option('ps_banner_button_text', 'Explore Now')); ?></span>
                                    </div>
                                </div>
                                <div
                                    style="position:absolute; bottom:20px; right:20px; background:rgba(255,255,255,0.15); backdrop-filter:blur(10px); padding:10px 15px; border-radius:8px; border:1px solid rgba(255,255,255,0.3);">
                                    <span style="display:block; font-size:24px; font-weight:800; line-height:1;">12</span>
                                    <span
                                        style="font-size:10px; text-transform:uppercase; letter-spacing:1px;"><?php _e('Products Ready', 'pocket-showroom'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="ps-form-group" style="margin-top: 30px;">
                        <?php submit_button(__('Save Changes', 'pocket-showroom'), 'primary ps-btn ps-btn-primary', 'submit', false); ?>
                    </div>
                </div>

                <div class="ps-tab-content" id="ps-tab-watermark" style="">
                    <div class="ps-settings-layout-3col">
                        <!-- Col 1: Controls -->
                        <div class="ps-col-inputs">
                            <h3><?php _e('Watermark Configuration', 'pocket-showroom'); ?></h3>

                            <div class="ps-form-group">
                                <label><?php _e('Enable Watermarking', 'pocket-showroom'); ?></label>
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
                                <label><?php _e('Watermark Type', 'pocket-showroom'); ?></label>
                                <div class="ps-radio-group">
                                    <label><input type="radio" name="ps_watermark_type" value="text" <?php checked('text', $type); ?>>
                                        <?php _e('Text', 'pocket-showroom'); ?></label>
                                    <label><input type="radio" name="ps_watermark_type" value="image" <?php checked('image', $type); ?>>
                                        <?php _e('Image', 'pocket-showroom'); ?></label>
                                </div>
                            </div>

                            <div class="ps-form-group ps-type-text"
                                style="<?php echo $type === 'image' ? 'display:none;' : ''; ?>">
                                <label><?php _e('Watermark Text', 'pocket-showroom'); ?></label>
                                <input type="text" name="ps_watermark_text" id="ps_watermark_text"
                                    value="<?php echo esc_attr($text); ?>" class="regular-text ps-input" />
                            </div>

                            <div class="ps-form-group ps-type-image"
                                style="<?php echo $type === 'text' ? 'display:none;' : ''; ?>">
                                <label><?php _e('Watermark Image', 'pocket-showroom'); ?></label>
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
                                        <button type="button" class="ps-btn ps-btn-secondary"
                                            id="ps-upload-watermark"><?php _e('Select Image', 'pocket-showroom'); ?></button>
                                        <button type="button" class="ps-btn ps-btn-secondary" id="ps-remove-watermark"
                                            style="<?php echo $image_id ? '' : 'display:none;'; ?> color: #d63638; border-color: #d63638;"><?php _e('Remove', 'pocket-showroom'); ?></button>
                                    </div>
                                </div>
                            </div>

                            <div class="ps-form-group">
                                <label><?php _e('Opacity', 'pocket-showroom'); ?> (<span
                                        id="ps-opacity-val"><?php echo $opacity; ?></span>%)</label>
                                <input type="range" name="ps_watermark_opacity" id="ps_watermark_opacity" min="0" max="100"
                                    value="<?php echo esc_attr($opacity); ?>" style="width: 100%;">
                            </div>

                            <div class="ps-form-group">
                                <label><?php _e('Size', 'pocket-showroom'); ?> (<span
                                        id="ps-size-val"><?php echo $size; ?></span>%)</label>
                                <input type="range" name="ps_watermark_size" id="ps_watermark_size" min="5" max="100"
                                    value="<?php echo esc_attr($size); ?>" style="width: 100%;">
                            </div>
                        </div>

                        <!-- Col 2: Visual Preview -->
                        <div class="ps-col-preview">
                            <h3><?php _e('Visual Preview', 'pocket-showroom'); ?></h3>
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
                                <label><?php _e('Position', 'pocket-showroom'); ?></label>
                                <div class="ps-position-grid">
                                    <?php
                                    $positions = array(
                                        'tl' => 'Top Left',
                                        'tc' => 'Top Center',
                                        'tr' => 'Top Right',
                                        'ml' => 'Middle Left',
                                        'c' => 'Center',
                                        'mr' => 'Middle Right',
                                        'bl' => 'Bottom Left',
                                        'bc' => 'Bottom Center',
                                        'br' => 'Bottom Right'
                                    );
                                    foreach ($positions as $key => $label) {
                                        $checked = ($position === $key) ? 'checked' : '';
                                        echo '<label title="' . $label . '"><input type="radio" name="ps_watermark_position" value="' . $key . '" ' . $checked . '><span></span></label>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="ps-form-group">
                                <label><?php _e('Rotation', 'pocket-showroom'); ?></label>
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
                                <?php submit_button(__('Save Changes', 'pocket-showroom'), 'primary ps-btn ps-btn-primary', 'submit', false); ?>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>
        <?php
    }
}
