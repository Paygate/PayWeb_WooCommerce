<?php

namespace Payfast\PayfastCommon\Aggregator\Request;

class PaymentRequest
{
    // General Defines
    public const PF_TIMEOUT = 15;
    public const PF_EPSILON = 0.01;

    // Messages
    // Error
    public const PF_ERR_AMOUNT_MISMATCH      = 'Amount mismatch';
    public const PF_ERR_BAD_ACCESS           = 'Bad access of page';
    public const PF_ERR_BAD_SOURCE_IP        = 'Bad source IP address';
    public const PF_ERR_CONNECT_FAILED       = 'Failed to connect to Payfast';
    public const PF_ERR_INVALID_SIGNATURE    = 'Security signature mismatch';
    public const PF_ERR_MERCHANT_ID_MISMATCH = 'Merchant ID mismatch';
    public const PF_ERR_NO_SESSION           = 'No saved session found for ITN transaction';
    public const PF_ERR_ORDER_ID_MISSING_URL = 'Order ID not present in URL';
    public const PF_ERR_ORDER_ID_MISMATCH    = 'Order ID mismatch';
    public const PF_ERR_ORDER_INVALID        = 'This order ID is invalid';
    public const PF_ERR_ORDER_PROCESSED      = 'This order has already been processed';
    public const PF_ERR_PDT_FAIL             = 'PDT query failed';
    public const PF_ERR_PDT_TOKEN_MISSING    = 'PDT token not present in URL';
    public const PF_ERR_SESSIONID_MISMATCH   = 'Session ID mismatch';
    public const PF_ERR_UNKNOWN              = 'Unknown error occurred';

    // General
    public const PF_MSG_OK      = 'Payment was successful';
    public const PF_MSG_FAILED  = 'Payment has failed';
    public const PF_MSG_PENDING = 'The payment is pending. Please note, you will receive another Instant' .
    ' Transaction Notification when the payment status changes to' .
    ' "Completed", or "Failed"';
    private bool $debugMode;

    /**
     * @param bool $debugMode
     */
    public function __construct(bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
    }

    /**
     * pfValidData
     *
     * @param $moduleInfo array pfSoftwareName, pfSoftwareVer, pfSoftwareModuleName, pfModuleVer
     * @param $pfHost String Hostname to use
     * @param $pfParamString String
     *
     * @return bool
     */
    public function pfValidData(
        array $moduleInfo,
        string $pfHost = 'www.payfast.co.za',
        string $pfParamString = ''
    ): bool{
        $pfFeatures = 'PHP ' . phpversion() . ';';
        $pfCurl     = false;

        // - cURL
        if (in_array('curl', get_loaded_extensions())) {
            $pfCurl     = true;
            $pfVersion  = curl_version();
            $pfFeatures .= ' curl ' . $pfVersion['version'] . ';';
        } else {
            $pfFeatures .= ' nocurl;';
        }

        $pfUserAgent = $moduleInfo["pfSoftwareName"] . '/' . $moduleInfo['pfSoftwareVer'] .
            ' (' . trim(
                $pfFeatures
            ) . ') ' . $moduleInfo["pfSoftwareModuleName"] . '/' . $moduleInfo["pfModuleVer"];

        $this->pflog('Host = ' . $pfHost);
        $this->pflog('Params = ' . $pfParamString);

        // Use cURL (if available)
        if ($pfCurl) {
            // Variable initialization
            $url = 'https://' . $pfHost . '/eng/query/validate';

            // Create default cURL object
            $ch = curl_init();

            // Set cURL options - Use curl_setopt for greater PHP compatibility
            // Base settings
            curl_setopt($ch, CURLOPT_USERAGENT, $pfUserAgent);  // Set user agent
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);      // Return output as string rather than outputting it
            curl_setopt($ch, CURLOPT_HEADER, false);             // Don't include header in output
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            // Standard settings
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $pfParamString);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::PF_TIMEOUT);

            // Execute CURL
            $response = curl_exec($ch);
            curl_close($ch);
        } else { // Use fsockopen
            // Variable initialization
            $header     = '';
            $response   = '';
            $headerDone = false;

            // Construct Header
            $header = "POST /eng/query/validate HTTP/1.0\n";
            $header .= "Host: " . $pfHost . "\n";
            $header .= "User-Agent: " . $pfUserAgent . "\n";
            $header .= "Content-Type: application/x-www-form-urlencoded\n";
            $header .= "Content-Length: " . strlen($pfParamString) . "\n\n";

            // Connect to server
            $socket = fsockopen('ssl://' . $pfHost, 443, $errno, $errstr, self::PF_TIMEOUT);

            // Send command to server
            fputs($socket, $header . $pfParamString);

            // Read the response from the server
            while (!feof($socket)) {
                $line = fgets($socket, 1024);

                // Check if we are finished reading the header yet
                if (strcmp($line, "\n") == 0) {
                    // read the header
                    $headerDone = true;
                } elseif ($headerDone) { // If header has been processed
                    // Read the main response
                    $response .= $line;
                }
            }
        }

        $this->pflog("Response:\n" . print_r($response, true));

        // Interpret Response
        $lines        = explode("\n", $response);
        $verifyResult = trim($lines[0]);

        if (strcasecmp($verifyResult, 'VALID') == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Log public static function for logging output.
     *
     * @param $msg String Message to log
     * @param $close Boolean Whether to close the log file or not
     */
    public function pflog(string $msg = '', bool $close = false): void
    {
        static $fh = 0;

        // Only log if debugging is enabled
        if ($this->debugMode) {
            if ($close) {
                fclose($fh);
            } else {
                // If file doesn't exist, create it
                if (!$fh) {
                    $pathInfo = pathinfo(__FILE__);
                    $fh       = fopen($pathInfo['dirname'] . '/payfast.log', 'a+');
                }

                // If file was successfully created
                if ($fh) {
                    $line = date('Y-m-d H:i:s') . ' : ' . $msg . "\n";

                    try{
                        fwrite($fh, $line);
                    } catch (\Exception $e){
                        error_log($e);
                    }
                }
            }
        }
    }

    /**
     * pfGetData
     *
     */
    public static function pfGetData(): bool|array
    {
        // Posted variables from ITN
        $pfData = $_POST;

        // Strip any slashes in data
        foreach ($pfData as $key => $val) {
            $pfData[$key] = stripslashes($val);
        }


        // Return "false" if no data was received
        if (empty($pfData)) {
            return false;
        } else {
            return $pfData;
        }
    }

    /**
     * pfValidSignature
     *
     */
    public function pfValidSignature($pfData = null, &$pfParamString = null, $pfPassphrase = null): bool
    {
        // Dump the submitted variables and calculate security signature
        foreach ($pfData as $key => $val) {
            if ($key != 'signature' && $key != 'option' && $key != 'Itemid') {
                $pfParamString .= $key . '=' . urlencode($val) . '&';
            }
        }

        $pfParamString = substr($pfParamString, 0, -1);

        if (!empty($pfPassphrase)) {
            $pfParamStringWithPassphrase = $pfParamString . "&passphrase=" . urlencode($pfPassphrase);
            $signature                   = md5($pfParamStringWithPassphrase);
        } else {
            $signature = md5($pfParamString);
        }

        $result = ($pfData['signature'] == $signature);

        $this->pflog('Signature = ' . ($result ? 'valid' : 'invalid'));

        return $result;
    }


    /**
     * pfValidIP
     *
     * @param $sourceIP String Source IP address
     */
    public function pfValidIP(string $sourceIP): bool
    {
        // Variable initialization
        $validHosts = array(
            'www.payfast.co.za',
            'sandbox.payfast.co.za',
            'w1w.payfast.co.za',
            'w2w.payfast.co.za',
        );

        $validIps = array();

        foreach ($validHosts as $pfHostname) {
            $ips = gethostbynamel($pfHostname);

            if ($ips !== false) {
                $validIps = array_merge($validIps, $ips);
            }
        }

        // Remove duplicates
        $validIps = array_unique($validIps);

        $this->pflog("Valid IPs:\n" . print_r($validIps, true));

        if (in_array($sourceIP, $validIps)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * pfAmountsEqual
     *
     * Checks to see whether the given amounts are equal using a proper floating
     * point comparison with an Epsilon which ensures that insignificant decimal
     * places are ignored in the comparison.
     *
     * e.g. 100.00 is equal to 100.0001
     *
     * @param $amount1 Float 1st amount for comparison
     * @param $amount2 Float 2nd amount for comparison
     */
    public function pfAmountsEqual(float $amount1, float $amount2): bool
    {
        if (abs(floatval($amount1) - floatval($amount2)) > self::PF_EPSILON) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Generate signature for API
     *
     * @param array $pfData (all the header, body and query string param values to be sent to the API)
     * @param null $passPhrase
     *
     * @return string
     */
    public static function generateApiSignature(array $pfData, $passPhrase = null): string
    {
        if ($passPhrase !== null) {
            $pfData['passphrase'] = $passPhrase;
        }

        // Sort the array by key, alphabetically
        ksort($pfData);

        //create parameter string
        $pfParamString = http_build_query($pfData);

        return md5($pfParamString);
    }

    /**
     * The Subscription Payments API gives Merchants the ability to interact with subscriptions on their accounts.
     *
     * @param $merchantID
     * @param $token
     * @param $action
     * @param array $data
     * @param $passphrase
     * @param bool $testMode
     * @param bool $returnCurlRequest
     *
     * @return string
     */
    public function subscriptionAction(
        $merchantID,
        $token,
        $action,
        array $data = [],
        $passphrase = null,
        bool $testMode = false,
        bool $returnCurlRequest = false
    ): string{
        $url = "https://api.payfast.co.za/subscriptions/$token/$action";

        if ($testMode) {
            $url .= "?testing=true";
        }

        $method = match ($action) {
            "fetch" => "GET",
            "pause", "unpause", "cancel" => "PUT",
            "update" => "PATCH",
            "adhoc" => "POST",
            default => null,
        };

        return $this->placeRequest($url, $merchantID, $passphrase, $data, $method, $returnCurlRequest);
    }

    /**
     * The Refunds API provides Merchants with the ability to process refunds to their buyers.
     *
     * @param $merchantID
     * @param $passphrase
     * @param $paymentID
     * @param $action
     * @param array $data
     * @param bool $testMode
     * @param bool $returnCurlRequest
     *
     * @return string
     */
    public function refundAction(
        $merchantID,
        $passphrase,
        $paymentID,
        $action,
        array $data = [],
        bool $testMode = false,
        bool $returnCurlRequest = false
    ): string{
        $url    = "https://api.payfast.co.za/refunds/";
        $method = "GET";

        if ($action === "query") {
            $url .= "query/";
        } elseif ($action === "create") {
            $method = "POST";
        }

        $url .= $testMode ? "$paymentID?testing=true" : "$paymentID";

        return $this->placeRequest($url, $merchantID, $passphrase, $data, $method, $returnCurlRequest);
    }

    /**
     * Test API
     *
     * @param $merchantID
     * @param null $passphrase
     *
     * @return string
     */
    public function pingPayfast($merchantID, $passphrase = null): string
    {
        $url = "https://api.payfast.co.za/ping?testing=true";

        return $this->placeRequest($url, $merchantID, $passphrase);
    }

    /**
     * Reusable Curl Request
     *
     * @param $url
     * @param $merchantID
     * @param null $passphrase
     * @param array $body
     * @param null $method
     * @param bool $returnCurlRequest
     *
     * @return string
     */
    public function placeRequest(
        $url,
        $merchantID,
        $passphrase = null,
        array $body = [],
        $method = null,
        bool $returnCurlRequest = false
    ): string{
        $date      = date("Y-m-d");
        $time      = date("H:i:s");
        $timeStamp = $body['timestamp'] ?? $date . "T" . $time;
        $version   = $body['version'] ?? "v1";
        $pfData    = [
            "merchant-id" => $merchantID,
            "timestamp"   => $timeStamp,
            "version"     => $version,
        ];

        if (array_key_exists('action', $body)) {
            $body = [];
        }

        $pfData = array_merge($pfData, $body);

        $signature = self::generateApiSignature($pfData, $passphrase);

        $headers = [
            "merchant-id: $merchantID",
            "version: $version",
            "timestamp: $timeStamp",
            "signature: $signature",
        ];

        $ch         = curl_init();
        $curlConfig = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
        ];

        if (!empty($body)) {
            $curlConfig[CURLOPT_POST]       = 1;
            $curlConfig[CURLOPT_POSTFIELDS] = http_build_query($body);
        }

        if ($method === "PUT" || $method === "PATCH") {
            $curlConfig[CURLOPT_CUSTOMREQUEST] = $method;
            if (empty($body)) {
                $headers[]                      = "Content-Length: 0";
                $curlConfig[CURLOPT_HTTPHEADER] = $headers;
            }
        }

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return curl_error($ch);
        }

        if ($returnCurlRequest) {
            // Determine the HTTP method for the bash command and cURL request
            if ($method === "PUT" || $method === "PATCH") {
                $bashMethod = $method;
            } elseif (!empty($body)) {
                $bashMethod = "POST";
            } else {
                $bashMethod = "GET";
            }

            // Build the bash curl command
            $bashCommand = "curl -X " . escapeshellarg($bashMethod);
            foreach ($headers as $header) {
                $bashCommand .= " -H " . escapeshellarg($header);
            }
            if (!empty($body) && in_array($bashMethod, ["POST", "PUT", "PATCH"])) {
                $bashCommand .= " --data " . escapeshellarg(http_build_query($body));
            }
            $bashCommand .= " " . escapeshellarg($url);

            $response = json_encode([
                                        'request'  => $bashCommand,
                                        'response' => $response,
                                    ]);
        }

        return $response;
    }

    /**
     * Build a checkout form and receive payments securely from our payment platform.
     * This process can be used for both one-time and recurring payments.
     *
     * @param $payArray
     * @param null $passphrase
     * @param bool $testMode
     *
     * @return void
     */
    public static function createTransaction($payArray, $passphrase = null, bool $testMode = false, $returnForm = false)
    {
        $url = $testMode ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';

        $secureString = '';
        foreach ($payArray as $k => $v) {
            $secureString .= $k . '=' . urlencode(trim($v)) . '&';
        }

        if (!empty($passphrase)) {
            $secureString .= 'passphrase=' . urlencode($passphrase);
        } else {
            $secureString = substr($secureString, 0, -1);
        }

        $securityHash = md5($secureString);

        $payArray['signature'] = $securityHash;
        $inputs                = '';
        foreach ($payArray as $k => $v) {
            $inputs .= '<input type="hidden" name="' . $k . '" value="' . $v . '"/>';
        }

        $form = <<<HTML
    <html lang="en">
    <body onLoad="document.payfast_form.submit();">
        <form action="$url" method="post" name="payfast_form">
            $inputs
        </form>
    </body>
    </html>
HTML;

        if ($returnForm) {
            return $responseData = ['form' => $form, 'secureString' => $secureString, 'securityHash' => $securityHash];
        } else {
            echo $form;
        }
    }
}
