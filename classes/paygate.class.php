<?php
/*
 * Copyright (c) 2018 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * PayGate Payment Gateway - PayWeb3
 *
 * Provides a PayGate PayWeb3 Payment Gateway.
 *
 * @class       woocommerce_paygate
 * @package     WooCommerce
 * @category    Payment Gateways
 * @author      PayGate
 *
 */

class WC_Gateway_PayGate extends WC_Payment_Gateway
{
    protected static $_instance = null;

    const TEST_PAYGATE_ID = '10011072130';
    const TEST_SECRET_KEY = 'secret';

    public $version = '3.2.3';

    public $id = 'paygate';

    private $query_url = 'https://secure.paygate.co.za/payweb3/query.trans';

    private $paygate_id;
    private $encryption_key;

    private $initiate_response;
    private $notify_url;
    private $redirect_url;
    private $data_to_send;

    public static function instance()
    {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {

        $this->method_title       = __( 'PayGate via PayWeb3', 'paygate' );
        $this->method_description = __( 'PayGate via PayWeb3 works by sending the customer to PayGate to complete their payment.', 'paygate' );
        $this->icon               = PAYGATE_PLUGIN_URL . '/assets/images/logo_small.png';
        $this->has_fields         = true;
        $this->supports           = array(
            'products',
            'tokenization',
        );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        if ( isset( $this->settings['paygate_id'] ) ) {
            $this->paygate_id = $this->settings['paygate_id'];
        }
        if ( isset( $this->settings['encryption_key'] ) ) {
            $this->encryption_key = $this->settings['encryption_key'];
        }
        if ( isset( $this->settings['title'] ) ) {
            $this->title = $this->settings['title'];
        }
        if ( isset( $this->settings['button_text'] ) ) {
            $this->order_button_text = $this->settings['button_text'];
        }

        if ( isset( $this->settings['description'] ) ) {
            $this->description = $this->settings['description'];
        }

        // Setup the test data, if in test mode.
        if ( isset( $this->settings['testmode'] ) && $this->settings['testmode'] == 'yes' ) {
            $this->add_testmode_admin_settings_notice();
        }

        $this->notify_url   = add_query_arg( 'wc-api', 'WC_Gateway_PayGate_Notify', home_url( '/' ) );
        $this->redirect_url = add_query_arg( 'wc-api', 'WC_Gateway_PayGate_Redirect', home_url( '/' ) );

        add_action( 'woocommerce_api_wc_gateway_paygate_redirect', array(
            $this,
            'check_paygate_response',
        ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'paygate_payment_scripts' ) );

        add_action( 'woocommerce_api_wc_gateway_paygate_notify', array(
            $this,
            'check_paygate_notify_response',
        ) );

        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options',
            ) );
        } else {
            add_action( 'woocommerce_update_options_payment_gateways', array(
                &$this,
                'process_admin_options',
            ) );
        }
    }

    public function check_paygate_response()
    {

        global $woocommerce;

        if ( isset( $_GET['gid'] ) && !empty( $_GET['gid'] ) && isset( $_POST['PAY_REQUEST_ID'] ) && !empty( $_POST['PAY_REQUEST_ID'] ) ) {

            $order_id = $_GET['gid'];

            $pay_request_id = $_POST['PAY_REQUEST_ID'];

            $status = isset( $_POST['TRANSACTION_STATUS'] ) ? $_POST['TRANSACTION_STATUS'] : "";

            $checksum = isset( $_POST['CHECKSUM'] ) ? $_POST['CHECKSUM'] : "";

            $order = wc_get_order( $order_id );

            if ( $this->settings['testmode'] == 'yes' ) {

                $this->paygate_id = self::TEST_PAYGATE_ID;
                $this->encryption_key = self::TEST_SECRET_KEY;

            }

            $checksum_source = $this->paygate_id . $pay_request_id . $status . $order->get_order_number() . $this->encryption_key;

            $test_checksum = md5( $checksum_source );

            if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {

                if ( $checksum == $test_checksum ) {

                    $fields = array(
                        'PAYGATE_ID'     => $this->paygate_id,
                        'PAY_REQUEST_ID' => $_POST['PAY_REQUEST_ID'],
                        'REFERENCE'      => $order->get_order_number(),
                    );

                    $fields['CHECKSUM'] = md5( implode( '', $fields ) . $this->encryption_key );

                    $response = wp_remote_post( $this->query_url, array(
                        'method'      => 'POST',
                        'body'        => $fields,
                        'timeout'     => 70,
                        'sslverify'   => false,
                        'user-agent'  => 'WooCommerce/' . WC_VERSION,
                        'httpversion' => '1.1',
                    ) );

                    parse_str( $response['body'], $parsed_response );

                    $transaction_id = isset( $parsed_response['TRANSACTION_ID'] ) ? $parsed_response['TRANSACTION_ID'] : "";
                    $result_desc    = isset( $parsed_response['RESULT_DESC'] ) ? $parsed_response['RESULT_DESC'] : "";

                    // Get latest order in case notify has updated first
                    $order = wc_get_order( $order_id );

                    // Check if IPN disabled and use redirect instead
                    if ( $this->settings['disablenotify'] == 'yes' ) {
                        switch ( $status ) {
                            case 1:
                                $order->add_order_note( 'Response via Redirect: Transaction successful<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {
                                    $order->payment_complete();
                                }
                                $woocommerce->cart->empty_cart();

                                break;
                            case 2:
                                $order->add_order_note( 'Response via Redirect, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                if ( !$order->has_status( 'failed' ) ) {
                                    $order->update_status( 'failed' );
                                }

                                break;
                            case 4:
                                $order->add_order_note( 'Response via Redirect: User cancelled transaction<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                if ( !$order->has_status( 'failed' ) ) {
                                    $order->update_status( 'failed' );
                                }

                                break;
                            default:
                                $order->add_order_note( 'Response via Redirect, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                if ( !$order->has_status( 'pending' ) ) {
                                    $order->update_status( 'pending' );
                                }

                                $this->declined_msg( 'Your purchase is either pending or an error has occurred. Please follow up with the whomever necessary.' );

                                break;
                        }
                    }
                } else {
                    // Check if IPN disabled and use redirect instead
                    if ( $this->settings['disablenotify'] == 'yes' ) {
                        $order->add_order_note( 'Response via Redirect, Transaction declined.' . '<br/>' );
                        if ( !$order->has_status( 'failed' ) ) {
                            $order->update_status( 'failed' );
                        }
                    }
                }

            }

            $order = new WC_Order( $order_id );

            echo '<script>window.top.location.href="' . $this->get_return_url( $order ) . '";</script>';

        } else {

            echo '<script>window.top.location.href="' . get_permalink( wc_get_page_id( 'myaccount' ) ) . '";</script>';

        }
        exit;
    }

    /**
     * Check for valid PayGate Notify
     *
     * @since 1.0.0
     */
    public function check_paygate_notify_response()
    {
        // Tell PayGate notify we have received
        echo 'OK';

        // Check if IPN enabled
        if ( !isset( $this->settings['disablenotify'] ) || empty( $this->settings['disablenotify'] ) || $this->settings['disablenotify'] != 'yes' ) {

            if ( isset( $_POST ) ) {

                $errors = false;

                $paygate_data = array();

                $notify_data = array();

                // Get notify data
                if ( !$errors ) {

                    $paygate_data = $_POST;

                    if ( count( $paygate_data ) == 0 ) {
                        $errors = true;
                    }

                }

                // Verify security signature
                $checkSumParams = '';

                if ( $this->settings['testmode'] == 'yes' ) {
                    $this->encryption_key = 'secret';
                }

                if ( !$errors ) {
                    foreach ( $paygate_data as $key => $val ) {
                        $notify_data[$key] = stripslashes( $val );

                        if ( $key == 'PAYGATE_ID' ) {
                            $checkSumParams .= $val;
                        }
                        if ( $key != 'CHECKSUM' && $key != 'PAYGATE_ID' ) {
                            $checkSumParams .= $val;
                        }

                        if ( sizeof( $notify_data ) == 0 ) {
                            $errors = true;
                        }
                    }

                    $checkSumParams .= $this->encryption_key;
                }

                // Verify security signature
                if ( !$errors ) {
                    $checkSumParams = md5( $checkSumParams );
                    if ( $checkSumParams != $paygate_data['CHECKSUM'] ) {
                        $errors     = true;
                        $error_desc = 'Transaction declined.';
                    }
                }

                if ( isset( $paygate_data['REFERENCE'] ) ) {
                    $order_id = $paygate_data['REFERENCE'];
                } else {
                    $order_id = '';
                }

                if ( $order_id != '' ) {
                    $order = wc_get_order( trim( $order_id ) );
                    if ( !$errors ) {
                        if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {

                            $transaction_id = isset( $paygate_data['TRANSACTION_ID'] ) ? $paygate_data['TRANSACTION_ID'] : "";
                            $result_desc    = isset( $paygate_data['RESULT_DESC'] ) ? $paygate_data['RESULT_DESC'] : "";

                            switch ( $paygate_data['TRANSACTION_STATUS'] ) {
                                case 1:

                                    $order->add_order_note( 'Response via Notify: Transaction successful<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                    if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {
                                        $order->payment_complete();
                                    }
                                    break;
                                case 2:

                                    $order->add_order_note( 'Response via Notify, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                    if ( !$order->has_status( 'failed' ) ) {
                                        $order->update_status( 'failed' );
                                    }
                                    break;
                                case 4:

                                    $order->add_order_note( 'Response via Notify, User cancelled transaction<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                    if ( !$order->has_status( 'failed' ) ) {
                                        $order->update_status( 'failed' );
                                    }
                                    break;
                                default:

                                    $order->add_order_note( 'Response via Notify, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . '<br/>' );
                                    if ( !$order->has_status( 'pending' ) ) {
                                        $order->update_status( 'pending' );
                                    }
                                    break;
                            }
                        }
                    } else {

                        $order->add_order_note( 'Response via Notify, ' . $error_desc . '<br/>' );
                        if ( !$order->has_status( 'failed' ) ) {
                            $order->update_status( 'failed' );
                        }
                    }
                }
            }
        }
        die();
    }

    public function get_order_id_order_pay()
    {
        global $wp;

        // Get the order ID
        $order_id = absint( $wp->query_vars['order-pay'] );

        if ( empty( $order_id ) || $order_id == 0 ) {
            return;
        }
        // Exit;
        // Testing output (always use return with a shortcode)
        return $order_id;
    }

    /**
     * Add payment scripts for iFrame support
     *
     * @since 1.0.0
     */
    public function paygate_payment_scripts()
    {
        wp_enqueue_script( 'paygate-checkout-js', PAYGATE_PLUGIN_URL . 'assets/js/paygate_checkout.js', array(), WC_VERSION, true );
        if ( is_wc_endpoint_url( 'order-pay' ) ) {
            wp_localize_script( 'paygate-checkout-js', 'paygate_checkout_js', array(
                'order_id' => $this->get_order_id_order_pay(),
            ) );

        } else {
            wp_localize_script( 'paygate-checkout-js', 'paygate_checkout_js', array(
                'order_id' => 0,
            ) );
        }

        wp_enqueue_style( 'paygate-checkout-css', PAYGATE_PLUGIN_URL . 'assets/css/paygate_checkout.css', array(), WC_VERSION );
    }

    /**
     * Add a notice to the encryption_key and paygate_id fields when in test mode.
     *
     * @since 1.0.0
     */
    public function add_testmode_admin_settings_notice()
    {
        $this->form_fields['paygate_id']['description'] .= ' <br><br><strong>' . __( 'PayGate ID currently in use.', 'paygate' ) . ' ( 10011072130 )</strong>';
        $this->form_fields['encryption_key']['description'] .= ' <br><br><strong>' . __( 'PayGate Secret Key currently in use.', 'paygate' ) . ' ( secret )</strong>';
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled'       => array(
                'title'       => __( 'Enable/Disable', 'paygate' ),
                'label'       => __( 'Enable PayGate Payment Gateway', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title'         => array(
                'title'       => __( 'Title', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'paygate' ),
                'desc_tip'    => false,
                'default'     => __( 'PayGate Payment Gateway', 'paygate' ),
            ),
            'paygate_id'    => array(
                'title'       => __( 'PayGate ID', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This is the PayGate ID, received from PayGate.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'disablenotify' => array(
                'title'       => __( 'Disable IPN', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'Disable IPN notify method and use redirect method instead.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'encryption_key'    => array(
                'title'       => __( 'Secret Key', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This is the Secret Key set in the PayGate Back Office.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'testmode'      => array(
                'title'       => __( 'Test mode', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'Uses a PayGate test account. Request test cards from PayGate', 'paygate' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'description'   => array(
                'title'       => __( 'Description', 'paygate' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'paygate' ),
                'default'     => 'Pay via PayGate',
            ),
            'button_text'   => array(
                'title'       => __( 'Order Button Text', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'Changes the text that appears on the Place Order button', 'paygate' ),
                'default'     => 'Proceed to PayGate',
            ),
        );

    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title'
     *
     * @since 1.0.0
     */
    public function admin_options()
    {
        ?>
            <h3><?php _e( 'PayGate Payment Gateway', 'paygate' );?></h3>
            <p><?php printf( __( 'PayGate works by Iframe popup at checkout %sPayGate%s.', 'paygate' ), '<a href="https://www.paygate.co.za/">', '</a>' );?></p>

            <table class="form-table">
                <?php $this->generate_settings_html(); // Generate the HTML For the settings form. ?>
            </table><!--/.form-table-->
            <?php
}

    /**
     * Check store currency eligible for paygate.
     *
     * @since 1.0.0
     *
     */
    public function check_currency()
    {

        $currency = get_woocommerce_currency();

        if ( $currency != 'ZAR' ) {

            $message = 'Store currency must be South Africa';
            // If function should we use?
            if ( function_exists( "wc_add_notice" ) ) {
                // Use the new version of the add_error method
                wc_add_notice( $message );

            } else {
                // Use the old version
                $woocommerce->add_error( $message );

            }
            return false;

        } else {

            return true;

        }

    }

    public function process_review_payment()
    {
        if ( !empty( $_POST['order_id'] ) ) {
            $this->process_payment( $_POST['order_id'] );
        }
    }

    /**
     * Process the payment and return the result.
     *
     * @since 1.0.0
     *
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment( $order_id )
    {

        if ( !empty( $order_id ) ) {

            $check = $this->check_currency();

            if ( $check ) {

                $result = $this->initiate_transaction( $order_id );

                $return_data = array(
                    'PAY_REQUEST_ID'   => $result['PAY_REQUEST_ID'],
                    'CHECKSUM'         => $result['CHECKSUM'],
                    'result'           => 'failure',
                    'reload'           => false,
                    'refresh'          => true,
                    'paygate_override' => true,
                    'message'          => false,
                );

                echo json_encode( $return_data );
                die;

            } else {

                return;

            }

        }

    }

    /**
     * Does the initiate to PayGate
     *
     * @param $order_id
     *
     * @return array|WP_Error
     */
    public function initiate_transaction( $order_id )
    {

        $order = new WC_Order( $order_id );

        unset( $this->data_to_send );

        if ( $this->settings['testmode'] == 'yes' ) {
            $this->paygate_id = self::TEST_PAYGATE_ID;
            $this->encryption_key = self::TEST_SECRET_KEY;
        }

        // Construct variables for post
        $order_total = $order->get_total();

        $this->data_to_send = array(
            'PAYGATE_ID'       => $this->paygate_id,
            'REFERENCE'        => $order->get_order_number(),
            'AMOUNT'           => number_format( $order_total, 2, '', '' ),
            'CURRENCY'         => get_woocommerce_currency(),
            'RETURN_URL'       => $this->redirect_url . '&gid=' . $order_id,
            'TRANSACTION_DATE' => date( 'Y-m-d H:m:s' ),
            'LOCALE'           => 'en-za',
            'COUNTRY'          => 'ZAF',
            'EMAIL'            => $order->get_billing_email(),
            'NOTIFY_URL'       => $this->notify_url,
            'USER3'            => 'woocommerce-v' . $this->version,
        );

        $this->data_to_send['CHECKSUM'] = md5( implode( '', $this->data_to_send ) . $this->encryption_key );

        $response = $this->curlPost( 'https://secure.paygate.co.za/payweb3/initiate.trans', $this->data_to_send );

        parse_str( $response, $this->data_to_send );

        if ( isset( $this->data_to_send['PAY_REQUEST_ID'] ) ) {

            $processData = array(
                'PAY_REQUEST_ID' => $this->data_to_send['PAY_REQUEST_ID'],
                'CHECKSUM'       => $this->data_to_send['CHECKSUM'],
            );

            return $processData;

        }
    }

    public function curlPost( $url, $fields )
    {
        $curl = curl_init( $url );
        curl_setopt( $curl, CURLOPT_POST, count( $fields ) );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $fields );
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
        $response = curl_exec( $curl );
        curl_close( $curl );
        return $response;
    }

}
add_action( 'wp_ajax_order_pay_payment', array( WC_Gateway_PayGate::instance(), 'process_review_payment' ) );
add_action( 'wp_ajax_nopriv_order_pay_payment', array( WC_Gateway_PayGate::instance(), 'process_review_payment' ) );
