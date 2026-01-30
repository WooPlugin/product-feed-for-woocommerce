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

// Calculate missing data
$gswc_missing_gtin = $gswc_total_products - $gswc_products_with_gtin;
$gswc_missing_brand = $gswc_total_products - $gswc_products_with_brand;

// Count products missing both GTIN and Brand
$gswc_products_missing_both = (int) $GLOBALS['wpdb']->get_var(
    "SELECT COUNT(DISTINCT p.ID)
    FROM {$GLOBALS['wpdb']->posts} p
    LEFT JOIN {$GLOBALS['wpdb']->postmeta} pm_gtin ON p.ID = pm_gtin.post_id AND pm_gtin.meta_key = '_gswc_gtin'
    LEFT JOIN {$GLOBALS['wpdb']->postmeta} pm_brand ON p.ID = pm_brand.post_id AND pm_brand.meta_key = '_gswc_brand'
    WHERE p.post_type = 'product'
    AND p.post_status = 'publish'
    AND (pm_gtin.meta_value IS NULL OR pm_gtin.meta_value = '')
    AND (pm_brand.meta_value IS NULL OR pm_brand.meta_value = '')"
);

// Calculate health percentage (average of GTIN and Brand coverage)
$gswc_gtin_coverage = $gswc_total_products > 0 ? ($gswc_products_with_gtin / $gswc_total_products) * 100 : 0;
$gswc_brand_coverage = $gswc_total_products > 0 ? ($gswc_products_with_brand / $gswc_total_products) * 100 : 0;
$gswc_health_percentage = round(($gswc_gtin_coverage + $gswc_brand_coverage) / 2);

// Get file size if feed exists
$gswc_file_size = $gswc_feed_exists ? size_format(filesize($gswc_feed_file), 1) : '0 B';

// phpcs:enable
?>
<div class="wrap gswc-dashboard">
    <h1 class="gswc-header">
        <span class="gswc-logo">GTIN Product Feed</span>
        <?php esc_html_e('for Google Shopping', 'gtin-product-feed-for-google-shopping'); ?>
    </h1>

    <!-- Store Health Section -->
    <div class="gswc-health-section">
        <h2 class="gswc-health-title">
            <?php esc_html_e('Store Health', 'gtin-product-feed-for-google-shopping'); ?>
            <span class="gswc-health-badge"><?php echo esc_html($gswc_health_percentage); ?>% <?php esc_html_e('Ready', 'gtin-product-feed-for-google-shopping'); ?></span>
        </h2>

        <div class="gswc-stats-grid gswc-stats-grid-5">
            <div class="gswc-stat-card">
                <span class="stat-number"><?php echo esc_html($gswc_total_products); ?></span>
                <span class="stat-label"><?php esc_html_e('Total Products', 'gtin-product-feed-for-google-shopping'); ?></span>
                <span class="stat-indicator complete"></span>
                <span class="stat-status"><?php esc_html_e('Complete', 'gtin-product-feed-for-google-shopping'); ?></span>
            </div>
            <div class="gswc-stat-card">
                <span class="stat-number"><?php echo esc_html($gswc_product_count); ?></span>
                <span class="stat-label"><?php esc_html_e('In Feed', 'gtin-product-feed-for-google-shopping'); ?></span>
                <span class="stat-indicator complete"></span>
                <span class="stat-status"><?php esc_html_e('Complete', 'gtin-product-feed-for-google-shopping'); ?></span>
            </div>
            <div class="gswc-stat-card <?php echo $gswc_products_with_gtin > 0 ? 'success' : ''; ?>">
                <span class="stat-number"><?php echo esc_html($gswc_products_with_gtin); ?></span>
                <span class="stat-label"><?php esc_html_e('With GTIN', 'gtin-product-feed-for-google-shopping'); ?></span>
                <span class="stat-indicator <?php echo $gswc_missing_gtin === 0 ? 'complete' : 'needs-work'; ?>"></span>
                <span class="stat-status"><?php echo $gswc_missing_gtin === 0 ? esc_html__('Complete', 'gtin-product-feed-for-google-shopping') : esc_html__('Needs Work', 'gtin-product-feed-for-google-shopping'); ?></span>
            </div>
            <div class="gswc-stat-card <?php echo $gswc_products_with_brand > 0 ? 'success' : ''; ?>">
                <span class="stat-number"><?php echo esc_html($gswc_products_with_brand); ?></span>
                <span class="stat-label"><?php esc_html_e('With Brand', 'gtin-product-feed-for-google-shopping'); ?></span>
                <span class="stat-indicator <?php echo $gswc_missing_brand === 0 ? 'complete' : 'needs-work'; ?>"></span>
                <span class="stat-status"><?php echo $gswc_missing_brand === 0 ? esc_html__('Complete', 'gtin-product-feed-for-google-shopping') : esc_html__('Needs Work', 'gtin-product-feed-for-google-shopping'); ?></span>
            </div>
            <div class="gswc-stat-card <?php echo $gswc_products_missing_both > 0 ? 'warning' : ''; ?>">
                <span class="stat-number"><?php echo esc_html($gswc_products_missing_both); ?></span>
                <span class="stat-label"><?php esc_html_e('Missing Data', 'gtin-product-feed-for-google-shopping'); ?></span>
                <span class="stat-indicator <?php echo $gswc_products_missing_both === 0 ? 'complete' : 'action-needed'; ?>"></span>
                <span class="stat-status"><?php echo $gswc_products_missing_both === 0 ? esc_html__('Complete', 'gtin-product-feed-for-google-shopping') : esc_html__('Action Needed', 'gtin-product-feed-for-google-shopping'); ?></span>
            </div>
        </div>
    </div>

    <!-- Split Panel: Feed Status | Feed Actions -->
    <div class="gswc-split-panel">
        <!-- Left: Feed Status -->
        <div class="gswc-split-left">
            <div class="gswc-card">
                <h2>
                    <?php esc_html_e('Feed Status', 'gtin-product-feed-for-google-shopping'); ?>
                    <?php if ($gswc_feed_exists) : ?>
                        <span class="gswc-status-badge gswc-status-active"><?php esc_html_e('ACTIVE', 'gtin-product-feed-for-google-shopping'); ?></span>
                    <?php else : ?>
                        <span class="gswc-status-badge gswc-status-inactive"><?php esc_html_e('INACTIVE', 'gtin-product-feed-for-google-shopping'); ?></span>
                    <?php endif; ?>
                </h2>

                <?php if ($gswc_feed_exists) : ?>
                    <div class="gswc-feed-url-section">
                        <label><?php esc_html_e('Feed URL', 'gtin-product-feed-for-google-shopping'); ?></label>
                        <div class="gswc-feed-url-row">
                            <input type="text" class="gswc-feed-url-input" value="<?php echo esc_url($gswc_feed_url); ?>" readonly onclick="this.select();" />
                            <button type="button" class="button button-small gswc-copy-url" data-url="<?php echo esc_attr($gswc_feed_url); ?>">
                                <?php esc_html_e('Copy', 'gtin-product-feed-for-google-shopping'); ?>
                            </button>
                            <a href="<?php echo esc_url($gswc_feed_url); ?>" target="_blank" class="button button-small">
                                <?php esc_html_e('Open', 'gtin-product-feed-for-google-shopping'); ?>
                            </a>
                        </div>
                    </div>

                    <table class="gswc-info-table">
                        <tr>
                            <th><?php esc_html_e('Last Generated', 'gtin-product-feed-for-google-shopping'); ?></th>
                            <td>
                                <?php
                                if ($gswc_last_generated) {
                                    echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $gswc_last_generated));
                                    echo ' <span class="gswc-time-ago">(';
                                    echo esc_html(human_time_diff($gswc_last_generated, time()));
                                    echo ' ' . esc_html__('ago', 'gtin-product-feed-for-google-shopping') . ')</span>';
                                } else {
                                    esc_html_e('Never', 'gtin-product-feed-for-google-shopping');
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Products', 'gtin-product-feed-for-google-shopping'); ?></th>
                            <td><?php echo esc_html($gswc_product_count); ?> <?php esc_html_e('products included', 'gtin-product-feed-for-google-shopping'); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('File Size', 'gtin-product-feed-for-google-shopping'); ?></th>
                            <td><?php echo esc_html($gswc_file_size); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Next Update', 'gtin-product-feed-for-google-shopping'); ?></th>
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
            </div>
        </div>

        <!-- Right: Feed Actions -->
        <div class="gswc-split-right">
            <div class="gswc-card">
                <h2><?php esc_html_e('Feed Actions', 'gtin-product-feed-for-google-shopping'); ?></h2>

                <div class="gswc-actions-list">
                    <button type="button" id="gswc-generate-feed" class="gswc-action-item">
                        <span class="gswc-action-icon dashicons dashicons-update-alt"></span>
                        <span class="gswc-action-label"><?php esc_html_e('Regenerate Feed', 'gtin-product-feed-for-google-shopping'); ?></span>
                    </button>

                    <a href="<?php echo esc_url(admin_url('admin.php?page=gswc-general')); ?>" class="gswc-action-item">
                        <span class="gswc-action-icon dashicons dashicons-admin-generic"></span>
                        <span class="gswc-action-label"><?php esc_html_e('Settings', 'gtin-product-feed-for-google-shopping'); ?></span>
                    </a>

                </div>

                <?php
                $gswc_last_action = get_transient('gswc_last_action');
                if ($gswc_last_action) {
                    delete_transient('gswc_last_action');
                }
                ?>
                <div class="gswc-spinner-wrapper">
                    <span id="gswc-feed-spinner" class="spinner"></span>
                    <span id="gswc-feed-result" class="<?php echo $gswc_last_action ? esc_attr($gswc_last_action['type']) : ''; ?>"><?php echo $gswc_last_action ? esc_html($gswc_last_action['message']) : ''; ?></span>
                </div>

                <?php if ($gswc_missing_gtin > 0) : ?>
                    <div class="gswc-data-to-fix">
                        <h3><?php esc_html_e('Data to Fix', 'gtin-product-feed-for-google-shopping'); ?></h3>
                        <p>
                            <?php
                            printf(
                                /* translators: %d: number of products */
                                esc_html(_n('%d product lacks', '%d products lack', $gswc_missing_gtin, 'gtin-product-feed-for-google-shopping')),
                                esc_html($gswc_missing_gtin)
                            );
                            ?>
                            <strong><?php esc_html_e('GTIN number', 'gtin-product-feed-for-google-shopping'); ?></strong>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
