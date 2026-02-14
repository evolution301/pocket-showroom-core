=== Pocket Showroom Core ===
Contributors: Evolution301
Tags: b2b, catalog, product showroom, csv import, gallery, furniture, wholesale
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modern B2B product catalog with CSV import, multi-image gallery, and interactive frontend.

== Description ==

Pocket Showroom Core is a WordPress plugin designed for B2B furniture exporters and wholesalers. It replaces traditional PDF catalogs with an interactive online product showroom experience.

**Key Features:**

* **Interactive Product Gallery** - Modern grid layout with category filtering and search
* **CSV Bulk Import/Export** - Import hundreds of products instantly
* **Multi-Image Gallery** - Drag-and-drop image upload with sorting
* **Social Sharing** - Beautiful Open Graph cards for WhatsApp/WeChat sharing
* **Image Watermarking** - Automatic watermark on uploaded images
* **AJAX-powered Details** - Product details in modal without page refresh
* **Responsive Design** - Perfect on mobile, tablet, and desktop

== Installation ==

1. Upload `pocket-showroom-core` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Pocket Showroom** → **Settings** to configure
4. Use shortcode `[pocket_showroom]` on any page

== Frequently Asked Questions ==

= How do I add products? =

Go to **Pocket Showroom** → **Add New** in the WordPress admin. Fill in product details and upload images.

= Can I import products from CSV? =

Yes! Go to **Pocket Showroom** → **Import/Export**, download the CSV template, fill it with your data, and upload.

= How do I display the showroom on my site? =

Simply add the shortcode `[pocket_showroom]` to any page or post.

== Screenshots ==

1. Frontend product gallery with grid layout
2. Product detail modal with all specifications
3. Admin settings page
4. CSV import interface

== Changelog ==

= 1.2.2 =
* Fix: Critical Error - System crash due to class name collision in updater. Renamed to PocketShowroom_Core_Updater and added safety checks.

= 1.2.1 =
* Fix: ZIP package structure now includes the top-level folder to allow proper overwriting/updating in WordPress.

= 1.2.0 =
* Feature: Restored GitHub Auto-Updater (PS_Plugin_Updater).

= 1.1.9 =
* Fix: Settings link pointing to incorrect page slug (ps-settings).

= 1.1.8 =
* Revert: Restored original plugin codebase (v1.0.0 base) as requested.
* Note: This version removes the namespace refactoring.

= 1.1.7 =
* Fix: Added missing PHP opening tag (Critical Fix)

= 1.1.6 =
* Fix: Critical Error resolution via namespace isolation (PS_Core prefix)
* Improvement: Complete conflict immunity with legacy versions


= 1.1.5 =
* Fix: Auto-release zip structure now includes correct plugin folder (Resolves "Plugin file does not exist")
* Improvement: Standardized versioning across all files

= 1.1.3 =
* Fix: Added safe file loading for all class files to prevent fatal errors
* Improvement: Better error handling with admin notices for missing files
* Improvement: Class existence checks before initialization

= 1.1.2 =
* Fix: Critical error due to missing file dependencies
* Improvement: Enhanced error handling for cloud connectivity

= 1.1.1 =
* Initial public release
* GitHub auto-update support
* Bug fixes and performance improvements

= 1.1.0 =
* Added CSV import/export functionality
* Multi-image gallery support
* Social sharing with OG tags

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.1 =
First public release with GitHub auto-update support!

== Additional Info ==

This plugin supports automatic updates from GitHub Releases. When a new version is released, you'll see an update notification in your WordPress admin.
