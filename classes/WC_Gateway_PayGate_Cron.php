<?php
/*
 * Copyright (c) 2021 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class WC_Gateway_PayGate_Cron extends WC_Gateway_PayGate
{
    /**
     *
     */
    public static function paygate_order_query_cron()
    {
        $gwp = new WC_Gateway_PayGate_Portal();

        $orders = self::paygate_order_query_cron_query();
        foreach ($orders as $order_id) {
            $order = new WC_Order($order_id->ID);
            $notes = $gwp->getOrderNotes($order_id->ID);

            $payRequestId = self::getPayRequestIdNotes($notes);

            if ($payRequestId == '') {
                return;
            }

            $response          = $gwp->paywebQuery($payRequestId, $order);
            $transactionStatus = $gwp->paywebQueryStatus($response);
            $responseText      = $gwp->paywebQueryText($response, $payRequestId);

            if ((int)$transactionStatus === 1) {
                if ( ! $order->has_status(self::PROCESSING) && ! $order->has_status(self::COMPLETED)) {
                    $order->payment_complete();
                    $responseText .= "<br>Order set to \"Processing\"";
                }
            } else {
                if ( ! $order->has_status(self::FAILED)) {
                    $order->update_status(self::FAILED);
                }
            }
            $order->add_order_note('Queried by cron at ' . date('Y-m-d H:i') . '<br>Response: <br>' . $responseText);
        }
    }

    protected static function paygate_order_query_cron_query()
    {
        global $wpdb;

        $query = <<<QUERY
SELECT ID FROM `{$wpdb->prefix}posts`
INNER JOIN `{$wpdb->prefix}postmeta` ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id
WHERE meta_key = '_payment_method'
AND meta_value = 'paygate'
AND post_status = 'wc-pending'
QUERY;

        return $wpdb->get_results($query);
    }
}
