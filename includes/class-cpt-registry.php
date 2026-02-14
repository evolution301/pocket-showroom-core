<?php

if (!defined('ABSPATH')) {
    exit;
}

class PS_Core_CPT_Registry
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
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_filter('enter_title_here', array($this, 'change_title_placeholder'));
    }

    public function register_post_type()
    {
        $labels = array(
            'name' => _x('Showroom Items', 'Post Type General Name', 'pocket-showroom'),
            'singular_name' => _x('Item', 'Post Type Singular Name', 'pocket-showroom'),
            'menu_name' => __('Pocket Showroom', 'pocket-showroom'),
            'name_admin_bar' => __('Showroom Item', 'pocket-showroom'),
            'archives' => __('Item Archives', 'pocket-showroom'),
            'attributes' => __('Item Attributes', 'pocket-showroom'),
            'parent_item_colon' => __('Parent Item:', 'pocket-showroom'),
            'all_items' => __('All Items', 'pocket-showroom'),
            'add_new_item' => __('Add New Item', 'pocket-showroom'),
            'add_new' => __('Add New', 'pocket-showroom'),
            'new_item' => __('New Item', 'pocket-showroom'),
            'edit_item' => __('Edit Item', 'pocket-showroom'),
            'update_item' => __('Update Item', 'pocket-showroom'),
            'view_item' => __('View Item', 'pocket-showroom'),
            'view_items' => __('View Items', 'pocket-showroom'),
            'search_items' => __('Search Item', 'pocket-showroom'),
            'not_found' => __('Not found', 'pocket-showroom'),
            'not_found_in_trash' => __('Not found in Trash', 'pocket-showroom'),
            'featured_image' => __('Cover Image', 'pocket-showroom'),
            'set_featured_image' => __('Set cover image', 'pocket-showroom'),
            'remove_featured_image' => __('Remove cover image', 'pocket-showroom'),
            'use_featured_image' => __('Use as cover image', 'pocket-showroom'),
            'items_list' => __('Items list', 'pocket-showroom'),
        );
        $args = array(
            'label' => __('Showroom Item', 'pocket-showroom'),
            'description' => __('B2B Product Catalog', 'pocket-showroom'),
            'labels' => $labels,
            'supports' => array('title', 'thumbnail'),
            'taxonomies' => array('ps_category'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-grid-view',
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            'show_in_rest' => true,
        );
        register_post_type('ps_item', $args);
    }

    public function register_taxonomy()
    {
        $labels = array(
            'name' => _x('Collections', 'Taxonomy General Name', 'pocket-showroom'),
            'singular_name' => _x('Collection', 'Taxonomy Singular Name', 'pocket-showroom'),
            'menu_name' => __('Collections', 'pocket-showroom'),
            'all_items' => __('All Collections', 'pocket-showroom'),
            'new_item_name' => __('New Collection Name', 'pocket-showroom'),
            'add_new_item' => __('Add New Collection', 'pocket-showroom'),
            'edit_item' => __('Edit Collection', 'pocket-showroom'),
            'update_item' => __('Update Collection', 'pocket-showroom'),
            'view_item' => __('View Collection', 'pocket-showroom'),
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
        );
        register_taxonomy('ps_category', array('ps_item'), $args);
    }

    public function change_title_placeholder($title)
    {
        $screen = get_current_screen();
        if ($screen && 'ps_item' === $screen->post_type) {
            return __('Enter Product Name', 'pocket-showroom');
        }
        return $title;
    }

}
