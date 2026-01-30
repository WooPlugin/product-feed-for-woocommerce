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
        add_action('admin_init', [__CLASS__, 'handle_settings_save']);
    }

    /**
     * Get sections
     *
     * @return array
     */
    public static function get_sections() {
        return [
            ''          => __('General', 'gtin-product-feed-for-google-shopping'),
            'feeds'     => __('Feeds', 'gtin-product-feed-for-google-shopping'),
            'filters'   => __('Filters', 'gtin-product-feed-for-google-shopping'),
            'customize' => __('Customization', 'gtin-product-feed-for-google-shopping'),
        ];
    }

    /**
     * Output sections (deprecated - sections are now separate menu items)
     */
    public static function output_sections() {
        // Sections are now individual menu items, no need for navigation
        return;
    }

    /**
     * Output settings page (standalone, without WooCommerce Settings API)
     *
     * @param string $current_section Current section ID.
     */
    public static function output_settings_page($current_section = '') {
        self::$current_section = $current_section;

        // Get current page for navigation highlighting
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'gswc-general';

        // Page titles
        $page_titles = [
            'gswc-general'    => __('General', 'gtin-product-feed-for-google-shopping'),
            'gswc-feeds'      => __('Feeds', 'gtin-product-feed-for-google-shopping'),
            'gswc-filters'    => __('Filters', 'gtin-product-feed-for-google-shopping'),
            'gswc-customize' => __('Customization', 'gtin-product-feed-for-google-shopping'),
        ];
        $page_title = $page_titles[$page] ?? __('Settings', 'gtin-product-feed-for-google-shopping');

        // Show save notice
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'gtin-product-feed-for-google-shopping') . '</p></div>';
        }

        ?>
        <div class="gswc-dashboard">
            <h1 class="gswc-header">
                <span class="gswc-logo">GTIN Product Feed</span>
                <?php esc_html_e('for Google Shopping', 'gtin-product-feed-for-google-shopping'); ?>
            </h1>

            <div class="gswc-settings-wrapper">
                <div class="gswc-settings-main">
                    <h2 class="gswc-page-title"><?php echo esc_html($page_title); ?></h2>

                    <div class="gswc-content-card">
                    <?php if ($page === 'gswc-feeds') : ?>
                        <?php self::render_feeds_page_content(); ?>
                    <?php else : ?>
                        <form method="post" action="">
                            <?php wp_nonce_field('gswc_settings_nonce', 'gswc_settings_nonce'); ?>
                            <input type="hidden" name="gswc_action" value="save_settings" />
                            <input type="hidden" name="gswc_section" value="<?php echo esc_attr($current_section); ?>" />
                            <input type="hidden" name="gswc_page" value="<?php echo esc_attr($page); ?>" />
                            <div class="gswc-form-fields">
                                <?php
                                $settings = self::get_settings_for_section($current_section);
                                self::render_settings_fields($settings);
                                ?>
                            </div>
                            <div class="gswc-form-actions">
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e('Save Changes', 'gtin-product-feed-for-google-shopping'); ?>
                                </button>
                                <a href="<?php echo esc_url(GSWC_Feed_Generator::get_feed_url('google')); ?>" target="_blank" class="button">
                                    <?php esc_html_e('View Feed', 'gtin-product-feed-for-google-shopping'); ?>
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render feeds page content (special layout for feeds section)
     */
    private static function render_feeds_page_content() {
        $feed_url = GSWC_Feed_Generator::get_feed_url('google');
        $feed_file = GSWC_Feed_Generator::get_feed_path('google');
        $feed_exists = file_exists($feed_file);
        $last_generated = get_option('gswc_feed_last_generated', 0);
        $product_count = get_option('gswc_feed_product_count', 0);
        $feed_enabled = get_option('gswc_feed_enabled', 'yes') === 'yes';
        $file_size = $feed_exists ? size_format(filesize($feed_file), 1) : '0 B';

        ?>
        <form method="post" action="">
            <?php wp_nonce_field('gswc_settings_nonce', 'gswc_settings_nonce'); ?>
            <input type="hidden" name="gswc_action" value="save_feed_settings" />
            <input type="hidden" name="gswc_page" value="gswc-feeds" />

            <!-- Google Shopping Feed Card -->
            <div class="gswc-feed-channel-card">
                <div class="gswc-feed-channel-header">
                    <div class="gswc-feed-channel-info">
                        <div class="gswc-feed-channel-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <div>
                            <h3><?php esc_html_e('Google Shopping', 'gtin-product-feed-for-google-shopping'); ?></h3>
                            <?php if ($feed_enabled && $feed_exists) : ?>
                                <span class="gswc-feed-status-badge gswc-status-active">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e('Enabled', 'gtin-product-feed-for-google-shopping'); ?>
                                </span>
                            <?php elseif ($feed_enabled) : ?>
                                <span class="gswc-feed-status-badge gswc-status-pending">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Enabled - Not Generated', 'gtin-product-feed-for-google-shopping'); ?>
                                </span>
                            <?php else : ?>
                                <span class="gswc-feed-status-badge gswc-status-inactive">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php esc_html_e('Disabled', 'gtin-product-feed-for-google-shopping'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <label class="gswc-toggle-switch">
                        <input type="checkbox" name="gswc_feed_enabled" value="1" <?php checked($feed_enabled); ?> />
                        <span class="gswc-toggle-slider"></span>
                    </label>
                </div>

                <?php if ($feed_enabled) : ?>
                    <div class="gswc-feed-channel-body">
                        <div class="gswc-feed-stats-row">
                            <div class="gswc-feed-stat">
                                <span class="gswc-feed-stat-label"><?php esc_html_e('Products', 'gtin-product-feed-for-google-shopping'); ?></span>
                                <span class="gswc-feed-stat-value"><?php echo esc_html($product_count); ?></span>
                            </div>
                            <div class="gswc-feed-stat">
                                <span class="gswc-feed-stat-label"><?php esc_html_e('File Size', 'gtin-product-feed-for-google-shopping'); ?></span>
                                <span class="gswc-feed-stat-value"><?php echo esc_html($file_size); ?></span>
                            </div>
                            <div class="gswc-feed-stat">
                                <span class="gswc-feed-stat-label"><?php esc_html_e('Last Generated', 'gtin-product-feed-for-google-shopping'); ?></span>
                                <span class="gswc-feed-stat-value">
                                    <?php
                                    if ($last_generated) {
                                        echo esc_html(human_time_diff($last_generated, time()));
                                    } else {
                                        esc_html_e('Never', 'gtin-product-feed-for-google-shopping');
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <div class="gswc-feed-url-section">
                            <label><?php esc_html_e('Feed URL', 'gtin-product-feed-for-google-shopping'); ?></label>
                            <div class="gswc-feed-url-row">
                                <input type="text" class="gswc-feed-url-input" value="<?php echo esc_url($feed_url); ?>" readonly onclick="this.select();" />
                                <button type="button" class="button button-small gswc-copy-url" data-url="<?php echo esc_attr($feed_url); ?>">
                                    <span class="dashicons dashicons-admin-page"></span>
                                    <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                                </button>
                                <?php if ($feed_exists) : ?>
                                    <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button button-small">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php esc_html_e('Open', 'gtin-product-feed-for-google-shopping'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="gswc-feed-actions">
                            <span id="gswc-feed-result"></span>
                            <span id="gswc-feed-spinner" class="spinner"></span>
                            <button type="button" id="gswc-generate-feed" class="button button-primary">
                                <span class="dashicons dashicons-update-alt"></span>
                                <?php esc_html_e('Generate Feed', 'gtin-product-feed-for-google-shopping'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Feed Options Info -->
            <div class="gswc-feed-options-info">
                <h4>
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('About Feed Options', 'gtin-product-feed-for-google-shopping'); ?>
                </h4>
                <p>
                    <?php esc_html_e('Enable or disable this feed to control whether it is generated. When disabled, the feed file will not be created or updated. This is useful if you want to temporarily stop generating a feed without losing your settings.', 'gtin-product-feed-for-google-shopping'); ?>
                </p>
            </div>

            <div class="gswc-form-actions">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save Changes', 'gtin-product-feed-for-google-shopping'); ?>
                </button>
                <?php if ($feed_exists) : ?>
                    <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button">
                        <?php esc_html_e('View Feed', 'gtin-product-feed-for-google-shopping'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        <?php
    }

    /**
     * Render settings fields (inline layout)
     *
     * @param array $settings Settings array.
     */
    private static function render_settings_fields($settings) {
        foreach ($settings as $field) {
            $type = $field['type'] ?? 'text';
            $id = $field['id'] ?? '';
            $title = $field['title'] ?? '';
            $desc = $field['desc'] ?? '';
            $value = get_option($id, $field['default'] ?? '');

            // Handle special field types
            if (in_array($type, ['title', 'sectionend'], true)) {
                continue; // Skip section dividers in inline layout
            }

            // Handle custom field types
            if (strpos($type, 'gswc_') === 0) {
                $callback = 'output_' . str_replace('gswc_', '', $type);
                if (method_exists(__CLASS__, $callback)) {
                    self::$callback($field);
                }
                continue;
            }

            // Render field
            echo '<div class="gswc-form-field">';
            echo '<label for="' . esc_attr($id) . '" class="gswc-field-label">' . esc_html($title) . '</label>';

            echo '<div class="gswc-field-input">';

            switch ($type) {
                case 'text':
                    echo '<input type="text" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="gswc-input-text" />';
                    break;
                case 'number':
                    $min = $field['custom_attributes']['min'] ?? '';
                    $step = $field['custom_attributes']['step'] ?? '';
                    echo '<input type="number" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="gswc-input-number"';
                    if ($min) echo ' min="' . esc_attr($min) . '"';
                    if ($step) echo ' step="' . esc_attr($step) . '"';
                    echo ' />';
                    break;
                case 'select':
                    echo '<select name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" class="gswc-select">';
                    foreach ($field['options'] as $opt_value => $opt_label) {
                        echo '<option value="' . esc_attr($opt_value) . '" ' . selected($value, $opt_value, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 'checkbox':
                    echo '<label class="gswc-checkbox-label">';
                    echo '<input type="checkbox" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="1" ' . checked($value, 'yes', false) . ' /> ';
                    echo '<span>' . esc_html($desc) . '</span>';
                    echo '</label>';
                    break;
                case 'multiselect':
                    echo '<select name="' . esc_attr($id) . '[]" id="' . esc_attr($id) . '" multiple="multiple" class="wc-enhanced-select gswc-multiselect">';
                    foreach ($field['options'] as $opt_value => $opt_label) {
                        echo '<option value="' . esc_attr($opt_value) . '" ' . selected(is_array($value) && in_array((string) $opt_value, $value, true), true, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    break;
            }

            if ($desc && $type !== 'checkbox') {
                echo '<p class="gswc-field-description">' . esc_html($desc) . '</p>';
            }

            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Output inline pair field (two inputs side by side with example)
     *
     * @param array $field Field config.
     */
    public static function output_inline_pair($field) {
        $title = $field['title'] ?? '';
        $fields = $field['fields'] ?? [];
        $example = $field['example'] ?? 'Product Name';

        // Get current values for preview
        $prefix_value = '';
        $suffix_value = '';
        $prefix_id = '';
        $suffix_id = '';

        foreach ($fields as $index => $subfield) {
            $id = $subfield['id'] ?? '';
            $value = get_option($id, $subfield['default'] ?? '');
            if ($index === 0) {
                $prefix_value = $value;
                $prefix_id = $id;
            } else {
                $suffix_value = $value;
                $suffix_id = $id;
            }
        }

        echo '<div class="gswc-form-field">';
        echo '<label class="gswc-field-label">' . esc_html($title) . '</label>';
        echo '<div class="gswc-inline-pair">';

        foreach ($fields as $subfield) {
            $id = $subfield['id'] ?? '';
            $label = $subfield['label'] ?? '';
            $value = get_option($id, $subfield['default'] ?? '');

            echo '<div class="gswc-inline-pair-field">';
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
            echo '<div class="gswc-input-clearable">';
            echo '<input type="text" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="gswc-input-text gswc-inline-pair-input" data-prefix-id="' . esc_attr($prefix_id) . '" data-suffix-id="' . esc_attr($suffix_id) . '" />';
            echo '<button type="button" class="gswc-input-clear" tabindex="-1">&times;</button>';
            echo '</div>';
            echo '</div>';
        }

        // Example preview
        echo '<div class="gswc-inline-pair-example">';
        echo '<label>' . esc_html__('Example', 'gtin-product-feed-for-google-shopping') . '</label>';
        echo '<div class="gswc-example-preview" data-example="' . esc_attr($example) . '" data-prefix-id="' . esc_attr($prefix_id) . '" data-suffix-id="' . esc_attr($suffix_id) . '">';
        $preview = trim($prefix_value . ' ' . $example . ' ' . $suffix_value);
        echo esc_html($preview);
        echo '</div>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    /**
     * Output field row (multiple fields side by side)
     *
     * @param array $field Field config.
     */
    public static function output_field_row($field) {
        $title = $field['title'] ?? '';
        $fields = $field['fields'] ?? [];

        echo '<div class="gswc-form-field">';
        echo '<label class="gswc-field-label">' . esc_html($title) . '</label>';
        echo '<div class="gswc-field-row">';

        foreach ($fields as $subfield) {
            $id = $subfield['id'] ?? '';
            $label = $subfield['title'] ?? '';
            $type = $subfield['type'] ?? 'text';
            $value = get_option($id, $subfield['default'] ?? '');

            echo '<div class="gswc-field-row-item">';
            echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';

            switch ($type) {
                case 'multiselect':
                    echo '<select name="' . esc_attr($id) . '[]" id="' . esc_attr($id) . '" multiple="multiple" class="wc-enhanced-select gswc-multiselect">';
                    foreach ($subfield['options'] ?? [] as $opt_value => $opt_label) {
                        echo '<option value="' . esc_attr($opt_value) . '" ' . selected(is_array($value) && in_array((string) $opt_value, $value, true), true, false) . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'number':
                    $min = $subfield['custom_attributes']['min'] ?? '';
                    $step = $subfield['custom_attributes']['step'] ?? '';
                    echo '<input type="number" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="gswc-input-number"';
                    if ($min !== '') {
                        echo ' min="' . esc_attr($min) . '"';
                    }
                    if ($step !== '') {
                        echo ' step="' . esc_attr($step) . '"';
                    }
                    echo ' />';
                    break;

                default:
                    echo '<input type="text" name="' . esc_attr($id) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '" class="gswc-input-text" />';
                    break;
            }

            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Handle settings save
     */
    public static function handle_settings_save() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = isset($_POST['gswc_action']) ? sanitize_text_field(wp_unslash($_POST['gswc_action'])) : '';

        if (!in_array($action, ['save_settings', 'save_feed_settings'], true)) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['gswc_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['gswc_settings_nonce'])), 'gswc_settings_nonce')) {
            return;
        }

        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_POST['gswc_page']) ? sanitize_text_field(wp_unslash($_POST['gswc_page'])) : 'gswc-general';

        // Handle feed settings save
        if ($action === 'save_feed_settings') {
            // Save feed enabled setting
            $feed_enabled = isset($_POST['gswc_feed_enabled']) ? 'yes' : 'no';
            update_option('gswc_feed_enabled', $feed_enabled);

            // Redirect with success message
            wp_safe_redirect(admin_url('admin.php?page=' . $page . '&settings-updated=true'));
            exit;
        }

        // Handle regular settings save
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $section = isset($_POST['gswc_section']) ? sanitize_text_field(wp_unslash($_POST['gswc_section'])) : '';

        // Map section back to page slug for redirect
        $section_to_page = [
            ''         => 'gswc-general',
            'feeds'    => 'gswc-feeds',
            'filters'  => 'gswc-filters',
            'customize'=> 'gswc-customize',
        ];

        $page = $section_to_page[$section] ?? 'gswc-general';

        // Get settings for this section
        $settings = self::get_settings_for_section($section);

        // Save each setting
        foreach ($settings as $field) {
            $type = $field['type'] ?? '';
            $id = $field['id'] ?? '';

            // Skip special field types (except inline_pair which needs processing)
            if (in_array($type, ['title', 'sectionend'], true)) {
                continue;
            }

            // Handle inline pair fields
            if ($type === 'gswc_inline_pair') {
                foreach ($field['fields'] ?? [] as $subfield) {
                    $subfield_id = $subfield['id'] ?? '';
                    if ($subfield_id) {
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $value = isset($_POST[$subfield_id]) ? sanitize_text_field(wp_unslash($_POST[$subfield_id])) : '';
                        update_option($subfield_id, $value);
                    }
                }
                continue;
            }

            // Handle field row (multiple fields in a row)
            if ($type === 'gswc_field_row') {
                foreach ($field['fields'] ?? [] as $subfield) {
                    $subfield_id = $subfield['id'] ?? '';
                    $subfield_type = $subfield['type'] ?? 'text';
                    if ($subfield_id) {
                        if ($subfield_type === 'multiselect') {
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            $value = isset($_POST[$subfield_id]) ? array_map('sanitize_text_field', wp_unslash($_POST[$subfield_id])) : [];
                        } else {
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            $value = isset($_POST[$subfield_id]) ? sanitize_text_field(wp_unslash($_POST[$subfield_id])) : '';
                        }
                        update_option($subfield_id, $value);
                    }
                }
                continue;
            }

            // Skip other custom field types
            if (strpos($type, 'gswc_') === 0) {
                continue;
            }

            // Get value from POST
            if ($type === 'checkbox') {
                $value = isset($_POST[$id]) ? 'yes' : 'no';
            } elseif ($type === 'multiselect') {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $value = isset($_POST[$id]) ? array_map('sanitize_text_field', wp_unslash($_POST[$id])) : [];
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $value = isset($_POST[$id]) ? sanitize_text_field(wp_unslash($_POST[$id])) : '';
            }

            update_option($id, $value);
        }

        // Redirect with success message
        wp_safe_redirect(admin_url('admin.php?page=' . $page . '&settings-updated=true'));
        exit;
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
            case 'filters':
                return self::get_filters_settings();
            case 'customize':
                return self::get_customize_settings();
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
                'title'  => __('Exclusions', 'gtin-product-feed-for-google-shopping'),
                'type'   => 'gswc_field_row',
                'fields' => [
                    [
                        'title'   => __('Exclude Categories', 'gtin-product-feed-for-google-shopping'),
                        'id'      => 'gswc_feed_exclude_categories',
                        'type'    => 'multiselect',
                        'options' => $category_options,
                        'default' => [],
                    ],
                    [
                        'title'   => __('Exclude Tags', 'gtin-product-feed-for-google-shopping'),
                        'id'      => 'gswc_feed_exclude_tags',
                        'type'    => 'multiselect',
                        'options' => $tag_options,
                        'default' => [],
                    ],
                ],
            ],
            [
                'title'  => __('Price', 'gtin-product-feed-for-google-shopping'),
                'type'   => 'gswc_field_row',
                'fields' => [
                    [
                        'title'             => __('Minimum Price', 'gtin-product-feed-for-google-shopping'),
                        'id'                => 'gswc_feed_min_price',
                        'type'              => 'number',
                        'default'           => '',
                        'custom_attributes' => ['min' => '0', 'step' => '0.01'],
                    ],
                    [
                        'title'             => __('Maximum Price', 'gtin-product-feed-for-google-shopping'),
                        'id'                => 'gswc_feed_max_price',
                        'type'              => 'number',
                        'default'           => '',
                        'custom_attributes' => ['min' => '0', 'step' => '0.01'],
                    ],
                    [
                        'title'             => __('Product Limit', 'gtin-product-feed-for-google-shopping'),
                        'id'                => 'gswc_feed_limit',
                        'type'              => 'number',
                        'default'           => 0,
                        'custom_attributes' => ['min' => '0'],
                    ],
                ],
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
                'title'   => __('Title', 'gtin-product-feed-for-google-shopping'),
                'type'    => 'gswc_inline_pair',
                'example' => __('Blue T-Shirt', 'gtin-product-feed-for-google-shopping'),
                'fields'  => [
                    [
                        'label'   => __('Prefix', 'gtin-product-feed-for-google-shopping'),
                        'id'      => 'gswc_feed_title_prefix',
                        'default' => '',
                    ],
                    [
                        'label'   => __('Suffix', 'gtin-product-feed-for-google-shopping'),
                        'id'      => 'gswc_feed_title_suffix',
                        'default' => '',
                    ],
                ],
            ],
            [
                'title'   => __('Description', 'gtin-product-feed-for-google-shopping'),
                'type'    => 'gswc_inline_pair',
                'example' => __('Comfortable cotton t-shirt...', 'gtin-product-feed-for-google-shopping'),
                'fields'  => [
                    [
                        'label'   => __('Prefix', 'gtin-product-feed-for-google-shopping'),
                        'id'      => 'gswc_feed_desc_prefix',
                        'default' => '',
                    ],
                    [
                        'label'   => __('Suffix', 'gtin-product-feed-for-google-shopping'),
                        'id'      => 'gswc_feed_desc_suffix',
                        'default' => '',
                    ],
                ],
            ],
        ];
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
            'google' => ['name' => 'Google Shopping', 'pro' => false],
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
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox"
                                           <?php checked($id === 'google'); ?>
                                           disabled />
                                </td>
                                <td>
                                    <strong><?php echo esc_html($channel['name']); ?></strong>
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
