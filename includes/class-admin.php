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

        // Debug: Test if class is being loaded
        error_log('GSWC: Class initialized, hook added');
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
        $our_pages = ['gswc-dashboard', 'gswc-general', 'gswc-customize', 'gswc-feeds', 'gswc-filters'];
        $is_our_page_by_param = in_array($page, $our_pages, true);
        $is_our_page = $is_our_page_by_hook || $is_our_page_by_param;

        // Also load on product pages and WooCommerce pages
        $screen = get_current_screen();
        $is_product_page = $screen && ($screen->post_type === 'product' || $screen->id === 'product');
        $is_wp_dashboard = $hook === 'index.php';
        $is_woocommerce_page = $screen && strpos($screen->id, 'woocommerce') !== false;

        if (!$is_our_page && !$is_product_page && !$is_wp_dashboard && !$is_woocommerce_page) {
            return;
        }

        wp_enqueue_style(
            'gswc-admin',
            GSWC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            GSWC_VERSION
        );

        wp_enqueue_script(
            'gswc-admin',
            GSWC_PLUGIN_URL . 'admin/js/admin.js',
            [],
            GSWC_VERSION,
            true
        );

        wp_localize_script('gswc-admin', 'gswcFeed', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('gswc_feed_nonce'),
            'strings' => [
                'generating' => __('Generating...', 'gtin-product-feed-for-google-shopping'),
                'success'    => __('Feed generated successfully!', 'gtin-product-feed-for-google-shopping'),
                'error'      => __('Error:', 'gtin-product-feed-for-google-shopping'),
            ],
        ]);
    }

    /**
     * Add admin menu
     */
    public static function add_menu() {
        // Top-level menu
        add_menu_page(
            __('GTIN Product Feed', 'gtin-product-feed-for-google-shopping'),
            __('GTIN Product Feed', 'gtin-product-feed-for-google-shopping'),
            'manage_woocommerce',
            'gswc-dashboard',
            [__CLASS__, 'render_dashboard'],
            'dashicons-rss',
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
    }

    /**
     * Add screen IDs for WooCommerce
     *
     * @param array $screen_ids Screen IDs.
     * @return array
     */
    public static function add_screen_ids($screen_ids) {
        $screen_ids[] = 'toplevel_page_gswc-dashboard';
        $screen_ids[] = 'gtin-product-feed_page_gswc-general';
        $screen_ids[] = 'gtin-product-feed_page_gswc-feeds';
        $screen_ids[] = 'gtin-product-feed_page_gswc-filters';
        $screen_ids[] = 'gtin-product-feed_page_gswc-customize';
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
