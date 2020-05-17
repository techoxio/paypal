<?php
require_once "vendor/autoload.php";
use techoxio\paypal\PayPal;
$paypal=new PayPal(['businessAccount'=>'info@teamoxio.com']);
$paypal->printPayPalForm();