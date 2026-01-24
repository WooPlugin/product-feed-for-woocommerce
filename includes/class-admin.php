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
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_filter('woocommerce_screen_ids', [__CLASS__, 'add_screen_ids']);
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_scripts($hook) {
        $screen = get_current_screen();

        // Load on WooCommerce settings page (our tab), product pages, dashboard, and our dashboard
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_our_settings = $hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'gswc_feed';
        $is_product_page = $screen && ($screen->post_type === 'product' || $screen->id === 'product');
        $is_our_dashboard = $hook === 'woocommerce_page_gswc-dashboard';
        $is_wp_dashboard = $hook === 'index.php'; // Main WordPress dashboard

        if (!$is_our_settings && !$is_product_page && !$is_our_dashboard && !$is_wp_dashboard) {
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
     * Add admin menu for dashboard
     */
    public static function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('Google Shopping', 'gtin-product-feed-for-google-shopping'),
            __('Google Shopping', 'gtin-product-feed-for-google-shopping'),
            'manage_woocommerce',
            'gswc-dashboard',
            [__CLASS__, 'render_dashboard']
        );
    }

    /**
     * Add screen IDs for WooCommerce
     *
     * @param array $screen_ids Screen IDs.
     * @return array
     */
    public static function add_screen_ids($screen_ids) {
        $screen_ids[] = 'woocommerce_page_gswc-dashboard';
        return $screen_ids;
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
        $last_generated = get_option('gswc_feed_last_generated', 0);
        $product_count = get_option('gswc_feed_product_count', 0);
        $promotion = GSWC_Remote_Data::get_promotion();
        $pro = GSWC_Remote_Data::get_pro_pricing();

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
            <?php if ($promotion) : ?>
                <div class="gswc-widget-promo gswc-widget-promo-<?php echo esc_attr($promotion['style'] ?? 'highlight'); ?>">
                    <strong><?php echo esc_html($promotion['title'] ?? __('Special Offer', 'gtin-product-feed-for-google-shopping')); ?></strong>
                    <span><?php echo esc_html($promotion['message']); ?></span>
                    <?php if (!empty($promotion['code'])) : ?>
                        <code><?php echo esc_html($promotion['code']); ?></code>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
                            $products_changed
                        );
                        ?>
                    </span>
                    <a href="<?php echo esc_url($pro['url']); ?>" target="_blank" class="gswc-widget-stale-link">
                        <?php esc_html_e('Auto-update with Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
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

                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=gswc_feed')); ?>" class="gswc-widget-settings">
                    <?php esc_html_e('Settings', 'gtin-product-feed-for-google-shopping'); ?> →
                </a>
            </div>

            <div class="gswc-widget-upsell">
                <p>
                    <strong><?php esc_html_e('Need automation?', 'gtin-product-feed-for-google-shopping'); ?></strong>
                    <?php esc_html_e('Pro auto-updates your feed + adds Facebook, Pinterest, TikTok, Snapchat.', 'gtin-product-feed-for-google-shopping'); ?>
                </p>
                <a href="<?php echo esc_url($pro['url']); ?>" target="_blank" class="gswc-widget-pro-link">
                    <?php
                    printf(
                        /* translators: %s: price */
                        esc_html__('Get Pro - %s', 'gtin-product-feed-for-google-shopping'),
                        esc_html($pro['display'])
                    );
                    ?>
                </a>
            </div>
        </div>

        <style>
            .gswc-widget {
                margin: -12px;
            }

            .gswc-widget-promo {
                padding: 12px;
                margin-bottom: 0;
                border-bottom: 1px solid #e0e0e0;
                font-size: 13px;
            }

            .gswc-widget-promo strong {
                display: block;
                margin-bottom: 4px;
            }

            .gswc-widget-promo code {
                display: inline-block;
                margin-top: 6px;
                padding: 2px 8px;
                background: #fff;
                border-radius: 3px;
                font-weight: 600;
            }

            .gswc-widget-promo-highlight {
                background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            }

            .gswc-widget-promo-urgent {
                background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            }

            .gswc-widget-promo-subtle {
                background: #fef9c3;
            }

            .gswc-widget-stats {
                display: flex;
                border-bottom: 1px solid #e0e0e0;
            }

            .gswc-widget-stat {
                flex: 1;
                text-align: center;
                padding: 16px 8px;
                border-right: 1px solid #e0e0e0;
            }

            .gswc-widget-stat:last-child {
                border-right: none;
            }

            .gswc-widget-stat-value {
                display: block;
                font-size: 24px;
                font-weight: 600;
                color: #1e1e1e;
                line-height: 1.2;
            }

            .gswc-widget-stat-value.status-ok {
                color: #16a34a;
            }

            .gswc-widget-stat-value.status-none {
                color: #9ca3af;
            }

            .gswc-widget-stat-time {
                font-size: 16px;
            }

            .gswc-widget-stale {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 12px;
                background: #fef3c7;
                border-bottom: 1px solid #e0e0e0;
                font-size: 12px;
            }

            .gswc-widget-stale-icon {
                flex-shrink: 0;
            }

            .gswc-widget-stale-text {
                flex: 1;
                color: #92400e;
            }

            .gswc-widget-stale-link {
                flex-shrink: 0;
                color: #4285f4;
                text-decoration: none;
                font-weight: 500;
            }

            .gswc-widget-stale-link:hover {
                text-decoration: underline;
            }

            .gswc-widget-stat-label {
                display: block;
                font-size: 11px;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-top: 4px;
            }

            .gswc-widget-url {
                display: flex;
                padding: 12px;
                gap: 8px;
                border-bottom: 1px solid #e0e0e0;
            }

            .gswc-widget-url input {
                flex: 1;
                font-size: 12px;
                padding: 4px 8px;
            }

            .gswc-widget-actions {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px;
                border-bottom: 1px solid #e0e0e0;
            }

            .gswc-widget-actions .spinner {
                float: none;
                margin: 0;
            }

            .gswc-widget-settings {
                margin-left: auto;
                text-decoration: none;
                font-size: 13px;
            }

            #gswc-widget-result {
                font-size: 13px;
            }

            #gswc-widget-result.success {
                color: #16a34a;
            }

            #gswc-widget-result.error {
                color: #dc2626;
            }

            .gswc-widget-upsell {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: linear-gradient(135deg, #f0f6ff 0%, #e8f5e9 100%);
                font-size: 13px;
            }

            .gswc-widget-upsell p {
                flex: 1;
                margin: 0;
            }

            .gswc-widget-pro-link {
                flex-shrink: 0;
                display: inline-block;
                padding: 6px 12px;
                background: linear-gradient(135deg, #4285f4, #34a853);
                color: #fff;
                text-decoration: none;
                border-radius: 4px;
                font-weight: 600;
                font-size: 12px;
            }

            .gswc-widget-pro-link:hover {
                color: #fff;
                opacity: 0.9;
            }
        </style>
        <?php
    }
}
