<?php
require_once __DIR__."/vendor/autoload.php";
$paypal=new \techoxio\paypal\PayPal();
$paypal->printPayPalForm();