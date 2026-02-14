<?php
/*
Plugin Name: Pocket Showroom Core
Plugin URI: https://github.com/evolution301/pocket-showroom-core
Description: A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.
Version: 1.1.3
Author: evolution301
Author URI: https://github.com/evolution301
Text Domain: pocket-showroom
Requires at least: 5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('PS_CORE_VERSION', '1.1.3');
define('PS_CORE_PATH', plugin_dir_path(__FILE__));
define('PS_CORE_URL', plugin_dir_url(__FILE__));

// GitHub Updater Configuration
define('PS_GITHUB_USER', 'evolution301');
define('PS_GITHUB_REPO', 'pocket-showroom-core');

/**
 * Safe require_once with file existence and readability check
 * Prevents fatal errors if files are missing or corrupted
 */
function ps_safe_require($file_path, $file_name) {
    if (!file_exists($file_path)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Pocket Showroom: Missing file - {$file_name}");
        }
        return false;
    }
    if (!is_readable($file_path)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Pocket Showroom: File not readable - {$file_name}");
        }
        return false;
    }
    require_once $file_path;
    return true;
}

// Include Trait
ps_safe_require(PS_CORE_PATH . 'includes/trait-singleton.php', 'trait-singleton.php');

// Include Classes (all with safe loading)
$required_classes = array(
    'class-cpt-registry.php',
    'class-meta-fields.php',
    'class-social-share.php',
    'class-csv-importer.php',
    'class-frontend-gallery.php',
    'class-settings.php',
    'class-image-watermarker.php',
    'class-rest-api.php',
);

$missing_classes = array();
foreach ($required_classes as $class_file) {
    if (!ps_safe_require(PS_CORE_PATH . 'includes/' . $class_file, $class_file)) {
        $missing_classes[] = $class_file;
    }
}

// Include Updater (optional)
ps_safe_require(PS_CORE_PATH . 'includes/class-plugin-updater.php', 'class-plugin-updater.php');

// Initialize Classes
function ps_core_init_plugin()
{
    global $missing_classes;
    
    // Check critical classes
    $critical_classes = array(
        'PS_CPT_Registry'     => 'class-cpt-registry.php',
        'PS_Meta_Fields'      => 'class-meta-fields.php',
        'PS_Frontend_Gallery' => 'class-frontend-gallery.php',
    );
    
    foreach ($critical_classes as $class_name => $file_name) {
        if (!class_exists($class_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Pocket Showroom: Critical class missing - {$class_name}");
            }
            add_action('admin_notices', function() use ($file_name) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('Pocket Showroom: Critical file missing or corrupted: %s. Please reinstall the plugin.', 'pocket-showroom'),
                    '<code>' . esc_html($file_name) . '</code>'
                );
                echo '</p></div>';
            });
            return;
        }
    }
    
    // Load translation files
    load_plugin_textdomain('pocket-showroom', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialize all classes with existence checks
    if (class_exists('PS_CPT_Registry')) {
        PS_CPT_Registry::get_instance();
    }
    if (class_exists('PS_Meta_Fields')) {
        PS_Meta_Fields::get_instance();
    }
    if (class_exists('PS_Social_Share')) {
        PS_Social_Share::get_instance();
    }
    if (class_exists('PS_CSV_Importer')) {
        PS_CSV_Importer::get_instance();
    }
    if (class_exists('PS_Frontend_Gallery')) {
        PS_Frontend_Gallery::get_instance();
    }
    if (class_exists('PS_Settings')) {
        PS_Settings::get_instance();
    }
    if (class_exists('PS_Image_Watermarker')) {
        PS_Image_Watermarker::get_instance();
    }
    if (class_exists('PS_REST_API')) {
        PS_REST_API::get_instance();
    }
}
add_action('plugins_loaded', 'ps_core_init_plugin');

// Initialize GitHub Updater
function ps_core_init_updater()
{
    if (class_exists('PS_Plugin_Updater')) {
        new PS_Plugin_Updater(PS_GITHUB_USER, PS_GITHUB_REPO, __FILE__);
    }
}
add_action('admin_init', 'ps_core_init_updater');

// Add Settings & Add New Item links next to Deactivate on Plugins page
function ps_core_action_links($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=ps-settings') . '">Settings</a>';
    $add_new_link = '<a href="' . admin_url('post-new.php?post_type=ps_item') . '">Add New Item</a>';
    array_unshift($links, $settings_link, $add_new_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ps_core_action_links');
