<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
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

    const TEST_PAYGATE_ID     = '10011072130';
    const TEST_ENCRYPTION_KEY = 'secret';

    public $version = '4.0.0';

    public $id = 'paygate';

    private $initiate_url = 'https://secure.paygate.co.za/payweb3/initiate.trans';
    private $process_url  = 'https://secure.paygate.co.za/payweb3/process.trans';
    private $query_url    = 'https://secure.paygate.co.za/payweb3/query.trans';

    private $merchant_id;
    private $encryption_key;
    private $payVault;

    private $vaultCard;
    private $vaultId;

    private $initiate_response;
    private $notify_url;
    private $redirect_url;
    private $data_to_send;

    private $msg;
    private $post;

    private $paywebStatus = [
        0 => 'Not Done',
        1 => 'Approved',
        2 => 'Declined',
        3 => 'Cancelled',
        4 => 'User Cancelled',
        5 => 'Received by PayGate',
        7 => 'Settlement Voided',
    ];

    public function __construct()
    {
        session_start();

        $this->post = $_POST;
        if ( !empty( $_POST ) ) {
            if ( isset( $_POST['wc-paygate-new-payment-method'] ) ) {
                $_SESSION['wc-paygate-new-payment-method'] = '1';
            } else {
                $_SESSION['wc-paygate-new-payment-method'] = '0';
            }

            if ( isset( $_POST['wc-paygate-payment-token'] ) ) {
                $_SESSION['wc-paygate-payment-token'] = $_POST['wc-paygate-payment-token'];
            } else {
                unset( $_SESSION['wc-paygate-payment-token'] );
            }
        }

        $this->method_title       = __( 'PayGate via PayWeb3', 'paygate' );
        $this->method_description = __( 'PayGate via PayWeb3 works by sending the customer to PayGate to complete their payment.',
            'paygate' );
        $this->icon       = $this->get_plugin_url() . '/assets/images/logo_small.png';
        $this->has_fields = true;
        $this->supports   = array(
            'products',
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->merchant_id       = $this->settings['paygate_id'];
        $this->encryption_key    = $this->settings['encryption_key'];
        $this->title             = $this->settings['title'];
        $this->order_button_text = $this->settings['button_text'];
        $this->description       = $this->settings['description'];
        $this->payVault          = $this->settings['payvault'];

        if ( $this->payVault == 'yes' ) {
            $this->supports[] = 'tokenization';
        }

        $this->msg['message'] = "";
        $this->msg['class']   = "";

        // Setup the test data, if in test mode
        if ( $this->settings['testmode'] == 'yes' ) {
            $this->add_testmode_admin_settings_notice();
        }

        $this->notify_url   = add_query_arg( 'wc-api', 'WC_Gateway_PayGate_Notify', home_url( '/' ) );
        $this->redirect_url = add_query_arg( 'wc-api', 'WC_Gateway_PayGate_Redirect', home_url( '/' ) );

        add_action( 'woocommerce_api_wc_gateway_paygate_redirect', array(
            $this,
            'check_paygate_response',
        ) );

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

        add_action( 'woocommerce_receipt_paygate', array(
            $this,
            'receipt_page',
        ) );

        add_action( 'wp_ajax_order_pay_payment', array( $this, 'process_review_payment' ) );
        add_action( 'wp_ajax_nopriv_order_pay_payment', array( $this, 'process_review_payment' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'paygate_payment_scripts' ) );

    }

    /**
     * Get the plugin URL
     *
     * @since 1.0.0
     */
    public function get_plugin_url()
    {
        if ( isset( $this->plugin_url ) ) {
            return $this->plugin_url;
        }

        if ( is_ssl() ) {
            return $this->plugin_url = str_replace( 'http://', 'https://',
                WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
        } else {
            return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    public function init_form_fields()
    {

        $this->form_fields = array(
            'enabled'        => array(
                'title'       => __( 'Enable/Disable', 'paygate' ),
                'label'       => __( 'Enable PayGate Payment Gateway', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.',
                    'paygate' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title'          => array(
                'title'       => __( 'Title', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'paygate' ),
                'desc_tip'    => false,
                'default'     => __( 'PayGate Payment Gateway', 'paygate' ),
            ),
            'paygate_id'     => array(
                'title'       => __( 'PayGate ID', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This is the PayGate ID, received from PayGate.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'encryption_key' => array(
                'title'       => __( 'Encryption Key', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'This is the Encryption Key set in the PayGate Back Office.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'payment_type'   => array(
                'title'       => __( 'Implementation', 'paygate' ),
                'label'       => __( 'Choose payment type', 'paygate' ),
                'type'        => 'select',
                'description' => 'Which implementation to use - Redirect or iFrame',
                'default'     => 'redirect',
                'options'     => array(
                    'redirect' => 'Redirect',
                    'iframe'   => 'iFrame',
                ),
            ),
            'testmode'       => array(
                'title'       => __( 'Test mode', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'Uses a PayGate test account. Request test cards from PayGate', 'paygate' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'disablenotify'  => array(
                'title'       => __( 'Disable IPN', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'Disable IPN notify method and use redirect method instead.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'payvault'       => array(
                'title'       => __( 'Enable PayVault', 'paygate' ),
                'type'        => 'checkbox',
                'description' => __( 'Provides the ability for users to store their credit card details.', 'paygate' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'description'    => array(
                'title'       => __( 'Description', 'paygate' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'paygate' ),
                'default'     => 'Pay via PayGate',
            ),
            'button_text'    => array(
                'title'       => __( 'Order Button Text', 'paygate' ),
                'type'        => 'text',
                'description' => __( 'Changes the text that appears on the Place Order button', 'paygate' ),
                'default'     => 'Proceed to PayGate',
            ),
        );

    }

    /**
     * Add a notice to the merchant_key and merchant_id fields when in test mode.
     *
     * @since 1.0.0
     */
    public function add_testmode_admin_settings_notice()
    {
        $this->form_fields['paygate_id']['description'] .= ' <br><br><strong>' . __( 'PayGate ID currently in use.',
            'paygate' ) . ' ( 10011072130 )</strong>';
        $this->form_fields['encryption_key']['description'] .= ' <br><br><strong>' . __( 'PayGate Encryption Key currently in use.',
            'paygate' ) . ' ( secret )</strong>';
    }

    /**
     * Custom order action - query order status
     * Add custom action to order actions select box
     */
    public static function paygate_add_order_meta_box_action( $actions )
    {
        global $theorder;
        if ( $theorder->get_payment_method() == 'paygate' ) {
            $actions['wc_custom_order_action'] = __( 'Query order status with PayGate', 'paygate' );
        }

        return $actions;
    }

    public static function paygate_order_query_action( $order )
    {
        global $wpdb;
        $gw       = new WC_Gateway_PayGate();
        $order_id = $order->get_id();

        $table = $wpdb->prefix . 'comments';
        $notes = $gw->getOrderNotes( $order_id );

        $payRequestId = 0;
        foreach ( $notes as $note ) {
            if ( strpos( $note->comment_content, 'Pay Request Id:' ) !== false ) {
                preg_match( '/.*Pay Request Id: ([\dA-Z\-]+)/', $note->comment_content, $a );
                if ( isset( $a[1] ) ) {
                    $payRequestId = $a[1];
                }
            }
        }

        $response          = $gw->paywebQuery( $payRequestId, $order );
        $transactionStatus = !empty( $response['TRANSACTION_STATUS'] ) ? $response['TRANSACTION_STATUS'] : 'Null';
        $resultCode        = !empty( $response['RESULT_CODE'] ) ? $response['RESULT_CODE'] : 'Null';
        $resultDesc        = !empty( $response['RESULT_DESC'] ) ? $response['RESULT_DESC'] : 'Null';
        $responseText      = <<<RT
Transaction Status: {$transactionStatus} => {$gw->paywebStatus[(int)$transactionStatus]}<br>
Result Code: {$resultCode}<br>
Result Description: {$resultDesc}
RT;

        if ( (int) $transactionStatus === 1 ) {
            if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {
                $order->payment_complete();
                $responseText .= "<br>Order set to \"Processing\"";
            }
        } else {
            if ( !$order->has_status( 'failed' ) ) {
                $order->update_status( 'failed' );
            }
        }
        $order->add_order_note( 'Queried at ' . date( 'Y-m-d H:i' ) . '<br>Response: <br>' . $responseText );
    }

    /**
     * @param $order_id
     *
     * @return array|object|null
     */
    private function getOrderNotes( $order_id )
    {
        global $wpdb;

        $table = $wpdb->prefix . 'comments';
        $notes = $wpdb->get_results( "
        SELECT comment_content from $table
        WHERE `comment_post_ID` = $order_id
        AND `comment_type` = 'order_note'
        " );

        return $notes;
    }

    private function paywebQuery( $payRequestId, $order )
    {
        $fields = array(
            'PAYGATE_ID'     => $this->merchant_id,
            'PAY_REQUEST_ID' => $payRequestId,
            'REFERENCE'      => $order->get_id() . '-' . $order->get_order_number(),
        );
        $fields['CHECKSUM'] = md5( implode( '', $fields ) . $this->encryption_key );

        $response = wp_remote_post( $this->query_url, array(
            'method'      => 'POST',
            'body'        => $fields,
            'timeout'     => 70,
            'sslverify'   => true,
            'user-agent'  => 'WooCommerce/' . WC_VERSION,
            'httpversion' => '1.1',
        ) );

        parse_str( $response['body'], $parsed_response );

        return $parsed_response;
    }

    public static function paygate_order_query_cron()
    {
        global $wpdb;
        $gw = new WC_Gateway_PayGate();

        $query = <<<QUERY
SELECT ID FROM `{$wpdb->prefix}posts`
INNER JOIN `{$wpdb->prefix}postmeta` ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id
WHERE meta_key = '_payment_method'
AND meta_value = 'paygate'
AND post_status == 'wc-pending'
QUERY;

        $orders = $wpdb->get_results( $query );
        foreach ( $orders as $order_id ) {
            $order = new WC_Order( $order_id->ID );
            $notes = $gw->getOrderNotes( $order_id->ID );

            $payRequestId = '';
            foreach ( $notes as $note ) {
                if ( strpos( $note->comment_content, 'Pay Request Id:' ) !== false ) {
                    preg_match( '/.*Pay Request Id: ([\dA-Z\-]+)/', $note->comment_content, $a );
                    if ( isset( $a[1] ) ) {
                        $payRequestId = $a[1];
                    }
                }
            }

            if ( $payRequestId != '' ) {
                $response          = $gw->paywebQuery( $payRequestId, $order );
                $transactionStatus = !empty( $response['TRANSACTION_STATUS'] ) ? $response['TRANSACTION_STATUS'] : 'Null';
                $resultCode        = !empty( $response['RESULT_CODE'] ) ? $response['RESULT_CODE'] : 'Null';
                $resultDesc        = !empty( $response['RESULT_DESC'] ) ? $response['RESULT_DESC'] : 'Null';
                $responseText      = <<<RT
 Transaction Status: {$transactionStatus} => {$gw->paywebStatus[(int)$transactionStatus]}<br>
 Result Code: {$resultCode}<br>
 Result Description: {$resultDesc}
RT;

                if ( (int) $transactionStatus === 1 ) {
                    if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {
                        $order->payment_complete();
                        $responseText .= "<br>Order set to \"Processing\"";
                    }
                } else {
                    if ( !$order->has_status( 'failed' ) ) {
                        $order->update_status( 'failed' );
                    }
                }
                $order->add_order_note( 'Queried by cron at ' . date( 'Y-m-d H:i' ) . '<br>Response: <br>' . $responseText );
            }
        }
    }

    /**
     * @param $resultDescription string
     */
    public function declined_msg( $resultDescription )
    {
        echo '<p class="woocommerce-thankyou-order-failed">';
        _e( $resultDescription, 'woocommerce' );
        echo '</p>';
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
        <p><?php printf( __( 'PayGate works by sending the user to %sPayGate%s to enter their payment information.',
            'paygate' ), '<a href="https://www.paygate.co.za/">', '</a>' );?></p>

        <table class="form-table">
            <?php $this->generate_settings_html(); // Generate the HTML For the settings form.
        ?>
        </table><!--/.form-table-->
        <?php
}

    /**
     * Return false to bypass adding Tokenization in "My Account" section
     *
     * @return bool
     */
    public function add_payment_method()
    {
        return false;
    }

    /**
     * Enable vaulting and card selection for PayGate
     *
     * @since 1.0.0
     */
    public function payment_fields()
    {

        if ( $this->payVault == 'yes' ) {
            if ( count( $_POST ) > 0 ) {
                // Display stored credit card selection
                $tokens       = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $this->id );
                $defaultToken = WC_Payment_Tokens::get_customer_default_token( get_current_user_id() );

                if ( count( $tokens ) > 0 ) {

                    echo <<<HTML
                        <select name="wc-{$this->id}-payment-token">
HTML;

                    /**
                     * @var $token WC_Payment_Token_CC
                     */
                    $now = new DateTime( date( 'Y-m' ) );
                    foreach ( $tokens as $token ) {
                        $valid   = false;
                        $expires = new DateTime( $token->get_expiry_year() . '-' . $token->get_expiry_month() );
                        $valid   = $expires >= $now;

                        // Don't show expired cards
                        if ( $valid ) {
                            $cardType = ucwords( $token->get_card_type() );

                            if ( $defaultToken && $token->get_id() == $defaultToken->get_id() ) {
                                $selected = 'selected';
                            } else {
                                $selected = '';
                            }

                            echo <<<HTML
                     <option value="{$token->get_token()}" {$selected}>Use {$cardType} ending in {$token->get_last4()}</option> }

HTML;
                        }
                    }

                    echo <<<HTML
                    <option value="new">Use a new card</option>
                    <option value="no">Use a new card and don't save</option>
                </select>

HTML;

                } else {
                    echo <<<HTML
                <input type="checkbox" name="wc-{$this->id}-new-payment-method" id="wc-paygate-new-payment-method" value="true"> Remember my credit card number

HTML;
                }
            } else {
                // Display message for adding cards via "My Account" screen

                echo <<<HTML
    <p>Cards cannot be added manually. Please select the "Use a new card" option in the checkout process when paying with PayGate</p>

HTML;

            }
        } else {
            if ( isset( $this->settings['description'] ) && $this->settings['description'] != '' ) {
                echo wpautop( wptexturize( $this->settings['description'] ) );
            }
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
     * @param int $order_id
     *
     * @return array
     * @since 1.0.0
     *
     */
    public function process_payment( $order_id )
    {
        if ( $this->settings['payment_type'] === 'iframe' ) {
            echo $this->get_ajax_return_data_json( $order_id );
            die;
        } else {
            $order = new WC_Order( $order_id );

            return [
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url( true ),
            ];
        }

    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     *
     * @return array
     * @since 1.0.0
     *
     */
    public function get_ajax_return_data_json( $order_id )
    {
        if ( session_status() === PHP_SESSION_NONE ) {
            session_start();
        }
        $_SESSION['POST'] = $_POST;

        $returnParams = $this->initiate_transaction( $order_id );

        $return_data = array(
            'PAY_REQUEST_ID'   => $returnParams['PAY_REQUEST_ID'],
            'CHECKSUM'         => $returnParams['CHECKSUM'],
            'result'           => 'failure',
            'reload'           => false,
            'refresh'          => true,
            'paygate_override' => true,
            'message'          => false,
        );

        return json_encode( $return_data );
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
        $order       = new WC_Order( $order_id );
        $customer_id = $order->get_customer_id();
        unset( $this->data_to_send );

        if ( $this->settings['testmode'] == 'yes' ) {
            $this->merchant_id    = self::TEST_PAYGATE_ID;
            $this->encryption_key = self::TEST_ENCRYPTION_KEY;
        }

        // Construct variables for post
        $order_total = $order->get_total();
        if ( $this->settings['disablenotify'] != 'yes' ) {
            $this->data_to_send = array(
                'PAYGATE_ID'       => $this->merchant_id,
                'REFERENCE'        => $order->get_id() . '-' . $order->get_order_number(),
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
        } else {
            $this->data_to_send = array(
                'PAYGATE_ID'       => $this->merchant_id,
                'REFERENCE'        => $order->get_id() . '-' . $order->get_order_number(),
                'AMOUNT'           => number_format( $order_total, 2, '', '' ),
                'CURRENCY'         => get_woocommerce_currency(),
                'RETURN_URL'       => $this->redirect_url . '&gid=' . $order_id,
                'TRANSACTION_DATE' => date( 'Y-m-d H:m:s' ),
                'LOCALE'           => 'en-za',
                'COUNTRY'          => 'ZAF',
                'EMAIL'            => $order->get_billing_email(),
                'USER3'            => 'woocommerce-v' . $this->version,
            );
        }

        $newPaymentMethod = false;
        if ( isset( $_SESSION['wc-paygate-new-payment-method'] ) && $_SESSION['wc-paygate-new-payment-method'] == '1' ) {
            $newPaymentMethod = true;
        }

        if ( $this->payVault == 'yes' ) {
            // Tokenisation is enabled for store
            // Check if customer has existing tokens or chooses to tokenise
            $custVault = get_post_meta( $customer_id, 'wc-' . $this->id . '-new-payment-method', true );
            if ( !$custVault && $newPaymentMethod ) {
                // Customer requesting remember card number
                update_post_meta( $customer_id, 'wc-' . $this->id . '-new-payment-method', true );
                $this->data_to_send['VAULT'] = 1;
            } elseif ( isset( $_SESSION['wc-' . $this->id . '-payment-token'] ) && $_SESSION['wc-' . $this->id . '-payment-token'] == 'new' ) {
                update_post_meta( $customer_id, 'wc-' . $this->id . '-new-payment-method', true );
                $this->data_to_send['VAULT'] = 1;
            } elseif ( isset( $_SESSION['wc-' . $this->id . '-payment-token'] ) && $_SESSION['wc-' . $this->id . '-payment-token'] == 'no' ) {
                update_post_meta( $customer_id, 'wc-' . $this->id . '-new-payment-method', false );
            } elseif ( isset( $_SESSION['wc-' . $this->id . '-payment-token'] ) ) {
                $this->data_to_send['VAULT_ID'] = $_SESSION['wc-' . $this->id . '-payment-token'];
            } elseif ( $newPaymentMethod ) {
                $this->data_to_send['VAULT'] = 1;
            }
        }

        $this->data_to_send['CHECKSUM'] = md5( implode( '', $this->data_to_send ) . $this->encryption_key );

        $this->initiate_response = wp_remote_post( $this->initiate_url, array(
            'method'      => 'POST',
            'body'        => $this->data_to_send,
            'timeout'     => 70,
            'sslverify'   => true,
            'user-agent'  => 'WooCommerce',
            'httpversion' => '1.1',
        ) );

        if ( is_wp_error( $this->initiate_response ) ) {
            return $this->initiate_response;
        }

        parse_str( $this->initiate_response['body'], $parsed_response );

        if ( empty( $this->initiate_response['body'] ) || array_key_exists( 'ERROR',
            $parsed_response ) || !array_key_exists( 'PAY_REQUEST_ID', $parsed_response ) ) {
            $this->msg['class']   = 'woocommerce-error';
            $this->msg['message'] = "Thank you for shopping with us. However, we were unable to initiate your payment. Please try again.";
            if ( !$order->has_status( 'failed' ) ) {
                $order->update_status( 'failed' );
            }
            $order->add_order_note( 'Response from initiating payment:' . print_r( $this->data_to_send,
                true ) . ' ' . $this->initiate_response['body'] );

            return new WP_Error( 'paygate-error',
                __( $this->show_message( '<br><a class="button wc-forward" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart',
                    'paygate' ) . '</a>' ), 'paygate' ) );
        } else {
            $_SESSION['PARSED_RESPONSE'] = $parsed_response;
        }

        $this->initiate_response['body'] = $parsed_response;

        return $parsed_response;
    }

    /**
     * Show Message.
     *
     * Display message depending on order results.
     *
     * @param $content
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function show_message( $content )
    {
        return '<div class="' . $this->msg['class'] . '">' . $this->msg['message'] . '</div>' . $content;
    }

    /**
     * Add payment scripts for iFrame support
     *
     * @since 1.0.0
     */
    public function paygate_payment_scripts()
    {
        if ( $this->settings['payment_type'] === 'iframe' ) {
            wp_enqueue_script( 'paygate-checkout-js', $this->get_plugin_url() . '/assets/js/paygate_checkout.js',
                array(),
                WC_VERSION, true );
            if ( is_wc_endpoint_url( 'order-pay' ) ) {
                $order_id = $this->get_order_id_order_pay();
                wp_localize_script( 'paygate-checkout-js', 'paygate_checkout_js', array(
                    'is_order_pay'      => true,
                    'pay_now_form_data' => $this->get_ajax_return_data_json( $order_id ),
                ) );

            } else {
                wp_localize_script( 'paygate-checkout-js', 'paygate_checkout_js', array(
                    'is_order_pay' => false,
                    'order_id'     => 0,
                ) );
            }
            wp_enqueue_style( 'paygate-checkout-css', $this->get_plugin_url() . '/assets/css/paygate_checkout.css',
                array(), WC_VERSION );
        }
    }

    public function get_order_id_order_pay()
    {
        global $wp;

        // Get the order ID
        $order_id = absint( $wp->query_vars['order-pay'] );

        if ( empty( $order_id ) || $order_id == 0 ) {
            return;
        }

        // Exit
        return $order_id;
    }

    /**
     * Receipt page.
     *
     * Display text and a button to direct the customer to PayGate.
     *
     * @param $order
     *
     * @since 1.0.0
     *
     */
    public function receipt_page( $order_id )
    {
        $return = $this->initiate_transaction( $order_id );
        if ( is_wp_error( $return ) ) {
            echo $return->get_error_message();
        } else {
            if ( $this->settings['payment_type'] !== 'iframe' ) {
                // Do redirect
                echo $this->generate_paygate_form( $order_id );
            }
        }
    }

    /**
     * Generate the PayGate button link.
     * Redirect case
     *
     * @param $order_id
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function generate_paygate_form( $order_id )
    {
        $order = new WC_Order( $order_id );

        $parsed_response = $this->initiate_response['body'];

        $messageText = esc_js( __( 'Thank you for your order. We are now redirecting you to PayGate to make payment.',
            'paygate' ) );

        unset( $parsed_response['CHECKSUM'] );
        $checksum = md5( implode( '', $parsed_response ) . $this->encryption_key );

        $heading    = __( 'Thank you for your order, please click the button below to pay via PayGate.', 'paygate' );
        $buttonText = __( $this->order_button_text, 'paygate' );
        $cancelUrl  = esc_url( $order->get_cancel_order_url() );
        $cancelText = __( 'Cancel order &amp; restore cart', 'paygate' );

        $form = <<<HTML
<p>{$heading}</p>
<form action="{$this->process_url}" method="post" id="paygate_payment_form">
    <input name="PAY_REQUEST_ID" type="hidden" value="{$parsed_response['PAY_REQUEST_ID']}" />
    <input name="CHECKSUM" type="hidden" value="{$checksum}" />
    <!-- Button Fallback -->
    <div class="payment_buttons">
        <input type="submit" class="button alt" id="submit_paygate_payment_form" value="{$buttonText}" /> <a class="button cancel" href="{$cancelUrl}">{$cancelText}</a>
    </div>
</form>
<script>
jQuery(document).ready(function(){
    jQuery(function(){
        jQuery("body").block({
            message: "",
            overlayCSS: {
                background: "#fff",
                opacity: 0.6
            },
            css: {
                padding:        20,
                textAlign:      "center",
                color:          "#555",
                border:         "3px solid #aaa",
                backgroundColor:"#fff",
                cursor:         "wait"
            }
        });
    });

    jQuery("#submit_paygate_payment_form").click();
    jQuery("#submit_paygate_payment_form").attr("disabled", true);
});
</script>
HTML;

        return $form;
    }

    /**
     * Check for valid PayGate Redirect - from iFrame or  Redirect
     *
     * @since 1.0.0
     */
    public function check_paygate_response()
    {
        global $woocommerce;

        // Only process if IPN is disabled
        if ( isset( $_GET['gid'] ) && isset( $_POST['PAY_REQUEST_ID'] ) ) {
            $order_id = $_GET['gid'];

            if ( $order_id != '' ) {
                $order       = wc_get_order( $order_id );
                $customer_id = $order->get_customer_id();

                $pay_request_id = $_POST['PAY_REQUEST_ID'];
                $status         = isset( $_POST['TRANSACTION_STATUS'] ) ? $_POST['TRANSACTION_STATUS'] : "";
                $checksum       = isset( $_POST['CHECKSUM'] ) ? $_POST['CHECKSUM'] : "";

                if ( $this->settings['testmode'] == 'yes' ) {
                    $this->merchant_id    = self::TEST_PAYGATE_ID;
                    $this->encryption_key = self::TEST_ENCRYPTION_KEY;
                }

                $reference       = $order->get_id() . '-' . $order->get_order_number();
                $checksum_source = $this->merchant_id . $pay_request_id . $status . $reference . $this->encryption_key;
                $test_checksum   = md5( $checksum_source );

                if ( $checksum == $test_checksum ) {
                    $fields = array(
                        'PAYGATE_ID'     => $this->merchant_id,
                        'PAY_REQUEST_ID' => $_POST['PAY_REQUEST_ID'],
                        'REFERENCE'      => $order->get_id() . '-' . $order->get_order_number(),
                    );
                    $fields['CHECKSUM'] = md5( implode( '', $fields ) . $this->encryption_key );

                    $response = wp_remote_post( $this->query_url, array(
                        'method'      => 'POST',
                        'body'        => $fields,
                        'timeout'     => 70,
                        'sslverify'   => true,
                        'user-agent'  => 'WooCommerce/' . WC_VERSION,
                        'httpversion' => '1.1',
                    ) );

                    parse_str( $response['body'], $parsed_response );

                    if ( $this->payVault == 'yes' ) {
                        $vaultCard = get_post_meta( $customer_id, 'wc-' . $this->id . '-new-payment-method', true );

                        if ( $vaultCard == true && array_key_exists( 'VAULT_ID', $parsed_response ) ) {
                            // Save Token details
                            $vaultId  = $parsed_response['VAULT_ID'];
                            $card     = isset( $parsed_response['PAYVAULT_DATA_1'] ) ? $parsed_response['PAYVAULT_DATA_1'] : "";
                            $expiry   = isset( $parsed_response['PAYVAULT_DATA_2'] ) ? $parsed_response['PAYVAULT_DATA_2'] : "";
                            $cardType = isset( $parsed_response['PAY_METHOD_DETAIL'] ) ? $parsed_response['PAY_METHOD_DETAIL'] : "";

                            // Get existing tokens for user
                            $tokenDs = new WC_Payment_Token_Data_Store();
                            $tokens  = $tokenDs->get_tokens( [
                                'user_id' => $customer_id,
                            ] );

                            $exists = false;
                            $now    = ( new DateTime() )->format( 'mY' );

                            foreach ( $tokens as $token ) {
                                if ( $token->token == $vaultId ) {
                                    $exists = true;
                                }
                            }

                            if ( !$exists ) {
                                $token = new WC_Payment_Token_CC();

                                $token->set_token( $vaultId );
                                $token->set_gateway_id( $this->id );
                                $token->set_card_type( strtolower( $cardType ) );
                                $token->set_last4( substr( $card, -4 ) );
                                $token->set_expiry_month( substr( $expiry, 0, 2 ) );
                                $token->set_expiry_year( substr( $expiry, -4 ) );
                                $token->set_user_id( $customer_id );
                                $token->set_default( true );

                                $token->save();
                            }
                        }
                    }

                    $transaction_id = isset( $parsed_response['TRANSACTION_ID'] ) ? $parsed_response['TRANSACTION_ID'] : "";
                    $result_desc    = isset( $parsed_response['RESULT_DESC'] ) ? $parsed_response['RESULT_DESC'] : "";
                    $pay_request_id = $_POST['PAY_REQUEST_ID'];

                    // Get latest order in case notify has updated first
                    $order = wc_get_order( $order_id );
                    switch ( $status ) {
                        case 1:
                            if ( $this->settings['disablenotify'] == 'yes' ) {
                                $order->add_order_note( 'Response via Redirect: Transaction successful<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {
                                    $order->payment_complete();
                                }
                                $woocommerce->cart->empty_cart();
                            }
                            $redirect_link = $this->get_return_url( $order );
                            if ( $this->settings['payment_type'] !== 'iframe' ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                            }
                            exit;
                            break;
                        case 2:
                            $this->add_notice( 'The transaction failed', 'error' );
                            if ( $this->settings['disablenotify'] == 'yes' ) {
                                $order->add_order_note( 'Response via Redirect, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                if ( !$order->has_status( 'failed' ) ) {
                                    $order->update_status( 'failed' );
                                }
                            }
                            $redirect_link = $order->get_cancel_order_url();
                            if ( $this->settings['payment_type'] !== 'iframe' ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                            }
                            exit;
                            break;
                        case 4:
                            $this->add_notice( 'The transaction was cancelled', 'error' );
                            if ( $this->settings['disablenotify'] == 'yes' ) {
                                $order->add_order_note( 'Response via Redirect: User cancelled transaction<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                if ( !$order->has_status( 'failed' ) ) {
                                    $order->update_status( 'failed' );
                                }
                            }
                            $redirect_link = $order->get_cancel_order_url();
                            if ( $this->settings['payment_type'] !== 'iframe' ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                            }
                            exit;
                            break;
                        default:
                            if ( $this->settings['disablenotify'] == 'yes' ) {
                                $order->add_order_note( 'Response via ' . $this->settings['payment_type'] . ', RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                if ( !$order->has_status( 'pending' ) ) {
                                    $order->update_status( 'pending' );
                                }

                                $this->add_notice( 'Your purchase is either pending or an error has occurred. Please follow up with the whomever necessary.',
                                    'error' );
                            }
                            $redirect_link = $order->get_cancel_order_url();
                            if ( $this->settings['payment_type'] !== 'iframe' ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                            }
                            exit;
                            break;
                    }
                }

                $order = new WC_Order( $order_id );

                $redirect_link = $status == 1 ? $this->get_return_url( $order ) : htmlspecialchars_decode( urldecode( $order->get_cancel_order_url() ) );

                wp_redirect( $redirect_link );
            } else {
                wp_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ) );
            }

            exit;
        }
        die();
    }

    /**
     * Add WooCommerce notice
     *
     * @since 1.0.0
     *
     */
    public function add_notice(
        $message, $notice_type = 'success'
    ) {
        global $woocommerce;
        // If function should we use?
        if ( function_exists( "wc_add_notice" ) ) {
            // Use the new version of the add_error method
            wc_add_notice( $message, $notice_type );
        } else {
            // Use the old version
            $woocommerce->add_error( $message );
        }
    }

    /**
     * Check for valid PayGate Notify
     *
     * @since 1.0.0
     */
    public function check_paygate_notify_response()
    {
        global $woocommerce;

        // Tell PayGate notify we have received
        echo 'OK';

        // Check if IPN disabled and process if not
        if ( $this->settings['disablenotify'] != 'yes' ) {
            if ( isset( $_POST ) ) {

                $errors       = false;
                $paygate_data = array();
                $notify_data  = array();

                // Get notify data
                if ( !$errors ) {
                    $paygate_data = $this->get_post_data();
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
                    $order_id = explode( "-", $paygate_data['REFERENCE'] );
                    $order_id = $order_id[0];
                } else {
                    $order_id = '';
                }

                if ( $this->payVault == 'yes' ) {
                    $order       = wc_get_order( trim( $order_id ) );
                    $customer_id = $order->get_customer_id();
                    $vaultCard   = get_post_meta( $customer_id, 'wc-' . $this->id . '-new-payment-method', true );

                    if ( $vaultCard == true && array_key_exists( 'VAULT_ID', $paygate_data ) ) {
                        // Save Token details
                        $vaultId  = $paygate_data['VAULT_ID'];
                        $card     = isset( $paygate_data['PAYVAULT_DATA_1'] ) ? $paygate_data['PAYVAULT_DATA_1'] : "";
                        $expiry   = isset( $paygate_data['PAYVAULT_DATA_2'] ) ? $paygate_data['PAYVAULT_DATA_2'] : "";
                        $cardType = isset( $paygate_data['PAY_METHOD_DETAIL'] ) ? $paygate_data['PAY_METHOD_DETAIL'] : "";

                        // Get existing tokens for user
                        $tokenDs = new WC_Payment_Token_Data_Store();
                        $tokens  = $tokenDs->get_tokens( [
                            'user_id' => $customer_id,
                        ] );

                        $exists = false;
                        foreach ( $tokens as $token ) {
                            if ( $token->token == $vaultId ) {
                                $exists = true;
                            }
                        }

                        if ( !$exists ) {
                            $token = new WC_Payment_Token_CC();

                            $token->set_token( $vaultId );
                            $token->set_gateway_id( $this->id );
                            $token->set_card_type( strtolower( $cardType ) );
                            $token->set_last4( substr( $card, -4 ) );
                            $token->set_expiry_month( substr( $expiry, 0, 2 ) );
                            $token->set_expiry_year( substr( $expiry, -4 ) );
                            $token->set_user_id( get_current_user_id() );
                            $token->set_default( true );

                            $token->save();
                        }
                    }
                }

                if ( $order_id != '' ) {
                    $order = wc_get_order( trim( $order_id ) );
                    if ( !$errors ) {
                        if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {

                            $transaction_id = isset( $paygate_data['TRANSACTION_ID'] ) ? $paygate_data['TRANSACTION_ID'] : "";
                            $result_desc    = isset( $paygate_data['RESULT_DESC'] ) ? $paygate_data['RESULT_DESC'] : "";
                            $pay_request_id = isset( $paygate_data['PAY_REQUEST_ID'] ) ? $paygate_data['PAY_REQUEST_ID'] : "";

                            switch ( $paygate_data['TRANSACTION_STATUS'] ) {
                                case 1:
                                    $order->add_order_note( 'Response via Notify: Transaction successful<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                    if ( !$order->has_status( 'processing' ) && !$order->has_status( 'completed' ) ) {
                                        $order->payment_complete();
                                    }
                                    $woocommerce->cart->empty_cart();
                                    $redirect_link = $this->get_return_url( $order );
                                    echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                                    exit;
                                    break;
                                case 2:

                                    $order->add_order_note( 'Response via Notify, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                    if ( !$order->has_status( 'failed' ) ) {
                                        $order->update_status( 'failed' );
                                    }
                                    $redirect_link = $this->get_return_url( $order );
                                    echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                                    exit;
                                    break;
                                case 4:

                                    $order->add_order_note( 'Response via Notify, User cancelled transaction<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                    if ( !$order->has_status( 'failed' ) ) {
                                        $order->update_status( 'failed' );
                                    }
                                    $redirect_link = $this->get_return_url( $order );
                                    echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                                    exit;
                                    break;
                                default:

                                    $order->add_order_note( 'Response via Notify, RESULT_DESC: ' . $result_desc . '<br/>PayGate Trans Id: ' . $transaction_id . ' Pay Request Id: ' . $pay_request_id . '<br/>' );
                                    if ( !$order->has_status( 'pending' ) ) {
                                        $order->update_status( 'pending' );
                                    }
                                    $redirect_link = $this->get_return_url( $order );
                                    echo '<script>window.top.location.href="' . $redirect_link . '";</script>';
                                    exit;
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
    }

    /**
     * Debug logger
     *
     * @since 1.1.3
     */
    public function write_log(
        $log
    ) {
        if ( is_array( $log ) || is_object( $log ) ) {
            error_log( print_r( $log, true ) );
        } else {
            error_log( $log );
        }
    }
}
