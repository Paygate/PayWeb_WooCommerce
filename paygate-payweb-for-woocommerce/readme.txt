=== PayGate PayWeb for WooCommerce ===
Contributors: appinlet
Tags: ecommerce, e-commerce, woocommerce, automattic, payment, paygate, app inlet, credit card, payment request
Requires at least: 5.6
Tested up to: 5.8
Requires PHP: 7.4
Stable tag: 1.4.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official PayGate extension to receive payments for WooCommerce.

== Description ==

The PayGate extension for WooCommerce enables you to accept payments including Card, SiD Secure EFT, Zapper, SnapScan, PayPal, Mobicred, MoMoPay and MasterPass via one of South Africa’s most popular payment gateways.

= Why choose PayGate? =

PayGate gives your customers more flexibility including payments using Card, SiD Secure EFT, Zapper, SnapScan, PayPal, Mobicred, MoMoPay and MasterPass.

== Frequently Asked Questions ==

= Does this require a PayGate merchant account? =

Yes! A PayGate merchant account, Encryption key and PayGate ID are required for this gateway to function.

= Does this require an SSL certificate? =

An SSL certificate is recommended for additional safety and security for your customers.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://github.com/PayGate/PayWeb_WooCommerce)

= Where can I get support or talk to other users? =

If you get stuck, feel free to contact the PayGate support team at [support@paygate.co.za](mailto:support@paygate.co.za) should you require any assistance.

== Screenshots ==
1. WooComemrce Admin Payments Screen
2. WooComemrce Admin PayGate Primary Settings
3. WooComemrce Admin PayGate Additional Settings
4. WooComemrce Admin PayGate Additional Settings continued

== Changelog ==

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
