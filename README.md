# PayPal
PHP package to integrate PayPal Form and IPN Settings.

#Steps for sending user to PayPal

```
<?php
require_once __DIR__."/vendor/autoload.php";
use techoxio\paypal\PayPal;

$paypal=new PayPal([
    'businessAccount'=>'', //required - PayPal account where you receive payment 
    'amount'=>10, //required - Order Amount
    'notifyUrl'=>'', //Merchant website URL, where you receive paypal ipn 
    'cancelUrl' => '', //Merchant website URL, where user will return in case of rejecting order payment
    'returnUrl' => '', //Merchant website URL, where user will return after payment
    'autoSubmitForm' => true, //Auto submit PayPal form. Default value is false.
]);

//$paypal->activateSandboxMode(); //For activating sandbox mode

//This will automatically redirect user to PayPal.   
$paypal->printPayPalForm();

```
#Steps for receiving PayPal IPN

```
<?php
require_once __DIR__."/vendor/autoload.php";
use techoxio\paypal\PayPal;

$paypal=new PayPal([
    'businessAccount'=>'', //required - PayPal account where you receive payment 
]);

//$paypal->activateSandboxMode(); //For activating sandbox mode

//Verify PayPal payment.   
$payment=$paypal->verifyPayment();
if($payment)
{
    echo $payment->businessAccount."<br />";
    echo $payment->amount."<br />";
    echo $payment->currency."<br />";
    echo $payment->paymentStatus."<br />";
    echo $payment->txnId."<br />";
}
else
{
    //For getting error messages you can just simply do $payment->getErrors()
    $errors = $payment->getErrors();
    //$errors return multidimensional array
}
```