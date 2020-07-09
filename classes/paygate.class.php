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

    const TEST_PAYGATE_ID            = '10011072130';
    const TEST_ENCRYPTION_KEY        = 'secret';
    const ID                         = 'paygate';
    const NEW_PAYMENT_METHOD_SESSION = 'wc-paygate-new-payment-method';
    const PAYGATE_PAYMENT_TOKEN      = 'wc-paygate-payment-token';
    const ENCRYPTION_KEY             = 'encryption_key';
    const TITLE                      = 'title';
    const PAYGATE_ID_LOWER_CASE      = 'paygate_id';
    const DESCRIPTION                = 'description';
    const MESSAGE                    = 'message';
    const WC_CLASS                   = 'class';
    const TESTMODE                   = 'testmode';
    const CHECKBOX                   = 'checkbox';
    const DESC_TIP                   = 'desc_tip';
    const DEFAULT_CONST              = 'default';
    const PAYMENT_TYPE               = 'payment_type';
    const REDIRECT                   = 'redirect';
    const IFRAME                     = 'iframe';
    const DISABLENOTIFY              = 'disablenotify';
    const TRANSACTION_STATUS         = 'TRANSACTION_STATUS';
    const RESULT_CODE                = 'RESULT_CODE';
    const RESULT_DESC                = 'RESULT_DESC';
    const PROCESSING                 = 'processing';
    const COMPLETED                  = 'completed';
    const FAILED                     = 'failed';
    const PAYGATE_ID                 = 'PAYGATE_ID';
    const PAY_REQUEST_ID             = 'PAY_REQUEST_ID';
    const REFERENCE                  = 'REFERENCE';
    const CHECKSUM                   = 'CHECKSUM';
    const METHOD                     = 'method';
    const TIMEOUT                    = 'timeout';
    const SSLVERIFY                  = 'sslverify';
    const USER_AGENT                 = 'user-agent';
    const HTTPVERSION                = 'httpversion';
    const ORDER_ID                   = 'order_id';
    const VAULT                      = 'VAULT';
    const PAYMENT_TOKEN              = '-payment-token';
    const VAULT_ID                   = 'VAULT_ID';
    const PAYGATE_CHECKOUT_JS        = 'paygate-checkout-js';
    const NEW_PAYMENT_METHOD         = '-new-payment-method';
    const PAYVAULT_DATA_1            = 'PAYVAULT_DATA_1';
    const PAYVAULT_DATA_2            = 'PAYVAULT_DATA_2';
    const PAY_METHOD_DETAIL          = 'PAY_METHOD_DETAIL';
    const TRANSACTION_ID             = 'TRANSACTION_ID';
    const PAY_REQUEST_ID_TEXT        = ' Pay Request Id: ';
    const BR                         = '<br/>';
    const SCRIPT_TAG                 = '";</script>';
    const SCRIPT_WIN_TOP_LOCAT_HREF  = '<script>window.top.location.href="';
    const ERROR                      = 'error';
    const PAYGATE_TRANS_ID           = '<br/>PayGate Trans Id: ';
    const PENDING                    = 'pending';

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

        $this->post = $_POST;
        if ( !empty( $_POST ) ) {
            if ( isset( $_POST[self::NEW_PAYMENT_METHOD_SESSION] ) ) {
                $_SESSION[self::NEW_PAYMENT_METHOD_SESSION] = '1';
            } else {
                $_SESSION[self::NEW_PAYMENT_METHOD_SESSION] = '0';
            }

            if ( isset( $_POST[self::PAYGATE_PAYMENT_TOKEN] ) ) {
                $_SESSION[self::PAYGATE_PAYMENT_TOKEN] = $_POST[self::PAYGATE_PAYMENT_TOKEN];
            } else {
                unset( $_SESSION[self::PAYGATE_PAYMENT_TOKEN] );
            }
        }

        $this->method_title       = __( 'PayGate via PayWeb3', self::ID );
        $this->method_description = __( 'PayGate via PayWeb3 works by sending the customer to PayGate to complete their payment.',
            self::ID );
        $this->icon       = $this->get_plugin_url() . '/assets/images/logo_small.png';
        $this->has_fields = true;
        $this->supports   = array(
            'products',
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->merchant_id       = $this->settings[self::PAYGATE_ID_LOWER_CASE];
        $this->encryption_key    = $this->settings[self::ENCRYPTION_KEY];
        $this->title             = $this->settings[self::TITLE];
        $this->order_button_text = $this->settings['button_text'];
        $this->description       = $this->settings[self::DESCRIPTION];
        $this->payVault          = $this->settings['payvault'];

        if ( $this->payVault == 'yes' ) {
            $this->supports[] = 'tokenization';
        }

        $this->msg[self::MESSAGE]  = "";
        $this->msg[self::WC_CLASS] = "";

        // Setup the test data, if in test mode
        if ( $this->settings[self::TESTMODE] == 'yes' ) {
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
            'enabled'                   => array(
                self::TITLE         => __( 'Enable/Disable', self::ID ),
                'label'             => __( 'Enable PayGate Payment Gateway', self::ID ),
                'type'              => self::CHECKBOX,
                self::DESCRIPTION   => __( 'This controls whether or not this gateway is enabled within WooCommerce.',
                    self::ID ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::TITLE                 => array(
                self::TITLE         => __( 'Title', self::ID ),
                'type'              => 'text',
                self::DESCRIPTION   => __( 'This controls the title which the user sees during checkout.', self::ID ),
                self::DESC_TIP      => false,
                self::DEFAULT_CONST => __( 'PayGate Payment Gateway', self::ID ),
            ),
            self::PAYGATE_ID_LOWER_CASE => array(
                self::TITLE         => __( 'PayGate ID', self::ID ),
                'type'              => 'text',
                self::DESCRIPTION   => __( 'This is the PayGate ID, received from PayGate.', self::ID ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => '',
            ),
            self::ENCRYPTION_KEY        => array(
                self::TITLE         => __( 'Encryption Key', self::ID ),
                'type'              => 'text',
                self::DESCRIPTION   => __( 'This is the Encryption Key set in the PayGate Back Office.', self::ID ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => '',
            ),
            self::PAYMENT_TYPE          => array(
                self::TITLE         => __( 'Implementation', self::ID ),
                'label'             => __( 'Choose payment type', self::ID ),
                'type'              => 'select',
                self::DESCRIPTION   => 'Changes the display implementation - Redirect or iFrame.',
                self::DEFAULT_CONST => self::REDIRECT,
                'options'           => array(
                    self::REDIRECT => 'Redirect',
                    self::IFRAME   => 'iFrame',
                ),
            ),
            self::TESTMODE              => array(
                self::TITLE         => __( 'Test mode', self::ID ),
                'type'              => self::CHECKBOX,
                self::DESCRIPTION   => __( 'Uses a PayGate test account. Request test cards from PayGate', self::ID ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'yes',
            ),
            self::DISABLENOTIFY         => array(
                self::TITLE         => __( 'Disable IPN', self::ID ),
                'type'              => self::CHECKBOX,
                self::DESCRIPTION   => __( 'Disable IPN notify method and use redirect method instead.', self::ID ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            'payvault'                  => array(
                self::TITLE         => __( 'Enable PayVault', self::ID ),
                'type'              => self::CHECKBOX,
                self::DESCRIPTION   => __( 'Provides the ability for users to store their credit card details.', self::ID ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::DESCRIPTION           => array(
                self::TITLE         => __( 'Description', self::ID ),
                'type'              => 'textarea',
                self::DESCRIPTION   => __( 'This controls the description which the user sees during checkout.', self::ID ),
                self::DEFAULT_CONST => 'Pay via PayGate',
            ),
            'button_text'               => array(
                self::TITLE         => __( 'Order Button Text', self::ID ),
                'type'              => 'text',
                self::DESCRIPTION   => __( 'Changes the text that appears on the Place Order button', self::ID ),
                self::DEFAULT_CONST => 'Proceed to PayGate',
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
        $this->form_fields[self::PAYGATE_ID_LOWER_CASE][self::DESCRIPTION] .= ' <br><br><strong>' . __( 'PayGate ID currently in use.',
            self::ID ) . ' ( 10011072130 )</strong>';
        $this->form_fields[self::ENCRYPTION_KEY][self::DESCRIPTION] .= ' <br><br><strong>' . __( 'PayGate Encryption Key currently in use.',
            self::ID ) . ' ( secret )</strong>';
    }

    /**
     * Custom order action - query order status
     * Add custom action to order actions select box
     */
    public static function paygate_add_order_meta_box_action( $actions )
    {
        global $theorder;
        if ( $theorder->get_payment_method() == self::ID ) {
            $actions['wc_custom_order_action'] = __( 'Query order status with PayGate', self::ID );
        }

        return $actions;
    }

    public static function paygate_order_query_action( $order )
    {
        global $wpdb;
        $gw       = new WC_Gateway_PayGate();
        $order_id = $order->get_id();

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
        $transactionStatus = !empty( $response[self::TRANSACTION_STATUS] ) ? $response[self::TRANSACTION_STATUS] : 'Null';
        $resultCode        = !empty( $response[self::RESULT_CODE] ) ? $response[self::RESULT_CODE] : 'Null';
        $resultDesc        = !empty( $response[self::RESULT_DESC] ) ? $response[self::RESULT_DESC] : 'Null';
        $responseText      = <<<RT
Pay_Request_Id: {$payRequestId}<br>
Transaction Status: {$transactionStatus} => {$gw->paywebStatus[(int)$transactionStatus]}<br>
Result Code: {$resultCode}<br>
Result Description: {$resultDesc}
RT;

        if ( (int) $transactionStatus === 1 ) {
            if ( !$order->has_status( self::PROCESSING ) && !$order->has_status( self::COMPLETED ) ) {
                $order->payment_complete();
                $responseText .= "<br>Order set to \"Processing\"";
            }
        } else {
            if ( !$order->has_status( self::FAILED ) ) {
                $order->update_status( self::FAILED );
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
        return $wpdb->get_results( "
        SELECT comment_content from $table
        WHERE `comment_post_ID` = $order_id
        AND `comment_type` = 'order_note'
        " );

    }

    private function paywebQuery( $payRequestId, $order )
    {
        $fields = array(
            self::PAYGATE_ID     => $this->merchant_id,
            self::PAY_REQUEST_ID => $payRequestId,
            self::REFERENCE      => $order->get_id() . '-' . $order->get_order_number(),
        );
        $fields[self::CHECKSUM] = md5( implode( '', $fields ) . $this->encryption_key );

        $response = wp_remote_post( $this->query_url, array(
            self::METHOD      => 'POST',
            'body'            => $fields,
            self::TIMEOUT     => 70,
            self::SSLVERIFY   => true,
            self::USER_AGENT  => 'WooCommerce/' . WC_VERSION,
            self::HTTPVERSION => '1.1',
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
                $transactionStatus = !empty( $response[self::TRANSACTION_STATUS] ) ? $response[self::TRANSACTION_STATUS] : 'Null';
                $resultCode        = !empty( $response[self::RESULT_CODE] ) ? $response[self::RESULT_CODE] : 'Null';
                $resultDesc        = !empty( $response[self::RESULT_DESC] ) ? $response[self::RESULT_DESC] : 'Null';
                $responseText      = <<<RT
 Pay_Request_Id: {$payRequestId}<br>
 Transaction Status: {$transactionStatus} => {$gw->paywebStatus[(int)$transactionStatus]}<br>
 Result Code: {$resultCode}<br>
 Result Description: {$resultDesc}
RT;

                if ( (int) $transactionStatus === 1 ) {
                    if ( !$order->has_status( self::PROCESSING ) && !$order->has_status( self::COMPLETED ) ) {
                        $order->payment_complete();
                        $responseText .= "<br>Order set to \"Processing\"";
                    }
                } else {
                    if ( !$order->has_status( self::FAILED ) ) {
                        $order->update_status( self::FAILED );
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
        <h3><?php _e( 'PayGate Payment Gateway', self::ID );?></h3>
        <p><?php printf( __( 'PayGate works by sending the user to %sPayGate%s to enter their payment information.',
            self::ID ), '<a href="https://www.paygate.co.za/">', '</a>' );?></p>

        <table class="form-table">
            <th scope="col">PayGate Settings</th>
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
            if ( !empty( $_POST ) ) {
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
            if ( isset( $this->settings[self::DESCRIPTION] ) && $this->settings[self::DESCRIPTION] != '' ) {
                echo wpautop( wptexturize( $this->settings[self::DESCRIPTION] ) );
            }
        }
    }

    public function process_review_payment()
    {
        if ( !empty( $_POST[self::ORDER_ID] ) ) {
            $this->process_payment( $_POST[self::ORDER_ID] );
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
        if ( $this->settings[self::PAYMENT_TYPE] === self::IFRAME ) {
            echo $this->get_ajax_return_data_json( $order_id );
            die;
        } else {
            $order = new WC_Order( $order_id );

            return [
                'result'       => 'success',
                self::REDIRECT => $order->get_checkout_payment_url( true ),
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
            self::PAY_REQUEST_ID => $returnParams[self::PAY_REQUEST_ID],
            self::CHECKSUM       => $returnParams[self::CHECKSUM],
            'result'             => 'failure',
            'reload'             => false,
            'refresh'            => true,
            'paygate_override'   => true,
            self::MESSAGE        => false,
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

        if ( $this->settings[self::TESTMODE] == 'yes' ) {
            $this->merchant_id    = self::TEST_PAYGATE_ID;
            $this->encryption_key = self::TEST_ENCRYPTION_KEY;
        }

        // Construct variables for post
        $order_total = $order->get_total();
        if ( $this->settings[self::DISABLENOTIFY] != 'yes' ) {
            $this->data_to_send = array(
                self::PAYGATE_ID   => $this->merchant_id,
                self::REFERENCE    => $order->get_id() . '-' . $order->get_order_number(),
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
                self::PAYGATE_ID   => $this->merchant_id,
                self::REFERENCE    => $order->get_id() . '-' . $order->get_order_number(),
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
        if ( isset( $_SESSION[self::NEW_PAYMENT_METHOD_SESSION] ) && $_SESSION[self::NEW_PAYMENT_METHOD_SESSION] == '1' ) {
            $newPaymentMethod = true;
        }

        if ( $this->payVault == 'yes' ) {
            // Tokenisation is enabled for store
            // Check if customer has existing tokens or chooses to tokenise
            $custVault = get_post_meta( $customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, true );

            switch ( true ) {
                case ( !$custVault && $newPaymentMethod ):
                case ( isset( $_SESSION['wc-' . $this->id . self::PAYMENT_TOKEN] ) && $_SESSION['wc-' . $this->id . self::PAYMENT_TOKEN] == 'new' ):
                    // Customer requesting remember card number
                    update_post_meta( $customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, true );
                    $this->data_to_send[self::VAULT] = 1;
                    break;
                case ( isset( $_SESSION['wc-' . $this->id . self::PAYMENT_TOKEN] ) && $_SESSION['wc-' . $this->id . self::PAYMENT_TOKEN] == 'no' ):
                    update_post_meta( $customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, false );
                    break;
                case ( isset( $_SESSION['wc-' . $this->id . self::PAYMENT_TOKEN] ) ):
                    $this->data_to_send[self::VAULT_ID] = $_SESSION['wc-' . $this->id . self::PAYMENT_TOKEN];
                    break;
                case ( $newPaymentMethod ):
                    $this->data_to_send[self::VAULT] = 1;
                    break;
                default:
                    $this->data_to_send[self::VAULT] = 0;
                    break;
            }
        }

        $this->data_to_send[self::CHECKSUM] = md5( implode( '', $this->data_to_send ) . $this->encryption_key );

        $this->initiate_response = wp_remote_post( $this->initiate_url, array(
            self::METHOD      => 'POST',
            'body'            => $this->data_to_send,
            self::TIMEOUT     => 70,
            self::SSLVERIFY   => true,
            self::USER_AGENT  => 'WooCommerce',
            self::HTTPVERSION => '1.1',
        ) );

        if ( is_wp_error( $this->initiate_response ) ) {
            return $this->initiate_response;
        }

        parse_str( $this->initiate_response['body'], $parsed_response );

        if ( empty( $this->initiate_response['body'] ) || array_key_exists( 'ERROR',
            $parsed_response ) || !array_key_exists( self::PAY_REQUEST_ID, $parsed_response ) ) {
            $this->msg[self::WC_CLASS] = 'woocommerce-error';
            $this->msg[self::MESSAGE]  = "Thank you for shopping with us. However, we were unable to initiate your payment. Please try again.";
            if ( !$order->has_status( self::FAILED ) ) {
                $order->update_status( self::FAILED );
            }
            $order->add_order_note( 'Response from initiating payment:' . print_r( $this->data_to_send,
                true ) . ' ' . $this->initiate_response['body'] );

            return new WP_Error( 'paygate-error',
                __( $this->show_message( '<br><a class="button wc-forward" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart',
                    self::ID ) . '</a>' ), self::ID ) );
        } else {
            $_SESSION['PARSED_RESPONSE'] = $parsed_response;
        }

        $this->initiate_response['body'] = $parsed_response;

        // Add order note with the PAY_REQUEST_ID for custom query
        $order->add_order_note( 'Initiate payment successful. Pay Request Id: ' . $parsed_response[self::PAY_REQUEST_ID] );

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
        return '<div class="' . $this->msg[self::WC_CLASS] . '">' . $this->msg[self::MESSAGE] . '</div>' . $content;
    }

    /**
     * Add payment scripts for iFrame support
     *
     * @since 1.0.0
     */
    public function paygate_payment_scripts()
    {
        if ( $this->settings[self::PAYMENT_TYPE] === self::IFRAME ) {
            wp_enqueue_script( self::PAYGATE_CHECKOUT_JS, $this->get_plugin_url() . '/assets/js/paygate_checkout.js',
                array(),
                WC_VERSION, true );
            if ( is_wc_endpoint_url( 'order-pay' ) ) {
                $order_id = $this->get_order_id_order_pay();
                wp_localize_script( self::PAYGATE_CHECKOUT_JS, 'paygate_checkout_js', array(
                    'is_order_pay'      => true,
                    'pay_now_form_data' => $this->get_ajax_return_data_json( $order_id ),
                ) );

            } else {
                wp_localize_script( self::PAYGATE_CHECKOUT_JS, 'paygate_checkout_js', array(
                    'is_order_pay' => false,
                    self::ORDER_ID => 0,
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
            return null;
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
            if ( $this->settings[self::PAYMENT_TYPE] !== self::IFRAME ) {
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
            self::ID ) );

        unset( $parsed_response[self::CHECKSUM] );
        $checksum = md5( implode( '', $parsed_response ) . $this->encryption_key );

        $heading    = __( 'Thank you for your order, please click the button below to pay via PayGate.', self::ID );
        $buttonText = __( $this->order_button_text, self::ID );
        $cancelUrl  = esc_url( $order->get_cancel_order_url() );
        $cancelText = __( 'Cancel order &amp; restore cart', self::ID );

        $form = <<<HTML
<p>{$heading}</p>
<form action="{$this->process_url}" method="post" id="paygate_payment_form">
    <input name="PAY_REQUEST_ID" type="hidden" value="{$parsed_response[self::PAY_REQUEST_ID]}" />
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
        if ( isset( $_GET['gid'] ) && isset( $_POST[self::PAY_REQUEST_ID] ) ) {
            $order_id = $_GET['gid'];

            if ( $order_id != '' ) {
                $order       = wc_get_order( $order_id );
                $customer_id = $order->get_customer_id();

                $pay_request_id = $_POST[self::PAY_REQUEST_ID];
                $status         = isset( $_POST[self::TRANSACTION_STATUS] ) ? $_POST[self::TRANSACTION_STATUS] : "";
                $checksum       = isset( $_POST[self::CHECKSUM] ) ? $_POST[self::CHECKSUM] : "";

                if ( $this->settings[self::TESTMODE] == 'yes' ) {
                    $this->merchant_id    = self::TEST_PAYGATE_ID;
                    $this->encryption_key = self::TEST_ENCRYPTION_KEY;
                }

                $reference       = $order->get_id() . '-' . $order->get_order_number();
                $checksum_source = $this->merchant_id . $pay_request_id . $status . $reference . $this->encryption_key;
                $test_checksum   = md5( $checksum_source );

                if ( $checksum == $test_checksum ) {
                    $fields = array(
                        self::PAYGATE_ID     => $this->merchant_id,
                        self::PAY_REQUEST_ID => $_POST[self::PAY_REQUEST_ID],
                        self::REFERENCE      => $order->get_id() . '-' . $order->get_order_number(),
                    );
                    $fields[self::CHECKSUM] = md5( implode( '', $fields ) . $this->encryption_key );

                    $response = wp_remote_post( $this->query_url, array(
                        self::METHOD      => 'POST',
                        'body'            => $fields,
                        self::TIMEOUT     => 70,
                        self::SSLVERIFY   => true,
                        self::USER_AGENT  => 'WooCommerce/' . WC_VERSION,
                        self::HTTPVERSION => '1.1',
                    ) );

                    parse_str( $response['body'], $parsed_response );

                    if ( $this->payVault == 'yes' ) {
                        $this->vaultCard = get_post_meta( $customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, true );

                        if ( $this->vaultCard && array_key_exists( self::VAULT_ID, $parsed_response ) ) {
                            // Save Token details
                            $this->vaultId = $parsed_response[self::VAULT_ID];
                            $card          = isset( $parsed_response[self::PAYVAULT_DATA_1] ) ? $parsed_response[self::PAYVAULT_DATA_1] : "";
                            $expiry        = isset( $parsed_response[self::PAYVAULT_DATA_2] ) ? $parsed_response[self::PAYVAULT_DATA_2] : "";
                            $cardType      = isset( $parsed_response[self::PAY_METHOD_DETAIL] ) ? $parsed_response[self::PAY_METHOD_DETAIL] : "";

                            // Get existing tokens for user
                            $tokenDs = new WC_Payment_Token_Data_Store();
                            $tokens  = $tokenDs->get_tokens( [
                                'user_id' => $customer_id,
                            ] );

                            $exists = false;

                            foreach ( $tokens as $token ) {
                                if ( $token->token == $this->vaultId ) {
                                    $exists = true;
                                }
                            }

                            if ( !$exists ) {
                                $token = new WC_Payment_Token_CC();

                                $token->set_token( $this->vaultId );
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

                    $transaction_id = isset( $parsed_response[self::TRANSACTION_ID] ) ? $parsed_response[self::TRANSACTION_ID] : "";
                    $result_desc    = isset( $parsed_response[self::RESULT_DESC] ) ? $parsed_response[self::RESULT_DESC] : "";
                    $pay_request_id = $_POST[self::PAY_REQUEST_ID];

                    // Get latest order in case notify has updated first
                    $order = wc_get_order( $order_id );
                    switch ( $status ) {
                        case 1:
                            if ( $this->settings[self::DISABLENOTIFY] == 'yes' ) {
                                $order->add_order_note( 'Response via Redirect: Transaction successful<br/>PayGate Trans Id: ' . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::PROCESSING ) && !$order->has_status( self::COMPLETED ) ) {
                                    $order->payment_complete();
                                }
                                $woocommerce->cart->empty_cart();
                            }
                            $redirect_link = $this->get_return_url( $order );
                            if ( $this->settings[self::PAYMENT_TYPE] !== self::IFRAME ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                            }
                            exit;
                            break;
                        case 2:
                            $this->add_notice( 'The transaction failed', self::ERROR );
                            if ( $this->settings[self::DISABLENOTIFY] == 'yes' ) {
                                $order->add_order_note( 'Response via Redirect, RESULT_DESC: ' . $result_desc . self::PAYGATE_TRANS_ID . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::FAILED ) ) {
                                    $order->update_status( self::FAILED );
                                }
                            }
                            $redirect_link = $order->get_cancel_order_url();
                            if ( $this->settings[self::PAYMENT_TYPE] !== self::IFRAME ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                            }
                            exit;
                            break;
                        case 4:
                            $this->add_notice( 'The transaction was cancelled', self::ERROR );
                            if ( $this->settings[self::DISABLENOTIFY] == 'yes' ) {
                                $order->add_order_note( 'Response via Redirect: User cancelled transaction<br/>PayGate Trans Id: ' . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::FAILED ) ) {
                                    $order->update_status( self::FAILED );
                                }
                            }
                            $redirect_link = $order->get_cancel_order_url();
                            if ( $this->settings[self::PAYMENT_TYPE] !== self::IFRAME ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                            }
                            exit;
                            break;
                        default:
                            if ( $this->settings[self::DISABLENOTIFY] == 'yes' ) {
                                $order->add_order_note( 'Response via ' . $this->settings[self::PAYMENT_TYPE] . ', RESULT_DESC: ' . $result_desc . self::PAYGATE_TRANS_ID . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::PENDING ) ) {
                                    $order->update_status( self::PENDING );
                                }

                                $this->add_notice( 'Your purchase is either pending or an error has occurred. Please follow up with the whomever necessary.',
                                    self::ERROR );
                            }
                            $redirect_link = $order->get_cancel_order_url();
                            if ( $this->settings[self::PAYMENT_TYPE] !== self::IFRAME ) {
                                wp_redirect( $redirect_link );
                            } else {
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
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
        if ( $this->settings[self::DISABLENOTIFY] != 'yes' && isset( $_POST ) ) {

            $errors       = false;
            $paygate_data = array();
            $notify_data  = array();

            // Get notify data
            if ( !$errors ) {
                $paygate_data = $this->get_post_data();
                if ( empty( $paygate_data ) ) {
                    $errors = true;
                }
            }

            // Verify security signature
            $checkSumParams = '';
            if ( $this->settings[self::TESTMODE] == 'yes' ) {
                $this->encryption_key = 'secret';
            }

            if ( !$errors ) {
                foreach ( $paygate_data as $key => $val ) {
                    $notify_data[$key] = stripslashes( $val );

                    if ( $key == self::PAYGATE_ID ) {
                        $checkSumParams .= $val;
                    }
                    if ( $key != self::CHECKSUM && $key != self::PAYGATE_ID ) {
                        $checkSumParams .= $val;
                    }

                    if ( empty( $notify_data ) ) {
                        $errors = true;
                    }
                }

                $checkSumParams .= $this->encryption_key;
            }

            // Verify security signature
            if ( !$errors ) {
                $checkSumParams = md5( $checkSumParams );
                if ( $checkSumParams != $paygate_data[self::CHECKSUM] ) {
                    $errors     = true;
                    $error_desc = 'Transaction declined.';
                }
            }

            if ( isset( $paygate_data[self::REFERENCE] ) ) {
                $order_id = explode( "-", $paygate_data[self::REFERENCE] );
                $order_id = $order_id[0];
            } else {
                $order_id = '';
            }

            if ( $this->payVault == 'yes' ) {
                $order           = wc_get_order( trim( $order_id ) );
                $customer_id     = $order->get_customer_id();
                $this->vaultCard = get_post_meta( $customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, true );

                if ( $this->vaultCard && array_key_exists( self::VAULT_ID, $paygate_data ) ) {
                    // Save Token details
                    $this->vaultId = $paygate_data[self::VAULT_ID];
                    $card          = isset( $paygate_data[self::PAYVAULT_DATA_1] ) ? $paygate_data[self::PAYVAULT_DATA_1] : "";
                    $expiry        = isset( $paygate_data[self::PAYVAULT_DATA_2] ) ? $paygate_data[self::PAYVAULT_DATA_2] : "";
                    $cardType      = isset( $paygate_data[self::PAY_METHOD_DETAIL] ) ? $paygate_data[self::PAY_METHOD_DETAIL] : "";

                    // Get existing tokens for user
                    $tokenDs = new WC_Payment_Token_Data_Store();
                    $tokens  = $tokenDs->get_tokens( [
                        'user_id' => $customer_id,
                    ] );

                    $exists = false;
                    foreach ( $tokens as $token ) {
                        if ( $token->token == $this->vaultId ) {
                            $exists = true;
                        }
                    }

                    if ( !$exists ) {
                        $token = new WC_Payment_Token_CC();

                        $token->set_token( $this->vaultId );
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
                    if ( !$order->has_status( self::PROCESSING ) && !$order->has_status( self::COMPLETED ) ) {

                        $transaction_id = isset( $paygate_data[self::TRANSACTION_ID] ) ? $paygate_data[self::TRANSACTION_ID] : "";
                        $result_desc    = isset( $paygate_data[self::RESULT_DESC] ) ? $paygate_data[self::RESULT_DESC] : "";
                        $pay_request_id = isset( $paygate_data[self::PAY_REQUEST_ID] ) ? $paygate_data[self::PAY_REQUEST_ID] : "";

                        switch ( $paygate_data[self::TRANSACTION_STATUS] ) {
                            case 1:
                                $order->add_order_note( 'Response via Notify: Transaction successful<br/>PayGate Trans Id: ' . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::PROCESSING ) && !$order->has_status( self::COMPLETED ) ) {
                                    $order->payment_complete();
                                }
                                $woocommerce->cart->empty_cart();
                                $redirect_link = $this->get_return_url( $order );
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                                exit;
                                break;
                            case 2:

                                $order->add_order_note( 'Response via Notify, RESULT_DESC: ' . $result_desc . self::PAYGATE_TRANS_ID . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::FAILED ) ) {
                                    $order->update_status( self::FAILED );
                                }
                                $redirect_link = $this->get_return_url( $order );
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                                exit;
                                break;
                            case 4:

                                $order->add_order_note( 'Response via Notify, User cancelled transaction<br/>PayGate Trans Id: ' . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::FAILED ) ) {
                                    $order->update_status( self::FAILED );
                                }
                                $redirect_link = $this->get_return_url( $order );
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                                exit;
                                break;
                            default:

                                $order->add_order_note( 'Response via Notify, RESULT_DESC: ' . $result_desc . self::PAYGATE_TRANS_ID . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR );
                                if ( !$order->has_status( self::PENDING ) ) {
                                    $order->update_status( self::PENDING );
                                }
                                $redirect_link = $this->get_return_url( $order );
                                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . $redirect_link . self::SCRIPT_TAG;
                                exit;
                                break;
                        }
                    }
                } else {

                    $order->add_order_note( 'Response via Notify, ' . $error_desc . self::BR );
                    if ( !$order->has_status( self::FAILED ) ) {
                        $order->update_status( self::FAILED );
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
