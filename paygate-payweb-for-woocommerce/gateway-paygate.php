<?php
/**
 * Plugin Name: PayGate PayWeb for WooCommerce
 * Plugin URI: https://github.com/PayGate/PayWeb_WooCommerce
 * Description: Receive payments using the South African PayGate PW3 payments provider.
 * Author: PayGate (Pty) Ltd
 * Author URI: https://www.paygate.co.za/
 * Version: 1.4.3
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
 * Text Domain: paygate-payweb-for-woocommerce
 */

add_action('plugins_loaded', 'woocommerce_paygate_init', 0);
// Check that WooCommerce is active before activating plugin
add_action('admin_init', 'woocommerce_paygate_registered');

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 * @noinspection PhpUnused
 */

function woocommerce_paygate_init()
{
    if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return;
    }

    if ( ! class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_basename('classes/WC_Gateway_PayGate.php');

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_paygate_gateway');

    /**
     * Custom order action - query order status
     * Add custom action to order actions select box
     */
    add_action(
        'woocommerce_order_actions',
        array(WC_Gateway_PayGate_Admin_Actions::class, 'paygate_add_order_meta_box_action')
    );
    add_action(
        'woocommerce_order_action_wc_custom_order_action',
        array(WC_Gateway_PayGate_Admin_Actions::class, 'paygate_order_query_action')
    );
    add_action(
        'woocommerce_order_action_wc_custom_order_action',
        array(WC_Gateway_PayGate_Portal::class, 'paygate_order_query_cron')
    );
    add_action('paygate_query_cron_hook', array(WC_Gateway_PayGate_Cron::class, 'paygate_order_query_cron'));

    $nxt = wp_next_scheduled('paygate_query_cron_hook');
    if ( ! $nxt ) {
        wp_schedule_event(time(), 'hourly', 'paygate_query_cron_hook');
    }

    add_action('woocommerce_before_cart', array(WC_Gateway_PayGate::class, 'show_cart_messages'), 10, 1);

} // End woocommerce_paygate_init()

/**
 * Add the gateway to WooCommerce
 *
 * @param $methods
 *
 * @return mixed
 * @since 1.0.0
 */

function woocommerce_add_paygate_gateway($methods)
{
    $methods[] = 'WC_Gateway_PayGate';

    return $methods;
} // End woocommerce_add_paygate_gateway()

function woocommerce_paygate_registered()
{
    if(!paygate_wc_is_installed()) {
        add_action('admin_notices', 'addInvalidPluginNoticePG');
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

function recurseRmdir($dir) {
  $files = array_diff(scandir($dir), array('.','..'));
  foreach ($files as $file) {
    (is_dir("$dir/$file")) ? recurseRmdir("$dir/$file") : unlink("$dir/$file");
  }
  return rmdir($dir);
}

function paygate_wc_is_installed()
{
    $new_folder = 'woocommerce-gateway-paygate-pw3';
    $target = WP_PLUGIN_DIR . '/' . $new_folder;
    $new_file = '/gateway-paygate.php';
    if(file_exists($target . $new_file)) {
        recurseRmdir($target);
    }
    return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}

function addInvalidPluginNoticePG()
{
    echo <<<NOTICE
<div id="message" class="error">
<p>WooCommerce is required for this plugin</p>
</div>
NOTICE
    ;
}
