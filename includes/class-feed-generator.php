<?php
/**
 * Feed Generator
 *
 * Generates XML product feeds for Google Merchant Center.
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Feed_Generator
 */
class GSWC_Feed_Generator {

    /**
     * Initialize feed generator
     */
    public static function init() {
        add_action('gswc_generate_cron', [__CLASS__, 'generate']);
        add_action('wp_ajax_gswc_generate_feed', [__CLASS__, 'ajax_generate']);
    }

    /**
     * Get feed directory path
     *
     * @return string
     */
    public static function get_feed_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/gswc-feeds';
    }

    /**
     * Get feed file path
     *
     * @param string $type Feed type (google, facebook, etc.).
     * @return string
     */
    public static function get_feed_path($type = 'google') {
        return self::get_feed_dir() . '/' . $type . '-feed.xml';
    }

    /**
     * Get feed URL
     *
     * @param string $type Feed type.
     * @return string
     */
    public static function get_feed_url($type = 'google') {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/gswc-feeds/' . $type . '-feed.xml';
    }

    /**
     * AJAX handler for generating feed
     */
    public static function ajax_generate() {
        check_ajax_referer('gswc_feed_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Permission denied.', 'gtin-product-feed-for-google-shopping'));
        }

        $result = self::generate();

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: number of products */
                __('Feed generated with %d products.', 'gtin-product-feed-for-google-shopping'),
                $result['count']
            ),
            'url'     => self::get_feed_url('google'),
            'count'   => $result['count'],
            'timeago' => __('just now', 'gtin-product-feed-for-google-shopping'),
        ]);
    }

    /**
     * Generate product feed
     *
     * @return array|WP_Error
     */
    public static function generate() {
        $feed_dir = self::get_feed_dir();
        if (!file_exists($feed_dir)) {
            wp_mkdir_p($feed_dir);
        }

        // Get products
        $products = self::get_products();

        // Generate Google feed
        $google_enabled = get_option('gswc_feed_google_enabled', 'yes');
        if ($google_enabled === 'yes') {
            $result = self::generate_google_feed($products);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        $count = count($products);
        update_option('gswc_feed_last_generated', time());
        update_option('gswc_feed_product_count', $count);

        return ['count' => $count];
    }

    /**
     * Get products for feed
     *
     * @return array
     */
    private static function get_products() {
        $limit = (int) get_option('gswc_feed_limit', 0);
        $include_outofstock = get_option('gswc_feed_include_outofstock', 'no') === 'yes';

        $args = [
            'status'  => 'publish',
            'limit'   => $limit > 0 ? $limit : -1,
            'orderby' => 'date',
            'order'   => 'DESC',
        ];

        if (!$include_outofstock) {
            $args['stock_status'] = 'instock';
        }

        return wc_get_products($args);
    }

    /**
     * Generate Google Merchant Center feed
     *
     * @param array $products Products to include.
     * @return bool|WP_Error
     */
    private static function generate_google_feed($products) {
        $store_name = get_option('gswc_feed_store_name', get_bloginfo('name'));
        $default_brand = get_option('gswc_feed_default_brand', '');
        $default_condition = get_option('gswc_feed_default_condition', 'new');

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');

        $xml->startElement('channel');
        $xml->writeElement('title', $store_name);
        $xml->writeElement('link', home_url());
        $xml->writeElement('description', get_bloginfo('description'));

        foreach ($products as $product) {
            self::write_product_item($xml, $product, $default_brand, $default_condition);
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss
        $xml->endDocument();

        $content = $xml->outputMemory();
        $file = self::get_feed_path('google');

        $result = file_put_contents($file, $content);
        if ($result === false) {
            return new WP_Error('write_error', __('Failed to write feed file.', 'gtin-product-feed-for-google-shopping'));
        }

        return true;
    }

    /**
     * Write single product item to feed
     *
     * @param XMLWriter  $xml              XML writer.
     * @param WC_Product $product          Product object.
     * @param string     $default_brand    Default brand.
     * @param string     $default_condition Default condition.
     */
    private static function write_product_item($xml, $product, $default_brand, $default_condition) {
        // Skip variable products (include variations instead)
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation && $variation->is_purchasable()) {
                    self::write_product_item($xml, $variation, $default_brand, $default_condition);
                }
            }
            return;
        }

        $id = $product->get_id();
        $gtin = GSWC_Product_Fields::get_field($id, 'gtin');
        $mpn = GSWC_Product_Fields::get_field($id, 'mpn');
        $brand = GSWC_Product_Fields::get_field($id, 'brand') ?: $default_brand;
        $condition = GSWC_Product_Fields::get_field($id, 'condition') ?: $default_condition;
        $identifier_exists = GSWC_Product_Fields::get_field($id, 'identifier_exists');

        $xml->startElement('item');

        // Required fields
        $xml->writeElement('g:id', $product->get_sku() ?: $id);
        $xml->writeElement('title', html_entity_decode(wp_strip_all_tags($product->get_name()), ENT_QUOTES, 'UTF-8'));
        $xml->writeElement('link', $product->get_permalink());

        // Description
        $description = $product->get_description() ?: $product->get_short_description();
        $description = html_entity_decode(wp_strip_all_tags($description), ENT_QUOTES, 'UTF-8');
        $description = mb_substr($description, 0, 5000);
        if ($description) {
            $xml->writeElement('description', $description);
        }

        // Image
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
            if ($image_url) {
                $xml->writeElement('g:image_link', $image_url);
            }
        }

        // Additional images
        $gallery_ids = $product->get_gallery_image_ids();
        $additional_count = 0;
        foreach ($gallery_ids as $gallery_id) {
            if ($additional_count >= 10) {
                break;
            }
            $gallery_url = wp_get_attachment_url($gallery_id);
            if ($gallery_url) {
                $xml->writeElement('g:additional_image_link', $gallery_url);
                $additional_count++;
            }
        }

        // Price
        $price = $product->get_price();
        if ($price) {
            $currency = get_woocommerce_currency();
            $xml->writeElement('g:price', number_format((float) $price, 2, '.', '') . ' ' . $currency);

            // Sale price
            $sale_price = $product->get_sale_price();
            if ($sale_price && $sale_price < $product->get_regular_price()) {
                $xml->writeElement('g:sale_price', number_format((float) $sale_price, 2, '.', '') . ' ' . $currency);
            }
        }

        // Availability
        $availability = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';
        $xml->writeElement('g:availability', $availability);

        // Condition
        $xml->writeElement('g:condition', $condition);

        // Brand
        if ($brand) {
            $xml->writeElement('g:brand', $brand);
        }

        // GTIN
        if ($gtin) {
            $xml->writeElement('g:gtin', $gtin);
        }

        // MPN
        if ($mpn) {
            $xml->writeElement('g:mpn', $mpn);
        }

        // Identifier exists
        if ($identifier_exists === 'no' || (!$gtin && !$mpn && $identifier_exists !== 'yes')) {
            $xml->writeElement('g:identifier_exists', 'false');
        }

        // Categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        if (!empty($categories) && !is_wp_error($categories)) {
            $xml->writeElement('g:product_type', implode(' > ', $categories));
        }

        // Weight
        $weight = $product->get_weight();
        if ($weight) {
            $weight_unit = get_option('woocommerce_weight_unit', 'kg');
            $xml->writeElement('g:shipping_weight', $weight . ' ' . $weight_unit);
        }

        $xml->endElement(); // item
    }
}
