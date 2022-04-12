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
        CRM_Core_Error::fatal(ts('This function is not implemented'));
    }

    function doTransferCheckout(&$params, $component = 'component')
    {
        $component = strtolower($component);
        $config = CRM_Core_Config::singleton();

        if ($component != 'contribute' && $component != 'event') {
            CRM_Core_Error::fatal(ts('Component is invalid'));
        }

        $email = isset($params['email-5']) ? $params['email-5'] : $params['email-Primary'];

        /* Sanitization of every data is important to calculate checksum. */
        /* Refer to zaakpay transact api documentation to see which array key means what */

        $this->data = array(
            'merchantIdentifier' => Checksum::sanitizedParam($this->_paymentProcessor['user_name']),
            'orderId' => Checksum::sanitizedParam(substr($params['invoiceID'], 0, 15)),
            'buyerEmail' => Checksum::sanitizedParam($email),
            'buyerFirstName' => Checksum::sanitizedParam($params['first_name']),
            'buyerLastName' => Checksum::sanitizedParam($params['last_name']),
            'buyerAddress' => Checksum::sanitizedParam($params['address_name-Primary']),
            'buyerCity' => Checksum::sanitizedParam($params['city-Primary']),
            'buyerState' => Checksum::sanitizedParam($params['state_province-Primary']),
            'buyerCountry' => Checksum::sanitizedParam($params['country-Primary']),
            'buyerPincode' => Checksum::sanitizedParam($params['postal_code-Primary']),
            'buyerPhoneNumber' => Checksum::sanitizedParam($params['phone-Primary-1']),
            'txnType' => Checksum::sanitizedParam(1),
            'zpPayOption' => Checksum::sanitizedParam(1),
            'mode' => Checksum::sanitizedParam($this->_mode == 'test' ? 0 : 1),
            'currency' => Checksum::sanitizedParam('INR'), // zaakpay only supports INR
            'amount' => Checksum::sanitizedParam($params['amount'] * 100),
            'merchantIpAddress' => Checksum::sanitizedParam($this->_paymentProcessor['signature']),
            'purpose' => Checksum::sanitizedParam(1),
            'productDescription' => Checksum::sanitizedParam($params['description']),
            'txnDate' => Checksum::sanitizedParam(date('Y-n-d')),
        );


        /* set return url based on the component */

        if ($component == 'contribute') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() . "civicrm/payment/ipn?processor_name=Zaakpay&md=contribute&qfKey=" . $params['qfKey'] . '&inId=' . $params['invoiceID'];
        } else if ($component == 'event') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() . "civicrm/payment/ipn?processor_name=Zaakpay&md=event&qfKey=" . $params['qfKey'] . '&pid=' . $params['participantID'] . "&eid=" . $params['eventID'] . "&inId=" . $params['invoiceID'];
        }

        /* important because without storing session objects,
         *  civicrm wouldnt know if the confirm page ever submitted as we are using exit at the end
         *  and it will never redirect to the thank you page, rather keeps redirecting to the confirmation page.
         */

        require_once 'CRM/Core/Session.php';
        CRM_Core_Session::storeSessionObjects();

        $secret = $this->_paymentProcessor['password'];

        /* calculate checksum by using the functions given in checksum.php which is provide by Zaakpay */

        $all = Checksum::getAllParams($this->data);

        $checksum = Checksum::calculateChecksum($secret, $all);
        $this->data['checksum'] = $checksum;


        /* includes zaakpay.tpl which posts the data to zaakpay */

        $template = CRM_Core_Smarty::singleton();
        $tpl = $this->templateDir . 'zaakpay.tpl';

        $template->assign('data', $this->data);
        $tpl = $template->fetch($tpl);
        print $tpl;
        exit;
    }

    /*
     * 	This is the function which handles the response
     * when zaakpay redirects the user back to our website
     * after transaction.
     * Refer to the $this->data['returnURL'] in above function to see how the Url should be created
     */

    public function handlePaymentNotification()
    {
        CRM_Core_Error::debug_var('request', $_REQUEST);
        CRM_Core_Error::debug_var('post', $_POST);
        $paymentResponse = $_POST['$paymentResponse'];

        require_once 'CRM/Utils/Array.php';

        $module = CRM_Utils_Array::value('md', $_GET);
        $qfKey = CRM_Utils_Array::value('qfKey', $_GET);
        $invoiceId = CRM_Utils_Array::value('inId', $_GET);

        switch ($module) {
            case 'contribute':
                if ($paymentResponse['responseCode'] == 100) {
                    $query = "UPDATE civicrm_contribution SET trxn_id='" . $_POST['orderId'] . "', contribution_status_id=1 where invoice_id='" . $invoiceId . "'";
                    CRM_Core_DAO::executeQuery($query);
                    $url = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_ThankYou_display=1&qfKey={$qfKey}", FALSE, NULL, FALSE
                    );
                } else {
                    CRM_Core_Session::setStatus(ts($_POST['responseDescription']), ts('Zaakpay Error:'), 'error');
                    $url = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_Confirm_display=true&qfKey={$qfKey}", FALSE, NULL, FALSE
                    );
                }

                break;

            case 'event':

                if ($paymentResponse['responseCode'] == 100) { // success code
                    $participantId = CRM_Utils_Array::value('pid', $_GET);
                    $eventId = CRM_Utils_Array::value('eid', $_GET);

                    $query = "UPDATE civicrm_participant SET status_id = 1 where id =" . $participantId . " AND event_id=" . $eventId;
                    CRM_Core_DAO::executeQuery($query);

                    $query = "UPDATE civicrm_contribution SET trxn_id='" . $_POST['orderId'] . "', contribution_status_id=1 where invoice_id='" . $invoiceId . "'";

                    CRM_Core_DAO::executeQuery($query);

                    $url = CRM_Utils_System::url('civicrm/event/register', "_qf_ThankYou_display=1&qfKey={$qfKey}", FALSE, NULL, FALSE
                    );
                } else { // error code
                    CRM_Core_Session::setStatus(ts($_POST['responseDescription']), ts('Zaakpay Error:'), 'error');
                    $url = CRM_Utils_System::url('civicrm/event/register', "_qf_Confirm_display=true&qfKey={$qfKey}", FALSE, NULL, FALSE
                    );
                }

                break;

            default:
                require_once 'CRM/Core/Error.php';
                CRM_Core_Error::debug_log_message("Could not get module name from request url");
                echo "Could not get module name from request url\r\n";
        }
        CRM_Utils_System::redirect($url);
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

    public function getPaymentPayload($secretkey, $merchant_id, $invoice_no, $description, $amount, $currency, $frontendReturnUrl)
    {
        $paymentTokenRequest = new CRM_Payment2c2p_PaymentTokenRequest;
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

        return CRM_Payment2c2p_PaymentTokenRequest::getDecodedTokenResponse($response, $secretKey, $responseType);
    }

    public function getEncodedTokenResponse($url, $payload)
    {

        return CRM_Payment2c2p_PaymentTokenRequest::getEncodedTokenResponse($url, $payload);
    }

    public function getReturnUrl($processor_name, $params, $component = 'contribute')
    {
        if ($component == 'contribute') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_name=$processor_name&md=contribute&qfKey=" .
                $params['qfKey'] .
                '&inId=' . $params['invoiceID'];
            return $this->data['returnUrl'];
        } else if ($component == 'event') {
            $this->data['returnUrl'] = CRM_Utils_System::baseCMSURL() .
                "civicrm/payment/ipn?processor_name=$processor_name&md=event&qfKey=" .
                $params['qfKey'] .
                '&pid=' . $params['participantID'] .
                "&eid=" . $params['eventID'] .
                "&inId=" . $params['invoiceID'];
            return $this->data['returnUrl'];
        }
        return $this->data['returnUrl'];
    }




}

