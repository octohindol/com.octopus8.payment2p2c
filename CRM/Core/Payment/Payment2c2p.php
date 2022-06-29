<?php
/*
   +----------------------------------------------------------------------------+
   | 2c2p Core Payment Module for CiviCRM version 5                      |
   +----------------------------------------------------------------------------+
   | Licensed to CiviCRM under the Academic Free License version 3.0            |
   |                                                                            |
   | Written & Contributed by Octopus8                                          |
   +---------------------------------------------------------------------------+
  */

use CRM_Payment2c2p_ExtensionUtil as E;

use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\{
    Algorithm\KeyEncryption\RSAOAEP,
    Algorithm\ContentEncryption\A256GCM,
    Compression\CompressionMethodManager,
    Compression\Deflate,
    JWEBuilder,
    JWELoader,
    JWEDecrypter,
    JWETokenSupport,
    Serializer\CompactSerializer as EncryptionCompactSerializer,
    Serializer\JWESerializerManager
};

use \Jose\Component\Checker\AlgorithmChecker;
use \Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Signature\{
    Algorithm\PS256,
    JWSBuilder,
    JWSTokenSupport,
    JWSLoader,
    JWSVerifier,
    Serializer\JWSSerializerManager,
    Serializer\CompactSerializer as SignatureCompactSerializer};

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    CRM_Core_Error::debug_var('autoload1', $autoload);
    require_once $autoload;
} else {
    $autoload = E::path() . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        CRM_Core_Error::debug_var('autoload2', $autoload);
    }
}

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

    private const OPEN_CERTIFICATE_FILE_NAME = "sandbox-jwt-2c2p.demo.2.1(public).cer";
    private const CLOSED_CERTIFICATE_FILE_NAME = "private.pem";
    private const CLOSED_CERTIFICATE_PWD = "octopus8";


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

    /**
     * @param CRM_Core_Form $form
     * @return bool
     * @throws CRM_Core_Exception
     */
    public function buildForm(&$form)
    {
//        CRM_Core_Error::debug_var('form', $form);
        $financialType = new CRM_Financial_DAO_FinancialType();
        $financialType->id = $form->_values['financial_type_id'];
        $financialType->find(TRUE);
//        CRM_Core_Error::debug_var('financialType', $financialType);

        $is_deductible = FALSE;
        if ($financialType->is_deductible) {
            $is_deductible = TRUE;
        }

        $userId = CRM_Core_Session::singleton()->get('userID');
//        $contactID = 0;
        $contactID = CRM_Utils_Request::retrieveValue('cid', 'Positive', 0);


        $external_identifier = "";
        if ($userId) {
            $contact = new CRM_Contact_BAO_Contact();
            $contact->id = $userId;
            if ($contact->find(TRUE)) {
                $external_identifier = $contact->external_identifier;
            }
        }
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

        if ($is_deductible === TRUE) {
//            CRM_Core_Error::debug_var('is_r_d', $is_deductible);

            $nric = $form->addElement('text',
                'nric',
                ts('NRIC/FIN/UEN'),
                NULL
            );
            $form->addRule('nric', 'Please enter NRIC/FIN/UEN', 'required', null, 'client');
            if (($userId != null) //if there is user
                AND ($external_identifier != "") //if he has NRIC
            ) {
                IF ($contactID !== 0) { //if it's his profile
                    $nric->freeze();
                }
            }
            $defaults['nric'] = $external_identifier;
            $form->setDefaults($defaults);
            $form->assign('is_deductible', TRUE);
            $form->set('is_deductible', TRUE);
        }
        CRM_Core_Region::instance('contribution-main-not-you-block')->add(
            ['template' => 'CRM/Core/Payment/Card.tpl', 'weight' => +11]);

        return parent::buildForm($form);
    }

    public function doRefund(&$params)
    {
        CRM_Core_Error::debug_var('refund_params', $params);
//        return parent::doRefund($params);
    }

    /**
     * @param array|\Civi\Payment\PropertyBag $params
     * @param string $component
     * @return array|mixed
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public function doPayment(&$params, $component = 'contribute')
    {
        $this->_component = $component;
//        CRM_Core_Error::debug_var('params_before', $params);

        $propertyBag = \Civi\Payment\PropertyBag::cast($params);
//        CRM_Core_Error::debug_var('propertyBag', $propertyBag);
        if ($propertyBag->getAmount() == 0) {
            $statuses = CRM_Contribute_BAO_Contribution::buildOptions('contribution_status_id', 'validate');
            $result['payment_status_id'] = array_search('Completed', $statuses);
            $result['payment_status'] = 'Completed';
            return $result;
        }
        $recurring = false;


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
        $contact = new CRM_Contact_BAO_Contact();
        if (array_key_exists("contactID", $params)) {
            $contact_id = $params['contactID'];
            if ($contact_id) {
                $displayName = CRM_Contact_BAO_Contact::displayName($contact_id);
                $contact->id = $contact_id;
                if ($contact->find(TRUE)) {
                    $external_identifier = $contact->external_identifier;
                }
            }
        }

        if (array_key_exists("nric", $params)) {
            $nric = $params['nric'];
            try {
                $contactNRIC = civicrm_api3('Contact', 'getsingle', [
                    'return' => [
                        "id"],
                    'external_identifier' => $nric
                ]);

                if ($contactNRIC['is_error']) {
                    CRM_Core_Error::debug_var('error', 'no such NRIC');

                } else {
                    $contact_id = $contactNRIC['id'];
                    $params['contactID'] = $contact_id;
                }
            } catch (CiviCRM_API3_Exception $e) {
                if ($external_identifier == "" or $external_identifier == null) {
                    $contact->external_identifier = $nric;
                    $contact->update();
                } else {
                    CRM_Core_Error::debug_var('error', 'absent NRIC');
                }
            }


            $query = "UPDATE civicrm_contribution SET contact_id = $contact_id where invoice_id='$invoiceNo'";
            CRM_Core_DAO::executeQuery($query);

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
        $params['destination'] = 'front';
        $frontendReturnUrl = self::getReturnUrl($processor_id, $processor_name, $params, $component);
//        CRM_Core_Error::debug_var('frontendReturnUrl', $frontendReturnUrl);
        $params['destination'] = 'back';
        $backendReturnUrl = self::getReturnUrl($processor_id, $processor_name, $params, $component);
//        CRM_Core_Error::debug_var('backendReturnUrl', $backendReturnUrl);
        if (CRM_Utils_Array::value('is_recur', $params) == TRUE) {
//
//            if (CRM_Utils_Array::value('frequency_unit', $params) == 'day') {
//                $frequencyUnit = "day";
//            } else {
//                throw new CRM_Core_Exception(ts('2c2p - recurring payments should be set in days'));
//            }
            $recurring = true;
            $invoicePrefix = substr($params['invoiceID'], 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
            $allowAccumulate = true;
            $maxAccumulateAmount = 1;
            $recurringInterval = intval(CRM_Utils_Array::value('frequency_interval', $params, 0));
            $date = date('Y-m-d h:i:s');
            $chargeNextDate = date('dmY', strtotime($date . ' +1 day'));
            $recurringCount = 1;
//            throw new CRM_Core_Exception(ts('2c2p - recurring payments not implemented'));
        }


        $payload = array(
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceNo,
            "description" => $description,
            "amount" => $amount,
            "currencyCode" => $currency,
            "request3DS" => "F",
            "frontendReturnUrl" => $frontendReturnUrl,
            "backendReturnUrl" => $backendReturnUrl,
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
            $intervaldays = 1;
            if ($params['frequency_unit'] == 'week') {
                $intervaldays = 7;
            }
            if ($params['frequency_unit'] == 'month') {
                $intervaldays = 30;
            }
            $recurringCount = $params['installments'];
            $recurringInterval = $params['frequency_interval'] * $intervaldays;
            $chargeNextDate = $chargeNextDate = date('dmY', strtotime($date . " +$intervaldays day"));
            $payload['recurring'] = TRUE;
            $payload['invoicePrefix'] = $invoicePrefix;
            $payload['allowAccumulate'] = $allowAccumulate;
            $payload['maxAccumulateAmount'] = $maxAccumulateAmount;
            $payload['recurringInterval'] = $recurringInterval;
            $payload['recurringAmount'] = $amount;
            $payload['amount'] = 0;
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
     * @throws CiviCRM_API3_Exception
     */
    public
    function handlePaymentNotification()
    {
        require_once 'CRM/Utils/Array.php';
        $params = array_merge($_GET, $_REQUEST);
//        CRM_Core_Error::debug_var('getbackparams', $params);
        $destination = CRM_Utils_Array::value('destination', $params);
        $invoiceId = CRM_Utils_Array::value('inId', $_GET);
        if ($destination == "back") {
            //there is no payload in back notification
//            CRM_Core_Error::debug_var('backParams', $params);
            self::verifyContribution($invoiceId);
        }
        if ($destination == "front") {
//            CRM_Core_Error::debug_var('destination', $destination);
//            CRM_Core_Error::debug_var('invoiceId', $invoiceId);
            $failureUrl = self::getFailureUrlViaInvoiceID($invoiceId);
            $redirectUrl = $failureUrl;
            try {
                $contribution = self::getContributionByInvoiceId($invoiceId);
            } catch (CRM_Core_Exception $e) {
                CRM_Core_Error::debug_var('ErrorgetContributionByInvoiceId', $e->getMessage());
            }
//            CRM_Core_Error::debug_var('redirectUrl1', $redirectUrl);
            //            CRM_Core_Error::debug_var('contribution', $contribution);
            $encodedPaymentResponse = $params['paymentResponse'];
            $paymentResponse = self::getDecodedPayload64($encodedPaymentResponse);
//            CRM_Core_Error::debug_var('paymentResponse', $paymentResponse);
//            CRM_Core_Error::debug_var('redirectUrl2', $redirectUrl);
            $module = CRM_Utils_Array::value('md', $params);
//            CRM_Core_Error::debug_var('respCode', $paymentResponse['respCode']);
            switch ($module) {
                case 'contribute':
                    if ($paymentResponse['respCode'] == 2000) {
                        $redirectUrl = self::redirectByInvoiceId($invoiceId);
                    } else {
                        self::setContributionStatusCancelled($contribution);
                    }
                    break;

                case 'event':
                    if ($paymentResponse['respCode'] == 2000) { // success code
                        //@todo properly
                        $participantId = CRM_Utils_Array::value('pid', $_GET);
                        $eventId = CRM_Utils_Array::value('eid', $_GET);
                        $query = "UPDATE civicrm_participant SET status_id = 1 where id =$participantId AND event_id=$eventId";
                        CRM_Core_DAO::executeQuery($query);
                        $redirectUrl = self::redirectByInvoiceId($invoiceId);
                    } else { // error code
                        self::setContributionStatusCancelled($contribution);
                    }
                    break;

                default:
                    CRM_Core_Error::statusBounce("Could not get module name from request thanxUrl", $redirectUrl);
            }
//            CRM_Core_Error::debug_var('redirectUrl5', $redirectUrl);
            CRM_Utils_System::redirect($redirectUrl);

//        $thanxUrl = CRM_Utils_System::thanxUrl($this->_paymentProcessor['subject']);


            return TRUE;
        }
    }

    /**
     * Just returns redirect string for front
     * @param $invoiceId
     * @return string
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function redirectByInvoiceId($invoiceId): string
    {
//        CRM_Core_Error::debug_var('contribution', $contribution);
        $thanxUrl = self::getThanxUrlViaInvoiceID($invoiceId);
        $failureUrl = self::getFailureUrlViaInvoiceID($invoiceId);
//        CRM_Core_Error::debug_var('url', $url);
        $paymentInquery = self::getPaymentInquiryViaPaymentToken($invoiceId);
        if (array_key_exists('respCode', $paymentInquery)) {
            $resp_code = $paymentInquery['respCode'];
//            CRM_Core_Error::debug_var('paymentInquery', $paymentInquery);

            if ($resp_code === "0000") {
                return $thanxUrl;
            }
            if ($resp_code == "0003") {
                self::verifyContribution($invoiceId);
                return $failureUrl;
            }
            if ($resp_code == "0001") {
                self::verifyContribution($invoiceId);
                return $failureUrl;
            }
            if ($resp_code == "2001") {
                self::verifyContribution($invoiceId);
                return $thanxUrl;
            }
        }
        self::verifyContribution($invoiceId);
        return $failureUrl;
    }

    public
    function getCurrentVersion()
    {
        return CRM_Payment2c2p_Config::PAYMENT_2C2P_VERSION;
    }

    /**
     * @param $url
     * @param $payload
     * @return \Psr\Http\Message\StreamInterface
     * @throws CRM_Core_Exception
     */
    public static
    function getEncodedResponse($url, $payload)
    {
        $client = new \GuzzleHttp\Client();
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
            CRM_Core_Error::statusBounce('2c2p Error: Request error', null, $e->getMessage());
            throw new CRM_Core_Exception('2c2p Error: Request error: ' . $e->getMessage());
        }
        return $response->getBody();
    }


    /**
     * @param $secretKey
     * @param $response
     * @return array
     */
    public static
    function getDecodedResponse($secretKey, $response, $responseType = "payload")
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

    static
    function getReturnUrl($processor_id, $processor_name, $params, $component = 'contribute')
    {
        $returnUrl = "";
        if (!isset($params['orderID'])) {
            $params['orderID'] = substr($params['invoiceID'], 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
        }
        if ($component == 'contribute') {
            $returnUrl = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_id=$processor_id&processor_name=$processor_name&md=contribute&qfKey=" .
                $params['qfKey'] .
                '&inId=' . $params['invoiceID'] .
                '&orderId=' . $params['orderID'] .
                '&cid=' . $params['cid'] .
                '&destination=' . $params['destination'];


        } else if ($component == 'event') {
            $returnUrl = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_id=$processor_id&processor_name=$processor_name&md=event&qfKey=" .
                $params['qfKey'] .
                '&pid=' . $params['participantID'] .
                "&eid=" . $params['eventID'] .
                "&inId=" . $params['invoiceID'] .
                '&orderId=' . $params['orderID'] .
                '&destination=' . $params['destination'];

        }
        return $returnUrl;
    }

    /**
     * @param $webPaymentUrl
     */
    public
    function gotoPaymentGateway($webPaymentUrl): void
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
    public static
    function decodePayload64($payloadResponse): array
    {
        $paymentResponse = self::getDecodedPayload64($payloadResponse);
        return $paymentResponse;
    }

    /**
     * @param $invoiceId
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function verifyContribution($invoiceId): void
    {
        //todo recieve only payment
        $trxnId = substr($invoiceId, 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
        $contribution = self::getContributionByInvoiceId($invoiceId);
//        CRM_Core_Error::debug_var('contribution', $contribution);
        //try to catch info using PaymentToken
        $paymentInquery = self::getPaymentInquiryViaPaymentToken($invoiceId);
        CRM_Core_Error::debug_var('paymentInqueryinVerifyContribution', $paymentInquery);
        if ($contribution['contribution_recur_id'] != null) {
//        CRM_Core_Error::debug_var('url', $url);
            self::saveRecurringTokenValue($invoiceId, $paymentInquery);
        }
        if ("0000" == $paymentInquery['respCode']) {
            //@todo recurring contribution
            self::setContributionStatusCompleted($invoiceId, $paymentInquery, $contribution, $trxnId);
            return;
        }
        if ("0003" == $paymentInquery['respCode']) {
            self::setContributionStatusCancelled($contribution);
            return;
        }
        $decodedTokenResponse = self::getPaymentInquiryViaKeySignature($invoiceId);
        CRM_Core_Error::debug_var('paymentInqueryinVerifyContribution', $decodedTokenResponse);

        $resp_code = strval($decodedTokenResponse['respCode']);
//        CRM_Core_Error::debug_var('decodedTokenResponse', $decodedTokenResponse);

        //        CRM_Core_Error::debug_var('resp_code', $resp_code);
        if ($resp_code == "15") {
            self::setContributionStatusCancelled($contribution);
            return;
        }
        if ($resp_code == "16") {
            self::setContributionStatusCancelled($contribution);
            return;
        }
        $contribution_status = $decodedTokenResponse['status'];
//        CRM_Core_Error::debug_var('contribution_status', $contribution_status);
        if ($decodedTokenResponse['status'] == "S") {
            if ($decodedTokenResponse['respDesc'] != "No refund records") {
                $pending_status = CRM_Core_PseudoConstant::getKey(
                    'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
//                CRM_Core_Error::debug_var('contribution_status', $contribution['contribution_status_id']);
//                CRM_Core_Error::debug_var('Pending', $pending_status);
                if ($contribution['contribution_status_id'] == $pending_status) {
                    self::setContributionStatusCompleted($invoiceId, $decodedTokenResponse, $contribution, $trxnId);
                }
                self::setContributionStatusRefunded($contribution);
                return;
            }
        }
        if ($contribution_status !== "A") {

            if (in_array($contribution_status, [
                "V"])) {
                self::setContributionStatusCancelled($contribution);
                return;
            }
            if (in_array($contribution_status, [
                "AR",
                "FF",
                "IP",
                "ROE",
                "EX",
                "CTF"])) {
                $failed_status_id = CRM_Core_PseudoConstant::getKey(
                    'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');
                self::changeContributionStatusViaDB($invoiceId, $failed_status_id);
                return;
            }

            if ($contribution_status == "RF") {
                $pending_status = CRM_Core_PseudoConstant::getKey(
                    'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
                if ($contribution['contribution_status_id'] == $pending_status) {
                    self::setContributionStatusCompleted($invoiceId, $decodedTokenResponse, $contribution, $trxnId);
                }
                self::setContributionStatusRefunded($contribution);
            }

            if (in_array($contribution_status, ["AP", "RP", "VP"])) {
                $contribution_status_id =
                    CRM_Core_PseudoConstant::getKey(
                        'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
                self::changeContributionStatusViaDB($invoiceId, $contribution_status_id);
            }

            if ($contribution_status == "RS") {
                $contribution_status_id =
                    CRM_Core_PseudoConstant::getKey(
                        'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');
                self::changeContributionStatusViaDB($invoiceId, $contribution_status_id);
            }

//            CRM_Core_Error::debug_var('contribution_status_id', $contribution_status_id);
            return;
        }
        self::setContributionStatusCompleted($invoiceId, $decodedTokenResponse, $contribution, $trxnId);
    }

    /**
     * @param $contributionId
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function setCancelledContributionStatus($contributionId): void
    {
        //todo recieve only payment
        $contributionParams = [
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ];
        //this is for single contribution
        $contributionParams['id'] = $contributionId;
        $contribution = civicrm_api3('Contribution', 'get', $contributionParams)['values'];
        $contribution = reset($contribution);
//        CRM_Core_Error::debug_var('contribution', $contribution);
        $invoiceId = $contribution["invoice_id"];
//        CRM_Core_Error::debug_var('gotcontribution', date("Y-m-d H:i:s"));
        $decodedTokenResponse = self::getPaymentInquiryViaKeySignature($invoiceId);
//        CRM_Core_Error::debug_var('decodedTokenResponseStatus', $decodedTokenResponse['status']);
//        CRM_Core_Error::debug_var('gotdecodedTokenResponseStatus', date("Y-m-d H:i:s"));
        if ($decodedTokenResponse['status'] == "A") {
            $cancelledTokenResponse = self::setPaymentInquiryViaKeySignature($invoiceId, "V");
        }
        if ($decodedTokenResponse['status'] == "S") {
            $decodedTokenResponse = self::getPaymentInquiryViaKeySignature($invoiceId, "RS");
            if ($decodedTokenResponse['status'] == "S") {
                if ($decodedTokenResponse['respDesc'] == "No refund records") {
                    $amount = strval($decodedTokenResponse["amount"]);
                    $cancelledTokenResponse = self::setPaymentInquiryViaKeySignature($invoiceId, "R", $amount);
                }
                if ($decodedTokenResponse['respDesc'] != "No refund records") {
//                    CRM_Core_Error::debug_var('decodedRFTokenResponse', $decodedTokenResponse);
                    CRM_Core_Error::debug_var('gotdecodedRFTokenResponse', date("Y-m-d H:i:s"));
                }
            }
            if ($decodedTokenResponse['status'] != "S") {
//                CRM_Core_Error::debug_var('decodedNonSRFTokenResponse', $decodedTokenResponse);
                CRM_Core_Error::debug_var('gotdecodedNonSRFTokenResponse', date("Y-m-d H:i:s"));
            }
        }
        CRM_Core_Error::debug_var('setPaymentStatus', date("Y-m-d H:i:s"));
    }

    /**
     * @param $invoiceId
     * @param string $url
     * @throws CRM_Core_Exception
     */
    public static
    function setContributionStatusRejected($invoiceId): void
    {
        $failureUrl = self::getFailureUrlViaInvoiceID($invoiceId);
        $query = "UPDATE civicrm_contribution SET check_number='' where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
        $query = "UPDATE civicrm_contribution SET contribution_status_id=4 where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
        CRM_Core_Error::statusBounce(ts($_POST['respDesc']) . ts('2c2p Error:') . 'error', $failureUrl, 'error');
    }


    public static
    function encodeJwtData($secretKey, $payload): string
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
    private
    static function getDecodedPayloadJWT($secretKey, $payloadResponse): array
    {
        $decodedPayload = JWT::decode($payloadResponse, $secretKey, array('HS256'));
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param $payloadResponse string
     * @return array
     */
    private
    static function getDecodedPayload64($payloadResponse): array
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
     * @throws CRM_Core_Exception
     */
    public
    function getDecodedTokenResponse(string $url, string $paymentTokenRequest, $secretKey): array
    {
//        CRM_Core_Error::debug_var('paymentTokenRequest', $paymentTokenRequest);
        $encodedTokenResponse = self::getEncodedResponse($url, $paymentTokenRequest);
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
    public
    function savePaymentToken($paymentToken, $invoiceNo, $contact_id, $processor_id): void
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

    /**
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function checkPendingContribution()
    {

//        CRM_Core_Error::debug_var('request', $_REQUEST);
//        CRM_Core_Error::debug_var('post', $_POST);
//        CRM_Core_Error::debug_var('started_checking_pending', date("Y-m-d H:i:s"));
//        CRM_Core_Error::statusBounce(date("Y-m-d H:i:s"), null, '2c2p');
        $invoiceId = null;
        $invoiceId = CRM_Utils_Request::retrieveValue('invoiceId', 'String', null);
//        CRM_Core_Error::debug_var('invoiceId', $invoiceId);
        if ($invoiceId) {
//            try {
//                CRM_Core_Error::debug_var('before', $invoiceId);
            self::verifyContribution($invoiceId);
//            CRM_Core_Error::debug_var('after', $invoiceId);
//            } catch (Exception $e) {
//                CRM_Core_Error::debug_var('error', $e->getMessage());
//                CRM_Core_Error::statusBounce("Operation Failed", null, '2c2p');
//            }
        } else {
            CRM_Core_Error::statusBounce("No Invoice ID", null, '2c2p');
        }
//        CRM_Core_Error::debug_var('ended_checking_pending', date("Y-m-d H:i:s"));
    }

    /**
     * @param $response_body_contents
     * @param $path_to_2c2p_certificate
     * @param $path_to_merchant_pem
     * @param $merchant_password
     * @param $merchant_secret
     * @throws CRM_Core_Exception
     */

    public static function getPaymentFrom2c2pResponse($response_body_contents,
                                                      $path_to_2c2p_certificate,
                                                      $path_to_merchant_pem,
                                                      $merchant_password,
                                                      $merchant_secret): array
    {
        //credentials part
        $receiverPublicCertPath = $path_to_2c2p_certificate;
        try {
            $receiverPublicCertKey = JWKFactory::createFromCertificateFile(
                $receiverPublicCertPath, // The filename
            );
        } catch (Exception $e) {
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());
        }

        $senderPrivateKeyPath = $path_to_merchant_pem;

        $senderPrivateKeyPassword = $merchant_password;
        try {
            $jw_signature_key = JWKFactory::createFromKeyFile(
                $senderPrivateKeyPath,
                $senderPrivateKeyPassword
            );
        } catch (Exception $e) {
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

        }

        $secretKey = $merchant_secret;    //Get SecretKey from 2C2P PGW Dashboard
//end credentials part

        $response_body = $response_body_contents;

        $signatureAlgorithmManager = new AlgorithmManager([
            new PS256(),
        ]);


        // We instantiate our JWS Verifier.
        $jwsVerifier = new JWSVerifier(
            $signatureAlgorithmManager
        );

        $signature_serializer = new SignatureCompactSerializer(); // The serializer

        $signatureSerializerManager = new JWSSerializerManager([
            $signature_serializer,
        ]);

        $headerSignatureCheckerManager = new HeaderCheckerManager(
            [
                new AlgorithmChecker(['PS256']),
            ],
            [
                new JWSTokenSupport(), // Adds JWS token type support
            ]
        );

        $jw_signed_response = $signature_serializer->unserialize($response_body);
        try {
            $isVerified = $jwsVerifier->verifyWithKey($jw_signed_response, $receiverPublicCertKey, 0);
        } catch (Exception $e) {
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

        }
        if ($isVerified) {
            $jwsLoader = new JWSLoader(
                $signatureSerializerManager,
                $jwsVerifier,
                $headerSignatureCheckerManager
            );
            try {
                $jwsigned_response_loaded = $jwsLoader->loadAndVerifyWithKey((string)$response_body, $receiverPublicCertKey, $signature, null);
            } catch (Exception $e) {
                throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

            }
            $encrypted_serialized_response = $jwsigned_response_loaded->getPayload();
        } else {
            throw new CRM_Core_Exception(ts("2c2p Error: Not Verified "));
        }

        $encryption_serializer = new EncryptionCompactSerializer(); // The serializer

        try {

            $encryptionSerializerManager = new JWESerializerManager([
                $encryption_serializer,
            ]);

            $jw_encrypted_response = $encryption_serializer->unserialize($encrypted_serialized_response);

            // The key encryption algorithm manager with the A256KW algorithm.
            $keyEncryptionAlgorithmManager = new AlgorithmManager([
                new RSAOAEP(),
            ]);

// The content encryption algorithm manager with the A256CBC-HS256 algorithm.
            $contentEncryptionAlgorithmManager = new AlgorithmManager([
                new A256GCM(),
            ]);

// The compression method manager with the DEF (Deflate) method.
            $compressionMethodManager = new CompressionMethodManager([
                new Deflate(),
            ]);

// We instantiate our JWE Decrypter.
            $jweDecrypter = new JWEDecrypter(
                $keyEncryptionAlgorithmManager,
                $contentEncryptionAlgorithmManager,
                $compressionMethodManager
            );
            $headerCheckerManagerE = new HeaderCheckerManager(
                [
                    new AlgorithmChecker(['RSA-OAEP']),
                ],
                [
                    new JWETokenSupport(), // Adds JWS token type support
                ]
            );

            $success = $jweDecrypter->decryptUsingKey($jw_encrypted_response, $jw_signature_key, 0);
            CRM_Core_Error::debug_var('success_within_getPaymentFrom2c2pResponse: ', strval($success));

            $jweLoader = new JWELoader(
                $encryptionSerializerManager,
                $jweDecrypter,
                $headerCheckerManagerE
            );
            $jw_encrypted_response = $jweLoader->loadAndDecryptWithKey($encrypted_serialized_response,
                $jw_signature_key,
                $recipient);
            $unencrypted_payload = $jw_encrypted_response->getPayload();


        } catch (Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - JWE Error'));
        }

        $answer = self::unencryptPaymentAnswer($unencrypted_payload, $secretKey);
//            CRM_Core_Error::debug_var('answer_within_getPaymentFrom2c2pResponse', $answer);
        return $answer;
    }

    /**
     * @param array $payment_inquiry
     * @return \Psr\Http\Message\ResponseInterface
     * @throws CRM_Core_Exception
     */
    public static function getPaymentResponseViaKeySignature(
        array $payment_inquiry): \Psr\Http\Message\ResponseInterface
    {

        $invoiceId = $payment_inquiry['invoiceNo'];

        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $receiverPublicCertPath = $paymentProcessor->getOpenCertificatePath();
        $senderPrivateKeyPath = $paymentProcessor->getClosedCertificatePath();
        $senderPrivateKeyPassword = $paymentProcessor->getClosedCertificatePwd(); //private key password
        $payment_processor_array = $paymentProcessor->_paymentProcessor;
        $merchantID = $payment_processor_array['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $payment_processor_array['password'];    //Get SecretKey from 2C2P PGW Dashboard

        $url = $payment_processor_array['url_api'];

        try {
            $keyEncryptionAlgorithmManager = new AlgorithmManager([
                new RSAOAEP(),
            ]);

        } catch (CRM_Core_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid keyEncryptionAlgorithmManager') . $e->getMessage());
        }


// The content encryption algorithm manager with the A256CBC-HS256 algorithm.
        try {
            $contentEncryptionAlgorithmManager = new AlgorithmManager([
                new A256GCM(),
            ]);
        } catch (CRM_Core_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid contentEncryptionAlgorithmManager') . $e->getMessage());
        }
//        CRM_Core_Error::debug_var('contentEncryptionAlgorithmManager', '2');

// The compression method manager with the DEF (Deflate) method.
        $compressionMethodManager = new CompressionMethodManager([
            new Deflate(),
        ]);

// We instantiate our JWE Builder.
        $jwencryptedBuilder = new JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );


// Our key.
        $receiverPublicCertKey = JWKFactory::createFromCertificateFile(
            $receiverPublicCertPath, // The filename
        );


        $stringToHashOne = "";
        $stringXML = "<" . $payment_inquiry["request_type"] . ">";
        foreach ($payment_inquiry as $key => $value) {
            if ($key != "request_type") {
                $stringToHashOne = $stringToHashOne . $value;
                $stringXML = $stringXML . "\n<" . $key . ">" . $value . "</" . $key . ">";
            }
        }
        $hashone = strtoupper(hash_hmac('sha1', $stringToHashOne, $secretKey, false));    //Compute hash value
        $stringXML = $stringXML . "<hashValue>$hashone</hashValue>";
        $stringXML = $stringXML . "\n</" . $payment_inquiry["request_type"] . ">";
        $xml = $stringXML;

        CRM_Core_Error::debug_var('xml', $xml);

        $jw_encrypted_response = $jwencryptedBuilder
            ->create()// We want to create a new JWE
            ->withPayload($xml)// We set the payload
            ->withSharedProtectedHeader([
                'alg' => 'RSA-OAEP', // Key Encryption Algorithm
                'enc' => 'A256GCM',  // Content Encryption Algorithm
                'typ' => 'JWT'
            ])
            ->addRecipient($receiverPublicCertKey)// We add a recipient (a shared key or public key).
            ->build();

        $encryption_serializer = new EncryptionCompactSerializer(); // The serializer
        $jwe_request_payload = $encryption_serializer->serialize($jw_encrypted_response, 0); // We serialize the recipient at index 0 (we only have one recipient).

// The algorithm manager with the HS256 algorithm.
        $signatureAlgorithmManager = new AlgorithmManager([
            new PS256(),
        ]);

// Our key.
//echo "jwk:\n";
        $jw_signature_key = JWKFactory::createFromKeyFile(
            $senderPrivateKeyPath,
            $senderPrivateKeyPassword
        );


        $jwsBuilder = new JWSBuilder($signatureAlgorithmManager);

        $jw_signed_request = $jwsBuilder
            ->create()// We want to create a new JWS
            ->withPayload($jwe_request_payload)// We set the payload
            ->addSignature($jw_signature_key, [
                'alg' => 'PS256',
                'typ' => 'JWT'
            ])// We add a signature with a simple protected header
            ->build();


        $signature_serializer = new \Jose\Component\Signature\Serializer\CompactSerializer(); // The serializer

        $jw_signed_payload = $signature_serializer->serialize($jw_signed_request, 0); // We serialize the signature at index 0 (we only have one signature).

        $client = new GuzzleHttp\Client();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';
//        CRM_Core_Error::debug_var('signed_payload', $jw_signed_payload);
        try {
            $response = $client->request('POST', $url, [
                'body' => $jw_signed_payload,
                'user_agent' => $user_agent,
                'headers' => [
                    'Accept' => 'text/plain',
                    'Content-Type' => 'application/*+json',
                    'X-VPS-Timeout' => '45',

                ],
            ]);
//            CRM_Core_Error::debug_var('response', $response->getBody()->getContents());
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            CRM_Core_Error::debug_var('guzzle_error', "\n2:\n" . $e->getMessage());
            CRM_Core_Error::debug_var('guzzle_error_xml', $stringXML);
            throw new CRM_Core_Exception("2c2p Error: " . $e->getMessage());

        }
        return $response;
    }

    /**
     * @return string
     */
    private function getOpenCertificatePath()
    {
        $path = E::path();
        $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . self::OPEN_CERTIFICATE_FILE_NAME;
        return $path_to_2c2p_certificate;
    }

    /**
     * @return string
     */
    private function getClosedCertificatePath()
    {
        $path = E::path();
        $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . self::CLOSED_CERTIFICATE_FILE_NAME;
        return $path_to_2c2p_certificate;
    }

    /**
     * @return string
     */
    private function getClosedCertificatePwd()
    {
        return self::CLOSED_CERTIFICATE_PWD;
    }


    /**
     * @param string|null $unencrypted_payload
     * @param $secretKey
     * @return array
     * @throws CRM_Core_Exception
     */
    protected static function unencryptRecurringPaymentAnswer(?string $unencrypted_payload, $secretKey): array
    {
        $resXml = simplexml_load_string($unencrypted_payload);
        $array = json_decode(json_encode((array)simplexml_load_string($resXml)), $resXml);
//        print_r($array);
        $res_version = (string)$resXml->version;
        $res_timeStamp = (string)$resXml->timeStamp;
        $res_respCode = (string)$resXml->respCode;
        $res_respReason = (string)$resXml->respReason;
        $res_recurringUniqueID = (string)$resXml->recurringUniqueID;
        $res_recurringStatus = (string)$resXml->recurringStatus;
        $res_invoicePrefix = (string)$resXml->invoicePrefix;
        $res_currency = (int)$resXml->currency;
        $res_amount = (int)$resXml->amount;
        $res_maskedCardNo = (string)$resXml->maskedCardNo;
        $res_allowAccumulate = (boolean)$resXml->allowAccumulate;
        $res_maxAccumulateAmount = (int)$resXml->maxAccumulateAmount;
        $res_recurringInterval = (int)$resXml->recurringInterval;
        $res_recurringCount = (int)$resXml->recurringCount;
        $res_currentCount = (int)$resXml->currentCount;
        $res_chargeNextDate = (string)$resXml->chargeNextDate;


//Compute response hash
        $res_stringToHash =
            $res_version
            . $res_respCode
            . $res_recurringUniqueID
            . $res_recurringStatus
            . $res_invoicePrefix
            . $res_currency
            . $res_amount
            . $res_maskedCardNo
            . $res_allowAccumulate
            . $res_maxAccumulateAmount
            . $res_recurringInterval
            . $res_recurringCount
            . $res_currentCount
            . $res_chargeNextDate;

        $res_responseHash = strtoupper(hash_hmac('sha1', $res_stringToHash, $secretKey, false));    //Compute hash value

        if (strtolower($resXml->hashValue) != strtolower($res_responseHash)) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid Response Hash'));
        }

        $answer = [
            'version' => $res_version,
            'timeStamp' => $res_timeStamp,
            'respCode' => $res_respCode,
            'respReason' => $res_respReason,
            'recurringUniqueID' => $res_recurringUniqueID,
            'recurringStatus' => $res_recurringStatus,
            'invoicePrefix' => $res_invoicePrefix,
            'currency' => $res_currency,
            'amount' => $res_amount,
            'maskedCardNo' => $res_maskedCardNo,
            'allowAccumulate' => $res_allowAccumulate,
            'maxAccumulateAmount' => $res_maxAccumulateAmount,
            'recurringInterval' => $res_recurringInterval,
            'recurringCount' => $res_recurringCount,
            'currentCount' => $res_currentCount,
            'chargeNextDate' => $res_chargeNextDate,
        ];
        return $answer;
    }

    /**
     * @param $invoiceId
     * @param array $decodedTokenResponse
     */
    private static function saveRecurringTokenValue($invoiceId, array $decodedTokenResponse): void
    {
        $tranRef = $decodedTokenResponse['tranRef'];
        $recurringUniqueID = $decodedTokenResponse['recurringUniqueID'];
        $referenceNo = $decodedTokenResponse['referenceNo'];

        $query = "UPDATE civicrm_payment_token SET 
                                billing_first_name='$recurringUniqueID',
                                 billing_middle_name='$tranRef',
                                 billing_last_name='$referenceNo'
                      where masked_account_number='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
    }

    /**
     * @param $invoiceId
     * @param array $decodedTokenResponse
     * @param $contribution
     * @param $trxnId
     * @throws CRM_Core_Exception
     */
    private static function setContributionStatusCompleted($invoiceId, array $decodedTokenResponse, $contribution, $trxnId): void
    {
        if (key_exists('cardNo', $decodedTokenResponse)) {
            $cardNo = substr($decodedTokenResponse['cardNo'], -4);
        }
        if (key_exists('maskedPan', $decodedTokenResponse)) {
            $cardNo = substr($decodedTokenResponse['maskedPan'], -4);
        }
        if (key_exists('channelCode', $decodedTokenResponse)) {
            $channelCode = $decodedTokenResponse['channelCode'];
        }
        if (key_exists('processBy', $decodedTokenResponse)) {
            $channelCode = $decodedTokenResponse['processBy'];
        }
        $cardTypeId = 2;
        $paymentInstrumentId = null;
        $paymentInstrumentId = 1;
        if ($channelCode == 'VI') {
            $cardTypeId = 1;
        }

//        CRM_Core_Error::debug_var('contribution_status_id', "SUPER");
        $contributionId = $contribution['id'];
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $failed_status_id = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');;
        $cancelled_status_id = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Cancelled');;
        $pending_status_id = CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
        if (in_array($contribution['contribution_status_id'], [$failed_status_id, $cancelled_status_id])) {
            self::changeContributionStatusViaDB($invoiceId, $pending_status_id);
            //to give possibility to make it fulfiled
        }
        try {
            civicrm_api3('contribution', 'completetransaction',
                ['id' => $contributionId,
                    'trxn_id' => $trxnId,
                    'pan_truncation' => $cardNo,
                    'card_type_id' => $cardTypeId,
                    'cancel_date' => "",
                    'cancel_reason' => "",
                    'is_email_receipt' => false,
                    'payment_instrument_id' => $paymentInstrumentId,
                    'processor_id' => $paymentProcessorId]);
        } catch (CiviCRM_API3_Exception $e) {
            if (!stristr($e->getMessage(), 'Contribution already completed')) {
                Civi::log()->debug("2c2p IPN Error Updating contribution: " . $e->getMessage());
            }
//            throw $e;
        }
    }

    /**
     * @param $invoiceId
     * @return array|int|mixed
     * @throws CiviCRM_API3_Exception
     */
    private static function getContributionByInvoiceId($invoiceId)
    {
        $contributionParams = [
            'invoice_id' => $invoiceId,
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ];
//        CRM_Core_Error::debug_var('invoiceId', $invoiceId);
        $contribution = civicrm_api3('Contribution', 'get', $contributionParams);
        if (array_key_exists('values', $contribution)) {
            $contribution = $contribution['values'];
            $contribution = reset($contribution);
            return $contribution;
        } else {
            new CRM_Core_Exception(ts('2c2p - Unvalid Contribution'));
        }
    }

    /**
     * @param $contribution
     * @return bool|int|string|null
     * @throws CiviCRM_API3_Exception
     */
    private static function setContributionStatusCancelled($contribution): void
    {
        $contribution_status_id
            =
            CRM_Core_PseudoConstant::getKey(
                'CRM_Contribute_BAO_Contribution',
                'contribution_status_id',
                'Cancelled');

        if ($contribution['contribution_status_id'] == $contribution_status_id) {
            return;
        }

        $contribution_change = array(
            'id' => $contribution['id'],
//                    'cancel_reason' => $params['cancel_reason'] ?? NULL,
            'contribution_status_id' => $contribution_status_id,
        );
        if ((!array_key_exists('cancel_date', $contribution))
            || $contribution['cancel_date'] == null
            || $contribution['cancel_date'] == "") {
            $contribution_change['cancel_date'] = date('YmdHis');
        }

        civicrm_api3('Contribution', 'create', $contribution_change);

    }

    /**
     * @param $contribution
     * @throws CiviCRM_API3_Exception
     */
    private static function setContributionStatusRefunded($contribution): void
    {
        $contribution_status_id =
            CRM_Core_PseudoConstant::getKey(
                'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded');
        $contribution_change = array(
            'id' => $contribution['id'],
//                    'cancel_reason' => $params['cancel_reason'] ?? NULL,
            'contribution_status_id' => $contribution_status_id,
        );
        if (!array_key_exists('cancel_date', $contribution) || $contribution['cancel_date'] == null || $contribution['cancel_date'] == "") {
            $contribution_change['cancel_date'] = date('YmdHis');
        }
        civicrm_api3('Contribution', 'create', $contribution_change);
    }

    /**
     * @param $invoiceId
     * @param $contribution_status_id
     */
    protected static function changeContributionStatusViaDB($invoiceId, $contribution_status_id): void
    {
        $query = "UPDATE civicrm_contribution SET 
                                contribution_status_id=$contribution_status_id 
                      where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
    }

    /**
     * @param string|null $unencrypted_payload
     * @param $secretKey
     * @return array
     * @throws CRM_Core_Exception
     */
    protected static function unencryptPaymentAnswer(?string $unencrypted_payload, $secretKey): array
    {

        $answer = json_decode(json_encode((array)simplexml_load_string($unencrypted_payload)), $unencrypted_payload);
        $answer = array_map(function ($o) {
            if (is_array($o)) {
                if (sizeof($o) == 0) {
                    return "";
                } else {
                    return array_map("strval", $o);
                }
            }
            return (string)$o;
        }, $answer);
        return $answer;
    }

    /**
     * @param $invoiceId
     * @return mixed
     * @throws CRM_Core_Exception
     */
    protected static function getPaymentProcessorIdViaInvoiceID($invoiceId)
    {
        try {
            $payment_token = self::getPaymentTokenViaInvoiceID($invoiceId);
            $paymentProcessorId = $payment_token['payment_processor_id'];
            return $paymentProcessorId;

        } catch (CRM_Core_Exception $e) {
            $pP = self::getPaymentProcessorViaProcessorName('Payment2c2p');
            $pp = $pP->_paymentProcessor;
            return $pp['id'];
        }
    }

    /**
     * @param $invoiceID
     * @param $paymentProcessorId
     * @return CRM_Financial_DAO_PaymentProcessor
     * @throws CRM_Core_Exception
     */
    protected static function getPaymentProcessorViaProcessorID($paymentProcessorId): CRM_Core_Payment_Payment2c2p
    {

        $paymentProcessorInfo = civicrm_api3('PaymentProcessor', 'get', [
            'id' => $paymentProcessorId,
            'sequential' => 1,
        ]);
        $paymentProcessorInfo = $paymentProcessorInfo['values'];
        if (count($paymentProcessorInfo) <= 0) {
            return NULL;
        }
        $paymentProcessorInfo = $paymentProcessorInfo[0];
        $paymentProcessor = new CRM_Core_Payment_Payment2c2p(($paymentProcessorInfo['is_test']) ? 'test' : 'live', $paymentProcessorInfo);
        return $paymentProcessor;
    }

    protected static function getPaymentProcessorViaProcessorName($paymentProcessorName): CRM_Core_Payment_Payment2c2p
    {

        $paymentProcessorInfo = civicrm_api3('PaymentProcessor', 'get', [
            'name' => $paymentProcessorName,
            'sequential' => 1,
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ]);
        $paymentProcessorInfo = $paymentProcessorInfo['values'];
        if (count($paymentProcessorInfo) <= 0) {
            return NULL;
        }
        $paymentProcessorInfo = $paymentProcessorInfo[0];
        $paymentProcessor = new CRM_Core_Payment_Payment2c2p(($paymentProcessorInfo['is_test']) ? 'test' : 'live', $paymentProcessorInfo);
        return $paymentProcessor;
    }

    /**
     * @param $invoiceID
     * @return array|int
     * @throws CRM_Core_Exception
     */
    protected static function getPaymentTokenViaInvoiceID($invoiceID)
    {
        try {
            $payment_token = civicrm_api3('PaymentToken', 'getsingle', [
                'masked_account_number' => $invoiceID,
            ]);
        } catch (CiviCRM_API3_Exception $e) {
            CRM_Core_Error::debug_var('API error', $e->getMessage() . "\nInvoiceID: $invoiceID\n");
//            $query = "UPDATE civicrm_contribution SET contribution_status_id=4 where invoice_id='$invoiceID'";
//            CRM_Core_DAO::executeQuery($query);
            throw new CRM_Core_Exception(ts('2c2p - Could not find payment token') . "\nInvoiceID: $invoiceID\n");
        }
        return $payment_token;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    protected static function getPaymentInquiryViaPaymentToken($invoiceID): array
    {
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceID);
        $paymentToken = $payment_token['token'];
        $paymentProcessorId = $payment_token['payment_processor_id'];
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $payment_processor_array = $paymentProcessor->_paymentProcessor;
        $url = $payment_processor_array['url_site'] . '/payment/4.1/paymentInquiry';
        $secretkey = $payment_processor_array['password'];
        $merchantID = $payment_processor_array['user_name'];
        $payload = array(
            "paymentToken" => $paymentToken,
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceID,
            "locale" => "en");
        $inquiryRequestData = self::encodeJwtData($secretkey, $payload);
        $encodedTokenResponse = self::getEncodedResponse($url, $inquiryRequestData);
        $decodedTokenResponse = self::getDecodedResponse($secretkey, $encodedTokenResponse);
        return $decodedTokenResponse;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    public static function getPaymentInquiryViaKeySignature(
        $invoiceID,
        $processType = "I",
        $request_type = "PaymentProcessRequest",
        $version = "3.8",
        $recurringUniqueID = ""
    ): array
    {

        $paymentProcessor = self::getPaymentProcessorViaProcessorName('Payment2c2p');
        $payment_processor = $paymentProcessor->_paymentProcessor;
        $merchant_id = $payment_processor['user_name'];
        $merchant_secret = $payment_processor['password'];
        $now = DateTime::createFromFormat('U.u', microtime(true));
        $date = date('Y-m-d h:i:s');
        $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
        if ($request_type == "RecurringMaintenanceRequest") {
            $version = "2.1";
        }

        $payment_inquiry = array(
            'version' => $version,
            'processType' => $processType,
            'invoiceNo' => $invoiceID,
            'timeStamp' => $time_stamp,
            'merchantID' => $merchant_id,
            'actionAmount' => "",
            'request_type' => $request_type,

        );
        if ($recurringUniqueID != "") {
            $payment_inquiry["recurringUniqueID"] = $recurringUniqueID;
        }
//        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $response = self::getPaymentResponseViaKeySignature(
            $payment_inquiry,
            );
        $response_body_contents = $response->getBody()->getContents();

        CRM_Core_Error::debug_var('response_body_contents_before', $response_body_contents);
        $path_to_2c2p_certificate = $paymentProcessor->getOpenCertificatePath();
        $path_to_merchant_pem = $paymentProcessor->getClosedCertificatePath();
        $merchant_password = $paymentProcessor->getClosedCertificatePwd();
        $answer =
            CRM_Core_Payment_Payment2c2p::getPaymentFrom2c2pResponse($response_body_contents,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);
        CRM_Core_Error::debug_var('answer', $answer);

        return $answer;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    public static function setPaymentInquiryViaKeySignature($invoiceID, $status = "", $amount = ""): array
    {
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceID);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);

        $payment_processor = $paymentProcessor->_paymentProcessor;
        $merchant_id = $payment_processor['user_name'];
        $merchant_secret = $payment_processor['password'];

        $date = date('Y-m-d h:i:s');
        $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
        if ($status === "V") {
            $payment_inquiry = array(
                'version' => "3.8",
                'processType' => "V",
                'invoiceNo' => $invoiceID,
                'timeStamp' => $time_stamp,
                'merchantID' => $merchant_id,
                'actionAmount' => "",
                'request_type' => "PaymentProcessRequest"
            );
        }
        if ($status === "R") {
            $payment_inquiry = array(
                'version' => "3.8",
                'processType' => "R",
                'invoiceNo' => $invoiceID,
                'timeStamp' => $time_stamp,
                'merchantID' => $merchant_id,
                'actionAmount' => $amount,
                'request_type' => "PaymentProcessRequest"
            );
        }
//        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $response = self::getPaymentResponseViaKeySignature(
            $payment_inquiry,
            );
        $response_body_contents = $response->getBody()->getContents();
//        CRM_Core_Error::debug_var('response_body_contents', $response_body_contents);

        $path_to_2c2p_certificate = $paymentProcessor->getOpenCertificatePath();
        $path_to_merchant_pem = $paymentProcessor->getClosedCertificatePath();
        $merchant_password = $paymentProcessor->getClosedCertificatePwd();
        $answer =
            CRM_Core_Payment_Payment2c2p::getPaymentFrom2c2pResponse($response_body_contents,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);
//        CRM_Core_Error::debug_var('answersetPaymentInquiryViaKeySignature', $answer);

        return $answer;
    }

    /**
     * @param $paymentProcessor
     * @return string
     */
    protected static function getFailureUrl($paymentProcessor): string
    {
        $paymentProcessor = (array)$paymentProcessor;
        $failureUrl = strval($paymentProcessor['signature']);
        if ($failureUrl == null || $failureUrl == "") {
            $failureUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $failureUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     */
    private static function getThanxUrl($paymentProcessor): string
    {
        $paymentProcessor = (array)$paymentProcessor;
        $thanxUrl = strval($paymentProcessor['subject']);
        if ($thanxUrl == null || $thanxUrl == "") {
            $thanxUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $thanxUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     * @throws CRM_Core_Exception
     */
    private static function getThanxUrlViaInvoiceID($invoiceID): string
    {
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceID);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $payment_processor_array = $paymentProcessor->_paymentProcessor;
        $thanxUrl = strval($payment_processor_array['subject']);
//        CRM_Core_Error::debug_var('$payment_processor_array_thanx', $payment_processor_array);
        if ($thanxUrl == null || $thanxUrl == "") {
            $thanxUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $thanxUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     * @throws CRM_Core_Exception
     */
    private static function getFailureUrlViaInvoiceID($invoiceID): string
    {

        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceID);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);

        $payment_processor_array = $paymentProcessor->_paymentProcessor;
//        CRM_Core_Error::debug_var('$payment_processor_array_failure', $payment_processor_array);
        $failureUrl = strval($payment_processor_array['signature']);
        if ($failureUrl == null || $failureUrl == "") {
            $failureUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $failureUrl;
    }


}

