<?php
namespace techoxio\paypal;

class PayPal
{
    private $curl;
    private $debug=false;
    private $errorMessages = [];

    const VERIFY_URI = 'https://ipnpb.paypal.com/cgi-bin/webscr';
    const PAYPAL_URI = 'https://www.paypal.com/cgi-bin/webscr';

    /** Sandbox Postback URL */
    const SANDBOX_VERIFY_URI = 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr';
    const SANDBOX_PAYPAL_URI = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    /** Response from PayPal indicating validation was successful */
    const VALID = 'VERIFIED';

    /** Response from PayPal indicating validation failed */
    const INVALID = 'INVALID';

    /** PayPal certificate */
    public $paypalCertificate = __DIR__ . "/cacert.pem";

    /** PayPal mode */
    private $isLive = true;

    /** PayPal business account */
    public $businessAccount = false;

    /** PayPal IPN notify URL */
    public $notifyUrl = false;

    /** PayPal cancel and return URL */
    public $cancelUrl = false;

    /** PayPal return URL */
    public $returnUrl = false;

    /** Currency code */
    public $currency = "USD";

    /** PayPal order amount */
    public $amount = false;

    /** PayPal custom data */
    public $customData = false;

    /** PayPal order item name */
    public $itemName = false;

    /** PayPal ipn payment status */
    public $paymentStatus = null;

    /** PayPal ipn payment transaction id */
    public $txnId = null;

    /** PayPal auto submit form */
    public $autoSubmitForm = false;

    public function __construct(Array $params=[])
    {
        $this->curl = curl_init();

        if(count($params)>0)
        {
            foreach ($params as $index=>$value)
            {
                if(property_exists($this,$index))
                {
                    $this->{$index}=$value;
                }
            }
        }

        if(!$this->businessAccount)
        {
            $this->showException('PayPal business account is missing.');
        }

        if(!filter_var($this->businessAccount,FILTER_VALIDATE_EMAIL))
        {
            $this->showException('PayPal business account is invalid.');
        }
    }

    /**
     * PayPal mode to live
     */
    public function activateLiveMode()
    {
        $this->isLive = true;
    }

    /**
     * PayPal mode to sandbox
     */
    public function activateSandboxMode()
    {
        $this->isLive = false;
    }

    /**
     * PayPal auto submit payment form
     */
    public function autoSubmitPaymentForm()
    {
        $this->autoSubmitForm = true;
    }

    /**
     * PayPal debug mode activate
     */
    public function activateDebugMode()
    {
        $this->debug = true;
    }

    /**
     * @param $message
     * @throws \Exception
     */
    private function showException($message)
    {
        throw new \Exception($message,500);
    }

    /**
     * @param $key
     * @param $message
     */
    private function addErrorMessage($key,$message)
    {
        $this->errorMessages[$key][] = $message;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errorMessages;
    }

    /**
     * @return string
     */
    public function getCallingScriptUri()
    {
        return $_SERVER['REQUEST_SCHEME']."://".$_SERVER['HTTP_HOST'];
    }

    /**
     * Determine endpoint to post the verification data to.
     *
     * @return string
     */
    public function getPayPalIpnUri()
    {
        if($this->isLive)
        {
            return self::VERIFY_URI;
        }
        else
        {
            return self::SANDBOX_VERIFY_URI;
        }
    }

    public function getPayPalFormUrl()
    {
        if($this->isLive)
        {
            return self::PAYPAL_URI;
        }
        else
        {
            return self::SANDBOX_PAYPAL_URI;
        }
    }

    public function printPayPalForm()
    {
        if(!$this->returnUrl)
        {
            $this->returnUrl = $this->getCallingScriptUri();
        }

        if(!$this->cancelUrl)
        {
            $this->cancelUrl = $this->getCallingScriptUri();
        }

        if(!$this->itemName)
        {
            $this->itemName = $_SERVER['SERVER_NAME'];
        }

        if(!$this->amount)
        {
            $this->showException('Invalid PayPal amount entered.');
        }
        ?>
        <form id="my_paypal_form" action="<?=$this->getPayPalFormUrl()?>" method="post">
            <input type="hidden" name="business" value="<?= $this->businessAccount; ?>" />
            <input type="hidden" name="rm" value="2" />
            <input type="hidden" name="lc" value="" />
            <input type="hidden" name="no_shipping" value="1" />
            <input type="hidden" name="no_note" value="1" />
            <input type="hidden" name="currency_code" value="<?=strtoupper($this->currency)?>" />
            <input type="hidden" name="page_style" value="paypal" />
            <input type="hidden" name="charset" value="utf-8" />
            <input type="hidden" name="item_name" value="<?= $this->itemName ?>" />
            <!--<input type="hidden" name="cbt" value="<?='cbt' ?>" />-->
            <input type="hidden" value="_xclick" name="cmd"/>
            <input type="hidden" name="amount" value="<?= $this->amount ?>" />
            <?php
            if($this->customData)
            {
                ?>
                <input type="hidden" name="custom" value="<?= $this->customData?>" />
                <?php
            }
            ?>
            <?php
            if($this->notifyUrl)
            {
                ?>
                <input type="hidden" name="notify_url" value="<?= $this->notifyUrl ?>" />
                <?php
            }
            ?>
            <input type="hidden" name="cancel_return" value="<?= $this->cancelUrl ?>" />
            <input type="hidden" name="return" value="<?= $this->returnUrl ?>" />
            <?php
            if(!$this->autoSubmitForm)
            {
                ?>
                <button type="submit">
                    <img src="PayPal_Logo.png" alt="PayPal">
                </button>
                <?php
            }
            ?>
        </form>
        <?php
        if($this->autoSubmitForm)
        {
            ?>
            <script type="text/javascript">
                function submitForm() {
                    document.getElementById('my_paypal_form').submit();
                }
                window.addEventListener('load',submitForm)
            </script>
            <?php
        }
        ?>
        <?php
    }

    private function sendRequest(Array $params=[])
    {
        curl_setopt_array($this->curl,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_MAXREDIRS=>10,
            CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_SSL_VERIFYHOST=>false,
            CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_TIMEOUT=>0,
        ]);

        if(is_array($params) && count($params)>0)
        {
            curl_setopt_array($this->curl,$params);
        }
        $response = curl_exec($this->curl);
        return $this->parseResponse($response);
    }

    /**
     * @param $response
     * @return array
     */
    private function parseResponse($response)
    {
        $result = array(
            'status' => 'error',
            'response' => '',
        );
        if ($this->debug)
        {
            $result['actual_response'] = $response;
            $result['requested_url'] = curl_getinfo($this->curl,CURLINFO_EFFECTIVE_URL);
        }
        $curl_error = curl_error($this->curl);
        if ($curl_error)
        {
            $result['curl_error'] = $curl_error;
        }
        else
        {
            if ($response == null || $response == false)
            {
                $result['response'] = false;
            }
            else
            {
                switch ($http_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE)) {
                    case 200:  //ok
                        {
                            $result['status']='success';
                            $result['response']=$response;
                        }
                        break;
                    default:
                        {
                            $result['response']='Unexpected HTTP code: '.$http_code;
                        }
                        break;
                }
            }
        }
        return $result;
    }

    /**
     * @return array|bool|string
     * @throws \Exception
     */
    private function verifyIpnResponse()
    {
        $payPalIpnResponseContent = "";
        if (count($_POST)<=0) {
            $this->showException("Missing POST Data");
        }

        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = array();
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
                if ($keyval[0] === 'payment_date') {
                    if (substr_count($keyval[1], '+') === 1) {
                        $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                    }
                }
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }

        $payPalIpnResponseContent = $myPost;

        // Build the body of the verification post request, adding the _notify-validate command.
        $req = 'cmd=_notify-validate';
        $get_magic_quotes_exists = false;
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($myPost as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $req .= "&$key=$value";
        }

        $response= $this->sendRequest([
            CURLOPT_URL=>$this->getPayPalIpnUri(),
            CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,
            CURLOPT_POST=>1,
            CURLOPT_POSTFIELDS=>$req,
            CURLOPT_SSLVERSION=>6,
            CURLOPT_SSL_VERIFYPEER=>1,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_CAINFO=>$this->paypalCertificate,
            CURLOPT_FORBID_REUSE=>1,
            CURLOPT_CONNECTTIMEOUT=>30,
            CURLOPT_HTTPHEADER=>array(
                'User-Agent: PHP-IPN-Verification-Script',
                'Connection: Close',
            ),
        ]);
        if(is_array($response) && $response['status']=="success") {
            if ($response['response'] == self::VALID)
            {
                return $payPalIpnResponseContent;
            }
            else
            {
                $this->addErrorMessage('Verify IPN','Invalid IPN received.');
            }
        }
        else
        {
            $this->addErrorMessage('Verify IPN',$response['response']);
        }
        return false;
    }

    /**
     * @return bool
     */
    public function verifyPayment()
    {
        $ipnResponse=$this->verifyIpnResponse();
        if($ipnResponse) {
            $parseIpn=$this->parseIpnResponse($ipnResponse);
            if(array_key_exists('paymentStatus',$parseIpn) && $parseIpn['paymentStatus']!='')
            {
                if($parseIpn['paymentStatus']=="Completed")
                {
                    if($this->isLive)
                    {
                        if($parseIpn['testingIpn'])
                        {
                            $this->addErrorMessage('Parsing IPN','PayPal testing ipn received on live mode.');
                            return false;
                        }
                    }
                    if(count($parseIpn)>0) {
                        foreach ($parseIpn as $index=>$value) {
                            if(property_exists($this,$index)) {
                                $this->{$index}=$value;
                            }
                        }
                    }
                    return true;
                }
                else
                {
                    $this->addErrorMessage('Parsing IPN','PayPal payment status is '.strtolower($parseIpn['paymentStatus']).'.');
                }
            }
            else
            {
                $this->addErrorMessage('Parsing IPN','PayPal ipn not parsed properly.');
            }
        }
        else
        {
            $this->addErrorMessage('Verify Payment','PayPal ipn not verified.');
        }
        return false;
    }

    /**
     * @param $ipnResponse
     * @return array
     */
    private function parseIpnResponse($ipnResponse)
    {
        $ipn_data=array(
            'businessAccount'=>'',
            'paymentStatus'=>'',
            'customData'=>'',
            'amount'=>'',
            'currency'=>'',
            'txnId'=>'',
            'testing_ipn'=>false,
        );

        $businessEmail = $this->businessAccount;

        $ipnBusinessEmail = "";

        if(array_key_exists('business', $ipnResponse))
        {
            $ipnBusinessEmail = $ipnResponse['business'];
        }

        if(array_key_exists('receiver_email', $ipnResponse))
        {
            $ipnBusinessEmail = $ipnResponse['receiver_email'];
        }

        $ipn_data['businessAccount']=trim($businessEmail);

        if(array_key_exists('payment_status', $ipnResponse))
        {
            if ($ipnResponse['payment_status'] != null)
            {
                $ipn_data['paymentStatus']=trim($ipnResponse['payment_status']);
            }
        }

        if(array_key_exists('custom', $ipnResponse))
        {
            if ($ipnResponse['custom'] != null)
            {
                $ipn_data['custom']=trim($ipnResponse['custom']);
            }
        }

        if(array_key_exists('mc_gross', $ipnResponse))
        {
            if ($ipnResponse['mc_gross'] != null)
            {
                $ipn_data['amount']=trim($ipnResponse['mc_gross']);
            }
        }

        if(array_key_exists('mc_currency', $ipnResponse))
        {
            if ($ipnResponse['mc_currency'] != null)
            {
                $ipn_data['currency']=trim($ipnResponse['mc_currency']);
            }
        }

        if(array_key_exists('test_ipn', $ipnResponse))
        {
            if($ipnResponse['test_ipn'])
            {
                $ipn_data['testing_ipn']=true;
            }
        }

        if(array_key_exists('txn_id', $ipnResponse))
        {
            if($ipnResponse['txn_id'])
            {
                $ipn_data['txnId']=$ipnResponse['txn_id'];
            }
        }
        return $ipn_data;
    }
}