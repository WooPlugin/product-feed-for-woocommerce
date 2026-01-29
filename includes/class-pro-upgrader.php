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
        if (!$screen || $screen->id !== 'toplevel_page_gswc-dashboard') {
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

        $license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
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

        $download_url = isset($_POST['download_url']) ? esc_url_raw(wp_unslash($_POST['download_url'])) : '';
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

        // Use anonymous class for quiet upgrader skin
        $skin = new class extends WP_Upgrader_Skin {
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
        };
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
                'message'   => sprintf(
                    /* translators: %s: error message */
                    __('Pro installed but activation failed: %s', 'gtin-product-feed-for-google-shopping'),
                    $activate_result->get_error_message()
                ),
                'activated' => false,
            ]);
        }

        // Auto-activate the pending license in Pro
        // (admin_init won't fire again in this request, so we call it explicitly)
        $pending_license = get_option('gswc_pending_license_key');
        if ($pending_license && class_exists('GSWC_Pro_License')) {
            GSWC_Pro_License::activate($pending_license);
            delete_option('gswc_pending_license_key');
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
                           placeholder="<?php esc_attr_e('Enter license key...', 'gtin-product-feed-for-google-shopping'); ?>" />
                    <button type="button" id="gswc-validate-license" class="button button-primary">
                        <?php esc_html_e('Validate & Install Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    </button>
                </div>

                <div id="gswc-upgrade-status" class="gswc-upgrade-status"
                     data-nonce="<?php echo esc_attr($nonce); ?>"
                     data-enter-key="<?php echo esc_attr__('Please enter a license key.', 'gtin-product-feed-for-google-shopping'); ?>"
                     data-validating="<?php echo esc_attr__('Validating license...', 'gtin-product-feed-for-google-shopping'); ?>"
                     data-installing="<?php echo esc_attr__('Installing Pro...', 'gtin-product-feed-for-google-shopping'); ?>">
                </div>

                <p class="gswc-upgrade-help">
                    <?php esc_html_e("Don't have a license?", 'gtin-product-feed-for-google-shopping'); ?>
                    <a href="<?php echo esc_url(GSWC_PRO_URL); ?>" target="_blank">
                        <?php esc_html_e('Get Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
}
