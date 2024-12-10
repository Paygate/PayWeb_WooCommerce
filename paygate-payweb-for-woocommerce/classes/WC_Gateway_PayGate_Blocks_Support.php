<?php
/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Paygate payment method integration
 *
 * @since 1.5.0
 */
final class WC_Gateway_PayGate_Blocks_Support extends AbstractPaymentMethodType
{
    /**
     * Name of the payment method.
     *
     * @var string
     */
    protected $name = 'paygate';
    protected $settings;
    protected $paygate_gateway;

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $this->settings        = get_option('woocommerce_paygate_settings', []);
        $this->paygate_gateway = new WC_Gateway_PayGate();
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active()
    {
        $payment_gateways_class = WC()->payment_gateways();
        $payment_gateways       = $payment_gateways_class->payment_gateways();

        return $payment_gateways['paygate']->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles()
    {
        $parent_directory_path = dirname(__FILE__, 2);
        $plugin_url            = $this->paygate_gateway->get_plugin_url();
        $indexJsPath           = $plugin_url . '/assets/js/index.js';
        $asset_path            = $parent_directory_path . '/assets/js/index.asset.php';
        $version               = $this->paygate_gateway->version;
        $dependencies          = [];
        if (file_exists($asset_path)) {
            $asset        = require $asset_path;
            $version      = is_array($asset) && isset($asset['version'])
                ? $asset['version']
                : $version;
            $dependencies = is_array($asset) && isset($asset['dependencies'])
                ? $asset['dependencies']
                : $dependencies;
        }
        wp_register_script(
            'wc-paygate-blocks-integration',
            $indexJsPath,
            $dependencies,
            $version,
            true
        );
        wp_set_script_translations(
            'wc-paygate-blocks-integration',
            'woocommerce'
        );

        return ['wc-paygate-blocks-integration'];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => $this->get_supported_features(),
            'logo_url'    => $this->paygate_gateway->get_plugin_url() . '/assets/images/PayGate_logo.svg',
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features()
    {
        $payment_gateways = WC()->payment_gateways->payment_gateways();

        return $payment_gateways['paygate']->supports;
    }
}
