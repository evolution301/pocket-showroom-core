<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PS_CSV_Importer
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
        add_action('admin_menu', [$this, 'add_import_page']);
        add_action('admin_init', [$this, 'process_csv_import']);
        add_action('admin_init', [$this, 'process_csv_export']);
        add_action('admin_post_ps_download_template', [$this, 'handle_template_download']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'ps-import') !== false) {
            wp_enqueue_style('ps-admin-shared', PS_CORE_URL . 'assets/admin-shared.css', [], PS_CORE_VERSION);
        }
    }

    public function add_import_page()
    {
        add_submenu_page(
            'edit.php?post_type=ps_item',
            'Import / Export',
            'Import / Export',
            'manage_options',
            'ps-import',
            [$this, 'render_import_page']
        );
    }

    public function render_import_page()
    {
        ?>
        <div class="ps-modern-ui wrap">
            <div class="ps-header">
                <div class="ps-title-section">
                    <h1 class="wp-heading-inline" style="font-size: 24px; font-weight: 600;">
                        <?php _e('Data Tools', 'pocket-showroom'); ?>
                    </h1>
                </div>
            </div>

            <div class="ps-tools-container">
                <!-- Import Card -->
                <div class="ps-tool-card ps-import-card">
                    <div class="ps-card-icon"><span class="dashicons dashicons-cloud-upload"></span></div>
                    <h2><?php _e('Import Products', 'pocket-showroom'); ?></h2>
                    <p><?php _e('Bulk create or update products by uploading a CSV file.', 'pocket-showroom'); ?></p>

                    <form method="post" enctype="multipart/form-data" class="ps-import-form">
                        <?php wp_nonce_field('ps_csv_import', 'ps_csv_nonce'); ?>

                        <div class="ps-drop-zone" onclick="document.getElementById('ps_csv_file').click();">
                            <span class="dashicons dashicons-media-document"></span>
                            <p><?php _e('Click to select CSV file', 'pocket-showroom'); ?></p>
                            <input type="file" name="ps_csv_file" id="ps_csv_file" accept=".csv" required style="display:none;"
                                onchange="this.previousElementSibling.textContent = this.files[0].name;" />
                        </div>

                        <button type="submit" class="ps-btn ps-btn-primary ps-full-btn">
                            <?php _e('Run Importer', 'pocket-showroom'); ?>
                        </button>
                    </form>

                    <div class="ps-card-footer">
                        <a href="<?php echo esc_url(admin_url('admin-post.php?action=ps_download_template')); ?>" class="ps-text-link">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Download Sample Template', 'pocket-showroom'); ?>
                        </a>
                    </div>
                </div>

                <!-- Export Card -->
                <div class="ps-tool-card ps-export-card">
                    <div class="ps-card-icon"><span class="dashicons dashicons-cloud-download"></span></div>
                    <h2><?php _e('Export Products', 'pocket-showroom'); ?></h2>
                    <p><?php _e('Download a complete CSV file containing all your showroom items.', 'pocket-showroom'); ?></p>

                    <div class="ps-export-actions">
                        <form method="post">
                            <?php wp_nonce_field('ps_csv_export', 'ps_export_nonce'); ?>
                            <input type="hidden" name="ps_action" value="export_csv">
                            <button type="submit" class="ps-btn ps-btn-primary ps-full-btn">
                                <?php _e('Export to CSV', 'pocket-showroom'); ?>
                            </button>
                        </form>
                    </div>

                    <div class="ps-card-footer">
                        <p class="description">
                            <?php _e('Includes all metadata, image URLs, and variants.', 'pocket-showroom'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_template_download()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $file_path = PS_CORE_PATH . 'assets/sample-import.csv';
        if (!file_exists($file_path)) {
            wp_die('Template file not found.');
        }

        // Force download headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=sample-import.csv');
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache');
        header('Content-Length: ' . filesize($file_path));

        // UTF-8 BOM for Excel
        echo "\xEF\xBB\xBF";
        
        readfile($file_path);
        exit;
    }

    public function process_csv_export()
    {
        if (isset($_POST['ps_action']) && $_POST['ps_action'] == 'export_csv') {
            if (!isset($_POST['ps_export_nonce']) || !wp_verify_nonce($_POST['ps_export_nonce'], 'ps_csv_export')) {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            // Headers (Fix #18: 添加缓存控制和 BOM)
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=pocket-showroom-products-' . gmdate('Y-m-d') . '.csv');
            header('Pragma: no-cache');
            header('Cache-Control: no-store, no-cache');

            $output = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fwrite($output, "\xEF\xBB\xBF");

            // CSV Columns
            fputcsv($output, ['Product Name', 'Model No.', 'EXW Price', 'Category', 'Description', 'Material', 'MOQ', 'Loading', 'Delivery Time', 'Images', 'Size Variants', 'Custom Fields']);

            // Fetch Items
            $args = [
                'post_type' => 'ps_item',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ];
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    global $post;

                    $id = $post->ID;
                    $title = get_the_title();
                    $sku = get_post_meta($id, '_ps_model', true);
                    if (empty($sku))
                        $sku = get_post_meta($id, '_bfs_sku', true); // Legacy fallback

                    $price = get_post_meta($id, '_ps_list_price', true);
                    if (empty($price))
                        $price = get_post_meta($id, '_bfs_list_price', true); // Legacy fallback

                    $desc = $post->post_content;

                    // Category
                    $terms = get_the_terms($id, 'ps_category');
                    $category = '';
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $cat_names = wp_list_pluck($terms, 'name');
                        $category = implode(', ', $cat_names);
                    }

                    // Images: 合并 Featured Image + Gallery 为单列
                    // Featured Image 放最前面，与编辑器"第一张即封面"逻辑一致
                    $all_image_urls = [];

                    $thumb_id = get_post_thumbnail_id($id);
                    if ($thumb_id) {
                        $thumb_url = wp_get_attachment_url($thumb_id);
                        if ($thumb_url)
                            $all_image_urls[] = $thumb_url;
                    }

                    $gallery_ids = get_post_meta($id, '_ps_gallery_images', true);
                    if ($gallery_ids) {
                        $ids = explode(',', $gallery_ids);
                        foreach ($ids as $gid) {
                            // 跳过已作为 Featured Image 输出的（去重）
                            if ($thumb_id && intval($gid) === intval($thumb_id))
                                continue;
                            $url = wp_get_attachment_url($gid);
                            if ($url)
                                $all_image_urls[] = $url;
                        }
                    }
                    $images_str = implode(',', $all_image_urls);

                    // Standard Fields
                    $material = get_post_meta($id, '_ps_material', true);
                    $moq = get_post_meta($id, '_ps_moq', true);
                    $loading = get_post_meta($id, '_ps_loading', true);
                    $lead_time = get_post_meta($id, '_ps_lead_time', true);

                    // Size Variants
                    $variants = get_post_meta($id, '_ps_size_variants', true);
                    $variant_str = '';
                    if (is_array($variants)) {
                        $v_parts = [];
                        foreach ($variants as $v) {
                            $v_parts[] = $v['label'] . ':' . $v['value'];
                        }
                        $variant_str = implode(' | ', $v_parts);
                    } else {
                        $legacy_size = get_post_meta($id, '_ps_size', true);
                        if ($legacy_size)
                            $variant_str = 'Standard:' . $legacy_size;
                    }

                    // Custom Fields (Dynamic Specs)
                    $dynamic_specs = get_post_meta($id, '_ps_dynamic_specs', true);
                    $specs_str = '';
                    if (is_array($dynamic_specs) && !empty($dynamic_specs)) {
                        $s_parts = [];
                        foreach ($dynamic_specs as $spec) {
                            if (!empty($spec['key'])) {
                                $s_parts[] = $spec['key'] . ':' . $spec['val'];
                            }
                        }
                        $specs_str = implode(' | ', $s_parts);
                    }

                    fputcsv($output, [$title, $sku, $price, $category, $desc, $material, $moq, $loading, $lead_time, $images_str, $variant_str, $specs_str]);
                }
            }
            wp_reset_postdata();

            // Fix BUG: Close and instantly die to prevent WP from rendering the admin page HTML into the CSV file
            fclose($output);
            exit;
        }
    }

    public function process_csv_import()
    {
        if (!isset($_POST['ps_csv_nonce']) || !wp_verify_nonce($_POST['ps_csv_nonce'], 'ps_csv_import')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!empty($_FILES['ps_csv_file']['tmp_name'])) {
            // Fix #20: Large batch imports need more time
            @set_time_limit(0);
            ini_set('auto_detect_line_endings', '1');

            $csv_file = $_FILES['ps_csv_file']['tmp_name'];
            $file_content = file_get_contents($csv_file);

            if ($file_content !== FALSE) {
                // 1. Strip UTF-8 BOM if present
                if (substr($file_content, 0, 3) === "\xEF\xBB\xBF") {
                    $file_content = substr($file_content, 3);
                }

                // 2. Detect and fix encoding (GBK/ANSI fallback)
                $encoding = mb_detect_encoding($file_content, ['UTF-8', 'GBK', 'BIG5', 'CP936'], true);
                if ($encoding && $encoding !== 'UTF-8') {
                    $file_content = mb_convert_encoding($file_content, 'UTF-8', $encoding);
                }

                // 3. Auto-detect delimiter
                $delimiters = [',', ';', "\t"];
                $delimiter = ',';
                $max_count = 0;
                $first_line = strtok($file_content, "\r\n");
                foreach ($delimiters as $d) {
                    $count = count(str_getcsv($first_line, $d));
                    if ($count > $max_count) {
                        $max_count = $count;
                        $delimiter = $d;
                    }
                }

                // 4. Parse rows
                $lines = explode("\n", str_replace("\r", "", $file_content));
                $raw_rows = array_map(function($line) use ($delimiter) {
                    return str_getcsv($line, $delimiter);
                }, array_filter($lines));

                if (count($raw_rows) < 1) return;

                $raw_header = $raw_rows[0];
                $header = array_map('trim', array_map('strtolower', $raw_header));
                
                // Fuzzy find column index
                $find_idx = function($keywords, $header) {
                    foreach ($keywords as $kw) {
                        $kw = strtolower($kw);
                        // Exact match
                        $idx = array_search($kw, $header);
                        if ($idx !== false) return $idx;
                        // Fuzzy match (substring)
                        foreach ($header as $i => $h) {
                            if (!empty($h) && (strpos($h, $kw) !== false || strpos($kw, $h) !== false)) {
                                return $i;
                            }
                        }
                    }
                    return false;
                };

                // Define keywords
                $map = [
                    'title'       => $find_idx(['product name', 'title', '产品名称', '名称', '品名', '产品标题'], $header),
                    'sku'         => $find_idx(['model no', 'sku', '型号', '款号', '编码'], $header),
                    'price'       => $find_idx(['price', '价格', '单价', '出厂价'], $header),
                    'category'    => $find_idx(['category', '分类', '系列'], $header),
                    'desc'        => $find_idx(['description', 'desc', '描述', '介绍', '正文'], $header),
                    'material'    => $find_idx(['material', '材质', '材料'], $header),
                    'moq'         => $find_idx(['moq', '起订量', '最小起订'], $header),
                    'loading'     => $find_idx(['loading', '装柜', '装载'], $header),
                    'lead_time'   => $find_idx(['lead time', 'delivery', '货期', '交货'], $header),
                    'images'      => $find_idx(['images', 'image', '图片', '图集', '照片'], $header),
                    'variants'    => $find_idx(['variants', 'size', '规格', '尺寸', '变体'], $header),
                    'custom'      => $find_idx(['custom fields', 'specs', '自定义', '参数', '规格字段'], $header),
                ];

                // Global fallback for critical columns if fuzzy failed
                if ($map['sku'] === false) $map['sku'] = 1;
                if ($map['title'] === false) $map['title'] = 0;

                $created = 0;
                $updated = 0;
                $skipped = 0;
                $errors  = 0;
                $error_messages = [];

                for ($i = 1; $i < count($raw_rows); $i++) {
                    $data = $raw_rows[$i];
                    if (count($data) < 1) {
                        $skipped++;
                        continue;
                    }

                    // Trim all cells
                    $data = array_map(function($val) {
                        return $val === null ? '' : trim((string)$val);
                    }, $data);

                    // Skip effectively empty rows
                    $title_val = ($map['title'] !== false && isset($data[$map['title']])) ? $data[$map['title']] : '';
                    $sku_val   = ($map['sku'] !== false && isset($data[$map['sku']])) ? $data[$map['sku']] : '';

                    if (empty($title_val) && empty($sku_val)) {
                        $skipped++;
                        continue;
                    }

                    // Extract data based on map
                    $title       = sanitize_text_field($title_val);
                    $sku         = sanitize_text_field($sku_val);
                    $price       = ($map['price'] !== false && isset($data[$map['price']])) ? sanitize_text_field($data[$map['price']]) : '';
                    $category    = ($map['category'] !== false && isset($data[$map['category']])) ? sanitize_text_field($data[$map['category']]) : '';
                    $description = ($map['desc'] !== false && isset($data[$map['desc']])) ? wp_kses_post($data[$map['desc']]) : '';
                    $material    = ($map['material'] !== false && isset($data[$map['material']])) ? sanitize_text_field($data[$map['material']]) : '';
                    $moq         = ($map['moq'] !== false && isset($data[$map['moq']])) ? sanitize_text_field($data[$map['moq']]) : '';
                    $loading     = ($map['loading'] !== false && isset($data[$map['loading']])) ? sanitize_text_field($data[$map['loading']]) : '';
                    $lead_time   = ($map['lead_time'] !== false && isset($data[$map['lead_time']])) ? sanitize_text_field($data[$map['lead_time']]) : '';
                    $images_raw  = ($map['images'] !== false && isset($data[$map['images']])) ? $data[$map['images']] : '';
                    $variants_raw = ($map['variants'] !== false && isset($data[$map['variants']])) ? $data[$map['variants']] : '';
                    $custom_fields_raw = ($map['custom'] !== false && isset($data[$map['custom']])) ? $data[$map['custom']] : '';

                    // Check if product exists by SKU
                    $existing_id = $this->get_product_by_sku($sku);

                    $post_data = [
                        'post_title' => $title ? $title : $sku, // Use SKU if title is blank
                        'post_content' => $description,
                        'post_status' => 'publish',
                        'post_type' => 'ps_item',
                    ];

                    if ($existing_id) {
                        $post_data['ID'] = $existing_id;
                        $post_id = wp_update_post($post_data);
                    } else {
                        $post_id = wp_insert_post($post_data);
                    }

                    if ($post_id && !is_wp_error($post_id)) {
                        // Metadata Updates
                        update_post_meta($post_id, '_ps_model', $sku);
                        update_post_meta($post_id, '_ps_list_price', $price);
                        update_post_meta($post_id, '_ps_material', $material);
                        update_post_meta($post_id, '_ps_moq', $moq);
                        update_post_meta($post_id, '_ps_loading', $loading);
                        update_post_meta($post_id, '_ps_lead_time', $lead_time);

                        // Sync exhibition toggles
                        update_post_meta($post_id, '_ps_show_model', !empty($sku) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_list_price', !empty($price) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_material', !empty($material) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_moq', !empty($moq) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_loading', !empty($loading) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_lead_time', !empty($lead_time) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_sizes', !empty($variants_raw) ? '1' : '0');
                        update_post_meta($post_id, '_ps_show_custom_fields', !empty($custom_fields_raw) ? '1' : '0');

                        // Parse Variants
                        if (!empty($variants_raw)) {
                            $variants_array = [];
                            $variant_items = explode('|', $variants_raw);
                            foreach ($variant_items as $v_item) {
                                $parts = explode(':', trim($v_item));
                                if (count($parts) >= 2) {
                                    $variants_array[] = ['label' => trim($parts[0]), 'value' => trim($parts[1])];
                                }
                            }
                            update_post_meta($post_id, '_ps_size_variants', $variants_array);
                        }

                        // Category
                        if ($category) {
                            $categories = array_map('trim', explode(',', $category));
                            wp_set_object_terms($post_id, $categories, 'ps_category', false);
                        }

                        // Images
                        if (!empty($images_raw)) {
                            $urls = array_map('trim', explode(',', $images_raw));
                            $gallery_ids = [];
                            $is_first = true;
                            foreach ($urls as $url) {
                                if (empty($url)) continue;
                                $att_id = $this->upload_image_from_url(esc_url_raw($url), $post_id, $is_first);
                                if ($att_id) $gallery_ids[] = $att_id;
                                $is_first = false;
                            }
                            if (!empty($gallery_ids)) {
                                update_post_meta($post_id, '_ps_gallery_images', implode(',', $gallery_ids));
                            }
                        }

                        // Custom Fields
                        if (!empty($custom_fields_raw)) {
                            $specs_array = [];
                            $spec_items = explode('|', $custom_fields_raw);
                            foreach ($spec_items as $s_item) {
                                $parts = explode(':', trim($s_item), 2);
                                if (count($parts) >= 2) {
                                    $specs_array[] = [
                                        'key' => sanitize_text_field(trim($parts[0])),
                                        'val' => sanitize_text_field(trim($parts[1]))
                                    ];
                                }
                            }
                            update_post_meta($post_id, '_ps_dynamic_specs', $specs_array);
                        }

                        if ($existing_id) {
                            $updated++;
                        } else {
                            $created++;
                        }
                    } else {
                        $errors++;
                        if (is_wp_error($post_id)) {
                            $error_messages[] = $title . ': ' . $post_id->get_error_message();
                        }
                    }
                }

                add_action('admin_notices', function () use ($created, $updated, $skipped, $errors, $error_messages) {
                    $class = ($created > 0 || $updated > 0) ? 'notice-success' : 'notice-warning';
                    echo '<div class="notice ' . $class . ' is-dismissible">';
                    echo '<p><strong>' . __('Import Summary:', 'pocket-showroom') . '</strong> ';
                    printf(__('Created: %d | Updated: %d | Skipped: %d | Errors: %d', 'pocket-showroom'), $created, $updated, $skipped, $errors);
                    if (!empty($error_messages)) {
                        echo '<br/>' . __('Details:', 'pocket-showroom') . ' ' . esc_html(implode(', ', array_slice($error_messages, 0, 5)));
                        if (count($error_messages) > 5) echo '...';
                    }
                    echo '</p></div>';
                });
            }
        }
    }

    private function get_product_by_sku($sku)
    {
        // Fix #17: 空 SKU 直接返回 false，防止误匹配
        if (empty($sku)) {
            return false;
        }

        $args = [
            'post_type' => 'ps_item',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_ps_model',
                    'value' => $sku,
                ],
                [
                    'key' => '_bfs_sku', // Legacy support
                    'value' => $sku,
                ],
            ],
            'fields' => 'ids',
        ];
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }

    private function upload_image_from_url($url, $post_id, $is_featured = false)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL))
            return false;

        // --- STEP 1: Fast URL Match (Is this an existing site URL?) ---
        $existing_id = attachment_url_to_postid($url);
        if ($existing_id) {
            if ($is_featured)
                set_post_thumbnail($post_id, $existing_id);
            return $existing_id;
        }

        // --- STEP 2: Database Filename Match (Double check against duplicates) ---
        global $wpdb;
        $filename = basename(parse_url($url, PHP_URL_PATH));
        if ($filename) {
            // Find attachment by checking _wp_attached_file meta
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_wp_attached_file' 
                 AND meta_value LIKE %s 
                 LIMIT 1",
                '%' . $wpdb->esc_like($filename)
            );
            $meta_id = $wpdb->get_var($query);
            if ($meta_id) {
                // Confirm the post actually exists and is an attachment
                if (get_post_type($meta_id) === 'attachment') {
                    if ($is_featured)
                        set_post_thumbnail($post_id, $meta_id);
                    return $meta_id;
                }
            }
        }

        // --- STEP 3: Fallback - Actually Download and Sideload ---
        // SSRF 防护: 仅允许 HTTP/HTTPS 协议
        $scheme = wp_parse_url($url, PHP_URL_SCHEME);
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        // SSRF 防护: 阻止私有 IP 和 localhost
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $host_lower = strtolower($host);
        $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array($host_lower, $blocked, true)) {
            return false;
        }
        // 阻止私有 IP 段: 10.x, 172.16-31.x, 192.168.x, 169.254.x
        $ip = gethostbyname($host);
        if ($ip && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $tmp = download_url($url);
        if (is_wp_error($tmp))
            return false;

        $file_array = [
            'name' => basename($url),
            'tmp_name' => $tmp
        ];

        $id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }

        if ($is_featured) {
            set_post_thumbnail($post_id, $id);
        }

        return $id;
    }
}
