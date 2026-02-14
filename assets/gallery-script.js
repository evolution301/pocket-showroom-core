jQuery(document).ready(function ($) {

    // ===== 全局安全检查 (Fix RISK-3) =====
    if (typeof ps_ajax === 'undefined') {
        console.warn('Pocket Showroom: ps_ajax not defined, gallery script aborted.');
        return;
    }

    // ===== UTILITIES =====

    /**
     * i18n helper: read from ps_ajax.i18n with fallback
     */
    function psI18n(key, fallback) {
        return (ps_ajax.i18n && ps_ajax.i18n[key]) ? ps_ajax.i18n[key] : fallback;
    }

    /**
     * Debounce: delay function execution until pause in calls
     */
    function debounce(fn, delay) {
        var timer;
        return function () {
            var context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    /**
     * 统一关闭产品模态框 (Fix BUG-1)
     * 所有关闭模态框的操作都通过此函数，确保 body class 一定被移除
     */
    function closeProductModal() {
        $('#ps-modal').fadeOut(200);
        $('body').removeClass('ps-modal-open');
    }

    // ===== PRODUCT MODAL =====

    // Open Modal
    $(document).on('click', '.ps-card', function (e) {
        e.preventDefault();
        var postId = $(this).data('id');

        $('#ps-modal').fadeIn(200);
        $('body').addClass('ps-modal-open');
        $('#ps-modal-body').html('<div class="ps-loader">' + psI18n('modal_loading', 'Loading...') + '</div>');

        $.ajax({
            url: ps_ajax.url,
            type: 'POST',
            timeout: 15000, // Fix RISK-4: 15秒超时
            data: {
                action: 'ps_get_product_modal',
                post_id: postId,
                nonce: ps_ajax.nonce
            },
            success: function (response) {
                // PHP handler outputs raw HTML via wp_die(), not JSON
                if (response && response.trim().length > 0) {
                    $('#ps-modal-body').html(response);
                } else {
                    $('#ps-modal-body').html('<p>' + psI18n('modal_error', 'Error loading details.') + '</p>');
                }
            },
            error: function (xhr, status) {
                var msg = (status === 'timeout')
                    ? psI18n('timeout_error', 'Request timed out, please try again.')
                    : psI18n('network_error', 'Network error, please try again.');
                $('#ps-modal-body').html('<p>' + msg + '</p>');
            }
        });
    });

    // Close Modal — 统一使用 closeProductModal (Fix BUG-1)
    // AUDIT2-6: 使用事件委托，因为 .ps-close 是 AJAX 动态加载到 modal 里的
    $(document).on('click', '.ps-close', function () {
        closeProductModal();
    });

    $(window).on('click', function (event) {
        if (event.target.id === 'ps-modal') { // AUDIT3-5: 严格等于
            closeProductModal();
        }
    });

    // ===== SEARCH & FILTER =====

    // Simple Search (Client Side) — debounced (Fix F1)
    $('#ps-search').on('keyup', debounce(function () {
        var value = $(this).val().toLowerCase();
        $(".ps-gallery-grid .ps-card").each(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
        if (value.length > 0) {
            $('.ps-filter-btn').removeClass('active');
        }
    }, 300));

    // Category Filter (Client Side)
    $('.ps-filter-btn').on('click', function () {
        var cat = $(this).data('cat');
        $('.ps-filter-btn').removeClass('active');
        $(this).addClass('active');

        // Clear search
        $('#ps-search').val('');

        if (cat === 'all') {
            $('.ps-card').show();
        } else {
            $('.ps-card').hide();
            // Support multi-category: data-cat contains space-separated slugs
            $('.ps-card').each(function () {
                var cats = ($(this).attr('data-cat') || '').split(' ');
                if (cats.indexOf(cat) !== -1) {
                    $(this).show();
                }
            });
        }
    });

    // ===== LOAD MORE (Fix #10 + Fix #20 i18n) =====
    var loadMoreText = psI18n('load_more', 'Load More');
    var loadingText = psI18n('loading', 'Loading...');

    $('#ps-load-more-btn').on('click', function () {
        var $wrap = $('#ps-load-more-wrap');
        var $btn = $(this);
        var currentPage = parseInt($wrap.data('page'), 10) || 1;  // AUDIT3-6: NaN 保护
        var maxPages = parseInt($wrap.data('max'), 10) || 1;       // AUDIT3-6: NaN 保护
        var perPage = parseInt($wrap.data('per-page'), 10) || 12;  // AUDIT3-6: NaN 保护
        var nextPage = currentPage + 1;

        // 防止重复点击
        if ($btn.hasClass('loading')) return;
        $btn.addClass('loading').text(loadingText);

        $.ajax({
            url: ps_ajax.url,
            type: 'POST',
            timeout: 15000, // Fix RISK-5: 15秒超时
            data: {
                action: 'ps_load_more',
                page: nextPage,
                per_page: perPage,
                nonce: ps_ajax.nonce
            },
            success: function (response) {
                if (response.success && response.data.html) {
                    // 将新卡片追加到网格中
                    $('.ps-gallery-grid').append(response.data.html);
                    $wrap.data('page', nextPage);

                    // 判断是否已到最后一页
                    if (nextPage >= maxPages) {
                        $wrap.fadeOut(300);
                    } else {
                        $btn.removeClass('loading').text(loadMoreText);
                    }
                } else {
                    $wrap.fadeOut(300); // 没有更多内容
                }
            },
            error: function () {
                $btn.removeClass('loading').text(loadMoreText);
            }
        });
    });

    // ===== SHARE FUNCTIONALITY =====

    // Current share data (stored when share is triggered)
    var currentShareData = { title: '', desc: '', url: '', img: '' };

    /**
     * Open Share Sheet with provided data
     * Fix IMPROVE-8: 使用 CSS class 控制布局隐藏，而非 JS DOM 操作
     */
    function openShareSheet(title, desc, url, img) {
        currentShareData = { title: title, desc: desc, url: url, img: img };

        // Populate preview
        $('#ps-share-preview-title').text(title || psI18n('untitled', 'Untitled'));
        $('#ps-share-preview-desc').text(desc || url);
        if (img) {
            $('#ps-share-preview-img').attr('src', img).show();
        } else {
            $('#ps-share-preview-img').hide();
        }

        // 使用 CSS class 隐藏页面布局元素 (Fix IMPROVE-8)
        $('body').addClass('ps-share-open');

        // Show overlay
        $('#ps-share-sheet-overlay').addClass('active');
    }

    /**
     * Close Share Sheet
     */
    function closeShareSheet() {
        $('#ps-share-sheet-overlay').removeClass('active');

        // 移除 CSS class 恢复页面布局 (Fix IMPROVE-8)
        $('body').removeClass('ps-share-open');
    }

    /**
     * Show toast notification
     */
    function showToast(message) {
        var $toast = $('#ps-toast');
        $toast.text(message).addClass('show');
        setTimeout(function () {
            $toast.removeClass('show');
        }, 2000);
    }

    // -- Share Button on Product Cards --
    $(document).on('click', '.ps-share-btn', function (e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent card click (modal open)

        var title = $(this).data('share-title');
        var desc = $(this).data('share-desc');
        var url = $(this).data('share-url');
        var img = $(this).data('share-img');

        openShareSheet(title, desc, url, img);
    });

    // -- Share Button on Banner --
    $(document).on('click', '.ps-banner-share-btn', function (e) {
        e.preventDefault();

        var title = $(this).data('share-title');
        var desc = $(this).data('share-desc');
        var url = $(this).data('share-url');
        var img = $(this).data('share-img');

        openShareSheet(title, desc, url, img);
    });

    // -- Close Share Sheet --
    $('#ps-share-sheet-close').on('click', closeShareSheet);
    $(document).on('click', '#ps-share-sheet-overlay', function (e) {
        if (e.target === this) closeShareSheet();
    });

    // -- WhatsApp Share --
    $('#ps-share-whatsapp').on('click', function () {
        var text = currentShareData.title + '\n' + currentShareData.url;
        if (currentShareData.desc) {
            text = currentShareData.title + '\n' + currentShareData.desc + '\n' + currentShareData.url;
        }
        var waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
        window.open(waUrl, '_blank');
        closeShareSheet();
    });

    // -- WeChat Share (show QR code) --
    $('#ps-share-wechat').on('click', function () {
        closeShareSheet();
        psOpenWeChatQR(currentShareData.url);
    });

    // -- Copy Link --
    $('#ps-share-copy').on('click', function () {
        psCopyToClipboard(currentShareData.url);
        closeShareSheet();
        showToast(psI18n('link_copied', 'Link copied!'));
    });

    // -- QR Modal Close -- (AUDIT2-3: 改用 CSS class 管理 overflow)
    $('#ps-qr-close').on('click', function () {
        $('#ps-qr-overlay').removeClass('active');
        $('body').removeClass('ps-modal-open');
    });
    $(document).on('click', '#ps-qr-overlay', function (e) {
        if (e.target === this) {
            $(this).removeClass('active');
            $('body').removeClass('ps-modal-open');
        }
    });

    // -- QR Copy Button --
    $('#ps-qr-copy-btn').on('click', function () {
        var url = $('#ps-qr-url-input').val();
        psCopyToClipboard(url);
        showToast(psI18n('link_copied', 'Link copied!'));
    });

    // ===== KEYBOARD ACCESSIBILITY (Fix F5 + Fix BUG-1) =====
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            // Close product modal — 统一使用 closeProductModal
            if ($('#ps-modal').is(':visible')) {
                closeProductModal();
                return;
            }
            // Close QR overlay (AUDIT2-3)
            if ($('#ps-qr-overlay').hasClass('active')) {
                $('#ps-qr-overlay').removeClass('active');
                $('body').removeClass('ps-modal-open');
                return;
            }
            // Close share sheet
            if ($('#ps-share-sheet-overlay').hasClass('active')) {
                closeShareSheet();
                return;
            }
        }
    });

    /**
     * Copy text to clipboard (with fallback) — Fix F3
     */
    function psCopyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () {
                // Permission denied → fallback to execCommand
                psFallbackCopy(text);
            });
        } else {
            psFallbackCopy(text);
        }
    }

    function psFallbackCopy(text) {
        // AUDIT3-7: 使用 textarea 而非 input，iOS Safari 对 input.select() 支持不佳
        var $temp = $('<textarea>');
        $temp.css({ position: 'fixed', opacity: 0, left: '-9999px' });
        $('body').append($temp);
        $temp.val(text);
        $temp[0].select();
        try { document.execCommand('copy'); } catch (e) { /* noop */ }
        $temp.remove();
    }

});

/**
 * Pocket Showroom Gallery Namespace
 * 将全局函数封装到命名空间中，避免全局污染
 * 
 * Fix M-2: 使用命名空间封装全局函数
 */
window.PS_Gallery = {
    /**
     * Share to WhatsApp
     * @param {string} title - Product title
     * @param {string} url - Share URL
     */
    shareWhatsApp: function (title, url) {
        var text = title + '\n' + url;
        var waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
        window.open(waUrl, '_blank');
    },

    /**
     * Open WeChat QR Modal
     * @param {string} url - URL to encode in QR code
     */
    openWeChatQR: function (url) {
        var qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);

        var $img = jQuery('#ps-qr-image');
        var $existingError = jQuery('#ps-qr-error');

        // 重置状态：显示图片，隐藏错误
        $img.show();
        if ($existingError.length) {
            $existingError.hide();
        }

        $img.off('error').on('error', function () {
            jQuery(this).hide();
            var $errEl = jQuery('#ps-qr-error');
            if (!$errEl.length) {
                var errText = (typeof ps_ajax !== 'undefined' && ps_ajax.i18n && ps_ajax.i18n.qr_error)
                    ? ps_ajax.i18n.qr_error
                    : 'QR code failed to load. Please copy the link below.';
                jQuery('<p id="ps-qr-error" style="color:#999; font-size:13px; padding:20px;">' + errText + '</p>')
                    .insertAfter(jQuery(this));
            } else {
                $errEl.show();
            }
        });
        $img.attr('src', qrApiUrl);
        jQuery('#ps-qr-url-input').val(url);
        jQuery('#ps-qr-overlay').addClass('active');
        jQuery('body').addClass('ps-modal-open');
    },

    /**
     * Copy text to clipboard
     * @param {string} text - Text to copy
     */
    copyToClipboard: function (text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () {
                window.PS_Gallery._fallbackCopy(text);
            });
        } else {
            window.PS_Gallery._fallbackCopy(text);
        }
    },

    /**
     * Fallback copy method for older browsers
     * @private
     */
    _fallbackCopy: function (text) {
        var $temp = jQuery('<textarea>');
        $temp.css({ position: 'fixed', opacity: 0, left: '-9999px' });
        jQuery('body').append($temp);
        $temp.val(text);
        $temp[0].select();
        try { document.execCommand('copy'); } catch (e) { /* noop */ }
        $temp.remove();
    }
};

/**
 * 向后兼容：保留旧的全局函数名（已废弃，建议使用 PS_Gallery.xxx）
 * @deprecated Use window.PS_Gallery.shareWhatsApp instead
 */
function psShareWhatsApp(title, url) {
    window.PS_Gallery.shareWhatsApp(title, url);
}

/**
 * @deprecated Use window.PS_Gallery.openWeChatQR instead
 */
function psOpenWeChatQR(url) {
    window.PS_Gallery.openWeChatQR(url);
}

/**
 * @deprecated Use window.PS_Gallery.copyToClipboard instead
 */
function psCopyToClipboard(text) {
    window.PS_Gallery.copyToClipboard(text);
}
