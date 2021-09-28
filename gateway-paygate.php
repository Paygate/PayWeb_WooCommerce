<?php
/**
 * Plugin Name: PayGate PayWeb for WooCommerce Migrator
 * Plugin URI: https://github.com/PayGate/PayWeb_WooCommerce
 * Description: Receive payments using the South African PayGate PW3 payments provider.
 * Author: PayGate (Pty) Ltd
 * Author URI: https://www.paygate.co.za/
 * Version: 1.4.4
 * Requires at least: 4.4
 * Tested up to: 5.8
 * WC tested up to: 5.6.0
 * WC requires at least: 4.9
 *
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2021 PayGate (Pty) Ltd.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-paygate-pw3
 */

$current_folder = plugin_basename(__DIR__);
$new_folder     = 'paygate-payweb-for-woocommerce';
$new_file       = '/gateway-paygate.php';
$source         = WP_PLUGIN_DIR . '/' . $current_folder . '/' . $new_folder;
$target         = WP_PLUGIN_DIR . '/' . $new_folder;
if (file_exists($source . $new_file)) {
    rename($source, $target);
}

$new_plugin = $new_folder . $new_file;
if (is_plugin_inactive($new_plugin)) {
    activate_plugin($new_plugin);
}
deactivate_plugins('woocommerce-gateway-paygate-pw3/gateway-paygate.php');
