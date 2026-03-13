<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_CSV_Importer Rewrite
 * 
 * Implements a 2-phase AJAX batch processing architecture similar to WooCommerce.
 * Phase 1: Upload CSV file, normalize encoding, and save to a temporary location.
 * Phase 2: Sequential AJAX calls process the file in batches of 30 rows using a file-position cursor.
 */
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
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_post_ps_download_template', [$this, 'handle_template_download']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_ps_process_batch', [$this, 'handle_batch_process']);
        add_action('wp_ajax_ps_cancel_import', [$this, 'handle_cancel_import']);
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'ps-import') !== false) {
            wp_enqueue_style('ps-admin-shared', PS_CORE_URL . 'assets/admin-shared.css', [], PS_CORE_VERSION);
            wp_enqueue_script('jquery');
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

    /**
     * Dispatcher for forms (Export and Phase 1 Upload)
     */
    public function handle_form_submissions()
    {
        // Handle Export
        if (isset($_POST['ps_action']) && $_POST['ps_action'] == 'export_csv') {
            $this->process_csv_export();
        }

        // Handle Import Upload (Phase 1)
        if (isset($_POST['ps_csv_nonce']) && wp_verify_nonce($_POST['ps_csv_nonce'], 'ps_csv_import')) {
            $this->process_upload_phase();
        }
    }

    /**
     * Phase 1: Upload file, sanitize, and redirect to process UI
     */
    private function process_upload_phase()
    {
        if (!current_user_can('manage_options')) return;

        if (empty($_FILES['ps_csv_file']['tmp_name'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Please select a file to upload.', 'pocket-showroom') . '</p></div>';
            });
            return;
        }

        $file = $_FILES['ps_csv_file'];
        
        // Create imports directory
        $upload_dir = wp_upload_dir();
        $import_dir = $upload_dir['basedir'] . '/ps-imports';
        if (!file_exists($import_dir)) {
            wp_mkdir_p($import_dir);
        }

        $filename = 'import-' . wp_generate_password(8, false) . '.csv';
        $dest_path = $import_dir . '/' . $filename;

        // Read and Normalize Encoding (Handle GBK/UTF-8)
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) return;

        // Strip UTF-8 BOM if present
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        // Detect Encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'GBK', 'CP936'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Save normalized file
        if (file_put_contents($dest_path, $content) === false) {
            wp_die('Failed to save import file.');
        }

        // Clean up temp upload
        @unlink($file['tmp_name']);

        // Redirect to Step 2: Mapping/Processing
        $redirect_url = admin_url('edit.php?post_type=ps_item&page=ps-import&step=import&file=' . urlencode($filename));
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * UI Renderer
     */
    public function render_import_page()
    {
        $step = $_GET['step'] ?? 'default';
        ?>
        <div class="ps-modern-ui wrap">
            <div class="ps-header">
                <div class="ps-title-section">
                    <h1 class="wp-heading-inline" style="font-size: 24px; font-weight: 600;">
                        <?php _e('Data Tools', 'pocket-showroom'); ?>
                    </h1>
                </div>
            </div>

            <?php
            switch ($step) {
                case 'import':
                    $this->render_import_ui();
                    break;
                case 'done':
                    $this->render_done_ui();
                    break;
                default:
                    $this->render_default_ui();
                    break;
            }
            ?>
        </div>
        <?php
    }

    private function render_default_ui()
    {
        ?>
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
                        <p id="ps-file-status"><?php _e('Click to select CSV file', 'pocket-showroom'); ?></p>
                        <input type="file" name="ps_csv_file" id="ps_csv_file" accept=".csv" required style="display:none;"
                            onchange="document.getElementById('ps-file-status').textContent = this.files[0].name;" />
                    </div>
                    <button type="submit" class="ps-btn ps-btn-primary ps-full-btn">
                        <?php _e('Upload and Process', 'pocket-showroom'); ?>
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
                <p><?php _e('Download a complete CSV file containing all items.', 'pocket-showroom'); ?></p>
                <div class="ps-export-actions">
                    <form method="post">
                        <?php wp_nonce_field('ps_csv_export', 'ps_export_nonce'); ?>
                        <input type="hidden" name="ps_action" value="export_csv">
                        <button type="submit" class="ps-btn ps-btn-primary ps-full-btn">
                            <?php _e('Export to CSV', 'pocket-showroom'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_import_ui()
    {
        $filename = sanitize_text_field($_GET['file'] ?? '');
        if (empty($filename)) {
            echo '<div class="notice notice-error"><p>No file specified.</p></div>';
            return;
        }
        ?>
        <div id="ps-import-progress" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); max-width: 600px; margin: 20px auto;">
            <h3 style="margin-top:0; display:flex; align-items:center; gap:12px; color: #2c3338;">
                <span class="dashicons dashicons-update" id="ps-progress-icon" style="color: #2271b1;"></span>
                <span id="ps-status-title"><?php _e('Analyzing Data...', 'pocket-showroom'); ?></span>
            </h3>
            
            <div style="margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; font-weight: 500;">
                    <span id="ps-progress-text">Initializing batch...</span>
                    <span id="ps-progress-percent">0%</span>
                </div>
                <div style="background:#f0f0f1; border-radius:10px; height:12px; overflow:hidden;">
                    <div id="ps-progress-bar" style="background:linear-gradient(90deg, #2271b1, #3598db); height:100%; width:0%; transition:width 0.4s ease; border-radius: 10px;"></div>
                </div>
            </div>

            <div style="background: #f6f7f7; padding: 15px; border-radius: 6px; border: 1px solid #dcdcde;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
                    <div>
                        <div style="font-size: 20px; font-weight: 600; color: #007cba;" id="ps-stats-created">0</div>
                        <div style="font-size: 11px; color: #646970; text-transform: uppercase;"><?php _e('New', 'pocket-showroom'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 600; color: #2271b1;" id="ps-stats-updated">0</div>
                        <div style="font-size: 11px; color: #646970; text-transform: uppercase;"><?php _e('Updated', 'pocket-showroom'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 600; color: #d63638;" id="ps-stats-errors">0</div>
                        <div style="font-size: 11px; color: #646970; text-transform: uppercase;"><?php _e('Errors', 'pocket-showroom'); ?></div>
                    </div>
                </div>
            </div>

            <div style="margin-top:25px; display:flex; justify-content:center;">
                <button type="button" id="ps-cancel-import" class="button button-link-delete" style="text-decoration:none;">
                    <?php _e('Cancel Import', 'pocket-showroom'); ?>
                </button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var filename = '<?php echo esc_js($filename); ?>';
            var position = 0;
            var stats = { created: 0, updated: 0, errors: 0 };
            var isCancelled = false;

            $('#ps-progress-icon').css('animation', 'rotation 2s infinite linear');

            function processBatch() {
                if (isCancelled) return;

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ps_process_batch',
                        file: filename,
                        position: position,
                        nonce: '<?php echo wp_create_nonce("ps_batch_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            position = data.position;
                            
                            // Update Stats
                            stats.created += data.created;
                            stats.updated += data.updated;
                            stats.errors += data.errors;
                            
                            $('#ps-stats-created').text(stats.created);
                            $('#ps-stats-updated').text(stats.updated);
                            $('#ps-stats-errors').text(stats.errors);
                            
                            // Update Progress
                            $('#ps-progress-bar').css('width', data.percentage + '%');
                            $('#ps-progress-percent').text(data.percentage + '%');
                            $('#ps-progress-text').text('Processing next batch...');
                            $('#ps-status-title').text('Importing Products...');

                            if (data.position === 'done') {
                                finishImport();
                            } else {
                                processBatch();
                            }
                        } else {
                            alert('Critical error: ' + (response.data ? response.data.message : 'Unknown'));
                            window.location.href = '<?php echo admin_url("edit.php?post_type=ps_item&page=ps-import"); ?>';
                        }
                    },
                    error: function() {
                        alert('Network error. Retrying in 3 seconds...');
                        setTimeout(processBatch, 3000);
                    }
                });
            }

            function finishImport() {
                $('#ps-progress-icon').css('animation', 'none').removeClass('dashicons-update').addClass('dashicons-yes-alt').css('color', '#00a32a');
                $('#ps-status-title').text('Import Complete!');
                $('#ps-progress-text').text('All items processed successfully.');
                $('#ps-cancel-import').hide();
                
                setTimeout(function() {
                    window.location.href = '<?php echo admin_url("edit.php?post_type=ps_item&page=ps-import&step=done"); ?>' + 
                        '&created=' + stats.created + '&updated=' + stats.updated + '&errors=' + stats.errors;
                }, 2000);
            }

            $('#ps-cancel-import').on('click', function() {
                if (confirm('Cancel this import? Progress so far will be saved.')) {
                    isCancelled = true;
                    window.location.href = '<?php echo admin_url("edit.php?post_type=ps_item&page=ps-import"); ?>';
                }
            });

            // Start processing
            processBatch();
        });
        </script>
        <style>
            @keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }
        </style>
        <?php
    }

    private function render_done_ui()
    {
        $created = (int)($_GET['created'] ?? 0);
        $updated = (int)($_GET['updated'] ?? 0);
        $errors = (int)($_GET['errors'] ?? 0);
        ?>
        <div style="background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); max-width: 600px; margin: 20px auto; text-align: center;">
            <div style="background: #e7f4e9; color: #1e8c45; width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 32px; height: 32px; width: 32px;"></span>
            </div>
            <h2 style="margin: 0 0 10px; font-weight: 600;"><?php _e('Success!', 'pocket-showroom'); ?></h2>
            <p style="color: #646970; margin-bottom: 30px;"><?php _e('Your product database has been updated.', 'pocket-showroom'); ?></p>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; background: #f6f7f7; padding: 20px; border-radius: 8px; border: 1px solid #dcdcde; margin-bottom: 30px;">
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #2c3338;"><?php echo $created; ?></div>
                    <div style="font-size: 12px; color: #646970;"><?php _e('Created', 'pocket-showroom'); ?></div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #2c3338;"><?php echo $updated; ?></div>
                    <div style="font-size: 12px; color: #646970;"><?php _e('Updated', 'pocket-showroom'); ?></div>
                </div>
                <div>
                    <div style="font-size: 24px; font-weight: 700; color: #d63638;"><?php echo $errors; ?></div>
                    <div style="font-size: 12px; color: #646970;"><?php _e('Errors', 'pocket-showroom'); ?></div>
                </div>
            </div>

            <div style="display: flex; gap: 15px; justify-content: center;">
                <a href="<?php echo admin_url('edit.php?post_type=ps_item'); ?>" class="button button-primary button-large"><?php _e('View All Items', 'pocket-showroom'); ?></a>
                <a href="<?php echo admin_url('edit.php?post_type=ps_item&page=ps-import'); ?>" class="button button-large"><?php _e('Import More', 'pocket-showroom'); ?></a>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX Batch Handler
     */
    public function handle_batch_process()
    {
        check_ajax_referer('ps_batch_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);

        $filename = sanitize_text_field($_POST['file'] ?? '');
        $position = (int)($_POST['position'] ?? 0);
        $batch_size = 30; // WooCommerce style batch size

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/ps-imports/' . $filename;

        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'Import file lost. Please re-upload.']);
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) wp_send_json_error(['message' => 'Cannot open file.']);

        // First seek to start position
        fseek($handle, $position);

        // Get actual file size for percentage
        $file_size = filesize($file_path);

        // Logic for first run: find mapping
        $mapping = [];
        if ($position === 0) {
            $headers = fgetcsv($handle);
            if (!$headers) wp_send_json_error(['message' => 'Empty CSV header.']);
            $mapping = $this->auto_map_headers($headers);
            $position = ftell($handle);
        } else {
            // We need to know headers every time because we are stateless
            // Simple hack: read headers again (or we could pass them from JS)
            rewind($handle);
            $headers = fgetcsv($handle);
            $mapping = $this->auto_map_headers($headers);
            fseek($handle, $position);
        }

        $processed = 0;
        $created = 0;
        $updated = 0;
        $errors = 0;

        while ($processed < $batch_size && ($data = fgetcsv($handle)) !== false) {
            $processed++;

            // Skip empty rows
            if (empty(array_filter($data))) continue;

            $result = $this->process_single_row($data, $mapping);
            if ($result === 'created') $created++;
            elseif ($result === 'updated') $updated++;
            else $errors++;
        }

        $new_position = ftell($handle);
        $is_done = feof($handle) || ($new_position >= $file_size);
        
        fclose($handle);

        if ($is_done) {
            @unlink($file_path); // Cleanup
            $new_position = 'done';
            $percentage = 100;
        } else {
            $percentage = floor(($new_position / $file_size) * 100);
        }

        wp_send_json_success([
            'position' => $new_position,
            'percentage' => $percentage,
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors
        ]);
    }

    private function auto_map_headers($headers)
    {
        $map = [
            'title' => false, 'sku' => false, 'price' => false, 'category' => false,
            'desc' => false, 'material' => false, 'moq' => false, 'loading' => false,
            'lead_time' => false, 'images' => false, 'variants' => false, 'custom' => false
        ];

        $keywords = [
            'title' => ['name', 'product name', 'title', '标题', '产品名称'],
            'sku' => ['sku', 'model', 'model no', '型号', '货号'],
            'price' => ['price', 'list price', 'exw', '价格', '单价'],
            'category' => ['category', 'taxonomy', '分类', '类目'],
            'desc' => ['description', 'content', 'details', '详情', '描述'],
            'material' => ['material', '质地', '材质'],
            'moq' => ['moq', 'minimum', '起订量'],
            'loading' => ['loading', 'pack', '装箱'],
            'lead_time' => ['lead', 'delivery', 'time', '交期', '货期'],
            'images' => ['image', 'photo', 'url', '图片'],
            'variants' => ['variant', 'size', 'color', '属性', '规格', '尺寸'],
            'custom' => ['custom', 'extra', 'spec', '参数', '自定义']
        ];

        foreach ($headers as $idx => $header) {
            $h = strtolower(trim((string)$header));
            if (empty($h)) continue;
            
            foreach ($keywords as $key => $kws) {
                if ($map[$key] !== false) continue;
                foreach ($kws as $kw) {
                    if (strpos($h, $kw) !== false) {
                        $map[$key] = $idx;
                        break;
                    }
                }
            }
        }
        return $map;
    }

    private function process_single_row($data, $map)
    {
        $title_val = ($map['title'] !== false && isset($data[$map['title']])) ? trim((string)$data[$map['title']]) : '';
        $sku_val   = ($map['sku'] !== false && isset($data[$map['sku']])) ? trim((string)$data[$map['sku']]) : '';

        if (empty($title_val) && empty($sku_val)) return 'skipped';

        $sku         = sanitize_text_field($sku_val);
        $title       = sanitize_text_field($title_val ? $title_val : $sku);
        $price       = ($map['price'] !== false && isset($data[$map['price']])) ? sanitize_text_field((string)$data[$map['price']]) : '';
        $category    = ($map['category'] !== false && isset($data[$map['category']])) ? sanitize_text_field((string)$data[$map['category']]) : '';
        $description = ($map['desc'] !== false && isset($data[$map['desc']])) ? wp_kses_post((string)$data[$map['desc']]) : '';
        $material    = ($map['material'] !== false && isset($data[$map['material']])) ? sanitize_text_field((string)$data[$map['material']]) : '';
        $moq         = ($map['moq'] !== false && isset($data[$map['moq']])) ? sanitize_text_field((string)$data[$map['moq']]) : '';
        $loading     = ($map['loading'] !== false && isset($data[$map['loading']])) ? sanitize_text_field((string)$data[$map['loading']]) : '';
        $lead_time   = ($map['lead_time'] !== false && isset($data[$map['lead_time']])) ? sanitize_text_field((string)$data[$map['lead_time']]) : '';
        $images_raw  = ($map['images'] !== false && isset($data[$map['images']])) ? (string)$data[$map['images']] : '';
        $variants_raw = ($map['variants'] !== false && isset($data[$map['variants']])) ? (string)$data[$map['variants']] : '';
        $custom_fields_raw = ($map['custom'] !== false && isset($data[$map['custom']])) ? (string)$data[$map['custom']] : '';

        // Check if exists
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

        if (!$post_id || is_wp_error($post_id)) return 'error';

        // Update Metadata
        update_post_meta($post_id, '_ps_model', $sku);
        update_post_meta($post_id, '_ps_list_price', $price);
        update_post_meta($post_id, '_ps_material', $material);
        update_post_meta($post_id, '_ps_moq', $moq);
        update_post_meta($post_id, '_ps_loading', $loading);
        update_post_meta($post_id, '_ps_lead_time', $lead_time);

        // Visibility Toggles
        update_post_meta($post_id, '_ps_show_model', !empty($sku) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_list_price', !empty($price) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_material', !empty($material) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_moq', !empty($moq) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_loading', !empty($loading) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_lead_time', !empty($lead_time) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_sizes', !empty($variants_raw) ? '1' : '0');
        update_post_meta($post_id, '_ps_show_custom_fields', !empty($custom_fields_raw) ? '1' : '0');

        // Variants
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

        // Custom Fields
        if (!empty($custom_fields_raw)) {
            $specs_array = [];
            $spec_items = explode('|', $custom_fields_raw);
            foreach ($spec_items as $s_item) {
                $parts = explode(':', trim($s_item), 2);
                if (count($parts) >= 2) {
                    $specs_array[] = ['key' => trim($parts[0]), 'val' => trim($parts[1])];
                }
            }
            update_post_meta($post_id, '_ps_dynamic_specs', $specs_array);
        }

        // Images (The slow part - handled here but in small batches it's fine)
        if (!empty($images_raw)) {
            $urls = array_map('trim', explode(',', $images_raw));
            $gallery_ids = [];
            $is_first = true;
            foreach ($urls as $url) {
                if (empty($url)) continue;
                $att_id = $this->upload_image_from_url(esc_url_raw($url), (int)$post_id, $is_first);
                if ($att_id) $gallery_ids[] = $att_id;
                $is_first = false;
            }
            if (!empty($gallery_ids)) {
                update_post_meta($post_id, '_ps_gallery_images', implode(',', $gallery_ids));
            }
        }

        return $existing_id ? 'updated' : 'created';
    }

    private function get_product_by_sku($sku)
    {
        if (empty($sku)) return false;
        $args = [
            'post_type' => 'ps_item',
            'meta_query' => [
                'relation' => 'OR',
                [['key' => '_ps_model', 'value' => $sku]],
                [['key' => '_bfs_sku', 'value' => $sku]]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ];
        $posts = get_posts($args);
        return !empty($posts) ? $posts[0] : false;
    }

    private function upload_image_from_url($url, $post_id, $is_featured = false)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Check if exists
        $existing_id = attachment_url_to_postid($url);
        if ($existing_id) {
            if ($is_featured) set_post_thumbnail($post_id, $existing_id);
            return $existing_id;
        }

        // Sideload
        $tmp = download_url($url);
        if (is_wp_error($tmp)) return false;

        $file_array = ['name' => basename($url), 'tmp_name' => $tmp];
        $id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return false;
        }

        if ($is_featured) set_post_thumbnail($post_id, $id);
        return $id;
    }

    /**
     * Export Login
     */
    public function process_csv_export()
    {
        check_admin_referer('ps_csv_export', 'ps_export_nonce');
        if (!current_user_can('manage_options')) return;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pocket-showroom-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM

        fputcsv($output, ['Name', 'Model', 'Price', 'Category', 'Description', 'Material', 'MOQ', 'Loading', 'Lead Time', 'Images', 'Variants', 'Specs']);

        $query = new WP_Query([
            'post_type' => 'ps_item',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $sku = get_post_meta($id, '_ps_model', true);
                $price = get_post_meta($id, '_ps_list_price', true);
                
                $terms = get_the_terms($id, 'ps_category');
                $cat = $terms && !is_wp_error($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '';
                
                // Images
                $imgs = [];
                if (has_post_thumbnail($id)) $imgs[] = wp_get_attachment_url(get_post_thumbnail_id($id));
                $gallery = get_post_meta($id, '_ps_gallery_images', true);
                if ($gallery) {
                    foreach (explode(',', $gallery) as $gid) {
                        $url = wp_get_attachment_url($gid);
                        if ($url && !in_array($url, $imgs)) $imgs[] = $url;
                    }
                }

                fputcsv($output, [
                    get_the_title(),
                    $sku,
                    $price,
                    $cat,
                    get_the_content(),
                    get_post_meta($id, '_ps_material', true),
                    get_post_meta($id, '_ps_moq', true),
                    get_post_meta($id, '_ps_loading', true),
                    get_post_meta($id, '_ps_lead_time', true),
                    implode(',', $imgs),
                    '', // Simplify rewrite for now
                    ''
                ]);
            }
        }
        fclose($output);
        exit;
    }

    public function handle_template_download()
    {
        check_admin_referer('ps_download_template');
        $file = PS_CORE_PATH . 'assets/sample-import.csv';
        if (!file_exists($file)) wp_die('Template not found');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=showroom-template.csv');
        readfile($file);
        exit;
    }

    public function handle_cancel_import()
    {
        // Simple cancellation check
        wp_send_json_success();
    }
}
