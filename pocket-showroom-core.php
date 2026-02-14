<?php
/*
Plugin Name: Pocket Showroom Core
Description: A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.
Version: 1.2.3
Author: evolution301
Text Domain: pocket-showroom
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('PS_CORE_VERSION', '1.2.3');
define('PS_CORE_PATH', plugin_dir_path(__FILE__));
define('PS_CORE_URL', plugin_dir_url(__FILE__));

// GitHub Updater Configuration
define('PS_GITHUB_USER', 'evolution301');
define('PS_GITHUB_REPO', 'pocket-showroom-core');

// Include Classes
require_once PS_CORE_PATH . 'includes/class-cpt-registry.php';
require_once PS_CORE_PATH . 'includes/class-meta-fields.php';
require_once PS_CORE_PATH . 'includes/class-social-share.php';
require_once PS_CORE_PATH . 'includes/class-csv-importer.php';
require_once PS_CORE_PATH . 'includes/class-frontend-gallery.php';
require_once PS_CORE_PATH . 'includes/class-settings.php';
require_once PS_CORE_PATH . 'includes/class-image-watermarker.php';
require_once PS_CORE_PATH . 'includes/class-rest-api.php';
require_once PS_CORE_PATH . 'includes/class-plugin-updater.php';

// Initialize Classes
function ps_core_init_plugin()
{
    // 加载翻译文件（Fix #14: i18n 支持）
    load_plugin_textdomain('pocket-showroom', false, dirname(plugin_basename(__FILE__)) . '/languages');

    PS_CPT_Registry::get_instance();
    PS_Meta_Fields::get_instance();
    PS_Social_Share::get_instance();
    PS_CSV_Importer::get_instance();
    PS_Frontend_Gallery::get_instance();
    PS_Settings::get_instance();
    PS_Image_Watermarker::get_instance();
    PS_REST_API::get_instance();
}
add_action('plugins_loaded', 'ps_core_init_plugin');

// Initialize GitHub Updater
function ps_core_init_updater()
{
    if (class_exists('PocketShowroom_Core_Updater')) {
        new PocketShowroom_Core_Updater(PS_GITHUB_USER, PS_GITHUB_REPO, __FILE__);
    }
}
add_action('admin_init', 'ps_core_init_updater');

// Add Settings & Add New Item links next to Deactivate on Plugins page
function ps_core_action_links($links)
{
    $settings_link = '<a href="' . admin_url('edit.php?post_type=ps_item&page=ps-settings') . '">Settings</a>';
    $add_new_link = '<a href="' . admin_url('post-new.php?post_type=ps_item') . '">Add New Item</a>';
    array_unshift($links, $settings_link, $add_new_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ps_core_action_links');
