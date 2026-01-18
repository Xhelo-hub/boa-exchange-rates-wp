/**
 * BoA Exchange Rates - Admin JavaScript
 */

(function($) {
    'use strict';

    // DOM ready
    $(document).ready(function() {
        initRefreshButton();
        initCurrencySelectors();
        initCopyButtons();
        initIconSettings();
        initPreviewUpdate();
    });

    /**
     * Initialize refresh button
     */
    function initRefreshButton() {
        var $btn = $('#boa-refresh-btn');
        var $status = $('#boa-refresh-status');

        $btn.on('click', function(e) {
            e.preventDefault();

            if ($btn.hasClass('loading')) {
                return;
            }

            // Set loading state
            $btn.addClass('loading').prop('disabled', true);
            $status.removeClass('success error').text(boaRatesAdmin.strings.refreshing);

            // Make AJAX request
            $.ajax({
                url: boaRatesAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boa_refresh_rates',
                    nonce: boaRatesAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(boaRatesAdmin.strings.refreshed);

                        // Update displayed values
                        if (response.data.boa_date && response.data.boa_time) {
                            $('#boa-last-update').text(response.data.boa_date + ' ' + response.data.boa_time);
                        }
                        if (response.data.fetched_at) {
                            $('#boa-last-fetched').text(response.data.fetched_at);
                        }

                        // Refresh preview
                        refreshPreview();
                    } else {
                        $status.addClass('error').text(response.data.message || boaRatesAdmin.strings.error);
                    }
                },
                error: function() {
                    $status.addClass('error').text(boaRatesAdmin.strings.error);
                },
                complete: function() {
                    $btn.removeClass('loading').prop('disabled', false);

                    // Clear status after 5 seconds
                    setTimeout(function() {
                        $status.text('');
                    }, 5000);
                }
            });
        });
    }

    /**
     * Initialize currency selector buttons
     */
    function initCurrencySelectors() {
        var $checkboxes = $('.boa-currency-checkbox input[type="checkbox"]');

        // Select All
        $('#boa-select-all').on('click', function(e) {
            e.preventDefault();
            $checkboxes.prop('checked', true);
        });

        // Deselect All
        $('#boa-select-none').on('click', function(e) {
            e.preventDefault();
            $checkboxes.prop('checked', false);
        });

        // Select Popular
        $('#boa-select-popular').on('click', function(e) {
            e.preventDefault();
            $checkboxes.prop('checked', false);
            $checkboxes.filter('[value="USD"], [value="EUR"], [value="GBP"], [value="CHF"]').prop('checked', true);
        });
    }

    /**
     * Initialize copy buttons
     */
    function initCopyButtons() {
        $('.boa-copy-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var textToCopy = $btn.data('copy');

            // Copy to clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    showCopySuccess($btn);
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(textToCopy).select();
                document.execCommand('copy');
                $temp.remove();
                showCopySuccess($btn);
            }
        });
    }

    /**
     * Show copy success feedback
     */
    function showCopySuccess($btn) {
        var originalText = $btn.text();
        $btn.addClass('copied').text('Copied!');

        setTimeout(function() {
            $btn.removeClass('copied').text(originalText);
        }, 2000);
    }

    /**
     * Initialize icon settings controls
     */
    function initIconSettings() {
        var $colorPicker = $('#boa-icon-color');
        var $colorText = $('#boa-icon-color-text');
        var $sizeSlider = $('#boa-icon-size');
        var $sizeValue = $('#boa-icon-size-value');
        var $styleSelect = $('#boa-icon-style');
        var $preview = $('#boa-icon-preview');

        // Sync color picker and text input
        $colorPicker.on('input change', function() {
            var color = $(this).val();
            $colorText.val(color);
            updateIconPreview();
        });

        $colorText.on('input change', function() {
            var color = $(this).val();
            if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                $colorPicker.val(color);
                updateIconPreview();
            }
        });

        // Size slider
        $sizeSlider.on('input change', function() {
            var size = $(this).val();
            $sizeValue.text(size + 'px');
            updateIconPreview();
        });

        // Style select
        $styleSelect.on('change', function() {
            updateIconPreview();
        });

        /**
         * Update the icon preview
         */
        function updateIconPreview() {
            var color = $colorPicker.val();
            var size = $sizeSlider.val();
            var style = $styleSelect.val();

            // Update preview icons (iconify-icon web components)
            $preview.find('iconify-icon').each(function() {
                var $icon = $(this);
                var currentIcon = $icon.attr('icon');
                var newIcon = transformIcon(currentIcon, style);

                $icon.attr('icon', newIcon);
                $icon.attr('width', size);
                $icon.attr('height', size);
                $icon.css('color', style === 'mono' ? color : '');
            });

            // Update currency grid icons
            $('.boa-currency-icon iconify-icon').each(function() {
                var $icon = $(this);
                $icon.attr('width', size);
                $icon.attr('height', size);
                $icon.css('color', style === 'mono' ? color : '');
            });
        }

        /**
         * Transform icon based on style
         */
        function transformIcon(icon, style) {
            var parts = icon.split(':');
            var iconSet = parts[0];
            var iconName = parts[1] || '';

            // Extract country code
            var countryCode = iconName.replace('-4x3', '');

            switch (style) {
                case 'flag':
                    return 'flag:' + countryCode + '-4x3';
                case 'mono':
                    return 'carbon:location-' + countryCode;
                case 'circle-flags':
                default:
                    return 'circle-flags:' + countryCode;
            }
        }
    }

    /**
     * Initialize preview update on settings change
     */
    function initPreviewUpdate() {
        // Debounce preview updates
        var previewTimeout;

        $('#boa-settings-form').on('change', 'input, select', function() {
            clearTimeout(previewTimeout);
            previewTimeout = setTimeout(function() {
                // Preview will update on save
            }, 500);
        });
    }

    /**
     * Refresh preview via AJAX
     */
    function refreshPreview() {
        var $preview = $('#boa-preview-container');

        if ($preview.length === 0) {
            return;
        }

        $.ajax({
            url: boaRatesAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'boa_get_preview',
                nonce: boaRatesAdmin.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    $preview.html(response.data.html);
                }
            }
        });
    }

})(jQuery);
