/**
 * Google Shopping for WooCommerce Admin JavaScript
 *
 * Uses vanilla JavaScript - no jQuery dependency
 *
 * @package Google_Shopping_For_WooCommerce
 */

(function () {
    'use strict';

    /**
     * Initialize when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function () {
        initGenerateFeed();
        initWidgetGenerateFeed();
        initCopyUrl();
        initWidgetCopy();
    });

    /**
     * Initialize feed generation button (settings page)
     */
    function initGenerateFeed() {
        var button = document.getElementById('gswc-generate-feed');
        if (!button) {
            return;
        }

        var spinner = document.getElementById('gswc-feed-spinner');
        var result = document.getElementById('gswc-feed-result');

        button.addEventListener('click', function () {
            generateFeed(button, spinner, result);
        });
    }

    /**
     * Initialize widget feed generation button (dashboard widget)
     */
    function initWidgetGenerateFeed() {
        var button = document.getElementById('gswc-widget-generate');
        if (!button) {
            return;
        }

        var spinner = document.getElementById('gswc-widget-spinner');
        var result = document.getElementById('gswc-widget-result');

        button.addEventListener('click', function () {
            generateFeed(button, spinner, result);
        });
    }

    /**
     * Generate feed via AJAX
     *
     * @param {HTMLButtonElement} button The button element
     * @param {HTMLElement} spinner The spinner element
     * @param {HTMLElement} result The result message element
     */
    function generateFeed(button, spinner, result) {
        var originalText = button.textContent;

        // Disable button and show spinner
        button.disabled = true;
        button.textContent = gswcFeed.strings.generating;
        if (spinner) {
            spinner.classList.add('is-active');
        }
        if (result) {
            result.textContent = '';
            result.className = '';
        }

        // Make AJAX request
        var formData = new FormData();
        formData.append('action', 'gswc_generate_feed');
        formData.append('nonce', gswcFeed.nonce);

        fetch(gswcFeed.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    if (result) {
                        result.textContent = data.data.message;
                        result.className = 'success';
                    }
                    // If this was the first feed generation, reload to show full UI
                    var feedTime = document.getElementById('gswc-feed-time');
                    if (!feedTime) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                        return;
                    }
                    // Update UI dynamically
                    updateFeedStats(data.data);
                } else {
                    if (result) {
                        result.textContent = gswcFeed.strings.error + ' ' + data.data;
                        result.className = 'error';
                    }
                }
            })
            .catch(function (error) {
                if (result) {
                    result.textContent = gswcFeed.strings.error + ' ' + error.message;
                    result.className = 'error';
                }
            })
            .finally(function () {
                button.disabled = false;
                button.textContent = originalText;
                if (spinner) {
                    spinner.classList.remove('is-active');
                }
            });
    }

    /**
     * Update feed stats after successful generation
     *
     * @param {Object} data Response data from AJAX
     */
    function updateFeedStats(data) {
        // Update "Last Update" time displays (widget)
        var timeElements = document.querySelectorAll('.gswc-widget-stat-time');
        timeElements.forEach(function (el) {
            el.textContent = data.timeago;
        });

        // Update "Last Generated" on dashboard page
        var feedTime = document.getElementById('gswc-feed-time');
        if (feedTime) {
            feedTime.textContent = data.timeago;
        }

        // Update product count on dashboard page
        var feedCount = document.getElementById('gswc-feed-count');
        if (feedCount && data.count !== undefined) {
            feedCount.textContent = data.count;
        }

        // Update product count in widget
        var countElements = document.querySelectorAll('.gswc-widget-stat-value');
        if (countElements.length > 0 && data.count !== undefined) {
            countElements[0].textContent = data.count;
        }

        // Update feed URL in input fields
        var urlInputs = document.querySelectorAll('.gswc-feed-url-input, .gswc-widget-url input');
        urlInputs.forEach(function (input) {
            input.value = data.url;
        });

        // Update copy button data-url attributes
        var copyButtons = document.querySelectorAll('.gswc-copy-url, .gswc-widget-copy');
        copyButtons.forEach(function (btn) {
            btn.dataset.url = data.url;
        });

        // Hide stale warning
        var staleWarning = document.querySelector('.gswc-widget-stale');
        if (staleWarning) {
            staleWarning.style.display = 'none';
        }

        // Update feed status indicator to show checkmark
        var feedStatus = document.querySelectorAll('.gswc-widget-stat-value.status-none');
        feedStatus.forEach(function (el) {
            el.textContent = '✓';
            el.classList.remove('status-none');
            el.classList.add('status-ok');
        });
    }

    /**
     * Initialize copy URL button (settings/dashboard pages)
     */
    function initCopyUrl() {
        var buttons = document.querySelectorAll('.gswc-copy-url');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                copyToClipboard(button.dataset.url, button);
            });
        });
    }

    /**
     * Initialize widget copy button (dashboard widget)
     */
    function initWidgetCopy() {
        var buttons = document.querySelectorAll('.gswc-widget-copy');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                copyToClipboard(button.dataset.url, button);
            });
        });
    }

    /**
     * Copy text to clipboard
     *
     * @param {string} text Text to copy
     * @param {HTMLButtonElement} button Button to update with feedback
     */
    function copyToClipboard(text, button) {
        var originalText = button.textContent;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopyFeedback(button, originalText);
            });
        } else {
            // Fallback for older browsers
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);

            showCopyFeedback(button, originalText);
        }
    }

    /**
     * Show copy feedback on button
     *
     * @param {HTMLButtonElement} button Button element
     * @param {string} originalText Original button text
     */
    function showCopyFeedback(button, originalText) {
        button.textContent = 'Copied!';
        setTimeout(function () {
            button.textContent = originalText;
        }, 2000);
    }

    /**
     * Simple dialog utility (replaces jQuery UI Dialog)
     */
    window.GSWCDialog = {
        /**
         * Show alert dialog
         *
         * @param {string} message Message to display
         * @param {Function} callback Optional callback after close
         */
        alert: function (message, callback) {
            var dialog = this._createDialog('Alert', message, [
                { label: 'OK', primary: true, action: callback }
            ]);
            dialog.showModal();
        },

        /**
         * Show confirm dialog
         *
         * @param {string} message Message to display
         * @param {Function} onConfirm Callback on confirm
         * @param {Function} onCancel Callback on cancel
         */
        confirm: function (message, onConfirm, onCancel) {
            var dialog = this._createDialog('Confirm', message, [
                { label: 'Cancel', action: onCancel },
                { label: 'OK', primary: true, action: onConfirm }
            ]);
            dialog.showModal();
        },

        /**
         * Create dialog element
         *
         * @param {string} title Dialog title
         * @param {string} content Dialog content
         * @param {Array} buttons Button definitions
         * @returns {HTMLDialogElement}
         */
        _createDialog: function (title, content, buttons) {
            // Remove existing dialog
            var existing = document.getElementById('gswc-dialog');
            if (existing) {
                existing.remove();
            }

            var dialog = document.createElement('dialog');
            dialog.id = 'gswc-dialog';
            dialog.className = 'gswc-dialog';

            var html = '<div class="gswc-dialog-header">' + this._escapeHtml(title) + '</div>';
            html += '<div class="gswc-dialog-content"><p>' + this._escapeHtml(content) + '</p></div>';
            html += '<div class="gswc-dialog-footer">';

            buttons.forEach(function (btn, index) {
                var className = btn.primary ? 'button button-primary' : 'button';
                html += '<button type="button" class="' + className + '" data-action="' + index + '">';
                html += btn.label;
                html += '</button>';
            });

            html += '</div>';
            dialog.innerHTML = html;

            // Add event listeners
            var self = this;
            dialog.querySelectorAll('button').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var actionIndex = parseInt(btn.dataset.action, 10);
                    var buttonDef = buttons[actionIndex];
                    dialog.close();
                    dialog.remove();
                    if (typeof buttonDef.action === 'function') {
                        buttonDef.action();
                    }
                });
            });

            // Close on backdrop click
            dialog.addEventListener('click', function (e) {
                if (e.target === dialog) {
                    dialog.close();
                    dialog.remove();
                }
            });

            document.body.appendChild(dialog);
            return dialog;
        },

        /**
         * Escape HTML entities
         *
         * @param {string} text Text to escape
         * @returns {string}
         */
        _escapeHtml: function (text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
})();
