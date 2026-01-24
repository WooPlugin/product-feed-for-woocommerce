<?php
/**
 * Product Fields
 *
 * Adds Google Shopping tab with GTIN, Brand, MPN fields to WooCommerce products.
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Product_Fields
 */
class GSWC_Product_Fields {

    /**
     * Field definitions
     *
     * @var array
     */
    private static $fields = [
        'gtin' => [
            'label'       => 'GTIN',
            'description' => 'Global Trade Item Number (UPC, EAN, ISBN). Leave empty to use WooCommerce Inventory GTIN field.',
            'placeholder' => 'e.g., 012345678901',
            'type'        => 'text',
        ],
        'mpn' => [
            'label'       => 'MPN',
            'description' => 'Manufacturer Part Number',
            'placeholder' => 'e.g., ABC123',
            'type'        => 'text',
        ],
        'brand' => [
            'label'       => 'Brand',
            'description' => 'Product brand or manufacturer',
            'placeholder' => 'e.g., Apple',
            'type'        => 'text',
        ],
        'condition' => [
            'label'       => 'Condition',
            'description' => 'Product condition. Leave as default to use store setting.',
            'type'        => 'select',
            'options'     => [
                ''            => 'Use default',
                'new'         => 'New',
                'refurbished' => 'Refurbished',
                'used'        => 'Used',
            ],
        ],
        'identifier_exists' => [
            'label'       => 'Identifier exists',
            'description' => 'Set to No for custom or handmade products without a GTIN/MPN.',
            'type'        => 'select',
            'options'     => [
                ''    => 'Use default (Yes)',
                'yes' => 'Yes',
                'no'  => 'No',
            ],
        ],
    ];

    /**
     * Initialize product fields
     */
    public static function init() {
        // Add custom product data tab
        add_filter('woocommerce_product_data_tabs', [__CLASS__, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [__CLASS__, 'add_product_data_panel']);

        // Save fields
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_fields']);
        add_action('woocommerce_save_product_variation', [__CLASS__, 'save_variation_fields'], 10, 2);
        add_action('woocommerce_product_quick_edit_save', [__CLASS__, 'save_quick_edit_fields']);
        add_action('woocommerce_rest_insert_product_object', [__CLASS__, 'save_fields_from_rest'], 10, 3);

        // Variable products - variation fields
        add_action('woocommerce_variation_options_pricing', [__CLASS__, 'add_variation_fields'], 10, 3);

        // REST API support
        add_filter('woocommerce_rest_prepare_product_object', [__CLASS__, 'add_fields_to_rest'], 10, 3);

        // Quick edit support
        add_action('woocommerce_product_quick_edit_end', [__CLASS__, 'quick_edit_fields']);

        // Admin styles for tab
        add_action('admin_head', [__CLASS__, 'add_tab_styles']);
    }

    /**
     * Add Google Shopping tab to product data tabs
     *
     * @param array $tabs Existing tabs.
     * @return array
     */
    public static function add_product_data_tab($tabs) {
        $tabs['gswc_google_shopping'] = [
            'label'    => __('Google Shopping', 'product-feed-for-woocommerce'),
            'target'   => 'gswc_google_shopping_data',
            'class'    => ['show_if_simple', 'show_if_variable', 'show_if_external'],
            'priority' => 25, // After Inventory (20), before Shipping (30)
        ];
        return $tabs;
    }

    /**
     * Add Google Shopping panel content
     */
    public static function add_product_data_panel() {
        global $post;
        $promotion = GSWC_Remote_Data::get_promotion();
        ?>
        <div id="gswc_google_shopping_data" class="panel woocommerce_options_panel">
            <?php if ($promotion) : ?>
                <div class="gswc-panel-promo gswc-promo-<?php echo esc_attr($promotion['style'] ?? 'highlight'); ?>">
                    <span class="gswc-promo-badge"><?php echo esc_html($promotion['title']); ?></span>
                    <span class="gswc-promo-text"><?php echo esc_html($promotion['message']); ?></span>
                    <?php if (!empty($promotion['code'])) : ?>
                        <code class="gswc-promo-code"><?php echo esc_html($promotion['code']); ?></code>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(GSWC_PRO_URL); ?>" class="gswc-promo-link" target="_blank">
                        <?php esc_html_e('Get Pro', 'product-feed-for-woocommerce'); ?> →
                    </a>
                </div>
            <?php endif; ?>

            <div class="options_group">
                <p class="form-field">
                    <span class="gswc-panel-title">
                        <?php esc_html_e('Product Identifiers', 'product-feed-for-woocommerce'); ?>
                    </span>
                    <span class="gswc-panel-desc">
                        <?php esc_html_e('Required for Google Merchant Center approval.', 'product-feed-for-woocommerce'); ?>
                    </span>
                </p>
                <?php
                foreach (self::$fields as $key => $field) {
                    $value = get_post_meta($post->ID, '_gswc_' . $key, true);
                    $field_type = $field['type'] ?? 'text';

                    if ($field_type === 'select') {
                        woocommerce_wp_select([
                            'id'          => '_gswc_' . $key,
                            'label'       => $field['label'],
                            'description' => $field['description'],
                            'desc_tip'    => true,
                            'options'     => $field['options'],
                            'value'       => $value,
                        ]);
                    } else {
                        woocommerce_wp_text_input([
                            'id'          => '_gswc_' . $key,
                            'label'       => $field['label'],
                            'description' => $field['description'],
                            'desc_tip'    => true,
                            'placeholder' => $field['placeholder'] ?? '',
                            'value'       => $value,
                        ]);
                    }
                }
                ?>
            </div>

            <?php self::output_pro_upsell(); ?>
        </div>
        <?php
    }

    /**
     * Output Pro upsell section
     */
    private static function output_pro_upsell() {
        $pro = GSWC_Remote_Data::get_pro_pricing();
        ?>
        <div class="options_group gswc-pro-upsell-panel">
            <div class="gswc-pro-card">
                <div class="gswc-pro-features">
                    <p class="gswc-pro-title">
                        <strong><?php esc_html_e('Need more options?', 'product-feed-for-woocommerce'); ?></strong>
                        <span><?php esc_html_e('Upgrade to Pro', 'product-feed-for-woocommerce'); ?></span>
                    </p>
                    <ul>
                        <li><?php esc_html_e('Age Group, Gender, Color, Size fields', 'product-feed-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Google Product Category mapping', 'product-feed-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Custom Labels for campaigns', 'product-feed-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Scheduled auto-updates', 'product-feed-for-woocommerce'); ?></li>
                        <li><?php esc_html_e('Facebook, Pinterest, TikTok, Snapchat feeds', 'product-feed-for-woocommerce'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url($pro['url']); ?>" class="button button-primary gswc-pro-btn" target="_blank">
                        <?php
                        printf(
                            /* translators: %s: price */
                            esc_html__('Get Pro - %s/year', 'product-feed-for-woocommerce'),
                            esc_html('$' . $pro['price'])
                        );
                        ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add tab styles
     */
    public static function add_tab_styles() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'product') {
            return;
        }
        ?>
        <style>
            /* Tab icon */
            #woocommerce-product-data ul.wc-tabs li.gswc_google_shopping_options a::before {
                content: "\f174";
                font-family: dashicons;
            }

            /* Panel title */
            .gswc-panel-title {
                display: block;
                font-weight: 600;
                font-size: 14px;
                color: #1e1e1e;
                margin-bottom: 4px;
            }

            .gswc-panel-desc {
                display: block;
                font-size: 12px;
                color: #757575;
                font-style: italic;
            }

            /* Pro upsell panel */
            .gswc-pro-upsell-panel {
                padding: 16px;
                border-top: 1px solid #e0e0e0;
                margin-top: 12px;
                background: #f9fafb;
            }

            /* Pro card */
            .gswc-pro-card {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }

            /* Panel promo banner (top of panel) */
            .gswc-panel-promo {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                padding: 12px 16px;
                font-size: 13px;
                margin: 0;
            }

            .gswc-panel-promo.gswc-promo-highlight {
                background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                border-bottom: 1px solid #3b82f6;
            }

            .gswc-panel-promo.gswc-promo-urgent {
                background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
                border-bottom: 1px solid #ef4444;
            }

            .gswc-panel-promo.gswc-promo-subtle {
                background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
                border-bottom: 1px solid #9ca3af;
            }

            .gswc-promo-badge {
                background: #3b82f6;
                color: #fff;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .gswc-promo-urgent .gswc-promo-badge {
                background: #ef4444;
            }

            .gswc-promo-text {
                color: #1f2937;
                font-weight: 500;
            }

            .gswc-promo-code {
                background: rgba(255,255,255,0.7);
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: 600;
            }

            .gswc-promo-link {
                margin-left: auto;
                color: #1e40af;
                font-weight: 600;
                text-decoration: none;
            }

            .gswc-promo-link:hover {
                text-decoration: underline;
            }

            .gswc-promo-urgent .gswc-promo-link {
                color: #991b1b;
            }

            /* Pro features */
            .gswc-pro-features {
                padding: 16px;
            }

            .gswc-pro-title {
                margin: 0 0 12px 0;
                font-size: 14px;
                color: #1e1e1e;
            }

            .gswc-pro-features ul {
                margin: 0 0 16px 0;
                padding: 0;
                list-style: none;
            }

            .gswc-pro-features li {
                position: relative;
                padding-left: 20px;
                margin-bottom: 6px;
                font-size: 13px;
                color: #374151;
            }

            .gswc-pro-features li::before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #16a34a;
                font-weight: 600;
            }

            .gswc-pro-btn {
                background: linear-gradient(135deg, #4285f4, #34a853) !important;
                border: none !important;
                color: #fff !important;
                padding: 8px 16px !important;
                font-weight: 600 !important;
            }

            .gswc-pro-btn:hover {
                opacity: 0.9;
            }
        </style>
        <?php
    }

    /**
     * Save product fields
     *
     * @param int $post_id Product ID.
     */
    public static function save_product_fields($post_id) {
        foreach (self::$fields as $key => $field) {
            $field_key = '_gswc_' . $key;
            if (isset($_POST[$field_key])) {
                update_post_meta($post_id, $field_key, sanitize_text_field(wp_unslash($_POST[$field_key])));
            }
        }
    }

    /**
     * Save simple product fields
     *
     * @param int $post_id Product ID.
     */
    public static function save_simple_product_fields($post_id) {
        foreach (self::$fields as $key => $field) {
            $field_key = '_gswc_' . $key;
            if (isset($_POST[$field_key])) {
                update_post_meta($post_id, $field_key, sanitize_text_field(wp_unslash($_POST[$field_key])));
            }
        }
    }

    /**
     * Add fields to product variations
     *
     * @param int     $loop           Variation loop index.
     * @param array   $variation_data Variation data.
     * @param WP_Post $variation      Variation post object.
     */
    public static function add_variation_fields($loop, $variation_data, $variation) {
        echo '<div class="gswc-variation-fields">';

        foreach (self::$fields as $key => $field) {
            $field_id = '_gswc_' . $key;
            $value = get_post_meta($variation->ID, $field_id, true);
            $field_type = $field['type'] ?? 'text';
            ?>
            <p class="form-row form-row-first">
                <label for="<?php echo esc_attr($field_id . '_' . $loop); ?>">
                    <?php echo esc_html($field['label']); ?>
                </label>
                <?php if ($field_type === 'select') : ?>
                    <select id="<?php echo esc_attr($field_id . '_' . $loop); ?>"
                            name="<?php echo esc_attr($field_id . '[' . $loop . ']'); ?>"
                            class="short">
                        <?php foreach ($field['options'] as $opt_value => $opt_label) : ?>
                            <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                                <?php echo esc_html($opt_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else : ?>
                    <input type="text"
                           id="<?php echo esc_attr($field_id . '_' . $loop); ?>"
                           name="<?php echo esc_attr($field_id . '[' . $loop . ']'); ?>"
                           value="<?php echo esc_attr($value); ?>"
                           placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"
                           class="short">
                <?php endif; ?>
            </p>
            <?php
        }

        echo '</div>';
    }

    /**
     * Save variation fields
     *
     * @param int $variation_id Variation ID.
     * @param int $i            Loop index.
     */
    public static function save_variation_fields($variation_id, $i) {
        foreach (self::$fields as $key => $field) {
            $field_key = '_gswc_' . $key;
            if (isset($_POST[$field_key][$i])) {
                update_post_meta($variation_id, $field_key, sanitize_text_field(wp_unslash($_POST[$field_key][$i])));
            }
        }
    }

    /**
     * Add fields to REST API response
     *
     * @param WP_REST_Response $response Response object.
     * @param WC_Product       $product  Product object.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response
     */
    public static function add_fields_to_rest($response, $product, $request) {
        $data = $response->get_data();

        $data['google_shopping'] = [
            'gtin'              => get_post_meta($product->get_id(), '_gswc_gtin', true),
            'mpn'               => get_post_meta($product->get_id(), '_gswc_mpn', true),
            'brand'             => get_post_meta($product->get_id(), '_gswc_brand', true),
            'condition'         => get_post_meta($product->get_id(), '_gswc_condition', true),
            'identifier_exists' => get_post_meta($product->get_id(), '_gswc_identifier_exists', true),
        ];

        $response->set_data($data);
        return $response;
    }

    /**
     * Save fields from REST API request
     *
     * @param WC_Product      $product  Product object.
     * @param WP_REST_Request $request  Request object.
     * @param bool            $creating Whether creating new product.
     */
    public static function save_fields_from_rest($product, $request, $creating) {
        $feed_data = $request->get_param('google_shopping');

        if (is_array($feed_data)) {
            foreach (self::$fields as $key => $field) {
                if (isset($feed_data[$key])) {
                    update_post_meta($product->get_id(), '_gswc_' . $key, sanitize_text_field($feed_data[$key]));
                }
            }
        }
    }

    /**
     * Quick edit fields
     */
    public static function quick_edit_fields() {
        ?>
        <br class="clear">
        <label class="alignleft">
            <span class="title"><?php esc_html_e('GTIN', 'product-feed-for-woocommerce'); ?></span>
            <span class="input-text-wrap">
                <input type="text" name="_gswc_gtin" class="text" value="">
            </span>
        </label>
        <label class="alignleft">
            <span class="title"><?php esc_html_e('Brand', 'product-feed-for-woocommerce'); ?></span>
            <span class="input-text-wrap">
                <input type="text" name="_gswc_brand" class="text" value="">
            </span>
        </label>
        <?php
    }

    /**
     * Save quick edit fields
     *
     * @param WC_Product $product Product object.
     */
    public static function save_quick_edit_fields($product) {
        if (isset($_REQUEST['_gswc_gtin'])) {
            update_post_meta($product->get_id(), '_gswc_gtin', sanitize_text_field(wp_unslash($_REQUEST['_gswc_gtin'])));
        }
        if (isset($_REQUEST['_gswc_brand'])) {
            update_post_meta($product->get_id(), '_gswc_brand', sanitize_text_field(wp_unslash($_REQUEST['_gswc_brand'])));
        }
    }

    /**
     * Get product field value
     *
     * @param int    $product_id Product ID.
     * @param string $field      Field key (gtin, mpn, brand).
     * @return string
     */
    public static function get_field($product_id, $field) {
        $value = get_post_meta($product_id, '_gswc_' . $field, true);

        // Fallback to WooCommerce built-in GTIN field (WC 9.2+) if our field is empty
        if (empty($value) && $field === 'gtin') {
            $product = wc_get_product($product_id);
            if ($product && method_exists($product, 'get_global_unique_id')) {
                $value = $product->get_global_unique_id();
            }
        }

        return $value;
    }

    /**
     * Get all fields for a product (Pro only)
     *
     * @param int $product_id Product ID.
     * @return array
     */
    public static function get_all_fields($product_id) {
        $values = [];
        foreach (self::$fields as $key => $field) {
            $values[$key] = self::get_field($product_id, $key);
        }
        return $values;
    }
}
