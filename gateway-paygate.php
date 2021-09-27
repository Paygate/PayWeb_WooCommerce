<?php
/**
 * Plugin Name: PayGate PayWeb3 plugin for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/paygate-payweb-for-woocommerce/
 * Description: Accept payments for WooCommerce using PayGate's PayWeb3 service
 * Version: 1.4.4
 * Requires at least: 4.4
 * Tested up to: 5.8
 * WC tested up to: 5.6.0
 * WC requires at least: 4.9
 *
 * Author: PayGate (Pty) Ltd
 * Author URI: https://www.paygate.co.za/
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * Copyright: © 2021 PayGate (Pty) Ltd.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: paygate-payweb-for-woocommerce
 */

add_action('plugins_loaded', 'woocommerce_paygate_init', 0);

register_activation_hook(__FILE__, 'paygate_payweb_on_plugin_activation');
function paygate_payweb_on_plugin_activation()
{
    $current = plugin_basename(__DIR__);
    $current_file = plugin_basename(__FILE__);
    $new = 'paygate-payweb-for-woocommerce';
    $new_file = str_replace($current, $new, $current_file);
    if($current === 'woocommerce-gateway-paygate-pw3') {
        deactivate_plugins($current_file);
        rename(WP_PLUGIN_DIR . '/' . $current, WP_PLUGIN_DIR . '/' . $new);
        activate_plugin($new_file);
    }
}

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
    if ( ! wp_next_scheduled('paygate_query_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'paygate_query_cron_hook');
    }

    add_action('woocommerce_before_cart', array(WC_Gateway_PayGate::class, 'show_cart_messages'), 10, 1);


    require_once 'classes/updater.class.php';

    if (is_admin()) {
        // Note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug'               => plugin_basename(__FILE__),
            'proper_folder_name' => 'paygate-payweb-for-woocommerce',
            'api_url'            => 'https://api.github.com/repos/linwor/PayWeb_WooCommerce',
            'raw_url'            => 'https://raw.github.com/linwor/PayWeb_WooCommerce/master',
            'github_url'         => 'https://github.com/linwor/PayWeb_WooCommerce',
            'zip_url'            => 'https://github.com/linwor/PayWeb_WooCommerce/archive/master.zip',
            'homepage'           => 'https://github.com/linwor/PayWeb_WooCommerce',
            'sslverify'          => true,
            'requires'           => '4.0',
            'tested'             => '5.6',
            'readme'             => 'README.md',
            'access_token'       => '',
        );

        $wpGitHubUpdater = new WP_GitHub_Updater_PW3($config);

        $wpGitHubUpdater->add_filters();

    }
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

function paygate_wc_is_installed()
{
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
