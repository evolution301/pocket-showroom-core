<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_Module_Loader
 * 
 * Central controller for the "Pocket Showroom Pro" B2B Power Suite.
 * Handles the loading of optional modules based on user settings (Feature Flags).
 * 
 * @package Pocket Showroom
 * @since 3.0.0
 */
class PS_Module_Loader
{
    private static $instance = null;
    private $active_modules = [];

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 1. Get enabled modules from settings
        $this->active_modules = get_option('ps_active_modules', []);

        // 2. Load enabled modules
        if (!empty($this->active_modules) && is_array($this->active_modules)) {
            $this->load_modules();
        }

        // 3. Register settings tab for Modules
        add_filter('ps_settings_tabs', [$this, 'add_settings_tab']);
        add_action('ps_render_tab_modules', [$this, 'render_modules_page']);
        add_action('admin_init', [$this, 'register_module_settings']);
    }

    /**
     * Load the PHP files for active modules
     */
    private function load_modules()
    {
        // Define available modules and their entry points
        $available_modules = [
            'b2b_calculator' => 'b2b-calculator/class-cbm-calc.php',
            'b2b_quotation'  => 'b2b-quotation/class-quote-cart.php',
            'b2b_private'    => 'b2b-private-mode/class-access-control.php',
            'b2b_fair'       => 'b2b-fair-mode/class-qr-label.php',
            'b2b_sales'      => 'b2b-sales-rep/class-sales-rep.php',
        ];

        foreach ($this->active_modules as $module_key => $is_active) {
            if ($is_active === 'on' || $is_active === true) {
                if (isset($available_modules[$module_key])) {
                    $file_path = PS_CORE_PATH . 'modules/' . $available_modules[$module_key];
                    if (file_exists($file_path)) {
                        require_once $file_path;
                    }
                }
            }
        }
    }

    /**
     * Add the "B2B Power Suite" tab to settings
     */
    public function add_settings_tab($tabs)
    {
        $tabs['modules'] = __('B2B Power Suite', 'pocket-showroom');
        return $tabs;
    }

    /**
     * Register the setting in WP
     */
    public function register_module_settings()
    {
        register_setting('ps_settings_group', 'ps_active_modules');
    }

    /**
     * Render the Modules Control Panel
     */
    public function render_modules_page()
    {
        $modules = get_option('ps_active_modules', []);
        ?>
        <div class="ps-card">
            <div class="ps-card-header">
                <h3><?php _e('B2B Power Suite Modules', 'pocket-showroom'); ?></h3>
                <p class="description"><?php _e('Enable only the features you need to keep your site fast.', 'pocket-showroom'); ?></p>
            </div>
            <div class="ps-card-body">
                <form method="post" action="options.php">
                    <?php settings_fields('ps_settings_group'); ?>
                    
                    <div class="ps-modules-grid">
                        
                        <!-- Module 1: Container Calculator -->
                        <div class="ps-module-card">
                            <div class="ps-module-header">
                                <h4 class="ps-module-title">📦 Container Calculator</h4>
                                <label class="ps-switch">
                                    <input type="checkbox" name="ps_active_modules[b2b_calculator]" 
                                        <?php checked(isset($modules['b2b_calculator']) && $modules['b2b_calculator'] == 'on'); ?>>
                                    <span class="ps-slider"></span>
                                </label>
                            </div>
                            <p class="ps-module-desc">Automatically calculate CBM and container loading (20GP/40HQ) for customer orders.</p>
                            <span class="ps-tag ps-tag-logistics">Logistics</span>
                        </div>

                        <!-- Module 2: Smart Quotation -->
                        <div class="ps-module-card">
                            <div class="ps-module-header">
                                <h4 class="ps-module-title">📄 Smart Quotation PDF</h4>
                                <label class="ps-switch">
                                    <input type="checkbox" name="ps_active_modules[b2b_quotation]" 
                                        <?php checked(isset($modules['b2b_quotation']) && $modules['b2b_quotation'] == 'on'); ?>>
                                    <span class="ps-slider"></span>
                                </label>
                            </div>
                            <p class="ps-module-desc">Allow customers to request quotes. Auto-generate professional PDF invoices/quotations.</p>
                            <span class="ps-tag ps-tag-sales">Sales</span>
                        </div>

                        <!-- Module 3: Private Mode -->
                        <div class="ps-module-card">
                            <div class="ps-module-header">
                                <h4 class="ps-module-title">🔒 Private Showroom</h4>
                                <label class="ps-switch">
                                    <input type="checkbox" name="ps_active_modules[b2b_private]" 
                                        <?php checked(isset($modules['b2b_private']) && $modules['b2b_private'] == 'on'); ?>>
                                    <span class="ps-slider"></span>
                                </label>
                            </div>
                            <p class="ps-module-desc">Protect your intellectual property. Hide prices or require login to view specific collections.</p>
                            <span class="ps-tag ps-tag-security">Security</span>
                        </div>

                        <!-- Module 4: Fair Label Printer -->
                        <div class="ps-module-card">
                            <div class="ps-module-header">
                                <h4 class="ps-module-title">🏷️ Fair Label Printer</h4>
                                <label class="ps-switch">
                                    <input type="checkbox" name="ps_active_modules[b2b_fair]" 
                                        <?php checked(isset($modules['b2b_fair']) && $modules['b2b_fair'] == 'on'); ?>>
                                    <span class="ps-slider"></span>
                                </label>
                            </div>
                            <p class="ps-module-desc">One-click print product labels with QR codes for trade shows.</p>
                            <span class="ps-tag ps-tag-exhibition">Exhibition</span>
                        </div>

                        <!-- Module 5: Sales Rep Avatar -->
                        <div class="ps-module-card">
                            <div class="ps-module-header">
                                <h4 class="ps-module-title">🕴️ Sales Rep Avatar</h4>
                                <label class="ps-switch">
                                    <input type="checkbox" name="ps_active_modules[b2b_sales]" 
                                        <?php checked(isset($modules['b2b_sales']) && $modules['b2b_sales'] == 'on'); ?>>
                                    <span class="ps-slider"></span>
                                </label>
                            </div>
                            <p class="ps-module-desc">Dynamically change contact info based on ?rep=alice URL parameter.</p>
                            <span class="ps-tag ps-tag-growth">Growth</span>
                        </div>

                    </div>

                    <div style="margin-top: 30px;">
                        <?php submit_button('Save Modules Configuration', 'primary', 'ps-btn ps-btn-primary', false); ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
