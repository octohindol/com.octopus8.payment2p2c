<?php
require_once __DIR__ . '../../vendor/autoload.php';

use CRM_Payment2c2p_ExtensionUtil as E;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Encryption\{Algorithm\KeyEncryption\RSAOAEP,
    Algorithm\ContentEncryption\A256GCM,
    Compression\CompressionMethodManager,
    Compression\Deflate,
    JWEBuilder,
    Serializer\CompactSerializer as EncryptionCompactSerializer,
};
use Jose\Component\Signature\{Algorithm\PS256,
    JWSBuilder,

    Serializer\CompactSerializer as SignatureCompactSerializer};


$invoiceNo = "31282fc8656e5fd6405e974a40183b7b";

//common data
/**
 * @param string $invoiceNo
 * @throws CRM_Core_Exception
 */
function printInvoiceInfo(string $invoiceNo): void
{
    $path = E::path();
    $receiverPublicCert = "sandbox-jwt-2c2p.demo.2.1(public).cer";
    $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $receiverPublicCert;
    $senderPrivateKeyName = "private.pem"; //merchant generated private key
    $path_to_merchant_pem = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $senderPrivateKeyName; //merchant generated private key
    $merchant_password = "octopus8"; //private key password
    $merchant_secret = "2FC22F51DBF485FC7821005B9BAF98BE609D28BAE12977039D59FB991B42B999";    //Get SecretKey from 2C2P PGW Dashboard
    $merchant_id = "702702000001066";        //Get MerchantID when opening account with 2C2P
    $date = date('Y-m-d h:i:s');
    $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
//print("\n$time_stamp\n");
//inquery

    $processType = "I";
$request_type = "PaymentProcessRequest";
    $version = "3.8";
//    $version = "2.1";
//    $request_type = "RecurringMaintenanceRequest";
//    $recurringUniqueID = "4992455";

//$payment_inquiry = array (
//    'request_type' => 'RecurringMaintenanceRequest',
//            'version' => '2.1',
//            'timeStamp' => $time_stamp,
//            'merchantID' => $merchant_id,
//            'recurringUniqueID' => '170966',
//            'processType' => 'I',
//            'recurringStatus' => 'Y',
//            'amount' => '99.90',
//            'allowAccumulate' => '',
//            'maxAccumulateAmount' => '',
//            'recurringInterval' => '30',
//            'recurringCount' => '12',
//            'chargeNextDate' => '',
//            'chargeOnDate' => '',
//);

    $response = CRM_Payment2c2p_Utils::getPaymentInquiryViaKeySignature(
        $invoiceNo,
        $processType,
        $request_type,
        $version,
//        $recurringUniqueID
    );


    print_r($response);
}

function printRecurInvoiceInfo(string $invoiceNo): void
{
    $path = E::path();
    $receiverPublicCert = "sandbox-jwt-2c2p.demo.2.1(public).cer";
    $path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $receiverPublicCert;
    $senderPrivateKeyName = "private.pem"; //merchant generated private key
    $path_to_merchant_pem = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $senderPrivateKeyName; //merchant generated private key
    $merchant_password = "octopus8"; //private key password
    $merchant_secret = "2FC22F51DBF485FC7821005B9BAF98BE609D28BAE12977039D59FB991B42B999";    //Get SecretKey from 2C2P PGW Dashboard
    $merchant_id = "702702000001066";        //Get MerchantID when opening account with 2C2P
    $date = date('Y-m-d h:i:s');
    $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
//print("\n$time_stamp\n");
//inquery

    $processType = "I";
//$request_type = "PaymentProcessRequest";
    $version = "2.1";
    $request_type = "RecurringMaintenanceRequest";
    $recurringUniqueID = "";
    try {
        $paymentInquiry = CRM_Payment2c2p_Utils::getPaymentInquiryViaPaymentToken($invoiceNo);
        $recurringUniqueID = $paymentInquiry['recurringUniqueID'];
    } catch (CRM_Core_Exception $e) {
        print $e->getMessage();
    }
//$payment_inquiry = array (
//    'request_type' => 'RecurringMaintenanceRequest',
//            'version' => '2.1',
//            'timeStamp' => $time_stamp,
//            'merchantID' => $merchant_id,
//            'recurringUniqueID' => '170966',
//            'processType' => 'I',
//            'recurringStatus' => 'Y',
//            'amount' => '99.90',
//            'allowAccumulate' => '',
//            'maxAccumulateAmount' => '',
//            'recurringInterval' => '30',
//            'recurringCount' => '12',
//            'chargeNextDate' => '',
//            'chargeOnDate' => '',
//);

    $response = CRM_Payment2c2p_Utils::getPaymentInquiryViaKeySignature(
        $invoiceNo,
        $processType,
        $request_type,
        $version,
        $recurringUniqueID
    );


    print_r($response);
}

function printInvoiceInfoViaPaymentToken(string $invoiceNo): void
{
    $path = E::path();
    $merchant_secret = "2FC22F51DBF485FC7821005B9BAF98BE609D28BAE12977039D59FB991B42B999";    //Get SecretKey from 2C2P PGW Dashboard
    $merchant_id = "702702000001066";        //Get MerchantID when opening account with 2C2P
    $date = date('Y-m-d h:i:s');
    $time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
//print("\n$time_stamp\n");
//inquery

    $processType = "I";
//$request_type = "PaymentProcessRequest";
    $version = "2.1";
    $request_type = "RecurringMaintenanceRequest";
    $recurringUniqueID = "";
    $paymentInquiry = CRM_Payment2c2p_Utils::getPaymentInquiryViaPaymentToken($invoiceNo);
//$payment_inquiry = array (
//    'request_type' => 'RecurringMaintenanceRequest',
//            'version' => '2.1',
//            'timeStamp' => $time_stamp,
//            'merchantID' => $merchant_id,
//            'recurringUniqueID' => '170966',
//            'processType' => 'I',
//            'recurringStatus' => 'Y',
//            'amount' => '99.90',
//            'allowAccumulate' => '',
//            'maxAccumulateAmount' => '',
//            'recurringInterval' => '30',
//            'recurringCount' => '12',
//            'chargeNextDate' => '',
//            'chargeOnDate' => '',
//);

    print_r($paymentInquiry);
}

function runScheduledContributionsList(): void
{
    $payment_processor = CRM_Payment2c2p_Utils::getPaymentProcessorViaProcessorName('Payment2c2p');
    $payment_processor_array = $payment_processor->getPaymentProcessor();
    $response = CRM_Payment2c2p_Utils::process_recurring_payments($payment_processor_array);

    print_r($response);
}

//printInvoiceInfoViaPaymentToken($invoiceNo);
//printInvoiceInfo($invoiceNo);
//printRecurInvoiceInfo($invoiceNo);

runScheduledContributionsList();