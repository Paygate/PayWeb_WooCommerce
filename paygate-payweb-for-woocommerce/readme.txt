=== PayGate PayWeb for WooCommerce ===
Contributors: appinlet
Tags: ecommerce, e-commerce, woocommerce, automattic, payment, paygate, app inlet, credit card, payment request
Requires at least: 5.6
Tested up to: 6.0.1
Requires PHP: 7.4
Stable tag: 1.4.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official PayGate extension to receive payments for WooCommerce.

== Description ==

The PayGate PayWeb plugin for WooCommerce lets you accept online payments, including credit and debit cards, QR code payments with Zapper, digital wallets like MasterPass and other payment methods like SiD Secure EFT, PayPal, Mobicred, and MoMoPay.

== Why Choose PayGate? ==

We provide a secure checkout experience for your shoppers with a wealth of payment methods to choose from, knowing that intelligent fraud protection engines monitor your transactions around the clock.

== FAQ's ==

= Does this require a PayGate merchant account? =

Yes! You need to sign up with PayGate to receive an Encryption key and PayGate ID for this gateway to function. You can do so at [www.paygate.co.za](https://www.paygate.co.za) or by emailing [salessa@dpogroup.com](mailto:salessa@dpogroup.com).

= Does this require an SSL certificate? =

We do recommend obtaining an SSL certificate to allow an additional layer of safety for your online shoppers.

= Where can I find API documentation? =

For help setting up and configuring the PayGate PayWeb plugin, please refer to our [user guide](https://github.com/PayGate/PayWeb_WooCommerce).

= I need some assistance. Whom can I contact? =

Need help to configure this plugin? Feel free to connect with our PayGate Support Team by emailing us at [supportsa@dpogroup.com](mailto:supportsa@dpogroup.com) or give us a call at +27 (0) 878 20 2020.

If you get stuck, feel free to contact the PayGate support team at  should you require any assistance.

== Screenshots ==
1. WooCommerce Admin Payments Screen
2. WooCommerce Admin PayGate Primary Settings
3. WooCommerce Admin PayGate Additional Settings
4. WooCommerce Admin PayGate Additional Settings continued

== Changelog ==
= 1.4.6 - 2022-07-14 =
 * Tested on WooCommerce 6.7.0, PHP 8.0 and Wordpress 6.0.1.
 * Fix multi-domain multisite network activation.
 * Fix invalid checksum message if order is already paid. 

= 1.4.5 - 2022-01-04 =
 * Tested on WooCommerce 6.0 and Wordpress 5.8.
 * Implement payment type filter hooks.
 * Update Masterpass to Scan to Pay.
 * Add transient in notify handler to curb duplicate transactions.

= 1.4.4 - 2021-08-31 =
 * Add PayPal payment type.
 * Move plugin to WordPress.org.

= 1.4.3 - 2021-05-13 =
 * Tested on WooCommerce 5.3 and Wordpress 5.7.
 * Add SnapScan payment type.
 * Remove legacy reference to 'paypopup'.
 * Fix error messaging on cancelled and declined transactions.
 * Use pending status for checksum failures.
 * Add cron job for query function on orders older than 60 minutes.
 * Fixed incorrect order note on transaction declined for Notify method.

= 1.4.2 - 2021-01-18 =
 * Tested on WooCommerce 4.9 and Wordpress 5.6.
 * Fix an issue where PayVault did not work while Payment Type selection was active.
 * Remove iFrame support.
 * Add PayGate Plus logo option.
 * Switch to SVG payment logo.
 * Tweak order notes and default gateway title.
 * Alternative WC notices handling.
 * Add more information to logging when enabled.

[See changelog for all versions](https://raw.githubusercontent.com/PayGate/PayWeb_WooCommerce/master/changelog.txt).

== Upgrade Notice ==

= 1.4.4 - 2021-08-31 =
Added the PayPal payment type.

= 1.4.3 - 2021-05-13 =
Added the SnapScan payment type.
Added automated query for pending orders older than 60 minutes.

= 1.4.2 - 2021-01-18 =
Added the PayGate Plus logo option in checkout.
Switched to an SVG payment logo for better retina device support.
Added additional information to logging when enabled.
