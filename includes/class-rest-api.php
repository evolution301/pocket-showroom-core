<?php
/**
 * Pocket Showroom REST API
 *
 * @package PocketShowroom
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PS_REST_API
{
    const API_NAMESPACE = 'pocket-showroom/v1';

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
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register API routes
     */
    public function register_routes()
    {
        // --- Health Check ---
        register_rest_route(self::API_NAMESPACE, '/ping', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_ping'],
            'permission_callback' => '__return_true',
        ]);

        // --- Product List ---
        register_rest_route(self::API_NAMESPACE, '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => [
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default' => 12,
                    'sanitize_callback' => 'absint',
                ],
                'category' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // --- Single Product ---
        register_rest_route(self::API_NAMESPACE, '/products/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product'],
            'permission_callback' => '__return_true',
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // --- Categories ---
        register_rest_route(self::API_NAMESPACE, '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories'],
            'permission_callback' => '__return_true',
        ]);

        // --- Banner ---
        register_rest_route(self::API_NAMESPACE, '/banner', [
            'methods' => 'GET',
            'callback' => [$this, 'get_banner'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =============================================
    // Callbacks
    // =============================================

    /**
     * /ping
     */
    public function handle_ping()
    {
        return new WP_REST_Response([
            'status' => 'ok',
            'plugin' => 'pocket-showroom',
            'version' => defined('PS_CORE_VERSION') ? PS_CORE_VERSION : 'unknown',
            'site' => get_bloginfo('name'),
        ], 200);
    }

    // =============================================
    // Products
    // =============================================

    /**
     * GET /products
     */
    public function get_products($request)
    {
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $category = $request->get_param('category');
        $search = $request->get_param('search');

        $args = [
            'post_type' => 'ps_item',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // Category filter
        if (!empty($category)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'ps_category',
                    'field' => 'slug',
                    'terms' => $category,
                ],
            ];
        }

        // Search filter
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $items = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $items[] = $this->format_product_summary(get_the_ID());
            }
            wp_reset_postdata();
        }

        return new WP_REST_Response([
            'items' => $items,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'page' => $page,
            'per_page' => $per_page,
        ], 200);
    }

    /**
     * GET /products/:id
     */
    public function get_product($request)
    {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'ps_item' || $post->post_status !== 'publish') {
            return new WP_REST_Response([
                'error' => 'Product not found',
            ], 404);
        }

        return new WP_REST_Response($this->format_product_detail($post), 200);
    }

    // =============================================
    // Categories
    // =============================================

    /**
     * GET /categories
     */
    public function get_categories()
    {
        $terms = get_terms([
            'taxonomy' => 'ps_category',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        $categories = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $categories[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'count' => $term->count,
                ];
            }
        }

        return new WP_REST_Response([
            'categories' => $categories,
        ], 200);
    }

    // =============================================
    // Banner
    // =============================================

    /**
     * GET /banner
     */
    public function get_banner()
    {
        $banner_image_id = get_option('ps_banner_image_id');
        $banner_bg = '';
        if ($banner_image_id) {
            $banner_bg = wp_get_attachment_image_url($banner_image_id, 'full');
        }

        return new WP_REST_Response([
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
        ], 200);
    }

    // =============================================
    // Helpers
    // =============================================

    /**
     * Format for list view
     */
    private function format_product_summary($post_id)
    {
        $model = get_post_meta($post_id, '_ps_model', true);
        $price = get_post_meta($post_id, '_ps_list_price', true);

        $thumb_id = get_post_thumbnail_id($post_id);
        $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';

        // Categories
        $terms = get_the_terms($post_id, 'ps_category');
        $categories = [];
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = [
                    'id' => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ];
            }
        }

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'model' => $model ?: '',
            'price' => $price ?: '',
            'thumbnail' => $thumb_url,
            'categories' => $categories,
        ];
    }

    /**
     * Format for detail view
     */
    private function format_product_detail($post)
    {
        $post_id = $post->ID;

        // Gallery images (full URLs)
        $gallery_ids = get_post_meta($post_id, '_ps_gallery_images', true);
        $gallery = [];
        if (!empty($gallery_ids)) {
            $ids = explode(',', $gallery_ids);
            foreach ($ids as $img_id) {
                $url = wp_get_attachment_image_url($img_id, 'large');
                if ($url) {
                    $gallery[] = $url;
                }
            }
        }

        // Add featured image to gallery if not present
        $feat_id = get_post_thumbnail_id($post_id);
        if ($feat_id) {
            $feat_url = wp_get_attachment_image_url($feat_id, 'large');
            if ($feat_url && !in_array($feat_url, $gallery)) {
                array_unshift($gallery, $feat_url);
            }
        }

        // Size variants
        $size_variants = get_post_meta($post_id, '_ps_size_variants', true);
        if (!is_array($size_variants)) {
            $size_variants = [];
        }

        // Dynamic specs
        $dynamic_specs = get_post_meta($post_id, '_ps_dynamic_specs', true);
        if (!is_array($dynamic_specs)) {
            $dynamic_specs = [];
        }

        // Categories
        $terms = get_the_terms($post_id, 'ps_category');
        $categories = [];
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $t) {
                $categories[] = [
                    'id' => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ];
            }
        }

        return [
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
        ];
    }
}

// Initialize
PS_REST_API::get_instance();
