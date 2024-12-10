<?php

namespace Payfast\PayfastCommon\Gateway\Request;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

class PaymentRequest
{
    private string $encryptionKey;
    private string $paygateId;
    private GuzzleClient $client;

    // PayGate URLs
    private const INITIATE_URL = 'https://secure.paygate.co.za/payweb3/initiate.trans';
    private const REDIRECT_URL = 'https://secure.paygate.co.za/payweb3/process.trans';
    private const QUERY_URL    = 'https://secure.paygate.co.za/payweb3/query.trans';

    public function __construct(string $paygateId, string $encryptionKey)
    {
        $this->paygateId     = $paygateId;
        $this->encryptionKey = $encryptionKey;
        $this->client        = new GuzzleClient(['timeout' => 10]);
    }

    /**
     * Initiate a PayGate transaction.
     *
     * @param array transactionData
     *
     * @return string
     */
    public function initiate(array $transactionData): string
    {
        $data = array_merge([
                                'PAYGATE_ID' => $this->paygateId,
                            ], $transactionData);

        $data['CHECKSUM'] = md5(implode('', $data) . $this->encryptionKey);

        return $this->sendRequest(self::INITIATE_URL, $data);
    }

    /**
     * get Redirect HTML form to redirect to PayGate payment page.
     *
     * @param $payRequestId
     * @param $checksum
     */
    public function getRedirectHTML(string $payRequestId, string $checksum)
    {
        $url = self::REDIRECT_URL;

        return <<<HTML
        <form action="{$url}" method="POST" id="paygate_payment_form">
            <input type="hidden" name="PAY_REQUEST_ID" value="{$payRequestId}">
            <input type="hidden" name="CHECKSUM" value="{$checksum}">
        </form>
HTML;
    }

    /**
     * Query the status of a PayGate transaction.
     *
     * @param $payRequestId
     * @param $reference
     *
     * @return string
     */
    public function query(string $payRequestId, string $reference): string
    {
        $data = [
            'PAYGATE_ID'     => $this->paygateId,
            'PAY_REQUEST_ID' => $payRequestId,
            'REFERENCE'      => $reference,
        ];

        $data['CHECKSUM'] = md5(implode('', $data) . $this->encryptionKey);

        return $this->sendRequest(self::QUERY_URL, $data);
    }

    /**
     * Send a cURL request to PayGate.
     *
     * @param String $url
     * @param array $data
     */
    private function sendRequest(string $url, array $data)
    {
        try {
            $response = $this->client->post($url, [
                'form_params' => $data,
                'headers'     => [
                    'Referer' => $_SERVER['HTTP_HOST']
                ]
            ]);

            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            throw new Exception('Guzzle error during POST request: ' . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception('An unexpected error occurred: ' . $e->getMessage());
        }
    }
}
