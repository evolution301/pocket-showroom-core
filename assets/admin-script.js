jQuery(document).ready(function ($) {
    var mediaUploader;

    // --- Tab Switching Logic ---
    $('.nav-tab-wrapper a').click(function (e) {
        e.preventDefault();
        // AUDIT3-9: 验证 href 是合法的 ID 选择器
        var href = $(this).attr('href');
        if (!href || href.charAt(0) !== '#') return;
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.ps-tab-content').hide();
        $(href).show();
    });

    // --- Gallery Manager (Existing) ---
    $('#ps-add-images').on('click', function (e) {
        e.preventDefault();
        if (mediaUploader) { mediaUploader.open(); return; }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: ps_admin_vars.select_images,
            button: { text: ps_admin_vars.add_to_gallery },
            multiple: true
        });
        mediaUploader.on('select', function () {
            var selection = mediaUploader.state().get('selection');
            var ids = [];
            selection.map(function (attachment) {
                attachment = attachment.toJSON();
                ids.push(attachment.id);
                // Fix RISK-12: thumbnail 可能不存在，使用 fallback
                var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail)
                    ? attachment.sizes.thumbnail.url
                    : attachment.url; // fallback to full url
                var template = '<div class="ps-gallery-item" data-id="' + attachment.id + '"><img src="' + thumbUrl + '"><span class="remove">×</span></div>';
                $('#ps-add-images').before(template);
            });
            updateGalleryIds();
        });
        mediaUploader.open();
    });

    $(document).on('click', '.ps-gallery-item .remove', function () {
        $(this).parent('.ps-gallery-item').remove();
        updateGalleryIds();
    });

    // Fix RISK-11: Sortable 加异常保护
    if ($.fn.sortable) {
        try {
            $('.ps-gallery-grid').sortable({
                items: '.ps-gallery-item',
                cursor: 'move',
                update: function () { updateGalleryIds(); }
            });
        } catch (e) {
            console.warn('Pocket Showroom: sortable init failed', e);
        }
    }

    function updateGalleryIds() {
        var ids = [];
        $('.ps-gallery-item').each(function () { ids.push($(this).data('id')); });
        $('#_ps_gallery_images').val(ids.join(','));
    }

    // --- Size Variants Manager (Existing) ---
    // Add Size Variant
    $('#ps-add-size').on('click', function (e) {
        e.preventDefault();
        var index = $('#ps-size-variants .ps-size-row').length;
        var html = `
            <div class="ps-size-row" style="display:flex; gap:10px; margin-bottom:10px;">
                <input type="text" class="ps-input" name="_ps_size_variants[${index}][label]" placeholder="${ps_admin_vars.variant_name_placeholder}" style="flex:1;">
                <input type="text" class="ps-input" name="_ps_size_variants[${index}][value]" placeholder="${ps_admin_vars.dimensions_placeholder}" style="flex:2;">
                <span class="dashicons dashicons-move ps-sort-handle" style="cursor:move; color:#ccc; align-self:center;"></span>
                <button type="button" class="ps-remove-btn" style="color:red; background:none; border:none; cursor:pointer;">×</button>
            </div>
        `;
        $('#ps-size-variants').append(html);
        updateSizeVariantIndices(); // Update indices after add
    });

    // Sortable for Size Variants
    if ($.fn.sortable) {
        $('#ps-size-variants').sortable({
            handle: '.ps-sort-handle',
            cursor: 'move',
            update: function () {
                updateSizeVariantIndices();
            }
        });
    }

    function updateSizeVariantIndices() {
        $('#ps-size-variants .ps-size-row').each(function (index) {
            $(this).find('input[name*="[label]"]').attr('name', '_ps_size_variants[' + index + '][label]');
            $(this).find('input[name*="[value]"]').attr('name', '_ps_size_variants[' + index + '][value]');
        });
    }



    // Remove Row (Size or Spec)
    // Remove Row (Size or Spec)
    $(document).on('click', '.ps-remove-btn', function () {
        var $parent = $(this).closest('.ps-spec-row, .ps-size-row');
        var isSizeRow = $parent.hasClass('ps-size-row');
        $parent.remove();
        if (isSizeRow) updateSizeVariantIndices();
    });

    // --- Dynamic Specifications (v6.0) ---
    $('#ps-add-spec').on('click', function (e) {
        e.preventDefault();
        var template = `
            <div class="ps-spec-row">
                <input type="text" name="_ps_dynamic_specs[key][]" class="ps-spec-key" placeholder="${ps_admin_vars.field_name_placeholder}" value="">
                <input type="text" name="_ps_dynamic_specs[val][]" class="ps-spec-val" placeholder="${ps_admin_vars.value_placeholder}" value="">
                <span class="dashicons dashicons-move ps-sort-handle" style="cursor:move; color:#ccc; align-self:center;"></span>
                <button type="button" class="ps-remove-btn">×</button>
            </div>
        `;
        $('#ps-dynamic-specs').append(template);
    });

    // Sortable for Dynamic Specs
    if ($.fn.sortable) {
        $('#ps-dynamic-specs').sortable({
            handle: '.ps-sort-handle',
            cursor: 'move',
            // No index update needed for specs as they use array[] syntax
        });
    }

    // 已在第 71 行统一绑定 .ps-remove-btn，此处不再重复绑定

    // Banner Button Text Live Preview
    $('#ps_banner_button_text').on('input', function () {
        var val = $(this).val();
        $('#ps-preview-banner-btn').text(val ? val : ps_admin_vars.explore_now);
    });


    // ===== ADVANCED DIVI-CLONE COLOR PICKER LOGIC =====
    // Fix BUG-9/10: 将 document 级事件移到 .each() 外部，集中管理

    // 集中管理所有颜色选择器的拖拽状态
    var activePickerState = null; // { handleSat, handleHue, handleAlpha, isDraggingSat, isDraggingHue, isDraggingAlpha }

    // 统一的 document 级事件（只绑定一次）
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

    // Fix IMPROVE-14: 触摸设备坐标提取
    function getEventCoords(e) {
        if (e.type && e.type.indexOf('touch') !== -1) {
            var touch = e.originalEvent ? e.originalEvent.touches[0] || e.originalEvent.changedTouches[0] : e.touches[0] || e.changedTouches[0];
            return { pageX: touch.pageX, pageY: touch.pageY };
        }
        return { pageX: e.pageX, pageY: e.pageY };
    }

    // 只在 document 上绑定一次 (Fix BUG-9)
    $(document).on('mousemove touchmove', onDocMouseMove);
    $(document).on('mouseup touchend', onDocMouseUp);

    // Fix BUG-10: 点击外部关闭 — 只绑定一次
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.ps-divi-picker-container').length) {
            $('.ps-divi-picker-wrapper').removeClass('active');
        }
    });

    // 公共工具函数（提取到 .each 外部避免重复创建）
    function componentToHex(c) {
        var hex = c.toString(16);
        return hex.length == 1 ? "0" + hex : hex;
    }

    function rgbToHex(r, g, b) {
        return "#" + componentToHex(r) + componentToHex(g) + componentToHex(b);
    }

    function hexToRgb(hex) {
        // AUDIT2-7: 支持 3 位简写 (#fff → #ffffff)
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

    $('.ps-divi-picker').each(function () {
        var $container = $(this);
        var inputId = $container.attr('id').replace('ps-picker-', 'ps_banner_') + '_color';
        // Special case for primary
        if ($container.attr('id') === 'ps-picker-primary') {
            inputId = 'ps_primary_color';
        } else if ($container.attr('id') === 'ps-picker-button-text') {
            inputId = 'ps_button_text_color';
        } else if ($container.attr('id') === 'ps-picker-title') {
            inputId = 'ps_banner_title_color';
        } else if ($container.attr('id') === 'ps-picker-desc') {
            inputId = 'ps_banner_desc_color';
        }
        var $hiddenInput = $('#' + inputId);

        // Build UI Structure
        var html = `
            <div class="ps-divi-picker-container">
                <div class="ps-divi-trigger">
                    <div class="ps-divi-trigger-inner" style="background-color: transparent;"></div>
                </div>
                <div class="ps-divi-picker-wrapper">
                    <div class="ps-divi-palette">
                        <span class="ps-divi-swatch" data-r="0" data-g="0" data-b="0" style="background-color: #000000;"></span>
                        <span class="ps-divi-swatch" data-r="255" data-g="255" data-b="255" style="background-color: #ffffff;"></span>
                        <span class="ps-divi-swatch" data-r="46" data-g="125" data-b="50" style="background-color: #2e7d32;"></span>
                        <span class="ps-divi-swatch" data-r="197" data-g="138" data-b="16" style="background-color: #c58a10;"></span>
                        <span class="ps-divi-swatch" data-r="229" data-g="222" data-b="18" style="background-color: #e5de12;"></span>
                        <span class="ps-divi-swatch" data-r="107" data-g="209" data-b="63" style="background-color: #6bd13f;"></span>
                        <span class="ps-divi-swatch" data-r="31" data-g="121" data-b="200" style="background-color: #1f79c8;"></span>
                        <span class="ps-divi-swatch" data-r="122" data-g="23" data-b="216" style="background-color: #7a17d8;"></span>
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

        // State — 每个 picker 实例独立的状态
        var h = 0, s = 100, v = 100, a = 1;

        // Elements
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

        // Toggle Visibility
        $trigger.on('click', function (e) {
            e.stopPropagation();
            // Close others
            $('.ps-divi-picker-wrapper').not($wrapper).removeClass('active');
            $wrapper.toggleClass('active');
        });

        // 阻止 picker 内部点击冒泡（防止被 document click 关闭）
        $wrapper.on('click', function (e) {
            e.stopPropagation();
        });

        function updateUI(skipInputUpdate) {
            var rgb = hsvToRgb(h, s, v);
            var rgbaStr = 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + a.toFixed(2) + ')';

            // Update Hidden Input
            $hiddenInput.val(rgbaStr).trigger('change');

            // Update Trigger
            $triggerInner.css('background-color', rgbaStr);

            // Update Saturation BG
            var baseRgb = hsvToRgb(h, 100, 100);
            $sat.css('background-color', 'rgb(' + baseRgb.r + ',' + baseRgb.g + ',' + baseRgb.b + ')');

            // Update Cursor Pos
            $cursor.css({ left: s + '%', top: (100 - v) + '%' });

            // Update Hue Handle
            $hueHandle.css('top', (h * 100) + '%');

            // Update Alpha Handle & Gradient
            $alphaHandle.css('top', (100 - (a * 100)) + '%');
            $alphaGradient.css('background', 'linear-gradient(to bottom, rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ', 1) 0%, rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ', 0) 100%)');

            // Update Hex/Input
            if (!skipInputUpdate) {
                if (a >= 1) {
                    $input.val(rgbToHex(rgb.r, rgb.g, rgb.b));
                } else {
                    $input.val(rgbaStr);
                }
            }

            // Live Preview
            if (inputId === 'ps_banner_overlay_color') {
                $('#ps-banner-overlay-layer').css('background-color', rgbaStr);
            } else if (inputId === 'ps_primary_color') {
                $('.ps-preview-btn').css('background-color', rgbaStr);
            } else if (inputId === 'ps_button_text_color') {
                $('.ps-preview-btn').css('color', rgbaStr);
            } else if (inputId === 'ps_banner_title_color') {
                $('#ps-preview-banner-title').css('color', rgbaStr);
            } else if (inputId === 'ps_banner_desc_color') {
                $('#ps-preview-banner-desc').css('color', rgbaStr);
            }
        }

        // Fix RISK-13: 初始化支持 HEX 和 RGBA 两种格式
        var initVal = $hiddenInput.val();
        if (initVal) {
            if (initVal.indexOf('rgba') !== -1 || initVal.indexOf('rgb') !== -1) {
                // RGBA 格式
                var parts = initVal.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
                if (parts) {
                    var r = parseInt(parts[1]), g = parseInt(parts[2]), b = parseInt(parts[3]);
                    a = parts[4] ? parseFloat(parts[4]) : 1;
                    var hsv = rgbToHsv(r, g, b);
                    h = hsv.h; s = hsv.s; v = hsv.v;
                }
            } else if (initVal.indexOf('#') !== -1) {
                // HEX 格式 (Fix RISK-13)
                var rgb = hexToRgb(initVal);
                if (rgb) {
                    var hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
                    h = hsv.h; s = hsv.s; v = hsv.v;
                    a = 1;
                }
            }
        }
        updateUI();

        // --- Interactions ---
        // 本实例的拖拽处理器（通过闭包捕获本实例的 h/s/v/a 和 DOM 元素）
        var pickerState = {
            isDraggingSat: false,
            isDraggingHue: false,
            isDraggingAlpha: false,
            handleSat: function (e) {
                var coords = getEventCoords(e);
                var offset = $sat.offset();
                var x = coords.pageX - offset.left;
                var y = coords.pageY - offset.top;
                var w = $sat.width();
                var hH = $sat.height();
                s = Math.max(0, Math.min(100, (x / w) * 100));
                v = Math.max(0, Math.min(100, 100 - (y / hH) * 100));
                updateUI();
            },
            handleHue: function (e) {
                var coords = getEventCoords(e);
                var offset = $hueTrack.offset();
                var y = coords.pageY - offset.top;
                var hH = $hueTrack.height();
                h = Math.max(0, Math.min(1, y / hH));
                updateUI();
            },
            handleAlpha: function (e) {
                var coords = getEventCoords(e);
                var offset = $alphaTrack.offset();
                var y = coords.pageY - offset.top;
                var hH = $alphaTrack.height();
                a = 1 - Math.max(0, Math.min(1, y / hH));
                updateUI();
            }
        };

        // Saturation Area — Fix IMPROVE-14: 添加 touchstart
        $sat.on('mousedown touchstart', function (e) {
            e.preventDefault();
            pickerState.isDraggingSat = true;
            activePickerState = pickerState;
            pickerState.handleSat(e);
        });

        // Hue Slider
        $hueTrack.on('mousedown touchstart', function (e) {
            e.preventDefault();
            pickerState.isDraggingHue = true;
            activePickerState = pickerState;
            pickerState.handleHue(e);
        });

        // Alpha Slider
        $alphaTrack.on('mousedown touchstart', function (e) {
            e.preventDefault();
            pickerState.isDraggingAlpha = true;
            activePickerState = pickerState;
            pickerState.handleAlpha(e);
        });

        // Palette Click
        $container.find('.ps-divi-swatch').on('click', function () {
            var r = $(this).data('r');
            var g = $(this).data('g');
            var b = $(this).data('b');
            var hsv = rgbToHsv(r, g, b);
            h = hsv.h; s = hsv.s; v = hsv.v;
            a = 1;
            updateUI();
        });

        // Input Change Listener
        $input.on('keyup change', function () {
            var val = $(this).val();
            // Try Hex
            var rgb = hexToRgb(val);
            if (rgb) {
                var hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
                h = hsv.h; s = hsv.s; v = hsv.v;
                a = 1;
                updateUI(true);
                return;
            }
            // Try RGBA
            if (val.indexOf('rgba') !== -1 || val.indexOf('rgb') !== -1) {
                var parts = val.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
                if (parts) {
                    var r = parseInt(parts[1]), g = parseInt(parts[2]), b = parseInt(parts[3]);
                    a = parts[4] ? parseFloat(parts[4]) : 1;
                    var hsv = rgbToHsv(r, g, b);
                    h = hsv.h; s = hsv.s; v = hsv.v;
                    updateUI(true);
                }
            }
        });
    });

    // --- Live Text Preview ---
    $('#ps_banner_title').on('input', function () {
        var val = $(this).val();
        $('#ps-preview-banner-title').text(val ? val : ps_admin_vars.banner_title_fallback);
    });

    $('#ps_banner_desc').on('input', function () {
        var val = $(this).val();
        // AUDIT3-10: 使用 .text() 而非 .html() 防止 XSS
        $('#ps-preview-banner-desc').text(val ? val : ps_admin_vars.banner_desc_fallback);
    });

    // Banner Image Preview Update
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

            // Update Live Preview Background
            $('#ps-live-preview-container').css('background-image', 'url(' + attachment.url + ')');
        });
        bannerFrame.open();
    });

    // Remove Banner Image
    $('#ps_remove_banner_image').on('click', function (e) {
        e.preventDefault();
        $('#ps_banner_image_id').val('');
        $('.ps-image-preview-wrapper').html('<div class="ps-no-image">' + ps_admin_vars.no_image + '</div>');
        $('#ps-live-preview-container').css('background-image', 'none');
    });

    // --- Watermark Logic ---
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
            updatePreview();
        });
        wmUploader.open();
    });

    $('#ps-remove-watermark').on('click', function () {
        $('#ps_watermark_image_id').val('');
        $('#ps-watermark-image-preview').empty();
        $(this).hide();
        updatePreview();
    });

    // Real-time Preview Updates
    function updatePreview() {
        var type = $('input[name="ps_watermark_type"]:checked').val();
        var text = $('#ps_watermark_text').val();
        var opacity = $('#ps_watermark_opacity').val();
        var size = $('#ps_watermark_size').val();
        var position = $('input[name="ps_watermark_position"]:checked').val();
        var rotation = $('#ps_watermark_rotation').val();

        // Update UI Labels
        $('#ps-opacity-val').text(opacity);
        $('#ps-size-val').text(size);

        // Toggle Input Fields
        if (type === 'text') {
            $('.ps-type-text').show();
            $('.ps-type-image').hide();

            // Render Text
            $('#ps-watermark-layer .ps-wm-text').text(text).show();
            $('#ps-watermark-layer .ps-wm-image').hide();

            // Font Size (Size is percentage of container width roughly)
            var fontSize = Math.max(12, (size * 5)); // simplified scale
            $('#ps-watermark-layer .ps-wm-text').css('font-size', fontSize + 'px');

        } else {
            $('.ps-type-text').hide();
            $('.ps-type-image').show();

            // Render Image
            var imgUrl = $('#ps-watermark-image-preview img').attr('src');
            if (imgUrl) {
                $('#ps-watermark-layer .ps-wm-image').attr('src', imgUrl).show();
                $('#ps-watermark-layer .ps-wm-text').hide();

                // Image Size
                $('#ps-watermark-layer .ps-wm-image').css('width', (size * 3) + 'px'); // simplified scale
            } else {
                $('#ps-watermark-layer .ps-wm-text').text(ps_admin_vars.no_image_parens).show();
            }
        }

        // Opacity
        $('#ps-watermark-layer').css('opacity', opacity / 100);

        // Position Logic (AUDIT3-11: 移除上方冗余的 transform 设置，统一在此处处理)
        var layer = $('#ps-watermark-layer');
        var top = 'auto', bottom = 'auto', left = 'auto', right = 'auto', transform = 'rotate(' + rotation + 'deg)';

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

        layer.css({
            top: top, bottom: bottom, left: left, right: right, transform: transform
        });
    }

    // Bind Events
    $('#ps_watermark_text, #ps_watermark_opacity, #ps_watermark_size, input[name="ps_watermark_type"], input[name="ps_watermark_position"], #ps_watermark_rotation').on('change input', updatePreview);

    // Initial Run
    updatePreview();

});
