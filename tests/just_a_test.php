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


//common data
$path = E::path();
$receiverPublicCert = "sandbox-jwt-2c2p.demo.2.1(public).cer";
$path_to_2c2p_certificate = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $receiverPublicCert;
$senderPrivateKeyName = "private.pem"; //merchant generated private key
$path_to_merchant_pem = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $senderPrivateKeyName; //merchant generated private key
$merchant_password = "octopus8"; //private key password
$merchant_secret = "2FC22F51DBF485FC7821005B9BAF98BE609D28BAE12977039D59FB991B42B999";    //Get SecretKey from 2C2P PGW Dashboard
$merchant_id = "702702000001066";        //Get MerchantID when opening account with 2C2P
$date = date('Y-m-d');
$time_stamp = date('dmyhis', strtotime($date) . ' +1 day');
//print("\n$time_stamp\n");
//inquery

$invoiceNo = "9915d5b87e54e9cc7e7f0cc9cfa1c967";

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

$response = CRM_Core_Payment_Payment2c2p::getPaymentInquiryViaKeySignature(
    $invoiceNo
);



print_r($response);

