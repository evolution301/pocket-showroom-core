<?php

if (!defined('ABSPATH')) {
    exit;
}

class PS_Core_Social_Share
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
        // We use 'wp_head' to output Open Graph meta tags
        add_action('wp_head', array($this, 'add_og_tags'), 5);
    }

    public function add_og_tags()
    {
        global $post;

        // 1. Single Product Page
        if (is_singular('ps_item')) {
            $this->output_single_product_tags($post);
            return;
        }

        // 2. Showroom Archive or Page with Shortcode
        // We check if it is the post type archive OR if the current page content has our shortcode
        if (is_post_type_archive('ps_item') || (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'pocket_showroom'))) {
            $this->output_showroom_tags();
            return;
        }
    }

    private function output_single_product_tags($post)
    {
        $title = get_the_title($post->ID);
        $permalink = get_permalink($post->ID);

        // Construct Rich Description
        $model = get_post_meta($post->ID, '_ps_model', true);
        $material = get_post_meta($post->ID, '_ps_material', true);
        $price = get_post_meta($post->ID, '_ps_list_price', true);

        $desc_parts = array();
        if ($model)
            $desc_parts[] = "Model: $model";
        if ($price)
            $desc_parts[] = "Price: $price";
        if ($material)
            $desc_parts[] = "Material: $material";

        $description = implode(' | ', $desc_parts);
        if (empty($description)) {
            $description = wp_trim_words(get_the_excerpt($post->ID), 25);
        }

        // Image Logic: Featured > First Gallery Image > Banner Fallback
        $image_url = '';
        if (has_post_thumbnail($post->ID)) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'large');
        } else {
            // Try gallery
            $gallery_ids = get_post_meta($post->ID, '_ps_gallery_images', true);
            if ($gallery_ids) {
                $ids = explode(',', $gallery_ids);
                if (!empty($ids[0])) {
                    $img = wp_get_attachment_image_src($ids[0], 'large');
                    if ($img)
                        $image_url = $img[0];
                }
            }
        }

        // Fallback to global banner if no product image
        if (!$image_url) {
            $banner_id = get_option('ps_banner_image_id');
            if ($banner_id) {
                $img = wp_get_attachment_image_src($banner_id, 'large');
                if ($img)
                    $image_url = $img[0];
            }
        }

        $this->print_tags($title, $description, $permalink, $image_url);
    }

    private function output_showroom_tags()
    {
        $title = get_option('ps_banner_title', get_bloginfo('name'));
        $description = get_option('ps_banner_desc', get_bloginfo('description'));
        $permalink = home_url(add_query_arg(null, null));

        $image_url = '';
        $banner_id = get_option('ps_banner_image_id');
        if ($banner_id) {
            $img = wp_get_attachment_image_src($banner_id, 'large');
            if ($img)
                $image_url = $img[0];
        }

        $this->print_tags($title, $description, $permalink, $image_url);
    }

    private function print_tags($title, $description, $url, $image)
    {
        $site_name = get_bloginfo('name');

        echo "\n<!-- Pocket Showroom Social Share Tags -->\n";

        // Open Graph (Facebook, WhatsApp, WeChat, LinkedIn)
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";

        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
            // Recommended dimensions for WhatsApp
            echo '<meta property="og:image:width" content="1200" />' . "\n";
            echo '<meta property="og:image:height" content="630" />' . "\n";
        }

        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }

        echo "<!-- End Pocket Showroom Tags -->\n\n";
    }

}
