<?php

if (!defined('ABSPATH')) {
    exit;
}

class PS_Frontend_Gallery
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
        add_shortcode('pocket_showroom', array($this, 'render_gallery'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_ps_get_product_modal', array($this, 'ajax_get_product_modal'));
        add_action('wp_ajax_nopriv_ps_get_product_modal', array($this, 'ajax_get_product_modal'));
        // Fix #10: Load More AJAX
        add_action('wp_ajax_ps_load_more', array($this, 'ajax_load_more'));
        add_action('wp_ajax_nopriv_ps_load_more', array($this, 'ajax_load_more'));
        add_action('wp_head', array($this, 'maybe_hide_layout_elements'), 999);
        // Fix #23: 产品更新时清除缓存的最后更新日期
        add_action('save_post_ps_item', array($this, 'clear_last_updated_cache'));
    }

    public function enqueue_assets()
    {
        wp_register_style('ps-gallery-css', PS_CORE_URL . 'assets/gallery-style.css', array(), PS_CORE_VERSION);
        wp_register_script('ps-gallery-js', PS_CORE_URL . 'assets/gallery-script.js', array('jquery'), PS_CORE_VERSION, true);

        wp_localize_script('ps-gallery-js', 'ps_ajax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ps_gallery_nonce'),
            'watermark_text' => get_option('ps_watermark_text', 'Pocket Showroom'),
            'i18n' => array(
                'load_more' => __('Load More', 'pocket-showroom'),
                'loading' => __('Loading...', 'pocket-showroom'),
                // Fix F2: Add missing i18n strings
                'modal_loading' => __('Loading...', 'pocket-showroom'),
                'modal_error' => __('Error loading details.', 'pocket-showroom'),
                'network_error' => __('Network error, please try again.', 'pocket-showroom'),
                'timeout_error' => __('Request timed out, please try again.', 'pocket-showroom'),
                'untitled' => __('Untitled', 'pocket-showroom'),
                'link_copied' => __('Link copied!', 'pocket-showroom'),
                'qr_error' => __('QR code failed to load. Please copy the link below.', 'pocket-showroom'),
            ),
        ));
    }

    /**
     * Fix #23: 清除缓存的最后更新日期
     */
    public function clear_last_updated_cache()
    {
        delete_transient('ps_last_updated_date');
    }

    /**
     * Fix #25: 抽取产品卡片 HTML 为公共方法，消除 render_gallery 和 ajax_load_more 的重复代码
     *
     * @param string $watermark_text 水印文本
     */
    private function render_card_html($watermark_text)
    {
        $thumb_url = get_the_post_thumbnail_url(get_the_ID(), 'medium');
        if (!$thumb_url)
            $thumb_url = 'https://via.placeholder.com/300x200?text=No+Image';
        $model = get_post_meta(get_the_ID(), '_ps_model', true);
        $list_price = get_post_meta(get_the_ID(), '_ps_list_price', true);

        // Get Terms for Filter
        $item_terms = get_the_terms(get_the_ID(), 'ps_category');
        $term_slugs = array();
        if ($item_terms && !is_wp_error($item_terms)) {
            foreach ($item_terms as $t) {
                $term_slugs[] = $t->slug;
            }
        }
        $cat_slug = !empty($term_slugs) ? implode(' ', $term_slugs) : 'uncategorized';
        ?>
        <div class="ps-card" data-id="<?php echo get_the_ID(); ?>" data-cat="<?php echo esc_attr($cat_slug); ?>">
            <div class="ps-card-image">
                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                <?php if ($watermark_text): ?>
                    <div class="ps-watermark"><?php echo esc_html($watermark_text); ?></div>
                <?php endif; ?>
                <button class="ps-share-btn" data-share-title="<?php the_title_attribute(); ?>"
                    data-share-desc="<?php echo esc_attr($model); ?>"
                    data-share-url="<?php echo esc_url(add_query_arg('pshare', '1', get_permalink())); ?>"
                    data-share-img="<?php echo esc_url($thumb_url); ?>"
                    title="<?php esc_attr_e('Share', 'pocket-showroom'); ?>">
                    <svg viewBox="0 0 24 24">
                        <path
                            d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z" />
                    </svg>
                </button>
                <div class="ps-card-overlay">
                    <button class="ps-view-btn"><?php _e('Quick View', 'pocket-showroom'); ?></button>
                </div>
            </div>
            <div class="ps-card-body">
                <div class="ps-card-header">
                    <span class="ps-model"><?php echo esc_html($model); ?></span>
                    <?php if ($list_price): ?>
                        <span class="ps-price"><?php echo esc_html($list_price); ?></span>
                    <?php endif; ?>
                </div>
                <h3 class="ps-title"><?php the_title(); ?></h3>
            </div>
        </div>
        <?php
    }

    public function render_gallery($atts)
    {
        wp_enqueue_style('ps-gallery-css');
        wp_enqueue_script('ps-gallery-js');

        $atts = shortcode_atts(array(
            'posts_per_page' => 12,
        ), $atts, 'pocket_showroom');

        $watermark_text = get_option('ps_watermark_text', 'Pocket Showroom');

        // Banner Config
        $banner_title = get_option('ps_banner_title', 'Pocket Showroom');
        $banner_desc = get_option('ps_banner_desc', 'Discover our latest collection\nof premium furniture.');
        $banner_button_text = get_option('ps_banner_button_text', 'Get a Quote');
        $banner_button_url = get_option('ps_banner_button_url', '/contact');
        $banner_image_id = get_option('ps_banner_image_id');
        $banner_overlay_color = get_option('ps_banner_overlay_color', 'rgba(46, 125, 50, 0.4)');
        $primary_color = get_option('ps_primary_color', '#007cba');
        $button_text_color = get_option('ps_button_text_color', '#ffffff');
        $banner_title_color = get_option('ps_banner_title_color', '#ffffff');
        $banner_desc_color = get_option('ps_banner_desc_color', 'rgba(255, 255, 255, 0.95)');
        $banner_bg = '';
        if ($banner_image_id) {
            $banner_bg = wp_get_attachment_image_url($banner_image_id, 'full');
        }

        // Count published products
        $count_posts = wp_count_posts('ps_item');
        $published_count = $count_posts->publish;

        // Fix #23: 缓存最后更新日期，避免每次页面加载都查询
        $last_updated = get_transient('ps_last_updated_date');
        if (false === $last_updated) {
            $last_post = get_posts(array(
                'post_type' => 'ps_item',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => 'publish'
            ));
            $last_updated = '';
            if (!empty($last_post)) {
                $last_updated = get_the_date('F j, Y', $last_post[0]->ID);
            }
            set_transient('ps_last_updated_date', $last_updated, HOUR_IN_SECONDS);
        }

        $posts_per_page = intval($atts['posts_per_page']);
        $args = array(
            'post_type' => 'ps_item',
            'posts_per_page' => $posts_per_page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($args);
        $max_pages = $query->max_num_pages;

        // Get Categories
        $terms = get_terms(array(
            'taxonomy' => 'ps_category',
            'hide_empty' => false,
        ));

        ob_start();
        ?>
        <?php
        // Fix #26: CSS 变量改用 wp_add_inline_style 注入，避免内联 <style> 标签
        $card_ratio = get_option('ps_card_aspect_ratio', '3/4');
        // 安全校验：只允许合法的比例值
        $allowed_ratios = array('1/1', '3/4', '4/3', '16/9', '9/16', 'auto');
        if (!in_array($card_ratio, $allowed_ratios, true)) {
            $card_ratio = '3/4';
        }
        $ratio_css = ($card_ratio === 'auto') ? 'auto' : $card_ratio;

        $inline_css = sprintf(
            '.ps-gallery-container, .ps-modal { --ps-primary-color: %s; --ps-button-text-color: %s; --ps-title-color: %s; --ps-desc-color: %s; } .ps-card-image { --ps-card-ratio: %s; }',
            esc_attr($primary_color),
            esc_attr($button_text_color),
            esc_attr($banner_title_color),
            esc_attr($banner_desc_color),
            esc_attr($ratio_css)
        );
        wp_add_inline_style('ps-gallery-css', $inline_css);
        ?>

        <!-- Banner Section -->
        <div class="ps-banner"
            style="<?php echo $banner_bg ? 'background-image: url(' . esc_url($banner_bg) . ');' : 'background-color:#333;'; ?>">
            <div class="ps-banner-overlay" style="background-color: <?php echo esc_attr($banner_overlay_color); ?>;"></div>
            <div class="ps-banner-content">
                <h1><?php echo esc_html($banner_title); ?></h1>
                <div class="ps-banner-desc" style="max-width:500px; margin:0 auto; text-align:center;">
                    <?php echo nl2br(esc_html($banner_desc)); ?>
                </div>
                <div
                    style="margin-top:20px; display:flex; align-items:center; justify-content:center; gap:10px; flex-wrap:wrap;">
                    <?php if ($banner_button_text): ?>
                        <a href="<?php echo esc_url($banner_button_url); ?>" class="ps-banner-cta-btn"
                            style="display:inline-block; padding:12px 28px; background-color:<?php echo esc_attr($primary_color); ?>; color:<?php echo esc_attr($button_text_color); ?>; border-radius:4px; font-size:14px; font-weight:600; text-decoration:none; transition:opacity 0.3s;"><?php echo esc_html($banner_button_text); ?></a>
                    <?php endif; ?>

                    <?php
                    // Get current URL for sharing (安全方式，不直接使用 $_SERVER)
                    $current_url = home_url(add_query_arg(null, null));
                    $share_url = add_query_arg('pshare', '1', $current_url);
                    ?>
                    <button class="ps-banner-share-btn" data-share-title="<?php echo esc_attr($banner_title); ?>"
                        data-share-desc="<?php echo esc_attr($banner_desc); ?>"
                        data-share-url="<?php echo esc_url($share_url); ?>"
                        data-share-img="<?php echo $banner_bg ? esc_url($banner_bg) : ''; ?>">
                        <svg viewBox="0 0 24 24">
                            <path
                                d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z" />
                        </svg>
                        Share
                    </button>
                </div>
            </div>

            <div class="ps-product-count">
                <div class="ps-count-inner">
                    <span class="ps-count-number"><?php echo intval($published_count); ?></span>
                    <span class="ps-count-label"><?php _e('Products Ready', 'pocket-showroom'); ?></span>
                </div>
                <?php if ($last_updated): ?>
                    <div class="ps-last-updated">
                        <?php printf(__('Updated: %s', 'pocket-showroom'), $last_updated); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ps-gallery-container">
            <!-- Filter Bar -->
            <div class="ps-gallery-filters">
                <?php if (!empty($terms) && !is_wp_error($terms)): ?>
                    <div class="ps-filter-buttons">
                        <button class="ps-filter-btn active" data-cat="all"><?php _e('All', 'pocket-showroom'); ?></button>
                        <?php foreach ($terms as $term): ?>
                            <button class="ps-filter-btn"
                                data-cat="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <input type="text" id="ps-search" placeholder="<?php _e('Search products...', 'pocket-showroom'); ?>">
            </div>

            <!-- Grid -->
            <div class="ps-gallery-grid">
                <?php if ($query->have_posts()): ?>
                    <?php while ($query->have_posts()):
                        $query->the_post();
                        $this->render_card_html($watermark_text);
                    endwhile; ?>
                <?php else: ?>
                    <p><?php _e('No items found.', 'pocket-showroom'); ?></p>
                <?php endif;
                wp_reset_postdata(); ?>
            </div>

            <?php if ($max_pages > 1): ?>
                <!-- Load More Button (Fix #10) -->
                <div class="ps-load-more-wrap" id="ps-load-more-wrap" data-page="1" data-max="<?php echo esc_attr($max_pages); ?>"
                    data-per-page="<?php echo esc_attr($posts_per_page); ?>">
                    <button class="ps-load-more-btn" id="ps-load-more-btn">
                        <?php _e('Load More', 'pocket-showroom'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Modal -->
            <div id="ps-modal" class="ps-modal">
                <div class="ps-modal-content">
                    <span class="ps-close">&times;</span>
                    <div id="ps-modal-body">
                        <div class="ps-loader"><?php _e('Loading...', 'pocket-showroom'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Share Sheet -->
            <div class="ps-share-sheet-overlay" id="ps-share-sheet-overlay">
                <div class="ps-share-sheet">
                    <div class="ps-share-sheet-header">
                        <h4>Share</h4>
                        <button class="ps-share-sheet-close" id="ps-share-sheet-close">&times;</button>
                    </div>
                    <div class="ps-share-preview">
                        <img class="ps-share-preview-img" id="ps-share-preview-img" src="" alt="">
                        <div class="ps-share-preview-info">
                            <div class="ps-share-preview-title" id="ps-share-preview-title"></div>
                            <div class="ps-share-preview-desc" id="ps-share-preview-desc"></div>
                        </div>
                    </div>
                    <div class="ps-share-options">
                        <button class="ps-share-option" id="ps-share-whatsapp">
                            <span class="ps-share-option-icon ps-wa-icon">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                            </span>
                            <span class="ps-share-option-label">WhatsApp</span>
                        </button>
                        <button class="ps-share-option" id="ps-share-wechat">
                            <span class="ps-share-option-icon ps-wc-icon">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 01.213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 00.167-.054l1.903-1.114a.864.864 0 01.717-.098 10.16 10.16 0 002.837.403c.276 0 .543-.027.811-.05a6.266 6.266 0 01-.246-1.732c0-3.684 3.382-6.672 7.558-6.672.189 0 .37.017.555.028C16.727 4.842 13.073 2.188 8.691 2.188zm-2.27 3.766a1.113 1.113 0 110 2.226 1.113 1.113 0 010-2.226zm4.572 0a1.113 1.113 0 110 2.226 1.113 1.113 0 010-2.226zm5.322 3.87c-3.668 0-6.644 2.535-6.644 5.66 0 3.126 2.976 5.66 6.644 5.66.836 0 1.635-.145 2.373-.409a.696.696 0 01.58.083l1.546.917a.27.27 0 00.139.045c.13 0 .235-.108.235-.24 0-.06-.023-.116-.039-.173l-.317-1.204a.475.475 0 01.168-.534C21.413 18.584 22.36 16.922 22.36 15.084c0-3.125-2.977-5.66-6.645-5.66zm-2.376 3.255a.93.93 0 110 1.86.93.93 0 010-1.86zm4.752 0a.93.93 0 110 1.86.93.93 0 010-1.86z" />
                                </svg>
                            </span>
                            <span class="ps-share-option-label">WeChat</span>
                        </button>
                        <button class="ps-share-option" id="ps-share-copy">
                            <span class="ps-share-option-icon ps-copy-icon">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z" />
                                </svg>
                            </span>
                            <span class="ps-share-option-label">Copy Link</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- WeChat QR Modal -->
            <div class="ps-qr-modal-overlay" id="ps-qr-overlay">
                <div class="ps-qr-modal">
                    <h4>WeChat Share</h4>
                    <p>Scan QR code or copy link to share in WeChat</p>
                    <img id="ps-qr-image" src="" alt="QR Code">
                    <div class="ps-qr-copy-url">
                        <input type="text" id="ps-qr-url-input" readonly>
                        <button class="ps-qr-copy-btn" id="ps-qr-copy-btn">Copy</button>
                    </div>
                    <button class="ps-qr-close" id="ps-qr-close">Close</button>
                </div>
            </div>

            <!-- Toast -->
            <div class="ps-toast" id="ps-toast">Link copied!</div>

        </div>
        <?php
        return ob_get_clean();
    }

    public function ajax_get_product_modal()
    {
        check_ajax_referer('ps_gallery_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        // Validate post exists, is correct type and is published
        if (!$post || $post->post_type !== 'ps_item' || $post->post_status !== 'publish') {
            echo '<p>' . esc_html__('Product not found.', 'pocket-showroom') . '</p>';
            wp_die();
        }

        $watermark_text = get_option('ps_watermark_text', 'Pocket Showroom');

        // Get Gallery Images
        $gallery_ids = get_post_meta($post_id, '_ps_gallery_images', true);
        $images_html = '';

        // Helper to wrap image with watermark
        $wrap_img = function ($img_tag) use ($watermark_text) {
            if ($watermark_text) {
                return '<div class="ps-modal-img-wrapper">' . $img_tag . '<div class="ps-watermark">' . esc_html($watermark_text) . '</div></div>';
            }
            return '<div class="ps-modal-img-wrapper">' . $img_tag . '</div>';
        };

        if (!empty($gallery_ids)) {
            $ids = explode(',', $gallery_ids);
            foreach ($ids as $id) {
                $img_url = wp_get_attachment_image_url($id, 'large');
                if ($img_url) {
                    $img_tag = '<img src="' . esc_url($img_url) . '" class="ps-modal-img">';
                    $images_html .= $wrap_img($img_tag);
                }
            }
        } else {
            $thumb = get_the_post_thumbnail_url($post_id, 'large');
            if ($thumb) {
                $img_tag = '<img src="' . esc_url($thumb) . '" class="ps-modal-img">';
                $images_html = $wrap_img($img_tag);
            }
        }

        // Meta
        $model = get_post_meta($post_id, '_ps_model', true);
        $material = get_post_meta($post_id, '_ps_material', true);
        $moq = get_post_meta($post_id, '_ps_moq', true);
        $loading = get_post_meta($post_id, '_ps_loading', true);
        $lead_time = get_post_meta($post_id, '_ps_lead_time', true);
        $price = get_post_meta($post_id, '_ps_list_price', true);

        // Variants
        $variants = get_post_meta($post_id, '_ps_size_variants', true);
        $variants_html = '';
        if (!empty($variants) && is_array($variants)) {
            $variants_html .= '<div class="ps-variants-section"><h4>' . __('Available Sizes', 'pocket-showroom') . '</h4><table class="ps-variants-table"><thead><tr><th>' . __('Variant', 'pocket-showroom') . '</th><th>' . __('Dimensions', 'pocket-showroom') . '</th></tr></thead><tbody>';
            foreach ($variants as $v) {
                $variants_html .= '<tr><td>' . esc_html($v['label']) . '</td><td>' . esc_html($v['value']) . '</td></tr>';
            }
            $variants_html .= '</tbody></table></div>';
        }

        // Dynamic Specs (Custom Fields) — 修复 #3: 在 Modal 中显示自定义规格
        $dynamic_specs = get_post_meta($post_id, '_ps_dynamic_specs', true);
        $specs_html = '';
        if (!empty($dynamic_specs) && is_array($dynamic_specs)) {
            $specs_html .= '<div class="ps-specs-section"><h4>' . __('Specifications', 'pocket-showroom') . '</h4><table class="ps-variants-table"><tbody>';
            foreach ($dynamic_specs as $spec) {
                if (!empty($spec['key'])) {
                    $specs_html .= '<tr><td>' . esc_html($spec['key']) . '</td><td>' . esc_html($spec['val']) . '</td></tr>';
                }
            }
            $specs_html .= '</tbody></table></div>';
        }

        ?>
        <div class="ps-modal-layout">
            <div class="ps-modal-gallery">
                <?php echo $images_html; ?>
            </div>
            <div class="ps-modal-info">
                <h2><?php echo get_the_title($post_id); ?></h2>
                <div class="ps-header-meta">
                    <span class="ps-sku"><?php printf(__('Model: %s', 'pocket-showroom'), $model); ?></span>
                    <?php if ($price): ?>
                        <span class="ps-price-large"><?php printf(__('EXW: %s', 'pocket-showroom'), $price); ?></span>
                    <?php endif; ?>
                </div>

                <div class="ps-meta-table">
                    <?php if ($material): ?>
                        <div class="ps-meta-row"><span><?php _e('Material:', 'pocket-showroom'); ?></span>
                            <strong><?php echo esc_html($material); ?></strong>
                        </div><?php endif; ?>
                    <?php if ($moq): ?>
                        <div class="ps-meta-row"><span><?php _e('MOQ:', 'pocket-showroom'); ?></span>
                            <strong><?php echo esc_html($moq); ?></strong>
                        </div><?php endif; ?>
                    <?php if ($loading): ?>
                        <div class="ps-meta-row"><span><?php _e('Loading (40HQ):', 'pocket-showroom'); ?></span>
                            <strong><?php echo esc_html($loading); ?></strong>
                        </div><?php endif; ?>
                    <?php if ($lead_time): ?>
                        <div class="ps-meta-row"><span><?php _e('Delivery Time:', 'pocket-showroom'); ?></span>
                            <strong><?php echo esc_html($lead_time); ?></strong>
                        </div><?php endif; ?>
                </div>

                <?php echo $variants_html; ?>
                <?php echo $specs_html; ?>

                <div class="ps-desc">
                    <?php echo wp_kses_post(wpautop($post->post_content)); ?>
                </div>

                <!-- Share Buttons in Modal -->
                <div class="ps-modal-share-row">
                    <button class="ps-modal-share-btn ps-whatsapp"
                        onclick="psShareWhatsApp('<?php echo esc_js(get_the_title($post_id)); ?>', '<?php echo esc_url(add_query_arg('pshare', '1', get_permalink($post_id))); ?>')">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                        </svg>
                        WhatsApp
                    </button>
                    <button class="ps-modal-share-btn ps-wechat"
                        onclick="psOpenWeChatQR('<?php echo esc_url(add_query_arg('pshare', '1', get_permalink($post_id))); ?>')">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 01.213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 00.167-.054l1.903-1.114a.864.864 0 01.717-.098 10.16 10.16 0 002.837.403c.276 0 .543-.027.811-.05a6.266 6.266 0 01-.246-1.732c0-3.684 3.382-6.672 7.558-6.672.189 0 .37.017.555.028C16.727 4.842 13.073 2.188 8.691 2.188zm-2.27 3.766a1.113 1.113 0 110 2.226 1.113 1.113 0 010-2.226zm4.572 0a1.113 1.113 0 110 2.226 1.113 1.113 0 010-2.226zm5.322 3.87c-3.668 0-6.644 2.535-6.644 5.66 0 3.126 2.976 5.66 6.644 5.66.836 0 1.635-.145 2.373-.409a.696.696 0 01.58.083l1.546.917a.27.27 0 00.139.045c.13 0 .235-.108.235-.24 0-.06-.023-.116-.039-.173l-.317-1.204a.475.475 0 01.168-.534C21.413 18.584 22.36 16.922 22.36 15.084c0-3.125-2.977-5.66-6.645-5.66zm-2.376 3.255a.93.93 0 110 1.86.93.93 0 010-1.86zm4.752 0a.93.93 0 110 1.86.93.93 0 010-1.86z" />
                        </svg>
                        WeChat
                    </button>
                </div>
            </div>
        </div>
        <?php
        wp_die();
    }

    /**
     * Fix #10: Load More AJAX handler — 返回下一页产品卡片 HTML
     */
    public function ajax_load_more()
    {
        check_ajax_referer('ps_gallery_nonce', 'nonce');

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? min(100, absint($_POST['per_page'])) : 12;

        $args = array(
            'post_type' => 'ps_item',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($args);
        $watermark_text = get_option('ps_watermark_text', 'Pocket Showroom');

        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_card_html($watermark_text);
            }
        }
        wp_reset_postdata();
        $html = ob_get_clean();

        wp_send_json_success(array('html' => $html));
    }

    /**
     * Fix #27: 使用 body class 限定 CSS 作用域，避免全局选择器冲突
     * 兼容 Top 20+ WordPress 主题的 header / footer / sidebar 选择器
     */
    public function maybe_hide_layout_elements()
    {
        if (isset($_GET['pshare']) && sanitize_text_field(wp_unslash($_GET['pshare'])) === '1') {
            ?>
            <script>document.body.classList.add('ps-share-mode');</script>
            <style>
                /* --- 通用 HTML5 / 常见 class --- */
                body.ps-share-mode header,
                body.ps-share-mode footer,
                body.ps-share-mode .site-header,
                body.ps-share-mode .site-footer,
                body.ps-share-mode #masthead,
                body.ps-share-mode #colophon,
                body.ps-share-mode .sidebar,
                body.ps-share-mode #secondary,
                body.ps-share-mode .widget-area,
                body.ps-share-mode nav.main-navigation,
                /* --- Elementor / Hello Elementor --- */
                body.ps-share-mode .elementor-location-header,
                body.ps-share-mode .elementor-location-footer,
                body.ps-share-mode [data-elementor-type="header"],
                body.ps-share-mode [data-elementor-type="footer"],
                /* --- Divi --- */
                body.ps-share-mode #main-header,
                body.ps-share-mode #main-footer,
                body.ps-share-mode #top-header,
                body.ps-share-mode .et-l--header,
                body.ps-share-mode .et-l--footer,
                body.ps-share-mode #et-footer-nav,
                /* --- Avada --- */
                body.ps-share-mode .fusion-header-wrapper,
                body.ps-share-mode .fusion-footer,
                body.ps-share-mode .fusion-footer-widget-area,
                body.ps-share-mode .fusion-sliding-bar-wrapper,
                /* --- Astra --- */
                body.ps-share-mode .ast-above-header,
                body.ps-share-mode .ast-below-header,
                body.ps-share-mode .main-header-bar-wrap,
                body.ps-share-mode .ast-footer-overlay,
                body.ps-share-mode .site-below-footer-wrap,
                body.ps-share-mode .ast-above-footer,
                /* --- OceanWP --- */
                body.ps-share-mode #site-header,
                body.ps-share-mode #site-navigation-wrap,
                body.ps-share-mode .footer-widgets,
                body.ps-share-mode #footer-bottom,
                /* --- GeneratePress --- */
                body.ps-share-mode .site-info,
                /* --- Neve --- */
                body.ps-share-mode .hfg_header,
                body.ps-share-mode .hfg_footer,
                body.ps-share-mode .nv-header,
                /* --- Kadence --- */
                body.ps-share-mode .site-header-row,
                body.ps-share-mode .site-footer-wrap,
                /* --- Blocksy --- */
                body.ps-share-mode header[data-id="type-1"],
                body.ps-share-mode footer.ct-footer,
                /* --- BeTheme --- */
                body.ps-share-mode #Header_wrapper,
                body.ps-share-mode #Top_bar,
                body.ps-share-mode #Footer,
                /* --- Sydney --- */
                body.ps-share-mode .header-wrap,
                /* --- Enfold --- */
                body.ps-share-mode #header,
                body.ps-share-mode #socket,
                body.ps-share-mode .avia-footer,
                /* --- WordPress admin bar --- */
                body.ps-share-mode #wpadminbar {
                    display: none !important;
                }

                body.ps-share-mode {
                    padding: 0 !important;
                    margin: 0 !important;
                    background: #fff;
                }

                body.ps-share-mode .ps-gallery-container,
                body.ps-share-mode .ps-product-single {
                    margin: 0 auto !important;
                    width: 100% !important;
                    max-width: 100% !important;
                    padding: 20px;
                }
            </style>
            <?php
        }
    }
}
