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
    function _doTransferCheckout(&$params, $component = 'component')
    {
        $component = strtolower($component);

        if ($component != 'contribute' && $component != 'event') {
            //is used only for contribute and event
            CRM_Core_Error::statusBounce(ts('Component is invalid'));
        }

        $merchantId = $this->_paymentProcessor['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $this->_paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $url = $this->_paymentProcessor['url_site'] . '/payment/4.1/PaymentToken';    //Get url_site from 2C2P PGW Dashboard
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        $amount = $params['amount'];
        $currency = 'SGD'; //works only with for a while
        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
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

//        CRM_Core_Error::debug_var('paymentTokenRequest', $paymentTokenRequest);
        $encodedTokenResponse = $this->getEncodedTokenResponse($url, $paymentTokenRequest);
//        CRM_Core_Error::debug_var('encodedTokenResponse', $encodedTokenResponse);

        $decodedTokenResponse = $this->getDecodedTokenResponse($secretKey, $encodedTokenResponse);
        $webPaymentUrl = $decodedTokenResponse['webPaymentUrl'];
        $paymentToken = $decodedTokenResponse['paymentToken'];
//        CRM_Core_Error::debug_var('paymentToken', $paymentToken);

        $this->_paymentToken = $paymentToken;
        //can be used later to get info about the payment

        // Print the tpl to redirect and send POST variables to RedSys Getaway.
        $this->gotoPaymentGateway($webPaymentUrl);

        CRM_Utils_System::civiExit();

        exit;
    }

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

        if (CRM_Utils_Array::value('is_recur', $params) == TRUE) {
            CRM_Core_Error::debug_var('recurParams', $params);
            throw new CRM_Core_Exception(ts('2c2p - recurring payments not implemented'));
        }

        if (!defined('CURLOPT_SSLCERT')) {
            throw new CRM_Core_Exception(ts('2c2p - Gateway requires curl with SSL support'));
        }

        if ($this->_paymentProcessor['billing_mode'] != 4) {
            throw new CRM_Core_Exception(ts('2c2p - Direct payment not implemented'));
        }
        // 2c2p Merchant ID
        $ewayCustomerID = $this->_paymentProcessor['user_name'];
        // 2c2p GetToken URL
        $gateway_URL = $this->_paymentProcessor['url_site'];

//        //------------------------------------
//        // create eWAY gateway objects
//        //------------------------------------
//        $eWAYRequest = new GatewayRequest();
//
//        if (($eWAYRequest == NULL) || (!($eWAYRequest instanceof GatewayRequest))) {
//            throw new PaymentProcessorException('Error: Unable to create eWAY Request object.', 9001);
//        }
//
//        $eWAYResponse = new GatewayResponse();
//
//        if (($eWAYResponse == NULL) || (!($eWAYResponse instanceof GatewayResponse))) {
//            throw new PaymentProcessorException(9002, 'Error: Unable to create eWAY Response object.', 9002);
//        }
//
//        /*
//        //-------------------------------------------------------------
//        // NOTE: eWAY Doesn't use the following at the moment:
//        //-------------------------------------------------------------
//        $creditCardType = $params['credit_card_type'];
//        $currentcyID    = $params['currencyID'];
//        $country        = $params['country'];
//         */
//
//        //-------------------------------------------------------------
//        // Prepare some composite data from _paymentProcessor fields
//        //-------------------------------------------------------------
//        $fullAddress = $params['street_address'] . ", " . $params['city'] . ", " . $params['state_province'] . ".";
//        $expireYear = substr($params['year'], 2, 2);
//        $expireMonth = sprintf('%02d', (int) $params['month']);
//        $description = $params['description'];
//        $txtOptions = "";
//
//        $amountInCents = round(((float) $params['amount']) * 100);
//
//        $credit_card_name = $params['first_name'] . " ";
//        if (strlen($params['middle_name']) > 0) {
//            $credit_card_name .= $params['middle_name'] . " ";
//        }
//        $credit_card_name .= $params['last_name'];
//
//        //----------------------------------------------------------------------------------------------------
//        // We use CiviCRM's param's 'invoiceID' as the unique transaction token to feed to eWAY
//        // Trouble is that eWAY only accepts 16 chars for the token, while CiviCRM's invoiceID is an 32.
//        // As its made from a "$invoiceID = md5(uniqid(rand(), true));" then using the fierst 16 chars
//        // should be alright
//        //----------------------------------------------------------------------------------------------------
//        $uniqueTrnxNum = substr($params['invoiceID'], 0, 16);
//
//        //----------------------------------------------------------------------------------------------------
//        // OPTIONAL: If TEST Card Number force an Override of URL and CustomerID.
//        // During testing CiviCRM once used the LIVE URL.
//        // This code can be uncommented to override the LIVE URL that if CiviCRM does that again.
//        //----------------------------------------------------------------------------------------------------
//        //        if ( ( $gateway_URL == "https://www.eway.com.au/gateway_cvn/xmlpayment.asp")
//        //             && ( $params['credit_card_number'] == "4444333322221111" ) ) {
//        //            $ewayCustomerID = "87654321";
//        //            $gateway_URL    = "https://www.eway.com.au/gateway_cvn/xmltest/testpage.asp";
//        //        }
//
//        //----------------------------------------------------------------------------------------------------
//        // Now set the payment details - see http://www.eway.com.au/Support/Developer/PaymentsRealTime.aspx
//        //----------------------------------------------------------------------------------------------------
//        // 8 Chars - ewayCustomerID                 - Required
//        $eWAYRequest->EwayCustomerID($ewayCustomerID);
//        // 12 Chars - ewayTotalAmount  (in cents)    - Required
//        $eWAYRequest->InvoiceAmount($amountInCents);
//        // 50 Chars - ewayCustomerFirstName
//        $eWAYRequest->PurchaserFirstName($params['first_name']);
//        // 50 Chars - ewayCustomerLastName
//        $eWAYRequest->PurchaserLastName($params['last_name']);
//        // 50 Chars - ewayCustomerEmail
//        $eWAYRequest->PurchaserEmailAddress($params['email']);
//        // 255 Chars - ewayCustomerAddress
//        $eWAYRequest->PurchaserAddress($fullAddress);
//        // 6 Chars - ewayCustomerPostcode
//        $eWAYRequest->PurchaserPostalCode($params['postal_code']);
//        // 1000 Chars - ewayCustomerInvoiceDescription
//        $eWAYRequest->InvoiceDescription($description);
//        // 50 Chars - ewayCustomerInvoiceRef
//        $eWAYRequest->InvoiceReference($params['invoiceID']);
//        // 50 Chars - ewayCardHoldersName            - Required
//        $eWAYRequest->CardHolderName($credit_card_name);
//        // 20 Chars - ewayCardNumber                 - Required
//        $eWAYRequest->CardNumber($params['credit_card_number']);
//        // 2 Chars - ewayCardExpiryMonth            - Required
//        $eWAYRequest->CardExpiryMonth($expireMonth);
//        // 2 Chars - ewayCardExpiryYear             - Required
//        $eWAYRequest->CardExpiryYear($expireYear);
//        // 4 Chars - ewayCVN                        - Required if CVN Gateway used
//        $eWAYRequest->CVN($params['cvv2']);
//        // 16 Chars - ewayTrxnNumber
//        $eWAYRequest->TransactionNumber($uniqueTrnxNum);
//        // 255 Chars - ewayOption1
//        $eWAYRequest->EwayOption1($txtOptions);
//        // 255 Chars - ewayOption2
//        $eWAYRequest->EwayOption2($txtOptions);
//        // 255 Chars - ewayOption3
//        $eWAYRequest->EwayOption3($txtOptions);
//
//        $eWAYRequest->CustomerIPAddress($params['ip_address']);
//        $eWAYRequest->CustomerBillingCountry($params['country']);
//
//        // Allow further manipulation of the arguments via custom hooks ..
//        CRM_Utils_Hook::alterPaymentProcessorParams($this, $params, $eWAYRequest);
//
//        //----------------------------------------------------------------------------------------------------
//        // Check to see if we have a duplicate before we send
//        //----------------------------------------------------------------------------------------------------
//        if ($this->checkDupe($params['invoiceID'], CRM_Utils_Array::value('contributionID', $params))) {
//            throw new PaymentProcessorException('It appears that this transaction is a duplicate.  Have you already submitted the form once?  If so there may have been a connection problem.  Check your email for a receipt from eWAY.  If you do not receive a receipt within 2 hours you can try your transaction again.  If you continue to have problems please contact the site administrator.', 9003);
//        }
//
//        //----------------------------------------------------------------------------------------------------
//        // Convert to XML and send the payment information
//        //----------------------------------------------------------------------------------------------------
//        $requestxml = $eWAYRequest->ToXML();
//        //@todo
//        $responseData = (string) $this->getGuzzleClient()->post($this->_paymentProcessor['url_site'], [
//            'body' => $requestxml,
//            'curl' => [
//                CURLOPT_RETURNTRANSFER => TRUE,
//                CURLOPT_SSL_VERIFYPEER => Civi::settings()->get('verifySSL'),
//            ],
//        ])->getBody();
//
//        //----------------------------------------------------------------------------------------------------
//        // If null data returned - tell 'em and bail out
//        //
//        // NOTE: You will not necessarily get a string back, if the request failed for
//        //       any reason, the return value will be the boolean false.
//        //----------------------------------------------------------------------------------------------------
//        if (($responseData === FALSE) || (strlen($responseData) == 0)) {
//            throw new PaymentProcessorException('Error: Connection to payment gateway failed - no data returned.', 9006);
//        }
//
//        //----------------------------------------------------------------------------------------------------
//        // If gateway returned no data - tell 'em and bail out
//        //----------------------------------------------------------------------------------------------------
//        if (empty($responseData)) {
//            throw new PaymentProcessorException('Error: No data returned from payment gateway.', 9007);
//        }
//
//        //----------------------------------------------------------------------------------------------------
//        // Payment successfully sent to gateway - process the response now
//        //----------------------------------------------------------------------------------------------------
//        $eWAYResponse->ProcessResponse($responseData);
//
//        //----------------------------------------------------------------------------------------------------
//        // See if we got an OK result - if not tell 'em and bail out
//        //----------------------------------------------------------------------------------------------------
//        if (self::isError($eWAYResponse)) {
//            $eWayTrxnError = $eWAYResponse->Error();
//            CRM_Core_Error::debug_var('eWay Error', $eWayTrxnError, TRUE, TRUE);
//            if (substr($eWayTrxnError, 0, 6) === 'Error:') {
//                throw new PaymentProcessorException($eWayTrxnError, 9008);
//            }
//            $eWayErrorCode = substr($eWayTrxnError, 0, 2);
//            $eWayErrorDesc = substr($eWayTrxnError, 3);
//
//            throw new PaymentProcessorException('Error: [' . $eWayErrorCode . "] - " . $eWayErrorDesc . '.', 9008);
//        }
//
//        //=============
//        // Success !
//        //=============
//        $beaglestatus = $eWAYResponse->BeagleScore();
//        if (!empty($beaglestatus)) {
//            $beaglestatus = ': ' . $beaglestatus;
//        }
//        $params['trxn_result_code'] = $eWAYResponse->Status() . $beaglestatus;
//        $params['trxn_id'] = $eWAYResponse->TransactionNumber();
//        $params['payment_status_id'] = array_search('Completed', $statuses);
//        $params['payment_status'] = 'Completed';
//
//        return $params;
        $merchantId = $this->_paymentProcessor['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $this->_paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard
        $url = $this->_paymentProcessor['url_site'] . '/payment/4.1/PaymentToken';    //Get url_site from 2C2P PGW Dashboard
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        $amount = $params['amount'];
        $currency = 'SGD'; //works only with for a while
        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
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

//        CRM_Core_Error::debug_var('paymentTokenRequest', $paymentTokenRequest);
        $encodedTokenResponse = $this->getEncodedTokenResponse($url, $paymentTokenRequest);
//        CRM_Core_Error::debug_var('encodedTokenResponse', $encodedTokenResponse);

        $decodedTokenResponse = $this->getDecodedTokenResponse($secretKey, $encodedTokenResponse);
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
        $url = CRM_Utils_System::url(
            $this->_paymentProcessor['subject'], //$path
            null, //$query
            true, //$absolute
            null, //$fragment
            null, //$htmlize
            true); //$frontend

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
                    $query = "UPDATE civicrm_participant SET status_id = 1 where id =" . $participantId . " AND event_id=" . $eventId;
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
                                              $merchant_id,
                                              $invoice_no,
                                              $description,
                                              $amount,
                                              $currency,
                                              $frontendReturnUrl = "")
    {
        if ($frontendReturnUrl == "") {
            $frontendReturnUrl = CRM_Utils_System::baseCMSURL();
        }
        $paymentTokenRequest = new CRM_Payment2c2p_TokenRequest;
        $paymentTokenRequest->secretkey = $secretkey;
        $paymentTokenRequest->merchantID = $merchant_id;
        $paymentTokenRequest->invoiceNo = $invoice_no;
        $paymentTokenRequest->description = $description;
        $paymentTokenRequest->amount = $amount;
        $paymentTokenRequest->currencyCode = $currency;
        $paymentTokenRequest->frontendReturnUrl = $frontendReturnUrl;
        return $paymentTokenRequest->getJwtData();
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
        $orderId = substr($invoiceId, 0, 15);
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
        $merchantID =  $this->_paymentProcessor['user_name'];
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
        if($cardType=='CREDIT'){
//            $cardTypeId = 1;
            $paymentInstrumentId = 1;
        }
        if($channelCode=='VI'){
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
                    'trxn_id' => $orderId,
                    'pan_truncation' => $cardNo,
                    'card_type_id' => $cardTypeId,
                    'payment_instrument_id' => $paymentInstrumentId]);
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
        $query = "UPDATE civicrm_contribution SET check_number='' where invoice_id='" . $invoiceId . "'";
        CRM_Core_DAO::executeQuery($query);
        $query = "UPDATE civicrm_contribution SET contribution_status_id=4 where invoice_id='" . $invoiceId . "'";
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

