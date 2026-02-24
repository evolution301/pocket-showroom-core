<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_B2B_Fair_Mode
 * 
 * Module 4: Fair QR Code Label
 * Generates printable labels with Product Photo, Info, and MiniApp QR Code.
 * 
 * @package Pocket Showroom
 * @since 3.0.0
 */
class PS_B2B_Fair_Mode
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
        // 1. Add "Print Label" action to Post List Row Actions
        add_filter('post_row_actions', [$this, 'add_print_action'], 10, 2);

        // 2. Add "Print Label" button to Edit Screen (Meta Box)
        add_action('add_meta_boxes', [$this, 'add_print_metabox']);

        // 3. Handle Print Request
        add_action('admin_post_ps_print_label', [$this, 'handle_print_request']);
    }

    /**
     * Add "Print Label" link to the post list
     */
    public function add_print_action($actions, $post)
    {
        if ($post->post_type === 'ps_item') {
            $url = admin_url('admin-post.php?action=ps_print_label&post_id=' . $post->ID);
            $actions['ps_print'] = '<a href="' . esc_url($url) . '" target="_blank" style="color:#2563EB; font-weight:bold;">🖨️ Print Label</a>';
        }
        return $actions;
    }

    /**
     * Add "Print Label" meta box to the edit screen
     */
    public function add_print_metabox()
    {
        add_meta_box(
            'ps_fair_print',
            __('Fair Label', 'pocket-showroom'),
            function ($post) {
                $url = admin_url('admin-post.php?action=ps_print_label&post_id=' . $post->ID);
                echo '<a href="' . esc_url($url) . '" target="_blank" class="button button-secondary" style="width:100%; text-align:center;">🖨️ Generate Fair Label</a>';
                echo '<p class="description" style="margin-top:5px;">Print this label and stick it on your sample at the fair.</p>';
            },
            'ps_item',
            'side',
            'low'
        );
    }

    /**
     * Handle the Print Request and render HTML
     */
    public function handle_print_request()
    {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id)
            wp_die('Invalid Post ID');

        $post = get_post($post_id);
        $meta = get_post_meta($post_id);

        // Data
        $ref_code = $meta['_ps_ref_code'][0] ?? '--';
        $loading = $meta['_ps_loading'][0] ?? '--';
        $size = $meta['_ps_size'][0] ?? '--';
        $moq = $meta['_ps_moq'][0] ?? '--';
        $thumb_url = get_the_post_thumbnail_url($post_id, 'medium');

        // Note: Real QR Code generation would happen here using an API or library.
        // For MVP, we use a placeholder QR API (goqr.me) pointing to a dummy link.
        // In Prod, this should point to: miniapp://page/detail?id=POST_ID
        $qr_data = "https://example.com/item/" . $post_id;
        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>Label -
                <?php echo esc_html($ref_code); ?>
            </title>
            <style>
                body {
                    font-family: 'Arial', sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #eee;
                }

                .label-card {
                    width: 10cm;
                    /* Standard Label Size */
                    height: 15cm;
                    background: white;
                    margin: 0 auto;
                    padding: 20px;
                    box-sizing: border-box;
                    border: 1px solid #ccc;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    text-align: center;
                    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                }

                .product-img {
                    width: 100%;
                    height: 180px;
                    object-fit: contain;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    border: 1px solid #eee;
                }

                .ref-code {
                    font-size: 24px;
                    font-weight: 800;
                    margin: 0 0 10px 0;
                    color: #000;
                }

                .info-grid {
                    width: 100%;
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 10px;
                    font-size: 14px;
                    text-align: left;
                    margin-bottom: 20px;
                    border-top: 2px solid #000;
                    padding-top: 15px;
                }

                .info-item label {
                    font-size: 10px;
                    text-transform: uppercase;
                    color: #666;
                    display: block;
                }

                .info-item span {
                    font-weight: 600;
                    color: #000;
                }

                .qr-section {
                    margin-top: auto;
                }

                .qr-code {
                    width: 120px;
                    height: 120px;
                }

                .scan-hint {
                    font-size: 12px;
                    margin-top: 5px;
                    color: #666;
                }

                @media print {
                    body {
                        background: white;
                        padding: 0;
                    }

                    .label-card {
                        border: none;
                        box-shadow: none;
                        margin: 0;
                    }
                }
            </style>
        </head>

        <body onload="window.print()">
            <div class="label-card">
                <?php if ($thumb_url): ?>
                    <img src="<?php echo esc_url($thumb_url); ?>" class="product-img">
                <?php else: ?>
                    <div class="product-img"
                        style="background:#eee; display:flex; align-items:center; justify-content:center; color:#999;">No Image
                    </div>
                <?php endif; ?>

                <h1 class="ref-code">
                    <?php echo esc_html($ref_code); ?>
                </h1>

                <div class="info-grid">
                    <div class="info-item">
                        <label>Size / Dim.</label>
                        <span>
                            <?php echo esc_html($size); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Loading</label>
                        <span>
                            <?php echo esc_html($loading); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label>MOQ</label>
                        <span>
                            <?php echo esc_html($moq); ?>
                        </span>
                    </div>
                </div>

                <div class="qr-section">
                    <img src="<?php echo esc_url($qr_url); ?>" class="qr-code">
                    <div class="scan-hint">Scan for Details & Price</div>
                </div>
            </div>
        </body>

        </html>
        <?php
        exit;
    }
}

// Initialize
PS_B2B_Fair_Mode::get_instance();
