<?php

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
//    CRM_Core_Error::debug_var('autoload1', $autoload);
    require_once $autoload;
} else {
    $autoload = E::path() . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
//        CRM_Core_Error::debug_var('autoload2', $autoload);
    }
}

use \Firebase\JWT\JWT;

class CRM_Payment2c2p_Utils
{
    private const OPEN_CERTIFICATE_FILE_NAME = "sandbox-jwt-2c2p.demo.2.1(public).cer";
    private const CLOSED_CERTIFICATE_FILE_NAME = "private.pem";
    private const CLOSED_CERTIFICATE_PWD = "octopus8";


// @todo 3
//    public static function process_recurring_payments($payment_processor, $paymentObject) {
//        // If an ewayrecurring job is already running, we want to exit as soon as possible.
//        $lock = \Civi\Core\Container::singleton()
//            ->get('lockManager')
//            ->create('worker.ewayrecurring');
//        if (!$lock->isFree() || !$lock->acquire()) {
//            Civi::log()->warning("Detected processing race for scheduled payments, aborting");
//            return FALSE;
//        }
//
//        // Create eWay token client
//        $eWayClient = $paymentObject->getEWayClient();
//
//        // Process today's scheduled contributions.
//        $scheduled_contributions = get_scheduled_contributions($payment_processor);
//        $scheduled_failed_contributions = get_scheduled_failed_contributions($payment_processor);
//
//        $scheduled_contributions = array_merge($scheduled_failed_contributions, $scheduled_contributions);
//
//        foreach ($scheduled_contributions as $contribution) {
//            if ($contribution->payment_processor_id != $payment_processor['id']) {
//                continue;
//            }
//
//            // Re-check schedule time, in case contribution already processed.
//            $next_sched = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                $contribution->id,
//                'next_sched_contribution_date',
//                'id',
//                TRUE);
//
//            /* Get the number of Contributions already recorded for this Schedule. */
//            $mainContributions = civicrm_api3('Contribution', 'get', [
//                'options' => ['limit' => 0],
//                'sequential' => 1,
//                'return' => ['total_amount', 'tax_amount'],
//                'contribution_recur_id' => $contribution->id,
//            ]);
//
//            $mainContributions = $mainContributions['values'];
//            $ccount = count($mainContributions);
//
//            /* Schedule next contribution */
//            if (($contribution->installments <= 0) || ($contribution->installments > $ccount + 1)) {
//                $next_sched = date('Y-m-d 00:00:00', strtotime($next_sched . " +{$contribution->frequency_interval} {$contribution->frequency_unit}s"));
//            }
//            else {
//                $next_sched = NULL;
//                /* Mark recurring contribution as complteted*/
//                civicrm_api(
//                    'ContributionRecur', 'create',
//                    [
//                        'version' => '3',
//                        'id' => $contribution->id,
//                        'contribution_recur_status_id' => _contribution_status_id('Completed'),
//                    ]
//                );
//            }
//
//            // Process payment
//            // Civi::log()->debug("Processing payment for scheduled recurring contribution ID: " . $contribution->id . "\n");
//            $amount_in_cents = preg_replace('/\.([0-9]{0,2}).*$/', '$1',
//                $contribution->amount);
//
//            $addresses = civicrm_api('Address', 'get',
//                [
//                    'version' => '3',
//                    'contact_id' => $contribution->contact_id,
//                ]);
//
//            $billing_address = array_shift($addresses['values']);
//
//            $invoice_id = md5(uniqid(rand(), TRUE));
//            $eWayResponse = NULL;
//
//            try {
//                if (!$contribution->failure_retry_date) {
//                    // Only update the next schedule if we're not in a retry state.
//                    _eWayRecurring_update_contribution_status($next_sched, $contribution);
//                }
//
//                $mainContributions = $mainContributions[0];
//                $new_contribution_record = [];
//                if (empty($mainContributions['tax_amount'])) {
//                    $mainContributions['tax_amount'] = 0;
//                }
//
//                $repeat_params = [
//                    'contribution_recur_id'  => $contribution->id,
//                    'contribution_status_id' => _contribution_status_id('Pending'),
//                    'total_amount'           => $contribution->amount,
//                    'is_email_receipt'       => 0,
//                ];
//
//                $repeated = civicrm_api3('Contribution', 'repeattransaction', $repeat_params);
//
//                $new_contribution_record = $repeated;
//
//                // Civi::log()->debug("Creating contribution record\n");
//                $new_contribution_record['contact_id'] = $contribution->contact_id;
//                $new_contribution_record['receive_date'] = CRM_Utils_Date::isoToMysql(date('Y-m-d H:i:s'));
//                $new_contribution_record['total_amount'] = ($contribution->amount - $mainContributions['tax_amount']);
//                $new_contribution_record['contribution_recur_id'] = $contribution->id;
//                $new_contribution_record['payment_instrument_id'] = $contribution->payment_instrument_id;
//                $new_contribution_record['address_id'] = $billing_address['id'];
//                $new_contribution_record['invoice_id'] = $invoice_id;
//                $new_contribution_record['campaign_id'] = $contribution->campaign_id;
//                $new_contribution_record['financial_type_id'] = $contribution->financial_type_id;
//                $new_contribution_record['payment_processor'] = $contribution->payment_processor_id;
//                $new_contribution_record['payment_processor_id'] = $contribution->payment_processor_id;
//
//                $contributions = civicrm_api3(
//                    'Contribution', 'get', [
//                        'sequential' => 1,
//                        'contribution_recur_id' => $contribution->id,
//                        'options' => ['sort' => "id ASC"],
//                    ]
//                );
//
//                $precedent = new CRM_Contribute_BAO_Contribution();
//                $precedent->contribution_recur_id = $contribution->id;
//
//                $contributionSource = '';
//                $contributionPageId = '';
//                $contributionIsTest = 0;
//
//                if ($precedent->find(TRUE)) {
//                    $contributionSource = $precedent->source;
//                    $contributionPageId = $precedent->contribution_page_id;
//                    $contributionIsTest = $precedent->is_test;
//                }
//
//                try {
//                    $financial_type = civicrm_api3(
//                        'FinancialType', 'getsingle', [
//                        'sequential' => 1,
//                        'return' => "name",
//                        'id' => $contribution->financial_type_id,
//                    ]);
//                } catch (CiviCRM_API3_Exception $e) { // Most likely due to FinancialType API not being available in < 4.5 - try DAO directly
//                    $ft_bao = new CRM_Financial_BAO_FinancialType();
//                    $ft_bao->id = $contribution->financial_type_id;
//                    $found = $ft_bao->find(TRUE);
//
//                    $financial_type = (array) $ft_bao;
//                }
//
//
//                if (!isset($financial_type['name'])) {
//                    throw new Exception (
//                        "Financial type could not be loaded for {$contribution->id}"
//                    );
//                }
//
//                $new_contribution_record['source'] = "eWay Recurring {$financial_type['name']}:\n{$contributionSource}";
//                $new_contribution_record['contribution_page_id'] = $contributionPageId;
//                $new_contribution_record['is_test'] = $contributionIsTest;
//
//                // Retrieve the eWAY token
//
//                if (!empty($contribution->payment_token_id)) {
//                    try {
//                        $token = civicrm_api3('PaymentToken', 'getvalue', [
//                            'return' => 'token',
//                            'id'     => $contribution->payment_token_id,
//                        ]);
//                    } catch (CiviCRM_API3_Exception $e) {
//                        $token = $contribution->processor_id;
//                    }
//                }
//                else {
//                    $token = $contribution->processor_id;
//                }
//
//                if (!$token) {
//                    throw new CRM_Core_Exception(E::ts('No eWAY token found for Recurring Contribution %1', [1 => $contribution->id]));
//                }
//
//                $eWayResponse = process_eway_payment(
//                    $eWayClient,
//                    $token,
//                    $amount_in_cents,
//                    substr($invoice_id, 0, 16),
//                    $financial_type['name'] . ($contributionSource ?
//                        ":\n" . $contributionSource : '')
//                );
//
//                $new_contribution_record['trxn_id'] = $eWayResponse->getAttribute('TransactionID');
//
//                $responseErrors = $paymentObject->getEWayResponseErrors($eWayResponse);
//
//                if (!$eWayResponse->TransactionStatus) {
//                    $responseMessages = array_map('\Eway\Rapid::getMessage', explode(', ', $eWayResponse->ResponseMessage));
//                    $responseErrors = array_merge($responseMessages, $responseErrors);
//                }
//
//                if (count($responseErrors)) {
//                    // Mark transaction as failed
//                    $new_contribution_record['contribution_status_id'] = _contribution_status_id('Failed');
//                    _eWAYRecurring_mark_recurring_contribution_Failed($contribution);
//                }
//                else {
//                    // send_receipt_email($new_contribution_record->id);
//                    $new_contribution_record['contribution_status_id'] = _contribution_status_id('Completed');
//
//                    $new_contribution_record['is_email_receipt'] = 0;
//
//                    if ($contribution->failure_count > 0 && $contribution->contribution_status_id == _contribution_status_id('Failed')) {
//                        // Failed recurring contribution completed successfuly after several retry.
//                        _eWayRecurring_update_contribution_status($next_sched, $contribution);
//                        CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                            $contribution->id,
//                            'contribution_status_id',
//                            _contribution_status_id('In Progress'));
//
//                        try {
//                            civicrm_api3('Activity', 'create', [
//                                'source_contact_id' => $contribution->contact_id,
//                                'activity_type_id' => 'eWay Transaction Succeeded',
//                                'source_record' => $contribution->id,
//                                'details' => 'Transaction Succeeded after ' . $contribution->failure_count . ' retries',
//                            ]);
//                        }
//                        catch (CiviCRM_API3_Exception $e) {
//                            \Civi::log()->debug('eWAY Recurring: Couldn\'t record success activity: ' . $e->getMessage());
//                        }
//                    }
//
//                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                        $contribution->id, 'failure_count', 0);
//
//                    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur',
//                        $contribution->id, 'failure_retry_date', '');
//                }
//
//                $api_action = (
//                $new_contribution_record['contribution_status_id'] == _contribution_status_id('Completed')
//                    ? 'completetransaction'
//                    : 'create'
//                );
//
//                $updated = civicrm_api3('Contribution', $api_action, $new_contribution_record);
//
//                $new_contribution_record = reset($updated['values']);
//
//                // The invoice_id does not seem to be recorded by
//                // Contribution.completetransaction, so let's update it directly.
//                if ($api_action === 'completetransaction') {
//                    $updated = civicrm_api3('Contribution', 'create', [
//                        'id' => $new_contribution_record['id'],
//                        'invoice_id' => $invoice_id,
//                    ]);
//                    $new_contribution_record = reset($updated['values']);
//                }
//
//                if (count($responseErrors)) {
//                    $note = new CRM_Core_BAO_Note();
//
//                    $note->entity_table = 'civicrm_contribution';
//                    $note->contact_id = $contribution->contact_id;
//                    $note->entity_id = $new_contribution_record['id'];
//                    $note->subject = ts('Transaction Error');
//                    $note->note = implode("\n", $responseErrors);
//
//                    $note->save();
//                }
//
//                // Civi::log()->debug("Save contribution with trxn_id {$new_contribution_record->trxn_id}");
//
//            } catch (Exception $e) {
//                Civi::log()->warning("Processing payment {$contribution->id} for {$contribution->contact_id}: " . $e->getMessage());
//
//                // already talk to eway? then we need to check the payment status
//                if ($eWayResponse) {
//                    $new_contribution_record['contribution_status_id'] = _contribution_status_id('Pending');
//                } else {
//                    $new_contribution_record['contribution_status_id'] = _contribution_status_id('Failed');
//                }
//
//                $updated = civicrm_api3('Contribution', 'create', $new_contribution_record);
//                $new_contribution_record = reset($updated['values']);
//                // CIVIEWAY-147 there is an unknown system error that happen after civi talks to eway
//                // It might be a cache cleaning task happening at the same time that break this task
//                // Defer the query later to update the contribution status
//                if ($eWayResponse) {
//                    $ewayParams = [
//                        'access_code' => $eWayResponse->TransactionID,
//                        'contribution_id' => $new_contribution_record['id'],
//                        'payment_processor_id' => $contribution->payment_processor_id,
//                    ];
//                    civicrm_api3('EwayContributionTransactions', 'create', $ewayParams);
//                } else {
//                    // Just mark it failed when eWay have no info about this at all
//                    _eWAYRecurring_mark_recurring_contribution_Failed($contribution);
//                }
//
//                $note = new CRM_Core_BAO_Note();
//
//                $note->entity_table = 'civicrm_contribution';
//                $note->contact_id = $contribution->contact_id;
//                $note->entity_id = $new_contribution_record['id'];
//                $note->subject = ts('Contribution Error');
//                $note->note = $e->getMessage();
//
//                $note->save();
//            }
//
//            unset($eWayResponse);
//
//        }
//
//        $lock->release();
//    }
//
//    /**

    /**
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
            if ($resp_code === "4200") { //Successful tokenization for recurring payments
                return $thanxUrl;
            }
            self::verifyContribution($invoiceId);
            if ($resp_code == "0003") {
                return $failureUrl;
            }
            if ($resp_code == "0001") {
                return $failureUrl;
            }
            if ($resp_code == "2001") {
                return $thanxUrl;
            }
        }
        self::verifyContribution($invoiceId);
        return $failureUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     * @throws CRM_Core_Exception
     */
    public static function getThanxUrlViaInvoiceID($invoiceID): string
    {
        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceID);
        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
        $thanxUrl = strval($payment_processor_array['subject']);

        if ($thanxUrl == null || $thanxUrl == "") {
            $thanxUrl = CRM_Utils_System::url();
        }
        return $thanxUrl;
    }

    /**
     * @param $paymentProcessor
     * @return string
     * @throws CRM_Core_Exception
     */
    public static function getFailureUrlViaInvoiceID($invoiceID): string
    {
        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceID);
        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
//        CRM_Core_Error::debug_var('$payment_processor_array_failure', $payment_processor_array);
        $failureUrl = strval($payment_processor_array['signature']);
        if ($failureUrl == null || $failureUrl == "") {
            $failureUrl = CRM_Utils_System::url();
//            CRM_Core_Error::debug_var('thanxUrl1', $thanxUrl);
        }
        return $failureUrl;
    }

    /**
     * @param $invoiceId
     * @return mixed
     */
    protected static function getPaymentProcessorIdViaInvoiceID($invoiceId): int
    {
        try {
            $payment_token = self::getPaymentTokenViaInvoiceID($invoiceId);
            CRM_Core_Error::debug_var('payment_token_getPaymentProcessorIdViaInvoiceID', $payment_token);

            $paymentProcessorId = $payment_token['payment_processor_id'];
            return (int)$paymentProcessorId;

        } catch (CRM_Core_Exception $e) {
            $pP = self::getPaymentProcessorViaProcessorName('Payment2c2p');
            CRM_Core_Error::debug_var('pP_getPaymentProcessorIdViaInvoiceID', $pP);
            $pp = $pP->getPaymentProcessor();
            return (int)$pp['id'];
        }

    }

    protected static function getPaymentProcessorViaInvoiceID($invoiceId): CRM_Core_Payment_Payment2c2p
    {
        $paymentProcessorId = self::getPaymentProcessorIdViaInvoiceID($invoiceId);
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        return $paymentProcessor;
    }

    /**
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    protected static function getPaymentInquiryViaPaymentToken($invoiceID): array
    {
        $payment_token = self::getPaymentTokenViaInvoiceID($invoiceID);
        $paymentToken = $payment_token['token'];
        $paymentProcessorId = $payment_token['payment_processor_id'];
        $paymentProcessor = self::getPaymentProcessorViaProcessorID($paymentProcessorId);
        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
        $url = $payment_processor_array['url_site'] . '/payment/4.1/paymentInquiry';
        $secretkey = $payment_processor_array['password'];
        $merchantID = $payment_processor_array['user_name'];
        $payload = [
            "paymentToken" => $paymentToken,
            "merchantID" => $merchantID,
            "invoiceNo" => $invoiceID,
            "locale" => "en"];
        $inquiryRequestData = self::encodeJwtData($secretkey, $payload);
        $encodedTokenResponse = self::getEncodedResponse($url, $inquiryRequestData);
        $decodedTokenResponse = self::getDecodedResponse($secretkey, $encodedTokenResponse);
        return $decodedTokenResponse;
    }

    /**
     * @param $secretKey
     * @param $payload
     * @return string
     */
    public static function encodeJwtData($secretKey, $payload): string
    {

        $jwt = JWT::encode($payload, $secretKey);

        $data = '{"payload":"' . $jwt . '"}';

        return $data;
    }


    /**
     * @param $url
     * @param $payload
     * @return \Psr\Http\Message\StreamInterface
     * @throws CRM_Core_Exception
     */
    public static function getEncodedResponse($url, $payload)
    {
        $client = new GuzzleHttp\Client();
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
        } catch (GuzzleHttp\Exception\GuzzleException $e) {
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
    public static function getDecodedResponse($secretKey, $response, $responseType = "payload")
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

    /**
     * @param $payloadResponse string
     * @return array
     */
    public static function getDecodedPayload64($payloadResponse): array
    {
        $decodedPayloadString = base64_decode($payloadResponse);
        $decodedPayload = json_decode($decodedPayloadString);
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param $secretKey string
     * @param $payloadResponse string
     * @return array
     */
    public static function getDecodedPayloadJWT($secretKey, $payloadResponse): array
    {
        $decodedPayload = JWT::decode($payloadResponse, $secretKey, array('HS256'));
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @param $invoiceId
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function verifyContribution($invoiceId): void
    {
        //todo recieve only payment
//        CRM_Core_Error::debug_var('invoiceId', $invoiceId);
        $trxnId = substr($invoiceId, 0, CRM_Core_Payment_Payment2c2p::LENTRXNID);
        $contribution = self::getContributionByInvoiceId($invoiceId);
        //try to catch info using PaymentToken
        $paymentInquery = self::getPaymentInquiryViaPaymentToken($invoiceId);
        if ($contribution['contribution_recur_id'] != null) {
            self::saveRecurringTokenValue($invoiceId, $paymentInquery);
        }
        if ("0000" == $paymentInquery['respCode']) {
            //OK
            self::setContributionStatusCompleted($invoiceId, $paymentInquery, $contribution, $trxnId);
            return;
        }
        if ("4200" == $paymentInquery['respCode']) {
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
     * @param $invoiceId
     * @return array|int|mixed
     * @throws CiviCRM_API3_Exception
     */
    public static function getContributionByInvoiceId($invoiceId)
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
        return null;
    }


    /**
     * @param $invoiceId
     * @param array $decodedTokenResponse
     */
    public static function saveRecurringTokenValue($invoiceId, array $decodedTokenResponse): void
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
     * @param $invoiceID
     * @param $paymentProcessorId
     * @return CRM_Financial_DAO_PaymentProcessor
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    public static function getPaymentProcessorViaProcessorID($paymentProcessorId): CRM_Core_Payment_Payment2c2p
    {

        $paymentProcessorInfo = civicrm_api3('PaymentProcessor', 'get', [
            'id' => $paymentProcessorId,
            'sequential' => 1,
        ]);
        $paymentProcessorInfo = $paymentProcessorInfo['values'];
        if (count($paymentProcessorInfo) <= 0) {
            return NULL; //todo raise error
        }
        $paymentProcessorInfo = array_shift($paymentProcessorInfo);
        $paymentProcessor = new CRM_Core_Payment_Payment2c2p(($paymentProcessorInfo['is_test']) ? 'test' : 'live', $paymentProcessorInfo);
        return $paymentProcessor;
    }

    /**
     * @param $paymentProcessorName
     * @return CRM_Core_Payment_Payment2c2p
     * @throws CiviCRM_API3_Exception
     */
    public static function getPaymentProcessorViaProcessorName($paymentProcessorName): CRM_Core_Payment_Payment2c2p
    {
//        CRM_Core_Error::debug_var('paymentProcessorName', $paymentProcessorName);
        $paymentProcessorInfo = civicrm_api3('PaymentProcessor', 'get', [
            'name' => $paymentProcessorName,
            'sequential' => 1,
            'options' => ['limit' => 1, 'sort' => 'id DESC'],
        ]);
        $paymentProcessorInfo = $paymentProcessorInfo['values'];
        if (count($paymentProcessorInfo) <= 0) {
            return NULL;
        }
        $paymentProcessorInfo = array_shift($paymentProcessorInfo);
        $paymentProcessor = new CRM_Core_Payment_Payment2c2p(($paymentProcessorInfo['is_test']) ? 'test' : 'live', $paymentProcessorInfo);
        return $paymentProcessor;
    }

    /**
     * @param $invoiceID
     * @return array|int
     * @throws CRM_Core_Exception
     */
    public static function getPaymentTokenViaInvoiceID($invoiceID)
    {
        try {
            $payment_token = civicrm_api3('PaymentToken', 'getsingle', [
                'masked_account_number' => $invoiceID,
            ]);
        } catch (CiviCRM_API3_Exception $e) {
            CRM_Core_Error::debug_var('API error', $e->getMessage() . "\nInvoiceID: $invoiceID\n");
            throw new CRM_Core_Exception(ts('2c2p - Could not find payment token') . "\nInvoiceID: $invoiceID\n");
        }
        return $payment_token;
    }


    /**
     * @param $invoiceId
     * @param array $decodedTokenResponse
     * @param $contribution
     * @param $trxnId
     * @throws CRM_Core_Exception
     */
    public static function setContributionStatusCompleted($invoiceId, array $decodedTokenResponse, $contribution, $trxnId): void
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
        $failed_status_id = self::contribution_status_id('Failed');
        $cancelled_status_id = self::contribution_status_id('Cancelled');
        $pending_status_id = self::contribution_status_id('Pending');
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
     * @param $contribution
     * @return bool|int|string|null
     * @throws CiviCRM_API3_Exception
     */
    public static function setContributionStatusCancelled($contribution): void
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
    public static function setContributionStatusRefunded($contribution): void
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
     * @param $name
     * @return int|string|null
     */
    public static function contribution_status_id($name)
    {
        return CRM_Utils_Array::key($name, \CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'));
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
        $payment_processor = $paymentProcessor->getPaymentProcessor();
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
        $path_to_2c2p_certificate = self::getOpenCertificatePath();
        $path_to_merchant_pem = self::getClosedCertificatePath();
        $merchant_password = self::getClosedCertificatePwd();
        $answer =
            self::getPaymentFrom2c2pResponse($response_body_contents,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);
//        CRM_Core_Error::debug_var('answer', $answer);

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
        $receiverPublicCertPath = self::getOpenCertificatePath();
        $senderPrivateKeyPath = self::getClosedCertificatePath();
        $senderPrivateKeyPassword = self::getClosedCertificatePwd(); //private key password

        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceId);

        $payment_processor_array = $paymentProcessor->getPaymentProcessor();
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

//        CRM_Core_Error::debug_var('xml', $xml);

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
    private static function getOpenCertificatePath()
    {
        $path = E::path();
        $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . self::OPEN_CERTIFICATE_FILE_NAME;
        return $path_to_2c2p_certificate;
    }

    /**
     * @return string
     */
    private static function getClosedCertificatePath()
    {
        $path = E::path();
        $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . self::CLOSED_CERTIFICATE_FILE_NAME;
        return $path_to_2c2p_certificate;
    }

    /**
     * @return string
     */
    private static function getClosedCertificatePwd()
    {
        return self::CLOSED_CERTIFICATE_PWD;
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
//            CRM_Core_Error::debug_var('success_within_getPaymentFrom2c2pResponse: ', strval($success));

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
            throw new CRM_Core_Exception(ts('2c2p - JWE Error: ') . $e->getMessage());
        }

        $answer = self::unencryptPaymentAnswer($unencrypted_payload, $secretKey);
//            CRM_Core_Error::debug_var('answer_within_getPaymentFrom2c2pResponse', $answer);
        return $answer;
    }



    /**
     * @todo?
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
     * @param $invoiceID
     * @return array
     * @throws CRM_Core_Exception
     */
    public static function setPaymentInquiryViaKeySignature($invoiceID, $status = "", $amount = ""): array
    {
        $paymentProcessor = self::getPaymentProcessorViaInvoiceID($invoiceID);

        $payment_processor = $paymentProcessor->getPaymentProcessor();
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

        $path_to_2c2p_certificate = self::getOpenCertificatePath();
        $path_to_merchant_pem = self::getClosedCertificatePath();
        $merchant_password = self::getClosedCertificatePwd();
        $answer =
            self::getPaymentFrom2c2pResponse($response_body_contents,
                $path_to_2c2p_certificate,
                $path_to_merchant_pem,
                $merchant_password,
                $merchant_secret);
//        CRM_Core_Error::debug_var('answersetPaymentInquiryViaKeySignature', $answer);

        return $answer;
    }

}