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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'ps-import') !== false) {
            wp_enqueue_style('ps-admin-style', PS_CORE_URL . 'assets/admin-style.css', [], PS_CORE_VERSION);
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
                        <a href="<?php echo PS_CORE_URL . 'assets/sample-import.csv'; ?>" download class="ps-text-link">
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
                    $category = $terms ? $terms[0]->name : '';

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
            $csv_file = $_FILES['ps_csv_file']['tmp_name'];
            $handle = fopen($csv_file, 'r');

            if ($handle !== FALSE) {
                $header = fgetcsv($handle); // Skip header

                // CSV 列顺序: Product Name, Model No., EXW Price, Category, Description, Material, MOQ, Loading, Delivery Time, Images, Size Variants, Custom Fields
                // Images 列: 逗号分隔的 URL，第一张自动设为封面图
                // Custom Fields 列: key:value | key:value 格式

                $imported = 0;

                while (($data = fgetcsv($handle)) !== FALSE) {
                    // 至少要有 Title (5列最低限度)
                    if (count($data) < 5) {
                        continue;
                    }

                    $title = sanitize_text_field($data[0]);
                    $sku = sanitize_text_field($data[1]);
                    $price = sanitize_text_field($data[2]);
                    $category = sanitize_text_field($data[3]);
                    $description = wp_kses_post($data[4]);
                    $material = isset($data[5]) ? sanitize_text_field($data[5]) : '';
                    $moq = isset($data[6]) ? sanitize_text_field($data[6]) : '';
                    $loading = isset($data[7]) ? sanitize_text_field($data[7]) : '';
                    $lead_time = isset($data[8]) ? sanitize_text_field($data[8]) : '';
                    $images_raw = isset($data[9]) ? $data[9] : '';
                    $variants_raw = isset($data[10]) ? $data[10] : '';
                    $custom_fields_raw = isset($data[11]) ? $data[11] : '';

                    // Check if product exists by SKU
                    $existing_id = $this->get_product_by_sku($sku);

                    $post_data = [
                        'post_title' => $title,
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

                    if ($post_id) {
                        update_post_meta($post_id, '_ps_model', $sku);
                        update_post_meta($post_id, '_ps_list_price', $price);

                        // Standard Fields — 更新模式下始终写入（允许清空旧值）
                        update_post_meta($post_id, '_ps_material', $material);
                        update_post_meta($post_id, '_ps_moq', $moq);
                        update_post_meta($post_id, '_ps_loading', $loading);
                        update_post_meta($post_id, '_ps_lead_time', $lead_time);

                        // Parse Variants
                        if (!empty($variants_raw)) {
                            $variants_array = [];
                            $variant_items = explode('|', $variants_raw);
                            foreach ($variant_items as $v_item) {
                                $parts = explode(':', $v_item);
                                if (count($parts) >= 2) {
                                    $variants_array[] = [
                                        'label' => trim($parts[0]),
                                        'value' => trim($parts[1])
                                    ];
                                }
                            }
                            if (!empty($variants_array)) {
                                update_post_meta($post_id, '_ps_size_variants', $variants_array);
                            }
                        }

                        // Handle Category (Multi-support)
                        if ($category) {
                            // Split by comma, trim whitespace
                            $categories = array_map('trim', explode(',', $category));
                            // true = append, false = replace. Use false to sync exactly with CSV.
                            wp_set_object_terms($post_id, $categories, 'ps_category', false);
                        }

                        // Handle Images: 第一张设为封面，全部存入 Gallery
                        if (!empty($images_raw)) {
                            $urls = array_map('trim', explode(',', $images_raw));
                            $gallery_ids = [];
                            $is_first = true;

                            foreach ($urls as $url) {
                                if (empty($url))
                                    continue;
                                $url = esc_url_raw($url);
                                // 第一张同时设为 Featured Image
                                $att_id = $this->upload_image_from_url($url, $post_id, $is_first);
                                if ($att_id) {
                                    $gallery_ids[] = $att_id;
                                }
                                $is_first = false;
                            }

                            if (!empty($gallery_ids)) {
                                update_post_meta($post_id, '_ps_gallery_images', implode(',', $gallery_ids));
                            }
                        }

                        // Handle Custom Fields (Dynamic Specs): format "key1:value1 | key2:value2"
                        if (!empty($custom_fields_raw)) {
                            $specs_array = [];
                            $spec_items = explode('|', $custom_fields_raw);
                            foreach ($spec_items as $s_item) {
                                $parts = explode(':', trim($s_item), 2); // limit 2 to allow : in values
                                if (count($parts) >= 2) {
                                    $specs_array[] = [
                                        'key' => sanitize_text_field(trim($parts[0])),
                                        'val' => sanitize_text_field(trim($parts[1]))
                                    ];
                                }
                            }
                            if (!empty($specs_array)) {
                                update_post_meta($post_id, '_ps_dynamic_specs', $specs_array);
                            }
                        }

                        $imported++;
                    }
                }
                fclose($handle);

                add_action('admin_notices', function () use ($imported) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('Imported %d items successfully.', 'pocket-showroom'), $imported) . '</p></div>';
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
