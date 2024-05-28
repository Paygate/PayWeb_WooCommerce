<?php
/*
 * Copyright (c) 2024 Payfast (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class WC_Gateway_PayGate_Cron extends WC_Gateway_PayGate
{
    const LOGGING        = 'logging';
    const CUTOFF_MINUTES = 30;

    public static function paygate_order_query_cron()
    {
        $logger = wc_get_logger();

        if (is_multisite()) {
            $logger->add('paygate-cron', 'Starting multisite cron jobs');
            $url = add_query_arg('wc-api', 'WC_Gateway_PayGate_Cron', home_url('/'));
            preg_match_all('/(.*)(\/\?wc.*)/', $url, $parts);
            $sites = get_sites();
            foreach ($sites as $site) {
                $url = $parts[1][0] . $site->path . '?wc-api=WC_Gateway_PayGate_Cron_Site';
                wp_remote_post($url, ['sslverify' => false]);
            }

            return;
        }

        self::paygate_order_query_cron_site();
    }

    /**
     * Multisite queries are redirected here by remote post
     */
    public static function paygate_order_query_cron_site()
    {
        $gwp    = new WC_Gateway_PayGate_Portal();
        $logger = wc_get_logger();
        $logger->add('payweb-site-cron', 'Redirected to here');

        $cutoffTime    = new DateTime('now', new DateTimeZone('UTC'));
        $cutoffMinutes = self::CUTOFF_MINUTES;
        $cutoff        = $cutoffTime->sub(new DateInterval("P0DT0H{$cutoffMinutes}M"))->getTimestamp();

        // Load the settings
        $settings = get_option('woocommerce_paygate_settings', false);

        $logging = false;

        if (isset($settings[self::LOGGING]) && $settings[self::LOGGING] === 'yes') {
            $logging = true;
        }

        $logging ? $logger->add('payweb-site-cron', 'Starting site cron job') : '';

        $orders = wc_get_orders([
                                    'post_status'  => 'wc-pending',
                                    'date_created' => '<' . $cutoff,
                                ]);
        $logging ? $logger->add('payweb-site-cron', 'Orders: ' . serialize($orders)) : '';

        foreach ($orders as $order) {
            $orderId = $order->get_id();
            try {
                $logging ? $logger->add('payweb-site-cron', 'Order: ' . serialize($order)) : '';
            } catch (Exception $e) {
                $logging ? $logger->add('payweb-site-cron', 'Fatal error: ' . $e->getMessage()) : '';
            }

            $notes = self::getOrderNotes($orderId);

            $payRequestId = WC_Gateway_PayGate_Portal::getPayRequestIdNotes($notes);
            $logging ? $logger->add('payweb-site-cron', 'PayRequestId: ' . $payRequestId) : '';

            if ($payRequestId == '') {
                break;
            }

            $response          = $gwp->paywebQuery($payRequestId, $order);
            $transactionStatus = $gwp->paywebQueryStatus($response);
            $responseText      = $gwp->paywebQueryText($response, $payRequestId);

            $logging ? $logger->add('payweb-site-cron', 'Response Text: ' . $responseText) : '';

            if ((int)$transactionStatus === 1) {
                if (!$order->has_status(self::PROCESSING) && !$order->has_status(self::COMPLETED)) {
                    $order->update_status(self::PROCESSING);
                    $responseText .= "<br>Order set to \"Processing\" by PayWeb Cron";
                }
            } else {
                if (!$order->has_status(self::FAILED)) {
                    $order->update_status(self::FAILED);
                }
            }
            $order->add_order_note('Queried by cron at ' . date('Y-m-d H:i') . '<br>Response: <br>' . $responseText);
        }
    }
}
