<?php
declare(strict_types=1);

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
        add_shortcode('pocket_showroom', [$this, 'render_gallery']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_ps_get_product_modal', [$this, 'ajax_get_product_modal']);
        add_action('wp_ajax_nopriv_ps_get_product_modal', [$this, 'ajax_get_product_modal']);
        // Fix #10: Load More AJAX
        add_action('wp_ajax_ps_load_more', [$this, 'ajax_load_more']);
        add_action('wp_ajax_nopriv_ps_load_more', [$this, 'ajax_load_more']);
        add_action('wp_head', [$this, 'maybe_hide_layout_elements'], 999);
        // Fix #23: 产品更新时清除缓存的最后更新日期
        add_action('save_post_ps_item', [$this, 'clear_last_updated_cache']);
        // Fix #28: Override the single product layout
        add_filter('the_content', [$this, 'render_single_product'], 99);
    }

    public function enqueue_assets()
    {
        wp_register_style('ps-gallery-css', PS_CORE_URL . 'assets/gallery-style.css', [], PS_CORE_VERSION);
        wp_register_script('ps-gallery-js', PS_CORE_URL . 'assets/gallery-script.js', ['jquery'], PS_CORE_VERSION, true);

        wp_localize_script('ps-gallery-js', 'ps_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ps_gallery_nonce'),
            'watermark_text' => get_option('ps_watermark_text', 'Pocket Showroom'),
            'i18n' => [
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
            ],
        ]);
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
        $term_slugs = [];
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

        $atts = shortcode_atts([
            'posts_per_page' => 12,
        ], $atts, 'pocket_showroom');

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
            $last_post = get_posts([
                'post_type' => 'ps_item',
                'posts_per_page' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => 'publish'
            ]);
            $last_updated = '';
            if (!empty($last_post)) {
                $last_updated = get_the_date('F j, Y', $last_post[0]->ID);
            }
            set_transient('ps_last_updated_date', $last_updated, HOUR_IN_SECONDS);
        }

        $posts_per_page = intval($atts['posts_per_page']);
        $args = [
            'post_type' => 'ps_item',
            'posts_per_page' => $posts_per_page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new WP_Query($args);
        $max_pages = $query->max_num_pages;

        // Get Categories
        $terms = get_terms([
            'taxonomy' => 'ps_category',
            'hide_empty' => false,
        ]);

        ob_start();
        ?>
        <?php
        // Fix #26: CSS 变量改用 wp_add_inline_style 注入，避免内联 <style> 标签
        $card_ratio = get_option('ps_card_aspect_ratio', '3/4');
        // 安全校验：只允许合法的比例值
        $allowed_ratios = ['1/1', '3/4', '4/3', '16/9', '9/16', 'auto'];
        if (!in_array($card_ratio, $allowed_ratios, true)) {
            $card_ratio = '3/4';
        }
        $ratio_css = ($card_ratio === 'auto') ? 'auto' : $card_ratio;

        $banner_cta_scale = get_option('ps_banner_cta_scale', '1');
        $banner_share_scale = get_option('ps_banner_share_scale', '1');

        $inline_css = sprintf(
            ':root { --ps-primary-color: %s; --ps-button-text-color: %s; --ps-title-color: %s; --ps-desc-color: %s; --ps-cta-scale: %s; --ps-share-scale: %s; } .ps-card-image { --ps-card-ratio: %s; }',
            esc_attr($primary_color),
            esc_attr($button_text_color),
            esc_attr($banner_title_color),
            esc_attr($banner_desc_color),
            esc_attr($banner_cta_scale),
            esc_attr($banner_share_scale),
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
                <div class="ps-banner-buttons"
                    style="margin-top:45px; display:flex; align-items:center; justify-content:center; gap:20px; flex-wrap:wrap;">
                    <?php if ($banner_button_text): ?>
                        <a href="<?php echo esc_url($banner_button_url); ?>" class="ps-banner-cta-btn"
                            style="display:inline-flex; align-items:center; justify-content:center; background-color:<?php echo esc_attr($primary_color); ?>; color:<?php echo esc_attr($button_text_color); ?>; border-radius:4px; font-weight:600; text-decoration:none; transition:opacity 0.3s;"><?php echo esc_html($banner_button_text); ?></a>
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
                </div> <!-- End Buttons -->

                <!-- Mobile Only Product Count (Inside content flow) -->
                <div class="ps-product-count ps-product-count-mobile">
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

            <!-- Desktop Only Product Count (Absolute positioned) -->
            <div class="ps-product-count ps-product-count-desktop">
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
                <!-- Placing search top helps it act as a primary action on mobile -->
                <input type="text" id="ps-search" placeholder="<?php _e('Search products...', 'pocket-showroom'); ?>">

                <?php if (!empty($terms) && !is_wp_error($terms)): ?>
                    <div class="ps-filter-buttons-wrapper">
                        <div class="ps-filter-buttons">
                            <button class="ps-filter-btn active" data-cat="all"><?php _e('All', 'pocket-showroom'); ?></button>
                            <?php foreach ($terms as $term): ?>
                                <button class="ps-filter-btn"
                                    data-cat="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Layout for Sidebar & Grid -->
            <div class="ps-gallery-layout">
                <!-- Sidebar (Desktop only) -->
                <aside class="ps-gallery-sidebar" id="ps-gallery-sidebar">
                    <div class="ps-sidebar-inner">
                        <h3 class="ps-sidebar-title"><?php _e('Categories', 'pocket-showroom'); ?></h3>
                        <ul class="ps-sidebar-categories">
                            <li class="ps-sidebar-cat active" data-cat="all"><?php _e('All Categories', 'pocket-showroom'); ?>
                            </li>
                            <?php if (!empty($terms) && !is_wp_error($terms)): ?>
                                <?php foreach ($terms as $term): ?>
                                    <li class="ps-sidebar-cat" data-cat="<?php echo esc_attr($term->slug); ?>">
                                        <?php echo esc_html($term->name); ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </aside>

                <!-- Grid wrapper -->
                <div class="ps-gallery-grid-wrapper">
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
                </div> <!-- End Main Grid Column -->
            </div> <!-- End Layout -->

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

        // Visibility Toggles
        $show_model = get_post_meta($post_id, '_ps_show_model', true) !== '' ? get_post_meta($post_id, '_ps_show_model', true) : '1';
        $show_list_price = get_post_meta($post_id, '_ps_show_list_price', true) !== '' ? get_post_meta($post_id, '_ps_show_list_price', true) : '1';
        $show_material = get_post_meta($post_id, '_ps_show_material', true) !== '' ? get_post_meta($post_id, '_ps_show_material', true) : '1';
        $show_moq = get_post_meta($post_id, '_ps_show_moq', true) !== '' ? get_post_meta($post_id, '_ps_show_moq', true) : '1';
        $show_loading = get_post_meta($post_id, '_ps_show_loading', true) !== '' ? get_post_meta($post_id, '_ps_show_loading', true) : '1';
        $show_lead_time = get_post_meta($post_id, '_ps_show_lead_time', true) !== '' ? get_post_meta($post_id, '_ps_show_lead_time', true) : '1';

        // Variants
        $variants = get_post_meta($post_id, '_ps_size_variants', true);

        // Dynamic Specs (Custom Fields)
        $dynamic_specs = get_post_meta($post_id, '_ps_dynamic_specs', true);
        ?>
        <div class="ps-modal-layout">
            <div class="ps-modal-gallery">
                <?php echo $images_html; ?>
            </div>
            <div class="ps-modal-info ps-single-content">
                <!-- 卡片 1: 基础信息 -->
                <div class="ps-single-card ps-card-highlight">
                    <h1 class="ps-single-title"><?php echo get_the_title($post_id); ?></h1>

                    <div class="ps-single-meta-flex">
                        <?php if ($show_model === '1' && $model): ?>
                            <div class="ps-meta-item">
                                <span class="ps-meta-label">Model</span>
                                <span class="ps-meta-val ps-model-badge"><?php echo esc_html($model); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_list_price === '1' && $price): ?>
                            <div class="ps-meta-item ps-price-flag">
                                <span class="ps-meta-label">Price</span>
                                <span class="ps-meta-val ps-price-amount"><?php echo esc_html($price); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 卡片 2: 规格参数列表 -->
                <div class="ps-single-card">
                    <h3 class="ps-card-title">Specifications</h3>
                    <div class="ps-specs-list">
                        <?php if ($show_material === '1' && $material): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">Material</span>
                                <span class="ps-spec-val"><?php echo esc_html($material); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_moq === '1' && $moq): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">MOQ</span>
                                <span class="ps-spec-val"><?php echo esc_html($moq); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_loading === '1' && $loading): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">40HQ Loading</span>
                                <span class="ps-spec-val"><?php echo esc_html($loading); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_lead_time === '1' && $lead_time): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">Lead Time</span>
                                <span class="ps-spec-val"><?php echo esc_html($lead_time); ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- 动态参数 -->
                        <?php if (!empty($dynamic_specs) && is_array($dynamic_specs)): ?>
                            <?php foreach ($dynamic_specs as $spec): ?>
                                <?php $show_spec = isset($spec['show']) ? $spec['show'] : '1'; ?>
                                <?php if ($show_spec === '1' && !empty($spec['key'])): ?>
                                    <div class="ps-spec-row">
                                        <span class="ps-spec-key"><?php echo esc_html($spec['key']); ?></span>
                                        <span class="ps-spec-val"><?php echo esc_html($spec['val']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 卡片 3: 可选尺寸 (如果有) -->
                <?php
                $has_visible_sizes = false;
                if (!empty($variants) && is_array($variants)) {
                    foreach ($variants as $v) {
                        $show_v = isset($v['show']) ? $v['show'] : '1';
                        if ($show_v === '1') {
                            $has_visible_sizes = true;
                            break;
                        }
                    }
                }
                ?>
                <?php if ($has_visible_sizes): ?>
                    <div class="ps-single-card">
                        <h3 class="ps-card-title">Available Sizes</h3>
                        <div class="ps-variants-grid">
                            <?php foreach ($variants as $v): ?>
                                <?php $show_v = isset($v['show']) ? $v['show'] : '1'; ?>
                                <?php if ($show_v === '1'): ?>
                                    <div class="ps-variant-box">
                                        <div class="ps-variant-label"><?php echo esc_html($v['label']); ?></div>
                                        <div class="ps-variant-val"><?php echo esc_html($v['value']); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 卡片 4: 产品详情/描述 -->
                <?php if (trim($post->post_content)): ?>
                    <div class="ps-single-card ps-desc-card">
                        <h3 class="ps-card-title">Details</h3>
                        <div class="ps-single-desc">
                            <?php echo wp_kses_post(wpautop($post->post_content)); ?>
                        </div>
                    </div>
                <?php endif; ?>

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

        $args = [
            'post_type' => 'ps_item',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

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

        wp_send_json_success(['html' => $html]);
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

    /**
     * Fix #28: 统一前端产品详情页布局 (Mobile-first, Mini-program style)
     * 忽略后台编辑器（如 Elementor 等）自由排版，强制使用统一样式
     */
    public function render_single_product($content)
    {
        if (!is_singular('ps_item') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();

        // --- 收集数据 ---
        $watermark_text = get_option('ps_watermark_text', 'Pocket Showroom');
        $model = get_post_meta($post_id, '_ps_model', true);
        $material = get_post_meta($post_id, '_ps_material', true);
        $moq = get_post_meta($post_id, '_ps_moq', true);
        $loading = get_post_meta($post_id, '_ps_loading', true);
        $lead_time = get_post_meta($post_id, '_ps_lead_time', true);
        $price = get_post_meta($post_id, '_ps_list_price', true);
        $variants = get_post_meta($post_id, '_ps_size_variants', true);
        $dynamic_specs = get_post_meta($post_id, '_ps_dynamic_specs', true);

        // Visibility Toggles
        $show_model = get_post_meta($post_id, '_ps_show_model', true) !== '' ? get_post_meta($post_id, '_ps_show_model', true) : '1';
        $show_list_price = get_post_meta($post_id, '_ps_show_list_price', true) !== '' ? get_post_meta($post_id, '_ps_show_list_price', true) : '1';
        $show_material = get_post_meta($post_id, '_ps_show_material', true) !== '' ? get_post_meta($post_id, '_ps_show_material', true) : '1';
        $show_moq = get_post_meta($post_id, '_ps_show_moq', true) !== '' ? get_post_meta($post_id, '_ps_show_moq', true) : '1';
        $show_loading = get_post_meta($post_id, '_ps_show_loading', true) !== '' ? get_post_meta($post_id, '_ps_show_loading', true) : '1';
        $show_lead_time = get_post_meta($post_id, '_ps_show_lead_time', true) !== '' ? get_post_meta($post_id, '_ps_show_lead_time', true) : '1';

        // 轮播图配置
        $gallery_ids = get_post_meta($post_id, '_ps_gallery_images', true);
        $images_html = '';

        $wrap_img = function ($img_tag) use ($watermark_text) {
            if ($watermark_text) {
                return '<div class="ps-single-swiper-slide swiper-slide">' . $img_tag . '<div class="ps-watermark">' . esc_html($watermark_text) . '</div></div>';
            }
            return '<div class="ps-single-swiper-slide swiper-slide">' . $img_tag . '</div>';
        };

        if (!empty($gallery_ids)) {
            $ids = explode(',', $gallery_ids);
            foreach ($ids as $id) {
                $img_url = wp_get_attachment_image_url($id, 'large');
                if ($img_url) {
                    $img_tag = '<img src="' . esc_url($img_url) . '" alt="' . esc_attr(get_the_title()) . '">';
                    $images_html .= $wrap_img($img_tag);
                }
            }
        } else {
            $thumb = get_the_post_thumbnail_url($post_id, 'large');
            if ($thumb) {
                $img_tag = '<img src="' . esc_url($thumb) . '" alt="' . esc_attr(get_the_title()) . '">';
                $images_html = $wrap_img($img_tag);
            }
        }

        // --- 构建 HTML ---
        ob_start();
        ?>
        <!-- 引入 Swiper (如果没全局引入的话) -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

        <div class="ps-single-product-wrapper">
            <!-- 1. 顶部轮播图 -->
            <div class="ps-single-swiper swiper">
                <div class="swiper-wrapper">
                    <?php echo $images_html; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>

            <!-- 内容区域：卡片式结构 -->
            <div class="ps-single-content">

                <!-- 卡片 1: 基础信息 -->
                <div class="ps-single-card ps-card-highlight">
                    <h1 class="ps-single-title"><?php the_title(); ?></h1>

                    <div class="ps-single-meta-flex">
                        <?php if ($show_model === '1' && $model): ?>
                            <div class="ps-meta-item">
                                <span class="ps-meta-label">Model</span>
                                <span class="ps-meta-val ps-model-badge"><?php echo esc_html($model); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_list_price === '1' && $price): ?>
                            <div class="ps-meta-item ps-price-flag">
                                <span class="ps-meta-label">Price</span>
                                <span class="ps-meta-val ps-price-amount"><?php echo esc_html($price); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 卡片 2: 规格参数列表 -->
                <div class="ps-single-card">
                    <h3 class="ps-card-title">Specifications</h3>
                    <div class="ps-specs-list">
                        <?php if ($show_material === '1' && $material): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">Material</span>
                                <span class="ps-spec-val"><?php echo esc_html($material); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_moq === '1' && $moq): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">MOQ</span>
                                <span class="ps-spec-val"><?php echo esc_html($moq); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_loading === '1' && $loading): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">40HQ Loading</span>
                                <span class="ps-spec-val"><?php echo esc_html($loading); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($show_lead_time === '1' && $lead_time): ?>
                            <div class="ps-spec-row">
                                <span class="ps-spec-key">Lead Time</span>
                                <span class="ps-spec-val"><?php echo esc_html($lead_time); ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- 动态参数 -->
                        <?php if (!empty($dynamic_specs) && is_array($dynamic_specs)): ?>
                            <?php foreach ($dynamic_specs as $spec): ?>
                                <?php $show_spec = isset($spec['show']) ? $spec['show'] : '1'; ?>
                                <?php if ($show_spec === '1' && !empty($spec['key'])): ?>
                                    <div class="ps-spec-row">
                                        <span class="ps-spec-key"><?php echo esc_html($spec['key']); ?></span>
                                        <span class="ps-spec-val"><?php echo esc_html($spec['val']); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 卡片 3: 可选尺寸 (如果有) -->
                <?php
                $has_visible_sizes = false;
                if (!empty($variants) && is_array($variants)) {
                    foreach ($variants as $v) {
                        $show_v = isset($v['show']) ? $v['show'] : '1';
                        if ($show_v === '1') {
                            $has_visible_sizes = true;
                            break;
                        }
                    }
                }
                ?>
                <?php if ($has_visible_sizes): ?>
                    <div class="ps-single-card">
                        <h3 class="ps-card-title">Available Sizes</h3>
                        <div class="ps-variants-grid">
                            <?php foreach ($variants as $v): ?>
                                <?php $show_v = isset($v['show']) ? $v['show'] : '1'; ?>
                                <?php if ($show_v === '1'): ?>
                                    <div class="ps-variant-box">
                                        <div class="ps-variant-label"><?php echo esc_html($v['label']); ?></div>
                                        <div class="ps-variant-val"><?php echo esc_html($v['value']); ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 卡片 4: 产品详情/描述 -->
                <?php if (trim($content)): ?>
                    <div class="ps-single-card ps-desc-card">
                        <h3 class="ps-card-title">Details</h3>
                        <div class="ps-single-desc">
                            <?php echo do_shortcode($content); ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- 底部工具栏 -->
            <div class="ps-single-bottom-bar">
                <button class="ps-bottom-btn ps-copy-btn" onclick="psCopyCurrentUrl()">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path
                            d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z" />
                    </svg>
                    Copy Link
                </button>
            </div>

            <!-- Toast 通知 -->
            <div class="ps-toast" id="ps-global-toast">Link Copied</div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // 初始化轮播图
                if (typeof Swiper !== 'undefined') {
                    new Swiper('.ps-single-swiper', {
                        pagination: {
                            el: '.swiper-pagination',
                            clickable: true,
                        },
                        loop: false,
                        autoHeight: false
                    });
                }

                // 为了避免和自带的主题/内容冲突，我们将这个生成好的 wrapper 直接 append 到 content 中
                // 某些主题强行在外层加了很多 padding，我们把自身的样式定死
            });

            function psCopyCurrentUrl() {
                var dummy = document.createElement('input'), url = window.location.href;
                document.body.appendChild(dummy);
                dummy.value = url;
                dummy.select();
                document.execCommand('copy');
                document.body.removeChild(dummy);

                var toast = document.getElementById('ps-global-toast');
                toast.classList.add('show');
                setTimeout(function () { toast.classList.remove('show'); }, 2000);
            }
        </script>
        <?php
        return ob_get_clean();
    }
}

