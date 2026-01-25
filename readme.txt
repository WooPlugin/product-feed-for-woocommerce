=== GTIN Product Feed for Google Shopping ===
Contributors: wooplugin
Donate link: https://wooplugin.pro/google-shopping-pro
Tags: google shopping, product feed, woocommerce feed, google merchant center, gtin
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generate Google Shopping product feeds for WooCommerce. Add GTIN, Brand, MPN fields. Google Merchant Center compliant XML feeds. Free & lightweight.

== Description ==

**GTIN Product Feed for Google Shopping** is a lightweight WooCommerce plugin that generates Google Merchant Center compliant product feeds. Add GTIN, Brand, and MPN identifiers to your products and create XML feeds ready for Google Shopping.

= Why Choose This Plugin? =

* **Lightweight & Fast** - No bloat, minimal footprint
* **Google Compliant** - Follows Google's product data specification exactly
* **Actually Free** - No product limits, no artificial restrictions
* **Developer Friendly** - Clean code, REST API support, hooks for customization

= Free Features =

* **GTIN, Brand, MPN Fields** - Add product identifiers required by Google Shopping
* **Google Merchant Center Feed** - Generate compliant XML feeds
* **Variable Product Support** - Full support for WooCommerce variations
* **Unlimited Products** - No artificial product limits (unlike some competitors)
* **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
* **REST API Support** - Manage product fields programmatically
* **Identifier Exists Field** - For products without GTINs (handmade, custom items)
* **Condition Field** - New, refurbished, or used product support

= Pro Features =

Upgrade to [Pro](https://wooplugin.pro/google-shopping-pro) for advanced features:

* **Scheduled Feed Updates** - Automatic regeneration (hourly, daily, weekly)
* **Auto-sync on Changes** - Feed updates when products change
* **Multi-Channel Feeds** - Facebook, Pinterest, TikTok, Bing, Snapchat
* **Category Mapping** - Map WooCommerce categories to Google taxonomy
* **Advanced Filtering** - Include/exclude products by rules
* **Priority Support** - Direct developer support

= Supported Channels =

**Free Version:**

* Google Shopping / Google Merchant Center

**Pro Version:**

* Google Shopping
* Facebook Catalog / Meta
* Pinterest Catalog
* TikTok Shop
* Bing Shopping
* Snapchat Product Catalog

= Compatible With =

* WooCommerce 8.0+
* WordPress 6.0+
* WooCommerce HPOS (High-Performance Order Storage)
* PHP 8.0+
* All themes (Storefront, Astra, flavor theme, etc.)

= Perfect For =

* **Online retailers** selling physical products on Google Shopping
* **Dropshippers** who need GTIN fields for supplier products
* **Agencies** managing client WooCommerce stores
* **Developers** who want clean, extensible code

= How It Works =

1. Install and activate the plugin
2. Add GTIN, Brand, MPN to products (via Google Shopping tab in product editor)
3. Configure feed settings: WooCommerce → Settings → Google Shopping
4. Generate your feed and copy the URL
5. Submit feed URL to Google Merchant Center

= Documentation & Support =

* [Getting Started Guide](https://wooplugin.pro/guides)
* [GitHub Repository](https://github.com/WooPlugin/product-feed-for-woocommerce)
* [Support Forum](https://wordpress.org/support/plugin/gtin-product-feed-for-google-shopping/)

= About WooPlugin =

We build focused, lightweight WooCommerce plugins that do one thing well. No bloat, no upsell nag screens, no tracking. Just clean code that works.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through 'Plugins' menu in WordPress
3. Go to WooCommerce → Settings → Google Shopping to configure
4. Add product identifiers to your products
5. Generate your feed

== Frequently Asked Questions ==

= How do I create a Google Shopping feed? =

1. Install and activate the plugin
2. Go to WooCommerce → Settings → Google Shopping
3. Configure your store details and feed options
4. Click "Generate Feed"
5. Copy the feed URL and add it to Google Merchant Center under Products → Feeds

= What is GTIN and why do I need it? =

GTIN (Global Trade Item Number) includes UPC, EAN, ISBN, and JAN barcodes. Google requires GTIN for most products to appear in Shopping results. Without GTIN, your products may have limited visibility or be disapproved.

= My products don't have GTINs. What do I do? =

Use the "Identifier Exists" field and set it to "No" for products without manufacturer GTINs (handmade items, custom products, vintage goods). Google allows this for qualifying products.

= What product identifiers are supported? =

GTIN (UPC, EAN, ISBN), MPN (Manufacturer Part Number), and Brand.

= Does this work with WooCommerce product variations? =

Yes! You can set GTIN, Brand, and MPN on both parent variable products and individual variations. Variations inherit parent values unless overridden.

= How do I fix Google Merchant Center errors? =

Common fixes:

* **Missing GTIN**: Add GTIN to product or set "Identifier Exists" to No
* **Missing Brand**: Add Brand field to all products
* **Invalid price**: Ensure products have valid prices in WooCommerce
* **Missing images**: Add product images in WooCommerce

= Is the feed URL public? =

The feed URL is accessible without authentication so Google can fetch it. It uses a unique hash to prevent guessing. You can regenerate the URL anytime in settings.

= Can I use this with other Google plugins? =

Yes, this plugin focuses only on product identifiers and feed generation. It's compatible with Google Site Kit, Google Listings & Ads, and other Google plugins.

= How often should I update my feed? =

Google recommends updating feeds at least daily. The free version requires manual regeneration. Pro version includes scheduled automatic updates (hourly, daily, weekly).

= Does this plugin slow down my site? =

No. The plugin only loads on admin pages and during feed generation. It adds no frontend JavaScript or CSS. Feed generation runs via WP-Cron or manual trigger.

= What's the difference between this and Google Listings & Ads? =

Google Listings & Ads is Google's official plugin for syncing products. This plugin gives you more control over product data (GTIN, Brand, MPN fields) and generates standard XML feeds. Many stores use both together.

= Is the feed compatible with Google Merchant Center? =

Yes, the feed follows Google's product data specification exactly.

= Can I use this with other marketplaces? =

Currently supports Google Merchant Center. Pro version adds Facebook, Pinterest, TikTok, Bing, and Snapchat feeds.

= What's the difference between free and Pro? =

Free: Manual feed generation, Google Shopping only, community support.
Pro: Scheduled updates, auto-sync on product changes, multi-channel feeds, priority support.

== Screenshots ==

1. GTIN, Brand, MPN, and Condition fields in the product editor (Google Shopping tab)
2. Plugin settings page with feed configuration options (WooCommerce → Settings → Google Shopping)
3. Feed status showing generated feed URL ready to submit to Google Merchant Center
4. Variable product with GTIN fields available on each variation
5. Filters tab for excluding categories, tags, and setting price ranges
6. Generated XML feed preview showing Google-compliant product data

== Changelog ==

= 1.0.2 =
* Redesign settings to match Pro structure (7 tabs)
* Add Filters tab: exclude categories/tags, price range, product limit
* Add Customization tab: title/description prefix/suffix
* Add License tab for Pro activation
* Improve Pro feature previews with upsell cards
* Feed generator now applies filters and customizations

= 1.0.1 =
* Add WordPress.org review request notice (shows after 7 days of use)

= 1.0.0 =
* Initial release
* GTIN, Brand, MPN, Condition product fields
* Identifier Exists field
* Google Merchant Center XML feed generation
* WooCommerce Settings API integration
* HPOS compatibility
* REST API support
* Pro upsell integration

== Upgrade Notice ==

= 1.0.0 =
Initial release.
