<?php
/**
 * Pocket Showroom — 卸载清理脚本
 *
 * Fix #29: 插件卸载时删除自定义文章类型、选项、瞬态缓存等数据
 *
 * 安全检查: 仅当由 WordPress 卸载程序调用时才执行,
 * 防止未经授权的直接访问。
 *
 * @package PocketShowroom
 */

// 安全检查: 如果不是通过 WordPress 卸载程序触发，立即退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * ========================================
 * 1. 删除插件选项 (Options)
 * ========================================
 */
$ps_options = array(
    // 水印设置
    'ps_watermark_text',
    'ps_watermark_opacity',
    'ps_watermark_font_size',
    'ps_watermark_color',
    'ps_watermark_position',

    // Banner 设置
    'ps_banner_title',
    'ps_banner_desc',
    'ps_banner_button_text',
    'ps_banner_button_url',
    'ps_banner_image_id',
    'ps_banner_overlay_color',

    // 颜色设置
    'ps_primary_color',
    'ps_button_text_color',
    'ps_banner_title_color',
    'ps_banner_desc_color',
);

foreach ($ps_options as $option) {
    delete_option($option);
}

/**
 * ========================================
 * 2. 删除瞬态缓存 (Transients)
 * ========================================
 */
delete_transient('ps_last_updated_date');

/**
 * ========================================
 * 3. 删除自定义文章类型的所有文章及其 Meta
 * ========================================
 */
$ps_posts = get_posts(array(
    'post_type' => 'ps_item',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
));

foreach ($ps_posts as $post_id) {
    // 删除所有关联的 post meta
    $meta_keys = get_post_custom_keys($post_id);
    if ($meta_keys) {
        foreach ($meta_keys as $meta_key) {
            // 只删除插件的 _ps_ 前缀 meta，避免误伤
            if (strpos($meta_key, '_ps_') === 0) {
                delete_post_meta($post_id, $meta_key);
            }
        }
    }

    // 删除附件（缩略图等）
    $attachments = get_attached_media('', $post_id);
    foreach ($attachments as $attachment) {
        wp_delete_attachment($attachment->ID, true);
    }

    // 彻底删除文章（跳过回收站）
    wp_delete_post($post_id, true);
}

/**
 * ========================================
 * 4. 删除自定义分类法的所有术语
 * ========================================
 */
$ps_terms = get_terms(array(
    'taxonomy' => 'ps_category',
    'hide_empty' => false,
    'fields' => 'ids',
));

if (!is_wp_error($ps_terms) && !empty($ps_terms)) {
    foreach ($ps_terms as $term_id) {
        wp_delete_term($term_id, 'ps_category');
    }
}

/**
 * ========================================
 * 5. 清除定时任务 (如有)
 * ========================================
 * 当前无 cron 任务，保留钩子以备将来扩展
 */

/**
 * ========================================
 * 6. 清除角色和权限 (如有自定义)
 * ========================================
 * 当前无自定义角色/权限，保留钩子以备将来扩展
 */

// 刷新重写规则（删除CPT后需要）
flush_rewrite_rules();
