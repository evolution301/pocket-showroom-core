<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_B2B_Quote_Cart
 * 
 * Module 2: Smart Quotation
 * Allows users to add items to a "Quote Cart" and request a PDF invoice.
 * 
 * @package Pocket Showroom
 * @since 3.0.0
 */
class PS_B2B_Quote_Cart
{
    private static $instance = null;
    private $cookie_name = 'ps_quote_cart';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 1. Frontend: Add "Add to Quote" button
        add_action('ps_frontend_after_add_to_cart', [$this, 'render_quote_button'], 20);

        // 2. Handle Logic: Add/Remove items (AJAX)
        add_action('wp_ajax_ps_add_to_quote', [$this, 'ajax_add_to_quote']);
        add_action('wp_ajax_nopriv_ps_add_to_quote', [$this, 'ajax_add_to_quote']);

        // 3. Shortcode: [ps_quote_cart]
        add_shortcode('ps_quote_cart', [$this, 'render_cart_page']);

        // 4. Handle Submission
        add_action('admin_post_ps_submit_quote', [$this, 'handle_quote_submission']);
        add_action('admin_post_nopriv_ps_submit_quote', [$this, 'handle_quote_submission']);
    }

    /**
     * Render "Add to Quote" Button
     */
    public function render_quote_button()
    {
        global $post;
        ?>
        <button type="button" class="ps-btn-quote ps-add-quote-btn" data-id="<?php echo esc_attr($post->ID); ?>">
            <span class="dashicons dashicons-format-aside"></span>
            Add to Quote Request
        </button>
        <script>
            jQuery(document).ready(function ($) {
                $('.ps-add-quote-btn').on('click', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var pid = btn.data('id');
                    btn.text('Adding...');

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'ps_add_to_quote',
                        product_id: pid
                    }, function (res) {
                        if (res.success) {
                            btn.text('Added to Quote!');
                            setTimeout(function () { btn.text('Add to Quote Request'); }, 2000);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX Handler: Add to Quote
     */
    public function ajax_add_to_quote()
    {
        $pid = intval($_POST['product_id']);
        if (!$pid)
            wp_send_json_error();

        // Simple Session/Cookie Logic
        $cart = isset($_COOKIE[$this->cookie_name]) ? json_decode(stripslashes($_COOKIE[$this->cookie_name]), true) : [];
        if (!is_array($cart))
            $cart = [];

        if (!in_array($pid, $cart)) {
            $cart[] = $pid;
        }

        // Set cookie for 7 days
        setcookie($this->cookie_name, json_encode($cart), time() + 604800, COOKIEPATH, COOKIE_DOMAIN);

        wp_send_json_success(['count' => count($cart)]);
    }

    /**
     * Render the Cart Page Shortcode [ps_quote_cart]
     */
    public function render_cart_page()
    {
        $cart = isset($_COOKIE[$this->cookie_name]) ? json_decode(stripslashes($_COOKIE[$this->cookie_name]), true) : [];

        ob_start();
        if (empty($cart)) {
            echo '<div class="ps-quote-empty">Your quote list is empty.</div>';
        } else {
            echo '<div class="ps-quote-wrap">';
            echo '<h2>Request for Quotation</h2>';
            echo '<table class="ps-quote-table">';
            echo '<thead><tr><th>Product</th><th>Reference</th><th>Qty</th></tr></thead>';
            echo '<tbody>';

            foreach ($cart as $pid) {
                $title = get_the_title($pid);
                $ref = get_post_meta($pid, '_ps_ref_code', true);
                echo '<tr>';
                echo '<td>' . esc_html($title) . '</td>';
                echo '<td>' . esc_html($ref) . '</td>';
                echo '<td><input type="number" name="qty[' . $pid . ']" value="1" class="ps-b2b-input" style="width:60px;"></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Submission Form
            echo '<form action="' . admin_url('admin-post.php') . '" method="post" class="ps-quote-form">';
            echo '<input type="hidden" name="action" value="ps_submit_quote">';
            echo '<div class="ps-form-row"><label>Company Name</label><input type="text" name="company" required class="ps-input"></div>';
            echo '<div class="ps-form-row"><label>Email Address</label><input type="email" name="email" required class="ps-input"></div>';
            echo '<button type="submit" class="ps-form-btn">Generate Quotation PDF</button>';
            echo '</form>';
            echo '</div>';
        }
        return ob_get_clean();
    }

    /**
     * Handle Form Submission -> Generate "PDF" (Print View)
     */
    public function handle_quote_submission()
    {
        if (!isset($_POST['company']))
            return;

        $company = sanitize_text_field($_POST['company']);
        $email = sanitize_email($_POST['email']);

        // In a real PDF generator, we would use TCPDF here.
        // For Phase 3 MVP, we generate a print-friendly HTML page.

        $cart = isset($_COOKIE[$this->cookie_name]) ? json_decode(stripslashes($_COOKIE[$this->cookie_name]), true) : [];

        // Output Print View
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>Quotation -
                <?php echo esc_html($company); ?>
            </title>
            <style>
                body {
                    font-family: sans-serif;
                    padding: 40px;
                    color: #333;
                }

                .header {
                    display: flex;
                    justify-content: space-between;
                    border-bottom: 2px solid #000;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                }

                .meta {
                    margin-bottom: 40px;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                }

                th {
                    text-align: left;
                    border-bottom: 1px solid #ccc;
                    padding: 10px;
                }

                td {
                    border-bottom: 1px solid #eee;
                    padding: 10px;
                }

                .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }

                @media print {
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>

        <body onload="window.print()">
            <div class="no-print"
                style="margin-bottom:20px; background:#e0f2fe; padding:10px; color:#0c4a6e; text-align:center;">
                ✅ Quotation Generated! Please <strong>Save as PDF</strong> or Print using your browser.
            </div>

            <div class="header">
                <div>
                    <h1>QUOTATION</h1>
                    <p>Date:
                        <?php echo date('Y-m-d'); ?>
                    </p>
                </div>
                <div style="text-align:right;">
                    <h3>
                        <?php echo get_bloginfo('name'); ?>
                    </h3>
                    <p>Contact:
                        <?php echo get_bloginfo('admin_email'); ?>
                    </p>
                </div>
            </div>

            <div class="meta">
                <strong>To:</strong><br>
                <?php echo esc_html($company); ?><br>
                <?php echo esc_html($email); ?>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Reference</th>
                        <th style="width:100px;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $pid): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?php echo get_the_title($pid); ?>
                                </strong><br>
                                <small>
                                    <?php echo esc_html(get_post_meta($pid, '_ps_loading', true)); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo esc_html(get_post_meta($pid, '_ps_ref_code', true)); ?>
                            </td>
                            <td>__________</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer">
                <p>Thank you for your inquiry!</p>
            </div>
        </body>

        </html>
        <?php
        exit;
    }
}

// Initialize
PS_B2B_Quote_Cart::get_instance();
