<?php
/*
Plugin Name: Pocket Showroom Core
Plugin URI: https://github.com/evolution301/pocket-showroom-core
Description: A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.
Version: 1.1.4
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
define('PS_CORE_VERSION', '1.1.4');
define('PS_CORE_PATH', plugin_dir_path(__FILE__));
define('PS_CORE_URL', plugin_dir_url(__FILE__));

// GitHub Updater Configuration
define('PS_GITHUB_USER', 'evolution301');
define('PS_GITHUB_REPO', 'pocket-showroom-core');

// Include Trait
if (file_exists(PS_CORE_PATH . 'includes/trait-singleton.php')) {
    require_once PS_CORE_PATH . 'includes/trait-singleton.php';
}

// Include Classes
$ps_class_files = array(
    'class-cpt-registry.php',
    'class-meta-fields.php',
    'class-social-share.php',
    'class-csv-importer.php',
    'class-frontend-gallery.php',
    'class-settings.php',
    'class-image-watermarker.php',
    'class-rest-api.php',
    'class-plugin-updater.php',
);

foreach ($ps_class_files as $class_file) {
    $file_path = PS_CORE_PATH . 'includes/' . $class_file;
    if (file_exists($file_path)) {
        require_once $file_path;
    }
}

// Initialize Classes
function ps_core_init_plugin()
{
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
