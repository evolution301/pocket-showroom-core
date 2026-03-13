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
        
        // AJAX handlers for progress tracking
        add_action('wp_ajax_ps_import_progress', [$this, 'handle_import_progress']);
        add_action('wp_ajax_ps_cancel_import', [$this, 'handle_cancel_import']);
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
        // Check for completed import progress
        $import_id = get_transient('ps_current_import_id');
        $progress = $import_id ? get_transient('ps_import_progress_' . $import_id) : false;
        ?>
        <div class="ps-modern-ui wrap">
            <div class="ps-header">
                <div class="ps-title-section">
                    <h1 class="wp-heading-inline" style="font-size: 24px; font-weight: 600;">
                        <?php _e('Data Tools', 'pocket-showroom'); ?>
                    </h1>
                </div>
            </div>

            <!-- Progress Bar (shown during import) -->
            <div id="ps-import-progress" style="display:none; margin-bottom: 20px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <h3 style="margin-top:0; display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-update" style="animation: rotation 2s infinite linear;"></span>
                    <?php _e('Import in Progress...', 'pocket-showroom'); ?>
                </h3>
                <div style="margin-bottom:10px; font-size:14px; color:#666;">
                    <span id="ps-progress-text">Processing...</span>
                </div>
                <div style="background:#f0f0f1; border-radius:4px; height:24px; overflow:hidden;">
                    <div id="ps-progress-bar" style="background:linear-gradient(90deg, #2271b1, #135e96); height:100%; width:0%; transition:width 0.3s ease;"></div>
                </div>
                <div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-size:12px; color:#666;">
                        <span id="ps-stats-created">0</span> created | 
                        <span id="ps-stats-updated">0</span> updated | 
                        <span id="ps-stats-errors">0</span> errors
                    </div>
                    <button type="button" id="ps-cancel-import" class="button" style="color:#d63638; border-color:#d63638;">
                        <?php _e('Cancel Import', 'pocket-showroom'); ?>
                    </button>
                </div>
            </div>

            <div class="ps-tools-container">
                <!-- Import Card -->
                <div class="ps-tool-card ps-import-card">
                    <div class="ps-card-icon"><span class="dashicons dashicons-cloud-upload"></span></div>
                    <h2><?php _e('Import Products', 'pocket-showroom'); ?></h2>
                    <p><?php _e('Bulk create or update products by uploading a CSV file.', 'pocket-showroom'); ?></p>

                    <form method="post" enctype="multipart/form-data" class="ps-import-form" id="ps-import-form">
                        <?php wp_nonce_field('ps_csv_import', 'ps_csv_nonce'); ?>

                        <div class="ps-drop-zone" onclick="document.getElementById('ps_csv_file').click();">
                            <span class="dashicons dashicons-media-document"></span>
                            <p><?php _e('Click to select CSV file', 'pocket-showroom'); ?></p>
                            <input type="file" name="ps_csv_file" id="ps_csv_file" accept=".csv" required style="display:none;"
                                onchange="this.previousElementSibling.textContent = this.files[0].name;" />
                        </div>

                        <button type="submit" class="ps-btn ps-btn-primary ps-full-btn" id="ps-import-submit">
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
        
        <style>
            @keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var progressInterval = null;
            var importId = '<?php echo esc_js($import_id ? $import_id : ''); ?>';
            
            // Show progress bar if import is in progress
            <?php if ($progress): ?>
            $('#ps-import-progress').show();
            $('.ps-import-card').hide();
            startProgressPolling();
            <?php endif; ?>
            
            // Handle form submission
            $('#ps-import-form').on('submit', function(e) {
                var fileInput = document.getElementById('ps_csv_file');
                if (fileInput.files.length > 0) {
                    var fileSize = fileInput.files[0].size;
                    if (fileSize > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('File size exceeds 10MB limit. Please use a smaller file.');
                        return false;
                    }
                }
                $('#ps-import-progress').show();
                $('.ps-import-card').hide();
                // Delay polling start to give server time to begin processing
                setTimeout(function() {
                    startProgressPolling();
                }, 2000);
            });
            
            // Cancel import
            $('#ps-cancel-import').on('click', function() {
                if (confirm('Are you sure you want to cancel this import?')) {
                    $.post(ajaxurl, {
                        action: 'ps_cancel_import',
                        import_id: importId
                    }, function(response) {
                        if (response.success) {
                            alert('Import cancelled.');
                            window.location.href = window.location.href.split('?')[0] + '?post_type=ps_item&page=ps-import';
                        }
                    });
                }
            });
            
            function startProgressPolling() {
                progressInterval = setInterval(function() {
                    $.get(ajaxurl, {
                        action: 'ps_import_progress',
                        import_id: importId
                    }, function(response) {
                        if (response.success && response.data) {
                            var data = response.data;
                            var percent = data.total_rows > 0 ? Math.round((data.processed_rows / data.total_rows) * 100) : 0;
                            
                            $('#ps-progress-bar').css('width', percent + '%');
                            $('#ps-progress-text').text('Processed ' + data.processed_rows + ' of ' + data.total_rows + ' rows (' + percent + '%)');
                            $('#ps-stats-created').text(data.created);
                            $('#ps-stats-updated').text(data.updated);
                            $('#ps-stats-errors').text(data.errors);
                            
                            if (data.status === 'completed') {
                                clearInterval(progressInterval);
                                // Show completion message instead of reloading (prevents POST re-submission)
                                $('#ps-import-progress h3').html('<span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span> Import Complete!');
                                $('#ps-cancel-import').hide();
                                // Navigate via GET to prevent POST re-submission
                                setTimeout(function() {
                                    window.location.href = window.location.href.split('?')[0] + '?post_type=ps_item&page=ps-import&ps_import_done=1';
                                }, 3000);
                            } else if (data.status === 'cancelled' || data.status === 'failed') {
                                clearInterval(progressInterval);
                                alert('Import ' + data.status + ': ' + (data.message || ''));
                                window.location.href = window.location.href.split('?')[0] + '?post_type=ps_item&page=ps-import';
                            }
                        }
                    });
                }, 1500);
            }
        });
        </script>
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

    /**
     * AJAX handler for import progress
     */
    public function handle_import_progress()
    {
        // Security: Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }
        
        $import_id = sanitize_text_field($_GET['import_id'] ?? '');
        if (empty($import_id)) {
            wp_send_json_error(['message' => 'Invalid import ID']);
            return;
        }

        $progress = get_transient('ps_import_progress_' . $import_id);
        if (!$progress) {
            wp_send_json_error(['message' => 'Import not found or completed']);
            return;
        }

        wp_send_json_success($progress);
    }

    /**
     * AJAX handler for cancelling import
     */
    public function handle_cancel_import()
    {
        // Security: Permission check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }
        
        $import_id = sanitize_text_field($_POST['import_id'] ?? '');
        if (empty($import_id)) {
            wp_send_json_error(['message' => 'Invalid import ID']);
            return;
        }

        set_transient('ps_import_cancel_' . $import_id, '1', HOUR_IN_SECONDS);
        wp_send_json_success(['message' => 'Cancel requested']);
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

        // ====== CRITICAL FIX: Prevent duplicate submission ======
        // If we already processed this nonce, skip (prevents infinite loop on page reload)
        $nonce_val = sanitize_text_field($_POST['ps_csv_nonce']);
        $nonce_key = 'ps_csv_processed_' . md5($nonce_val);
        if (get_transient($nonce_key)) {
            // This nonce was already processed, skip to prevent re-import
            return;
        }
        // Mark this nonce as processed immediately
        set_transient($nonce_key, '1', HOUR_IN_SECONDS);

        if (!empty($_FILES['ps_csv_file']['tmp_name'])) {
            // ========== ENHANCEMENT 1: File Validation ==========
            
            // File size validation (max 10MB)
            $max_file_size = 10 * 1024 * 1024; // 10MB
            if ($_FILES['ps_csv_file']['size'] > $max_file_size) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('File size exceeds 10MB limit. Please split large files or remove images.', 'pocket-showroom') . '</p></div>';
                });
                return;
            }

            // MIME type validation
            $allowed_mime_types = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['ps_csv_file']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime_type, $allowed_mime_types)) {
                    add_action('admin_notices', function() use ($mime_type) {
                        echo '<div class="notice notice-error"><p>' . __('Invalid file type. Expected CSV, got: ', 'pocket-showroom') . esc_html($mime_type) . '</p></div>';
                    });
                    return;
                }
            }

            // Fix #20: Large batch imports need more time
            @set_time_limit(0);
            ini_set('auto_detect_line_endings', '1');

            // ========== ENHANCEMENT 2: Progress Tracking Initialization ==========
            $import_id = uniqid('ps_import_');
            set_transient('ps_current_import_id', $import_id, HOUR_IN_SECONDS);
            
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

                // 4. Parse rows - Use proper CSV parsing to handle multi-line fields
                // FIX: str_getcsv with explode("\n") breaks when fields contain newlines
                // Use a streaming approach that respects quoted fields
                $raw_rows = [];
                $temp_file = tmpfile();
                fwrite($temp_file, $file_content);
                fseek($temp_file, 0);
                
                while (($row = fgetcsv($temp_file, 0, $delimiter)) !== false) {
                    // Skip completely empty rows (handles ,,,,, and single-cell empty)
                    $is_empty = true;
                    foreach ($row as $cell) {
                        if (trim((string)$cell) !== '') {
                            $is_empty = false;
                            break;
                        }
                    }
                    if ($is_empty) {
                        continue;
                    }
                    $raw_rows[] = $row;
                }
                fclose($temp_file);

                if (count($raw_rows) < 1) return;

                $raw_header = $raw_rows[0];
                $header = array_map('trim', array_map('strtolower', $raw_header));
                
                // Column matching - exact match only, then keyword-contains (ONE direction only)
                // CRITICAL FIX: removed dangerous reverse-substring match strpos($kw, $h)
                // which caused false matches (e.g. header 'e' matches keyword 'price')
                $find_idx = function($keywords, $header) {
                    // Pass 1: Exact match
                    foreach ($keywords as $kw) {
                        $kw = strtolower($kw);
                        $idx = array_search($kw, $header);
                        if ($idx !== false) return $idx;
                    }
                    // Pass 2: Header CONTAINS keyword (safe direction only)
                    foreach ($keywords as $kw) {
                        $kw = strtolower($kw);
                        if (mb_strlen($kw) < 2) continue; // Skip single-char keywords
                        foreach ($header as $i => $h) {
                            if (!empty($h) && mb_strlen($h) >= 2 && strpos($h, $kw) !== false) {
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

                // Initialize progress tracking
                set_transient('ps_import_progress_' . $import_id, [
                    'total_rows' => count($raw_rows) - 1,
                    'processed_rows' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'errors' => 0,
                    'status' => 'started'
                ], HOUR_IN_SECONDS);

                $created = 0;
                $updated = 0;
                $skipped = 0;
                $errors  = 0;
                $error_messages = [];
                $validation_failed = 0;

                for ($i = 1; $i < count($raw_rows); $i++) {
                    // ========== ENHANCEMENT 3: Progress Update & Cancel Check ==========
                    // FIX: Update progress more frequently (every 10 rows instead of 50) for smoother UI
                    if ($i % 10 === 0 || $i === 1) {
                        $progress = get_transient('ps_import_progress_' . $import_id);
                        if ($progress) {
                            $progress['processed_rows'] = $i;
                            $progress['created'] = $created;
                            $progress['updated'] = $updated;
                            $progress['errors'] = $errors;
                            set_transient('ps_import_progress_' . $import_id, $progress, HOUR_IN_SECONDS);
                        }
                        
                        // Check if import was cancelled
                        if (get_transient('ps_import_cancel_' . $import_id)) {
                            add_action('admin_notices', function() use ($i, $created, $updated) {
                                echo '<div class="notice notice-warning"><p><strong>' . __('Import Cancelled', 'pocket-showroom') . '</strong> ' . 
                                    sprintf(__('Import was cancelled by user after processing %d rows. Created: %d, Updated: %d', 'pocket-showroom'), $i, $created, $updated) . '</p></div>';
                            });
                            delete_transient('ps_import_cancel_' . $import_id);
                            // Mark import as cancelled
                            $progress = get_transient('ps_import_progress_' . $import_id);
                            if ($progress) {
                                $progress['status'] = 'cancelled';
                                set_transient('ps_import_progress_' . $import_id, $progress, HOUR_IN_SECONDS);
                            }
                            return;
                        }
                    }
                    
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

                    // Data validation: only check truly critical issues
                    // SKU format is NOT validated - real-world SKUs can contain dots, spaces, slashes etc.
                    // Price and MOQ are NOT validated - they may contain currency symbols or units

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

                // ========== ENHANCEMENT 5: Final Progress Update ==========
                $progress = get_transient('ps_import_progress_' . $import_id);
                if ($progress) {
                    $progress['processed_rows'] = count($raw_rows) - 1;
                    $progress['created'] = $created;
                    $progress['updated'] = $updated;
                    $progress['errors'] = $errors;
                    $progress['status'] = 'completed';
                    set_transient('ps_import_progress_' . $import_id, $progress, HOUR_IN_SECONDS);
                }

                add_action('admin_notices', function () use ($created, $updated, $skipped, $errors, $error_messages, $validation_failed) {
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

    /**
     * Row data validation - intentionally permissive
     * Real-world SKUs can contain dots, spaces, slashes, Chinese characters etc.
     * We only reject if truly malformed data would crash the system.
     */
    private function validate_row_data($sku, $price, $moq, $row_number)
    {
        // Intentionally empty - no strict validation
        // Real B2B product data is messy and we should accept it all
        return [];
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

        // SSRF 防护: 阻止 localhost 和常见内网地址（仅基于主机名，不做 DNS 查询）
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $host_lower = strtolower($host);
        $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
        if (in_array($host_lower, $blocked, true)) {
            return false;
        }
        // 阻止常见内网 IP 段的主机名模式（如 10.x.x.x, 192.168.x.x）
        if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/i', $host_lower)) {
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
