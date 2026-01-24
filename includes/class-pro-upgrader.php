<?php
/**
 * Pro Upgrader
 *
 * Handles license validation and automatic Pro plugin installation.
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Pro_Upgrader
 */
class GSWC_Pro_Upgrader {

    /**
     * API base URL
     */
    const API_URL = 'https://wooplugin.pro/api';

    /**
     * Pro plugin slug
     */
    const PRO_PLUGIN_SLUG = 'product-feed-for-woocommerce-pro/product-feed-for-woocommerce-pro.php';

    /**
     * Initialize
     */
    public static function init() {
        add_action('wp_ajax_gswc_validate_license', [__CLASS__, 'ajax_validate_license']);
        add_action('wp_ajax_gswc_install_pro', [__CLASS__, 'ajax_install_pro']);
        add_action('admin_notices', [__CLASS__, 'maybe_show_upgrade_notice']);
    }

    /**
     * Check if Pro is installed
     *
     * @return bool
     */
    public static function is_pro_installed() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = get_plugins();
        return isset($plugins[self::PRO_PLUGIN_SLUG]);
    }

    /**
     * Check if Pro is active
     *
     * @return bool
     */
    public static function is_pro_active() {
        return is_plugin_active(self::PRO_PLUGIN_SLUG);
    }

    /**
     * Show upgrade notice on dashboard
     */
    public static function maybe_show_upgrade_notice() {
        // Don't show if Pro is already active
        if (self::is_pro_active()) {
            return;
        }

        // Only show on our dashboard page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_gswc-dashboard') {
            return;
        }

        // Check if user dismissed the upgrade prompt
        if (get_user_meta(get_current_user_id(), 'gswc_dismiss_upgrade_prompt', true)) {
            return;
        }
    }

    /**
     * AJAX: Validate license key
     */
    public static function ajax_validate_license() {
        check_ajax_referer('gswc_pro_upgrade', 'nonce');

        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gtin-product-feed-for-google-shopping')]);
        }

        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        if (empty($license_key)) {
            wp_send_json_error(['message' => __('Please enter a license key.', 'gtin-product-feed-for-google-shopping')]);
        }

        // Validate with our API
        $response = wp_remote_post(self::API_URL . '/license/validate', [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'license_key'   => $license_key,
                'instance_name' => self::get_site_url(),
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200 || empty($body['success'])) {
            $error = $body['error'] ?? __('Invalid license key.', 'gtin-product-feed-for-google-shopping');
            wp_send_json_error(['message' => $error]);
        }

        // Store license for Pro plugin to use after installation
        update_option('gswc_pending_license_key', $license_key);

        wp_send_json_success([
            'message'      => __('License validated! Ready to install Pro.', 'gtin-product-feed-for-google-shopping'),
            'download_url' => $body['download_url'] ?? '',
            'license'      => $body['license'] ?? [],
        ]);
    }

    /**
     * AJAX: Install Pro plugin
     */
    public static function ajax_install_pro() {
        check_ajax_referer('gswc_pro_upgrade', 'nonce');

        if (!current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('Permission denied.', 'gtin-product-feed-for-google-shopping')]);
        }

        $download_url = esc_url_raw($_POST['download_url'] ?? '');
        if (empty($download_url)) {
            wp_send_json_error(['message' => __('Download URL is missing.', 'gtin-product-feed-for-google-shopping')]);
        }

        // Verify URL is from our domain
        $parsed = wp_parse_url($download_url);
        if (empty($parsed['host']) || $parsed['host'] !== 'wooplugin.pro') {
            wp_send_json_error(['message' => __('Invalid download URL.', 'gtin-product-feed-for-google-shopping')]);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        // Use custom skin to capture output
        $skin = new GSWC_Quiet_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        // Install the plugin
        $result = $upgrader->install($download_url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        if ($result === false) {
            $errors = $skin->get_errors();
            $message = !empty($errors) ? implode(', ', $errors) : __('Installation failed.', 'gtin-product-feed-for-google-shopping');
            wp_send_json_error(['message' => $message]);
        }

        // Activate Pro
        $activate_result = activate_plugin(self::PRO_PLUGIN_SLUG);
        if (is_wp_error($activate_result)) {
            wp_send_json_success([
                'message'   => __('Pro installed! Please activate it manually.', 'gtin-product-feed-for-google-shopping'),
                'activated' => false,
            ]);
        }

        // Deactivate Free plugin
        deactivate_plugins(plugin_basename(GSWC_PLUGIN_FILE));

        wp_send_json_success([
            'message'     => __('Pro installed and activated!', 'gtin-product-feed-for-google-shopping'),
            'activated'   => true,
            'redirect_to' => admin_url('admin.php?page=wc-settings&tab=gswc_pro_feed&section=license'),
        ]);
    }

    /**
     * Get site URL for license instance
     *
     * @return string
     */
    private static function get_site_url() {
        $url = home_url();
        $parsed = wp_parse_url($url);
        return $parsed['host'] ?? $url;
    }

    /**
     * Render the upgrade UI for the dashboard
     */
    public static function render_upgrade_ui() {
        if (self::is_pro_active()) {
            return;
        }

        $nonce = wp_create_nonce('gswc_pro_upgrade');
        $pending_license = get_option('gswc_pending_license_key', '');
        ?>
        <div class="gswc-card gswc-upgrade-card">
            <h2><?php esc_html_e('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'); ?></h2>

            <?php if (self::is_pro_installed()) : ?>
                <div class="gswc-notice info">
                    <?php esc_html_e('Pro is installed but not active.', 'gtin-product-feed-for-google-shopping'); ?>
                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-small">
                        <?php esc_html_e('Activate Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('Enter your license key to download and install Pro automatically.', 'gtin-product-feed-for-google-shopping'); ?></p>

                <div class="gswc-license-form">
                    <input type="text"
                           id="gswc-license-key"
                           class="gswc-license-input"
                           placeholder="<?php esc_attr_e('Enter license key...', 'gtin-product-feed-for-google-shopping'); ?>"
                           value="<?php echo esc_attr($pending_license); ?>" />
                    <button type="button" id="gswc-validate-license" class="button button-primary">
                        <?php esc_html_e('Validate & Install Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    </button>
                </div>

                <div id="gswc-upgrade-status" class="gswc-upgrade-status"></div>

                <p class="gswc-upgrade-help">
                    <?php esc_html_e("Don't have a license?", 'gtin-product-feed-for-google-shopping'); ?>
                    <a href="<?php echo esc_url(GSWC_PRO_URL); ?>" target="_blank">
                        <?php esc_html_e('Get Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <script>
        (function() {
            var validateBtn = document.getElementById('gswc-validate-license');
            var licenseInput = document.getElementById('gswc-license-key');
            var status = document.getElementById('gswc-upgrade-status');

            if (!validateBtn) return;

            validateBtn.addEventListener('click', function() {
                var licenseKey = licenseInput.value.trim();
                if (!licenseKey) {
                    showStatus('error', '<?php echo esc_js(__('Please enter a license key.', 'gtin-product-feed-for-google-shopping')); ?>');
                    return;
                }

                showStatus('loading', '<?php echo esc_js(__('Validating license...', 'gtin-product-feed-for-google-shopping')); ?>');
                validateBtn.disabled = true;

                // Step 1: Validate license
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'gswc_validate_license',
                        nonce: '<?php echo esc_js($nonce); ?>',
                        license_key: licenseKey
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        throw new Error(data.data.message);
                    }

                    showStatus('loading', '<?php echo esc_js(__('Installing Pro...', 'gtin-product-feed-for-google-shopping')); ?>');

                    // Step 2: Install Pro
                    return fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'gswc_install_pro',
                            nonce: '<?php echo esc_js($nonce); ?>',
                            download_url: data.data.download_url
                        })
                    });
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        throw new Error(data.data.message);
                    }

                    showStatus('success', data.data.message);

                    if (data.data.redirect_to) {
                        setTimeout(function() {
                            window.location.href = data.data.redirect_to;
                        }, 1500);
                    }
                })
                .catch(function(error) {
                    showStatus('error', error.message);
                    validateBtn.disabled = false;
                });
            });

            function showStatus(type, message) {
                status.className = 'gswc-upgrade-status ' + type;
                if (type === 'loading') {
                    status.innerHTML = '<span class="spinner is-active"></span> ' + message;
                } else {
                    status.textContent = message;
                }
            }
        })();
        </script>

        <style>
            .gswc-upgrade-card {
                background: linear-gradient(135deg, #f0f6ff 0%, #e8f5e9 100%);
                border: 2px solid #4285f4;
            }
            .gswc-license-form {
                display: flex;
                gap: 10px;
                margin: 16px 0;
            }
            .gswc-license-input {
                flex: 1;
                padding: 8px 12px;
                font-size: 14px;
            }
            .gswc-upgrade-status {
                margin: 12px 0;
                padding: 10px;
                border-radius: 4px;
                display: none;
            }
            .gswc-upgrade-status.loading,
            .gswc-upgrade-status.success,
            .gswc-upgrade-status.error {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .gswc-upgrade-status.loading {
                background: #e0f2fe;
                color: #075985;
            }
            .gswc-upgrade-status.success {
                background: #dcfce7;
                color: #166534;
            }
            .gswc-upgrade-status.error {
                background: #fee2e2;
                color: #991b1b;
            }
            .gswc-upgrade-status .spinner {
                float: none;
                margin: 0;
            }
            .gswc-upgrade-help {
                font-size: 13px;
                color: #6b7280;
                margin: 0;
            }
        </style>
        <?php
    }
}

/**
 * Quiet upgrader skin that captures errors
 */
class GSWC_Quiet_Upgrader_Skin extends WP_Upgrader_Skin {
    private $errors = [];

    public function header() {}
    public function footer() {}
    public function feedback($feedback, ...$args) {}

    public function error($errors) {
        if (is_wp_error($errors)) {
            $this->errors = array_merge($this->errors, $errors->get_error_messages());
        } elseif (is_string($errors)) {
            $this->errors[] = $errors;
        }
    }

    public function get_errors() {
        return $this->errors;
    }
}
