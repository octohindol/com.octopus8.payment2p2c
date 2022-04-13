<?php
/*
   +----------------------------------------------------------------------------+
   | Payflow Pro Core Payment Module for CiviCRM version 5                      |
   +----------------------------------------------------------------------------+
   | Licensed to CiviCRM under the Academic Free License version 3.0            |
   |                                                                            |
   | Written & Contributed by Eileen McNaughton - 2009                          |
   +---------------------------------------------------------------------------+
  */

use Civi\Payment\Exception\PaymentProcessorException;

/**
 * Class CRM_Core_Payment_Payment2c2p.
 */
class CRM_Core_Payment_Payment2c2p extends CRM_Core_Payment
{
    private $data;
    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleClient;

    /**
     * We only need one instance of this object. So we use the singleton
     * pattern and cache the instance in this variable
     *
     * @var object
     * @static
     */
    static private $_singleton = null;

    /**
     * mode of operation: live or test
     *
     * @var object
     * @static
     */
    static protected $_mode = null;

    protected $_payment_token = null;

    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    function __construct($mode, &$paymentProcessor)
    {

        $config = CRM_Core_Config::singleton();
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_processorName = ts('2c2p Payment Processor');
    }


    /**
     * @return \GuzzleHttp\Client
     */
    public function getGuzzleClient(): \GuzzleHttp\Client
    {
        return $this->guzzleClient ?? new \GuzzleHttp\Client();
    }

    /**
     * @param \GuzzleHttp\Client $guzzleClient
     */
    public function setGuzzleClient(\GuzzleHttp\Client $guzzleClient)
    {
        $this->guzzleClient = $guzzleClient;
    }

    /*
     * This function  sends request and receives response from
     * the processor. It is the main function for processing on-server
     * credit card transactions
     */


    /**
     * singleton function used to manage this object
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return object
     * @static
     *
     */
    static function &singleton($mode, &$paymentProcessor)
    {
        $processorName = $paymentProcessor['name'];
        if (self::$_singleton[$processorName] === null) {
            self::$_singleton[$processorName] = new org_civicrm_payment_zaakpay($mode, $paymentProcessor);
        }
        return self::$_singleton[$processorName];
    }

    /**
     * This function checks to see if we have the right config values
     *
     * @return string the error message if any
     * @public
     */
    function checkConfig()
    {
        $config = CRM_Core_Config::singleton();

        $error = array();

        if (empty($this->_paymentProcessor['user_name'])) {
            $error[] = ts('Merchant Identifier must not be empty.');
        }

        if (empty($this->_paymentProcessor['password'])) {
            $error[] = ts('Secret Key must not be empty.');
        }


        if (!empty($error)) {
            return implode('<p>', $error);
        } else {
            return NULL;
        }
    }

    function doDirectPayment(&$params)
    {
//        CRM_Core_Error::debug_var('paymentProcessor', $this->_paymentProcessor);
//        CRM_Core_Error::debug_var('params', $params);

//        print_r($this->_paymentProcessor);
//        print_r($params);
//        $this->doTransferCheckout($params, 'component');
        CRM_Core_Error::statusBounce(ts('This function is not implemented'));
    }

    /**
     * @param array $params
     * @param string $component
     * @return array|void
     */
    function doTransferCheckout(&$params, $component = 'component')
    {
        $component = strtolower($component);

        if ($component != 'contribute' && $component != 'event') {
            //is used only for contribute and event
            CRM_Core_Error::statusBounce(ts('Component is invalid'));
        }

        $merchantId = $this->_paymentProcessor['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $this->_paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $url = $this->_paymentProcessor['url_site'];    //Get url_site from 2C2P PGW Dashboard
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        $amount = $params['amount'];
        $currency = 'SGD'; //works only with for a while
        $processor_name = $this->_paymentProcessor['signature']; //Get processor_name from 2C2P PGW Dashboard
        $frontendReturnUrl = $this->getReturnUrl($processor_name, $params, $component);


        /*
         * 1) Create Token Request
         * 2) Get Payment encoded Token Response
         * 3) Get decoded Payment Response
         * */

        $paymentTokenRequest = $this->createPaymentTokenRequest(
            $secretKey,
            $merchantId,
            $invoiceNo,
            $description,
            $amount,
            $currency,
            $frontendReturnUrl
        );

        $encodedTokenResponse = $this->getEncodedTokenResponse($url, $paymentTokenRequest);

        $decodedTokenResponse = $this->getDecodedTokenResponse($secretKey, $encodedTokenResponse);
        $webPaymentUrl = $decodedTokenResponse['webPaymentUrl'];
        $paymentToken = $decodedTokenResponse['paymentToken'];

        $this->_paymentToken = $paymentToken;
        //can be used later to get info about the payment

        // Print the tpl to redirect and send POST variables to RedSys Getaway.
        $this->gotoPaymentGateway($webPaymentUrl);

        CRM_Utils_System::civiExit();

        exit;
    }

    /*
     * 	This is the function which handles the response
     * when 2c2p redirects the user back to our website
     * after transaction.
     * Refer to the $this->data['returnURL'] in above function to see how the Url should be created
     */

    public function handlePaymentNotification()
    {

        $payloadResponse = $_REQUEST['paymentResponse'];
        require_once 'CRM/Utils/Array.php';
        $paymentResponse = CRM_Payment2c2p_Helper::getDecodedPayload64($payloadResponse);
//        CRM_Core_Error::debug_var('paymentResponse', $paymentResponse);

        $module = CRM_Utils_Array::value('md', $_GET);
        $qfKey = CRM_Utils_Array::value('qfKey', $_GET);
        $invoiceId = CRM_Utils_Array::value('inId', $_GET);
        $orderId = substr($invoiceId, 0, 15);
        $url = CRM_Utils_System::url('civicrm');
        switch ($module) {
            case 'contribute':
                $url = CRM_Utils_System::url('civicrm/contribute');
                if ($paymentResponse['respCode'] == 2000) {
                    $query = "UPDATE civicrm_contribution SET trxn_id='" . $orderId . "', contribution_status_id=1 where invoice_id='" . $invoiceId . "'";
                    CRM_Core_DAO::executeQuery($query);
                } else {
                    $query = "UPDATE civicrm_contribution SET trxn_id='" . $orderId . "', contribution_status_id=4 where invoice_id='" . $invoiceId . "'";
                    CRM_Core_DAO::executeQuery($query);
                    CRM_Core_Error::statusBounce(ts($_POST['respDesc']) . ts('2c2p Error:') . 'error', $url, 'error');
                    return FALSE;
                }

                break;

            case 'event':
                $url = CRM_Utils_System::url('civicrm/event');

                if ($paymentResponse['respCode'] == 2000) { // success code
                    $participantId = CRM_Utils_Array::value('pid', $_GET);
                    $eventId = CRM_Utils_Array::value('eid', $_GET);
                    $query = "UPDATE civicrm_participant SET status_id = 1 where id =" . $participantId . " AND event_id=" . $eventId;
                    CRM_Core_DAO::executeQuery($query);
                    $query = "UPDATE civicrm_contribution SET trxn_id='" . $orderId . "', contribution_status_id=1 where invoice_id='" . $invoiceId . "'";
                    CRM_Core_DAO::executeQuery($query);

                } else { // error code
                    $query = "UPDATE civicrm_contribution SET trxn_id='" . $orderId . "', contribution_status_id=4 where invoice_id='" . $invoiceId . "'";
                    CRM_Core_DAO::executeQuery($query);
                    CRM_Core_Error::statusBounce(ts($_POST['respDesc']) . ts('2c2p Error:') . 'error', $url, 'error');
                    return FALSE;
                }

                break;

            default:
                CRM_Core_Error::statusBounce("Could not get module name from request url", $url);
        }

        CRM_Utils_System::redirect($url);
        return TRUE;
    }


    public function getCurrentVersion()
    {
        return CRM_Payment2c2p_Config::PAYMENT_2C2P_VERSION;
    }

    public function base64url_encode($data)
    {
        return CRM_Payment2c2p_Helper::base64url_encode($data);
    }

    public function base64url_decode($data)
    {
        return CRM_Payment2c2p_Helper::base64url_decode($data);
    }

    public function createPaymentTokenRequest($secretkey,
                                              $merchant_id,
                                              $invoice_no,
                                              $description,
                                              $amount,
                                              $currency,
                                              $frontendReturnUrl = "" )
    {
        if($frontendReturnUrl == ""){
            $frontendReturnUrl = CRM_Utils_System::baseCMSURL();
        }
        $paymentTokenRequest = new CRM_Payment2c2p_Helper;
        $paymentTokenRequest->secretkey = $secretkey;
        $paymentTokenRequest->merchant_id = $merchant_id;
        $paymentTokenRequest->invoice_no = $invoice_no;
        $paymentTokenRequest->description = $description;
        $paymentTokenRequest->amount = $amount;
        $paymentTokenRequest->currency = $currency;
        $paymentTokenRequest->frontendReturnUrl = $frontendReturnUrl;
        return $paymentTokenRequest->getJwtData();
    }

    /**
     * @param $secretKey
     * @param $response
     * @return array
     */
    public function getDecodedTokenResponse($response, $secretKey, $responseType = "payload")
    {

        return CRM_Payment2c2p_Helper::getDecodedTokenResponse($response, $secretKey, $responseType);
    }

    public function getEncodedTokenResponse($url, $payload)
    {
        return CRM_Payment2c2p_Helper::getEncodedTokenResponse($url, $payload);
    }

    public function getReturnUrl($processor_name, $params, $component = 'contribute')
    {
        if (!isset($params['orderID'])) {
            $params['orderID'] = substr($params['invoiceID'], 0, 15);
        }
        if ($component == 'contribute') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_name=$processor_name&md=contribute&qfKey=" .
                $params['qfKey'] .
                '&inId=' . $params['invoiceID'] .
                '&orderId=' . $params['orderID'];
            return $this->data['returnUrl'];
        } else if ($component == 'event') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_name=$processor_name&md=event&qfKey=" .
                $params['qfKey'] .
                '&pid=' . $params['participantID'] .
                "&eid=" . $params['eventID'] .
                "&inId=" . $params['invoiceID'] .
                '&orderId=' . $params['orderID'];
            return $this->data['returnUrl'];
        }
        return $this->data['returnUrl'];
    }

    /**
     * @param $webPaymentUrl
     */
    public function gotoPaymentGateway($webPaymentUrl): void
    {
        $template = CRM_Core_Smarty::singleton();
        $tpl = 'CRM/Core/Payment/Payment2c2p.tpl';
        $template->assign('webPaymentUrl', $webPaymentUrl);
        print $template->fetch($tpl);
    }


}

