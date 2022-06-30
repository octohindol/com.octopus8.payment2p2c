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

    protected $_cid; // cid is contact

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
            $payload['amount'] = $amount;
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

    /**
     * @todo
     * Function handles Recurring Payments cron job.
     *
     * @return bool
     */
    function handlePaymentCron()
    {
        return true;
//        return CRM_Payment2c2p_Utils::process_recurring_payments($this->_paymentProcessor, $this);
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
     * this function handles notification from the payment server
     */
    public function handlePaymentNotification()
    {
        require_once 'CRM/Utils/Array.php';
        $params = array_merge($_GET, $_REQUEST);
//        CRM_Core_Error::debug_var('getbackparams', $params);
        $destination = CRM_Utils_Array::value('destination', $params);
        $invoiceId = CRM_Utils_Array::value('inId', $_GET);
        if ($destination == "back") {
            //there is no payload in back notification
//            CRM_Core_Error::debug_var('backParams', $params);
            CRM_Payment2c2p_Utils::verifyContribution($invoiceId);
        }
        if ($destination == "front") {
//            CRM_Core_Error::debug_var('destination', $destination);
//            CRM_Core_Error::debug_var('invoiceId', $invoiceId);
            $failureUrl = CRM_Payment2c2p_Utils::getFailureUrlViaInvoiceID($invoiceId);
            $redirectUrl = $failureUrl;
            try {
                $contribution = CRM_Payment2c2p_Utils::getContributionByInvoiceId($invoiceId);
            } catch (CRM_Core_Exception $e) {
                CRM_Core_Error::debug_var('ErrorgetContributionByInvoiceId', $e->getMessage());
            }
//            CRM_Core_Error::debug_var('redirectUrl1', $redirectUrl);
            //            CRM_Core_Error::debug_var('contribution', $contribution);
            $encodedPaymentResponse = $params['paymentResponse'];
            $paymentResponse = CRM_Payment2c2p_Utils::getDecodedPayload64($encodedPaymentResponse);
//            CRM_Core_Error::debug_var('paymentResponse', $paymentResponse);
//            CRM_Core_Error::debug_var('redirectUrl2', $redirectUrl);
            $module = CRM_Utils_Array::value('md', $params);
//            CRM_Core_Error::debug_var('respCode', $paymentResponse['respCode']);
            switch ($module) {
                case 'contribute':
                    if ($paymentResponse['respCode'] == 2000) {
                        $redirectUrl = CRM_Payment2c2p_Utils::redirectByInvoiceId($invoiceId);
                    } else {
                        CRM_Payment2c2p_Utils::setContributionStatusCancelled($contribution);
                    }
                    break;

                case 'event':
                    if ($paymentResponse['respCode'] == 2000) { // success code
                        //@todo properly
                        $participantId = CRM_Utils_Array::value('pid', $_GET);
                        $eventId = CRM_Utils_Array::value('eid', $_GET);
                        $query = "UPDATE civicrm_participant SET status_id = 1 where id =$participantId AND event_id=$eventId";
                        CRM_Core_DAO::executeQuery($query);
                        $redirectUrl = CRM_Payment2c2p_Utils::redirectByInvoiceId($invoiceId);
                    } else { // error code
                        CRM_Payment2c2p_Utils::setContributionStatusCancelled($contribution);
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

    public function getCurrentVersion()
    {
        return CRM_Payment2c2p_Config::PAYMENT_2C2P_VERSION;
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
        $failureUrl = CRM_Payment2c2p_Utils::getFailureUrlViaInvoiceID($invoiceId);
        $query = "UPDATE civicrm_contribution SET check_number='' where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
        $query = "UPDATE civicrm_contribution SET contribution_status_id=4 where invoice_id='$invoiceId'";
        CRM_Core_DAO::executeQuery($query);
        CRM_Core_Error::statusBounce(ts($_POST['respDesc']) . ts('2c2p Error:') . 'error', $failureUrl, 'error');
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


}

