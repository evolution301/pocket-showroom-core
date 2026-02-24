<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_B2B_Calculator
 * 
 * Module 1: Container Loading Calculator
 * Adds a CBM calculator widget to the frontend product page.
 * 
 * @package Pocket Showroom
 * @since 3.0.0
 */
class PS_B2B_Calculator
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
        // Add calculator after "Add to Cart" button (or similar hook)
        add_action('ps_frontend_after_add_to_cart', [$this, 'render_calculator_widget']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets()
    {
        // Enqueue B2B Power Suite shared styles
        wp_enqueue_style('ps-b2b-style', PS_CORE_URL . 'assets/b2b-style.css', [], '3.0.0');
    }

    /**
     * Render the Calculator Widget
     */
    public function render_calculator_widget()
    {
        global $post;
        $loading = get_post_meta($post->ID, '_ps_loading', true);

        // Parse "45 Sets / 40HQ" to get number
        $qty_per_40hq = 0;
        if (preg_match('/(\d+)/', $loading, $matches)) {
            $qty_per_40hq = (int) $matches[1];
        }

        if ($qty_per_40hq <= 0)
            return;

        ?>
        <div class="ps-b2b-calculator">
            <h4 class="ps-b2b-cal-title">
                <span class="dashicons dashicons-chart-bar"></span>
                Container Loading Calculator
            </h4>

            <div class="ps-b2b-calc-row">
                <input type="number" id="ps-calc-qty" placeholder="Enter Qty" class="ps-b2b-input">
                <span id="ps-calc-result" class="ps-b2b-result">--</span>
            </div>

            <p class="ps-b2b-note">
                Based on loading qty: <?php echo esc_html($loading); ?>
            </p>

            <script>
                document.getElementById('ps-calc-qty').addEventListener('input', function (e) {
                    const qty = parseInt(e.target.value) || 0;
                    const per40hq = <?php echo $qty_per_40hq; ?>;
                    if (qty > 0) {
                        const containers = (qty / per40hq).toFixed(2);
                        const cbm = (qty * (68 / per40hq)).toFixed(2); // Approx 68 CBM for 40HQ
                        document.getElementById('ps-calc-result').innerHTML =
                            containers + ' x 40HQ (' + cbm + ' m³)';
                    } else {
                        document.getElementById('ps-calc-result').innerHTML = '--';
                    }
                });
            </script>
        </div>
        <?php
    }
}

// Initialize
PS_B2B_Calculator::get_instance();
