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

    // Category Filter (Client Side) & Scroll to Top
    $('.ps-filter-btn').on('click', function () {
        var cat = $(this).data('cat');

        // Sync active states
        $('.ps-filter-btn').removeClass('active');
        $(this).addClass('active');

        // Smooth scroll back to the top of the filters logic (Fix #13 & #14)
        var $filterBar = $('.ps-gallery-filters');
        if ($filterBar.length && window.innerWidth >= 768) {
            // Target slightly above the filter bar to account for header styling delays 
            // and the new 20px top offset (e.g. 70px buffer)
            var filterTop = $filterBar.offset().top - 70; 
            // Only scroll if we are already scrolled down past the target
            if ($(window).scrollTop() > filterTop) {
                // Smoothly animate the scrollbar
                $('html, body').animate({ scrollTop: filterTop }, 400, 'swing');
            }
        }

        // Clear search
        $('#ps-search').val('');

        var $grid = $('.ps-gallery-grid');
        var $wrap = $('#ps-load-more-wrap');
        var perPage = $wrap.length ? parseInt($wrap.data('per-page'), 10) || 12 : 12;
        var loadMoreText = psI18n('load_more', 'Load More');

        // Show Loading State
        $grid.html('<div class="ps-loader" style="grid-column: 1 / -1; margin: 40px auto; text-align: center;">' + psI18n('loading', 'Loading...') + '</div>');
        if ($wrap.length) {
            $wrap.hide(); // Hide load more while loading new category
        }

        $.ajax({
            url: ps_ajax.url,
            type: 'POST',
            timeout: 15000,
            data: {
                action: 'ps_load_more',
                page: 1,
                per_page: perPage,
                category: cat,
                nonce: ps_ajax.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $grid.html(response.data.html);
                    if ($wrap.length) {
                        var maxPages = parseInt(response.data.max_pages, 10) || 1;
                        $wrap.data('page', 1);
                        $wrap.data('max', maxPages);
                        if (maxPages > 1) {
                            $wrap.show();
                            $wrap.find('.ps-load-more-btn').removeClass('loading').text(loadMoreText).show();
                        } else {
                            $wrap.hide();
                        }
                    }
                } else {
                    $grid.html('<p style="grid-column: 1 / -1; text-align: center;">' + psI18n('no_results', 'No items found.') + '</p>');
                }
            },
            error: function() {
                $grid.html('<p style="grid-column: 1 / -1; text-align: center;">' + psI18n('error', 'Error loading products.') + '</p>');
            }
        });
    });

    // ===== HIDE THEME HEADER ON SCROLL (Fix #12) =====
    // Goal: Maximize screen space for the gallery by hiding WP headers when scrolled down.
    var lastScrollTop = $(window).scrollTop();
    var isHeaderHidden = false;
    var ticking = false;

    $(window).on('scroll', function () {
        lastScrollTop = $(window).scrollTop();
        if (!ticking) {
            window.requestAnimationFrame(function () {
                // Fix #15: Disable header hiding on mobile
                if (window.innerWidth < 768) {
                    if (isHeaderHidden) {
                        $('body').removeClass('ps-hide-theme-header');
                        isHeaderHidden = false;
                    }
                    ticking = false;
                    return;
                }

                var $filterBar = $('.ps-gallery-filters');
                if ($filterBar.length) {
                    var filterTop = $filterBar.offset().top;
                    var triggerPoint = filterTop - 50; // Start hiding just before filters hit the top

                    if (lastScrollTop > triggerPoint) {
                        if (!isHeaderHidden) {
                            $('body').addClass('ps-hide-theme-header');
                            isHeaderHidden = true;
                        }
                    } else {
                        if (isHeaderHidden) {
                            $('body').removeClass('ps-hide-theme-header');
                            isHeaderHidden = false;
                        }
                    }
                }
                ticking = false;
            });
            ticking = true;
        }
    });

    // ===== CATEGORY TOGGLE (Removed in favor of horizontal scroll with mask) =====

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
                category: $('.ps-filter-btn.active').data('cat') || 'all',
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
 * Global function: Share to WhatsApp (called from modal)
 */
function psShareWhatsApp(title, url) {
    var text = title + '\n' + url;
    var waUrl = 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
    window.open(waUrl, '_blank');
}

/**
 * Global function: Open WeChat QR Modal (called from modal and share sheet)
 * Fix IMPROVE-7: 不再使用 replaceWith 破坏 DOM，改用 hide + 错误提示
 */
function psOpenWeChatQR(url) {
    // Use QR Server API to generate QR code image
    var qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(url);

    var $img = jQuery('#ps-qr-image');

    // AUDIT2-4: 每次从 DOM 重新查询错误提示元素，避免闭包缓存问题
    var $existingError = jQuery('#ps-qr-error');

    // 重置状态：显示图片，隐藏错误
    $img.show();
    if ($existingError.length) {
        $existingError.hide();
    }

    // Fix IMPROVE-7: 使用 hide + 错误提示而非 replaceWith
    $img.off('error').on('error', function () {
        jQuery(this).hide();
        // AUDIT2-4: 在 error 回调内重新查询 DOM
        var $errEl = jQuery('#ps-qr-error');
        if (!$errEl.length) {
            // 首次创建错误提示
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
    // AUDIT2-3: 使用 CSS class 管理 body overflow
    jQuery('body').addClass('ps-modal-open');
}
