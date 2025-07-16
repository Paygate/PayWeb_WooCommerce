=== Paygate for WooCommerce ===
Contributors: appinlet
Tags: woocommerce, payment, paygate, ecommerce, credit card
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.6.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This is the official Paygate extension to receive payments for WooCommerce.

== Description ==

The Paygate plugin for WooCommerce lets you accept online payments, including credit and debit cards, QR code payments with Zapper, digital wallets like MasterPass and other payment methods like SiD Secure EFT, PayPal, Mobicred, and MoMoPay.

== Why Choose Paygate? ==

We provide a secure checkout experience for your shoppers with a wealth of payment methods to choose from, knowing that intelligent fraud protection engines monitor your transactions around the clock.

== FAQ's ==

= Does this require a Paygate merchant account? =

Yes! You need to sign up with Payfast to receive an Encryption key and Paygate ID for this gateway to function. You can do so at [payfast.io](https://payfast.ioza) or by emailing [support@payfast.help](mailto:support@payfast.help).

= Does this require an SSL certificate? =

We do recommend obtaining an SSL certificate to allow an additional layer of safety for your online shoppers.

= Where can I find API documentation? =

For help setting up and configuring the Paygate plugin, please refer to our [user guide](https://github.com/PayGate/PayWeb_WooCommerce).

= I need some assistance. Whom can I contact? =

Need help to configure this plugin? Feel free to connect with our Payfast Support Team by emailing us at [support@payfast.help](mailto:support@payfast.help).

== Screenshots ==
1. WooCommerce Admin Payments Screen
2. WooCommerce Admin Paygate Primary Settings
3. WooCommerce Admin Paygate Additional Settings
4. WooCommerce Admin Paygate Additional Settings continued

== Changelog ==
= 1.6.0 - 2025-07-16 =
 * Resolved a fatal error that could occur when retrieving order notes for invalid or missing orders.
 * Updated to the Payfast Common Library v1.4.0 for improved payment processing.
 * Tested on WooCommerce 9.9 and WordPress 6.8.
 * Code quality and security fixes.

= 1.5.0 - 2024-12-10 =
 *  Integration with the Payfast common library for streamlined payment processing.
 *  Full compatibility with PHP 8.2, ensuring optimal performance on the latest platform version.
 *  Enhanced code quality through refactoring and adherence to modern coding standards.
 *  Fixed initiate_transaction method firing more than once.
 *  Tested on WooCommerce 9.4.1 and WordPress 6.7.

= 1.4.9 - 2024-10-08 =
 * Tested on WooCommerce 9.3.2, PHP 8.1 and WordPress 6.6.2.
 * Fix inline script blocking redirect to pay page.

= 1.4.8 - 2024-05-28 =
 * Tested on WooCommerce 8.9.1, PHP 8.1 and WordPress 6.5.3.
 * Fix payment types compatibility.

= 1.4.7 - 2023-11-22 =
 * Tested on WooCommerce 8.3.1, PHP 8.0 and WordPress 6.4.1.
 * Add support for HPOS and Blocks.
 * Add Apple Pay, Samsung Pay and RCS Payment Types.
 * Other fixes and improvements.

= 1.4.6 - 2022-07-14 =
 * Tested on WooCommerce 6.7.0, PHP 8.0 and WordPress 6.0.1.
 * Fix multi-domain multisite network activation.
 * Fix invalid checksum message if order is already paid.

[See changelog for all versions](https://raw.githubusercontent.com/PayGate/PayWeb_WooCommerce/master/CHANGELOG.md).

== Upgrade Notice ==
= 1.5.0 - 2024-12-10 =
 *  Integration with the Payfast common library for streamlined payment processing.
 *  Full compatibility with PHP 8.2, ensuring optimal performance on the latest platform version.
 *  Enhanced code quality through refactoring and adherence to modern coding standards.
 *  Fixed initiate_transaction method firing more than once.
 *  Tested on WooCommerce 9.4.1 and WordPress 6.7.

= 1.4.9 - 2024-10-08 =
 * Tested on WooCommerce 9.3.2, PHP 8.1 and WordPress 6.6.2.
 * Fix inline script blocking redirect to pay page.

= 1.4.8 - 2024-05-28 =
 * Tested on WooCommerce 8.9.1, PHP 8.1 and WordPress 6.5.3.
 * Fix payment types compatibility.
