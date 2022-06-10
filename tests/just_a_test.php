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
$payment_inquiry = array(
    'version' => "3.8",
    'processType' => "I",
    'invoiceNo' => "2e45878eed7d9377b4fb6c7cf94c7440",
    'timeStamp' => $time_stamp,
    'merchantID' => $merchant_id,
    'actionAmount' => "10",
    'request_type' => "PaymentProcessRequest"
);

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

$receiverPublicCertPath = $path_to_2c2p_certificate;
$senderPrivateKeyPath = $path_to_merchant_pem;
$senderPrivateKeyPassword = $merchant_password; //private key password
$merchantID = $merchant_id;        //Get MerchantID when opening account with 2C2P
$secretKey = $merchant_secret;    //Get SecretKey from 2C2P PGW Dashboard

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


//Request Information
/*
Process Type:
    I = Inquiry RPP information
    U = Update RPP information
    C = Cancel RPP
*/
$merchantID = CRM_Utils_Array::value('merchantID', $payment_inquiry, $merchantID);
$version = CRM_Utils_Array::value('version', $payment_inquiry, "");
$processType = CRM_Utils_Array::value('processType', $payment_inquiry, "");
$timeStamp = CRM_Utils_Array::value('timeStamp', $payment_inquiry, "");
$invoiceNo = CRM_Utils_Array::value('invoiceNo', $payment_inquiry, "");
$amount = CRM_Utils_Array::value('amount', $payment_inquiry, "");
$actionAmount = CRM_Utils_Array::value('actionAmount', $payment_inquiry, 0);

$recurringStatus = CRM_Utils_Array::value('recurringStatus', $payment_inquiry);
$recurringUniqueID = CRM_Utils_Array::value('recurringUniqueID', $payment_inquiry);
$allowAccumulate = CRM_Utils_Array::value('allowAccumulate', $payment_inquiry);
$maxAccumulateAmount = CRM_Utils_Array::value('maxAccumulateAmount', $payment_inquiry);
$recurringInterval = CRM_Utils_Array::value('recurringInterval', $payment_inquiry);
$recurringCount = CRM_Utils_Array::value('recurringCount', $payment_inquiry);
$chargeNextDate = CRM_Utils_Array::value('chargeNextDate', $payment_inquiry);

//Construct signature string
//Construct request message

    $stringToHashOne = "";
    $stringXML = "<" . $payment_inquiry["request_type"] . ">";
    foreach ($payment_inquiry as $key => $value) {
        if ($key != "request_type") {
            $stringToHashOne = $stringToHashOne . $value;
            $stringXML = $stringXML . "<" . $key . ">" . $value . "</" . $key . ">";
        }
    }
    $hashone = strtoupper(hash_hmac('sha1', $stringToHashOne, $secretKey, false));    //Compute hash value
    $stringXML = $stringXML ."<hashValue>$hashone</hashValue>";
    $stringXML = $stringXML . "</" . $payment_inquiry["request_type"] . ">";
    $xml = "<PaymentProcessRequest><version>$version</version><timeStamp>$timeStamp</timeStamp><merchantID>$merchantID</merchantID><invoiceNo>$invoiceNo</invoiceNo><processType>$processType</processType><actionAmount>$actionAmount</actionAmount><hashValue>$hashone</hashValue></PaymentProcessRequest>";
    $xml = $stringXML;


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
    ->addSignature($jw_signature_key, ['alg' => 'PS256', 'typ' => 'JWT'])// We add a signature with a simple protected header
    ->build();


$signature_serializer = new SignatureCompactSerializer(); // The serializer

$jw_signed_payload = $signature_serializer->serialize($jw_signed_request, 0); // We serialize the signature at index 0 (we only have one signature).
$url = "https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action";
$client = new GuzzleHttp\Client();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';
//
//try {
//    $response = $client->request('POST', $url, [
//        'body' => $jw_signed_payload,
//        'user_agent' => $user_agent,
//        'headers' => [
//            'Accept' => 'text/plain',
//            'Content-Type' => 'application/*+json',
//            'X-VPS-Timeout' => '45',
////            'X-VPS-VIT-Integration-Product' => 'CiviCRM',
////            'X-VPS-Request-ID' => strval(rand(1, 1000000000)),
//        ],
//    ]);
//} catch (GuzzleHttp\Exception\GuzzleException $e) {
//    print("\n1:\n" . $e->getMessage());
//}

try {
    $response = $client->request('POST', $url, [
        'body' => $jw_signed_payload,
        'user_agent' => $user_agent,
        'headers' => [
            'Accept' => 'text/plain',
            'Content-Type' => 'application/*+json',
            'X-VPS-Timeout' => '45',
//            'X-VPS-VIT-Integration-Product' => 'CiviCRM',
//            'X-VPS-Request-ID' => strval(rand(1, 1000000000)),
        ],
    ]);

} catch (GuzzleHttp\Exception\GuzzleException $e) {
    print("\n2:\n" . $e->getMessage());
}


$answer =
    CRM_Core_Payment_Payment2c2p::getPaymentFrom2c2pResponse($response,
        $path_to_2c2p_certificate,
        $path_to_merchant_pem,
        $merchant_password,
        $merchant_secret);


print_r($answer);

