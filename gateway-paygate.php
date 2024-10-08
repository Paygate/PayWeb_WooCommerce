<?php
/**
 * Plugin Name: Paygate for WooCommerce
 * Plugin URI: https://github.com/PayGate/PayWeb_WooCommerce
 * Description: Receive payments using the South African Paygate payments provider.
 * Author: Payfast (Pty) Ltd
 * Author URI: https://payfast.io/
 * Version: 1.4.9
 * Requires at least: 5.6
 * Tested up to: 6.6.2
 * WC tested up to: 9.3.2
 * WC requires at least: 6.0
 * Requires PHP: 8.0
 *
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2024 Payfast (Pty) Ltd.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: paygate-payweb-for-woocommerce
 */

add_action('plugins_loaded', 'woocommerce_paygate_init', 0);

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 * @noinspection PhpUnused
 */

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
add_action('woocommerce_receipt_paygate', 'custom_function_after_order_placed', 10, 1);

function custom_function_after_order_placed($order_id) {
    wp_enqueue_script(
        'classic-checkout',
        plugins_url( 'assets-classic/js/classic-checkout.js', __FILE__ ),
        array(),
        '1.4.9',
        true
    );
}
function woocommerce_paygate_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    register_activation_hook(__FILE__, 'paygate_add_cron_hook');
    register_deactivation_hook(__FILE__, 'paygate_remove_cron_hook');

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
        'woocommerce_order_action_wc_custom_order_action_paygate',
        array(WC_Gateway_PayGate_Admin_Actions::class, 'paygate_order_query_action')
    );

    paygate_add_cron_hook();

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

function paygate_every_ten_minutes($schedules)
{
    $schedules['paygate_every_ten_minutes'] = [
        'interval' => 600,
        'display'  => __('Paygate Every 10 Minutes'),
    ];

    return $schedules;
}

function paygate_add_cron_hook()
{
    // Cron job
    add_filter('cron_schedules', 'paygate_every_ten_minutes'); // Custom 10 minute schedule
    add_action('paygate_query_cron_hook', array(WC_Gateway_PayGate_Cron::class, 'paygate_order_query_cron'));

    $nxt = wp_next_scheduled('paygate_query_cron_hook');
    if (!$nxt) {
        wp_schedule_event(time(), 'paygate_every_ten_minutes', 'paygate_query_cron_hook');
    }
}

function paygate_remove_cron_hook()
{
    while (wp_next_scheduled('paygate_query_cron_hook')) {
        wp_clear_scheduled_hook('paygate_query_cron_hook');
    }
}

add_action('before_woocommerce_init', 'woocommerce_paygatepayweb_declare_hpos_compatibility');

/**
 * Declares support for HPOS.
 *
 * @return void
 */
function woocommerce_paygatepayweb_declare_hpos_compatibility()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

add_action('woocommerce_blocks_loaded', 'woocommerce_paygate_woocommerce_blocks_support');

function woocommerce_paygate_woocommerce_blocks_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once dirname(__FILE__) . '/classes/WC_Gateway_PayGate_Blocks_Support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_Gateway_PayGate_Blocks_Support);
            }
        );
    }
}
