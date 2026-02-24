<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_B2B_Sales_Rep
 * 
 * Module 5: Sales Rep Avatar
 * Allows different sales reps to share the same catalog but with their own contact info.
 * 
 * @package Pocket Showroom
 * @since 3.0.0
 */
class PS_B2B_Sales_Rep
{
    private static $instance = null;
    private $cookie_name = 'ps_sales_rep';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 1. Add "WhatsApp" field to User Profile
        add_action('show_user_profile', [$this, 'add_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_profile_fields']);
        add_action('personal_options_update', [$this, 'save_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_profile_fields']);

        // 2. Capture ?rep=username URL parameter
        add_action('init', [$this, 'capture_rep_parameter']);

        // 3. Inject Floating Contact Button (Frontend)
        add_action('wp_footer', [$this, 'render_floating_contact']);
    }

    /**
     * Add fields to User Profile
     */
    public function add_profile_fields($user)
    {
        ?>
        <h3>Pocket Showroom - Sales Rep Info</h3>
        <table class="form-table">
            <tr>
                <th><label for="ps_whatsapp">WhatsApp / Mobile</label></th>
                <td>
                    <input type="text" name="ps_whatsapp" id="ps_whatsapp"
                        value="<?php echo esc_attr(get_the_author_meta('ps_whatsapp', $user->ID)); ?>"
                        class="regular-text" /><br />
                    <span class="description">Your personal inquiry number. Format: 8613800000000</span>
                </td>
            </tr>
            <tr>
                <th><label for="ps_wechat">WeChat ID</label></th>
                <td>
                    <input type="text" name="ps_wechat" id="ps_wechat"
                        value="<?php echo esc_attr(get_the_author_meta('ps_wechat', $user->ID)); ?>" class="regular-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save fields
     */
    public function save_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id))
            return false;

        update_user_meta($user_id, 'ps_whatsapp', sanitize_text_field($_POST['ps_whatsapp']));
        update_user_meta($user_id, 'ps_wechat', sanitize_text_field($_POST['ps_wechat']));
    }

    /**
     * Capture ?rep=alice and store in cookie
     */
    public function capture_rep_parameter()
    {
        if (isset($_GET['rep'])) {
            $username = sanitize_text_field($_GET['rep']);
            $user = get_user_by('login', $username);

            if ($user) {
                // Determine cookie path based on WP installation
                $path = parse_url(get_option('siteurl'), PHP_URL_PATH);
                $path = $path ? $path : '/';

                // Store user ID in cookie for 30 days
                setcookie($this->cookie_name, (string) $user->ID, time() + 2592000, $path);

                // Refresh execution with new rep (optional, but good for caching)
                $_COOKIE[$this->cookie_name] = $user->ID;
            }
        }
    }

    /**
     * Get Current Sales Rep (or Default Admin)
     */
    public function get_current_rep()
    {
        $rep_id = 0;

        // 1. Try Cookie
        if (isset($_COOKIE[$this->cookie_name])) {
            $rep_id = intval($_COOKIE[$this->cookie_name]);
        }

        // 2. Fallback: Post Author (if single product)
        if (!$rep_id && is_singular('ps_item')) {
            global $post;
            $rep_id = $post->post_author;
        }

        // 3. Fallback: Site Admin
        if (!$rep_id) {
            $rep_id = 1; // Default to admin
        }

        return get_userdata($rep_id);
    }

    /**
     * Render Sticky Contact Button
     */
    public function render_floating_contact()
    {
        $rep = $this->get_current_rep();
        if (!$rep)
            return;

        $whatsapp = get_user_meta($rep->ID, 'ps_whatsapp', true);
        $wechat = get_user_meta($rep->ID, 'ps_wechat', true);
        $avatar = get_avatar_url($rep->ID);

        if (!$whatsapp && !$wechat)
            return; // Nothing to show

        ?>
        <div id="ps-sales-rep-float" class="ps-sales-float">

            <!-- Chat Bubble -->
            <div class="ps-sales-bubble">
                <img src="<?php echo esc_url($avatar); ?>" class="ps-sales-avatar">
                <div class="ps-sales-info">
                    <small>Contact Sales Rep</small>
                    <h4><?php echo esc_html($rep->display_name); ?></h4>
                </div>
            </div>

            <!-- Buttons -->
            <?php if ($whatsapp): ?>
                <a href="https://wa.me/<?php echo esc_attr($whatsapp); ?>" target="_blank" class="ps-sales-btn">
                    <span class="dashicons dashicons-whatsapp"></span> WhatsApp
                </a>
            <?php endif; ?>

        </div>
        <?php
    }
}

// Initialize
PS_B2B_Sales_Rep::get_instance();
