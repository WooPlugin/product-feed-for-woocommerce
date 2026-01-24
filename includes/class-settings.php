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
        $tabs['gswc_feed'] = __('Google Shopping', 'gtin-product-feed-for-google-shopping');
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
        // Show license activation if Pro not active
        if (!GSWC_Pro_Upgrader::is_pro_active()) {
            self::output_license_activation();
        }

        $promotion = GSWC_Remote_Data::get_promotion();
        $pro = GSWC_Remote_Data::get_pro_pricing();
        $features = GSWC_Remote_Data::get_pro_features();

        // Output promotion if active
        if ($promotion) {
            self::output_promotion($promotion);
        }

        // Output Pro upsell (only if Pro not active)
        if (!GSWC_Pro_Upgrader::is_pro_active()) {
            self::output_pro_upsell($pro, $features);
        }
    }

    /**
     * Output license activation form
     */
    private static function output_license_activation() {
        if (GSWC_Pro_Upgrader::is_pro_installed()) {
            ?>
            <div class="gswc-sidebar-card gswc-license-card">
                <h3><?php esc_html_e('Pro Installed', 'gtin-product-feed-for-google-shopping'); ?></h3>
                <p><?php esc_html_e('Pro is installed but not active.', 'gtin-product-feed-for-google-shopping'); ?></p>
                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-primary">
                    <?php esc_html_e('Activate Pro', 'gtin-product-feed-for-google-shopping'); ?>
                </a>
            </div>
            <?php
            return;
        }

        $nonce = wp_create_nonce('gswc_pro_upgrade');
        ?>
        <div class="gswc-sidebar-card gswc-license-card">
            <h3><?php esc_html_e('Have a License?', 'gtin-product-feed-for-google-shopping'); ?></h3>
            <p><?php esc_html_e('Enter your license key to install Pro automatically.', 'gtin-product-feed-for-google-shopping'); ?></p>

            <input type="text"
                   id="gswc-license-key-sidebar"
                   class="gswc-license-input-sidebar"
                   placeholder="<?php esc_attr_e('License key...', 'gtin-product-feed-for-google-shopping'); ?>" />
            <button type="button" id="gswc-validate-license-sidebar" class="button button-primary">
                <?php esc_html_e('Install Pro', 'gtin-product-feed-for-google-shopping'); ?>
            </button>

            <div id="gswc-upgrade-status-sidebar" class="gswc-upgrade-status-sidebar"></div>
        </div>

        <script>
        (function() {
            var validateBtn = document.getElementById('gswc-validate-license-sidebar');
            var licenseInput = document.getElementById('gswc-license-key-sidebar');
            var status = document.getElementById('gswc-upgrade-status-sidebar');

            if (!validateBtn) return;

            validateBtn.addEventListener('click', function() {
                var licenseKey = licenseInput.value.trim();
                if (!licenseKey) {
                    showStatus('error', '<?php echo esc_js(__('Please enter a license key.', 'gtin-product-feed-for-google-shopping')); ?>');
                    return;
                }

                showStatus('loading', '<?php echo esc_js(__('Validating...', 'gtin-product-feed-for-google-shopping')); ?>');
                validateBtn.disabled = true;

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
                    if (!data.success) throw new Error(data.data.message);

                    showStatus('loading', '<?php echo esc_js(__('Installing Pro...', 'gtin-product-feed-for-google-shopping')); ?>');

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
                    if (!data.success) throw new Error(data.data.message);

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
                status.className = 'gswc-upgrade-status-sidebar ' + type;
                if (type === 'loading') {
                    status.innerHTML = '<span class="spinner is-active"></span> ' + message;
                } else {
                    status.textContent = message;
                }
            }
        })();
        </script>
        <?php
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
                <?php echo esc_html($promotion['title'] ?? __('Special Offer', 'gtin-product-feed-for-google-shopping')); ?>
            </div>
            <p class="gswc-promo-message">
                <?php echo esc_html($promotion['message']); ?>
            </p>
            <?php if (!empty($promotion['code'])) : ?>
                <div class="gswc-promo-code">
                    <span class="code"><?php echo esc_html($promotion['code']); ?></span>
                    <button type="button" class="gswc-copy-code" data-code="<?php echo esc_attr($promotion['code']); ?>">
                        <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                    </button>
                </div>
            <?php endif; ?>
            <?php if (!empty($promotion['expires'])) : ?>
                <p class="gswc-promo-expires">
                    <?php
                    printf(
                        /* translators: %s: expiration date */
                        esc_html__('Expires: %s', 'gtin-product-feed-for-google-shopping'),
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
            <h3><?php esc_html_e('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'); ?></h3>

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
                <?php esc_html_e('Get Pro', 'gtin-product-feed-for-google-shopping'); ?>
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
                'title' => __('Feed Settings', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Configure your Google Shopping product feed.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_feed_general',
            ],
            [
                'title'   => __('Include Out of Stock', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Include out of stock products in the feed', 'gtin-product-feed-for-google-shopping'),
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
                'title' => __('Google Merchant Center', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Settings for your Google Shopping feed.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_feed_google',
            ],
            [
                'title'       => __('Store Name', 'gtin-product-feed-for-google-shopping'),
                'desc'        => __('Your store name as it appears in the feed.', 'gtin-product-feed-for-google-shopping'),
                'id'          => 'gswc_feed_store_name',
                'type'        => 'text',
                'default'     => get_bloginfo('name'),
                'placeholder' => get_bloginfo('name'),
            ],
            [
                'title'   => __('Default Brand', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Default brand for products without a brand set.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_default_brand',
                'type'    => 'text',
                'default' => '',
            ],
            [
                'title'   => __('Default Condition', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_default_condition',
                'type'    => 'select',
                'options' => [
                    'new'         => __('New', 'gtin-product-feed-for-google-shopping'),
                    'refurbished' => __('Refurbished', 'gtin-product-feed-for-google-shopping'),
                    'used'        => __('Used', 'gtin-product-feed-for-google-shopping'),
                ],
                'default' => 'new',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_feed_google',
            ],

            // Feed Status Section
            [
                'title' => __('Feed Status', 'gtin-product-feed-for-google-shopping'),
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
                <?php esc_html_e('Feed URL', 'gtin-product-feed-for-google-shopping'); ?>
            </th>
            <td class="forminp">
                <?php if ($feed_exists) : ?>
                    <div class="gswc-feed-url-row">
                        <input type="text" class="gswc-feed-url-input" value="<?php echo esc_url($feed_url); ?>" readonly onclick="this.select();" />
                        <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e('Open feed', 'gtin-product-feed-for-google-shopping'); ?>">↗</a>
                        <button type="button" class="button button-small gswc-copy-url" data-url="<?php echo esc_attr($feed_url); ?>">
                            <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %1$s: product count, %2$s: date */
                            esc_html__('Last generated: %1$s products on %2$s', 'gtin-product-feed-for-google-shopping'),
                            '<strong>' . esc_html($product_count) . '</strong>',
                            '<strong>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_generated)) . '</strong>'
                        );
                        ?>
                    </p>
                <?php else : ?>
                    <p class="description">
                        <?php esc_html_e('Feed not yet generated. Click "Generate Feed Now" below.', 'gtin-product-feed-for-google-shopping'); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php esc_html_e('Actions', 'gtin-product-feed-for-google-shopping'); ?>
            </th>
            <td class="forminp">
                <button type="button" id="gswc-generate-feed" class="button button-primary">
                    <?php esc_html_e('Generate Feed Now', 'gtin-product-feed-for-google-shopping'); ?>
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

            /* License activation styles */
            .gswc-license-card {
                background: linear-gradient(135deg, #f0f6ff 0%, #e0f2fe 100%);
                border-color: #0284c7;
            }

            .gswc-license-card h3 {
                margin: 0 0 8px 0;
                font-size: 15px;
                color: #0369a1;
            }

            .gswc-license-card p {
                margin: 0 0 12px 0;
                font-size: 13px;
                color: #475569;
            }

            .gswc-license-input-sidebar {
                width: 100%;
                padding: 8px 10px;
                margin-bottom: 10px;
                border: 1px solid #cbd5e1;
                border-radius: 4px;
                font-size: 13px;
                box-sizing: border-box;
            }

            .gswc-license-card .button {
                width: 100%;
            }

            .gswc-upgrade-status-sidebar {
                margin-top: 10px;
                padding: 8px;
                border-radius: 4px;
                font-size: 12px;
                display: none;
            }

            .gswc-upgrade-status-sidebar.loading,
            .gswc-upgrade-status-sidebar.success,
            .gswc-upgrade-status-sidebar.error {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .gswc-upgrade-status-sidebar.loading {
                background: #e0f2fe;
                color: #075985;
            }

            .gswc-upgrade-status-sidebar.success {
                background: #dcfce7;
                color: #166534;
            }

            .gswc-upgrade-status-sidebar.error {
                background: #fee2e2;
                color: #991b1b;
            }

            .gswc-upgrade-status-sidebar .spinner {
                float: none;
                margin: 0;
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
                            btn.textContent = '<?php echo esc_js(__('Copied!', 'gtin-product-feed-for-google-shopping')); ?>';
                            setTimeout(function() {
                                btn.textContent = '<?php echo esc_js(__('Copy', 'gtin-product-feed-for-google-shopping')); ?>';
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
