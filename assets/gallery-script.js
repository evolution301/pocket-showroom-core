jQuery(document).ready(function ($) {

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

    // Open Modal
    $(document).on('click', '.ps-card', function (e) {
        e.preventDefault();
        var postId = $(this).data('id');

        $('#ps-modal').fadeIn(200);
        $('#ps-modal-body').html('<div class="ps-loader">' + psI18n('modal_loading', 'Loading...') + '</div>');

        $.ajax({
            url: ps_ajax.url,
            type: 'POST',
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
            error: function () {
                $('#ps-modal-body').html('<p>' + psI18n('network_error', 'Network error, please try again.') + '</p>');
            }
        });
    });

    // Close Modal
    $('.ps-close').on('click', function () {
        $('#ps-modal').fadeOut(200);
    });

    $(window).on('click', function (event) {
        if (event.target.id == 'ps-modal') {
            $('#ps-modal').fadeOut(200);
        }
    });

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
        var currentPage = parseInt($wrap.data('page'), 10);
        var maxPages = parseInt($wrap.data('max'), 10);
        var perPage = parseInt($wrap.data('per-page'), 10);
        var nextPage = currentPage + 1;

        // 防止重复点击
        if ($btn.hasClass('loading')) return;
        $btn.addClass('loading').text(loadingText);

        $.ajax({
            url: ps_ajax.url,
            type: 'POST',
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
     * Hide header, footer, sidebar elements
     */
    function psHideLayoutElements() {
        $('header, footer, .site-header, .site-footer, #masthead, #colophon, .sidebar, #secondary, .widget-area, .elementor-location-header, .elementor-location-footer, nav.main-navigation, .top-bar, .site-info, .footer-widgets').each(function () {
            $(this).attr('data-ps-was-visible', $(this).css('display'));
            $(this).hide();
        });
    }

    /**
     * Restore header, footer, sidebar elements
     */
    function psShowLayoutElements() {
        $('[data-ps-was-visible]').each(function () {
            var prev = $(this).attr('data-ps-was-visible');
            $(this).css('display', prev || '');
            $(this).removeAttr('data-ps-was-visible');
        });
    }

    /**
     * Open Share Sheet with provided data
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

        // Hide header & footer
        psHideLayoutElements();

        // Show overlay
        $('#ps-share-sheet-overlay').addClass('active');
        $('body').css('overflow', 'hidden');
    }

    /**
     * Close Share Sheet
     */
    function closeShareSheet() {
        $('#ps-share-sheet-overlay').removeClass('active');
        $('body').css('overflow', '');

        // Restore header & footer
        psShowLayoutElements();
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

    // -- QR Modal Close --
    $('#ps-qr-close').on('click', function () {
        $('#ps-qr-overlay').removeClass('active');
        $('body').css('overflow', '');
    });
    $(document).on('click', '#ps-qr-overlay', function (e) {
        if (e.target === this) {
            $(this).removeClass('active');
            $('body').css('overflow', '');
        }
    });

    // -- QR Copy Button --
    $('#ps-qr-copy-btn').on('click', function () {
        var url = $('#ps-qr-url-input').val();
        psCopyToClipboard(url);
        showToast(psI18n('link_copied', 'Link copied!'));
    });

    // ===== KEYBOARD ACCESSIBILITY (Fix F5) =====
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            // Close product modal
            if ($('#ps-modal').is(':visible')) {
                $('#ps-modal').fadeOut(200);
                return;
            }
            // Close QR overlay
            if ($('#ps-qr-overlay').hasClass('active')) {
                $('#ps-qr-overlay').removeClass('active');
                $('body').css('overflow', '');
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
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(text).select();
        try { document.execCommand('copy'); } catch (e) { /* noop */ }
        $temp.remove();
    }

});

/**
 * Global function: Share to WhatsApp (called from modal)
 */
function psShareWhatsApp(title, url) {
    var text = title + '\n' + url;
    var waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(waUrl, '_blank');
}

/**
 * Global function: Open WeChat QR Modal (called from modal and share sheet)
 */
function psOpenWeChatQR(url) {
    // Use QR Server API to generate QR code image
    var qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);

    var $img = jQuery('#ps-qr-image');
    // Fix F4: handle QR API failure
    $img.off('error').on('error', function () {
        jQuery(this).replaceWith(
            '<p style="color:#999; font-size:13px; padding:20px;">' +
            ((typeof ps_ajax !== 'undefined' && ps_ajax.i18n && ps_ajax.i18n.qr_error)
                ? ps_ajax.i18n.qr_error
                : 'QR code failed to load. Please copy the link below.') +
            '</p>'
        );
    });
    $img.attr('src', qrApiUrl);
    jQuery('#ps-qr-url-input').val(url);
    jQuery('#ps-qr-overlay').addClass('active');
    jQuery('body').css('overflow', 'hidden');
}
