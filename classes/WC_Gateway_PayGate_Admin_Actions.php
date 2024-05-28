<?php
/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Gateway_PayGate_Admin_Actions extends WC_Gateway_PayGate
{

    /**
     * Add a notice to the merchant_key and merchant_id fields when in test mode.
     *
     * @since 1.0.0
     */
    public static function add_testmode_admin_settings_notice($form_fields)
    {
        $form_fields[self::PAYGATE_ID_LOWER_CASE][self::DESCRIPTION] .= ' <br><br><strong>' . __(
                'Paygate ID currently in use.',
                self::ID
            ) . ' ( 10011072130 )</strong>';
        $form_fields[self::ENCRYPTION_KEY][self::DESCRIPTION]        .= ' <br><br><strong>' . __(
                'Paygate Encryption Key currently in use.',
                self::ID
            ) . ' ( secret )</strong>';

        return $form_fields;
    }

    /**
     * Custom order action - query order status
     * Add custom action to order actions select box
     *
     * @param $actions
     *
     * @return
     */
    public static function paygate_add_order_meta_box_action($actions)
    {
        global $theorder;
        if ($theorder->get_payment_method() == self::ID) {
            $actions['wc_custom_order_action_paygate'] = __('Query order status with Paygate', self::ID);
        }

        return $actions;
    }

    /**
     * Process custom query action
     *
     * @param $order
     */
    public static function paygate_order_query_action($order)
    {
        $gwp      = new WC_Gateway_PayGate_Portal();
        $order_id = $order->get_id();

        $notes = (new WC_Gateway_PayGate())->getOrderNotes($order_id);

        $payRequestId = 0;
        foreach ($notes as $note) {
            if (strpos($note->comment_content, 'Pay Request Id:') !== false) {
                preg_match('/.*Pay Request Id: ([\dA-Z\-]+)/', $note->comment_content, $a);
                if (isset($a[1])) {
                    $payRequestId = $a[1];
                }
            }
        }

        $response          = $gwp->paywebQuery($payRequestId, $order);
        $responseText      = $gwp->paywebQueryText($response, $payRequestId);
        $transactionStatus = $gwp->paywebQueryStatus($response);

        if ((int)$transactionStatus === 1) {
            if (!$order->has_status(self::PROCESSING) && !$order->has_status(self::COMPLETED)) {
                $order->payment_complete();
                $responseText .= "<br>Order set to \"Processing\"";
            }
        } else {
            if (!$order->has_status(self::FAILED)) {
                $order->update_status(self::FAILED);
            }
        }
        $order->add_order_note('Queried at ' . date('Y-m-d H:i') . '<br>Response: <br>' . $responseText);
    }
}
