<?php
/**
 * REST API for Pocket Showroom
 * 
 * Provides public endpoints for the WeChat Mini Program (and any other client)
 * to read product data from this WordPress site.
 *
 * Endpoints:
 *   GET /wp-json/ps/v1/products          — Product list (paginated, filterable)
 *   GET /wp-json/ps/v1/products/{id}     — Single product detail
 *   GET /wp-json/ps/v1/categories        — Category (Collection) list
 *   GET /wp-json/ps/v1/banner            — Banner configuration
 *   GET /wp-json/ps/v1/ping              — Health check (verify plugin is active)
 *
 * @package PocketShowroom
 */

if (!defined('ABSPATH')) {
    exit;
}

class PS_REST_API
{
    /**
     * API namespace
     */
    const API_NAMESPACE = 'ps/v1';

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
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register all REST API routes
     */
    public function register_routes()
    {
        // --- Health Check ---
        register_rest_route(self::API_NAMESPACE, '/ping', array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_ping'),
            'permission_callback' => '__return_true',
        ));

        // --- Product List ---
        register_rest_route(self::API_NAMESPACE, '/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_products'),
            'permission_callback' => '__return_true',
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ),
                'per_page' => array(
                    'default' => 12,
                    'sanitize_callback' => 'absint',
                ),
                'category' => array(
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'search' => array(
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // --- Single Product ---
        register_rest_route(self::API_NAMESPACE, '/products/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_product'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ),
            ),
        ));

        // --- Categories ---
        register_rest_route(self::API_NAMESPACE, '/categories', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_categories'),
            'permission_callback' => '__return_true',
        ));

        // --- Banner ---
        register_rest_route(self::API_NAMESPACE, '/banner', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_banner'),
            'permission_callback' => '__return_true',
        ));
    }

    // =============================================
    //  Ping / Health Check
    // =============================================

    /**
     * Health check endpoint — used by Mini Program to verify that
     * this WordPress site has the PS plugin installed and active.
     *
     * @return WP_REST_Response
     */
    public function handle_ping()
    {
        return new WP_REST_Response(array(
            'status' => 'ok',
            'plugin' => 'pocket-showroom',
            'version' => defined('PS_CORE_VERSION') ? PS_CORE_VERSION : 'unknown',
            'site' => get_bloginfo('name'),
        ), 200);
    }

    // =============================================
    //  Products
    // =============================================

    /**
     * Get paginated product list.
     *
     * Query params:
     *   page     — Page number (default: 1)
     *   per_page — Items per page (default: 12, max: 50)
     *   category — Filter by category slug
     *   search   — Search keyword
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_products($request)
    {
        $page = max(1, $request->get_param('page'));
        $per_page = min(50, max(1, $request->get_param('per_page')));
        $category = $request->get_param('category');
        $search = $request->get_param('search');

        $args = array(
            'post_type' => 'ps_item',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Category filter
        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'ps_category',
                    'field' => 'slug',
                    'terms' => $category,
                ),
            );
        }

        // Search filter
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $items = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $items[] = $this->format_product_summary(get_the_ID());
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response(array(
            'items' => $items,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ), 200);
    }

    /**
     * Get single product detail.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_product($request)
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'ps_item' || $post->post_status !== 'publish') {
            return new WP_REST_Response(array(
                'error' => 'Product not found',
            ), 404);
        }

        return new WP_REST_Response($this->format_product_detail($post), 200);
    }

    // =============================================
    //  Categories
    // =============================================

    /**
     * Get all product categories (Collections).
     *
     * @return WP_REST_Response
     */
    public function get_categories()
    {
        $terms = get_terms(array(
            'taxonomy' => 'ps_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        $categories = array();

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count,
                );
            }
        }

        return new WP_REST_Response(array(
            'categories' => $categories,
        ), 200);
    }

    // =============================================
    //  Banner
    // =============================================

    /**
     * Get banner configuration from plugin settings.
     *
     * @return WP_REST_Response
     */
    public function get_banner()
    {
        $banner_image_id = get_option('ps_banner_image_id');
        $banner_bg = '';
        if ($banner_image_id) {
            $banner_bg = wp_get_attachment_image_url($banner_image_id, 'full');
        }

        return new WP_REST_Response(array(
            'title' => get_option('ps_banner_title', 'Pocket Showroom'),
            'description' => get_option('ps_banner_desc', ''),
            'buttonText' => get_option('ps_banner_button_text', 'Get a Quote'),
            'buttonUrl' => get_option('ps_banner_button_url', ''),
            'backgroundUrl' => $banner_bg ?: '',
            'overlayColor' => get_option('ps_banner_overlay_color', 'rgba(46, 125, 50, 0.4)'),
            'primaryColor' => get_option('ps_primary_color', '#2E7D32'),
            'buttonTextColor' => get_option('ps_button_text_color', '#ffffff'),
            'titleColor' => get_option('ps_banner_title_color', '#ffffff'),
            'descColor' => get_option('ps_banner_desc_color', '#ffffffcc'),
        ), 200);
    }

    // =============================================
    //  Data Formatting Helpers
    // =============================================

    /**
     * Format a product for the list view (summary, lightweight).
     *
     * @param int $post_id
     * @return array
     */
    private function format_product_summary($post_id)
    {
        // Thumbnail (cover image)
        $thumb_url = get_the_post_thumbnail_url($post_id, 'medium');
        if (!$thumb_url) {
            $thumb_url = '';
        }

        // Model number
        $model = get_post_meta($post_id, '_ps_model', true);

        // Price
        $price = get_post_meta($post_id, '_ps_list_price', true);

        // Categories
        $terms = get_the_terms($post_id, 'ps_category');
        $categories = array();
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = array(
                    'id' => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                );
            }
        }

        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'model' => $model ?: '',
            'price' => $price ?: '',
            'thumbnail' => $thumb_url,
            'categories' => $categories,
        );
    }

    /**
     * Format a product for the detail view (full data).
     *
     * @param WP_Post $post
     * @return array
     */
    private function format_product_detail($post)
    {
        $post_id = $post->ID;

        // Gallery images (full URLs)
        $gallery_ids = get_post_meta($post_id, '_ps_gallery_images', true);
        $gallery = array();
        if (!empty($gallery_ids)) {
            $ids = explode(',', $gallery_ids);
            foreach ($ids as $img_id) {
                $url = wp_get_attachment_image_url(trim($img_id), 'large');
                if ($url) {
                    $gallery[] = $url;
                }
            }
        }

        // If no gallery, try featured image
        if (empty($gallery)) {
            $thumb = get_the_post_thumbnail_url($post_id, 'large');
            if ($thumb) {
                $gallery[] = $thumb;
            }
        }

        // Size variants
        $size_variants = get_post_meta($post_id, '_ps_size_variants', true);
        if (!is_array($size_variants)) {
            $size_variants = array();
        }

        // Dynamic specs
        $dynamic_specs = get_post_meta($post_id, '_ps_dynamic_specs', true);
        if (!is_array($dynamic_specs)) {
            $dynamic_specs = array();
        }

        // Categories
        $terms = get_the_terms($post_id, 'ps_category');
        $categories = array();
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = array(
                    'id' => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                );
            }
        }

        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'model' => get_post_meta($post_id, '_ps_model', true) ?: '',
            'price' => get_post_meta($post_id, '_ps_list_price', true) ?: '',
            'material' => get_post_meta($post_id, '_ps_material', true) ?: '',
            'moq' => get_post_meta($post_id, '_ps_moq', true) ?: '',
            'loading' => get_post_meta($post_id, '_ps_loading', true) ?: '',
            'leadTime' => get_post_meta($post_id, '_ps_lead_time', true) ?: '',
            'description' => wp_kses_post($post->post_content),
            'gallery' => $gallery,
            'sizeVariants' => $size_variants,
            'dynamicSpecs' => $dynamic_specs,
            'categories' => $categories,
            'permalink' => get_permalink($post_id),
        );
    }
}
