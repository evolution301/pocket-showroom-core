/**
 * ============================================================
 * POCKET SHOWROOM — ADMIN SCRIPT
 * ============================================================
 * 功能隔离架构：本文件分为 3 个独立的 IIFE 模块
 *
 * 模块 A: 产品编辑页 (.ps-edit-page)
 *   - Gallery 相册管理
 *   - 尺寸规格动态行
 *   - Quick Edit 集成
 *
 * 模块 B: Settings 设置页 (.ps-settings-page)
 *   - Tab 切换
 *   - 颜色选择器（Divi Clone）
 *   - 水印预览
 *   - Banner 预览
 *
 * 模块 C: 公共工具函数 (Admin 全局，无守卫)
 *   - 颜色转换工具（hexToRgb 等）
 *   ============================================================
 */

/* ============================================================
 * 模块 C：公共颜色工具函数（无守卫，供模块 B 调用）
 * ============================================================ */
var PS_ColorUtils = (function () {
    'use strict';

    function componentToHex(c) {
        var hex = c.toString(16);
        return hex.length == 1 ? '0' + hex : hex;
    }

    function rgbToHex(r, g, b) {
        return '#' + componentToHex(r) + componentToHex(g) + componentToHex(b);
    }

    /**
     * HEX 转 RGB，支持三位简写 #fff → #ffffff
     */
    function hexToRgb(hex) {
        hex = (hex || '').replace(/^#/, '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        var result = /^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function hsvToRgb(h, s, v) {
        s /= 100; v /= 100;
        var r, g, b, i, f, p, q, t;
        i = Math.floor(h * 6);
        f = h * 6 - i;
        p = v * (1 - s);
        q = v * (1 - f * s);
        t = v * (1 - (1 - f) * s);
        switch (i % 6) {
            case 0: r = v; g = t; b = p; break;
            case 1: r = q; g = v; b = p; break;
            case 2: r = p; g = v; b = t; break;
            case 3: r = p; g = q; b = v; break;
            case 4: r = t; g = p; b = v; break;
            case 5: r = v; g = p; b = q; break;
        }
        return { r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255) };
    }

    function rgbToHsv(r, g, b) {
        r /= 255; g /= 255; b /= 255;
        var max = Math.max(r, g, b), min = Math.min(r, g, b);
        var h, s, v = max;
        var d = max - min;
        s = max == 0 ? 0 : d / max;
        if (max == min) h = 0;
        else {
            switch (max) {
                case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                case g: h = (b - r) / d + 2; break;
                case b: h = (r - g) / d + 4; break;
            }
            h /= 6;
        }
        return { h: h, s: s * 100, v: v * 100 };
    }

    /**
     * 从触摸或鼠标事件中提取坐标
     */
    function getEventCoords(e) {
        if (e.type && e.type.indexOf('touch') !== -1) {
            var touch = e.originalEvent
                ? e.originalEvent.touches[0] || e.originalEvent.changedTouches[0]
                : e.touches[0] || e.changedTouches[0];
            return { pageX: touch.pageX, pageY: touch.pageY };
        }
        return { pageX: e.pageX, pageY: e.pageY };
    }

    return { rgbToHex: rgbToHex, hexToRgb: hexToRgb, hsvToRgb: hsvToRgb, rgbToHsv: rgbToHsv, getEventCoords: getEventCoords };
})();


/* ============================================================
 * 模块 A：产品编辑页 — 守卫：.ps-edit-page
 * ============================================================ */
jQuery(function ($) {
    'use strict';

    // ★ 安全门：如果当前页面不是产品编辑页，直接全部跳过
    if (!$('.ps-edit-page').length) return;

    var mediaUploader;

    // --- Gallery 相册管理 ---
    // 支持 ID 选择器（新UI）、Class 选择器（Fallback） 和批量上传按钮
    $('#ps-add-images, .ps-add-gallery-btn, #ps-bulk-upload-btn').on('click', function (e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: ps_admin_vars.select_images,
            button: { text: ps_admin_vars.add_to_gallery },
            multiple: 'add'
        });
        mediaUploader.on('select', function () {
            var selection = mediaUploader.state().get('selection');
            var ids = [];
            selection.map(function (attachment) {
                attachment = attachment.toJSON();
                ids.push(attachment.id);
                // 防御：thumbnail 可能不存在，使用 fallback
                var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;
                var template = '<div class="ps-gallery-item" data-id="' + attachment.id + '"><img src="' + thumbUrl + '"><span class="remove">×</span></div>';
                $('#ps-add-images').before(template);
            });
            updateGalleryIds();
        });
        mediaUploader.open();
    });

    // 移除相册图片
    $(document).on('click', '.ps-gallery-item .remove', function () {
        $(this).parent('.ps-gallery-item').remove();
        updateGalleryIds();
    });

    // 拖拽排序（加异常保护）
    if ($.fn.sortable) {
        try {
            $('.ps-gallery-grid').sortable({
                items: '.ps-gallery-item',
                cursor: 'move',
                update: function () { updateGalleryIds(); }
            });
        } catch (e) {
            console.warn('Pocket Showroom: gallery sortable init failed', e);
        }
    }

    /**
     * 更新相册隐藏 input 里的 ID 列表
     */
    function updateGalleryIds() {
        var ids = [];
        $('.ps-gallery-item').each(function () { ids.push($(this).data('id')); });
        $('#_ps_gallery_images').val(ids.join(','));
    }

    // --- 尺寸变体管理 ---
    $('#ps-add-size').on('click', function (e) {
        e.preventDefault();
        var index = $('#ps-size-variants .ps-size-row').length;
        var html = `
            <div class="ps-size-row" style="display:flex; gap:10px; margin-bottom:10px;">
                <input type="text" class="ps-input" name="_ps_size_variants[${index}][label]" placeholder="${ps_admin_vars.variant_name_placeholder}" style="flex:1;">
                <input type="text" class="ps-input" name="_ps_size_variants[${index}][value]" placeholder="${ps_admin_vars.dimensions_placeholder}" style="flex:2;">
                <label class="ps-switch" style="transform: scale(0.6); margin: 0 5px; align-self:center;">
                    <input type="checkbox" name="_ps_size_variants[${index}][show]" value="1" checked>
                    <span class="ps-slider round"></span>
                </label>
                <span class="dashicons dashicons-move ps-sort-handle" style="cursor:move; color:#ccc; align-self:center;"></span>
                <button type="button" class="ps-remove-btn" style="color:red; background:none; border:none; cursor:pointer;">×</button>
            </div>
        `;
        $('#ps-size-variants').append(html);
        updateSizeVariantIndices();
    });

    // 尺寸变体拖拽排序
    if ($.fn.sortable) {
        $('#ps-size-variants').sortable({
            handle: '.ps-sort-handle',
            cursor: 'move',
            update: function () { updateSizeVariantIndices(); }
        });
    }

    /**
     * 重排尺寸变体的 name 索引，确保 POST 数据顺序正确
     */
    function updateSizeVariantIndices() {
        $('#ps-size-variants .ps-size-row').each(function (index) {
            $(this).find('input[name*="[label]"]').attr('name', '_ps_size_variants[' + index + '][label]');
            $(this).find('input[name*="[value]"]').attr('name', '_ps_size_variants[' + index + '][value]');
            $(this).find('input[name*="[show]"]').attr('name', '_ps_size_variants[' + index + '][show]');
        });
    }

    // 删除行（尺寸或规格）
    $(document).on('click', '.ps-remove-btn', function () {
        var $parent = $(this).closest('.ps-spec-row, .ps-size-row');
        var isSizeRow = $parent.hasClass('ps-size-row');
        $parent.remove();
        if (isSizeRow) updateSizeVariantIndices();
    });

    // --- 动态规格行 (Dynamic Specifications) ---
    $('#ps-add-spec').on('click', function (e) {
        e.preventDefault();
        var template = `
            <div class="ps-spec-row">
                <input type="text" name="_ps_dynamic_specs[key][]" class="ps-spec-key" placeholder="${ps_admin_vars.field_name_placeholder}" value="">
                <input type="text" name="_ps_dynamic_specs[val][]" class="ps-spec-val" placeholder="${ps_admin_vars.value_placeholder}" value="">
                <input type="hidden" name="_ps_dynamic_specs[show][]" class="ps-spec-show-val" value="1">
                <label class="ps-switch" style="transform: scale(0.6); margin: 0 5px; align-self:center;">
                    <input type="checkbox" class="ps-spec-show-cb" checked>
                    <span class="ps-slider round"></span>
                </label>
                <span class="dashicons dashicons-move ps-sort-handle" style="cursor:move; color:#ccc; align-self:center;"></span>
                <button type="button" class="ps-remove-btn">×</button>
            </div>
        `;
        $('#ps-dynamic-specs').append(template);
    });

    // 动态规格拖拽排序
    if ($.fn.sortable) {
        $('#ps-dynamic-specs').sortable({
            handle: '.ps-sort-handle',
            cursor: 'move'
        });
    }

    // 同步可见性 checkbox 到隐藏 input
    $(document).on('change', '.ps-spec-show-cb', function () {
        $(this).closest('.ps-switch').siblings('.ps-spec-show-val').val(this.checked ? '1' : '0');
    });

    // The end of Module A
});

/* ============================================================
 * 模块 D：Quick Edit 媒体集成
 * ============================================================ */
jQuery(function ($) {
    'use strict';

    if (typeof inlineEditPost !== 'undefined') {
        var $wp_inline_edit = inlineEditPost.edit;

        /**
         * 在 Quick Edit 区域渲染缩略图预览
         */
        function updateQEPreview($container, idsString) {
            $container.empty();
            if (!idsString) return;

            var ids = idsString.split(',');
            var displayIds = ids.slice(0, 5);
            var remaining = ids.length - 5;

            var html = '';
            displayIds.forEach(function (id) {
                var attachment = wp.media.attachment(id);
                if (attachment.get('url')) {
                    var url = attachment.get('sizes') && attachment.get('sizes').thumbnail
                        ? attachment.get('sizes').thumbnail.url
                        : attachment.get('url');
                    html += '<img src="' + url + '" style="width:30px; height:30px; object-fit:cover; border-radius:3px; border:1px solid #ccc;"/>';
                    if (displayIds.indexOf(id) === displayIds.length - 1) finishRender();
                } else {
                    attachment.fetch().done(function () {
                        var url = attachment.get('sizes') && attachment.get('sizes').thumbnail
                            ? attachment.get('sizes').thumbnail.url
                            : attachment.get('url');
                        $container.append('<img src="' + url + '" style="width:30px; height:30px; object-fit:cover; border-radius:3px; border:1px solid #ccc;"/>');
                        if ($container.children('img').length === displayIds.length && remaining > 0) {
                            $container.append('<span style="font-size:12px; font-weight:bold; color:#666; margin-left:5px;">+' + remaining + '</span>');
                        }
                    });
                }
            });

            function finishRender() {
                $container.html(html);
                if (remaining > 0) {
                    $container.append('<span style="font-size:12px; font-weight:bold; color:#666; margin-left:5px;">+' + remaining + '</span>');
                }
            }
        }

        inlineEditPost.edit = function (id) {
            $wp_inline_edit.apply(this, arguments);
            var post_id = 0;
            if (typeof (id) == 'object') {
                post_id = parseInt(this.getId(id));
            }
            if (post_id > 0) {
                var $editRow = $('#edit-' + post_id);
                var $postRow = $('#post-' + post_id);
                var galleryIds = $postRow.find('.ps-qe-gallery-data').text();
                $editRow.find('#ps_qe_gallery_images').val(galleryIds);
                var $preview = $editRow.find('.ps-qe-image-preview');
                $preview.css({ 'display': 'flex', 'gap': '5px', 'align-items': 'center' });
                updateQEPreview($preview, galleryIds);
            }
        };

        // Quick Edit 添加媒体按钮
        $(document).on('click', '.ps-qe-add-media', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $input = $btn.siblings('#ps_qe_gallery_images');
            var $preview = $btn.siblings('.ps-qe-image-preview');

            var qeUploader = wp.media({
                title: ps_admin_vars.select_images || 'Select Images',
                button: { text: ps_admin_vars.use_this_image || 'Select' },
                multiple: 'add'
            });

            qeUploader.on('open', function () {
                var selection = qeUploader.state().get('selection');
                var ids = $input.val();
                if (ids) {
                    ids = ids.split(',');
                    ids.forEach(function (id) {
                        var attachment = wp.media.attachment(id);
                        attachment.fetch();
                        selection.add(attachment ? [attachment] : []);
                    });
                }
            });

            qeUploader.on('select', function () {
                var selection = qeUploader.state().get('selection');
                var ids = [];
                selection.map(function (attachment) { ids.push(attachment.id); });
                $input.val(ids.join(','));
                $preview.css({ 'display': 'flex', 'gap': '5px', 'align-items': 'center' });
                updateQEPreview($preview, ids.join(','));
            });

            qeUploader.open();
        });
    }
});


/* ============================================================
 * 模块 B：Settings 设置页 — 守卫：.ps-settings-page
 * ============================================================ */
jQuery(function ($) {
    'use strict';

    // ★ 安全门：如果当前页面不是 Settings 页，直接全部跳过
    if (!$('.ps-settings-page').length) return;

    // --- Tab 切换逻辑 ---
    // 仅限 .ps-settings-page 内部的 nav-tab，不影响其他页面
    $('.ps-settings-page .nav-tab-wrapper a').click(function (e) {
        e.preventDefault();
        var href = $(this).attr('href');
        if (!href || href.charAt(0) !== '#') return;
        $('.ps-settings-page .nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.ps-settings-page .ps-tab-content').hide();
        $(href).show();
    });

    // --- Banner 文字 Live Preview ---
    $('#ps_banner_title').on('input', function () {
        var val = $(this).val();
        $('#ps-preview-banner-title').text(val ? val : ps_admin_vars.banner_title_fallback);
    });

    $('#ps_banner_desc').on('input', function () {
        var val = $(this).val();
        $('#ps-preview-banner-desc').text(val ? val : ps_admin_vars.banner_desc_fallback);
    });

    // Banner 按钮文字预览
    $('#ps_banner_button_text').on('input', function () {
        var val = $(this).val();
        $('#ps-preview-banner-btn').text(val ? val : ps_admin_vars.explore_now);
    });

    // Banner CTA 按钮大小实时预览
    $('#ps_banner_cta_scale').on('input', function () {
        var scale = parseFloat($(this).val());
        $('#ps-cta-scale-val').text(Math.round(scale * 100));
        var btn = $('#ps-preview-banner-btn');
        btn.css({
            'padding': 'calc(12px * ' + scale + ') calc(28px * ' + scale + ')',
            'font-size': 'calc(14px * ' + scale + ')',
        });
    });

    // Banner Share 按钮大小实时预览
    $('#ps_banner_share_scale').on('input', function () {
        var scale = parseFloat($(this).val());
        $('#ps-share-scale-val').text(Math.round(scale * 100));
        var btn = $('#ps-preview-share-btn');
        btn.css({
            'padding': 'calc(10px * ' + scale + ') calc(22px * ' + scale + ')',
            'font-size': 'calc(13px * ' + scale + ')',
        });
        btn.find('svg').css({
            'width': 'calc(16px * ' + scale + ')',
            'height': 'calc(16px * ' + scale + ')',
        });
    });

    // --- Banner 图片上传 ---
    var bannerFrame;
    $('#ps_banner_image_btn').on('click', function (e) {
        e.preventDefault();
        if (bannerFrame) { bannerFrame.open(); return; }
        bannerFrame = wp.media({
            title: ps_admin_vars.select_banner_image,
            button: { text: ps_admin_vars.use_this_image },
            multiple: false
        });
        bannerFrame.on('select', function () {
            var attachment = bannerFrame.state().get('selection').first().toJSON();
            $('#ps_banner_image_id').val(attachment.id);
            $('.ps-image-preview-wrapper').html('<img src="' + attachment.url + '" style="max-width:100%;">');
            $('#ps-live-preview-container').css('background-image', 'url(' + attachment.url + ')');
        });
        bannerFrame.open();
    });

    // 移除 Banner 图片
    $('#ps_remove_banner_image').on('click', function (e) {
        e.preventDefault();
        $('#ps_banner_image_id').val('');
        $('.ps-image-preview-wrapper').html('<div class="ps-no-image">' + ps_admin_vars.no_image + '</div>');
        $('#ps-live-preview-container').css('background-image', 'none');
    });

    // --- 水印上传 ---
    var wmUploader;
    $('#ps-upload-watermark').on('click', function (e) {
        e.preventDefault();
        if (wmUploader) { wmUploader.open(); return; }
        wmUploader = wp.media({
            title: ps_admin_vars.select_watermark_image,
            button: { text: ps_admin_vars.use_this_image },
            multiple: false
        });
        wmUploader.on('select', function () {
            var attachment = wmUploader.state().get('selection').first().toJSON();
            $('#ps_watermark_image_id').val(attachment.id);
            $('#ps-watermark-image-preview').html('<img src="' + attachment.url + '" style="max-width:100px;">');
            $('#ps-remove-watermark').show();
            updateWatermarkPreview();
        });
        wmUploader.open();
    });

    $('#ps-remove-watermark').on('click', function () {
        $('#ps_watermark_image_id').val('');
        $('#ps-watermark-image-preview').empty();
        $(this).hide();
        updateWatermarkPreview();
    });

    // --- 水印实时预览 ---
    /**
     * 根据当前表单值更新水印预览层的位置/内容/透明度
     */
    function updateWatermarkPreview() {
        var type = $('input[name="ps_watermark_type"]:checked').val();
        var text = $('#ps_watermark_text').val();
        var opacity = $('#ps_watermark_opacity').val();
        var size = $('#ps_watermark_size').val();
        var position = $('input[name="ps_watermark_position"]:checked').val();
        var rotation = $('#ps_watermark_rotation').val();

        $('#ps-opacity-val').text(opacity);
        $('#ps-size-val').text(size);

        if (type === 'text') {
            $('.ps-type-text').show();
            $('.ps-type-image').hide();
            $('#ps-watermark-layer .ps-wm-text').text(text).show();
            $('#ps-watermark-layer .ps-wm-image').hide();
            var fontSize = Math.max(12, (size * 5));
            $('#ps-watermark-layer .ps-wm-text').css('font-size', fontSize + 'px');
        } else {
            $('.ps-type-text').hide();
            $('.ps-type-image').show();
            var imgUrl = $('#ps-watermark-image-preview img').attr('src');
            if (imgUrl) {
                $('#ps-watermark-layer .ps-wm-image').attr('src', imgUrl).show();
                $('#ps-watermark-layer .ps-wm-text').hide();
                $('#ps-watermark-layer .ps-wm-image').css('width', (size * 3) + 'px');
            } else {
                $('#ps-watermark-layer .ps-wm-text').text(ps_admin_vars.no_image_parens).show();
            }
        }

        $('#ps-watermark-layer').css('opacity', opacity / 100);

        // 位置逻辑
        var layer = $('#ps-watermark-layer');
        var top = 'auto', bottom = 'auto', left = 'auto', right = 'auto';
        var transform = 'rotate(' + rotation + 'deg)';

        switch (position) {
            case 'tl': top = '10px'; left = '10px'; break;
            case 'tc': top = '10px'; left = '50%'; transform += ' translateX(-50%)'; break;
            case 'tr': top = '10px'; right = '10px'; break;
            case 'ml': top = '50%'; left = '10px'; transform += ' translateY(-50%)'; break;
            case 'c': top = '50%'; left = '50%'; transform += ' translate(-50%, -50%)'; break;
            case 'mr': top = '50%'; right = '10px'; transform += ' translateY(-50%)'; break;
            case 'bl': bottom = '10px'; left = '10px'; break;
            case 'bc': bottom = '10px'; left = '50%'; transform += ' translateX(-50%)'; break;
            case 'br': bottom = '10px'; right = '10px'; break;
        }

        layer.css({ top: top, bottom: bottom, left: left, right: right, transform: transform });
    }

    // 绑定水印相关控件事件
    $('#ps_watermark_text, #ps_watermark_opacity, #ps_watermark_size, input[name="ps_watermark_type"], input[name="ps_watermark_position"], #ps_watermark_rotation')
        .on('change input', updateWatermarkPreview);

    // 初始运行一次水印预览
    updateWatermarkPreview();

    // ===== DIVI-CLONE 颜色选择器 =====
    // 集中管理所有颜色选择器的拖拽状态
    var activePickerState = null;

    // 统一的 document 级事件（只绑一次）
    function onDocMouseMove(e) {
        if (!activePickerState) return;
        if (activePickerState.isDraggingSat) activePickerState.handleSat(e);
        if (activePickerState.isDraggingHue) activePickerState.handleHue(e);
        if (activePickerState.isDraggingAlpha) activePickerState.handleAlpha(e);
    }

    function onDocMouseUp() {
        if (!activePickerState) return;
        activePickerState.isDraggingSat = false;
        activePickerState.isDraggingHue = false;
        activePickerState.isDraggingAlpha = false;
    }

    $(document).on('mousemove touchmove', onDocMouseMove);
    $(document).on('mouseup touchend', onDocMouseUp);

    // 点击外部自动关闭 picker
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ps-divi-picker-container').length) {
            $('.ps-divi-picker-wrapper').removeClass('active');
        }
    });

    // 初始化每个颜色选择器
    $('.ps-divi-picker').each(function () {
        var $container = $(this);
        var inputId = $container.attr('id').replace('ps-picker-', 'ps_banner_') + '_color';

        // 特殊 ID 映射
        if ($container.attr('id') === 'ps-picker-primary') { inputId = 'ps_primary_color'; }
        else if ($container.attr('id') === 'ps-picker-button-text') { inputId = 'ps_button_text_color'; }
        else if ($container.attr('id') === 'ps-picker-title') { inputId = 'ps_banner_title_color'; }
        else if ($container.attr('id') === 'ps-picker-desc') { inputId = 'ps_banner_desc_color'; }

        var $hiddenInput = $('#' + inputId);

        // 构建 Picker UI
        var html = `
            <div class="ps-divi-picker-container">
                <div class="ps-divi-trigger">
                    <div class="ps-divi-trigger-inner" style="background-color: transparent;"></div>
                </div>
                <div class="ps-divi-picker-wrapper">
                    <div class="ps-divi-palette">
                        <span class="ps-divi-swatch" data-r="0"   data-g="0"   data-b="0"   style="background-color: #000000;"></span>
                        <span class="ps-divi-swatch" data-r="255" data-g="255" data-b="255" style="background-color: #ffffff;"></span>
                        <span class="ps-divi-swatch" data-r="46"  data-g="125" data-b="50"  style="background-color: #2e7d32;"></span>
                        <span class="ps-divi-swatch" data-r="197" data-g="138" data-b="16"  style="background-color: #c58a10;"></span>
                        <span class="ps-divi-swatch" data-r="229" data-g="222" data-b="18"  style="background-color: #e5de12;"></span>
                        <span class="ps-divi-swatch" data-r="107" data-g="209" data-b="63"  style="background-color: #6bd13f;"></span>
                        <span class="ps-divi-swatch" data-r="31"  data-g="121" data-b="200" style="background-color: #1f79c8;"></span>
                        <span class="ps-divi-swatch" data-r="122" data-g="23"  data-b="216" style="background-color: #7a17d8;"></span>
                    </div>
                    <div class="ps-divi-input-area">
                        <input type="text" class="ps-divi-hex-input" value="" placeholder="#RRGGBB">
                    </div>
                    <div class="ps-divi-controls">
                        <div class="ps-divi-saturation">
                            <div class="ps-divi-cursor"></div>
                        </div>
                        <div class="ps-divi-sliders-col">
                            <div class="ps-divi-slider-track ps-divi-hue-track">
                                <div class="ps-divi-slider-handle"></div>
                            </div>
                            <div class="ps-divi-slider-track ps-divi-alpha-track">
                                <div class="ps-divi-alpha-gradient"></div>
                                <div class="ps-divi-slider-handle"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $container.html(html);

        // 每个 picker 实例独立状态
        var h = 0, s = 100, v = 100, a = 1;

        var $wrapper = $container.find('.ps-divi-picker-wrapper');
        var $trigger = $container.find('.ps-divi-trigger');
        var $triggerInner = $container.find('.ps-divi-trigger-inner');
        var $sat = $container.find('.ps-divi-saturation');
        var $cursor = $container.find('.ps-divi-cursor');
        var $hueTrack = $container.find('.ps-divi-hue-track');
        var $hueHandle = $hueTrack.find('.ps-divi-slider-handle');
        var $alphaTrack = $container.find('.ps-divi-alpha-track');
        var $alphaGradient = $container.find('.ps-divi-alpha-gradient');
        var $alphaHandle = $alphaTrack.find('.ps-divi-slider-handle');
        var $input = $container.find('.ps-divi-hex-input');

        // 开关显示/隐藏（含视口溢出检测，防止调色板被截断）
        $trigger.on('click', function (e) {
            e.stopPropagation();
            $('.ps-divi-picker-wrapper').not($wrapper).removeClass('active');
            $wrapper.toggleClass('active');

            // 如果调色板打开，检查它是否会溢出到屏幕左边或右边
            if ($wrapper.hasClass('active')) {
                // 重置为默认居中对齐
                $wrapper.css({ left: '', right: '', transform: '' });

                // 获取调色板弹窗在屏幕上的位置信息
                var wrapperRect = $wrapper[0].getBoundingClientRect();
                var viewportWidth = $(window).width();
                var MARGIN = 8; // 距离屏幕边缘的最小安全距离

                if (wrapperRect.left < MARGIN) {
                    // 向左溢出：把调色板移到触发按钮的左边对齐
                    var overflowLeft = MARGIN - wrapperRect.left;
                    $wrapper.css({
                        left: 'calc(50% + ' + overflowLeft + 'px)',
                        transform: 'translateX(-50%)'
                    });
                } else if (wrapperRect.right > viewportWidth - MARGIN) {
                    // 向右溢出：把调色板靠右对齐
                    var overflowRight = wrapperRect.right - (viewportWidth - MARGIN);
                    $wrapper.css({
                        left: 'calc(50% - ' + overflowRight + 'px)',
                        transform: 'translateX(-50%)'
                    });
                }
            }
        });
        $wrapper.on('click', function (e) { e.stopPropagation(); });

        /**
         * 刷新 Picker 整个 UI（颜色、位置、Hidden input 值）
         */
        function updateUI(skipInputUpdate) {
            var rgb = PS_ColorUtils.hsvToRgb(h, s, v);
            var rgbaStr = 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + a.toFixed(2) + ')';

            $hiddenInput.val(rgbaStr).trigger('change');
            $triggerInner.css('background-color', rgbaStr);

            var baseRgb = PS_ColorUtils.hsvToRgb(h, 100, 100);
            $sat.css('background-color', 'rgb(' + baseRgb.r + ',' + baseRgb.g + ',' + baseRgb.b + ')');
            $cursor.css({ left: s + '%', top: (100 - v) + '%' });
            $hueHandle.css('top', (h * 100) + '%');
            $alphaHandle.css('top', (100 - (a * 100)) + '%');
            $alphaGradient.css('background', 'linear-gradient(to bottom, rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ', 1) 0%, rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ', 0) 100%)');

            if (!skipInputUpdate) {
                $input.val(a >= 1 ? PS_ColorUtils.rgbToHex(rgb.r, rgb.g, rgb.b) : rgbaStr);
            }

            // 实时预览联动
            if (inputId === 'ps_banner_overlay_color') { $('#ps-banner-overlay-layer').css('background-color', rgbaStr); }
            else if (inputId === 'ps_primary_color') { $('.ps-preview-btn').css('background-color', rgbaStr); }
            else if (inputId === 'ps_button_text_color') { $('.ps-preview-btn').css('color', rgbaStr); }
            else if (inputId === 'ps_banner_title_color') { $('#ps-preview-banner-title').css('color', rgbaStr); }
            else if (inputId === 'ps_banner_desc_color') { $('#ps-preview-banner-desc').css('color', rgbaStr); }
        }

        // 读取已保存的初始值（支持 HEX 和 RGBA 两种格式）
        var initVal = $hiddenInput.val();
        if (initVal) {
            if (initVal.indexOf('rgba') !== -1 || initVal.indexOf('rgb') !== -1) {
                var parts = initVal.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
                if (parts) {
                    var r = parseInt(parts[1]), g = parseInt(parts[2]), b = parseInt(parts[3]);
                    a = parts[4] ? parseFloat(parts[4]) : 1;
                    var hsv = PS_ColorUtils.rgbToHsv(r, g, b);
                    h = hsv.h; s = hsv.s; v = hsv.v;
                }
            } else if (initVal.indexOf('#') !== -1) {
                var rgb = PS_ColorUtils.hexToRgb(initVal);
                if (rgb) {
                    var hsv = PS_ColorUtils.rgbToHsv(rgb.r, rgb.g, rgb.b);
                    h = hsv.h; s = hsv.s; v = hsv.v; a = 1;
                }
            }
        }
        updateUI();

        // 本实例的拖拽状态（通过闭包捕获实例变量）
        var pickerState = {
            isDraggingSat: false, isDraggingHue: false, isDraggingAlpha: false,
            handleSat: function (e) {
                var coords = PS_ColorUtils.getEventCoords(e);
                var offset = $sat.offset();
                s = Math.max(0, Math.min(100, ((coords.pageX - offset.left) / $sat.width()) * 100));
                v = Math.max(0, Math.min(100, 100 - ((coords.pageY - offset.top) / $sat.height()) * 100));
                updateUI();
            },
            handleHue: function (e) {
                var coords = PS_ColorUtils.getEventCoords(e);
                h = Math.max(0, Math.min(1, (coords.pageY - $hueTrack.offset().top) / $hueTrack.height()));
                updateUI();
            },
            handleAlpha: function (e) {
                var coords = PS_ColorUtils.getEventCoords(e);
                a = 1 - Math.max(0, Math.min(1, (coords.pageY - $alphaTrack.offset().top) / $alphaTrack.height()));
                updateUI();
            }
        };

        $sat.on('mousedown touchstart', function (e) {
            e.preventDefault(); pickerState.isDraggingSat = true; activePickerState = pickerState; pickerState.handleSat(e);
        });
        $hueTrack.on('mousedown touchstart', function (e) {
            e.preventDefault(); pickerState.isDraggingHue = true; activePickerState = pickerState; pickerState.handleHue(e);
        });
        $alphaTrack.on('mousedown touchstart', function (e) {
            e.preventDefault(); pickerState.isDraggingAlpha = true; activePickerState = pickerState; pickerState.handleAlpha(e);
        });

        // 调色板快速选色
        $container.find('.ps-divi-swatch').on('click', function () {
            var r = $(this).data('r'), g = $(this).data('g'), b = $(this).data('b');
            var hsv = PS_ColorUtils.rgbToHsv(r, g, b);
            h = hsv.h; s = hsv.s; v = hsv.v; a = 1;
            updateUI();
        });

        // 手动输入 HEX/RGBA
        $input.on('keyup change', function () {
            var val = $(this).val();
            var rgb = PS_ColorUtils.hexToRgb(val);
            if (rgb) {
                var hsv = PS_ColorUtils.rgbToHsv(rgb.r, rgb.g, rgb.b);
                h = hsv.h; s = hsv.s; v = hsv.v; a = 1;
                updateUI(true); return;
            }
            if (val.indexOf('rgba') !== -1 || val.indexOf('rgb') !== -1) {
                var parts = val.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
                if (parts) {
                    var r = parseInt(parts[1]), g = parseInt(parts[2]), b = parseInt(parts[3]);
                    a = parts[4] ? parseFloat(parts[4]) : 1;
                    var hsv = PS_ColorUtils.rgbToHsv(r, g, b);
                    h = hsv.h; s = hsv.s; v = hsv.v;
                    updateUI(true);
                }
            }
        });
    }); // end .ps-divi-picker.each
});
