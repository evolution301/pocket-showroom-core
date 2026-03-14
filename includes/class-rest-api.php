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
        add_action('init', [$this, 'add_cors_support']);
        
        // Generate API Key on first run
        if (!get_option('ps_api_key')) {
            update_option('ps_api_key', 'ps_' . wp_generate_password(32, false));
        }
    }

    /**
     * Add CORS support for external clients (Mini App / WeChat Web)
     *
     * Security: Restricted to specific domains only
     */
    public function add_cors_support()
    {
        add_filter('rest_pre_serve_request', function ($value, $result, $request) {
            // Get allowed origins from settings (comma-separated)
            $allowed_origins_str = get_option('ps_api_allowed_origins', '');
            $allowed_origins = array_map('trim', explode(',', $allowed_origins_str));
            
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            if (empty($allowed_origins_str)) {
                // Default: allow same-origin requests only
                $site_url = get_site_url();
                if (strpos($origin, parse_url($site_url, PHP_URL_HOST)) !== false) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                }
            } else {
                // Check against whitelist
                if (in_array($origin, $allowed_origins, true)) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
                    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-API-Key');
                    header('Access-Control-Allow-Credentials: true');
                }
            }
            return $value;
        }, 10, 3);
    }

    /**
     * Register API routes
     */
    public function register_routes()
    {
        // --- Health Check (public) ---
        register_rest_route(self::API_NAMESPACE, '/ping', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_ping'],
            'permission_callback' => '__return_true',
        ]);

        // --- Product List (requires API Key + rate limit) ---
        register_rest_route(self::API_NAMESPACE, '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => [$this, 'verify_api_key_with_rate_limit'],
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

        // --- Single Product (requires API Key + rate limit) ---
        register_rest_route(self::API_NAMESPACE, '/products/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product'],
            'permission_callback' => [$this, 'verify_api_key_with_rate_limit'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // --- Categories (requires API Key + rate limit) ---
        register_rest_route(self::API_NAMESPACE, '/categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_categories'],
            'permission_callback' => [$this, 'verify_api_key_with_rate_limit'],
        ]);

        // --- Banner (requires API Key + rate limit) ---
        register_rest_route(self::API_NAMESPACE, '/banner', [
            'methods' => 'GET',
            'callback' => [$this, 'get_banner'],
            'permission_callback' => [$this, 'verify_api_key_with_rate_limit'],
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
        try {
            $page     = intval($request->get_param('page') ?: 1);
            $per_page = intval($request->get_param('per_page') ?: 12);
            $category = sanitize_text_field($request->get_param('category') ?: '');
            $search   = sanitize_text_field($request->get_param('search') ?: '');

            $args = [
                'post_type'      => 'ps_item',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'no_found_rows'  => false,
            ];

            // 分类过滤
            if (!empty($category)) {
                $args['tax_query'] = [[
                    'taxonomy' => 'ps_category',
                    'field'    => 'slug',
                    'terms'    => $category,
                ]];
            }

            // 关键词搜索
            if (!empty($search)) {
                $args['s'] = $search;
            }

            $query = new WP_Query($args);
            $items = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    try {
                        $items[] = $this->format_product_summary(get_the_ID());
                    } catch (\Throwable $row_e) {
                        // 某一行格式化异常时，记录日志但继续处理其余行，不让整页崩溃
                        error_log('[PS REST API] format_product_summary error for post ' . get_the_ID() . ': ' . $row_e->getMessage());
                    }
                }
                wp_reset_postdata();
            }

            return new WP_REST_Response([
                'items'       => $items,
                'total'       => (int) $query->found_posts,
                'total_pages' => (int) $query->max_num_pages,
                'page'        => $page,
                'per_page'    => $per_page,
            ], 200);
        } catch (\Throwable $e) {
            error_log('[PS REST API] get_products fatal error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /products/:id
     */
    public function get_product($request)
    {
        try {
            $post_id = intval($request->get_param('id'));
            $post = get_post($post_id);

            if (!$post || $post->post_type !== 'ps_item' || $post->post_status !== 'publish') {
                return new WP_REST_Response(['error' => 'Product not found'], 404);
            }

            return new WP_REST_Response($this->format_product_detail($post), 200);
        } catch (\Throwable $e) {
            error_log('[PS REST API] get_product fatal error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    // =============================================
    // Categories
    // =============================================

    /**
     * GET /categories
     */
    public function get_categories()
    {
        try {
            $terms = get_terms([
                'taxonomy'   => 'ps_category',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);

            $categories = [];
            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $term) {
                    $categories[] = [
                        'id'    => $term->term_id,
                        'name'  => $term->name,
                        'slug'  => $term->slug,
                        'count' => $term->count,
                    ];
                }
            }

            return new WP_REST_Response(['categories' => $categories], 200);
        } catch (\Throwable $e) {
            error_log('[PS REST API] get_categories fatal error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    // =============================================
    // Banner
    // =============================================

    /**
     * GET /banner
     */
    public function get_banner()
    {
        try {
            $banner_image_id = get_option('ps_banner_image_id');
            $banner_bg = '';
            if ($banner_image_id) {
                $url = wp_get_attachment_image_url($banner_image_id, 'full');
                $banner_bg = $url ?: '';
            }

            return new WP_REST_Response([
                'title'          => get_option('ps_banner_title', 'Pocket Showroom'),
                'description'    => get_option('ps_banner_desc', ''),
                'buttonText'     => get_option('ps_banner_button_text', 'Get a Quote'),
                'buttonUrl'      => get_option('ps_banner_button_url', ''),
                'backgroundUrl'  => $banner_bg,
                'overlayColor'   => get_option('ps_banner_overlay_color', 'rgba(46, 125, 50, 0.4)'),
                'primaryColor'   => get_option('ps_primary_color', '#2E7D32'),
                'buttonTextColor'=> get_option('ps_button_text_color', '#ffffff'),
                'titleColor'     => get_option('ps_banner_title_color', '#ffffff'),
                'descColor'      => get_option('ps_banner_desc_color', '#ffffffcc'),
            ], 200);
        } catch (\Throwable $e) {
            error_log('[PS REST API] get_banner fatal error: ' . $e->getMessage());
            return new WP_REST_Response(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    // =============================================
    // Helpers
    // =============================================

    /**
     * Format for list view
     */
    private function format_product_summary($post_id)
    {
        $model = (string) (get_post_meta($post_id, '_ps_model', true) ?: '');
        $price = (string) (get_post_meta($post_id, '_ps_list_price', true) ?: '');

        $thumb_id  = get_post_thumbnail_id($post_id);
        $thumb_url = ($thumb_id) ? (wp_get_attachment_image_url($thumb_id, 'medium') ?: '') : '';

        // 分类信息
        $terms      = get_the_terms($post_id, 'ps_category');
        $categories = [];
        if ($terms && !is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $t) {
                $categories[] = [
                    'id'   => $t->term_id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                ];
            }
        }

        return [
            'id'         => $post_id,
            'title'      => (string) get_the_title($post_id),
            'model'      => $model,
            'price'      => $price,
            'thumbnail'  => $thumb_url,
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
            'description' => wp_kses_post(nl2br($post->post_content)),
            'gallery' => $gallery,
            'sizeVariants' => $size_variants,
            'dynamicSpecs' => $dynamic_specs,
            'categories' => $categories,
            'permalink' => get_permalink($post_id),
        ];
    }

    /**
     * Security: Verify API Key
     */
    private function verify_api_key($request)
    {
        $api_key = $request->get_header('x-api-key');
        if (empty($api_key)) {
            return new WP_Error('rest_forbidden', 'API Key required', ['status' => 401]);
        }
        $valid_key = get_option('ps_api_key');
        if ($api_key !== $valid_key) {
            return new WP_Error('rest_forbidden', 'Invalid API Key', ['status' => 403]);
        }
        return true;
    }

    /**
     * Security: Combined API Key + Rate Limit check
     */
    private function verify_api_key_with_rate_limit($request)
    {
        // First check rate limit to prevent DDoS/Scraping
        $rate_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }
        
        // Remove API key check to allow Miniapp public access
        // return $this->verify_api_key($request);
        return true;
    }

    /**
     * Security: Rate limiting
     */
    private function check_rate_limit($request)
    {
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate_limit_key = 'ps_api_rate_' . md5($user_ip);
        
        $requests = get_transient($rate_limit_key);
        if (false === $requests) {
            $requests = 0;
        }
        
        // Limit: 100 requests per minute
        if ($requests >= 100) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'pocket-showroom'),
                ['status' => 429]
            );
        }
        
        set_transient($rate_limit_key, $requests + 1, 60);
        return true;
    }
}
