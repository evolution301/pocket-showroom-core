<?php
/**
 * Plugin Name: Pocket Showroom Core
 * Description: A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.
 * Version:     3.4.2
 * Author:      Your Name
 * Text Domain: pocket-showroom
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────
define('PS_CORE_VERSION', '3.4.2');
define('PS_CORE_PATH', plugin_dir_path(__FILE__));
define('PS_CORE_URL', plugin_dir_url(__FILE__));

// GitHub Updater
define('PS_GITHUB_USER', 'evolution301');
define('PS_GITHUB_REPO', 'pocket-showroom-core');

// ─── Load Classes ─────────────────────────────────────────────────────────────
require_once PS_CORE_PATH . 'includes/class-cpt-registry.php';
require_once PS_CORE_PATH . 'includes/class-meta-fields.php';
require_once PS_CORE_PATH . 'includes/class-social-share.php';
require_once PS_CORE_PATH . 'includes/class-csv-importer.php';
require_once PS_CORE_PATH . 'includes/class-frontend-gallery.php';
require_once PS_CORE_PATH . 'includes/class-settings.php';
require_once PS_CORE_PATH . 'includes/class-image-watermarker.php';
require_once PS_CORE_PATH . 'includes/class-rest-api.php';
require_once PS_CORE_PATH . 'includes/class-plugin-updater.php';
require_once PS_CORE_PATH . 'includes/class-module-loader.php';

// ─── Bootstrap ────────────────────────────────────────────────────────────────
/**
 * 初始化所有插件功能模块。
 * 挂载在 plugins_loaded，确保 WP 核心完全加载后再运行。
 */
function ps_core_init_plugin(): void
{
    load_plugin_textdomain('pocket-showroom', false, dirname(plugin_basename(__FILE__)) . '/languages');

    PS_CPT_Registry::get_instance();
    PS_Meta_Fields::get_instance();
    PS_Social_Share::get_instance();
    PS_CSV_Importer::get_instance();
    PS_Frontend_Gallery::get_instance();
    PS_Settings::get_instance();
    PS_Image_Watermarker::get_instance();
    PS_REST_API::get_instance();
    PS_Module_Loader::get_instance();
}
add_action('plugins_loaded', 'ps_core_init_plugin');

// ─── GitHub Updater ───────────────────────────────────────────────────────────
/**
 * 在 admin_init 时初始化 GitHub 自动更新检查器。
 */
function ps_core_init_updater(): void
{
    if (class_exists('PocketShowroom_Core_Updater')) {
        new PocketShowroom_Core_Updater(PS_GITHUB_USER, PS_GITHUB_REPO, __FILE__);
    }
}
add_action('admin_init', 'ps_core_init_updater');

// ─── Plugin Action Links ──────────────────────────────────────────────────────
/**
 * 在插件列表页 "Deactivate" 旁添加 Settings 和 Add New Item 快捷链接。
 *
 * @param array $links 原始链接数组。
 * @return array 修改后的链接数组。
 */
function ps_core_action_links(array $links): array
{
    $settings_link = '<a href="' . admin_url('edit.php?post_type=ps_item&page=ps-settings') . '">' . __('Settings', 'pocket-showroom') . '</a>';
    $add_new_link = '<a href="' . admin_url('post-new.php?post_type=ps_item') . '">' . __('Add New Item', 'pocket-showroom') . '</a>';
    array_unshift($links, $settings_link, $add_new_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ps_core_action_links');

// ─── One-Time Fix for Existing Toggles ──────────────────────────────────────────
/**
 * 临时修复脚本：自动遍历所有已导入的产品，根据是否有数据来修正开关状态。
 * 这个脚本只会执行一次，执行后会在数据库打上标记。
 */
add_action('admin_init', function () {
    if (!get_option('ps_fixed_empty_toggles_v3')) {
        $products = get_posts([
            'post_type' => 'ps_item',
            'post_status' => 'any',
            'posts_per_page' => -1
        ]);

        foreach ($products as $p) {
            $post_id = $p->ID;
            $sku = get_post_meta($post_id, '_ps_model', true);
            $price = get_post_meta($post_id, '_ps_list_price', true);
            $material = get_post_meta($post_id, '_ps_material', true);
            $moq = get_post_meta($post_id, '_ps_moq', true);
            $loading = get_post_meta($post_id, '_ps_loading', true);
            $lead_time = get_post_meta($post_id, '_ps_lead_time', true);
            $variants_raw = get_post_meta($post_id, '_ps_sizes', true);
            $custom_fields_raw = get_post_meta($post_id, '_ps_custom_fields', true);

            update_post_meta($post_id, '_ps_show_model', !empty($sku) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_list_price', !empty($price) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_material', !empty($material) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_moq', !empty($moq) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_loading', !empty($loading) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_lead_time', !empty($lead_time) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_sizes', !empty($variants_raw) ? '1' : '0');
            update_post_meta($post_id, '_ps_show_custom_fields', !empty($custom_fields_raw) ? '1' : '0');
        }
        update_option('ps_fixed_empty_toggles_v3', 1);
    }
});
