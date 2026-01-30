<?php
/**
 * Admin functionality
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Admin
 */
class GSWC_Admin {

    /**
     * Initialize admin
     */
    public static function init() {
        // Test with very high priority to ensure it runs
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts'], 999);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_filter('woocommerce_screen_ids', [__CLASS__, 'add_screen_ids']);
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);

        // Pro upsell
        add_action('admin_notices', [__CLASS__, 'render_pro_banner']);
        add_action('admin_notices', [__CLASS__, 'render_plugin_header']);
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_scripts($hook) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        // Check by hook prefix OR page parameter
        $is_our_page_by_hook = strpos($hook, 'gtin-product-feed_page_') === 0 || $hook === 'toplevel_page_gswc-dashboard';
        $our_pages = ['gswc-dashboard', 'gswc-general', 'gswc-customize', 'gswc-feeds', 'gswc-filters', 'gswc-license'];
        $is_our_page_by_param = in_array($page, $our_pages, true);
        $is_our_page = $is_our_page_by_hook || $is_our_page_by_param;

        // Also load on product pages and WooCommerce pages
        $screen = get_current_screen();
        $is_product_page = $screen && ($screen->post_type === 'product' || $screen->id === 'product');
        $is_wp_dashboard = $hook === 'index.php';
        $is_woocommerce_page = $screen && strpos($screen->id, 'woocommerce') !== false;

        // Always enqueue CSS on all admin pages (menu badge needs it everywhere)
        wp_enqueue_style(
            'gswc-admin',
            GSWC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            GSWC_VERSION
        );

        // Hide WordPress help tab on our pages
        if ($is_our_page) {
            wp_add_inline_style('gswc-admin', '#contextual-help-link-wrap { display: none !important; }');
        }

        // Only enqueue JS on relevant pages
        if (!$is_our_page && !$is_product_page && !$is_wp_dashboard && !$is_woocommerce_page) {
            return;
        }

        // Enqueue WooCommerce Select2 for multiselect dropdowns on our pages
        if ($is_our_page) {
            wp_enqueue_script('wc-enhanced-select');
        }

        wp_enqueue_script(
            'gswc-admin',
            GSWC_PLUGIN_URL . 'admin/js/admin.js',
            [],
            GSWC_VERSION,
            true
        );

        $upgrade_url = add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'menu',
            'utm_campaign' => 'free-to-pro',
        ], 'https://wooplugin.pro/google-shopping-pro#pricing');

        wp_localize_script('gswc-admin', 'gswcFeed', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('gswc_feed_nonce'),
            'upgradeUrl' => $upgrade_url,
            'strings'    => [
                'generating' => __('Generating...', 'gtin-product-feed-for-google-shopping'),
                'success'    => __('Feed generated successfully!', 'gtin-product-feed-for-google-shopping'),
                'error'      => __('Error:', 'gtin-product-feed-for-google-shopping'),
            ],
        ]);

        // Upgrade link script (makes menu link open in new tab)
        wp_add_inline_script('gswc-admin', '(function() { var link = document.querySelector(\'a[href*="page=gswc-upgrade-pro"]\'); if (link) { link.href = gswcFeed.upgradeUrl; link.target = "_blank"; } })();');
    }

    /**
     * Add admin menu
     */
    public static function add_menu() {
        // Top-level menu
        add_menu_page(
            __('Product Feed', 'gtin-product-feed-for-google-shopping'),
            __('Product Feed', 'gtin-product-feed-for-google-shopping'),
            'manage_woocommerce',
            'gswc-dashboard',
            [__CLASS__, 'render_dashboard'],
            GSWC_PLUGIN_URL . 'admin/images/menu-icon.png',
            56
        );

        // Dashboard submenu (default)
        add_submenu_page(
            'gswc-dashboard',
            __('Dashboard', 'gtin-product-feed-for-google-shopping'),
            __('Dashboard', 'gtin-product-feed-for-google-shopping'),
            'manage_woocommerce',
            'gswc-dashboard',
            [__CLASS__, 'render_dashboard']
        );

        // Settings section submenus
        $sections = [
            'gswc-general'    => __('General', 'gtin-product-feed-for-google-shopping'),
            'gswc-customize' => __('Customization', 'gtin-product-feed-for-google-shopping'),
            'gswc-feeds'      => __('Feeds', 'gtin-product-feed-for-google-shopping'),
            'gswc-filters'    => __('Filters', 'gtin-product-feed-for-google-shopping'),
        ];

        foreach ($sections as $slug => $title) {
            add_submenu_page(
                'gswc-dashboard',
                $title,
                $title,
                'manage_woocommerce',
                $slug,
                [__CLASS__, 'render_settings_page']
            );
        }

        // License submenu
        add_submenu_page(
            'gswc-dashboard',
            __('License', 'gtin-product-feed-for-google-shopping'),
            __('License', 'gtin-product-feed-for-google-shopping'),
            'manage_woocommerce',
            'gswc-license',
            [__CLASS__, 'render_license_page']
        );

        // Upgrade to Pro external link (placeholder, will be modified via JS)
        add_submenu_page(
            'gswc-dashboard',
            __('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'),
            __('Upgrade to Pro', 'gtin-product-feed-for-google-shopping') . ' <span class="gswc-menu-badge">Pro</span>',
            'manage_woocommerce',
            'gswc-upgrade-pro',
            '__return_null'
        );
    }

    /**
     * Render persistent Pro banner on plugin pages
     */
    public static function render_pro_banner() {
        // Only show on our plugin pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $our_pages = ['gswc-dashboard', 'gswc-general', 'gswc-customize', 'gswc-feeds', 'gswc-filters', 'gswc-license'];
        if (!in_array($page, $our_pages, true)) {
            return;
        }

        $upgrade_url = add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'top-banner',
            'utm_campaign' => 'free-to-pro',
        ], 'https://wooplugin.pro/google-shopping-pro#pricing');
        ?>
        <div class="gswc-pro-banner">
            <?php
            printf(
                /* translators: %s: upgrade link */
                esc_html__("You're using GTIN Product Feed FREE VERSION. To unlock more features consider %s.", 'gtin-product-feed-for-google-shopping'),
                '<a href="' . esc_url($upgrade_url) . '" target="_blank">' . esc_html__('upgrading to Pro', 'gtin-product-feed-for-google-shopping') . '</a>'
            );
            ?>
        </div>
        <?php
    }

    /**
     * Render plugin header bar on plugin pages
     */
    public static function render_plugin_header() {
        // Only show on our plugin pages
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $our_pages = ['gswc-dashboard', 'gswc-general', 'gswc-customize', 'gswc-feeds', 'gswc-filters', 'gswc-license'];
        if (!in_array($page, $our_pages, true)) {
            return;
        }

        $upgrade_url = add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'header',
            'utm_campaign' => 'free-to-pro',
        ], 'https://wooplugin.pro/google-shopping-pro#pricing');

        $help_url = 'https://wooplugin.pro/docs';
        ?>
        <div class="gswc-plugin-header">
            <div class="gswc-plugin-header-left">
                <span class="gswc-plugin-header-logo">
                    <svg viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="256" height="256" rx="32" fill="#7f54b3"/>
                        <g transform="translate(68, 68) scale(5)" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none">
                            <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>
                        </g>
                    </svg>
                </span>
                <span class="gswc-plugin-header-name">WooPlugin</span>
                <a href="<?php echo esc_url($upgrade_url); ?>" class="gswc-upgrade-btn" target="_blank">
                    <?php esc_html_e('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </a>
            </div>
            <div class="gswc-plugin-header-right">
                <div class="gswc-help-dropdown">
                    <button type="button" class="gswc-header-btn gswc-help-toggle">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('Help', 'gtin-product-feed-for-google-shopping'); ?>
                    </button>
                    <div class="gswc-help-dropdown-menu">
                        <div class="gswc-help-section">
                            <h4><?php esc_html_e('Helpful Articles', 'gtin-product-feed-for-google-shopping'); ?></h4>
                            <a href="https://wooplugin.pro/docs/getting-started/installation" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                <?php esc_html_e('Getting Started Guide', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                            <a href="https://wooplugin.pro/docs/feeds/google-shopping" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                <?php esc_html_e('Connect to Google Merchant Center', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                            <a href="https://wooplugin.pro/docs/features/product-fields" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                <?php esc_html_e('Adding GTIN, Brand, MPN', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                            <a href="https://wooplugin.pro/docs/troubleshooting/faq" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                                <?php esc_html_e('Troubleshooting Feed Issues', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                        </div>
                        <div class="gswc-help-section">
                            <h4><?php esc_html_e('Resources', 'gtin-product-feed-for-google-shopping'); ?></h4>
                            <a href="https://wooplugin.pro/docs" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
                                <?php esc_html_e('Documentation', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                            <a href="https://wooplugin.pro/support" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-2 0c0 .993-.241 1.929-.668 2.754l-1.524-1.525a3.997 3.997 0 00.078-2.183l1.562-1.562C15.802 8.249 16 9.1 16 10zm-5.165 3.913l1.58 1.58A5.98 5.98 0 0110 16a5.976 5.976 0 01-2.516-.552l1.562-1.562a4.006 4.006 0 001.789.027zm-4.677-2.796a4.002 4.002 0 01-.041-2.08l-.08.08-1.53-1.533A5.98 5.98 0 004 10c0 .954.223 1.856.619 2.657l1.54-1.54zm1.088-6.45A5.974 5.974 0 0110 4c.954 0 1.856.223 2.657.619l-1.54 1.54a4.002 4.002 0 00-2.346.033L7.246 4.668zM12 10a2 2 0 11-4 0 2 2 0 014 0z" clip-rule="evenodd"/></svg>
                                <?php esc_html_e('Support', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                            <a href="https://wordpress.org/support/plugin/gtin-product-feed-for-google-shopping/reviews/#new-post" target="_blank">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                <?php esc_html_e('Leave a Review', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render license page
     */
    public static function render_license_page() {
        $upgrade_url = add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'license-page',
            'utm_campaign' => 'free-to-pro',
        ], 'https://wooplugin.pro/google-shopping-pro#pricing');

        $learn_more_url = add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'license-page',
            'utm_campaign' => 'free-to-pro',
        ], 'https://wooplugin.pro/google-shopping-pro');
        ?>
        <div class="wrap gswc-license-wrap">
            <h1 class="gswc-page-title"><?php esc_html_e('License', 'gtin-product-feed-for-google-shopping'); ?></h1>

            <!-- Plugin Info Card -->
            <div class="gswc-license-info-card">
                <div class="gswc-license-info-row">
                    <span class="gswc-license-info-label"><?php esc_html_e('GTIN Product Feed', 'gtin-product-feed-for-google-shopping'); ?></span>
                </div>
                <div class="gswc-license-info-row">
                    <span class="gswc-license-info-label"><?php esc_html_e('Version:', 'gtin-product-feed-for-google-shopping'); ?></span>
                    <span class="gswc-license-info-value"><?php echo esc_html(GSWC_VERSION); ?></span>
                </div>
                <div class="gswc-license-info-row">
                    <span class="gswc-license-info-label"><?php esc_html_e('License Status:', 'gtin-product-feed-for-google-shopping'); ?></span>
                    <span class="gswc-license-info-value gswc-license-free"><?php esc_html_e('Free Version', 'gtin-product-feed-for-google-shopping'); ?></span>
                </div>
            </div>

            <!-- Upgrade Cards Grid -->
            <div class="gswc-license-grid">

            <!-- Upgrade to Pro Card -->
            <div class="gswc-upgrade-card">
                <h2><?php esc_html_e('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'); ?></h2>
                <p class="gswc-upgrade-description">
                    <?php esc_html_e('The Pro version includes scheduled auto-updates, additional feed channels, smart auto-fill, category mapping, and priority support.', 'gtin-product-feed-for-google-shopping'); ?>
                </p>

                <ul class="gswc-feature-list">
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('Scheduled auto-updates - keep your feed always fresh', 'gtin-product-feed-for-google-shopping'); ?>
                    </li>
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('Update on product save - feed updates automatically when you edit products', 'gtin-product-feed-for-google-shopping'); ?>
                    </li>
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('Facebook, Pinterest, TikTok, Bing, Snapchat feed channels', 'gtin-product-feed-for-google-shopping'); ?>
                    </li>
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('Smart Auto-fill from product data', 'gtin-product-feed-for-google-shopping'); ?>
                    </li>
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('Auto Category Mapping for Google product taxonomy', 'gtin-product-feed-for-google-shopping'); ?>
                    </li>
                    <li>
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('1 year of updates & priority support', 'gtin-product-feed-for-google-shopping'); ?>
                    </li>
                </ul>

                <div class="gswc-upgrade-actions">
                    <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary button-hero gswc-upgrade-button" target="_blank">
                        <?php esc_html_e('Get Pro & Unlock All Features', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                    <a href="<?php echo esc_url($learn_more_url); ?>" class="gswc-learn-more-link" target="_blank">
                        <?php esc_html_e('Learn more about Pro features', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                </div>
            </div>

            <!-- Screenshot Card -->
            <div class="gswc-screenshot-card">
                <h2><?php esc_html_e('Pro Dashboard', 'gtin-product-feed-for-google-shopping'); ?></h2>
                <div class="gswc-screenshot-browser">
                    <div class="gswc-screenshot-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="gswc-screenshot-content">
                        <div class="gswc-screenshot-channels">
                            <h4><?php esc_html_e('Feed Channels', 'gtin-product-feed-for-google-shopping'); ?></h4>
                            <div class="gswc-screenshot-channel">
                                <span class="gswc-screenshot-icon" style="color: #4285f4;">&#9679;</span>
                                <span>Google Shopping</span>
                                <span class="gswc-screenshot-badge gswc-screenshot-active"><?php esc_html_e('Active', 'gtin-product-feed-for-google-shopping'); ?></span>
                            </div>
                            <div class="gswc-screenshot-channel">
                                <span class="gswc-screenshot-icon" style="color: #1877f2;">&#9679;</span>
                                <span>Facebook & Instagram</span>
                                <span class="gswc-screenshot-badge gswc-screenshot-active"><?php esc_html_e('Active', 'gtin-product-feed-for-google-shopping'); ?></span>
                            </div>
                            <div class="gswc-screenshot-channel">
                                <span class="gswc-screenshot-icon" style="color: #e60023;">&#9679;</span>
                                <span>Pinterest</span>
                                <span class="gswc-screenshot-badge gswc-screenshot-active"><?php esc_html_e('Active', 'gtin-product-feed-for-google-shopping'); ?></span>
                            </div>
                            <div class="gswc-screenshot-channel">
                                <span class="gswc-screenshot-icon" style="color: #000;">&#9679;</span>
                                <span>TikTok</span>
                                <span class="gswc-screenshot-badge gswc-screenshot-pending"><?php esc_html_e('Pending', 'gtin-product-feed-for-google-shopping'); ?></span>
                            </div>
                            <div class="gswc-screenshot-channel">
                                <span class="gswc-screenshot-icon" style="color: #00809d;">&#9679;</span>
                                <span>Bing Shopping</span>
                                <span class="gswc-screenshot-badge gswc-screenshot-pending"><?php esc_html_e('Pending', 'gtin-product-feed-for-google-shopping'); ?></span>
                            </div>
                        </div>
                        <div class="gswc-screenshot-schedule">
                            <h4><?php esc_html_e('Auto-Update Schedule', 'gtin-product-feed-for-google-shopping'); ?></h4>
                            <div class="gswc-screenshot-schedule-grid">
                                <div class="gswc-screenshot-schedule-row">
                                    <span><?php esc_html_e('Every 6 hours', 'gtin-product-feed-for-google-shopping'); ?></span>
                                    <span class="gswc-screenshot-badge gswc-screenshot-active"><?php esc_html_e('Enabled', 'gtin-product-feed-for-google-shopping'); ?></span>
                                </div>
                                <div class="gswc-screenshot-schedule-row">
                                    <span><?php esc_html_e('On product save', 'gtin-product-feed-for-google-shopping'); ?></span>
                                    <span class="gswc-screenshot-badge gswc-screenshot-active"><?php esc_html_e('Enabled', 'gtin-product-feed-for-google-shopping'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div><!-- .gswc-license-grid -->

        </div>
        <?php
    }

    /**
     * Render Pro upsell notice
     */
    public static function render_pro_notice() {
        // Only show to users with manage_woocommerce capability
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Only show on plugin pages (exclude upgrade page itself)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $notice_pages = ['gswc-dashboard', 'gswc-general', 'gswc-customize', 'gswc-feeds', 'gswc-filters'];
        if (!in_array($page, $notice_pages, true)) {
            return;
        }

        $user_id = get_current_user_id();

        // Check if permanently dismissed
        if (get_user_meta($user_id, 'gswc_pro_notice_dismissed', true)) {
            return;
        }

        // Check if snoozed
        $snoozed_until = get_user_meta($user_id, 'gswc_pro_notice_snoozed', true);
        if ($snoozed_until && time() < (int) $snoozed_until) {
            return;
        }

        $upgrade_url = add_query_arg([
            'utm_source'   => 'plugin',
            'utm_medium'   => 'notice',
            'utm_campaign' => 'free-to-pro',
        ], 'https://wooplugin.pro/google-shopping-pro#pricing');
        ?>
        <div class="notice notice-info is-dismissible gswc-pro-notice" data-nonce="<?php echo esc_attr(wp_create_nonce('gswc_dismiss_pro_notice')); ?>">
            <p>
                <strong><?php esc_html_e('Unlock More Features with Pro', 'gtin-product-feed-for-google-shopping'); ?></strong><br>
                <?php esc_html_e('Get multiple feeds, advanced filtering, category mapping, and priority support.', 'gtin-product-feed-for-google-shopping'); ?>
            </p>
            <p class="gswc-pro-notice-actions">
                <a href="<?php echo esc_url($upgrade_url); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Learn More', 'gtin-product-feed-for-google-shopping'); ?>
                </a>
                <button type="button" class="button gswc-pro-notice-snooze">
                    <?php esc_html_e('Maybe Later', 'gtin-product-feed-for-google-shopping'); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX handler for dismissing Pro notice
     */
    public static function ajax_dismiss_pro_notice() {
        check_ajax_referer('gswc_dismiss_pro_notice', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permission denied');
        }

        $user_id = get_current_user_id();
        $action = isset($_POST['dismiss_action']) ? sanitize_text_field(wp_unslash($_POST['dismiss_action'])) : 'dismiss';

        if ($action === 'snooze') {
            // Snooze for 30 days
            update_user_meta($user_id, 'gswc_pro_notice_snoozed', time() + (30 * DAY_IN_SECONDS));
        } else {
            // Permanent dismiss
            update_user_meta($user_id, 'gswc_pro_notice_dismissed', '1');
        }

        wp_send_json_success();
    }

    /**
     * Add screen IDs for WooCommerce
     *
     * @param array $screen_ids Screen IDs.
     * @return array
     */
    public static function add_screen_ids($screen_ids) {
        $screen_ids[] = 'toplevel_page_gswc-dashboard';
        $screen_ids[] = 'product-feed_page_gswc-general';
        $screen_ids[] = 'product-feed_page_gswc-feeds';
        $screen_ids[] = 'product-feed_page_gswc-filters';
        $screen_ids[] = 'product-feed_page_gswc-customize';
        $screen_ids[] = 'product-feed_page_gswc-license';
        return $screen_ids;
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Get current page slug
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'gswc-general';

        // Map page slug to section ID
        $section_map = [
            'gswc-general'    => '',
            'gswc-feeds'      => 'feeds',
            'gswc-filters'    => 'filters',
            'gswc-customize' => 'customize',
        ];

        $current_section = $section_map[$page] ?? '';

        // Output settings page with wrapper
        echo '<div class="wrap">';
        GSWC_Settings::output_settings_page($current_section);
        echo '</div>';
    }

    /**
     * Render dashboard page
     */
    public static function render_dashboard() {
        include GSWC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Add dashboard widget
     */
    public static function add_dashboard_widget() {
        // Only show to users who can manage WooCommerce
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        wp_add_dashboard_widget(
            'gswc_dashboard_widget',
            __('Google Shopping Feed', 'gtin-product-feed-for-google-shopping'),
            [__CLASS__, 'render_dashboard_widget']
        );

        // Position widget at top-left on first run for this user
        self::maybe_position_widget();
    }

    /**
     * Position our widget at top-left on first run for each user
     */
    private static function maybe_position_widget() {
        $user_id = get_current_user_id();

        // Check if we've already positioned the widget for this user
        if (get_user_meta($user_id, 'gswc_widget_positioned', true)) {
            return;
        }

        // Get current dashboard widget order
        $order = get_user_meta($user_id, 'meta-box-order_dashboard', true);

        if (!is_array($order)) {
            $order = [];
        }

        // Ensure 'normal' column exists
        if (!isset($order['normal'])) {
            $order['normal'] = '';
        }

        // Get widgets in normal column as array
        $normal_widgets = array_filter(explode(',', $order['normal']));

        // Remove our widget if it's already there (to avoid duplicates)
        $normal_widgets = array_diff($normal_widgets, ['gswc_dashboard_widget']);

        // Add our widget at the beginning (top)
        array_unshift($normal_widgets, 'gswc_dashboard_widget');

        // Save back
        $order['normal'] = implode(',', $normal_widgets);
        update_user_meta($user_id, 'meta-box-order_dashboard', $order);

        // Mark as positioned so we don't override user's choice later
        update_user_meta($user_id, 'gswc_widget_positioned', '1');
    }

    /**
     * Render dashboard widget content
     */
    public static function render_dashboard_widget() {
        $feed_file = GSWC_Feed_Generator::get_feed_path('google');
        $feed_url = GSWC_Feed_Generator::get_feed_url('google');
        $feed_exists = file_exists($feed_file);
        $feed_enabled = get_option('gswc_feed_enabled', 'yes') === 'yes';
        $last_generated = get_option('gswc_feed_last_generated', 0);
        $product_count = get_option('gswc_feed_product_count', 0);

        // Count products modified since last feed generation
        $products_changed = 0;
        if ($last_generated > 0) {
            $last_generated_date = gmdate('Y-m-d H:i:s', $last_generated);
            $products_changed = (int) $GLOBALS['wpdb']->get_var(
                $GLOBALS['wpdb']->prepare(
                    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts}
                    WHERE post_type = 'product'
                    AND post_status = 'publish'
                    AND post_modified_gmt > %s",
                    $last_generated_date
                )
            );
        }

        ?>
        <div class="gswc-widget">
            <?php if (!$feed_enabled) : ?>
                <div class="gswc-widget-disabled">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Feed is disabled.', 'gtin-product-feed-for-google-shopping'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=gswc-feeds')); ?>">
                        <?php esc_html_e('Enable it', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="gswc-widget-stats">
                    <div class="gswc-widget-stat">
                        <span class="gswc-widget-stat-value"><?php echo esc_html($product_count ?: '—'); ?></span>
                        <span class="gswc-widget-stat-label"><?php esc_html_e('Products', 'gtin-product-feed-for-google-shopping'); ?></span>
                    </div>
                    <div class="gswc-widget-stat">
                        <span class="gswc-widget-stat-value <?php echo $feed_exists ? 'status-ok' : 'status-none'; ?>">
                            <?php echo $feed_exists ? '✓' : '—'; ?>
                        </span>
                        <span class="gswc-widget-stat-label"><?php esc_html_e('Feed', 'gtin-product-feed-for-google-shopping'); ?></span>
                    </div>
                    <div class="gswc-widget-stat">
                        <span class="gswc-widget-stat-value gswc-widget-stat-time">
                            <?php
                            if ($last_generated) {
                                echo esc_html(human_time_diff($last_generated, time()));
                            } else {
                                echo '—';
                            }
                            ?>
                        </span>
                        <span class="gswc-widget-stat-label"><?php esc_html_e('Last Update', 'gtin-product-feed-for-google-shopping'); ?></span>
                    </div>
                </div>

                <?php if ($products_changed > 0) : ?>
                    <div class="gswc-widget-stale">
                        <span class="gswc-widget-stale-icon">⚠️</span>
                        <span class="gswc-widget-stale-text">
                            <?php
                            printf(
                                /* translators: %d: number of products */
                                esc_html(_n(
                                    '%d product changed since last feed update',
                                    '%d products changed since last feed update',
                                    $products_changed,
                                    'gtin-product-feed-for-google-shopping'
                                )),
                                (int) $products_changed
                            );
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($feed_exists) : ?>
                    <div class="gswc-widget-url">
                        <input type="text" value="<?php echo esc_url($feed_url); ?>" readonly onclick="this.select();" />
                        <button type="button" class="button gswc-widget-copy" data-url="<?php echo esc_url($feed_url); ?>">
                            <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="gswc-widget-actions">
                    <button type="button" id="gswc-widget-generate" class="button button-primary">
                        <?php $feed_exists ? esc_html_e('Regenerate Feed', 'gtin-product-feed-for-google-shopping') : esc_html_e('Generate Feed', 'gtin-product-feed-for-google-shopping'); ?>
                    </button>
                    <span id="gswc-widget-spinner" class="spinner"></span>
                    <span id="gswc-widget-result"></span>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=gswc-dashboard')); ?>" class="gswc-widget-settings">
                        <?php esc_html_e('Settings', 'gtin-product-feed-for-google-shopping'); ?> →
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
