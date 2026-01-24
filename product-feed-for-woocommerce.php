<?php
/**
 * Plugin Name: GTIN Product Feed for Google Shopping
 * Plugin URI: https://wooplugin.pro/google-shopping
 * Description: Add GTIN, Brand, MPN fields to products. Generate Google Merchant Center feeds. Works with WooCommerce. By WooPlugin.
 * Version: 1.0.1
 * Author: WooPlugin
 * Author URI: https://wooplugin.pro
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gtin-product-feed-for-google-shopping
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 *
 * @package Product_Feed_For_WooCommerce
 */

defined('ABSPATH') || exit;

// Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

// Plugin constants
define('GSWC_VERSION', '1.0.1');
define('GSWC_PLUGIN_FILE', __FILE__);
define('GSWC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GSWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GSWC_PRO_URL', 'https://wooplugin.pro/google-shopping-pro');

// GitHub update checker
if (class_exists(YahnisElsts\PluginUpdateChecker\v5\PucFactory::class)) {
    $gswc_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/WooPlugin/product-feed-for-woocommerce/',
        __FILE__,
        'gtin-product-feed-for-google-shopping'
    );
    $gswc_update_checker->getVcsApi()->enableReleaseAssets();
}

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Check if WooCommerce is active
 */
function gswc_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('GTIN Product Feed for Google Shopping requires WooCommerce to be installed and active.', 'gtin-product-feed-for-google-shopping'); ?></p>
            </div>
            <?php
        });
        return false;
    }
    return true;
}

/**
 * Initialize the plugin
 */
function gswc_init() {
    if (!gswc_check_woocommerce()) {
        return;
    }

    // Include required files
    require_once GSWC_PLUGIN_DIR . 'includes/class-remote-data.php';
    require_once GSWC_PLUGIN_DIR . 'includes/class-settings.php';
    require_once GSWC_PLUGIN_DIR . 'includes/class-product-fields.php';
    require_once GSWC_PLUGIN_DIR . 'includes/class-feed-generator.php';
    require_once GSWC_PLUGIN_DIR . 'includes/class-admin.php';
    require_once GSWC_PLUGIN_DIR . 'includes/class-review-notice.php';
    require_once GSWC_PLUGIN_DIR . 'includes/class-pro-upgrader.php';

    // Initialize components
    GSWC_Settings::init();
    GSWC_Product_Fields::init();
    GSWC_Feed_Generator::init();
    GSWC_Admin::init();
    GSWC_Review_Notice::init();
    GSWC_Pro_Upgrader::init();
}
add_action('plugins_loaded', 'gswc_init');

/**
 * Activation hook
 */
function gswc_activate() {
    // Create feed directory
    $upload_dir = wp_upload_dir();
    $feed_dir = $upload_dir['basedir'] . '/gswc-feeds';
    if (!file_exists($feed_dir)) {
        wp_mkdir_p($feed_dir);
    }

    // Add .htaccess to protect feed directory (allow XML access)
    $htaccess = $feed_dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\n");
    }

    // Record installation time for review notice
    if (!get_option('gswc_installed_time')) {
        update_option('gswc_installed_time', time());
    }
}
register_activation_hook(__FILE__, 'gswc_activate');

/**
 * Deactivation hook
 */
function gswc_deactivate() {
    wp_clear_scheduled_hook('gswc_generate_cron');
}
register_deactivation_hook(__FILE__, 'gswc_deactivate');

/**
 * Add settings and upgrade links to plugins page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=gswc_feed') . '">' .
        esc_html__('Settings', 'gtin-product-feed-for-google-shopping') . '</a>';
    array_unshift($links, $settings_link);

    $links[] = '<a href="' . esc_url(GSWC_PRO_URL) . '" style="color: #4285f4; font-weight: bold;">' .
        esc_html__('Upgrade to Pro', 'gtin-product-feed-for-google-shopping') . '</a>';

    return $links;
});
