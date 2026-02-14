/*
Plugin Name: Pocket Showroom Core
Plugin URI: https://github.com/evolution301/pocket-showroom-core
Description: A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.
Version: 1.1.6
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

// Define Constants (V2 Namespace to avoid conflict with old plugin)
define('PS_V2_VERSION', '1.1.6');
define('PS_V2_PATH', plugin_dir_path(__FILE__));
define('PS_V2_URL', plugin_dir_url(__FILE__));

// GitHub Updater Configuration
define('PS_V2_GITHUB_USER', 'evolution301');
define('PS_V2_GITHUB_REPO', 'pocket-showroom-core');

// Include Trait
if (file_exists(PS_V2_PATH . 'includes/trait-singleton.php')) {
require_once PS_V2_PATH . 'includes/trait-singleton.php';
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
$file_path = PS_V2_PATH . 'includes/' . $class_file;
if (file_exists($file_path)) {
require_once $file_path;
}
}

// Initialize Classes
function ps_core_init_plugin()
{
// Load translation files
load_plugin_textdomain('pocket-showroom', false, dirname(plugin_basename(__FILE__)) . '/languages');

// Initialize all classes with existence checks (Using PS_Core_ prefix)
if (class_exists('PS_Core_CPT_Registry')) {
PS_Core_CPT_Registry::get_instance();
}
if (class_exists('PS_Core_Meta_Fields')) {
PS_Core_Meta_Fields::get_instance();
}
if (class_exists('PS_Core_Social_Share')) {
PS_Core_Social_Share::get_instance();
}
if (class_exists('PS_Core_CSV_Importer')) {
PS_Core_CSV_Importer::get_instance();
}
if (class_exists('PS_Core_Frontend_Gallery')) {
PS_Core_Frontend_Gallery::get_instance();
}
if (class_exists('PS_Core_Settings')) {
PS_Core_Settings::get_instance();
}
if (class_exists('PS_Core_Image_Watermarker')) {
PS_Core_Image_Watermarker::get_instance();
}
if (class_exists('PS_Core_REST_API')) {
PS_Core_REST_API::get_instance();
}
}
add_action('plugins_loaded', 'ps_core_init_plugin');

// Initialize GitHub Updater
function ps_core_init_updater()
{
if (class_exists('PS_Core_Plugin_Updater')) {
new PS_Core_Plugin_Updater(PS_V2_GITHUB_USER, PS_V2_GITHUB_REPO, __FILE__);
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