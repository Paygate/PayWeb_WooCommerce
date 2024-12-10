# payfast-common

## Payfast common class for modules

This is the Payfast common class for modules.

## Installation

You can install this module using composer:

```console
composer require payfast/payfast-common
```

## Aggregator

### Module parameters for pfValidData()

Declare the relevant $moduleInfo values when using the **pfValidData()** method, for example:

```
$moduleInfo = [
    "pfSoftwareName"       => 'OpenCart',
    "pfSoftwareVer"        => '4.0.2.0',
    "pfSoftwareModuleName" => 'PF_OpenCart',
    "pfModuleVer"          => '2.3.1',
];

$pfValid = $payfastCommon->pfValidData($moduleInfo, $pfHost, $pfParamString);
```

### Debug Mode

Configure debug mode by passing true|false when instantiating the
**Payfast\PayfastCommon\Aggregator\Request\PaymentRequest** class.

```
$aggregatorPaymentRequest = new Payfast\PayfastCommon\Aggregator\Request\PaymentRequest(true);
```

### Breaking Changes since v1.2.0

#### Namespace Changes: **`Payfast` → `Aggregator`**

The namespaces for several core classes have been updated to improve consistency and better align with the library's
purpose. This requires updating your imports to reflect the new structure.

#### Class Renames

To enhance clarity and maintain consistency, some classes have been renamed. Make sure to update your code to reference
the new class names.

#### Example of Correct and Incorrect Usage:

#### Correct

```php
use Payfast\PayfastCommon\Aggregator\Request\PaymentRequest;

$paymentRequest = new PaymentRequest($testMode);
```

#### Incorrect

```php
use Payfast\PayfastCommon\PayfastCommon;

$payfastCommon = new PayfastCommon($testMode);
```

### Breaking Changes since v1.1.0

We have migrated from static to instance methods for the Aggregator PaymentRequest class.

For example, prior to v1.1.0 we used:

```
// Debug mode
define('PF_DEBUG', true);

// Module parameters for pfValidData
define('PF_SOFTWARE_NAME', 'GravityForms');
define('PF_SOFTWARE_VER', '2.8.7');
define('PF_MODULE_NAME', 'PayFast-GravityForms');
define('PF_MODULE_VER', '1.5.4');

// Calling methods on Payfast\PayfastCommon\Aggregator\Request\PaymentRequest
$pfData = PaymentRequest::pfGetData();
PaymentRequest::pflog('Verify data received');
```

But this has now become:

```
// Debug mode
$aggregatorPaymentRequest = new PaymentRequest(true);

// Module parameters for pfValidData
$moduleInfo = [
    "pfSoftwareName"       => 'GravityForms',
    "pfSoftwareVer"        => '2.8.7',
    "pfSoftwareModuleName" => 'PayFast-GravityForms',
    "pfModuleVer"          => '1.5.4',
];
$pfValid = $aggregatorPaymentRequest->pfValidData($moduleInfo, $pfHost, $pfParamString);

// Calling methods on Payfast\PayfastCommon\Aggregator\Request\PaymentRequest
$pfData = $aggregatorPaymentRequest->pfGetData();
$aggregatorPaymentRequest->pflog('Verify data received');
```

## Gateway

### Usage examples

#### Payment Initiate

```php
try {
    $paymentRequest   = new PaymentRequest($merchantId, $encryptionKey);
    $response = $paymentRequest->initiate($data);
} catch (Exceptione $e) {
    echo 'Error initiating payment: ' . $e->getMessage();
}
```

#### Redirect to Gateway

```php
echo $paymentRequest->getRedirectHTML($payRequestId, $checksum);
```

#### Query Transaction

```php
try {
    $paymentRequest   = new PaymentRequest($merchantId, $encryptionKey);
    $response = $paymentRequest->query($payRequestId, $reference);
} catch (Exceptione $e) {
    echo 'Error querying transaction: ' . $e->getMessage();
}
```
