<?php
/**
 * Remote Data Handler
 *
 * Fetches and caches pricing/promotion data from wooplugin.pro
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Remote_Data
 */
class GSWC_Remote_Data {

    /**
     * API endpoint URL
     */
    const API_URL = 'https://wooplugin.pro/api/pricing.json';

    /**
     * Cache transient key
     */
    const CACHE_KEY = 'gswc_remote_pricing';

    /**
     * Cache duration in seconds (24 hours)
     */
    const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Default pricing data (fallback if API unavailable)
     */
    private static $defaults = [
        'pro' => [
            'price'   => 79,
            'display' => '$7/mo',
            'period'  => 'Billed at $79/year',
            'url'     => 'https://wooplugin.pro/google-shopping-pro',
        ],
        'agency' => [
            'price'   => 299,
            'display' => '$25/mo',
            'period'  => 'Billed at $299/year',
            'url'     => 'https://wooplugin.pro/google-shopping-pro',
        ],
        'features' => [
            'Scheduled feed updates (hourly/daily)',
            'Auto-regenerate on product save',
            'Facebook/Meta Catalog feed',
            'Pinterest feed',
            'TikTok Catalog feed',
            'Bing Shopping feed',
            'Snapchat Catalog feed',
            'Priority email support',
        ],
        'promotion' => null,
    ];

    /**
     * Get pricing data (from cache or fresh fetch)
     *
     * @param bool $force_refresh Force a fresh fetch.
     * @return array
     */
    public static function get_pricing($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        $data = self::fetch_from_api();

        if ($data) {
            set_transient(self::CACHE_KEY, $data, self::CACHE_DURATION);
            return $data;
        }

        // Return defaults if API fetch failed
        return self::$defaults;
    }

    /**
     * Get API URL (filterable for testing)
     *
     * @return string
     */
    private static function get_api_url() {
        return apply_filters('gswc_remote_api_url', self::API_URL);
    }

    /**
     * Fetch data from API
     *
     * @return array|false
     */
    private static function fetch_from_api() {
        $response = wp_remote_get(self::get_api_url(), [
            'timeout' => 5,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['pro'])) {
            return false;
        }

        return $data;
    }

    /**
     * Get Pro pricing info
     *
     * @return array
     */
    public static function get_pro_pricing() {
        $data = self::get_pricing();
        return $data['pro'] ?? self::$defaults['pro'];
    }

    /**
     * Get Pro features list
     *
     * @return array
     */
    public static function get_pro_features() {
        $data = self::get_pricing();
        return $data['features'] ?? self::$defaults['features'];
    }

    /**
     * Get active promotion (if any)
     *
     * @return array|null
     */
    public static function get_promotion() {
        $data = self::get_pricing();
        $promo = $data['promotion'] ?? null;

        // Check if promotion has expired
        if ($promo && !empty($promo['expires'])) {
            $expires = strtotime($promo['expires']);
            if ($expires && $expires < time()) {
                return null;
            }
        }

        return $promo;
    }

    /**
     * Clear cached data
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }
}
