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

use CRM_Payment2c2p_ExtensionUtil as E;

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
//    CRM_Core_Error::debug_var('autoload1', $autoload);
    require_once $autoload;
} else {
    $autoload = E::path() . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
//        CRM_Core_Error::debug_var('autoload2', $autoload);
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


    const PAYMENT_RESPONCE = array(
        "0000" => "Successful",
        "0001" => "Transaction is pending", // Pending
        "0003" => "Transaction is cancelled",
        "0999" => "System error",
        "2001" => "Transaction in progress", //In Progress
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
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceId);
        $paymentProcessorId = $payment_token['payment_processor_id'];
        return $paymentProcessorId;
    }

    /**
     * @param $invoiceID
     * @param $paymentProcessorId
     * @return CRM_Financial_DAO_PaymentProcessor
     * @throws CRM_Core_Exception
     */
    protected static function getPaymentProcessorViaProcessorID($paymentProcessorId): CRM_Core_Payment_Payment2c2p
    {
//        try {
//            $paymentProcessor = new CRM_Financial_DAO_PaymentProcessor();
//            $paymentProcessor->id = $paymentProcessorId;
//            $paymentProcessor->find(TRUE);
//        } catch (CiviCRM_API3_Exception $e) {
//            CRM_Core_Error::debug_var('API error', $e->getMessage());
//            throw new CRM_Core_Exception(ts('2c2p - Could not find payment processor'));
//        }
//        return (CRM_Core_Payment_Payment2c2p) $paymentProcessor;
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
        $url = $paymentProcessor->url_site . '/payment/4.1/paymentInquiry';
        $secretkey = $paymentProcessor->password;
        $merchantID = $paymentProcessor->user_name;
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
    public static function getPaymentInquiryViaKeySignature($invoiceID): array
    {
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceID);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);

        $payment_processor = $paymentProcessor->_paymentProcessor;
        $merchant_id = $payment_processor['user_name'];
        $merchant_secret = $payment_processor['password'];

        $date = date('Y-m-d');
        $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');

        $payment_inquiry = array(
            'version' => "3.8",
            'processType' => "I",
            'invoiceNo' => $invoiceID,
            'timeStamp' => $time_stamp,
            'merchantID' => $merchant_id,
            'actionAmount' => "",
            'request_type' => "PaymentProcessRequest"
        );
//        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $response = self::getPaymentResponseViaKeySignature(
            $payment_inquiry,
            );
//        CRM_Core_Error::debug_var('response', $response);

        $path_to_2c2p_certificate = $paymentProcessor->getOpenCertificatePath();
        $path_to_merchant_pem = $paymentProcessor->getClosedCertificatePath();
        $merchant_password = $paymentProcessor->getClosedCertificatePwd();
        $answer =
            CRM_Core_Payment_Payment2c2p::getPaymentFrom2c2pResponse($response,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);

        return $answer;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    public static function setPaymentInquiryViaKeySignature($invoiceID): array
    {
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceID);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);

        $payment_processor = $paymentProcessor->_paymentProcessor;
        $merchant_id = $payment_processor['user_name'];
        $merchant_secret = $payment_processor['password'];

        $date = date('Y-m-d');
        $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');

        $payment_inquiry = array(
            'version' => "3.8",
            'processType' => "V",
            'invoiceNo' => $invoiceID,
            'timeStamp' => $time_stamp,
            'merchantID' => $merchant_id,
            'actionAmount' => "",
            'request_type' => "PaymentProcessRequest"
        );
//        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $response = self::getPaymentResponseViaKeySignature(
            $payment_inquiry,
            );
//        CRM_Core_Error::debug_var('response', $response);

        $path_to_2c2p_certificate = $paymentProcessor->getOpenCertificatePath();
        $path_to_merchant_pem = $paymentProcessor->getClosedCertificatePath();
        $merchant_password = $paymentProcessor->getClosedCertificatePwd();
        $answer =
            CRM_Core_Payment_Payment2c2p::getPaymentFrom2c2pResponse($response,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);

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
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceID);
        $paymentProcessorId = $payment_token['payment_processor_id'];
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
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
    private static function getFailureUrlViaInvoiceID($invoiceID): string
    {
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceID);
        $paymentProcessorId = $payment_token['payment_processor_id'];
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $paymentProcessor = (array)$paymentProcessor;
        $failureUrl = strval($paymentProcessor['signature']);
        if ($failureUrl == null || $failureUrl == "") {
            $failureUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $failureUrl;
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

    public
    function doPayment(&$params, $component = 'contribute')
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
        CRM_Core_Error::debug_var('frontendReturnUrl', $frontendReturnUrl);
        $params['destination'] = 'back';
        $backendReturnUrl = self::getReturnUrl($processor_id, $processor_name, $params, $component);
        CRM_Core_Error::debug_var('backendReturnUrl', $backendReturnUrl);
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
            $date = date('Y-m-d H:i:s');
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
            $payload['chargeNextDate'] = $chargeNextDate;
            $payload['recurringCount'] = $recurringCount;
        }

        CRM_Core_Error::debug_var('paymentTokenPayload', $payload);

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
    public
    function handlePaymentNotification()
    {
        $params = array_merge($_GET, $_REQUEST);
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
        $params = array_merge($_GET, $_REQUEST);
        CRM_Core_Error::debug_var('getbackparams', $params);
        $destination = $params['destination'];
//        if($destination == "front"){
//
//        }
        CRM_Core_Error::debug_var('destination', $destination);
        if ($destination == "back") {
            $invoiceId = CRM_Utils_Array::value('inId', $_GET);
            self::setRecievedContributionStatus($invoiceId);
        }
        if ($destination == "front") {
            $encodedPaymentResponse = $params['paymentResponse'];
            $paymentResponse = $this->decodePayload64($encodedPaymentResponse);
////        CRM_Core_Error::debug_var('paymentResponse', $paymentResponse);

            require_once 'CRM/Utils/Array.php';
            $module = CRM_Utils_Array::value('md', $_GET);
            $invoiceId = CRM_Utils_Array::value('inId', $_GET);

            /** @var TYPE_NAME $this */
            $paymentProcessor = $this->_paymentProcessor;
            $thanxUrl = self::getThanxUrl($paymentProcessor);
            $failureUrl = self::getFailureUrl($paymentProcessor);

            switch ($module) {
                case 'contribute':

                    if ($paymentResponse['respCode'] == 2000) {
                        self::setRecievedContributionStatus($invoiceId);
                    } else {
                        self::setContributionStatusRejected($invoiceId);
                    }
                    break;

                case 'event':


                    if ($paymentResponse['respCode'] == 2000) { // success code
                        $participantId = CRM_Utils_Array::value('pid', $_GET);
                        $eventId = CRM_Utils_Array::value('eid', $_GET);
                        $query = "UPDATE civicrm_participant SET status_id = 1 where id =$participantId AND event_id=$eventId";
                        CRM_Core_DAO::executeQuery($query);
                        self::setRecievedContributionStatus($invoiceId);
                    } else { // error code
                        self::setContributionStatusRejected($invoiceId);
                    }

                    break;

                default:
                    CRM_Core_Error::statusBounce("Could not get module name from request thanxUrl", $thanxUrl);
            }

//        $thanxUrl = CRM_Utils_System::thanxUrl($this->_paymentProcessor['subject']);
//        CRM_Core_Error::debug_var('thanxUrl4', $thanxUrl);

            return TRUE;
        }
    }


    public
    function getCurrentVersion()
    {
        return CRM_Payment2c2p_Config::PAYMENT_2C2P_VERSION;
    }


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
            CRM_Core_Error::statusBounce(ts('2c2p Error:') . 'Request error', null, $e->getMessage());
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
    public
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
    public static function setRecievedContributionStatus($invoiceId): void
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
//        CRM_Core_Error::debug_var('contribution', $contribution);
//        $decodedTokenResponse = self::getPaymentInquiryViaPaymentToken($invoiceId);
        $decodedTokenResponse = self::getPaymentInquiryViaKeySignature($invoiceId);
//        CRM_Core_Error::debug_var('contribution', $contribution['contribution_recur_id']);
        if ($contribution['contribution_recur_id'] != null) {
            //todo part about recurring payment
//        CRM_Core_Error::debug_var('url', $url);
            $resp_code = $decodedTokenResponse['respCode'];
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
        $resp_code = $decodedTokenResponse['respCode'];
//        CRM_Core_Error::debug_var('resp_code', $resp_code);
        if ($resp_code != "00") {
            throw new CRM_Core_Exception(self::PAYMENT_RESPONCE[$resp_code]);
        }
        $contribution_status = $decodedTokenResponse['status'];

        if (!in_array($contribution_status, ["A", "S"])) {
//        CRM_Core_Error::debug_var('decodedTokenResponse', $decodedTokenResponse);

            //            throw new CRM_Core_Exception(self::PAYMENT_RESPONCE[$resp_code]);
//            CRM_Core_Error::statusBounce(ts(self::PAYMENT_RESPONCE[$resp_code] . ts('2c2p Error:') . 'error', $url, 'error'));
            $contribution_status_id = 4;
            if ($contribution_status == "V") {
                $contribution_status_id
                    =
                    CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Cancelled');
                civicrm_api3('Contribution', 'create', array(
                    'id' => $contribution['id'],
                    'contribution_status_id' => $contribution_status_id,
                ));
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
            if ($contribution_status == "RF") {
                $contribution_status_id =
                    CRM_Core_PseudoConstant::getKey(
                        'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded');
                self::changeContributionStatusViaDB($invoiceId, $contribution_status_id);
            }
//            CRM_Core_Error::debug_var('contribution_status_id', $contribution_status_id);
            return;
        }
        $cardNo = substr($decodedTokenResponse['maskedPan'], -4);
        $cardType = $decodedTokenResponse['paymentScheme'];
        $channelCode = $decodedTokenResponse['processBy'];
        $cardTypeId = 2;
        $paymentInstrumentId = null;
        $paymentInstrumentId = 1;
        if ($channelCode == 'VI') {
            $cardTypeId = 1;
        }

//        CRM_Core_Error::debug_var('contribution_status_id', "SUPER");
        $contributionId = $contribution['id'];
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $thanxUrl = self::getThanxUrlViaInvoiceID($invoiceId);
        $failureUrl = self::getFailureUrlViaInvoiceID($invoiceId);
        $failed_status_id =                     CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');;
        $cancelled_status_id =                     CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Cancelled');;
        $pending_status_id =                     CRM_Core_PseudoConstant::getKey(
            'CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
        if(in_array($contribution['contribution_status_id'], [$failed_status_id, $cancelled_status_id])){
            self::changeContributionStatusViaDB($invoiceId, $pending_status_id);
            //to give possibility to make it fulfiled
        }
        try {
            civicrm_api3('contribution', 'completetransaction',
                ['id' => $contributionId,
                    'trxn_id' => $trxnId,
                    'pan_truncation' => $cardNo,
                    'card_type_id' => $cardTypeId,
                    'payment_instrument_id' => $paymentInstrumentId,
                    'processor_id' => $paymentProcessorId]);
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
     * @param $contributionId
     * @throws CRM_Core_Exception
     */
    public static function setContributionStatusCancelled($contributionId): void
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
//        $decodedTokenResponse = self::getPaymentInquiryViaPaymentToken($invoiceId);
        $invoiceId = $contribution["invoice_id"];
        $cancelledTokenResponse = self::setPaymentInquiryViaKeySignature($invoiceId, "V");
        CRM_Core_Error::debug_var('decodedTokenResponse', $cancelledTokenResponse);
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
     */
    public
    function getDecodedTokenResponse(string $url, string $paymentTokenRequest, $secretKey): array
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

    public static function checkPendingContribution()
    {

//        CRM_Core_Error::debug_var('request', $_REQUEST);
//        CRM_Core_Error::debug_var('post', $_POST);

        $invoiceId = null;
        $invoiceId = CRM_Utils_Request::retrieveValue('invoiceId', 'String', null);
//        CRM_Core_Error::debug_var('invoiceId', $invoiceId);
        if ($invoiceId) {
            try {
//                CRM_Core_Error::debug_var('before', $invoiceId);
                self::setRecievedContributionStatus($invoiceId);
//            CRM_Core_Error::debug_var('after', $invoiceId);
            } catch (Exception $e) {
                CRM_Core_Error::debug_var('error', $e->getMessage());
                CRM_Core_Error::statusBounce("Operation Failed", null, '2c2p');
            }
        } else {
            CRM_Core_Error::statusBounce("No Invoice ID", null, '2c2p');
        }
    }

    /**
     * @param $response
     * @param $path_to_2c2p_certificate
     * @param $path_to_merchant_pem
     * @param $merchant_password
     * @param $merchant_secret
     * @throws CRM_Core_Exception
     */

    public static function getPaymentFrom2c2pResponse($response,
                                                      $path_to_2c2p_certificate,
                                                      $path_to_merchant_pem,
                                                      $merchant_password,
                                                      $merchant_secret): array
    {
        //credentials part
        $receiverPublicCertPath = $path_to_2c2p_certificate;
        $receiverPublicCertKey = \Jose\Component\KeyManagement\JWKFactory::createFromCertificateFile(
            $receiverPublicCertPath, // The filename
        );

        $senderPrivateKeyPath = $path_to_merchant_pem;

        $senderPrivateKeyPassword = $merchant_password;
        $jw_signature_key = \Jose\Component\KeyManagement\JWKFactory::createFromKeyFile(
            $senderPrivateKeyPath,
            $senderPrivateKeyPassword
        );

        $secretKey = $merchant_secret;    //Get SecretKey from 2C2P PGW Dashboard
//end credentials part

        $response_body = $response->getBody()->getContents();

        $signatureAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
            new \Jose\Component\Signature\Algorithm\PS256(),
        ]);


        // We instantiate our JWS Verifier.
        $jwsVerifier = new \Jose\Component\Signature\JWSVerifier(
            $signatureAlgorithmManager
        );

        $signature_serializer = new \Jose\Component\Signature\Serializer\CompactSerializer(); // The serializer

        $signatureSerializerManager = new \Jose\Component\Signature\Serializer\JWSSerializerManager([
            $signature_serializer,
        ]);

        $headerSignatureCheckerManager = new \Jose\Component\Checker\HeaderCheckerManager(
            [
                new \Jose\Component\Checker\AlgorithmChecker(['PS256']),
            ],
            [
                new \Jose\Component\Signature\JWSTokenSupport(), // Adds JWS token type support
            ]
        );

        try {
            $jw_signed_response = $signature_serializer->unserialize($response_body);
            $isVerified = $jwsVerifier->verifyWithKey($jw_signed_response, $receiverPublicCertKey, 0);
            if ($isVerified) {

                $jwsLoader = new \Jose\Component\Signature\JWSLoader(
                    $signatureSerializerManager,
                    $jwsVerifier,
                    $headerSignatureCheckerManager
                );

                $jwsigned_response_loaded = $jwsLoader->loadAndVerifyWithKey((string)$response_body, $receiverPublicCertKey, $signature, null);
                $encrypted_serialized_response = $jwsigned_response_loaded->getPayload();
            } else {
                throw new CRM_Core_Exception(ts("2c2p Error: Not Verified "));
            }

        } catch (Exception $e) {
            $em = $e->getMessage();
            throw new CRM_Core_Exception(ts("2c2p Error: $em "));
        }


        $encryption_serializer = new \Jose\Component\Encryption\Serializer\CompactSerializer(); // The serializer

        try {

            $encryptionSerializerManager = new \Jose\Component\Encryption\Serializer\JWESerializerManager([
                $encryption_serializer,
            ]);

            $jw_encrypted_response = $encryption_serializer->unserialize($encrypted_serialized_response);

            // The key encryption algorithm manager with the A256KW algorithm.
            $keyEncryptionAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
                new \Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP(),
            ]);

// The content encryption algorithm manager with the A256CBC-HS256 algorithm.
            $contentEncryptionAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
                new \Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM(),
            ]);

// The compression method manager with the DEF (Deflate) method.
            $compressionMethodManager = new \Jose\Component\Encryption\Compression\CompressionMethodManager([
                new \Jose\Component\Encryption\Compression\Deflate(),
            ]);

// We instantiate our JWE Decrypter.
            $jweDecrypter = new \Jose\Component\Encryption\JWEDecrypter(
                $keyEncryptionAlgorithmManager,
                $contentEncryptionAlgorithmManager,
                $compressionMethodManager
            );
            $headerCheckerManagerE = new \Jose\Component\Checker\HeaderCheckerManager(
                [
                    new \Jose\Component\Checker\AlgorithmChecker(['RSA-OAEP']),
                ],
                [
                    new \Jose\Component\Encryption\JWETokenSupport(), // Adds JWS token type support
                ]
            );

            $success = $jweDecrypter->decryptUsingKey($jw_encrypted_response, $jw_signature_key, 0);
//        echo $success;
            $jweLoader = new \Jose\Component\Encryption\JWELoader(
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
        echo $unencrypted_payload;
//        if($request_type == "RecurringMaintenanceRequest"){
//        $answer = self::unencryptRecurringPaymentAnswer($unencrypted_payload, $secretKey);
//        }
//        if($request_type == "PaymentProcessRequest"){
        $answer = self::unencryptPaymentAnswer($unencrypted_payload, $secretKey);
//        }
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
        CRM_Core_Error::debug_var('payment_inquiry', $payment_inquiry);

        $invoiceId = $payment_inquiry['invoiceNo'];

        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $receiverPublicCertPath = $paymentProcessor->getOpenCertificatePath();
        $senderPrivateKeyPath = $paymentProcessor->getClosedCertificatePath();
        $senderPrivateKeyPassword = $paymentProcessor->getClosedCertificatePwd(); //private key password
        $payment_processor = $paymentProcessor->_paymentProcessor;
        $merchantID = $payment_processor['user_name'];        //Get MerchantID when opening account with 2C2P
        $secretKey = $payment_processor['password'];    //Get SecretKey from 2C2P PGW Dashboard
//        $url = $paymentProcessor->url_site . '/payment/4.1/paymentInquiry';
        $url = "https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action";
// The key encryption algorithm manager with the A256KW algorithm.
        CRM_Core_Error::debug_var('keyEncryptionAlgorithmManager', '0');
        CRM_Core_Error::debug_var('keyEncryptionAlgorithmManager', '1');
        try {
            $keyEncryptionAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
                new \Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP(),
            ]);
            CRM_Core_Error::debug_var('keyEncryptionAlgorithmManager', $keyEncryptionAlgorithmManager->list());
        } catch (CRM_Core_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid keyEncryptionAlgorithmManager') . $e->getMessage());
        }
        CRM_Core_Error::debug_var('keyEncryptionAlgorithmManager', '1');

// The content encryption algorithm manager with the A256CBC-HS256 algorithm.
        try {
            $contentEncryptionAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
                new \Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM(),
            ]);
        } catch (CRM_Core_Exception $e) {
            throw new CRM_Core_Exception(ts('2c2p - Unvalid contentEncryptionAlgorithmManager') . $e->getMessage());
        }
        CRM_Core_Error::debug_var('contentEncryptionAlgorithmManager', '2');

// The compression method manager with the DEF (Deflate) method.
        $compressionMethodManager = new \Jose\Component\Encryption\Compression\CompressionMethodManager([
            new \Jose\Component\Encryption\Compression\Deflate(),
        ]);

// We instantiate our JWE Builder.
        $jwencryptedBuilder = new \Jose\Component\Encryption\JWEBuilder(
            $keyEncryptionAlgorithmManager,
            $contentEncryptionAlgorithmManager,
            $compressionMethodManager
        );


// Our key.
        $receiverPublicCertKey = \Jose\Component\KeyManagement\JWKFactory::createFromCertificateFile(
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


//return;
        $jw_encrypted_response = $jwencryptedBuilder
            ->create()// We want to create a new JWE
            ->withPayload($xml)// We set the payload
            ->withSharedProtectedHeader([
                'alg' => 'RSA-OAEP', // Key Encryption Algorithm
                'enc' => 'A256GCM',  // Content Encryption Algorithm
                'typ' => 'JWT'
//        'zip' => 'DEF'       // We enable the compression (irrelevant as the payload is small, just for the example).
            ])
            ->addRecipient($receiverPublicCertKey)// We add a recipient (a shared key or public key).
            ->build();

        $encryption_serializer = new \Jose\Component\Encryption\Serializer\CompactSerializer(); // The serializer
        $jwe_request_payload = $encryption_serializer->serialize($jw_encrypted_response, 0); // We serialize the recipient at index 0 (we only have one recipient).

// The algorithm manager with the HS256 algorithm.
        $signatureAlgorithmManager = new \Jose\Component\Core\AlgorithmManager([
            new \Jose\Component\Signature\Algorithm\PS256(),
        ]);

// Our key.
//echo "jwk:\n";
        $jw_signature_key = \Jose\Component\KeyManagement\JWKFactory::createFromKeyFile(
            $senderPrivateKeyPath,
            $senderPrivateKeyPassword
        );


        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder($signatureAlgorithmManager);

        $jw_signed_request = $jwsBuilder
            ->create()// We want to create a new JWS
            ->withPayload($jwe_request_payload)// We set the payload
            ->addSignature($jw_signature_key, ['alg' => 'PS256', 'typ' => 'JWT'])// We add a signature with a simple protected header
            ->build();


        $signature_serializer = new \Jose\Component\Signature\Serializer\CompactSerializer(); // The serializer

        $jw_signed_payload = $signature_serializer->serialize($jw_signed_request, 0); // We serialize the signature at index 0 (we only have one signature).

        $client = new GuzzleHttp\Client();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';


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

        } catch (GuzzleHttp\Exception\GuzzleException $e) {
            print("\n2:\n" . $e->getMessage());
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


}

