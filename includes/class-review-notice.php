<?php
/**
 * Review notice functionality
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

/**
 * Class GSWC_Review_Notice
 *
 * Handles the WordPress.org review request notice.
 */
class GSWC_Review_Notice {

    /**
     * Option keys
     */
    const INSTALLED_TIME_KEY = 'gswc_installed_time';
    const DISMISSED_KEY = 'gswc_review_dismissed';
    const DISMISSED_UNTIL_KEY = 'gswc_review_dismissed_until';

    /**
     * Days before showing notice
     */
    const DAYS_BEFORE_NOTICE = 7;

    /**
     * Days to snooze when "Maybe Later" clicked
     */
    const SNOOZE_DAYS = 14;

    /**
     * Initialize
     */
    public static function init() {
        add_action('admin_notices', [__CLASS__, 'maybe_show_notice']);
        add_action('wp_ajax_gswc_dismiss_review', [__CLASS__, 'ajax_dismiss']);
    }

    /**
     * Check if notice should be shown and display it
     */
    public static function maybe_show_notice() {
        // Only show to users who can manage WooCommerce
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Only show on WooCommerce pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'woocommerce') === false) {
            return;
        }

        // Check if permanently dismissed
        if (get_option(self::DISMISSED_KEY) === 'done') {
            return;
        }

        // Check if snoozed
        $dismissed_until = get_option(self::DISMISSED_UNTIL_KEY, 0);
        if ($dismissed_until && time() < $dismissed_until) {
            return;
        }

        // Check if installed long enough
        $installed_time = get_option(self::INSTALLED_TIME_KEY, 0);
        if (!$installed_time) {
            return;
        }

        $days_installed = (time() - $installed_time) / DAY_IN_SECONDS;
        if ($days_installed < self::DAYS_BEFORE_NOTICE) {
            return;
        }

        // Check if feed has been generated at least once (meaningful usage)
        $feed_generated = get_option('gswc_feed_last_generated', 0);
        if (!$feed_generated) {
            return;
        }

        self::render_notice();
    }

    /**
     * Render the review notice
     */
    private static function render_notice() {
        $review_url = 'https://wordpress.org/support/plugin/gtin-product-feed-for-google-shopping/reviews/#new-post';
        $nonce = wp_create_nonce('gswc_dismiss_review');
        ?>
        <div class="notice notice-info is-dismissible gswc-review-notice" data-nonce="<?php echo esc_attr($nonce); ?>">
            <p>
                <strong><?php esc_html_e('Enjoying GTIN Product Feed for Google Shopping?', 'gtin-product-feed-for-google-shopping'); ?></strong>
                <?php esc_html_e('Help other store owners discover it by leaving a quick review!', 'gtin-product-feed-for-google-shopping'); ?>
            </p>
            <p class="gswc-review-actions">
                <a href="<?php echo esc_url($review_url); ?>" target="_blank" class="button button-primary gswc-review-btn" data-action="reviewed">
                    <?php esc_html_e('Rate on WordPress.org', 'gtin-product-feed-for-google-shopping'); ?> &#9734;
                </a>
                <button type="button" class="button gswc-review-btn" data-action="later">
                    <?php esc_html_e('Maybe Later', 'gtin-product-feed-for-google-shopping'); ?>
                </button>
                <button type="button" class="button gswc-review-btn" data-action="done">
                    <?php esc_html_e('Already Did', 'gtin-product-feed-for-google-shopping'); ?>
                </button>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX handler for dismissing the notice
     */
    public static function ajax_dismiss() {
        check_ajax_referer('gswc_dismiss_review', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }

        $action = isset($_POST['dismiss_action']) ? sanitize_text_field(wp_unslash($_POST['dismiss_action'])) : 'later';

        if ($action === 'done' || $action === 'reviewed') {
            // Permanently dismiss
            update_option(self::DISMISSED_KEY, 'done');
            delete_option(self::DISMISSED_UNTIL_KEY);
        } else {
            // Snooze for 14 days
            $snooze_until = time() + (self::SNOOZE_DAYS * DAY_IN_SECONDS);
            update_option(self::DISMISSED_UNTIL_KEY, $snooze_until);
        }

        wp_die();
    }

    /**
     * Record installation time (called on activation)
     */
    public static function record_install_time() {
        if (!get_option(self::INSTALLED_TIME_KEY)) {
            update_option(self::INSTALLED_TIME_KEY, time());
        }
    }
}
