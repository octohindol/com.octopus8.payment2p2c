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

    protected $_cid; // cid is contact for contact tab. if it's present, contact is freezed

    private $data;
    /**
     * @var GuzzleHttp\Client
     */
    protected $guzzleClient;

    const PAYMENT_RESPONCE = array(
        "0000" => "Successful",
        "0001" => "Transaction is pending",
        "0003" => "Transaction is cancelled",
        "0999" => "System error",
        "2001" => "Transaction in progress",
        "2002" => "Transaction not found",
        "2003" => "Failed To Inquiry",
        "4001" => "Refer to card issuer",
        "4002" => "Refer to issuer's special conditions",
        "4003" => "Invalid merchant ID",
        "4004" => "Pick up card",
        "4005" => "Do not honor",
        "4006" => "Error",
        "4007" => "Pick up card, special condition",
        "4008" => "Honor with ID",
        "4009" => "Request in progress",
        "4010" => "Partial amount approved",
        "4011" => "Approved VIP",
        "4012" => "Invalid Transaction",
        "4013" => "Invalid Amount",
        "4014" => "Invalid Card Number",
        "4015" => "No such issuer",
        "4016" => "Approved, Update Track 3",
        "4017" => "Customer Cancellation",
        "4018" => "Customer Dispute",
        "4019" => "Re-enter Transaction",
        "4020" => "Invalid Response",
        "4021" => "No Action Taken",
        "4022" => "Suspected Malfunction",
        "4023" => "Unacceptable Transaction Fee",
        "4024" => "File Update Not Supported by Receiver",
        "4025" => "Unable to Locate Record on File",
        "4026" => "Duplicate File Update Record",
        "4027" => "File Update Field Edit Error",
        "4028" => "File Update File Locked Out",
        "4029" => "File Update not Successful",
        "4030" => "Format Error",
        "4031" => "Bank Not Supported by Switch",
        "4032" => "Completed Partially",
        "4033" => "Expired Card - Pick Up",
        "4034" => "Suspected Fraud - Pick Up",
        "4035" => "Restricted Card - Pick Up",
        "4036" => "Allowable PIN Tries Exceeded",
        "4037" => "No Credit Account",
        "4038" => "Allowable PIN Tries Exceeded",
        "4039" => "No Credit Account",
        "4040" => "Requested Function not Supported",
        "4041" => "Lost Card - Pick Up",
        "4042" => "No Universal Amount",
        "4043" => "Stolen Card - Pick Up",
        "4044" => "No Investment Account",
        "4045" => "Settlement Success",
        "4046" => "Settlement Fail",
        "4047" => "Cancel Success",
        "4048" => "Cancel Fail",
        "4049" => "No Transaction Reference Number",
        "4050" => "Host Down",
        "4051" => "Insufficient Funds",
        "4052" => "No Cheque Account",
        "4053" => "No Savings Account",
        "4054" => "Expired Card",
        "4055" => "Incorrect PIN",
        "4056" => "No Card Record",
        "4057" => "Transaction Not Permitted to Cardholder",
        "4058" => "Transaction Not Permitted to Terminal",
        "4059" => "Suspected Fraud",
        "4060" => "Card Acceptor Contact Acquirer",
        "4061" => "Exceeds Withdrawal Amount Limits",
        "4062" => "Restricted Card",
        "4063" => "Security Violation",
        "4064" => "Original Amount Incorrect",
        "4065" => "Exceeds Withdrawal Frequency Limit",
        "4066" => "Card Acceptor Call Acquirer Security",
        "4067" => "Hard Capture - Pick Up Card at ATM",
        "4068" => "Response Received Too Late",
        "4069" => "Reserved",
        "4070" => "Settle amount cannot exceed authorized amount",
        "4071" => "Inquiry Record Not Exist",
        "4072" => "Promotion not allowed in current payment method",
        "4073" => "Promotion Limit Reached",
        "4074" => "Reserved",
        "4075" => "Allowable PIN Tries Exceeded",
        "4076" => "Invalid Credit Card Format",
        "4077" => "Invalid Expiry Date Format",
        "4078" => "Invalid Three Digits Format",
        "4079" => "Reserved",
        "4080" => "User Cancellation by Closing Internet Browser",
        "4081" => "Unable to Authenticate Card Holder",
        "4082" => "Reserved",
        "4083" => "Reserved",
        "4084" => "Reserved",
        "4085" => "Reserved",
        "4086" => "ATM Malfunction",
        "4087" => "No Envelope Inserted",
        "4088" => "Unable to Dispense",
        "4089" => "Administration Error",
        "4090" => "Cut-off in Progress",
        "4091" => "Issuer or Switch is Inoperative",
        "4092" => "Financial Insititution Not Found",
        "4093" => "Trans Cannot Be Completed",
        "4094" => "Duplicate Transmission",
        "4095" => "Reconcile Error",
        "4096" => "System Malfunction",
        "4097" => "Reconciliation Totals Reset",
        "4098" => "MAC Error",
        "4099" => "Unable to Complete Payment",
        "4110" => "Settled",
        "4120" => "Refunded",
        "4121" => "Refund Rejected",
        "4122" => "Refund Failed",
        "4130" => "Chargeback",
        "4131" => "Chargeback Rejected",
        "4132" => "Chargeback Failed",
        "4140" => "Transaction Does Not Exist",
        "4200" => "Tokenization Successful",
        "4201" => "Tokenization Failed",
        "5002" => "Timeout",
        "5003" => "Invalid Message",
        "5004" => "Invalid Profile (Merchant) ID",
        "5005" => "Duplicated Invoice",
        "5006" => "Invalid Amount",
        "5007" => "Insufficient Balance",
        "5008" => "Invalid Currency Code",
        "5009" => "Payment Expired",
        "5010" => "Payment Canceled By Payer",
        "5011" => "Invalid Payee ID",
        "5012" => "Invalid Customer ID",
        "5013" => "Account Does Not Exist",
        "5014" => "Authentication Failed",
        "5015" => "Customer paid more than transaction amount",
        "5016" => "Customer paid less than transaction amount",
        "5017" => "Paid Expired",
        "5018" => "Reserved",
        "5019" => "No-Action From WebPay",
        "5998" => "Internal Error",
        "6012" => "Invalid Transaction",
        "6101" => "Invalid request message",
        "6102" => "Required Payload",
        "6103" => "Invalid JWT data",
        "6104" => "Required merchantId",
        "6105" => "Required paymentChannel",
        "6106" => "Required authCode",
        "6107" => "Invalid merchantId",
        "6108" => "Invalid paymentChannel",
        "6109" => "paymentChannel is not configured",
        "6110" => "Unable to retrieve usertoken",
        "7012" => "Invalid Transaction",
        "9004" => "The value is not valid",
        "9005" => "Some mandatory fields are missing",
        "9006" => "This field exceeded its authorized length",
        "9007" => "Invalid merchant",
        "9008" => "Invalid payment expiry",
        "9009" => "Amount is invalid",
        "9010" => "Invalid Currency Code",
        "9012" => "paymentItem name is required",
        "9013" => "paymentItem quantity is required",
        "9014" => "paymentItem amount is required",
        "9015" => "Existing Invoice Number",
        "9035" => "Payment failed",
        "9037" => "Merchant configuration is missing",
        "9038" => "Failed To Generate Token",
        "9039" => "The merchant frontend URL is missing",
        "9040" => "The token is invalid",
        "9041" => "Payment token already used",
        "9042" => "Hash value mismatch",
        "9057" => "Payment options are invalid",
        "9058" => "Payment channel invalid",
        "9059" => "Payment channel unauthorized",
        "9060" => "Payment channel unconfigured",
        "9078" => "Promotion code does not exist",
        "9080" => "Tokenization not allowed",
        "9088" => "SubMerchant is required",
        "9089" => "Duplicated SubMerchant",
        "9090" => "SubMerchant Not Found",
        "9091" => "Invalid Sub Merchant ID",
        "9092" => "Invalid Sub Merchant invoiceNo",
        "9093" => "Existing Sub Merchant Invoice Number",
        "9094" => "Invalid Sub Merchant Amount",
        "9095" => "Sub Merchant Amount mismatch",
        "9901" => "Invalid invoicePrefix",
        "9902" => "allowAccumulate is required",
        "9903" => "maxAccumulateAmount is required",
        "9904" => "recurringInterval or ChargeOnDate is required",
        "9905" => "recurringCount is required",
        "9906" => "recurringInterval or ChargeOnDate is required",
        "9907" => "Invalid ChargeNextDate",
        "9908" => "Invalid ChargeOnDate",
        "9909" => "chargeNextDate is required",
        "9990" => "Request to merchant front end has failed",
        "9991" => "Request merchant secure has failed",
        "9992" => "Request payment secure has failed",
        "9993" => "An unknown error has occured",
        "9994" => "Request DB service has failed",
        "9995" => "Request payment service has failed",
        "9996" => "Request Qwik service has failed",
        "9997" => "Request user preferences has failed",
        "9998" => "Request store card has failed",
        "9999" => "Request to merchant backend has failed"
    );

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

    public function buildForm(&$form)
    {
//        CRM_Core_Error::debug_var('form', $form);
        $financialType = new CRM_Financial_DAO_FinancialType();
        $financialType->id = $form->_values['financial_type_id'];
        $financialType->find(TRUE);

        if ($financialType->is_deductible) {
            $form->assign('is_deductible', TRUE);
            $form->set('is_deductible', TRUE);
        }

        $userId = CRM_Core_Session::singleton()->get('userID');
//        $contactID = 0;
        $contactID = CRM_Utils_Request::retrieveValue('cid', 'Positive', 0);

        CRM_Core_Error::debug_var('userId', $userId);
        CRM_Core_Error::debug_var('contactID', $contactID);

//        $this->add('date', 'chargeNextDate', E::ts('Charge Next Date'),
//            CRM_Core_SelectValues::date(NULL, 'dmY'), TRUE);
//        $this->add('date', 'chargeOnDate', E::ts('Charge On Date'),
//            CRM_Core_SelectValues::date(NULL, 'dmY'), TRUE);
//        $this->add('date', 'paymentExpiry', E::ts('Payment Expiry'),
//            CRM_Core_SelectValues::date(NULL, 'dmY'), TRUE);
//        $request3DSarray = array(
//            "Y" => "do 3ds",
//            "F" => "force 3ds",
//            "N" => "no 3ds"
//        );
//        $request3DS = $this->add('select', 'request3DS',
//            E::ts('Use 3ds'),
//            $request3DSarray,
//            TRUE, ['class' => 'huge crm-select2']);
//        $request3DS->setSelected("Y");

        $nric = $form->addElement('text',
            'nric',
            ts('NRIC'),
            NULL
        );
        $form->addRule('nric', 'Please enter NRIC', 'required', null, 'client');
        $defaults['nric'] = '';
        $form->setDefaults($defaults);
        CRM_Core_Region::instance('contribution-main-not-you-block')->add(
            ['template' => 'CRM/Core/Payment/Card.tpl', 'weight' => +11]);
        return parent::buildForm($form);
    }


    public function doPayment(&$params, $component = 'contribute')
    {
        $this->_component = $component;
        $statuses = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id', 'validate');
//        CRM_Core_Error::debug_var('params', $params);
        // If we have a $0 amount, skip call to processor and set payment_status to Completed.
        // Conceivably a processor might override this - perhaps for setting up a token - but we don't
        // have an example of that at the moment.

        $propertyBag = \Civi\Payment\PropertyBag::cast($params);
        if ($propertyBag->getAmount() == 0) {
            $result['payment_status_id'] = array_search('Completed', $statuses);
            $result['payment_status'] = 'Completed';
            return $result;
        }
        $recurring = false;
//        $invoicePrefix = "";
//        $allowAccumulate = true;
//        $maxAccumulateAmount = 12;
//        $recurringInterval = 30;
//        $chargeNextDate = "";
//        $recurringCount = 12;

        if (!defined('CURLOPT_SSLCERT')) {
            throw new CRM_Core_Exception(ts('2c2p - Gateway requires curl with SSL support'));
        }

        if ($this->_paymentProcessor['billing_mode'] != 4) {
            throw new CRM_Core_Exception(ts('2c2p - Direct payment not implemented'));
        }

        $secretKey = $this->_paymentProcessor['password'];    //Get SecretKey from 2C2P PGW Dashboard

        $merchantID = $this->_paymentProcessor['user_name'];        //Get MerchantID when opening account with 2C2P
        $tokenUrl = $this->_paymentProcessor['url_site'] . '/payment/4.1/PaymentToken';    //Get url_site from 2C2P PGW Dashboard
        $invoiceNo = $params['invoiceID'];
        $description = $params['description'];
        $contact_id = null;
        $displayName = "";
        if (array_key_exists("contactID", $params)) {
            $contact_id = $params['contactID'];
            if ($contact_id) {
                $displayName = CRM_Contact_BAO_Contact::displayName($contact_id);
            }
        }
        if (array_key_exists("nric", $params)) {
            $nric = $params['nric'];
            CRM_Core_Error::debug_var('nric', $nric);
        }

        $email = "";
        if (array_key_exists("email-5", $params)) {
            $email = $params['email-5'];
        }
        if ($email == "") {
            if (array_key_exists("email", $params)) {
                $email = $params['email'];
            }
        }
        $amount = $params['amount'];
        $currency = 'SGD'; //works only with for a while
        $processor_name = $this->_paymentProcessor['name']; //Get processor_name from 2C2P PGW Dashboard
        $processor_id = $this->_paymentProcessor['id']; //Get processor_name from 2C2P PGW Dashboard
        $params['cid'] = $contact_id;
        $frontendReturnUrl = self::getReturnUrl($processor_id, $processor_name, $params, $component);
        if (CRM_Utils_Array::value('is_recur', $params) == TRUE) {

            if (CRM_Utils_Array::value('frequency_unit', $params) == 'day') {
                $frequencyUnit = "day";
            } else {
                throw new CRM_Core_Exception(ts('2c2p - recurring payments should be set in days'));
            }
            $recurring = true;
            $invoicePrefix = substr($params['invoiceID'], 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
            $allowAccumulate = true;
            $maxAccumulateAmount = 12;
            $recurringInterval = intval(CRM_Utils_Array::value('frequency_interval', $params, 0));
            $date = date('Y-m-d');
            $chargeNextDate = date('dmY', strtotime($date . ' +1 day'));
            $recurringCount = 12;
//            throw new CRM_Core_Exception(ts('2c2p - recurring payments not implemented'));
        }


        /*
         * 1) Create Token Request
         * 2) Get Payment encoded Token Response
         * 3) Get decoded Payment Response
         * */

//        $payload = array(
//            $secretKey,
//            $merchantID,
//            $invoiceNo,
//            $description,
//            $amount,
//            $currency,
//            $frontendReturnUrl,
//            $recurring,
//            $invoicePrefix,
//            $allowAccumulate,
//            $maxAccumulateAmount,
//            $recurringInterval,
//            $chargeNextDate,
//            $recurringCount
//        );
        /*
         * {
  "merchantID": "JT01",
  "invoiceNo": "238493467c6d716",
  "description": "Online Contribution: Help Support CiviCRM!",
  "amount": 10,
  "currencyCode": "SGD",
  "frontendReturnUrl": "http://localhost:3306/civicrm/payment/ipn?processor_name=Payment2c2p&md=contribute&qfKey=CRMContributeControllerContribution12eq3iuzhm0gogcosogcoo804wwg0kos0cs04skwcws8ws0kwk_2914&inId=118493467c6d716de08da42475ed2a4d&orderId=118493467c6d716d",
  "recurring": true,
  "invoicePrefix": "228493467c6d716",
  "allowAccumulate": true,
  "maxAccumulateAmount": 50,
  "recurringInterval": 1,
  "recurringCount": 5,
  "chargeNextDate": "02062022"
}
         */

        $payload = array(
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceNo,
            "description" => $description,
            "amount" => $amount,
            "currencyCode" => $currency,
            "frontendReturnUrl" => $frontendReturnUrl,
            "uiParams" => [
                "userInfo" => [
                    "email" => "",
                    "name" => ""
                ]
            ],
        );

        if ($displayName != "") {
            $payload['uiParams']['userInfo']['name'] = $displayName;
        }
        if ($email != "") {
            $payload['uiParams']['userInfo']['email'] = $email;
        }


        if ($recurring === TRUE) {
            $payload['recurring'] = TRUE;
            $payload['invoicePrefix'] = $invoicePrefix;
            $payload['allowAccumulate'] = $allowAccumulate;
            $payload['maxAccumulateAmount'] = $maxAccumulateAmount;
            $payload['recurringInterval'] = $recurringInterval;
            $payload['chargeNextDate'] = $chargeNextDate;
            $payload['recurringCount'] = $recurringCount;
        }

//        CRM_Core_Error::debug_var('paymentTokenPayload', $payload);

        $encodedTokenRequest = self::encodeJwtData($secretKey, $payload);

        $decodedTokenResponse = self::getDecodedTokenResponse($tokenUrl, $encodedTokenRequest, $secretKey);

//        CRM_Core_Error::debug_var('decodedTokenResponse', $decodedTokenResponse);
        $webPaymentUrl = $decodedTokenResponse['webPaymentUrl'];
        $paymentToken = $decodedTokenResponse['paymentToken'];

        //@todo create payment token
        //can be used later to get info about the payment


        if (!empty($invoiceNo)) {
            self::savePaymentToken($paymentToken, $invoiceNo, $contact_id, $processor_id);
        }
        // Print the tpl to redirect and send POST variables to Getaway.
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
    /**
     * @return bool
     * @throws CRM_Core_Exception
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
//        CRM_Core_Error::debug_var('paymentProcessor', $this->_paymentProcessor);

        $encodedPaymentResponse = $_REQUEST['paymentResponse'];
        $paymentResponse = $this->decodePayload64($encodedPaymentResponse);
////        CRM_Core_Error::debug_var('paymentResponse', $paymentResponse);

        require_once 'CRM/Utils/Array.php';
        $module = CRM_Utils_Array::value('md', $_GET);
        $invoiceId = CRM_Utils_Array::value('inId', $_GET);

        /** @var TYPE_NAME $this */
//        $thanxUrl = CRM_Utils_System::thanxUrl(
//            $this->_paymentProcessor['subject'], //$path
//            null, //$query
//            true, //$absolute
//            null, //$fragment
//            null, //$htmlize
//            true); //$frontend

        $thanxUrl = strval($this->_paymentProcessor['subject']);
        $failureUrl = strval($this->_paymentProcessor['signature']);
//                CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
//        if ($thanxUrl != "") {
//            CRM_Core_Error::debug_var('thanxUrl2', $thanxUrl);
//            $thanxUrl = CRM_Utils_System::url($thanxUrl, //$path
//            null, //$query
//            true, //$absolute
//            null, //$fragment
//            null, //$htmlize
//            true //$frontend
//            );
//            CRM_Core_Error::debug_var('thanxUrl3', $thanxUrl);
//        } else {
//            $thanxUrl = CRM_Utils_System::url();
//        }

        if ($thanxUrl == null || $thanxUrl == "") {
            $thanxUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }

        if ($failureUrl == null || $failureUrl == "") {
            $failureUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }

        switch ($module) {
            case 'contribute':

                if ($paymentResponse['respCode'] == 2000) {
                    $this->setContributionStatusRecieved($invoiceId, $thanxUrl, $failureUrl);
                } else {
                    $this->setContributionStatusRejected($invoiceId, $thanxUrl);
                }

                break;

            case 'event':


                if ($paymentResponse['respCode'] == 2000) { // success code
                    $participantId = CRM_Utils_Array::value('pid', $_GET);
                    $eventId = CRM_Utils_Array::value('eid', $_GET);
                    $query = "UPDATE civicrm_participant SET status_id = 1 where id =$participantId AND event_id=$eventId";
                    CRM_Core_DAO::executeQuery($query);

                    $this->setContributionStatusRecieved($invoiceId, $thanxUrl, $failureUrl);
                } else { // error code
                    $this->setContributionStatusRejected($invoiceId, $failureUrl);
                }

                break;

            default:
                CRM_Core_Error::statusBounce("Could not get module name from request thanxUrl", $thanxUrl);
        }

//        $thanxUrl = CRM_Utils_System::thanxUrl($this->_paymentProcessor['subject']);
//        CRM_Core_Error::debug_var('thanxUrl4', $thanxUrl);

        return TRUE;
    }


    public function getCurrentVersion()
    {
        return CRM_Payment2c2p_Config::PAYMENT_2C2P_VERSION;
    }


    public function getEncodedResponse($url, $payload)
    {
        $client = $this->getGuzzleClient();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';


        try {
            $response = $client->request('POST', $url, [
                'body' => $payload,
                'user_agent' => $user_agent,
                'headers' => [
                    'Accept' => 'text/plain',
                    'Content-Type' => 'application/*+json',
                    'X-VPS-Timeout' => '45',
                    'X-VPS-VIT-Integration-Product' => 'CiviCRM',
                    'X-VPS-Request-ID' => strval(rand(1, 1000000000)),
                ],
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            CRM_Core_Error::statusBounce(ts('2c2p Error:') . 'Request error', null, $e->getMessage());
        }
        return $response->getBody();
    }


    /**
     * @param $secretKey
     * @param $response
     * @return array
     */
    public function getDecodedResponse($secretKey, $response, $responseType = "payload")
    {

        $decoded = json_decode($response, true);
//        CRM_Core_Error::debug_var('decoded', $decoded);
        if (isset($decoded[$responseType])) {
            $payloadResponse = $decoded[$responseType];
            if ($responseType == 'payload') {
                $decoded_array = self::getDecodedPayloadJWT($secretKey, $payloadResponse);
            }
            if ($responseType == 'paymentResponse') {
                $decoded_array = self::getDecodedPayload64($payloadResponse);
            }
            return $decoded_array;
        } else {
            return $decoded;
        }

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
                '&orderId=' . $params['orderID'] .
                '&cid=' . $params['cid'];
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
        $paymentResponse = self::getDecodedPayload64($payloadResponse);
        return $paymentResponse;
    }

    /**
     * @param $invoiceId
     * @param $thanxUrl
     * @param $failureUrl
     */
    public function setContributionStatusRecieved($invoiceId, $thanxUrl, $failureUrl): void
    {


        //todo recieve only payment
        $trxnId = substr($invoiceId, 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);

        $contributionParams = [
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ];
        //this is for single contribution
        $contributionParams['invoice_id'] = $invoiceId;
        $contribution = civicrm_api3('Contribution', 'get', $contributionParams)['values'];
        $contribution = reset($contribution);
        $paymentToken = "";
        try {
            $payment_token = civicrm_api3('PaymentToken', 'getsingle', [
                'masked_account_number' => $invoiceId,
            ]);
            $paymentToken = $payment_token['token'];
        } catch (CiviCRM_API3_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Could not get payment token'));
        }
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
        $inquiryRequestData = self::encodeJwtData($secretkey, $payload);
        $encodedTokenResponse = self::getEncodedResponse($url, $inquiryRequestData);
        $decodedTokenResponse = self::getDecodedResponse($secretkey, $encodedTokenResponse);
//        CRM_Core_Error::debug_var('decodedTokenResponse', $decodedTokenResponse);
//        CRM_Core_Error::debug_var('failureUrl', $failureUrl);
//        CRM_Core_Error::debug_var('paymentProcessor', $this->_paymentProcessor);
        $resp_code = $decodedTokenResponse['respCode'];

        if ($resp_code != "0000") {
//            throw new CRM_Core_Exception(self::PAYMENT_RESPONCE[$resp_code]);
//            CRM_Core_Error::statusBounce(ts(self::PAYMENT_RESPONCE[$resp_code] . ts('2c2p Error:') . 'error', $url, 'error'));
            $query = "UPDATE civicrm_contribution SET contribution_status_id=4 where invoice_id='$invoiceId'";
            CRM_Core_DAO::executeQuery($query);
//            CRM_Core_Error::statusBounce(ts($_POST['respDesc']) . ts('2c2p Error:') . 'error', $url, 'error');
            CRM_Utils_System::redirect($failureUrl);
            return;
        }
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
//        $issuerBank = $decodedTokenResponse['issuerBank'];
//        $query = "UPDATE civicrm_contribution SET invoice_number='$issuerBank' where invoice_id='" . $invoiceId . "'";
//        CRM_Core_DAO::executeQuery($query);
//        $query = "UPDATE civicrm_contribution SET check_number='' where invoice_id='" . $invoiceId . "'";
//        CRM_Core_DAO::executeQuery($query);

        $contributionId = $contribution['id'];

        try {
            civicrm_api3('contribution', 'completetransaction',
                ['id' => $contributionId,
                    'trxn_id' => $trxnId,
                    'pan_truncation' => $cardNo,
                    'card_type_id' => $cardTypeId,
                    'payment_instrument_id' => $paymentInstrumentId,
                    'processor_id' => $this->_paymentProcessor['id']]);
        } catch (CiviCRM_API3_Exception $e) {
            if (!stristr($e->getMessage(), 'Contribution already completed')) {
                Civi::log()->debug("2c2p IPN Error Updating contribution: " . $e->getMessage());
                CRM_Utils_System::redirect($failureUrl);

            }
        }
        CRM_Utils_System::redirect($thanxUrl);

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


    public function encodeJwtData($secretKey, $payload): string
    {

        $jwt = JWT::encode($payload, $secretKey);

        $data = '{"payload":"' . $jwt . '"}';

        return $data;
    }

    /**
     * @param $secretKey string
     * @param $payloadResponse string
     * @return array
     */
    private static function getDecodedPayloadJWT($secretKey, $payloadResponse): array
    {
        $decodedPayload = JWT::decode($payloadResponse, $secretKey, array('HS256'));
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param $payloadResponse string
     * @return array
     */
    private static function getDecodedPayload64($payloadResponse): array
    {
        $decodedPayloadString = base64_decode($payloadResponse);
        $decodedPayload = json_decode($decodedPayloadString);
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param string $url
     * @param string $paymentTokenRequest
     * @param $secretKey
     * @return array
     */
    public function getDecodedTokenResponse(string $url, string $paymentTokenRequest, $secretKey): array
    {
//        CRM_Core_Error::debug_var('paymentTokenRequest', $paymentTokenRequest);
        $encodedTokenResponse = $this->getEncodedResponse($url, $paymentTokenRequest);
//        CRM_Core_Error::debug_var('encodedTokenResponse', $encodedTokenResponse);

        $decodedTokenResponse = self::getDecodedResponse($secretKey, $encodedTokenResponse);
        return $decodedTokenResponse;
    }

    /**
     * @param $paymentToken
     * @param $invoiceNo
     * @param $contact_id
     * @param $processor_id
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public function savePaymentToken($paymentToken, $invoiceNo, $contact_id, $processor_id): void
    {
        $payment_token_params = [
            'token' => $paymentToken,
            'masked_account_number' => $invoiceNo,
            'contact_id' => $contact_id,
            'payment_processor_id' => $processor_id,
        ];
        $token_result = civicrm_api3('PaymentToken', 'create', $payment_token_params);
        // Upon success, save the token table's id back in the recurring record.
        if (empty($token_result['id'])) {
            throw new CRM_Core_Exception(ts('2c2p - Could not save payment token'));
        }
    }
}

