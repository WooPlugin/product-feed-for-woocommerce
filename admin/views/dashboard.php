<?php
/**
 * Dashboard page
 *
 * @package Google_Shopping_For_WooCommerce
 */

defined('ABSPATH') || exit;

$feed_url = GSWC_Feed_Generator::get_feed_url('google');
$feed_file = GSWC_Feed_Generator::get_feed_path('google');
$feed_exists = file_exists($feed_file);
$last_generated = get_option('gswc_feed_last_generated', 0);
$product_count = get_option('gswc_feed_product_count', 0);

// Get product stats
$total_products = (int) wp_count_posts('product')->publish;

// Get promotion data
$promotion = GSWC_Remote_Data::get_promotion();
$products_with_gtin = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(DISTINCT post_id) FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_gswc_gtin' AND meta_value != ''"
);
$products_with_brand = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(DISTINCT post_id) FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_gswc_brand' AND meta_value != ''"
);
?>
<div class="wrap gswc-dashboard">
    <h1 class="gswc-header">
        <span class="gswc-logo">GTIN Product Feed</span>
        <?php esc_html_e('for Google Shopping', 'gtin-product-feed-for-google-shopping'); ?>
        <a href="https://wooplugin.pro" target="_blank" class="gswc-brand">by wooplugin.pro</a>
    </h1>

    <?php if ($promotion) : ?>
        <div class="gswc-dashboard-promo gswc-promo-<?php echo esc_attr($promotion['style'] ?? 'highlight'); ?>">
            <div class="gswc-promo-content">
                <span class="gswc-promo-badge"><?php echo esc_html($promotion['title'] ?? __('Special Offer', 'gtin-product-feed-for-google-shopping')); ?></span>
                <span class="gswc-promo-message"><?php echo esc_html($promotion['message']); ?></span>
                <?php if (!empty($promotion['code'])) : ?>
                    <span class="gswc-promo-code-inline">
                        <?php esc_html_e('Code:', 'gtin-product-feed-for-google-shopping'); ?>
                        <code><?php echo esc_html($promotion['code']); ?></code>
                    </span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url(GSWC_PRO_URL); ?>" class="button button-primary" target="_blank">
                <?php esc_html_e('Get Pro', 'gtin-product-feed-for-google-shopping'); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="gswc-stats-grid">
        <div class="gswc-stat-card">
            <span class="stat-number"><?php echo esc_html($total_products); ?></span>
            <span class="stat-label"><?php esc_html_e('Total Products', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
        <div class="gswc-stat-card">
            <span class="stat-number"><?php echo esc_html($product_count); ?></span>
            <span class="stat-label"><?php esc_html_e('In Feed', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
        <div class="gswc-stat-card <?php echo $products_with_gtin > 0 ? 'success' : ''; ?>">
            <span class="stat-number"><?php echo esc_html($products_with_gtin); ?></span>
            <span class="stat-label"><?php esc_html_e('With GTIN', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
        <div class="gswc-stat-card <?php echo $products_with_brand > 0 ? 'success' : ''; ?>">
            <span class="stat-number"><?php echo esc_html($products_with_brand); ?></span>
            <span class="stat-label"><?php esc_html_e('With Brand', 'gtin-product-feed-for-google-shopping'); ?></span>
        </div>
    </div>

    <div class="gswc-cards-grid">
        <div class="gswc-card">
            <h2><?php esc_html_e('Feed Status', 'gtin-product-feed-for-google-shopping'); ?></h2>

            <?php if ($feed_exists) : ?>
                <div class="gswc-notice success">
                    <?php esc_html_e('Feed is active and accessible.', 'gtin-product-feed-for-google-shopping'); ?>
                </div>

                <table class="gswc-info-table">
                    <tr>
                        <th><?php esc_html_e('Feed URL', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td>
                            <div class="gswc-feed-url-row">
                                <input type="text" class="gswc-feed-url-input" value="<?php echo esc_url($feed_url); ?>" readonly onclick="this.select();" />
                                <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button button-small" title="<?php esc_attr_e('Open feed', 'gtin-product-feed-for-google-shopping'); ?>">↗</a>
                                <button type="button" class="button button-small gswc-copy-url" data-url="<?php echo esc_attr($feed_url); ?>">
                                    <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Products in Feed', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td id="gswc-feed-count"><?php echo esc_html($product_count); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Last Generated', 'gtin-product-feed-for-google-shopping'); ?></th>
                        <td id="gswc-feed-time">
                            <?php
                            if ($last_generated) {
                                echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $last_generated));
                                echo ' (';
                                echo esc_html(human_time_diff($last_generated, time()));
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
                            <a href="<?php echo esc_url(GSWC_PRO_URL); ?>" style="color: #4285f4;">
                                <?php esc_html_e('Upgrade to Pro', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            <?php else : ?>
                <div class="gswc-notice info">
                    <?php esc_html_e('Feed has not been generated yet. Click the button below to generate your first feed.', 'gtin-product-feed-for-google-shopping'); ?>
                </div>
            <?php endif; ?>

            <div class="gswc-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=gswc_feed')); ?>" class="button">
                    <?php esc_html_e('Settings', 'gtin-product-feed-for-google-shopping'); ?>
                </a>

                <span id="gswc-feed-result"></span>
                <span id="gswc-feed-spinner" class="spinner"></span>
                <button type="button" id="gswc-generate-feed" class="button button-primary">
                    <?php esc_html_e('Generate Feed Now', 'gtin-product-feed-for-google-shopping'); ?>
                </button>
            </div>
        </div>

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
                            '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=gswc_feed')) . '">' . esc_html__('Settings', 'gtin-product-feed-for-google-shopping') . '</a>'
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

    <p class="gswc-footer">
        Google Shopping for WooCommerce v<?php echo esc_html(GSWC_VERSION); ?> &middot;
        <a href="https://wooplugin.pro/google-shopping-pro" target="_blank">wooplugin.pro</a>
    </p>
</div>
