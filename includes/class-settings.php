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
     * Current section
     *
     * @var string
     */
    private static $current_section = '';

    /**
     * Initialize settings
     */
    public static function init() {
        add_filter('woocommerce_settings_tabs_array', [__CLASS__, 'add_settings_tab'], 50);
        add_action('woocommerce_sections_gswc_feed', [__CLASS__, 'output_sections']);
        add_action('woocommerce_settings_tabs_gswc_feed', [__CLASS__, 'output_settings']);
        add_action('woocommerce_update_options_gswc_feed', [__CLASS__, 'save_settings']);
        add_action('woocommerce_admin_field_gswc_feed_status', [__CLASS__, 'output_status_field']);
        add_action('woocommerce_admin_field_gswc_feeds_table', [__CLASS__, 'output_feeds_table']);
        add_action('woocommerce_admin_field_gswc_pro_locked', [__CLASS__, 'output_pro_locked']);
        add_action('woocommerce_admin_field_gswc_license_field', [__CLASS__, 'output_license_field']);
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
     * Get sections
     *
     * @return array
     */
    public static function get_sections() {
        return [
            ''           => __('General', 'gtin-product-feed-for-google-shopping'),
            'feeds'      => __('Feeds', 'gtin-product-feed-for-google-shopping'),
            'categories' => __('Category Mapping', 'gtin-product-feed-for-google-shopping'),
            'schedule'   => __('Schedule', 'gtin-product-feed-for-google-shopping'),
            'filters'    => __('Filters', 'gtin-product-feed-for-google-shopping'),
            'customize'  => __('Customization', 'gtin-product-feed-for-google-shopping'),
            'license'    => __('License', 'gtin-product-feed-for-google-shopping'),
        ];
    }

    /**
     * Output sections
     */
    public static function output_sections() {
        global $current_section;

        $sections = self::get_sections();

        if (empty($sections) || 1 === count($sections)) {
            return;
        }

        echo '<div class="gswc-sections-card">';
        echo '<ul class="gswc-sections-nav">';

        foreach ($sections as $id => $label) {
            $url = admin_url('admin.php?page=wc-settings&tab=gswc_feed&section=' . $id);
            $current = ($current_section === $id) ? 'current' : '';

            // Add Pro badge for locked sections
            $pro_badge = '';
            if (in_array($id, ['categories', 'schedule'], true)) {
                $pro_badge = ' <span class="gswc-pro-badge-small">Pro</span>';
            }

            echo '<li><a href="' . esc_url($url) . '" class="' . esc_attr($current) . '">' . esc_html($label) . $pro_badge . '</a></li>';
        }

        echo '</ul>';
        echo '</div>';
    }

    /**
     * Output settings fields with sidebar layout
     */
    public static function output_settings() {
        global $current_section;
        self::$current_section = $current_section;

        ?>
        <div class="gswc-settings-wrapper">
            <div class="gswc-settings-main">
                <div class="gswc-content-card">
                    <?php
                    $settings = self::get_settings_for_section($current_section);
                    woocommerce_admin_fields($settings);
                    ?>
                </div>
            </div>
            <div class="gswc-settings-sidebar">
                <?php self::output_sidebar(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get settings for current section
     *
     * @param string $section Section ID.
     * @return array
     */
    private static function get_settings_for_section($section) {
        switch ($section) {
            case 'feeds':
                return self::get_feeds_settings();
            case 'categories':
                return self::get_categories_settings();
            case 'schedule':
                return self::get_schedule_settings();
            case 'filters':
                return self::get_filters_settings();
            case 'customize':
                return self::get_customize_settings();
            case 'license':
                return self::get_license_settings();
            default:
                return self::get_general_settings();
        }
    }

    /**
     * Get general settings
     *
     * @return array
     */
    private static function get_general_settings() {
        return [
            [
                'title' => __('General Settings', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Configure your product feed settings.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_feed_general',
            ],
            [
                'title'       => __('Store Name', 'gtin-product-feed-for-google-shopping'),
                'desc'        => __('Your store name as it appears in feeds.', 'gtin-product-feed-for-google-shopping'),
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
                'title'   => __('Include Out of Stock', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Include out of stock products in feeds', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_include_outofstock',
                'type'    => 'checkbox',
                'default' => 'no',
            ],
            [
                'title'             => __('Auto-fill Fields', 'gtin-product-feed-for-google-shopping'),
                'desc'              => __('Automatically suggest field values from product attributes, categories, and title when editing products', 'gtin-product-feed-for-google-shopping') . ' <span class="gswc-pro-badge"><span class="dashicons dashicons-lock"></span> ' . __('Pro', 'gtin-product-feed-for-google-shopping') . '</span>',
                'id'                => 'gswc_pro_auto_fill',
                'type'              => 'checkbox',
                'default'           => 'no',
                'custom_attributes' => ['disabled' => 'disabled'],
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_feed_general',
            ],
        ];
    }

    /**
     * Get feeds settings
     *
     * @return array
     */
    private static function get_feeds_settings() {
        return [
            [
                'title' => __('Feed Channels', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Enable and manage your product feeds.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_feeds_section',
            ],
            [
                'type' => 'gswc_feeds_table',
                'id'   => 'gswc_feeds_table',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_feeds_section',
            ],
        ];
    }

    /**
     * Get categories settings (Pro locked)
     *
     * @return array
     */
    private static function get_categories_settings() {
        return [
            [
                'type'    => 'gswc_pro_locked',
                'id'      => 'gswc_categories_locked',
                'feature' => 'categories',
            ],
        ];
    }

    /**
     * Get schedule settings (Pro locked)
     *
     * @return array
     */
    private static function get_schedule_settings() {
        return [
            [
                'type'    => 'gswc_pro_locked',
                'id'      => 'gswc_schedule_locked',
                'feature' => 'schedule',
            ],
        ];
    }

    /**
     * Get filters settings
     *
     * @return array
     */
    private static function get_filters_settings() {
        // Get categories for multiselect
        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);
        $category_options = [];
        if (!is_wp_error($categories)) {
            foreach ($categories as $cat) {
                $category_options[$cat->term_id] = $cat->name;
            }
        }

        // Get tags for multiselect
        $tags = get_terms([
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ]);
        $tag_options = [];
        if (!is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $tag_options[$tag->term_id] = $tag->name;
            }
        }

        return [
            [
                'title' => __('Product Filters', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Filter which products are included in feeds.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_filters_section',
            ],
            [
                'title'   => __('Exclude Categories', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Products in these categories will be excluded from feeds.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_exclude_categories',
                'type'    => 'multiselect',
                'class'   => 'wc-enhanced-select',
                'options' => $category_options,
                'default' => [],
            ],
            [
                'title'   => __('Exclude Tags', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Products with these tags will be excluded from feeds.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_exclude_tags',
                'type'    => 'multiselect',
                'class'   => 'wc-enhanced-select',
                'options' => $tag_options,
                'default' => [],
            ],
            [
                'title'             => __('Minimum Price', 'gtin-product-feed-for-google-shopping'),
                'desc'              => __('Exclude products below this price.', 'gtin-product-feed-for-google-shopping'),
                'id'                => 'gswc_feed_min_price',
                'type'              => 'number',
                'default'           => '',
                'custom_attributes' => ['min' => '0', 'step' => '0.01'],
            ],
            [
                'title'             => __('Maximum Price', 'gtin-product-feed-for-google-shopping'),
                'desc'              => __('Exclude products above this price.', 'gtin-product-feed-for-google-shopping'),
                'id'                => 'gswc_feed_max_price',
                'type'              => 'number',
                'default'           => '',
                'custom_attributes' => ['min' => '0', 'step' => '0.01'],
            ],
            [
                'title'             => __('Product Limit', 'gtin-product-feed-for-google-shopping'),
                'desc'              => __('Maximum number of products to include (0 for unlimited).', 'gtin-product-feed-for-google-shopping'),
                'id'                => 'gswc_feed_limit',
                'type'              => 'number',
                'default'           => 0,
                'custom_attributes' => ['min' => '0'],
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_filters_section',
            ],
        ];
    }

    /**
     * Get customize settings
     *
     * @return array
     */
    private static function get_customize_settings() {
        return [
            [
                'title' => __('Title Customization', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Add prefix or suffix to product titles in feeds.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_title_customize',
            ],
            [
                'title'   => __('Title Prefix', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Text to add before product titles.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_title_prefix',
                'type'    => 'text',
                'default' => '',
            ],
            [
                'title'   => __('Title Suffix', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Text to add after product titles.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_title_suffix',
                'type'    => 'text',
                'default' => '',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_title_customize',
            ],
            [
                'title' => __('Description Customization', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Add prefix or suffix to product descriptions in feeds.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_desc_customize',
            ],
            [
                'title'   => __('Description Prefix', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Text to add before product descriptions.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_desc_prefix',
                'type'    => 'text',
                'default' => '',
            ],
            [
                'title'   => __('Description Suffix', 'gtin-product-feed-for-google-shopping'),
                'desc'    => __('Text to add after product descriptions.', 'gtin-product-feed-for-google-shopping'),
                'id'      => 'gswc_feed_desc_suffix',
                'type'    => 'text',
                'default' => '',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_desc_customize',
            ],
        ];
    }

    /**
     * Get license settings
     *
     * @return array
     */
    private static function get_license_settings() {
        return [
            [
                'title' => __('License', 'gtin-product-feed-for-google-shopping'),
                'type'  => 'title',
                'desc'  => __('Activate your license to receive updates and support.', 'gtin-product-feed-for-google-shopping'),
                'id'    => 'gswc_license_section',
            ],
            [
                'type' => 'gswc_license_field',
                'id'   => 'gswc_license_field',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'gswc_license_section',
            ],
        ];
    }

    /**
     * Output license field
     *
     * @param array $value Field value.
     */
    public static function output_license_field($value) {
        $nonce = wp_create_nonce('gswc_pro_upgrade');
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php esc_html_e('License Key', 'gtin-product-feed-for-google-shopping'); ?></th>
            <td class="forminp">
                <div class="gswc-license-form-inline">
                    <input type="text"
                           id="gswc-license-key-input"
                           class="regular-text"
                           placeholder="<?php esc_attr_e('Enter your license key', 'gtin-product-feed-for-google-shopping'); ?>" />
                    <button type="button" id="gswc-license-activate" class="button button-primary"><?php esc_html_e('Activate', 'gtin-product-feed-for-google-shopping'); ?></button>
                </div>
                <p class="description">
                    <?php
                    printf(
                        /* translators: %s: link to purchase page */
                        esc_html__("Don't have a license? %s", 'gtin-product-feed-for-google-shopping'),
                        '<a href="https://wooplugin.pro/google-shopping-pro" target="_blank">' . esc_html__('Purchase one here', 'gtin-product-feed-for-google-shopping') . '</a>'
                    );
                    ?>
                </p>
                <div id="gswc-license-status" class="gswc-license-status"></div>
            </td>
        </tr>
        <script>
        (function() {
            var activateBtn = document.getElementById('gswc-license-activate');
            var licenseInput = document.getElementById('gswc-license-key-input');
            var status = document.getElementById('gswc-license-status');

            if (!activateBtn) return;

            activateBtn.addEventListener('click', function() {
                var licenseKey = licenseInput.value.trim();
                if (!licenseKey) {
                    showStatus('error', '<?php echo esc_js(__('Please enter a license key.', 'gtin-product-feed-for-google-shopping')); ?>');
                    return;
                }

                showStatus('loading', '<?php echo esc_js(__('Validating license...', 'gtin-product-feed-for-google-shopping')); ?>');
                activateBtn.disabled = true;

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
                    activateBtn.disabled = false;
                });
            });

            function showStatus(type, message) {
                status.className = 'gswc-license-status ' + type;
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
     * Output feeds table
     *
     * @param array $value Field value.
     */
    public static function output_feeds_table($value) {
        $feed_url = GSWC_Feed_Generator::get_feed_url('google');
        $feed_file = GSWC_Feed_Generator::get_feed_path('google');
        $feed_exists = file_exists($feed_file);
        $last_generated = get_option('gswc_feed_last_generated', 0);

        $channels = [
            'google'    => ['name' => 'Google Shopping', 'pro' => false],
            'facebook'  => ['name' => 'Facebook / Meta', 'pro' => true],
            'pinterest' => ['name' => 'Pinterest', 'pro' => true],
            'tiktok'    => ['name' => 'TikTok', 'pro' => true],
            'bing'      => ['name' => 'Bing / Microsoft', 'pro' => true],
            'snapchat'  => ['name' => 'Snapchat', 'pro' => true],
        ];
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php esc_html_e('Feed Channels', 'gtin-product-feed-for-google-shopping'); ?></th>
            <td class="forminp">
                <table class="gswc-feeds-table widefat striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" disabled /></th>
                            <th><?php esc_html_e('Channel', 'gtin-product-feed-for-google-shopping'); ?></th>
                            <th><?php esc_html_e('Status', 'gtin-product-feed-for-google-shopping'); ?></th>
                            <th><?php esc_html_e('Last Generated', 'gtin-product-feed-for-google-shopping'); ?></th>
                            <th><?php esc_html_e('Feed URL', 'gtin-product-feed-for-google-shopping'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($channels as $id => $channel) : ?>
                            <tr class="<?php echo $channel['pro'] ? 'gswc-feed-locked' : ''; ?>">
                                <td class="check-column">
                                    <input type="checkbox"
                                           <?php checked($id === 'google'); ?>
                                           <?php disabled($channel['pro']); ?> />
                                </td>
                                <td>
                                    <strong><?php echo esc_html($channel['name']); ?></strong>
                                    <?php if ($channel['pro']) : ?>
                                        <span class="gswc-pro-badge">
                                            <span class="dashicons dashicons-lock"></span> <?php esc_html_e('Pro', 'gtin-product-feed-for-google-shopping'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($id === 'google' && $feed_exists) : ?>
                                        <span class="gswc-status-badge gswc-status-active"><?php esc_html_e('Active', 'gtin-product-feed-for-google-shopping'); ?></span>
                                    <?php elseif ($id === 'google') : ?>
                                        <span class="gswc-status-badge gswc-status-inactive"><?php esc_html_e('Not Generated', 'gtin-product-feed-for-google-shopping'); ?></span>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($id === 'google' && $last_generated) : ?>
                                        <?php echo esc_html(human_time_diff($last_generated, time()) . ' ' . __('ago', 'gtin-product-feed-for-google-shopping')); ?>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($id === 'google' && $feed_exists) : ?>
                                        <div class="gswc-feed-url-cell">
                                            <code class="gswc-feed-url"><?php echo esc_url($feed_url); ?></code>
                                            <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button button-small">↗</a>
                                        </div>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description"><?php esc_html_e('Select the feed channels you want to generate.', 'gtin-product-feed-for-google-shopping'); ?></p>

                <p style="margin-top: 15px;">
                    <button type="button" id="gswc-generate-feed" class="button button-primary">
                        <?php esc_html_e('Generate All Enabled Feeds', 'gtin-product-feed-for-google-shopping'); ?>
                    </button>
                    <span id="gswc-feed-spinner" class="spinner" style="float: none;"></span>
                    <span id="gswc-feed-result"></span>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Output Pro locked content
     *
     * @param array $value Field value.
     */
    public static function output_pro_locked($value) {
        $feature = $value['feature'] ?? 'default';
        $pro_url = 'https://wooplugin.pro/google-shopping-pro';

        $features = [
            'categories' => [
                'title'       => __('Category Mapping', 'gtin-product-feed-for-google-shopping'),
                'description' => __('Map your WooCommerce categories to Google Product Categories for better product classification and ad performance.', 'gtin-product-feed-for-google-shopping'),
                'items'       => [
                    __('Map each category to Google taxonomy', 'gtin-product-feed-for-google-shopping'),
                    __('Autocomplete search with 5,500+ categories', 'gtin-product-feed-for-google-shopping'),
                    __('Quick "Suggest" button for auto-matching', 'gtin-product-feed-for-google-shopping'),
                ],
            ],
            'schedule' => [
                'title'       => __('Automatic Scheduling', 'gtin-product-feed-for-google-shopping'),
                'description' => __('Keep your feeds fresh with automatic scheduled updates and instant regeneration when products change.', 'gtin-product-feed-for-google-shopping'),
                'items'       => [
                    __('Scheduled updates: hourly, daily, weekly', 'gtin-product-feed-for-google-shopping'),
                    __('Auto-regenerate when products change', 'gtin-product-feed-for-google-shopping'),
                    __('Smart debouncing for bulk operations', 'gtin-product-feed-for-google-shopping'),
                    __('WP-Cron integration', 'gtin-product-feed-for-google-shopping'),
                ],
            ],
            'filters' => [
                'title'       => __('Advanced Filters', 'gtin-product-feed-for-google-shopping'),
                'description' => __('Control exactly which products appear in your feeds with powerful filtering options.', 'gtin-product-feed-for-google-shopping'),
                'items'       => [
                    __('Exclude by category or tag', 'gtin-product-feed-for-google-shopping'),
                    __('Filter by price range', 'gtin-product-feed-for-google-shopping'),
                    __('Filter by stock status', 'gtin-product-feed-for-google-shopping'),
                    __('Exclude individual products', 'gtin-product-feed-for-google-shopping'),
                ],
            ],
            'customize' => [
                'title'       => __('Feed Customization', 'gtin-product-feed-for-google-shopping'),
                'description' => __('Customize how your products appear in feeds with title and description modifications.', 'gtin-product-feed-for-google-shopping'),
                'items'       => [
                    __('Add prefix/suffix to product titles', 'gtin-product-feed-for-google-shopping'),
                    __('Add prefix/suffix to descriptions', 'gtin-product-feed-for-google-shopping'),
                    __('Enhance product visibility in ads', 'gtin-product-feed-for-google-shopping'),
                    __('Highlight promotions and offers', 'gtin-product-feed-for-google-shopping'),
                ],
            ],
        ];

        $feat = $features[$feature] ?? $features['categories'];
        ?>
        <div class="gswc-pro-locked-content">
            <div class="gswc-pro-locked-icon">
                <span class="dashicons dashicons-lock"></span>
            </div>
            <div class="gswc-pro-locked-info">
                <h3><?php echo esc_html($feat['title']); ?> <span class="gswc-pro-badge-purple">Pro</span></h3>
                <p><?php echo esc_html($feat['description']); ?></p>
                <ul>
                    <?php foreach ($feat['items'] as $item) : ?>
                        <li><span class="dashicons dashicons-yes"></span> <?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url($pro_url); ?>" class="button button-primary" target="_blank">
                    <?php esc_html_e('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'); ?>
                    <span class="dashicons dashicons-external"></span>
                </a>
            </div>
        </div>
        <?php

        // Show preview table for specific features
        if ($feature === 'categories') {
            self::output_categories_preview();
        } elseif ($feature === 'schedule') {
            self::output_schedule_preview();
        } elseif ($feature === 'filters') {
            self::output_filters_preview();
        } elseif ($feature === 'customize') {
            self::output_customize_preview();
        }
    }

    /**
     * Output category mapping preview table
     */
    private static function output_categories_preview() {
        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);
        ?>
        <div class="gswc-pro-preview-section">
            <p class="description" style="margin-bottom: 15px;">
                <?php esc_html_e('Map your WooCommerce categories to Google Product Categories for better feed quality. Start typing to search or click "Suggest" for automatic suggestions.', 'gtin-product-feed-for-google-shopping'); ?>
            </p>
            <table class="gswc-category-mapping-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('WooCommerce Category', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <th><?php esc_html_e('Google Product Category', 'gtin-product-feed-for-google-shopping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                        <?php foreach ($categories as $cat) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($cat->name); ?>
                                    <span class="gswc-cat-count">(<?php echo esc_html($cat->count); ?>)</span>
                                </td>
                                <td>
                                    <div class="gswc-category-input-row">
                                        <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Start typing to search...', 'gtin-product-feed-for-google-shopping'); ?>" disabled />
                                        <button type="button" class="button" disabled><?php esc_html_e('Suggest', 'gtin-product-feed-for-google-shopping'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td>Clothing <span class="gswc-cat-count">(0)</span></td>
                            <td>
                                <div class="gswc-category-input-row">
                                    <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Start typing to search...', 'gtin-product-feed-for-google-shopping'); ?>" disabled />
                                    <button type="button" class="button" disabled><?php esc_html_e('Suggest', 'gtin-product-feed-for-google-shopping'); ?></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Electronics <span class="gswc-cat-count">(0)</span></td>
                            <td>
                                <div class="gswc-category-input-row">
                                    <input type="text" class="regular-text" placeholder="<?php esc_attr_e('Start typing to search...', 'gtin-product-feed-for-google-shopping'); ?>" disabled />
                                    <button type="button" class="button" disabled><?php esc_html_e('Suggest', 'gtin-product-feed-for-google-shopping'); ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Output schedule preview
     */
    private static function output_schedule_preview() {
        ?>
        <div class="gswc-pro-preview-section">
            <h2><?php esc_html_e('Schedule Settings', 'gtin-product-feed-for-google-shopping'); ?></h2>
            <p class="description" style="margin-bottom: 20px;"><?php esc_html_e('Configure automatic feed generation.', 'gtin-product-feed-for-google-shopping'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Feed Schedule', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <select disabled>
                            <option><?php esc_html_e('Manual only', 'gtin-product-feed-for-google-shopping'); ?></option>
                            <option><?php esc_html_e('Hourly', 'gtin-product-feed-for-google-shopping'); ?></option>
                            <option><?php esc_html_e('Twice Daily', 'gtin-product-feed-for-google-shopping'); ?></option>
                            <option><?php esc_html_e('Daily', 'gtin-product-feed-for-google-shopping'); ?></option>
                            <option><?php esc_html_e('Weekly', 'gtin-product-feed-for-google-shopping'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('How often to automatically regenerate feeds.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Auto-Update on Product Save', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" disabled />
                            <?php esc_html_e('Automatically regenerate feeds when a product is created, updated, or deleted (debounced to 60 seconds)', 'gtin-product-feed-for-google-shopping'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Schedule Status', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <table class="gswc-schedule-status-table">
                            <tr>
                                <td><?php esc_html_e('Current Schedule', 'gtin-product-feed-for-google-shopping'); ?></td>
                                <td><strong><?php esc_html_e('Manual only', 'gtin-product-feed-for-google-shopping'); ?></strong></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Auto-Update', 'gtin-product-feed-for-google-shopping'); ?></td>
                                <td><strong><?php esc_html_e('Disabled', 'gtin-product-feed-for-google-shopping'); ?></strong></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Last Run', 'gtin-product-feed-for-google-shopping'); ?></td>
                                <td><strong>—</strong></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Next Run', 'gtin-product-feed-for-google-shopping'); ?></td>
                                <td><strong>—</strong></td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Output filters preview
     */
    private static function output_filters_preview() {
        ?>
        <div class="gswc-pro-preview-section">
            <table class="form-table gswc-pro-preview-disabled">
                <tr>
                    <th scope="row"><?php esc_html_e('Exclude Categories', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <select multiple disabled style="width: 400px; height: 80px;">
                            <option><?php esc_html_e('Select categories to exclude...', 'gtin-product-feed-for-google-shopping'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Products in these categories will be excluded from feeds.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Exclude Tags', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <select multiple disabled style="width: 400px; height: 80px;">
                            <option><?php esc_html_e('Select tags to exclude...', 'gtin-product-feed-for-google-shopping'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Products with these tags will be excluded from feeds.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Minimum Price', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <input type="number" disabled class="small-text" placeholder="0.00" />
                        <p class="description"><?php esc_html_e('Exclude products below this price.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Maximum Price', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <input type="number" disabled class="small-text" placeholder="0.00" />
                        <p class="description"><?php esc_html_e('Exclude products above this price.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Output customize preview
     */
    private static function output_customize_preview() {
        ?>
        <div class="gswc-pro-preview-section">
            <table class="form-table gswc-pro-preview-disabled">
                <tr>
                    <th scope="row"><?php esc_html_e('Title Prefix', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <input type="text" disabled class="regular-text" placeholder="<?php esc_attr_e('e.g., [NEW]', 'gtin-product-feed-for-google-shopping'); ?>" />
                        <p class="description"><?php esc_html_e('Text to add before product titles.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Title Suffix', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <input type="text" disabled class="regular-text" placeholder="<?php esc_attr_e('e.g., - Free Shipping', 'gtin-product-feed-for-google-shopping'); ?>" />
                        <p class="description"><?php esc_html_e('Text to add after product titles.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Description Prefix', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <input type="text" disabled class="regular-text" />
                        <p class="description"><?php esc_html_e('Text to add before product descriptions.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Description Suffix', 'gtin-product-feed-for-google-shopping'); ?></th>
                    <td>
                        <input type="text" disabled class="regular-text" />
                        <p class="description"><?php esc_html_e('Text to add after product descriptions.', 'gtin-product-feed-for-google-shopping'); ?></p>
                    </td>
                </tr>
            </table>
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
        global $current_section;
        $settings = self::get_settings_for_section($current_section);
        woocommerce_update_options($settings);
    }

    /**
     * Get settings array (for compatibility)
     *
     * @return array
     */
    public static function get_settings() {
        return self::get_general_settings();
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

            /* Sections card navigation */
            .gswc-sections-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 4px;
                margin-bottom: 20px;
            }

            .gswc-sections-nav {
                display: flex;
                gap: 4px;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            .gswc-sections-nav li {
                margin: 0;
                flex: 1;
            }

            .gswc-sections-nav a {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                padding: 12px 16px;
                border-radius: 6px;
                text-decoration: none;
                color: #374151;
                font-weight: 500;
                font-size: 13px;
                transition: all 0.15s ease;
            }

            .gswc-sections-nav a:hover {
                background: #f3f4f6;
                color: #1f2937;
            }

            .gswc-sections-nav a.current {
                background: #4f46e5;
                color: #fff;
            }

            .gswc-sections-nav a.current .gswc-pro-badge-small {
                background: rgba(255, 255, 255, 0.25);
            }

            /* Content card */
            .gswc-content-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 24px;
            }

            .gswc-content-card .form-table {
                margin: 0;
            }

            .gswc-content-card .form-table th {
                padding-top: 15px;
                padding-bottom: 15px;
            }

            .gswc-content-card h2 {
                margin-top: 0;
                padding-top: 0;
            }

            /* Section tabs Pro badge */
            .gswc-pro-badge-small {
                display: inline-block;
                background: #6366f1;
                color: #fff;
                font-size: 9px;
                font-weight: 600;
                padding: 2px 5px;
                border-radius: 3px;
                text-transform: uppercase;
                vertical-align: middle;
            }

            /* Feeds table */
            .gswc-feeds-table {
                margin-top: 10px;
            }

            .gswc-feeds-table th,
            .gswc-feeds-table td {
                padding: 10px;
                vertical-align: middle;
            }

            .gswc-feeds-table .check-column {
                width: 30px;
                vertical-align: middle;
                text-align: center;
                padding: 10px !important;
            }

            .gswc-feeds-table th.check-column,
            .gswc-feeds-table td.check-column {
                width: 30px;
                text-align: center;
            }

            .gswc-feeds-table .check-column input[type="checkbox"] {
                margin: 0;
                vertical-align: middle;
            }

            .gswc-feed-url-cell {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .gswc-feed-url {
                font-size: 11px;
                background: #f0f0f0;
                padding: 4px 8px;
                border-radius: 3px;
                max-width: 300px;
                display: inline-block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                vertical-align: middle;
            }

            .gswc-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
            }

            .gswc-status-active {
                background: #d4edda;
                color: #155724;
            }

            .gswc-status-inactive {
                background: #f0f0f0;
                color: #666;
            }

            .gswc-feed-locked {
                opacity: 0.6;
            }

            .gswc-feed-locked input[type="checkbox"] {
                cursor: not-allowed;
            }

            .gswc-pro-badge {
                display: inline-flex;
                align-items: center;
                gap: 3px;
                background: #f0f0f0;
                color: #666;
                font-size: 10px;
                font-weight: 600;
                padding: 2px 6px;
                border-radius: 3px;
                margin-left: 8px;
                vertical-align: middle;
            }

            .gswc-pro-badge .dashicons {
                font-size: 12px;
                width: 12px;
                height: 12px;
            }

            #gswc-feed-result {
                margin-left: 10px;
            }

            #gswc-feed-result.success {
                color: #16a34a;
            }

            #gswc-feed-result.error {
                color: #dc2626;
            }

            /* Pro locked content card */
            .gswc-pro-locked-content {
                display: flex;
                align-items: flex-start;
                gap: 24px;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 2px dashed #cbd5e1;
                border-radius: 12px;
                padding: 30px;
                margin: 0 0 20px 0;
            }

            .gswc-pro-badge-purple {
                display: inline-block;
                background: #6366f1;
                color: #fff;
                font-size: 11px;
                font-weight: 600;
                padding: 3px 8px;
                border-radius: 4px;
                vertical-align: middle;
            }

            /* Pro preview sections */
            .gswc-pro-preview-section {
                opacity: 0.6;
                pointer-events: none;
            }

            .gswc-pro-preview-section input:disabled,
            .gswc-pro-preview-section select:disabled,
            .gswc-pro-preview-section button:disabled {
                cursor: not-allowed;
            }

            .gswc-pro-preview-disabled {
                margin-top: 0;
            }

            /* Category mapping table */
            .gswc-category-mapping-table {
                margin-top: 0;
            }

            .gswc-category-mapping-table th,
            .gswc-category-mapping-table td {
                padding: 12px 15px;
                vertical-align: middle;
            }

            .gswc-category-mapping-table th {
                font-weight: 600;
            }

            .gswc-cat-count {
                color: #6b7280;
                font-size: 13px;
            }

            .gswc-category-input-row {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .gswc-category-input-row input {
                flex: 1;
                max-width: 400px;
            }

            /* Schedule status table */
            .gswc-schedule-status-table {
                border-collapse: collapse;
            }

            .gswc-schedule-status-table td {
                padding: 8px 20px 8px 0;
            }

            .gswc-schedule-status-table td:first-child {
                color: #6b7280;
            }

            /* License field */
            .gswc-license-form-inline {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 10px;
            }

            .gswc-license-status {
                margin-top: 10px;
                padding: 10px;
                border-radius: 4px;
                display: none;
            }

            .gswc-license-status.loading,
            .gswc-license-status.success,
            .gswc-license-status.error {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .gswc-license-status.loading {
                background: #e0f2fe;
                color: #075985;
            }

            .gswc-license-status.success {
                background: #dcfce7;
                color: #166534;
            }

            .gswc-license-status.error {
                background: #fee2e2;
                color: #991b1b;
            }

            .gswc-license-status .spinner {
                float: none;
                margin: 0;
            }

            .gswc-pro-locked-icon {
                flex-shrink: 0;
                width: 60px;
                height: 60px;
                background: #e0e7ff;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .gswc-pro-locked-icon .dashicons {
                font-size: 28px;
                width: 28px;
                height: 28px;
                color: #6366f1;
            }

            .gswc-pro-locked-info h3 {
                margin: 0 0 10px 0;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .gswc-pro-locked-info p {
                color: #64748b;
                margin: 0 0 16px 0;
            }

            .gswc-pro-locked-info ul {
                margin: 0 0 20px 0;
                padding: 0;
                list-style: none;
            }

            .gswc-pro-locked-info li {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 8px;
                color: #374151;
            }

            .gswc-pro-locked-info li .dashicons {
                color: #22c55e;
                font-size: 16px;
                width: 16px;
                height: 16px;
            }

            .gswc-pro-locked-info .button {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .gswc-pro-locked-info .button .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
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
