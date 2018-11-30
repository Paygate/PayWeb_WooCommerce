<?php

/**
 * Plugin Name: PayGate PayWeb3 plugin for WooCommerce (Redirect)
 * Plugin URI: https://github.com/RBAtkins0n/PayWeb_WooCommerce
 * Description: Accept payments for WooCommerce using PayGate's PayWeb3 service
 * Version: 1.1.7
 * Author: PayGate (Pty) Ltd
 * Author URI: https://www.paygate.co.za/
 * Developer: App Inlet (Pty) Ltd
 * Developer URI: https://www.appinlet.com/
 *
 * WC requires at least: 2.6
 * WC tested up to: 3.5
 *
 * Copyright: © 2018 PayGate (Pty) Ltd.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

add_action( 'plugins_loaded', 'woocommerce_paygate_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */

function woocommerce_paygate_init()
{

    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    require_once plugin_basename( 'classes/paygate.class.php' );

    add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_paygate_gateway' );

    require_once 'classes/updater.class.php';

    if ( is_admin() ) { // note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug' => plugin_basename( __FILE__ ),
            'proper_folder_name' => 'woocommerce-gateway-paygate-pw3',
            'api_url' => 'https://api.github.com/repos/RBAtkins0n/PayWeb_WooCommerce',
            'raw_url' => 'https://raw.github.com/RBAtkins0n/PayWeb_WooCommerce/master',
            'github_url' => 'https://github.com/RBAtkins0n/PayWeb_WooCommerce',
            'zip_url' => 'https://github.com/RBAtkins0n/PayWeb_WooCommerce/archive/master.zip',
            'homepage' => 'https://github.com/PayGate/PayWeb_WooCommerce',
            'sslverify' => true,
            'requires' => '4.0',
            'tested' => '4.9.8',
            'readme' => 'README.md',
            'access_token' => '',
        );

        new WP_GitHub_Updater( $config );

    }

} // End woocommerce_paygate_init()

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */

function woocommerce_add_paygate_gateway( $methods )
{

    $methods[] = 'WC_Gateway_PayGate';

    return $methods;

} // End woocommerce_add_paygate_gateway()
