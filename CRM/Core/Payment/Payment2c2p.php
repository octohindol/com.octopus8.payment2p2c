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
use \Firebase\JWT\JWT;

/**
 * Class CRM_Core_Payment_Payment2c2p.
 */
class CRM_Core_Payment_Payment2c2p extends CRM_Core_Payment
{
    const LENTRXNID = 10;

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
//        CRM_Core_Error::debug_var('this', $this);
//        CRM_Core_Error::debug_var('paymentProcessor', $paymentProcessor);

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
            self::$_singleton[$processorName] = new CRM_Core_Payment_Payment2c2p($mode, $paymentProcessor);
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

    function _doDirectPayment(&$params)
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
//    function _doTransferCheckout(&$params, $component = 'component')
//    {
//        $component = strtolower($component);
//
//        if ($component != 'contribute' && $component != 'event') {
//            //is used only for contribute and event
//            CRM_Core_Error::statusBounce(ts('Component is invalid'));
//        }
//
//        $merchantId = $this->_paymentProcessor['user_name'];        //Get MerchantID when opening account with 2C2P
//        $secretKey = $this->_paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
//        $url = $this->_paymentProcessor['url_site'] . '/payment/4.1/PaymentToken';    //Get url_site from 2C2P PGW Dashboard
//        $invoiceNo = $params['invoiceID'];
//        $description = $params['description'];
//        $amount = $params['amount'];
//        $currency = 'SGD'; //works only with for a while
//        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
//        $frontendReturnUrl = $this->getReturnUrl($processor_name, $params, $component);
//
//
//        /*
//         * 1) Create Token Request
//         * 2) Get Payment encoded Token Response
//         * 3) Get decoded Payment Response
//         * */
//
//        $paymentTokenRequest = $this->createPaymentTokenRequest(
//            $secretKey,
//            $merchantId,
//            $invoiceNo,
//            $description,
//            $amount,
//            $currency,
//            $frontendReturnUrl
//        );
//
////        CRM_Core_Error::debug_var('paymentTokenRequest', $paymentTokenRequest);
//        $encodedTokenResponse = $this->getEncodedTokenResponse($url, $paymentTokenRequest);
////        CRM_Core_Error::debug_var('encodedTokenResponse', $encodedTokenResponse);
//
//        $decodedTokenResponse = $this->getDecodedTokenResponse($secretKey, $encodedTokenResponse);
//        $webPaymentUrl = $decodedTokenResponse['webPaymentUrl'];
//        $paymentToken = $decodedTokenResponse['paymentToken'];
////        CRM_Core_Error::debug_var('paymentToken', $paymentToken);
//
//        $this->_paymentToken = $paymentToken;
//        //can be used later to get info about the payment
//
//        // Print the tpl to redirect and send POST variables to RedSys Getaway.
//        $this->gotoPaymentGateway($webPaymentUrl);
//
//        CRM_Utils_System::civiExit();
//
//        exit;
//    }

    public function doPayment(&$params, $component = 'contribute')
    {
        $propertyBag = \Civi\Payment\PropertyBag::cast($params);
        $this->_component = $component;
        $statuses = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id', 'validate');

        // If we have a $0 amount, skip call to processor and set payment_status to Completed.
        // Conceivably a processor might override this - perhaps for setting up a token - but we don't
        // have an example of that at the moment.
        if ($propertyBag->getAmount() == 0) {
            $result['payment_status_id'] = array_search('Completed', $statuses);
            $result['payment_status'] = 'Completed';
            return $result;
        }
        $recurring = false;
        $invoicePrefix = "";
        $allowAccumulate = true;
        $maxAccumulateAmount = 30;
        $recurringInterval = 1;
        $chargeNextDate = "";
        $recurringCount = 30;
        if (CRM_Utils_Array::value('is_recur', $params) == TRUE) {
            CRM_Core_Error::debug_var('recurParams', $params);
            if (CRM_Utils_Array::value('frequency_unit', $params) == 'day') {
                $frequencyUnit = "day";
            } else {
                throw new CRM_Core_Exception(ts('2c2p - recurring payments should be set in days'));
            }
            $recurring = true;
            $invoicePrefix = substr($params['invoiceID'], 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
            $allowAccumulate = true;
            $maxAccumulateAmount = 30;
            $recurringInterval = intval(CRM_Utils_Array::value('frequency_interval', $params, 0));
            $chargeNextDate = date('dmY');
            $recurringCount = 30;
//            throw new CRM_Core_Exception(ts('2c2p - recurring payments not implemented'));
        }

        if (!defined('CURLOPT_SSLCERT')) {
            throw new CRM_Core_Exception(ts('2c2p - Gateway requires curl with SSL support'));
        }

        if ($this->_paymentProcessor['billing_mode'] != 4) {
            throw new CRM_Core_Exception(ts('2c2p - Direct payment not implemented'));
        }
        // 2c2p Merchant ID
//        $ewayCustomerID = $this->_paymentProcessor['user_name'];
//        // 2c2p GetToken URL
//        $gateway_URL = $this->_paymentProcessor['url_site'];
        $merchantId = $this->_paymentProcessor['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $this->_paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $url = $this->_paymentProcessor['url_site'] . '/payment/4.1/PaymentToken';    //Get url_site from 2C2P PGW Dashboard
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        $amount = $params['amount'];
        $currency = 'SGD'; //works only with for a while
        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
        $processor_id = $this->_paymentProcessor['id']; //Get processor_name from 2C2P PGW Dashboard
        $frontendReturnUrl = $this->getReturnUrl($processor_id, $processor_name, $params, $component);


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
            $frontendReturnUrl,
            $recurring,
            $invoicePrefix,
            $allowAccumulate,
            $maxAccumulateAmount,
            $recurringInterval,
            $chargeNextDate,
            $recurringCount
        );

        CRM_Core_Error::debug_var('paymentTokenRequest', $paymentTokenRequest);
        $encodedTokenResponse = $this->getEncodedTokenResponse($url, $paymentTokenRequest);
        CRM_Core_Error::debug_var('encodedTokenResponse', $encodedTokenResponse);

        $decodedTokenResponse = $this->getDecodedTokenResponse($secretKey, $encodedTokenResponse);
        CRM_Core_Error::debug_var('decodedTokenResponse', $decodedTokenResponse);
        $webPaymentUrl = $decodedTokenResponse['webPaymentUrl'];
        $paymentToken = $decodedTokenResponse['paymentToken'];
        $query = "UPDATE civicrm_contribution SET check_number='$paymentToken' where invoice_id='" . $invoiceNo . "'";
        CRM_Core_DAO::executeQuery($query);
//        CRM_Core_Error::debug_var('paymentToken', $paymentToken);

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
//        $params = array_merge($_GET, $_REQUEST);
//        $q = explode('/', CRM_Utils_Array::value('q', $params, ''));
//        $lastParam = array_pop($q);
//        if (is_numeric($lastParam)) {
//            $params['processor_id'] = $lastParam;
//        }
//        $paymentProcessor = civicrm_api3('PaymentProcessor', 'get', [
//            'sequential' => 1,
//            'id' => $params['processor_id'],
//            'api.PaymentProcessorType.getvalue' => ['return' => "name"],
//        ]);
//        CRM_Core_Error::debug_var('paymentProcessor', $paymentProcessor);

        $encodedPaymentResponse = $_REQUEST['paymentResponse'];
        $paymentResponse = $this->decodePayload64($encodedPaymentResponse);
////        CRM_Core_Error::debug_var('paymentResponse', $paymentResponse);

        require_once 'CRM/Utils/Array.php';
        $module = CRM_Utils_Array::value('md', $_GET);
        $invoiceId = CRM_Utils_Array::value('inId', $_GET);

        /** @var TYPE_NAME $this */
//        $url = CRM_Utils_System::url(
//            $this->_paymentProcessor['subject'], //$path
//            null, //$query
//            true, //$absolute
//            null, //$fragment
//            null, //$htmlize
//            true); //$frontend

        $url = strval($this->_paymentProcessor['subject']);
        if($url != ""){
            $url = CRM_Utils_System::url($url,NULL,NULL,true,NULL,NULL,true);
        }else{
        $url = CRM_Utils_System::url();
        }

        switch ($module) {
            case 'contribute':
//                $url = CRM_Utils_System::url('civicrm/contribute');
                if ($paymentResponse['respCode'] == 2000) {
                    $this->setContributionStatusRecieved($invoiceId);
                } else {
                    $this->setContributionStatusRejected($invoiceId, $url);
                }

                break;

            case 'event':
//                $url = CRM_Utils_System::url('civicrm/event');

                if ($paymentResponse['respCode'] == 2000) { // success code
                    $participantId = CRM_Utils_Array::value('pid', $_GET);
                    $eventId = CRM_Utils_Array::value('eid', $_GET);
                    $query = "UPDATE civicrm_participant SET status_id = 1 where id =$participantId AND event_id=$eventId";
                    CRM_Core_DAO::executeQuery($query);

                    $this->setContributionStatusRecieved($invoiceId);
                } else { // error code
                    $this->setContributionStatusRejected($invoiceId, $url);
                }

                break;

            default:
                CRM_Core_Error::statusBounce("Could not get module name from request url", $url);
        }

//        $url = CRM_Utils_System::url($this->_paymentProcessor['subject']);
//        CRM_Utils_System::redirect($this->_paymentProcessor['subject']);
        return TRUE;
    }


    public function getCurrentVersion()
    {
        return CRM_Payment2c2p_Config::PAYMENT_2C2P_VERSION;
    }

    public function base64url_encode($data)
    {
        return CRM_Payment2c2p_TokenRequest::base64url_encode($data);
    }

    public function base64url_decode($data)
    {
        return CRM_Payment2c2p_TokenRequest::base64url_decode($data);
    }

    public function createPaymentTokenRequest($secretkey,
                                              $merchantId,
                                              $invoiceNo,
                                              $description,
                                              $amount,
                                              $currency,
                                              $frontendReturnUrl = "",
                                              $recurring = FALSE,
                                              $invoicePrefix = "",
                                              $allowAccumulate = TRUE,
                                              $maxAccumulateAmount = 30,
                                              $recurringInterval = 1,
                                              $chargeNextDate = "",
                                              $recurringCount = 30)
    {
        if ($frontendReturnUrl == "") {
            $frontendReturnUrl = CRM_Utils_System::baseCMSURL();
        }
        $paymentTokenRequest = new CRM_Payment2c2p_TokenRequest;
        $paymentTokenRequest->secretkey = $secretkey;
        $paymentTokenRequest->merchantID = $merchantId;
        $paymentTokenRequest->invoiceNo = $invoiceNo;
        $paymentTokenRequest->description = $description;
        $paymentTokenRequest->amount = $amount;
        $paymentTokenRequest->currencyCode = $currency;
        $paymentTokenRequest->frontendReturnUrl = $frontendReturnUrl;
        $payload = array(
            "merchantID" => $merchantId,
            "invoiceNo" => $invoiceNo,
            "description" => $description,
            "amount" => $amount,
            "currencyCode" => $currency,
            "frontendReturnUrl" => $frontendReturnUrl,
        );
        if ($recurring === TRUE) {
            $payload['recurring'] = TRUE;
            $payload['invoicePrefix'] = $invoicePrefix;
            $payload['allowAccumulate'] = $allowAccumulate;
            $payload['maxAccumulateAmount'] = $maxAccumulateAmount;
            $payload['recurringInterval'] = $recurringInterval;
            $payload['chargeNextDate'] = $chargeNextDate;
            $payload['recurringCount'] = $recurringCount;
        }
        CRM_Core_Error::debug_var('paymentTokenPayload', $payload);
        return $paymentTokenRequest->getJwtData($payload);
    }

    /**
     * @param $secretKey
     * @param $response
     * @return array
     */
    public function getDecodedTokenResponse($secretKey, $response, $responseType = "payload")
    {

        return CRM_Payment2c2p_TokenRequest::getDecodedTokenResponse($secretKey, $response, $responseType);
    }

    public function getEncodedTokenResponse($url, $payload)
    {
        return CRM_Payment2c2p_TokenRequest::getEncodedTokenResponse($url, $payload);
    }

    public function getReturnUrl($processor_id, $processor_name, $params, $component = 'contribute')
    {
        if (!isset($params['orderID'])) {
            $params['orderID'] = substr($params['invoiceID'], 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
        }
        if ($component == 'contribute') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_id=$processor_id&processor_name=$processor_name&md=contribute&qfKey=" .
                $params['qfKey'] .
                '&inId=' . $params['invoiceID'] .
                '&orderId=' . $params['orderID'];
            return $this->data['returnUrl'];
        } else if ($component == 'event') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_id=$processor_id&processor_name=$processor_name&md=event&qfKey=" .
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

    /**
     * @param $payloadResponse
     * @return array
     */
    public function decodePayload64($payloadResponse): array
    {
        $paymentResponse = CRM_Payment2c2p_TokenRequest::getDecodedPayload64($payloadResponse);
        return $paymentResponse;
    }

    /**
     * @param $invoiceId
     */
    public function setContributionStatusRecieved($invoiceId): void
    {
        $trxnId = substr($invoiceId, 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
        $contributionParams = [
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ];
        $contributionParams['invoice_id'] = $invoiceId;
        $contribution = civicrm_api3('Contribution', 'get', $contributionParams)['values'];
        $contribution = reset($contribution);
        $paymentToken = $contribution['check_number'];
//        CRM_Core_Error::debug_var('paymentToken', $paymentToken);
//        CRM_Core_Error::debug_var('paymentProcessor', $this->_paymentProcessor);
        $url = $this->_paymentProcessor['url_site'] . '/payment/4.1/paymentInquiry';    //Get url_site from 2C2P PGW Dashboard
        $secretkey = $this->_paymentProcessor['password'];
        $merchantID = $this->_paymentProcessor['user_name'];
        $payload = array(
            "paymentToken" => $paymentToken,
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceId,
            "locale" => "en");
        $inquiryRequestData = $this->encodeJwtData($secretkey, $payload);
        $encodedTokenResponse = $this->getEncodedTokenResponse($url, $inquiryRequestData);
        $decodedTokenResponse = $this->getDecodedTokenResponse($secretkey, $encodedTokenResponse);
        CRM_Core_Error::debug_var('decodedTokenResponse', $decodedTokenResponse);
//        CRM_Core_Error::debug_var('paymentProcessor', $this->_paymentProcessor);
        $cardNo = substr($decodedTokenResponse['cardNo'], -4);
        $cardType = $decodedTokenResponse['cardType'];
        $channelCode = $decodedTokenResponse['channelCode'];
        $cardTypeId = 2;
        $paymentInstrumentId = null;
        if ($cardType == 'CREDIT') {
//            $cardTypeId = 1;
            $paymentInstrumentId = 1;
        }
        if ($channelCode == 'VI') {
            $cardTypeId = 1;
//            $paymentInstrumentId = 1;
        }
        $issuerBank = $decodedTokenResponse['issuerBank'];
        $query = "UPDATE civicrm_contribution SET invoice_number='$issuerBank' where invoice_id='" . $invoiceId . "'";
        CRM_Core_DAO::executeQuery($query);
        $query = "UPDATE civicrm_contribution SET check_number='' where invoice_id='" . $invoiceId . "'";
        CRM_Core_DAO::executeQuery($query);

        $contributionId = $contribution['id'];
        try {
            civicrm_api3('contribution', 'completetransaction',
                ['id' => $contributionId,
                    'trxn_id' => $trxnId,
                    'pan_truncation' => $cardNo,
                    'card_type_id' => $cardTypeId,
                    'payment_instrument_id' => $paymentInstrumentId,
                    'processor_id' => $this->_paymentProcessor['id'] ]);
        } catch (CiviCRM_API3_Exception $e) {
            if (!stristr($e->getMessage(), 'Contribution already completed')) {
                Civi::log()->debug("2c2p IPN Error Updating contribution: " . $e->getMessage());
            }
        }

        //        CRM_Core_DAO::executeQuery($query);
    }

    /**
     * @param $invoiceId
     * @param string $url
     */
    public function setContributionStatusRejected($invoiceId, string $url): void
    {
        $query = "UPDATE civicrm_contribution SET check_number='' where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
        $query = "UPDATE civicrm_contribution SET contribution_status_id=4 where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
        CRM_Core_Error::statusBounce(ts($_POST['respDesc']) . ts('2c2p Error:') . 'error', $url, 'error');
    }


    public function encodeJwtData($secretkey, $payload): string
    {

        $jwt = JWT::encode($payload, $secretkey);

        $data = '{"payload":"' . $jwt . '"}';

        return $data;
    }

}

