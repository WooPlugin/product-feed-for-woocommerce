<?php
/**
 * Dashboard page
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables, not global

$gswc_feed_url = GSWC_Feed_Generator::get_feed_url('google');
$gswc_feed_file = GSWC_Feed_Generator::get_feed_path('google');
$gswc_feed_exists = file_exists($gswc_feed_file);
$gswc_last_generated = get_option('gswc_feed_last_generated', 0);
$gswc_product_count = get_option('gswc_feed_product_count', 0);

// Get product stats
$gswc_total_products = (int) wp_count_posts('product')->publish;

$gswc_products_with_gtin = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(DISTINCT post_id) FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_gswc_gtin' AND meta_value != ''"
);
$gswc_products_with_brand = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(DISTINCT post_id) FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_gswc_brand' AND meta_value != ''"
);

// phpcs:enable
?>
<div class="wrap gswc-dashboard">
    <h1 class="gswc-header">
        <span class="gswc-logo">GTIN Product Feed</span>
        <?php esc_html_e('for Google Shopping', 'gtin-product-feed-for-google-shopping'); ?>
    </h1>

    <div class="gswc-stats-grid">
        <div class="gswc-stat-card">
            <span class="stat-number"><?php echo esc_html($gswc_total_products); ?></span>
            <span class="stat-label"><?php esc_html_e('Total Products', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
        <div class="gswc-stat-card">
            <span class="stat-number"><?php echo esc_html($gswc_product_count); ?></span>
            <span class="stat-label"><?php esc_html_e('In Feed', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
        <div class="gswc-stat-card <?php echo $gswc_products_with_gtin > 0 ? 'success' : ''; ?>">
            <span class="stat-number"><?php echo esc_html($gswc_products_with_gtin); ?></span>
            <span class="stat-label"><?php esc_html_e('With GTIN', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
        <div class="gswc-stat-card <?php echo $gswc_products_with_brand > 0 ? 'success' : ''; ?>">
            <span class="stat-number"><?php echo esc_html($gswc_products_with_brand); ?></span>
            <span class="stat-label"><?php esc_html_e('With Brand', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
    </div>

    <div class="gswc-cards-grid">
        <div class="gswc-card">
            <h2><?php esc_html_e('Feed Status', 'gtin-product-feed-for-google-shopping'); ?></h2>

            <?php if ($gswc_feed_exists) : ?>
                <div class="gswc-notice success">
                    <?php esc_html_e('Feed is active and accessible.', 'gtin-product-feed-for-google-shopping'); ?>
                </div>

                <table class="gswc-info-table">
                    <tr>
                        <th><?php esc_html_e('Feed URL', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td>
                            <div class="gswc-feed-url-row">
                                <input type="text" class="gswc-feed-url-input" value="<?php echo esc_url($gswc_feed_url); ?>" readonly onclick="this.select();" />
                                <a href="<?php echo esc_url($gswc_feed_url); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e('Open feed', 'gtin-product-feed-for-google-shopping'); ?>">↗</a>
                                <button type="button" class="button button-small gswc-copy-url" data-url="<?php echo esc_attr($gswc_feed_url); ?>">
                                    <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Products in Feed', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td id="gswc-feed-count"><?php echo esc_html($gswc_product_count); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Last Generated', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td id="gswc-feed-time">
                            <?php
                            if ($gswc_last_generated) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $gswc_last_generated));
                                echo ' (';
                                echo esc_html(human_time_diff($gswc_last_generated, time()));
                                echo ' ' . esc_html__('ago', 'gtin-product-feed-for-google-shopping') . ')';
                            } else {
                                esc_html_e('Never', 'gtin-product-feed-for-google-shopping');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Auto-Update', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td>
                            <span class="gswc-status-badge"><?php esc_html_e('Manual only', 'gtin-product-feed-for-google-shopping'); ?></span>
                        </td>
                    </tr>
                </table>
            <?php else : ?>
                <div class="gswc-notice info">
                    <?php esc_html_e('Feed has not been generated yet. Click the button below to generate your first feed.', 'gtin-product-feed-for-google-shopping'); ?>
                </div>
            <?php endif; ?>

            <div class="gswc-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=gswc-general')); ?>" class="button">
                    <?php esc_html_e('Settings', 'gtin-product-feed-for-google-shopping'); ?>
                </a>

                <span id="gswc-feed-result"></span>
                <span id="gswc-feed-spinner" class="spinner"></span>
                <button type="button" id="gswc-generate-feed" class="button button-primary">
                    <?php esc_html_e('Generate Feed Now', 'gtin-product-feed-for-google-shopping'); ?>
                </button>
            </div>
        </div>

        <div class="gswc-column-right">
            <div class="gswc-card">
                <h2><?php esc_html_e('Quick Start', 'gtin-product-feed-for-google-shopping'); ?></h2>

            <ol class="gswc-steps">
                <li>
                    <strong><?php esc_html_e('Add product identifiers', 'gtin-product-feed-for-google-shopping'); ?></strong>
                    <p><?php esc_html_e('Edit your products and add GTIN, Brand, and MPN fields in the Inventory tab.', 'gtin-product-feed-for-google-shopping'); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e('Configure feed settings', 'gtin-product-feed-for-google-shopping'); ?></strong>
                    <p>
                        <?php
                        printf(
                            /* translators: %s: link to settings */
                            esc_html__('Set your default brand and other options in %s.', 'gtin-product-feed-for-google-shopping'),
                            '<a href="' . esc_url(admin_url('admin.php?page=gswc-general')) . '">' . esc_html__('Settings', 'gtin-product-feed-for-google-shopping') . '</a>'
                        );
                        ?>
                    </p>
                </li>
                <li>
                    <strong><?php esc_html_e('Generate your feed', 'gtin-product-feed-for-google-shopping'); ?></strong>
                    <p><?php esc_html_e('Click "Generate Feed Now" to create your XML feed.', 'gtin-product-feed-for-google-shopping'); ?></p>
                </li>
                <li>
                    <strong><?php esc_html_e('Submit to Google Merchant Center', 'gtin-product-feed-for-google-shopping'); ?></strong>
                    <p><?php esc_html_e('Copy the feed URL and add it as a scheduled feed in Google Merchant Center.', 'gtin-product-feed-for-google-shopping'); ?></p>
                </li>
            </ol>
            </div>
        </div>
    </div>

    <p class="gswc-footer">
        Google Shopping for WooCommerce v<?php echo esc_html(GSWC_VERSION); ?>
    </p>
</div>
