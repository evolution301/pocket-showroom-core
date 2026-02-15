<?php
/**
 * Social Sharing Meta Tags (Open Graph, Twitter)
 *
 * @package PocketShowroom
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PS_Social_Share
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
        add_action('wp_head', [$this, 'output_meta_tags']);
    }

    public function output_meta_tags()
    {
        // Only run on frontend
        if (is_admin()) {
            return;
        }

        global $post;

        // Default values
        $og_title = get_bloginfo('name');
        $og_desc = get_bloginfo('description');
        $og_image = '';
        $og_url = home_url();
        $og_type = 'website';

        // 1. Single Product Page
        if (is_singular('ps_item') && $post) {
            $og_title = get_the_title($post->ID);
            $og_desc = get_the_excerpt($post->ID);
            if (empty($og_desc)) {
                $og_desc = wp_trim_words($post->post_content, 20);
            }
            $og_url = get_permalink($post->ID);
            $og_type = 'product';

            // Image Priority: Featured -> First Gallery Image -> Placeholder
            if (has_post_thumbnail($post->ID)) {
                $og_image = get_the_post_thumbnail_url($post->ID, 'large');
            } else {
                $gallery = get_post_meta($post->ID, '_ps_gallery_images', true);
                if (!empty($gallery)) {
                    $ids = explode(',', $gallery);
                    if (!empty($ids[0])) {
                        $og_image = wp_get_attachment_image_url($ids[0], 'large');
                    }
                }
            }

            // Fallback price if available
            $price = get_post_meta($post->ID, '_ps_list_price', true);
            if ($price) {
                echo '<meta property="product:price:amount" content="' . esc_attr($price) . '" />' . "\n";
                echo '<meta property="product:price:currency" content="USD" />' . "\n";
            }
        }
        // 2. Archive or Shortcode Page (Detection is tricky, but we can check usage)
        elseif (is_post_type_archive('ps_item') || (is_page() && has_shortcode($post->post_content, 'pocket_showroom'))) {
            $og_title = get_option('ps_banner_title', 'Pocket Showroom');
            $og_desc = get_option('ps_banner_desc', 'Browse our latest collection.');

            $banner_id = get_option('ps_banner_image_id');
            if ($banner_id) {
                $og_image = wp_get_attachment_image_url($banner_id, 'full');
            }
            $og_url = get_permalink();
        }

        // Output tags
        echo "\n<!-- Pocket Showroom Social Meta -->\n";

        // Open Graph
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($og_desc) . '" />' . "\n";
        if ($og_image) {
            echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url($og_url) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";

        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($og_desc) . '" />' . "\n";
        if ($og_image) {
            echo '<meta name="twitter:image" content="' . esc_url($og_image) . '" />' . "\n";
        }

        echo "<!-- End Pocket Showroom Social Meta -->\n\n";
    }
}

// Initialize
PS_Social_Share::get_instance();
