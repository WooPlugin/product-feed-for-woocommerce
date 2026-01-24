<?php
/**
 * WooCommerce Settings Integration
 *
 * Adds a settings tab in WooCommerce > Settings > Google Shopping
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Settings
 */
class GSWC_Settings {

    /**
     * Initialize settings
     */
    public static function init() {
        add_filter('woocommerce_settings_tabs_array', [__CLASS__, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_gswc_feed', [__CLASS__, 'output_settings']);
        add_action('woocommerce_update_options_gswc_feed', [__CLASS__, 'save_settings']);
        add_action('woocommerce_admin_field_gswc_feed_status', [__CLASS__, 'output_status_field']);
        add_action('admin_footer', [__CLASS__, 'output_sidebar_styles']);
    }

    /**
     * Add settings tab
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public static function add_settings_tab($tabs) {
        $tabs['gswc_feed'] = __('Google Shopping', 'product-feed-for-woocommerce');
        return $tabs;
    }

    /**
     * Output settings fields with sidebar layout
     */
    public static function output_settings() {
        ?>
        <div class="gswc-settings-wrapper">
            <div class="gswc-settings-main">
                <?php woocommerce_admin_fields(self::get_settings()); ?>
            </div>
            <div class="gswc-settings-sidebar">
                <?php self::output_sidebar(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Output sidebar content (promotions + Pro upsell)
     */
    private static function output_sidebar() {
        $promotion = GSWC_Remote_Data::get_promotion();
        $pro = GSWC_Remote_Data::get_pro_pricing();
        $features = GSWC_Remote_Data::get_pro_features();

        // Output promotion if active
        if ($promotion) {
            self::output_promotion($promotion);
        }

        // Output Pro upsell
        self::output_pro_upsell($pro, $features);
    }

    /**
     * Output promotion banner
     *
     * @param array $promotion Promotion data.
     */
    private static function output_promotion($promotion) {
        $style_class = 'gswc-promo-' . ($promotion['style'] ?? 'highlight');
        ?>
        <div class="gswc-sidebar-card gswc-promo <?php echo esc_attr($style_class); ?>">
            <div class="gswc-promo-badge">
                <?php echo esc_html($promotion['title'] ?? __('Special Offer', 'product-feed-for-woocommerce')); ?>
            </div>
            <p class="gswc-promo-message">
                <?php echo esc_html($promotion['message']); ?>
            </p>
            <?php if (!empty($promotion['code'])) : ?>
                <div class="gswc-promo-code">
                    <span class="code"><?php echo esc_html($promotion['code']); ?></span>
                    <button type="button" class="gswc-copy-code" data-code="<?php echo esc_attr($promotion['code']); ?>">
                        <?php esc_html_e('Copy', 'product-feed-for-woocommerce'); ?>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (!empty($promotion['expires'])) : ?>
                <p class="gswc-promo-expires">
                    <?php
                    printf(
                        /* translators: %s: expiration date */
                        esc_html__('Expires: %s', 'product-feed-for-woocommerce'),
                        esc_html(wp_date(get_option('date_format'), strtotime($promotion['expires'])))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Output Pro upsell card
     *
     * @param array $pro      Pro pricing data.
     * @param array $features Pro features list.
     */
    private static function output_pro_upsell($pro, $features) {
        ?>
        <div class="gswc-sidebar-card gswc-pro-upsell">
            <h3><?php esc_html_e('Upgrade to Pro', 'product-feed-for-woocommerce'); ?></h3>

            <div class="gswc-pro-price">
                <span class="price"><?php echo esc_html($pro['display']); ?></span>
                <span class="period"><?php echo esc_html($pro['period']); ?></span>
            </div>

            <ul class="gswc-pro-features">
                <?php foreach ($features as $feature) : ?>
                    <li><?php echo esc_html($feature); ?></li>
                <?php endforeach; ?>
            </ul>

            <a href="<?php echo esc_url($pro['url']); ?>" class="gswc-pro-button" target="_blank">
                <?php esc_html_e('Get Pro', 'product-feed-for-woocommerce'); ?>
                <span class="dashicons dashicons-external"></span>
            </a>
        </div>
        <?php
    }

    /**
     * Save settings
     */
    public static function save_settings() {
        woocommerce_update_options(self::get_settings());
    }

    /**
     * Get settings array
     *
     * @return array
     */
    public static function get_settings() {
        $settings = [
            // General Section
            [
                'title' => __('Feed Settings', 'product-feed-for-woocommerce'),
                'type'  => 'title',
                'desc'  => __('Configure your Google Shopping product feed.', 'product-feed-for-woocommerce'),
                'id'    => 'gswc_feed_general',
            ],
            [
                'title'   => __('Include Out of Stock', 'product-feed-for-woocommerce'),
                'desc'    => __('Include out of stock products in the feed', 'product-feed-for-woocommerce'),
                'id'      => 'gswc_feed_include_outofstock',
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_feed_general',
            ],

            // Google Feed Section
            [
                'title' => __('Google Merchant Center', 'product-feed-for-woocommerce'),
                'type'  => 'title',
                'desc'  => __('Settings for your Google Shopping feed.', 'product-feed-for-woocommerce'),
                'id'    => 'gswc_feed_google',
            ],
            [
                'title'       => __('Store Name', 'product-feed-for-woocommerce'),
                'desc'        => __('Your store name as it appears in the feed.', 'product-feed-for-woocommerce'),
                'id'          => 'gswc_feed_store_name',
                'type'        => 'text',
                'default'     => get_bloginfo('name'),
                'placeholder' => get_bloginfo('name'),
            ],
            [
                'title'   => __('Default Brand', 'product-feed-for-woocommerce'),
                'desc'    => __('Default brand for products without a brand set.', 'product-feed-for-woocommerce'),
                'id'      => 'gswc_feed_default_brand',
                'type'    => 'text',
                'default' => '',
            ],
            [
                'title'   => __('Default Condition', 'product-feed-for-woocommerce'),
                'id'      => 'gswc_feed_default_condition',
                'type'    => 'select',
                'options' => [
                    'new'         => __('New', 'product-feed-for-woocommerce'),
                    'refurbished' => __('Refurbished', 'product-feed-for-woocommerce'),
                    'used'        => __('Used', 'product-feed-for-woocommerce'),
                ],
                'default' => 'new',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_feed_google',
            ],

            // Feed Status Section
            [
                'title' => __('Feed Status', 'product-feed-for-woocommerce'),
                'type'  => 'title',
                'id'    => 'gswc_feed_status_section',
            ],
            [
                'type' => 'gswc_feed_status',
                'id'   => 'gswc_feed_status',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_feed_status_section',
            ],
        ];

        return apply_filters('gswc_feed_settings', $settings);
    }

    /**
     * Output feed status field
     *
     * @param array $value Field value.
     */
    public static function output_status_field($value) {
        $feed_url = GSWC_Feed_Generator::get_feed_url('google');
        $feed_file = GSWC_Feed_Generator::get_feed_path('google');
        $feed_exists = file_exists($feed_file);
        $last_generated = get_option('gswc_feed_last_generated', 0);
        $product_count = get_option('gswc_feed_product_count', 0);
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php esc_html_e('Feed URL', 'product-feed-for-woocommerce'); ?>
            </th>
            <td class="forminp">
                <?php if ($feed_exists) : ?>
                    <div class="gswc-feed-url-row">
                        <input type="text" class="gswc-feed-url-input" value="<?php echo esc_url($feed_url); ?>" readonly onclick="this.select();" />
                        <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e('Open feed', 'product-feed-for-woocommerce'); ?>">↗</a>
                        <button type="button" class="button button-small gswc-copy-url" data-url="<?php echo esc_attr($feed_url); ?>">
                            <?php esc_html_e('Copy', 'product-feed-for-woocommerce'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %1$s: product count, %2$s: date */
                            esc_html__('Last generated: %1$s products on %2$s', 'product-feed-for-woocommerce'),
                            '<strong>' . esc_html($product_count) . '</strong>',
                            '<strong>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_generated)) . '</strong>'
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <p class="description">
                        <?php esc_html_e('Feed not yet generated. Click "Generate Feed Now" below.', 'product-feed-for-woocommerce'); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php esc_html_e('Actions', 'product-feed-for-woocommerce'); ?>
            </th>
            <td class="forminp">
                <button type="button" id="gswc-generate-feed" class="button button-primary">
                    <?php esc_html_e('Generate Feed Now', 'product-feed-for-woocommerce'); ?>
                </button>
                <span id="gswc-feed-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                <span id="gswc-feed-result"></span>
            </td>
        </tr>
        <?php
    }

    /**
     * Output sidebar styles
     */
    public static function output_sidebar_styles() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'woocommerce_page_wc-settings') {
            return;
        }

        // Only on our tab
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'gswc_feed') {
            return;
        }
        ?>
        <style>
            .gswc-settings-wrapper {
                display: grid;
                grid-template-columns: 1fr 300px;
                gap: 24px;
                align-items: start;
                margin-top: 20px;
            }

            .gswc-settings-main {
                min-width: 0;
            }

            .gswc-settings-main .form-table {
                margin-top: 0;
            }

            .gswc-settings-sidebar {
                position: sticky;
                top: 32px;
            }

            .gswc-sidebar-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 16px;
            }

            /* Promotion styles */
            .gswc-promo {
                background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                border-color: #f59e0b;
            }

            .gswc-promo-highlight {
                background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                border-color: #3b82f6;
            }

            .gswc-promo-urgent {
                background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
                border-color: #ef4444;
            }

            .gswc-promo-badge {
                display: inline-block;
                background: #f59e0b;
                color: #fff;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                padding: 4px 8px;
                border-radius: 4px;
                margin-bottom: 12px;
            }

            .gswc-promo-highlight .gswc-promo-badge {
                background: #3b82f6;
            }

            .gswc-promo-urgent .gswc-promo-badge {
                background: #ef4444;
            }

            .gswc-promo-message {
                margin: 0 0 12px 0;
                font-size: 14px;
                color: #1f2937;
                font-weight: 500;
            }

            .gswc-promo-code {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #fff;
                border-radius: 4px;
                padding: 8px 12px;
                margin-bottom: 8px;
            }

            .gswc-promo-code .code {
                font-family: monospace;
                font-size: 14px;
                font-weight: 600;
                color: #1f2937;
            }

            .gswc-copy-code {
                background: #e5e7eb;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                cursor: pointer;
            }

            .gswc-copy-code:hover {
                background: #d1d5db;
            }

            .gswc-promo-expires {
                margin: 0;
                font-size: 12px;
                color: #6b7280;
            }

            /* Pro upsell styles */
            .gswc-pro-upsell {
                background: linear-gradient(135deg, #f0f6ff 0%, #e8f5e9 100%);
                border-color: #4285f4;
            }

            .gswc-pro-upsell h3 {
                margin: 0 0 16px 0;
                font-size: 16px;
                color: #1a73e8;
            }

            .gswc-pro-price {
                margin-bottom: 16px;
            }

            .gswc-pro-price .price {
                font-size: 28px;
                font-weight: 700;
                color: #1f2937;
            }

            .gswc-pro-price .period {
                display: block;
                font-size: 13px;
                color: #6b7280;
                margin-top: 2px;
            }

            .gswc-pro-features {
                margin: 0 0 20px 0;
                padding: 0;
                list-style: none;
            }

            .gswc-pro-features li {
                position: relative;
                padding-left: 20px;
                margin-bottom: 8px;
                font-size: 13px;
                color: #374151;
            }

            .gswc-pro-features li::before {
                content: '✓';
                position: absolute;
                left: 0;
                color: #16a34a;
                font-weight: 600;
            }

            .gswc-pro-button {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                width: 100%;
                padding: 12px 20px;
                box-sizing: border-box;
                background: linear-gradient(135deg, #4285f4, #34a853);
                color: #fff;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 600;
                font-size: 14px;
                transition: opacity 0.2s;
            }

            .gswc-pro-button:hover {
                opacity: 0.9;
                color: #fff;
            }

            .gswc-pro-button .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            @media screen and (max-width: 1200px) {
                .gswc-settings-wrapper {
                    grid-template-columns: 1fr;
                }

                .gswc-settings-sidebar {
                    position: static;
                    max-width: 400px;
                }
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Copy promo code functionality
                document.querySelectorAll('.gswc-copy-code').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var code = this.getAttribute('data-code');
                        navigator.clipboard.writeText(code).then(function() {
                            btn.textContent = '<?php echo esc_js(__('Copied!', 'product-feed-for-woocommerce')); ?>';
                            setTimeout(function() {
                                btn.textContent = '<?php echo esc_js(__('Copy', 'product-feed-for-woocommerce')); ?>';
                            }, 2000);
                        });
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Get option value with default
     *
     * @param string $key     Option key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get($key, $default = '') {
        return get_option('gswc_feed_' . $key, $default);
    }
}
