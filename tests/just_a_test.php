<?php
require_once __DIR__ . '../../vendor/autoload.php';

use CRM_Payment2c2p_ExtensionUtil as E;
use Jose\Easy\JWT;

$path = E::path();
//$path = '..';
//$a = \Civi\Cxn\Rpc\X509Util::loadCACert();
$receiverPublicCert = "sandbox-jwt-2c2p.demo.2.1(public).cer";
$receiverPublicCertPath = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $receiverPublicCert;
//echo $cerpkscFile;


//string senderPrivateKeyPath = "C:/cert/Merchant12345.pfx"; //merchant generated private key
$senderPrivateKeyName = "private.pem"; //merchant generated private key
$senderPrivateKeyPath = $path . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . $senderPrivateKeyName; //merchant generated private key

$senderPrivateKeyPassword = "octopus8"; //private key password


use Jose\Component\Core\AlgorithmManager;
use \Jose\Component\Encryption\Algorithm\KeyEncryption\RSAOAEP;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\Compression\Deflate;
use Jose\Component\Encryption\JWEBuilder;

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
$jwePaymentBuilder = new JWEBuilder(
    $keyEncryptionAlgorithmManager,
    $contentEncryptionAlgorithmManager,
    $compressionMethodManager
);

use Jose\Component\KeyManagement\JWKFactory;


// Our key.
$receiverPublicCertKey = JWKFactory::createFromCertificateFile(
    $receiverPublicCertPath, // The filename
);

$merchantID = "702702000001066";		//Get MerchantID when opening account with 2C2P
$secretKey = "2FC22F51DBF485FC7821005B9BAF98BE609D28BAE12977039D59FB991B42B999";	//Get SecretKey from 2C2P PGW Dashboard

//Request Information
/*
Process Type:
    I = Inquiry RPP information
    U = Update RPP information
    C = Cancel RPP
*/
$version = "2.1";
$processType = "I" ;
$recurringUniqueID = "170966";
$timeStamp = "080622155002";
$recurringStatus = "";
$amount = "";
$allowAccumulate = "";
$maxAccumulateAmount= "";
$recurringInterval = "";
$recurringCount = "";
$chargeNextDate="";

//Construct signature string
$stringToHash = $version . $merchantID . $timeStamp. $recurringUniqueID . $processType . $recurringStatus . $amount . $allowAccumulate . $maxAccumulateAmount . $recurringInterval . $recurringCount . $chargeNextDate;
$hash = strtoupper(hash_hmac('sha1', $stringToHash ,$secretKey, false));	//Compute hash value

//Construct request message
$xml = "<RecurringMaintenanceRequest>
			<version>$version</version> 
			<merchantID>$merchantID</merchantID>
			<timeStamp>$timeStamp</timeStamp>
			<recurringUniqueID>$recurringUniqueID</recurringUniqueID>
			<processType>$processType</processType>
			<recurringStatus>$recurringStatus</recurringStatus>
			<amount>$amount</amount>
			<allowAccumulate>$allowAccumulate</allowAccumulate>
			<maxAccumulateAmount>$maxAccumulateAmount</maxAccumulateAmount>
			<recurringInterval>$recurringInterval</recurringInterval>
			<recurringCount>$recurringCount</recurringCount>
			<chargeNextDate>$chargeNextDate</chargeNextDate>
			<hashValue>$hash</hashValue>
			</RecurringMaintenanceRequest>";

$jwe = $jwePaymentBuilder
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

use Jose\Component\Encryption\Serializer\CompactSerializer as CompactSerializerE;

$serializerE = new CompactSerializerE(); // The serializer

$jwerequest = $serializerE->serialize($jwe, 0); // We serialize the recipient at index 0 (we only have one recipient).

//echo "token1:\n";
//print($jwerequest);
//echo "\n";

use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\JWSBuilder;

// The algorithm manager with the HS256 algorithm.
$algorithmManager = new AlgorithmManager([
    new PS256(),
]);


// Our key.
//echo "jwk:\n";
$jwsk = JWKFactory::createFromKeyFile(
    $senderPrivateKeyPath,
    $senderPrivateKeyPassword
);


$jwsBuilder = new JWSBuilder($algorithmManager);

$jws = $jwsBuilder
    ->create()// We want to create a new JWS
    ->withPayload($jwerequest)// We set the payload
    ->addSignature($jwsk, ['alg' => 'PS256', 'typ' => 'JWT'])// We add a signature with a simple protected header
    ->build();

use Jose\Component\Signature\Serializer\CompactSerializer as CompactSerializerS;

$serializerS = new CompactSerializerS(); // The serializer

$token = $serializerS->serialize($jws, 0); // We serialize the signature at index 0 (we only have one signature).

//echo "token2:\n";
//print_r($token);
//echo "\n";

$url = "https://demo2.2c2p.com/2C2PFrontend/PaymentAction/2.0/action";
$client = new GuzzleHttp\Client();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';

try {
    $response = $client->request('POST', $url, [
        'body' => $token,
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
    print("\n1:\n".$e->getMessage());
}
try {
    $response = $client->request('POST', $url, [
        'body' => $token,
        'user_agent' => $user_agent,
        'headers' => [
            'Accept' => 'text/plain',
            'Content-Type' => 'application/*+json',
            'X-VPS-Timeout' => '45',
//            'X-VPS-VIT-Integration-Product' => 'CiviCRM',
//            'X-VPS-Request-ID' => strval(rand(1, 1000000000)),
        ],
    ]);
    $response_body = $response->getBody()->getContents();
} catch (GuzzleHttp\Exception\GuzzleException $e) {
    print("\n2:\n".$e->getMessage());
}
echo "\n2:\n";
echo $response_body;
echo "\n";
//print_r((array)$response);
echo "\njws\n";
use Jose\Component\Signature\JWSLoader;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Jose\Component\Signature\JWSVerifier;
// The algorithm manager with the HS256 algorithm.
$algorithmManager = new AlgorithmManager([
    new PS256(),
]);

// We instantiate our JWS Verifier.
$jwsVerifier = new JWSVerifier(
    $algorithmManager
);
$serializerManagerS = new JWSSerializerManager([
    new CompactSerializerS(),
]);
use Jose\Component\Checker\HeaderCheckerManager;
use Jose\Component\Checker\AlgorithmChecker;
use Jose\Component\Signature\JWSTokenSupport;

$headerCheckerManagerS = new HeaderCheckerManager(
    [
        new AlgorithmChecker(['PS256']),
        // We want to verify that the header "alg" (algorithm)
        // is present and contains "HS256"
    ],
    [
        new JWSTokenSupport(), // Adds JWS token type support
    ]
);

try{
    $jws = $serializerS->unserialize($response_body);
    $isVerified = $jwsVerifier->verifyWithKey($jws, $receiverPublicCertKey, 0);
if($isVerified){

    $jwsLoader = new JWSLoader(
        $serializerManagerS,
        $jwsVerifier,
        $headerCheckerManagerS
    );

    $jwsloaded = $jwsLoader->loadAndVerifyWithKey((string) $response_body, $receiverPublicCertKey, $signature, null);
    echo $isVerified;
    $enPa = $jwsloaded->getEncodedPayload();
    echo "\ngetEnPayload! $enPa\n";
    $gePa = $jwsloaded->getPayload();
    echo "\ngetPayload! $gePa\n";
    echo "\nV!\n";
}else{
    echo "\nN!\n";
}

}catch (Exception $e){
    echo "\njws error\n";
    echo $e->getMessage();
}
echo "\njwe\n";
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\JWELoader;
use Jose\Component\Encryption\Serializer\JWESerializerManager;
use Jose\Component\Encryption\JWETokenSupport;
try{
//$jwe = $serializerE->unserialize($enPa);
//echo $jwe->getPayload();
    echo "\njwe\n";
    $serializerManagerE = new JWESerializerManager([
        new $serializerE(),
    ]);

    $jwe = $serializerE->unserialize($gePa);
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
echo $jwe->getPayload();
print_r((array) $jwe);
    $headerCheckerManagerE = new HeaderCheckerManager(
        [
            new AlgorithmChecker(['RSA-OAEP']),
            // We want to verify that the header "alg" (algorithm)
            // is present and contains "HS256"
        ],
        [
            new JWETokenSupport(), // Adds JWS token type support
        ]
    );
    $success = $jweDecrypter->decryptUsingKey($jwe, $jwsk, 0);
    echo $success;
    $jweLoader = new JWELoader(
        $serializerManagerE,
        $jweDecrypter,
        $headerCheckerManagerE
    );
    $jwe = $jweLoader->loadAndDecryptWithKey($gePa, $jwsk, $recipient);
    print $jwe->getPayload();
}catch (Exception $e){
    echo "\njwe error\n";
    echo $e->getMessage();
}
