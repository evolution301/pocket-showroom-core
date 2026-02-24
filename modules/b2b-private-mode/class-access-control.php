<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PS_B2B_Access_Control
 * 
 * Module 3: Private Showroom
 * Protects B2B content with a password barrier.
 * 
 * @package Pocket Showroom
 * @since 3.0.0
 */
class PS_B2B_Access_Control
{
    private static $instance = null;
    private $cookie_name = 'ps_showroom_auth';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // 1. Settings: Add Password Field
        add_action('admin_init', [$this, 'register_password_setting']);

        // 2. Intercept Content Viewing
        add_action('template_redirect', [$this, 'check_access']);

        // 3. Handle Login
        add_action('init', [$this, 'handle_login_submission']);
    }

    public function register_password_setting()
    {
        register_setting('ps_settings_group', 'ps_private_password');
        add_settings_section('ps_private_mode_section', 'Private Mode Settings', null, 'ps_settings_modules');
        add_settings_field('ps_private_password', 'Showroom Password', function () {
            echo '<input type="text" name="ps_private_password" value="' . esc_attr(get_option('ps_private_password')) . '" />';
            echo '<p class="description">Leave empty to disable global protection.</p>';
        }, 'ps_settings_modules', 'ps_private_mode_section');
    }

    public function handle_login_submission()
    {
        if (isset($_POST['ps_showroom_password'])) {
            $input_pass = sanitize_text_field($_POST['ps_showroom_password']);
            $real_pass = get_option('ps_private_password');

            if ($input_pass === $real_pass) {
                // Determine cookie path based on WP installation
                $path = parse_url(get_option('siteurl'), PHP_URL_PATH);
                $path = $path ? $path : '/';

                setcookie($this->cookie_name, md5($real_pass . 'salt'), time() + 86400, $path);
                wp_redirect(remove_query_arg('ps_login_error'));
                exit;
            } else {
                wp_redirect(add_query_arg('ps_login_error', '1'));
                exit;
            }
        }
    }

    public function check_access()
    {
        // If password not set, module is "dormant" even if enabled
        $password = get_option('ps_private_password');
        if (empty($password))
            return;

        // Check if user is already authenticated
        if (isset($_COOKIE[$this->cookie_name]) && $_COOKIE[$this->cookie_name] === md5($password . 'salt')) {
            return; // Access Granted
        }

        // Check if we are viewing a protected page (Showroom Item, or Archive)
        if (is_singular('ps_item') || is_post_type_archive('ps_item') || is_tax('ps_category')) {
            // Access Denied: Show Login Form
            $this->render_login_page();
            exit;
        }
    }

    private function render_login_page()
    {
        $error = isset($_GET['ps_login_error']) ? 'Incorrect Password. Please try again.' : '';
        ?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>Private Showroom Login</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body {
                    font-family: -apple-system, sans-serif;
                    background: #f3f4f6;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                }

                .login-card {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    width: 100%;
                    max-width: 320px;
                    text-align: center;
                }

                h2 {
                    margin-top: 0;
                    color: #111827;
                }

                input[type="password"] {
                    width: 100%;
                    padding: 12px;
                    margin: 20px 0;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    box-sizing: border-box;
                }

                button {
                    width: 100%;
                    padding: 12px;
                    background: #2563EB;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    font-weight: 600;
                    cursor: pointer;
                }

                button:hover {
                    background: #1d4ed8;
                }

                .error {
                    color: #ef4444;
                    font-size: 13px;
                    margin-bottom: 15px;
                }
            </style>
        </head>

        <body>
            <div class="login-card">
                <h2>🔒 Private Showroom</h2>
                <p style="color:#6b7280; font-size:14px;">Please enter the password to view this collection.</p>
                <?php if ($error): ?>
                    <div class="error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <input type="password" name="ps_showroom_password" placeholder="Password" required autofocus>
                    <button type="submit">Enter Showroom</button>
                </form>
            </div>
        </body>

        </html>
        <?php
    }
}

// Initialize
PS_B2B_Access_Control::get_instance();
