# Changelog

## [[1.6.0]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.6.0)

### Fixed

- Resolved a fatal error that could occur when retrieving order notes for invalid or missing orders.

### Added

- Updated to the Payfast Common Library v1.4.0 for improved payment processing.

### Tested

- Tested on WooCommerce 9.9 and WordPress 6.8.

### Updated

- Code quality and security fixes.

## [[1.5.0]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.5.0)

### Added

- Integration with the Payfast common library for streamlined payment processing.
- Full compatibility with PHP 8.2, ensuring optimal performance on the latest platform version.
- Enhanced code quality through refactoring and adherence to modern coding standards.

### Fixed

- **Initiate Transaction** method firing more than once.

### Tested

- Tested on WooCommerce 9.4.1 and WordPress 6.7.

## [[1.4.9]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.9)

### Fixed

- Fixed inline script blocking redirect to pay page.

### Tested

- Tested on WooCommerce 9.3.2, PHP 8.1, and WordPress 6.6.2.

## [[1.4.8]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.8)

### Fixed

- Fixed payment types compatibility.

### Tested

- Tested on WooCommerce 8.9.1, PHP 8.1, and WordPress 6.5.3.

## [[1.4.7]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.7)

### Added

- Support for HPOS and Blocks.
- Added Apple Pay, Samsung Pay, and RCS payment types.

### Fixed

- Other fixes and improvements.

### Tested

- Tested on WooCommerce 8.3.1, PHP 8.0, and WordPress 6.4.1.

## [[1.4.6]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.6)

### Fixed

- Fixed multi-domain multisite network activation.
- Fixed invalid checksum message if order is already paid.

### Tested

- Tested on WooCommerce 6.7.0, PHP 8.0, and WordPress 6.0.1.

## [[1.4.5]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.5)

### Added

- Implemented payment type filter hooks.
- Updated Masterpass to Scan to Pay.
- Added transient in notify handler to curb duplicate transactions.

### Tested

- Tested on WooCommerce 6.0 and WordPress 5.8.

## [[1.4.4]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.4)

### Added

- Added PayPal payment type.
- Moved plugin to WordPress.org.

## [[1.4.3]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.3)

### Added

- Added SnapScan payment type.
- Added cron job for query function on orders older than 60 minutes.

### Fixed

- Fixed error messaging on canceled and declined transactions.
- Fixed incorrect order note on transaction declined for Notify method.

### Tested

- Tested on WooCommerce 5.3 and WordPress 5.7.

## [[1.4.2]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.2)

### Added

- Added PayGate Plus logo option.
- Switched to SVG payment logo.

### Fixed

- Fixed an issue where PayVault did not work while Payment Type selection was active.
- Removed iFrame support.
- Tweaked order notes and default gateway title.
- Improved WC notices handling.

### Tested

- Tested on WooCommerce 4.9 and WordPress 5.6.

## [[1.4.1]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.1)

### Added

- Added alternate cart handling if cart is not cleared upon successful transaction.

### Tested

- Compatibility with WordPress 5.5.1.

## [[1.4.0]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.4.0)

### Added

- Added payment types selection on checkout (SiD, eWallet, etc.).
- Added custom order meta to payment reference.
- Improved error messaging.

### Fixed

- Fixed SQL syntax error in PayWeb query cron.
- Fixed cart not clearing on some configurations.
- Code quality improvements and refactor.

### Tested

- Tested with WooCommerce 4.3.1.

## [[1.3.2]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.3.2)

### Fixed

- Fixed session bug.
- Improved query reliability.

### Tested

- Tested with WooCommerce 4.3.0.

## [[1.3.1]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.3.1)

### Added

- Minified style and scripts for improved performance.
- Added Order Transaction Query function under the order menu.
- Added PayGate Query cron function for 'pending' orders PayGate orders.

### Fixed

- PayVault Bugfix for WooCommerce 4.2.0.
- Fixed the 'pay' link from the order-pay page and account link.
- Tested with WooCommerce 4.2.0 and WordPress 5.4.2.

## [[1.3.0]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.3.0)

### Added

- Combined iFrame and Redirect implementations.
- PHP 7.3 compatible.

### Fixed

- Fixed SSL Verify which breaks on some servers.
- Fixed bugs and improved card vaulting.

### Tested

- WooCommerce 4.0 compatible.

## [[1.2.0]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/1.2.0i)

### Fixed

- Fixed overflow scroll for smaller iPhones.
- WordPress 5.2 Update - handled WP_Error object.

### Tested

- Tested on WooCommerce 3.6.

## [[1.1.9]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.1.9i)

### Added

- Added support for sequential order number plugins.
- Added support for plugin update icons and 'Tested Version'.

### Fixed

- WordPress 5 compatibility.

## [[1.1.8]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.1.8i)

### Added

- Catered for abandoned carts allowing users to 'edit' cart on failed payment.

### Fixed

- Canceled transactions now have an order status of 'canceled'.

## [[1.1.7]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.1.7i)

### Added

- Added auto-update feature.
- Added check for terms and conditions on "pay_for_order" page.

## [[1.1.6]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.1.6i)

### Fixed

- Use non-conflict jQuery.

## [[1.1.5]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.1.5)

### Fixed

- Applied alternative approach to JS click event.

## [[1.1.4]](https://github.com/Paygate/PayWeb_WooCommerce/releases/tag/v1.1.4)

### Fixed

- Backwards compatibility with older plugin settings.

## [1.1.3]

### Added

- Added options to toggle redirect or notify.
- WooCommerce 3.3 compatibility.

### Fixed

- Fixed double stock reduction on SiD notify.
- Fixed WP debug.log entry when NOTIFY method is accessed directly.
- Fixed notify URL broken on some URL rewrites to HTTPS.
- Fixed redirect response sometimes not captured.

## [1.1.1]

### Fixed

- Fixed WooCommerce compatibility issues including order status on 'Thank You' page and order total.

## [1.1.0]

### Added

- Added PayVault tokenization functionality.

## [1.0.3]

### Fixed

- Updated return method to better handle transaction status and messages.

## [1.0.2]

### Fixed

- Updated plugin to update order status with the notify from PayGate.

## [1.0.1]

### Fixed

- Updated notify function on return from PayGate to echo OK and get Order number.

## [1.0.0]

### Added

- Initial release.
