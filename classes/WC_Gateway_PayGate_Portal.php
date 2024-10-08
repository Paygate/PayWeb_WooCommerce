<?php
/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class WC_Gateway_PayGate_Portal extends WC_Gateway_PayGate
{
    protected $error_desc = 'Checksum validation error.';

    public static function getPayRequestIdNotes($notes)
    {
        $payRequestId = '';
        foreach ($notes as $note) {
            if (strpos($note->comment_content, 'Pay Request Id:') !== false) {
                preg_match('/.*Pay Request Id: ([\dA-Z\-]+)/', $note->comment_content, $a);
                if (isset($a[1])) {
                    $payRequestId = $a[1];
                }
            }
        }

        return $payRequestId;
    }

    /**
     * Does the initiate to Paygate
     *
     * @param $order_id
     *
     * @return array|WP_Error
     */
    public function initiate_transaction($order_id)
    {
        $order       = new WC_Order($order_id);
        $customer_id = $order->get_customer_id();
        unset($this->data_to_send);

        $reference = $this->getOrderReference($order);

        // Construct variables for post
        $order_total        = $order->get_total();
        $this->data_to_send = array(
            self::PAYGATE_ID   => $this->merchant_id,
            self::REFERENCE    => $reference,
            'AMOUNT'           => number_format($order_total, 2, '', ''),
            'CURRENCY'         => $order->get_currency(),
            'RETURN_URL'       => $this->redirect_url . '&gid=' . $order_id,
            'TRANSACTION_DATE' => date('Y-m-d H:m:s'),
            'LOCALE'           => 'en-za',
            'COUNTRY'          => 'ZAF',
            'EMAIL'            => $order->get_billing_email(),
        );

        $vaultableMethod = $this->setVaultableMethod();

        if ($this->settings[self::DISABLENOTIFY] != 'yes') {
            $this->data_to_send['NOTIFY_URL'] = $this->notify_url;
        }

        $this->data_to_send['USER3'] = 'woocommerce-v' . $this->version;

        $newPaymentMethod = false;
        if ($wcsession = WC()->session) {
            $t                = $wcsession->get(self::NEW_PAYMENT_METHOD_SESSION);
            $newPaymentMethod = $this->isPaymentMethodNew($t, $newPaymentMethod);
        }

        if ($vaultableMethod && $this->payVault == 'yes') {
            // Tokenisation is enabled for store
            // Check if customer has existing tokens or chooses to tokenise
            $custVault = get_post_meta($customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, true);
            $newVault  = $noVault = false;
            if ($wcsession = WC()->session) {
                $newVault = $wcsession->get('wc-' . $this->id . self::PAYMENT_TOKEN) === 'new';
                $noVault  = $wcsession->get('wc-' . $this->id . self::PAYMENT_TOKEN) === 'no';
                $tokenSet = $wcsession->get('wc-' . $this->id . self::PAYMENT_TOKEN);
            }

            switch ($vaultableMethod) {
                case !$custVault && $newPaymentMethod:
                case $newVault:
                    // Customer requesting remember card number
                    update_post_meta($customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, true);
                    $this->data_to_send[self::VAULT] = 1;
                    break;
                case $noVault:
                    update_post_meta($customer_id, 'wc-' . $this->id . self::NEW_PAYMENT_METHOD, false);
                    break;
                case isset($tokenSet):
                    $this->data_to_send[self::VAULT_ID] = $tokenSet;
                    unset($this->data_to_send['PAY_METHOD_DETAIL']);
                    break;
                case $newPaymentMethod:
                    $this->data_to_send[self::VAULT] = 1;
                    break;
                default:
                    $this->data_to_send[self::VAULT] = 0;
                    break;
            }
        }

        $this->data_to_send[self::CHECKSUM] = md5(implode('', $this->data_to_send) . $this->encryption_key);

        $this->initiate_response = wp_remote_post(
            $this->initiate_url,
            array(
                self::METHOD      => 'POST',
                'body'            => $this->data_to_send,
                self::TIMEOUT     => 70,
                self::SSLVERIFY   => true,
                self::USER_AGENT  => 'WooCommerce',
                self::HTTPVERSION => '1.1',
            )
        );

        if (is_wp_error($this->initiate_response)) {
            return $this->initiate_response;
        }

        parse_str($this->initiate_response['body'], $parsed_response);

        if (empty($this->initiate_response['body']) || array_key_exists(
                'ERROR',
                $parsed_response
            ) || !array_key_exists(self::PAY_REQUEST_ID, $parsed_response)) {
            $this->msg[self::WC_CLASS] = 'woocommerce-error';
            $this->msg[self::MESSAGE]  = "Thank you for shopping with us. However, we were unable to initiate your payment. Please try again.";
            if (!$order->has_status(self::FAILED)) {
                $order->add_order_note(
                    'Response from initiating payment:' . print_r(
                        $this->data_to_send,
                        true
                    ) . ' ' . $this->initiate_response['body']
                );
                $order->update_status(self::FAILED);
            }

            return new WP_Error(
                'paygate-error',
                __(
                    $this->show_message(
                        '<br><a class="button wc-forward" href="' . esc_url($order->get_cancel_order_url()) . '">' . __(
                            'Cancel order &amp; restore cart',
                            self::ID
                        ) . '</a>'
                    ),
                    self::ID
                )
            );
        } else {
            if ($wcsession = WC()->session) {
                $wcsession->set('PARSED_RESPONSE', $parsed_response);
            }
        }

        $this->initiate_response['body'] = $parsed_response;

        // Add order note with the PAY_REQUEST_ID for custom query
        $order->add_order_note(
            'Initiate on Paygate started. Pay Request Id: ' . $parsed_response[self::PAY_REQUEST_ID]
        );

        if (!$order->has_status(self::PENDING)) {
            $order->update_status(self::PENDING);
        }

        return $parsed_response;
    }

    /**
     * Generate the Paygate button link.
     * Redirect case
     *
     * @param $order_id
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function generate_paygate_form($order_id)
    {
        $order           = new WC_Order($order_id);
        $parsed_response = $this->initiate_transaction($order_id);

        if ($this->settings[self::ALTERNATECARTHANDLING] == 'yes') {
            WC()->cart->empty_cart();
        }

        if (!is_wp_error($parsed_response)) {
            unset($parsed_response[self::CHECKSUM]);
            $checksum       = esc_attr(md5(implode('', $parsed_response) . $this->encryption_key));
            $process_url    = esc_url($this->process_url);
            $pay_request_id = esc_attr($parsed_response[self::PAY_REQUEST_ID]);

            return <<<HTML
<form action="{$process_url}" method="post" id="paygate_payment_form">
    <input name="PAY_REQUEST_ID" type="hidden" value="{$pay_request_id}" />
    <input name="CHECKSUM" type="hidden" value="{$checksum}" />
</form>
HTML;
        } else {
            echo esc_html($parsed_response->get_error_message());
        }
    }

    /**
     * Check for valid Paygate Redirect - from iFrame or  Redirect
     *
     * @since 1.0.0
     */
    public function check_paygate_response(): void
    {
        $this->logging ? self::$wc_logger->add('paygatepayweb', 'Redirect POST: ' . json_encode($_POST)) : '';
        // Sanitise GET and POST arrays
        $post = $this->sanitizeFields($_POST);
        $get  = $this->sanitizeFields($_GET);

        // Only process if IPN is disabled
        if (!(isset($get['gid']) && isset($post[self::PAY_REQUEST_ID]))) {
            die();
        }

        if (!$order_id = $get['gid']) {
            wp_redirect(get_permalink(wc_get_page_id('myaccount')));
        }

        if ($order_id == '') {
            exit();
        }

        $transient = get_transient('PAYGATE_PAYWEB_RED_' . $post[self::PAY_REQUEST_ID]);
        if ($transient !== false) {
            exit();
        }
        set_transient('PAYGATE_PAYWEB_RED_' . $post[self::PAY_REQUEST_ID], $post[self::PAY_REQUEST_ID], 15);

        $order       = wc_get_order($order_id);
        $customer_id = $order->get_customer_id();

        $pay_request_id = $post[self::PAY_REQUEST_ID];
        $status         = isset($post[self::TRANSACTION_STATUS]) ? $post[self::TRANSACTION_STATUS] : "";
        $reference      = $this->getOrderReference($order);

        if (!$this->validateChecksum($post, $reference) && !$order->is_paid()) {
            $order->update_status(self::PENDING, __('Checksum failed'));
            exit();
        }

        $reference = $this->getOrderReference($order);

        $fields                 = array(
            self::PAYGATE_ID     => $this->merchant_id,
            self::PAY_REQUEST_ID => $post[self::PAY_REQUEST_ID],
            self::REFERENCE      => $reference,
        );
        $fields[self::CHECKSUM] = md5(implode('', $fields) . $this->encryption_key);

        $response = wp_remote_post(
            $this->query_url,
            array(
                self::METHOD      => 'POST',
                'body'            => $fields,
                self::TIMEOUT     => 70,
                self::SSLVERIFY   => true,
                self::USER_AGENT  => 'WooCommerce/' . WC_VERSION,
                self::HTTPVERSION => '1.1',
            )
        );

        parse_str($response['body'], $parsed_response);

        if ((int)$status === 1) {
            $this->vaultCard($parsed_response, $customer_id);
        }

        $transaction_id = isset($parsed_response[self::TRANSACTION_ID]) ? $parsed_response[self::TRANSACTION_ID] : "";
        $result_desc    = isset($parsed_response[self::RESULT_DESC]) ? $parsed_response[self::RESULT_DESC] : "";

        // Get latest order in case notify has updated first
        $order = wc_get_order($order_id);

        $this->processOrderFinal($status, $order, $transaction_id, $result_desc, $pay_request_id);
    }

    /**
     * Check for valid Paygate Notify
     *
     * @since 1.0.0
     */

    public function check_paygate_notify_response(): void
    {
        // Log notify response for debugging purposes
        $this->logIPNRequest();

        // Tell Paygate notify we have received
        echo 'OK';

        if ($this->settings[self::DISABLENOTIFY] == 'yes') {
            exit();
        }

        if (!isset($_POST)) {
            exit();
        }

        // Get notify data
        $paygate_data = $this->sanitizeFields($_POST);

        $order_id = '';
        if (!isset($paygate_data[self::REFERENCE])) {
            exit;
        }

        $transient = get_transient('PAYGATE_PAYWEB_IPN_' . $paygate_data[self::REFERENCE]);
        if ($transient !== false) {
            exit();
        }
        set_transient('PAYGATE_PAYWEB_IPN_' . $paygate_data[self::REFERENCE], $paygate_data[self::REFERENCE], 15);

        $order_id = explode("-", $paygate_data[self::REFERENCE]);
        $order_id = trim($order_id[0]);

        $order = wc_get_order($order_id);
        if (!$order) {
            exit();
        }

        // Check if the order has already been paid - exit if so
        if ($order->is_paid()) {
            $order->add_order_note('Notify: Order is already paid... exiting');
            exit();
        }

        // Verify security signature
        $this->verifySecuritySignature($paygate_data, $order);

        $customer_id = $order->get_customer_id();

        if ((int)$paygate_data[self::TRANSACTION_STATUS] === 1) {
            $this->vaultCard($paygate_data, $customer_id);
        }

        if ($order->has_status(self::PROCESSING) || $order->has_status(self::COMPLETED)) {
            exit();
        }

        $transaction_id = $paygate_data[self::TRANSACTION_ID] ?? "";
        $result_desc    = $paygate_data[self::RESULT_DESC] ?? "";
        $pay_request_id = $paygate_data[self::PAY_REQUEST_ID] ?? "";

        switch ((int)$paygate_data[self::TRANSACTION_STATUS]) {
            case 1:
                $this->processOrderFinalSuccess($order, $transaction_id, $pay_request_id, 'ipn');
                exit;
                break;
            case 2:
                $this->add_notice('The transaction was declined', self::ERROR, $order_id);
                $this->processOrderFinalFail($order, $transaction_id, $pay_request_id, $result_desc, 'ipn');
                exit;
                break;
            case 4:
                $this->add_notice('The transaction was cancelled', self::ERROR, $order_id);
                $this->processOrderFinalCancel($order, $transaction_id, $pay_request_id, 'ipn');
                exit;
                break;
            default:
                if (!$order->has_status(self::PENDING)) {
                    if ($this->logging) {
                        self::$wc_logger->add('paygatepayweb', 'Reached default in switch statement');
                    }
                    $order->add_order_note(
                        'Response via Notify, RESULT_DESC: ' . $result_desc . self::PAYGATE_TRANS_ID . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                    );
                    $order->update_status(self::PENDING);
                }
                $redirect_link = $this->get_return_url($order);
                echo self::SCRIPT_WIN_TOP_LOCAT_HREF . esc_html($redirect_link) . self::SCRIPT_TAG;
                exit;
                break;
        }
    }

    /**
     * @param $payRequestId
     * @param $order
     *
     * @return mixed
     */
    public function paywebQuery($payRequestId, $order): mixed
    {
        $reference = $this->getOrderReference($order);

        $fields                 = array(
            self::PAYGATE_ID     => $this->merchant_id,
            self::PAY_REQUEST_ID => $payRequestId,
            self::REFERENCE      => $reference,
        );
        $fields[self::CHECKSUM] = md5(implode('', $fields) . $this->encryption_key);

        $response = wp_remote_post(
            $this->query_url,
            array(
                self::METHOD      => 'POST',
                'body'            => $fields,
                self::TIMEOUT     => 70,
                self::SSLVERIFY   => true,
                self::USER_AGENT  => 'WooCommerce/' . WC_VERSION,
                self::HTTPVERSION => '1.1',
            )
        );

        parse_str($response['body'], $parsed_response);

        return $parsed_response;
    }

    /**
     * @param $response
     * @param $payRequestId
     *
     * @return string
     */
    public function paywebQueryText($response, $payRequestId)
    {
        $transactionStatus = !empty($response[self::TRANSACTION_STATUS]) ? $response[self::TRANSACTION_STATUS] : 'Null';
        $resultCode        = !empty($response[self::RESULT_CODE]) ? $response[self::RESULT_CODE] : 'Null';
        $resultDesc        = !empty($response[self::RESULT_DESC]) ? $response[self::RESULT_DESC] : 'Null';

        return <<<RT
Pay_Request_Id: {$payRequestId}<br>
Transaction Status: {$transactionStatus} => {$this->paywebStatus[(int)$transactionStatus]}<br>
Result Code: {$resultCode}<br>
Result Description: {$resultDesc}
RT;
    }

    /**
     * @param $response
     *
     * @return string
     */
    public function paywebQueryStatus($response): string
    {
        return !empty($response[self::TRANSACTION_STATUS]) ? $response[self::TRANSACTION_STATUS] : 'Null';
    }

    /**
     * @param $t
     * @param true $newPaymentMethod
     *
     * @return true
     */
    public function isPaymentMethodNew($t, $newPaymentMethod): bool
    {
        if (isset($t) && $t === '1') {
            $newPaymentMethod = true;
        }

        return $newPaymentMethod;
    }

    /**
     * @return void
     */
    public function logIPNRequest(): void
    {
        if ($this->logging) {
            self::$wc_logger->add('paygatepayweb', 'Notify POST: ' . json_encode($_POST));
            self::$wc_logger->add('paygatepayweb', 'Notify GET: ' . json_encode($_GET));
        }
    }

    /**
     * @param array $paygate_data
     * @param $order
     *
     * @return void
     */
    public function verifySecuritySignature(array $paygate_data, $order): void
    {
        if (!$this->validateChecksumNotify($paygate_data)) {
            if ($this->logging) {
                self::$wc_logger->add(
                    'paygatepayweb',
                    'Failed to validate checksum with data ' . json_encode($paygate_data)
                );
            }
            if (!$order->has_status(self::FAILED)) {
                $order->add_order_note('Failed Response via Notify, ' . $this->error_desc . self::BR);
                $order->update_status(self::PENDING, __('Checksum failed in notify'));
            }
            exit();
        } else {
            if ($this->logging) {
                self::$wc_logger->add('paygatepayweb', 'Validated checksum with data ' . json_encode($paygate_data));
            }
        }
    }

    /**
     * @param $order
     *
     * @return void
     */
    public function alternativeCartMechanism($order): void
    {
        if (count($order->get_items()) > 0) {
            foreach ($order->get_items() as $product) {
                $product_id   = isset($product['product_id']) ? (int)$product['product_id'] : 0;
                $quantity     = isset($product['quantity']) ? (int)$product['quantity'] : 1;
                $variation_id = isset($product['variation_id']) ? (int)$product['variation_id'] : 0;
                $variation    = isset($product['variation']) ? (array)$product['variation'] : array();
                WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
            }
            WC()->cart->calculate_totals();
        }
    }

    /**
     * @param $order
     *
     * @return string
     */
    protected function getOrderReference($order): string
    {
        $order_id  = $order->get_id();
        $reference = $order_id . '-' . $order->get_order_number();

        if ($this->customPGReference) {
            $reference = $order->get_order_number();
        }

        // Set reference if custom order meta is set
        if ($this->order_meta_reference != '') {
            $reference_meta = $order->get_meta(sanitize_key($this->order_meta_reference), true);
            $reference      .= !empty($reference_meta) ? '-' . $reference_meta : '';
        }

        return $reference;
    }

    /**
     * @return bool
     */
    protected function setVaultableMethod(): bool
    {
        $wcsession        = WC()->session;
        $subpaymentmethod = $wcsession->get(self::SUB_PAYMENT_METHOD);
        $vaultableMethod  = false;
        if (isset($subpaymentmethod) && $subpaymentmethod != '') {
            $this->data_to_send['PAY_METHOD'] = substr($subpaymentmethod, 0, 2);
            if (isset($this->paymentTypes[$subpaymentmethod]) && $this->paymentTypes[$subpaymentmethod] != '') {
                $this->data_to_send['PAY_METHOD_DETAIL'] = $this->paymentTypes[$subpaymentmethod];
            } else {
                $this->data_to_send['PAY_METHOD_DETAIL'] = '';
            }
            if ($this->data_to_send['PAY_METHOD'] === self::CREDIT_CARD_METHOD) {
                $vaultableMethod = true;
            }
        } elseif ($this->payVault === 'yes') {
            $vaultableMethod = true;
        }

        return $vaultableMethod;
    }

    /**
     * @param $status
     * @param $order
     * @param $transaction_id
     * @param $result_desc
     * @param $pay_request_id
     */
    protected function processOrderFinal($status, $order, $transaction_id, $result_desc, $pay_request_id)
    {
        if ($this->settings[self::ALTERNATECARTHANDLING] == 'yes') {
            // Alternative cart mechanism
            $this->alternativeCartMechanism($order);
        }
        switch ($status) {
            case 1:
                $this->processOrderFinalSuccess($order, $transaction_id, $pay_request_id);
                exit();
                break;
            case 2:
                $this->add_notice('The transaction was declined', self::ERROR, $order->get_id());
                $this->processOrderFinalFail($order, $transaction_id, $pay_request_id, $result_desc);
                exit;
                break;
            case 4:
                $this->add_notice('The transaction was cancelled', self::ERROR, $order->get_id());
                $this->processOrderFinalCancel($order, $transaction_id, $pay_request_id);
                exit;
                break;
            default:
                if ($this->settings[self::DISABLENOTIFY] == 'yes') {
                    if (!$order->has_status(self::PENDING)) {
                        $order->add_order_note(
                            'Response via ' . $this->settings[self::PAYMENT_TYPE] . ', RESULT_DESC: ' . $result_desc . self::PAYGATE_TRANS_ID . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                        );
                        if (!$order->is_paid()) {
                            $order->update_status(self::PENDING);
                        }
                    }

                    $this->add_notice(
                        'Your purchase is either pending or an error has occurred. Please follow up with the whomever necessary.',
                        self::ERROR
                    );
                }
                $redirect_link = $order->get_cancel_order_url();
                $this->redirectAfterOrder($redirect_link);
                exit;
                break;
        }
    }

    /**
     * @param $redirect_link
     */
    protected function redirectAfterOrder($redirect_link): void
    {
        $redirect_link = str_replace('&amp;', '&', $redirect_link);
        wp_redirect($redirect_link);
    }

    /**
     * @param $order
     * @param $transaction_id
     * @param $pay_request_id
     */
    protected function processOrderFinalSuccess($order, $transaction_id, $pay_request_id, $notify = 'redirect'): void
    {
        WC()->cart->empty_cart();

        switch ($notify) {
            case 'redirect':
                if ($this->settings[self::DISABLENOTIFY] === 'yes') {
                    $order->add_order_note(
                        'Response via Redirect: Transaction successful<br/>Paygate Trans Id: ' . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                    );
                    if (!$order->has_status(self::PROCESSING) && !$order->has_status(self::COMPLETED)) {
                        $order->payment_complete();
                    }
                }
                break;
            case 'ipn':
                if ($this->settings[self::DISABLENOTIFY] !== 'yes') {
                    $order->add_order_note(
                        'Response via Notify: Transaction successful<br/>Paygate Trans Id: ' . $transaction_id . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                    );
                    if (!$order->has_status(self::PROCESSING) && !$order->has_status(self::COMPLETED)) {
                        $order->payment_complete();
                    }
                }
                break;
            default:
                break;
        }

        $redirect_link = $this->get_return_url($order);
        $this->custom_print_notices();
        $this->redirectAfterOrder($redirect_link);
    }

    /**
     * @param $order
     * @param $transaction_id
     * @param $pay_request_id
     */
    protected function processOrderFinalCancel($order, $transaction_id, $pay_request_id): void
    {
        if ($this->settings[self::DISABLENOTIFY] == 'yes') {
            if (!$order->has_status(self::FAILED)) {
                $order->add_order_note(
                    'Response via Redirect: User cancelled transaction<br/>' . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                );
                $order->update_status(self::FAILED);
            }
        } else {
            if (!$order->has_status(self::FAILED)) {
                $order->add_order_note(
                    'Response via Notify, User cancelled transaction<br/>' . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                );
                $order->update_status(self::FAILED);
            }
        }
        $redirect_link = $order->get_cancel_order_url();
        $this->custom_print_notices();
        $this->redirectAfterOrder($redirect_link);
    }

    /**
     * @param $order
     * @param $transaction_id
     * @param $pay_request_id
     * @param $result_desc
     */
    protected function processOrderFinalFail($order, $transaction_id, $pay_request_id, $result_desc): void
    {
        if ($this->settings[self::DISABLENOTIFY] == 'yes') {
            if (!$order->has_status(self::FAILED)) {
                $order->add_order_note(
                    'Response via Redirect, RESULT_DESC: ' . $result_desc . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                );
                $order->update_status(self::FAILED);
            }
        } else {
            if (!$order->has_status(self::FAILED)) {
                $order->add_order_note(
                    'Response via Notify, RESULT_DESC: ' . $result_desc . self::PAY_REQUEST_ID_TEXT . $pay_request_id . self::BR
                );
                $order->update_status(self::FAILED);
            }
        }
        $redirect_link = $order->get_cancel_order_url();
        $this->custom_print_notices();
        $this->redirectAfterOrder($redirect_link);
    }

    /**
     * @param $parsed_response
     * @param $customer_id
     */
    protected function vaultCard($parsed_response, $customer_id): void
    {
        if ($this->payVault == 'yes') {
            $this->vaultCard = get_post_meta(
                $customer_id,
                'wc-' . $this->id . self::NEW_PAYMENT_METHOD,
                true
            );

            if ($this->vaultCard && array_key_exists(self::VAULT_ID, $parsed_response)) {
                // Save Token details
                $this->saveToken($parsed_response, $customer_id);
            }
        }
    }

    /**
     * @param $post
     * @param $reference
     *
     * @return bool
     */
    protected function validateChecksum($post, $reference): bool
    {
        $pay_request_id = $post[self::PAY_REQUEST_ID];
        $status         = $post[self::TRANSACTION_STATUS] ?? "";
        $checksum       = $post[self::CHECKSUM] ?? "";

        $checksum_source = $this->merchant_id . $pay_request_id . $status . $reference . $this->encryption_key;
        $test_checksum   = md5($checksum_source);

        return hash_equals($checksum, $test_checksum);
    }

    protected function validateChecksumNotify($paygate_data)
    {
        $checkSumParams = '';
        foreach ($paygate_data as $key => $val) {
            if ($key == self::PAYGATE_ID) {
                $checkSumParams .= $val;
                continue;
            }
            if ($key === 'AUTH_CODE') {
                if ($val === 'null') {
                    $checkSumParams .= '';
                } else {
                    $checkSumParams .= $val;
                }
                continue;
            }
            if ($key != self::CHECKSUM && $key != self::PAYGATE_ID && $key !== 'AUTH_CODE') {
                $checkSumParams .= $val;
            }
        }
        $checkSumParams .= $this->encryption_key;

        $valid            = hash_equals($paygate_data['CHECKSUM'], md5($checkSumParams));
        $this->error_desc .= $valid ? '' : $paygate_data['self::PAY_REQUEST_ID'];

        return $valid;
    }

    /**
     * @param $fields
     *
     * @return array
     */
    protected function sanitizeFields($fields)
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $result[$key] = filter_var($field, FILTER_SANITIZE_STRING);
        }

        return $result;
    }

    /**
     * @param $data
     * @param $customer_id
     */
    protected function saveToken($data, $customer_id)
    {
        $this->vaultId = $data[self::VAULT_ID];
        $card          = isset($data[self::PAYVAULT_DATA_1]) ? $data[self::PAYVAULT_DATA_1] : "";
        $expiry        = isset($data[self::PAYVAULT_DATA_2]) ? $data[self::PAYVAULT_DATA_2] : "";
        $cardType      = isset($data[self::PAY_METHOD_DETAIL]) ? $data[self::PAY_METHOD_DETAIL] : "";

        // Get existing tokens for user
        $tokenDs = new WC_Payment_Token_Data_Store();
        $tokens  = $tokenDs->get_tokens(
            [
                'user_id' => $customer_id,
            ]
        );

        $exists = false;

        foreach ($tokens as $token) {
            if ($token->token == $this->vaultId) {
                $exists = true;
            }
        }

        if (!$exists) {
            $token = new WC_Payment_Token_CC();

            $token->set_token($this->vaultId);
            $token->set_gateway_id($this->id);
            $token->set_card_type(strtolower($cardType));
            $token->set_last4(substr($card, -4));
            $token->set_expiry_month(substr($expiry, 0, 2));
            $token->set_expiry_year(substr($expiry, -4));
            $token->set_user_id($customer_id);
            $token->set_default(true);

            $token->save();
        }
    }
}
