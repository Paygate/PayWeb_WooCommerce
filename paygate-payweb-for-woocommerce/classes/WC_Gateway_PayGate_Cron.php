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
    const LOGGING = 'logging';

    /**
     *
     */
    public static function paygate_order_query_cron()
    {
        $gwp = new WC_Gateway_PayGate_Portal();

        // Load the settings
        $settings = get_option('woocommerce_paygate_settings', false);

        $logging = false;

        if (isset($settings[self::LOGGING]) && $settings[self::LOGGING] === 'yes') {
            $logging = true;
        }

        $logger = wc_get_logger();
        $logging ? $logger->add('payweb_cron', 'Starting cron job') : '';

        $orders = self::paygate_order_query_cron_query();
        $logging ? $logger->add('payweb_cron', 'Orders: ' . serialize($orders)) : '';

        foreach ($orders as $order_id) {
            $order   = null;
            $orderId = $order_id->ID;
            $logging ? $logger->add('payweb_cron', 'Order ID: ' . $orderId) : '';
            try {
                $order = new WC_Order($orderId);
                $logging ? $logger->add('payweb_cron', 'Order: ' . serialize($order)) : '';
            } catch (Exception $e) {
                $logging ? $logger->add('payweb_cron', 'Fatal error: ' . $e->getMessage()) : '';
            }

            $notes = self::getOrderNotes($orderId);

            $payRequestId = WC_Gateway_PayGate_Portal::getPayRequestIdNotes($notes);
            $logging ? $logger->add('payweb_cron', 'PayRequestId: ' . $payRequestId) : '';

            if ($payRequestId == '') {
                break;
            }

            $response          = $gwp->paywebQuery($payRequestId, $order);
            $transactionStatus = $gwp->paywebQueryStatus($response);
            $responseText      = $gwp->paywebQueryText($response, $payRequestId);

            $logging ? $logger->add('payweb_cron', 'Response Text: ' . $responseText) : '';

            if ((int)$transactionStatus === 1) {
                if ( ! $order->has_status(self::PROCESSING) && ! $order->has_status(self::COMPLETED)) {
                    $order->payment_complete();
                    $responseText .= "<br>Order set to \"Processing\" by PayWeb Cron";
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

        // Orders from the last 60 minutes
        $ordersModifiedInLastHour = " " . date('Y-m-d H:i:s', strtotime('-60 minutes')) . "";

        $query = <<<QUERY
SELECT ID FROM `{$wpdb->prefix}posts`
INNER JOIN `{$wpdb->prefix}postmeta` ON {$wpdb->prefix}posts.ID = {$wpdb->prefix}postmeta.post_id
WHERE meta_key = '_payment_method'
AND meta_value = 'paygate'
AND post_status = 'wc-pending'
AND post_date_gmt < '$ordersModifiedInLastHour'
QUERY;

        return $wpdb->get_results($query);
    }
}
