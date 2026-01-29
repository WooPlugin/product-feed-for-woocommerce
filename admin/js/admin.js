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
        initFeedToggle();
        initInlinePairPreview();
        initProNotice();
        initDashboardPromo();
        initHelpDropdown();
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
        // Get original text - check for action item label first
        var labelSpan = button.querySelector('.gswc-action-label');
        var originalText = labelSpan ? labelSpan.textContent : button.textContent;

        // Disable button and show spinner
        button.disabled = true;
        if (labelSpan) {
            labelSpan.textContent = gswcFeed.strings.generating;
        } else {
            button.textContent = gswcFeed.strings.generating;
        }
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
                if (labelSpan) {
                    labelSpan.textContent = originalText;
                } else {
                    button.textContent = originalText;
                }
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
     * Initialize inline pair preview (live update as user types)
     */
    function initInlinePairPreview() {
        var inputs = document.querySelectorAll('.gswc-inline-pair-input');

        inputs.forEach(function (input) {
            // Update preview and clear button visibility on input
            input.addEventListener('input', function () {
                updateInlinePairPreview(input);
                updateClearButtonVisibility(input);
            });

            // Initial visibility check
            updateClearButtonVisibility(input);
        });

        // Clear buttons
        var clearButtons = document.querySelectorAll('.gswc-input-clear');
        clearButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = btn.previousElementSibling;
                if (input && input.tagName === 'INPUT') {
                    input.value = '';
                    input.focus();
                    updateClearButtonVisibility(input);
                    // Trigger input event to update preview
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });
    }

    /**
     * Show/hide clear button based on input value
     *
     * @param {HTMLInputElement} input The input element
     */
    function updateClearButtonVisibility(input) {
        var clearBtn = input.nextElementSibling;
        if (clearBtn && clearBtn.classList.contains('gswc-input-clear')) {
            if (input.value.trim()) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
        }
    }

    /**
     * Update the example preview for inline pair fields
     *
     * @param {HTMLInputElement} input The input that changed
     */
    function updateInlinePairPreview(input) {
        var prefixId = input.dataset.prefixId;
        var suffixId = input.dataset.suffixId;

        // Find the preview element in the same row
        var row = input.closest('.gswc-inline-pair');
        if (!row) return;

        var preview = row.querySelector('.gswc-example-preview');
        if (!preview) return;

        var example = preview.dataset.example || '';
        var prefixInput = document.getElementById(prefixId);
        var suffixInput = document.getElementById(suffixId);

        var prefix = prefixInput ? prefixInput.value.trim() : '';
        var suffix = suffixInput ? suffixInput.value.trim() : '';

        var parts = [];
        if (prefix) parts.push(prefix);
        parts.push(example);
        if (suffix) parts.push(suffix);

        preview.textContent = parts.join(' ');
    }

    /**
     * Initialize feed toggle auto-save
     */
    function initFeedToggle() {
        var toggle = document.querySelector('.gswc-toggle-switch input[name="gswc_feed_enabled"]');
        if (!toggle) {
            return;
        }

        toggle.addEventListener('change', function () {
            var enabled = toggle.checked;
            var card = toggle.closest('.gswc-feed-channel-card');
            var statusBadge = card ? card.querySelector('.gswc-feed-status-badge') : null;

            // Disable toggle during save
            toggle.disabled = true;

            // Make AJAX request
            var formData = new FormData();
            formData.append('action', 'gswc_toggle_feed');
            formData.append('nonce', gswcFeed.nonce);
            formData.append('enabled', enabled ? 'true' : 'false');

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
                        // Reload page to update UI properly
                        window.location.reload();
                    } else {
                        // Revert toggle on error
                        toggle.checked = !enabled;
                        alert(data.data || 'Error saving setting');
                    }
                })
                .catch(function (error) {
                    // Revert toggle on error
                    toggle.checked = !enabled;
                    alert('Error: ' + error.message);
                })
                .finally(function () {
                    toggle.disabled = false;
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
        // Get original text - check for action item label first
        var labelSpan = button.querySelector('.gswc-action-label');
        var originalText = labelSpan ? labelSpan.textContent : button.textContent;

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
        // For action items, update the label span
        var labelSpan = button.querySelector('.gswc-action-label');
        if (labelSpan) {
            labelSpan.textContent = 'Copied!';
            setTimeout(function () {
                labelSpan.textContent = originalText;
            }, 2000);
        } else {
            button.textContent = 'Copied!';
            setTimeout(function () {
                button.textContent = originalText;
            }, 2000);
        }
    }

    /**
     * Initialize dashboard promo dismissal
     */
    function initDashboardPromo() {
        var promo = document.querySelector('.gswc-dashboard-promo-box');
        if (!promo) {
            return;
        }

        var dismissBtn = promo.querySelector('.gswc-dashboard-promo-dismiss');
        if (!dismissBtn) {
            return;
        }

        dismissBtn.addEventListener('click', function () {
            var formData = new FormData();
            formData.append('action', 'gswc_dismiss_dashboard_promo');
            formData.append('nonce', promo.dataset.nonce);

            fetch(gswcFeed.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            promo.style.display = 'none';
        });
    }

    /**
     * Initialize Help dropdown
     */
    function initHelpDropdown() {
        var dropdown = document.querySelector('.gswc-help-dropdown');
        if (!dropdown) {
            return;
        }

        var toggle = dropdown.querySelector('.gswc-help-toggle');

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });

        // Close when clicking outside
        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });

        // Close on escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                dropdown.classList.remove('open');
            }
        });
    }

    /**
     * Initialize Pro notice dismissal
     */
    function initProNotice() {
        var notice = document.querySelector('.gswc-pro-notice');
        if (!notice) {
            return;
        }

        var nonce = notice.dataset.nonce;
        var snoozeBtn = notice.querySelector('.gswc-pro-notice-snooze');

        // Handle snooze button
        if (snoozeBtn) {
            snoozeBtn.addEventListener('click', function () {
                dismissProNotice(notice, nonce, 'snooze');
            });
        }

        // Handle X button (permanent dismiss)
        var dismissBtn = notice.querySelector('.notice-dismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                dismissProNotice(notice, nonce, 'dismiss');
            });
        }
    }

    /**
     * Dismiss Pro notice via AJAX
     *
     * @param {HTMLElement} notice The notice element
     * @param {string} nonce Security nonce
     * @param {string} action 'dismiss' or 'snooze'
     */
    function dismissProNotice(notice, nonce, action) {
        var formData = new FormData();
        formData.append('action', 'gswc_dismiss_pro_notice');
        formData.append('nonce', nonce);
        formData.append('dismiss_action', action);

        fetch(gswcFeed.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function () {
            // Hide the notice
            notice.style.display = 'none';
        });
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
