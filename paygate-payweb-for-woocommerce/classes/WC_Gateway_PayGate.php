<?php
/*
 * Copyright (c) 2020 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once 'WC_Gateway_PayGate_Portal.php';
require_once 'WC_Gateway_PayGate_Admin_Actions.php';
require_once 'WC_Gateway_PayGate_Cron.php';

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

    const TEST_PAYGATE_ID                     = '10011072130';
    const TEST_ENCRYPTION_KEY                 = 'secret';
    const ID                                  = 'paygate';
    const NEW_PAYMENT_METHOD_SESSION          = 'wc-paygate-new-payment-method';
    const PAYGATE_PAYMENT_TOKEN               = 'wc-paygate-payment-token';
    const ENCRYPTION_KEY                      = 'encryption_key';
    const TITLE                               = 'title';
    const PAYGATE_ID_LOWER_CASE               = 'paygate_id';
    const DESCRIPTION                         = 'description';
    const MESSAGE                             = 'message';
    const WC_CLASS                            = 'class';
    const TESTMODE                            = 'testmode';
    const CHECKBOX                            = 'checkbox';
    const DESC_TIP                            = 'desc_tip';
    const DEFAULT_CONST                       = 'default';
    const REDIRECT                            = 'redirect';
    const DISABLENOTIFY                       = 'disablenotify';
    const ALTERNATECARTHANDLING               = 'alternatecarthandling';
    const TRANSACTION_STATUS                  = 'TRANSACTION_STATUS';
    const RESULT_CODE                         = 'RESULT_CODE';
    const RESULT_DESC                         = 'RESULT_DESC';
    const PROCESSING                          = 'processing';
    const COMPLETED                           = 'completed';
    const FAILED                              = 'failed';
    const PENDING                             = 'pending';
    const PAYGATE_ID                          = 'PAYGATE_ID';
    const PAY_REQUEST_ID                      = 'PAY_REQUEST_ID';
    const REFERENCE                           = 'REFERENCE';
    const CHECKSUM                            = 'CHECKSUM';
    const METHOD                              = 'method';
    const TIMEOUT                             = 'timeout';
    const SSLVERIFY                           = 'sslverify';
    const USER_AGENT                          = 'user-agent';
    const HTTPVERSION                         = 'httpversion';
    const ORDER_ID                            = 'order_id';
    const VAULT                               = 'VAULT';
    const PAYMENT_TOKEN                       = '-payment-token';
    const VAULT_ID                            = 'VAULT_ID';
    const PAYGATE_CHECKOUT_JS                 = 'paygate-checkout-js';
    const PAYGATE_PAYMETHOD_JS                = 'paygate-paymethod-js';
    const NEW_PAYMENT_METHOD                  = '-new-payment-method';
    const PAYVAULT_DATA_1                     = 'PAYVAULT_DATA_1';
    const PAYVAULT_DATA_2                     = 'PAYVAULT_DATA_2';
    const PAYVAULT                            = 'PayVault';
    const ENABLE                              = 'Enable ';
    const LABEL                               = 'label';
    const TYPE                                = 'type';
    const PAY_METHOD_DETAIL                   = 'PAY_METHOD_DETAIL';
    const TRANSACTION_ID                      = 'TRANSACTION_ID';
    const PAY_REQUEST_ID_TEXT                 = ' Pay Request Id: ';
    const BR                                  = '<br/>';
    const SCRIPT_TAG                          = '";</script>';
    const SCRIPT_WIN_TOP_LOCAT_HREF           = '<script>window.top.location.href="';
    const ERROR                               = 'error';
    const PAYGATE_TRANS_ID                    = '<br/>PayGate Trans Id: ';
    const CREDIT_CARD                         = 'pw3_credit_card';
    const BANK_TRANSFER                       = 'pw3_bank_transfer';
    const ZAPPER                              = 'pw3_e_zapper';
    const SNAPSCAN                            = 'pw3_e_snapscan';
    const PAYPAL                              = 'pw3_e_paypal';
    const MOBICRED                            = 'pw3_e_mobicred';
    const MOMOPAY                             = 'pw3_e_momopay';
    const MASTERPASS                          = 'pw3_e_masterpass';
    const CREDIT_CARD_METHOD                  = 'CC';
    const BANK_TRANSFER_METHOD                = 'BT';
    const ZAPPER_METHOD                       = 'EW-ZAPPER';
    const SNAPSCAN_METHOD                     = 'EW-SNAPSCAN';
    const PAYPAL_METHOD                       = 'EW-PAYPAL';
    const MOBICRED_METHOD                     = 'EW-MOBICRED';
    const MOMOPAY_METHOD                      = 'EW-MOMOPAY';
    const MASTERPASS_METHOD                   = 'EW-MASTERPASS';
    const CREDIT_CARD_DESCRIPTION             = 'Card';
    const BANK_TRANSFER_DESCRIPTION           = 'SiD Secure EFT';
    const BANK_TRANSFER_METHOD_DETAIL         = 'SID';
    const ZAPPER_DESCRIPTION                  = 'Zapper';
    const SNAPSCAN_DESCRIPTION                = 'SnapScan';
    const PAYPAL_DESCRIPTION                  = 'PayPal';
    const MOBICRED_DESCRIPTION                = 'Mobicred';
    const MOMOPAY_DESCRIPTION                 = 'MoMoPay';
    const MOMOPAY_METHOD_DETAIL               = 'Momopay';
    const MASTERPASS_DESCRIPTION              = 'MasterPass';
    const ON_CHECKOUT                         = ' on Checkout';
    const CHECKOUT_PAYMENT_METHOD_DESCRIPTION = 'Enable quick select for this payment type on checkout.';
    const SUB_PAYMENT_METHOD                  = 'sub_payment_method';
    const SUB_PAYMENT_METHOD_DETAIL           = 'sub_payment_method_detail';
    const MUST_BE_ENABLED                     = ' must be enabled on your account. <a href="https://www.paygate.co.za/get-started/" target="_blank">Click here</a> to find out more.';
    const ORDER_META_REFERENCE                = 'order_meta_reference';
    const ORDER_META_REFERENCE_DESCRIPTION    = 'Order Meta Reference';
    const ORDER_META_REFERENCE_PLACEHOLDER    = 'Add order meta to the payment reference using a meta key (e.g. _billing_first_name)';
    const LOGGING                             = 'logging';

    public $version = '4.0.0';

    public $id = 'paygate';

    protected $initiate_url = 'https://secure.paygate.co.za/payweb3/initiate.trans';
    protected $process_url = 'https://secure.paygate.co.za/payweb3/process.trans';
    protected $query_url = 'https://secure.paygate.co.za/payweb3/query.trans';

    protected $merchant_id = self::TEST_PAYGATE_ID;
    protected $encryption_key = self::TEST_ENCRYPTION_KEY;
    protected $payVault;

    protected $vaultCard;
    protected $vaultId;

    protected $initiate_response;
    protected $notify_url;
    protected $redirect_url;
    protected $data_to_send;

    protected $msg;
    protected $post;

    protected $paywebStatus = [
        0 => 'Not Done',
        1 => 'Approved',
        2 => 'Declined',
        3 => 'Cancelled',
        4 => 'User Cancelled',
        5 => 'Received by PayGate',
        7 => 'Settlement Voided',
    ];

    protected $paymentTypes = [
        self::CREDIT_CARD_METHOD   => self::CREDIT_CARD_DESCRIPTION,
        self::BANK_TRANSFER_METHOD => self::BANK_TRANSFER_METHOD_DETAIL,
        self::ZAPPER_METHOD        => self::ZAPPER_DESCRIPTION,
        self::SNAPSCAN_METHOD      => self::SNAPSCAN_DESCRIPTION,
        self::PAYPAL_METHOD        => self::PAYPAL_DESCRIPTION,
        self::MOBICRED_METHOD      => self::MOBICRED_DESCRIPTION,
        self::MOMOPAY_METHOD       => self::MOMOPAY_METHOD_DETAIL,
        self::MASTERPASS_METHOD    => self::MASTERPASS_DESCRIPTION,
    ];

    protected $pw3_card_methods = array();
    protected $pw3_card_methods_enabled = false;

    protected $order_meta_reference = '';

    /**
     * @var WC_Logger
     */
    public static $wc_logger;

    /**
     * @var bool
     */
    public $logging = false;

    public function __construct()
    {
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        if (isset($this->settings[self::TESTMODE]) && $this->settings[self::TESTMODE] == 'no') {
            $this->merchant_id    = isset($this->settings[self::PAYGATE_ID_LOWER_CASE]) ? $this->settings[self::PAYGATE_ID_LOWER_CASE] : '';
            $this->encryption_key = isset($this->settings[self::ENCRYPTION_KEY]) ? $this->settings[self::ENCRYPTION_KEY] : '';
        } else {
            $this->form_fields = WC_Gateway_PayGate_Admin_Actions::add_testmode_admin_settings_notice(
                $this->form_fields
            );
            $this->post        = $_POST;
        }

        $wcsession = WC()->session;

        if (self::$wc_logger === null) {
            self::$wc_logger = wc_get_logger();
        }

        if (isset($this->settings[self::LOGGING]) && $this->settings[self::LOGGING] === 'yes') {
            $this->logging = true;
        }

        if ( ! empty($_POST)) {
            if (isset($_POST[self::NEW_PAYMENT_METHOD_SESSION])) {
                if ($wcsession) {
                    $wcsession->set(self::NEW_PAYMENT_METHOD_SESSION, '1');
                }
            } else {
                if ($wcsession) {
                    $wcsession->set(self::NEW_PAYMENT_METHOD_SESSION, '0');
                }
            }

            if (isset($_POST[self::PAYGATE_PAYMENT_TOKEN])) {
                if ($wcsession) {
                    $wcsession->set(
                        self::PAYGATE_PAYMENT_TOKEN,
                        filter_var(
                            $_POST[self::PAYGATE_PAYMENT_TOKEN],
                            FILTER_SANITIZE_STRING
                        )
                    );
                }
            } else {
                if ($wcsession) {
                    $wcsession->__unset(self::PAYGATE_PAYMENT_TOKEN);
                }
            }

            if (isset($_POST[self::SUB_PAYMENT_METHOD])) {
                if ($wcsession) {
                    $wcsession->set(
                        self::SUB_PAYMENT_METHOD,
                        filter_var(
                            $_POST[self::SUB_PAYMENT_METHOD],
                            FILTER_SANITIZE_STRING
                        )
                    );
                }
            } else {
                if ($wcsession) {
                    $wcsession->__unset(self::SUB_PAYMENT_METHOD);
                }
            }
        }

        $this->method_title       = __('PayGate via PayWeb3', self::ID);
        $this->method_description = __(
            'PayGate via PayWeb3 works by sending the customer to PayGate to complete their payment.',
            self::ID
        );
        $this->icon               = $this->get_plugin_url() . '/assets/images/PayGate_logo.svg';
        if (isset($this->settings['paygateplus']) && $this->settings['paygateplus'] === 'yes') {
            $this->icon = $this->get_plugin_url() . '/assets/images/PayGate_Plus_logo.svg';
        }
        $this->has_fields = true;
        $this->supports   = array(
            'products',
        );

        // Define user set variables
        $this->title             = isset($this->settings[self::TITLE]) ? $this->settings[self::TITLE] : '';
        $this->order_button_text = isset($this->settings['button_text']) ? $this->settings['button_text'] : '';
        $this->description       = isset($this->settings['button_text']) ? $this->settings[self::DESCRIPTION] : '';
        $this->payVault          = isset($this->settings['payvault']) ? $this->settings['payvault'] : '';

        if ($this->payVault == 'yes') {
            $this->supports[] = 'tokenization';
        }

        $this->msg[self::MESSAGE]  = "";
        $this->msg[self::WC_CLASS] = "";

        $this->notify_url   = add_query_arg('wc-api', 'WC_Gateway_PayGate_Notify', home_url('/'));
        $this->redirect_url = add_query_arg('wc-api', 'WC_Gateway_PayGate_Redirect', home_url('/'));

        $order_meta_reference = isset($this->settings[self::ORDER_META_REFERENCE]) ? $this->settings[self::ORDER_META_REFERENCE] : '';
        if (strlen($order_meta_reference) > 0) {
            $this->order_meta_reference = $order_meta_reference;
        }

        $this->addActions();
        $this->setCardMethods();
    }

    public static function show_cart_messages($messages)
    {
        if (isset($_GET['order_id'])) {
            $order_id      = filter_var($_GET['order_id'], FILTER_SANITIZE_NUMBER_INT);
            $orderMessages = get_post_meta($order_id, 'paygate_error');
            $orderMessage  = $orderMessages[count($orderMessages) - 1];

            echo '<h3 style="color: red;">' . esc_html($orderMessage) . '</h3>';
        }
    }

    /**
     * Custom function added to overcome notice failures in recent versions
     *
     * @param false $return
     *
     * @return string|void
     */
    protected function custom_print_notices($return = false)
    {
        if ( ! did_action('woocommerce_init')) {
            wc_doing_it_wrong(
                __FUNCTION__,
                __('This function should not be called before woocommerce_init.', 'woocommerce'),
                '2.3'
            );

            return;
        }

        $all_notices  = WC()->session->get('wc_notices', array());
        $notice_types = apply_filters('woocommerce_notice_types', array('error', 'success', 'notice'));

        // Buffer output.
        ob_start();

        foreach ($notice_types as $notice_type) {
            if (wc_notice_count($notice_type) > 0) {
                $messages = array();

                foreach ($all_notices[$notice_type] as $notice) {
                    $messages[] = isset($notice['notice']) ? $notice['notice'] : $notice;
                }

                wc_get_template(
                    "notices/{$notice_type}.php",
                    array(
                        'messages' => array_filter($messages), // @deprecated 3.9.0
                        'notices'  => array_filter($all_notices[$notice_type]),
                    )
                );
            }
        }

        if ($return) {
            return wc_kses_notice(ob_get_clean());
        }

        echo wc_kses_notice(ob_get_clean());
    }

    /**
     * Processes redirect from pay portal
     */
    public function check_paygate_response()
    {
        $check = new WC_Gateway_PayGate_Portal();
        $check->check_paygate_response();
    }

    /**
     * Processes IPN notification from pay portal
     */
    public function check_paygate_notify_response()
    {
        $check = new WC_Gateway_PayGate_Portal();
        $check->check_paygate_notify_response();
    }

    /**
     * Get the plugin URL
     *
     * @since 1.0.0
     */
    public function get_plugin_url()
    {
        if (isset($this->plugin_url)) {
            return $this->plugin_url;
        }

        if (is_ssl()) {
            return $this->plugin_url = str_replace(
                                           'http://',
                                           'https://',
                                           WP_PLUGIN_URL
                                       ) . "/" . plugin_basename(dirname(dirname(__FILE__)));
        } else {
            return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename(dirname(dirname(__FILE__)));
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
                self::TITLE         => __('Enable/Disable', self::ID),
                self::LABEL         => __('Enable PayGate Payment Gateway', self::ID),
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(
                    'This controls whether or not this gateway is enabled within WooCommerce.',
                    self::ID
                ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::TITLE                 => array(
                self::TITLE         => __('Title', self::ID),
                self::TYPE          => 'text',
                self::DESCRIPTION   => __('This controls the title which the user sees during checkout.', self::ID),
                self::DESC_TIP      => false,
                self::DEFAULT_CONST => __('PayGate', self::ID),
            ),
            self::PAYGATE_ID_LOWER_CASE => array(
                self::TITLE         => __('PayGate ID', self::ID),
                self::TYPE          => 'text',
                self::DESCRIPTION   => __('This is the PayGate ID, received from PayGate.', self::ID),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => '',
            ),
            self::ENCRYPTION_KEY        => array(
                self::TITLE         => __('Encryption Key', self::ID),
                self::TYPE          => 'text',
                self::DESCRIPTION   => __('This is the Encryption Key set in the PayGate Back Office.', self::ID),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => '',
            ),
            self::TESTMODE              => array(
                self::TITLE         => __('Test mode', self::ID),
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __('Uses a PayGate test account. Request test cards from PayGate', self::ID),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'yes',
            ),
            self::LOGGING               => array(
                self::TITLE         => __('Enable Logging', self::ID),
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __('Enable WooCommerce Logging', self::ID),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::DISABLENOTIFY         => array(
                self::TITLE         => __('Disable IPN', self::ID),
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __('Disable IPN notify method and use redirect method instead.', self::ID),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::ALTERNATECARTHANDLING => array(
                self::TITLE         => __('Alternate Cart Handling', self::ID),
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(
                    'Enable this if your cart is not cleared upon successful transaction.',
                    self::ID
                ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::DESCRIPTION           => array(
                self::TITLE         => __('Description', self::ID),
                self::TYPE          => 'textarea',
                self::DESCRIPTION   => __(
                    'This controls the description which the user sees during checkout.',
                    self::ID
                ),
                self::DEFAULT_CONST => 'Pay via PayGate',
            ),
            'button_text'               => array(
                self::TITLE         => __('Order Button Text', self::ID),
                self::TYPE          => 'text',
                self::DESCRIPTION   => __('Changes the text that appears on the Place Order button', self::ID),
                self::DEFAULT_CONST => 'Proceed to PayGate',
            ),
            'payvault'                  => array(
                self::TITLE         => __(self::ENABLE . self::PAYVAULT, self::ID),
                self::TYPE          => self::CHECKBOX,
                self::LABEL         => self::PAYVAULT . self::MUST_BE_ENABLED,
                self::DESCRIPTION   => __(
                    'Provides the ability for users to store their credit card details.',
                    self::ID
                ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            'paygateplus'               => array(
                self::TITLE         => __('Use PayGate Plus logo', self::ID),
                self::TYPE          => self::CHECKBOX,
                self::LABEL         => 'Enable the PayGate Plus logo',
                self::DESCRIPTION   => __(
                    'Check to use the PayGate Plus logo rather than the default PayGate logo',
                    self::ID
                ),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::CREDIT_CARD           => array(
                self::TITLE         => __(self::ENABLE . self::CREDIT_CARD_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::CREDIT_CARD_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::BANK_TRANSFER         => array(
                self::TITLE         => __(self::ENABLE . self::BANK_TRANSFER_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::BANK_TRANSFER_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::ZAPPER                => array(
                self::TITLE         => __(self::ENABLE . self::ZAPPER_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::ZAPPER_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::SNAPSCAN              => array(
                self::TITLE         => __(self::ENABLE . self::SNAPSCAN_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::SNAPSCAN_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::PAYPAL              => array(
                self::TITLE         => __(self::ENABLE . self::PAYPAL_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::PAYPAL_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::MOBICRED              => array(
                self::TITLE         => __(self::ENABLE . self::MOBICRED_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::MOBICRED_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::MOMOPAY               => array(
                self::TITLE         => __(self::ENABLE . self::MOMOPAY_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::MOMOPAY_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::MASTERPASS            => array(
                self::TITLE         => __(self::ENABLE . self::MASTERPASS_DESCRIPTION . self::ON_CHECKOUT, self::ID),
                self::LABEL         => self::MASTERPASS_DESCRIPTION . self::MUST_BE_ENABLED,
                self::TYPE          => self::CHECKBOX,
                self::DESCRIPTION   => __(self::CHECKOUT_PAYMENT_METHOD_DESCRIPTION),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => 'no',
            ),
            self::ORDER_META_REFERENCE  => array(
                self::TITLE         => __(self::ORDER_META_REFERENCE_DESCRIPTION, self::ID),
                self::TYPE          => 'text',
                self::DESCRIPTION   => __(self::ORDER_META_REFERENCE_PLACEHOLDER),
                self::DESC_TIP      => true,
                self::DEFAULT_CONST => '',
            ),

        );
    }

    /**
     * @param $resultDescription string
     */
    public function declined_msg($resultDescription)
    {
        echo '<p class="woocommerce-thankyou-order-failed">';
        esc_html_e($resultDescription, 'woocommerce');
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
        <h3><?php
            _e('PayGate Payment Gateway', self::ID); ?></h3>
        <p><?php
            printf(
                __(
                    'PayGate works by sending the user to %sPayGate%s to enter their payment information.',
                    self::ID
                ),
                '<a href="https://www.paygate.co.za/">',
                '</a>'
            ); ?></p>

        <table class="form-table" aria-describedby="paygate">
            <th scope="col">PayGate Settings</th>
            <?php
            $this->generate_settings_html(); // Generate the HTML For the settings form.
            ?>
        </table><!--/.form-table-->
        <?php
    }

    /**
     * Enable vaulting and card selection for PayGate
     *
     * @throws Exception
     * @throws Exception
     * @throws Exception
     * @since 1.0.0
     */
    public function payment_fields()
    {
        if ($this->payVault == 'yes' && ! empty($_POST)) {
            // Display stored credit card selection
            $tokens       = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
            $defaultToken = WC_Payment_Tokens::get_customer_default_token(get_current_user_id());
            if (count($tokens) > 0) {
                $this->showTokens($defaultToken, $tokens);
            } else {
                $token = 'wc-' . esc_attr($this->id) . '-payment-token';
                $new_method = 'wc-' . esc_attr($this->id) . '-new-payment-method';
                echo <<<HTML
<div name="$token" >
                <input type="checkbox" name="$new_method" id="wc-paygate-new-payment-method" value="true"> Remember my credit card number
</div>
HTML;
            }
        } elseif ($this->payVault == 'yes' && empty($_POST)) {
            // Display message for adding cards via "My Account" screen

            echo <<<HTML
    <p>Cards cannot be added manually. Please select the "Use a new card" option in the checkout process when paying with PayGate</p>

HTML;
        } else {
            if (isset($this->settings[self::DESCRIPTION]) && $this->settings[self::DESCRIPTION] != '') {
                echo wp_kses_post(wpautop(wptexturize(esc_html($this->settings[self::DESCRIPTION]))));
            }
        }
        $quickSelectPaymentMethods = false;


        // Add card field for enabled PayGate PayWeb payment method
        if ($this->pw3_card_methods_enabled) {
            $html = <<<HTML
<table>
<thead><tr><td></td><td></td></tr></thead>
<tbody>
<script>jQuery(".payment_method_paygate tr").click(function(){jQuery(this).find("input:first").attr("checked",!0)});</script>
HTML;
            foreach ($this->pw3_card_methods as $pw_3_card_method) {
                $html .= '<tr>';
                if ($pw_3_card_method['value'] !== '') {
                    $quickSelectPaymentMethods = true;
                    $html                      .= '<td class="card_method" ><input type="radio" name="sub_payment_method" value="' . esc_attr($pw_3_card_method['value']) . '" >&nbsp;' . esc_html($pw_3_card_method['description']) . '</td>';
                    $html                      .= '<td class="pay_method_image">';
                    $html                      .= '<img src="' . esc_url(
                            WC_HTTPS::force_https_url($pw_3_card_method['image'])
                        ) . '" alt="' . esc_attr($pw_3_card_method['description']) . '">';
                    $html                      .= isset($pw_3_card_method['image2']) ? '<img src="' . esc_url(
                            WC_HTTPS::force_https_url($pw_3_card_method['image2'])
                        ) . '" alt="' . esc_attr($pw_3_card_method['description']) . '2">' : '';
                    $html                      .= '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            if ($quickSelectPaymentMethods) {
                $allowed_tags = array_replace_recursive(
                        wp_kses_allowed_html('post'),
                    [
                        'script' => [],
                        'input' => [
                            'name' => true,
                            'value' => true,
                            'type' => true,
                        ]
                    ]
                );
                echo wp_kses($html, $allowed_tags);
            }
        }
    }

    public function process_review_payment()
    {
        if ( ! empty($_POST[self::ORDER_ID])) {
            $this->process_payment(filter_var($_POST[self::ORDER_ID], FILTER_SANITIZE_STRING));
        }
    }

    /**
     * get_icon
     *
     * Add SVG icon to checkout
     */
    public function get_icon()
    {
        $icon = '<img src="' . esc_url(WC_HTTPS::force_https_url($this->icon)) . '" alt="' . esc_attr(
                $this->get_title()
            ) . '" style="width: auto !important; height: 25px !important; max-width: 100px; border: none !important;">';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
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
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        return [
            'result'       => 'success',
            self::REDIRECT => $order->get_checkout_payment_url(true),
        ];
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     *
     * @return false|string
     * @since 1.0.0
     */
    public function get_ajax_return_data_json($order_id)
    {
        if ($wcsession = WC()->session) {
            $wcsession->set('POST', $_POST);
        }

        if (isset($this->settings[self::ALTERNATECARTHANDLING]) && $this->settings[self::ALTERNATECARTHANDLING] == 'yes') {
            WC()->cart->empty_cart();
        }

        $initiate     = new WC_Gateway_PayGate_Portal();
        $returnParams = $initiate->initiate_transaction($order_id);

        $return_data = array(
            self::PAY_REQUEST_ID => $returnParams[self::PAY_REQUEST_ID],
            self::CHECKSUM       => $returnParams[self::CHECKSUM],
            'result'             => 'failure',
            'reload'             => false,
            'refresh'            => true,
            'paygate_override'   => true,
            self::MESSAGE        => false,
        );

        return json_encode($return_data);
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
    public function show_message($content)
    {
        return '<div class="' . $this->msg[self::WC_CLASS] . '">' . $this->msg[self::MESSAGE] . '</div>' . $content;
    }

    /**
     * Add payment script for iFrame support
     * Add payment script for pay method support
     *
     * @since 1.0.0
     */
    public function paygate_payment_scripts()
    {
        wp_enqueue_style(
            'paygate-checkout-css',
            $this->get_plugin_url() . '/assets/css/paygate_checkout.css',
            array(),
            WC_VERSION
        );
        wp_enqueue_script(
            self::PAYGATE_PAYMETHOD_JS,
            $this->get_plugin_url() . '/assets/js/paygate_paymethod.js',
            array(),
            WC_VERSION,
            true
        );
    }

    /**
     * @return int|null
     */
    public function get_order_id_order_pay()
    {
        global $wp;

        // Get the order ID
        $order_id = absint($wp->query_vars['order-pay']);

        if (empty($order_id) || $order_id == 0) {
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
     * @param $order_id
     *
     * @since 1.0.0
     */
    public function receipt_page($order_id)
    {
        $receipt = new WC_Gateway_PayGate_Portal();
        // Do redirect
        $allowed_tags = array_replace_recursive(
            wp_kses_allowed_html('post'),
            [
                'script' => [],
                'form' => [
                    'action' => true,
                    'method' => true,
                    'id' => true,
                    'class' => true,
                ],
                'input' => [
                    'name' => true,
                    'type' => true,
                    'value' => true,
                    'class' => true,
                    'id' => true,
                ]
            ]
        );

        echo wp_kses($receipt->generate_paygate_form($order_id), $allowed_tags);
    }

    /**
     * Add WooCommerce notice
     *
     * @param $message
     * @param string $notice_type
     *
     * @since 1.0.0
     */
    public function add_notice($message, $notice_type = 'success', $order_id = '')
    {
        global $woocommerce;

        if ($order_id != '') {
            add_post_meta($order_id, 'paygate_error', $message);
        }

        self::$wc_logger->add('paygatepayweb', 'In add notice: ' . json_encode($message));

        if ($wc_session = WC()->session) {
            $wc_session->set('payweb3_error_message', $message);
            $notices = $wc_session->get('wc_notices');
            if (self::$wc_logger) {
                self::$wc_logger->add('paygatepayweb', 'Session notices: ' . json_encode($notices));
                self::$wc_logger->add('paygatepayweb', 'Session : ' . json_encode($wc_session));
            }
        } else {
            if (self::$wc_logger) {
                self::$wc_logger->add('paygatepayweb', 'Session not set ');
            }
        }

        // If function should we use?
        if (function_exists("wc_add_notice")) {
            // Use the new version of the add_error method
            wc_add_notice($message, $notice_type);
        } else {
            // Use the old version
            $woocommerce->add_error($message);
        }
    }

    /**
     * Helper for constructor
     */
    protected function setCardMethods()
    {
        if (isset($this->settings[self::CREDIT_CARD])) {
            $this->pw3_card_methods['credit_card']   = array(
                'description' => self::CREDIT_CARD_DESCRIPTION,
                'value'       => $this->settings[self::CREDIT_CARD] == 'yes' ? self::CREDIT_CARD_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/mastercard-visa.svg',
            );
            $this->pw3_card_methods['bank_transfer'] = array(
                'description' => self::BANK_TRANSFER_DESCRIPTION,
                'value'       => $this->settings[self::BANK_TRANSFER] == 'yes' ? self::BANK_TRANSFER_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/sid.svg',
            );
            $this->pw3_card_methods['zapper']        = array(
                'description' => self::ZAPPER_DESCRIPTION,
                'value'       => $this->settings[self::ZAPPER] == 'yes' ? self::ZAPPER_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/zapper.svg',
            );
            $this->pw3_card_methods['snapscan']      = array(
                'description' => self::SNAPSCAN_DESCRIPTION,
                'value'       => $this->settings[self::SNAPSCAN] == 'yes' ? self::SNAPSCAN_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/snapscan.svg',
            );
            $this->pw3_card_methods['paypal']      = array(
                'description' => self::PAYPAL_DESCRIPTION,
                'value'       => $this->settings[self::PAYPAL] == 'yes' ? self::PAYPAL_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/paypal.svg',
            );
            $this->pw3_card_methods['mobicred']      = array(
                'description' => self::MOBICRED_DESCRIPTION,
                'value'       => $this->settings[self::MOBICRED] == 'yes' ? self::MOBICRED_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/mobicred.svg',
            );
            $this->pw3_card_methods['momopay']       = array(
                'description' => self::MOMOPAY_DESCRIPTION,
                'value'       => $this->settings[self::MOMOPAY] == 'yes' ? self::MOMOPAY_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/momopay.svg',
            );
            $this->pw3_card_methods['masterpass']    = array(
                'description' => self::MASTERPASS_DESCRIPTION,
                'value'       => $this->settings[self::MASTERPASS] == 'yes' ? self::MASTERPASS_METHOD : '',
                'image'       => $this->get_plugin_url() . '/assets/images/masterpass.svg',
            );
        }

        foreach ($this->settings as $key => $setting) {
            if (strpos($key, 'pw3_') === 0) {
                if ($setting !== 'no' && $key !== 'pw3_credit_card') {
                    $this->pw3_card_methods_enabled = true;
                }
            }
        }
    }

    /**
     * Helper for constructor
     */
    protected function addActions()
    {
        add_action(
            'woocommerce_api_wc_gateway_paygate_redirect',
            array(
                $this,
                'check_paygate_response',
            )
        );

        add_action(
            'woocommerce_api_wc_gateway_paygate_notify',
            array(
                $this,
                'check_paygate_notify_response',
            )
        );

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action(
                'woocommerce_update_options_payment_gateways_' . $this->id,
                array(
                    &$this,
                    'process_admin_options',
                )
            );
        } else {
            add_action(
                'woocommerce_update_options_payment_gateways',
                array(
                    &$this,
                    'process_admin_options',
                )
            );
        }

        add_action(
            'woocommerce_receipt_paygate',
            array(
                $this,
                'receipt_page',
            )
        );

        add_action('wp_ajax_order_pay_payment', array($this, 'process_review_payment'));
        add_action('wp_ajax_nopriv_order_pay_payment', array($this, 'process_review_payment'));

        add_action('wp_enqueue_scripts', array($this, 'paygate_payment_scripts'));
    }

    /**
     * @param $order_id
     *
     * @return array|object|null
     */
    protected static function getOrderNotes($order_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'comments';

        return $wpdb->get_results(
            "
        SELECT comment_content from $table
        WHERE `comment_post_ID` = $order_id
        AND `comment_type` = 'order_note'
        "
        );
    }

    protected function showTokens($defaultToken, $tokens)
    {
        $token = esc_attr("wc-{$this->id}-payment-token");
        if ($this->pw3_card_methods_enabled) {
            echo <<<HTML
                        <select name="$token" class="start_hidden">
HTML;
        } else {
            echo <<<HTML
                        <select name="$token">
HTML;
        }

        $now = new DateTime(date('Y-m'));
        foreach ($tokens as $token) {
            $expires = new DateTime($token->get_expiry_year() . '-' . $token->get_expiry_month());
            $valid   = $expires >= $now;

            // Don't show expired cards
            if ($valid) {
                $cardType = ucwords($token->get_card_type());

                if ($defaultToken && $token->get_id() == $defaultToken->get_id()) {
                    $selected = 'selected';
                } else {
                    $selected = '';
                }

                $option_value = esc_attr($token->get_token());
                $card_type = esc_html($cardType);
                $last4 = esc_html($token->get_last4());
                echo <<<HTML
                     <option value="{$option_value}" {$selected}>Use {$card_type} ending in {$last4}</option> }
HTML;
            }
        }

        echo <<<HTML
                    <option value="new">Use a new card</option>
                    <option value="no">Use a new card and don't save</option>
                </select>
HTML;
    }
}
