<?php
//require_once('../../vendor/autoload.php');

use CRM_Payment2c2p_ExtensionUtil as E;
use \Firebase\JWT\JWT;
use GuzzleHttp\Client;


class CRM_Payment2c2p_TokenRequest
{
    /*
     * Parameter	Data Type	Mandatory	Description
tokenize	B	O	Specify whether display store card checkbox is in payment page. Used to enable tokenization and card token will be returned when payment is approved.
Used by SDK UI
cardTokens	Array
C 255	O	To register card tokens for payment. When a merchant requests to do Payment API later, only the registered card tokens are retrieved
Value must be in string array if multiple card tokens are requested.
Example : ["00001", "00002"]
cardTokenOnly	B	O	Specify whether to allow card token only. This is only applicable for card payment page.
If merchant set to card token only, new card options are not allowed in the payment page.
tokenizeOnly	B	O	Specify whether to require tokenization with authorization
true - Tokenization without authorization
false - Tokenization with authorization (Default)
Used by SDK UI
interestType	A 1	O	Installment interest type
A – All available options (Default)
C – Customer Pay Interest Option ONLY
M – Merchant Pay Interest Option ONLY
installmentPeriodFilter	Array
N 2	O	Select Installment Payment Plan Period that the merchant wants to offer.
Example:
Available periods are 3,6,12,24 months, but if the merchant wants to restrict to 3 & 6 months only, enter the value as [3,6]
Value must be in Integer
productCode	AN 50	O	Installment product code
recurring	B	O	true - enable recurring
false - disable recurring (Default)
invoicePrefix	AN 15	C	Recurring transaction will add 5 additional digit behind invoicePrefix as invoice number.
mandatory if recurring is set to true
recurringAmount	D (12,5)	O	Recurring charge amount. If this value is not set, system will use transaction amount
allowAccumulate	B	C	Allow accumulation of failed Recurring transaction amounts
true - Allow
false - Do not allow
mandatory if recurring is set to true
maxAccumulateAmount	D (12,5)	C	Maximum recurring accumulated amount.
If the maximum accumulated amount is exceeded, recurring feature will be terminated.
mandatory if recurring is set to true
recurringInterval	N 3	C	Recurring interval in days, system will charge every x days
Example:
set to 30 days, payment was made on 1 May 2020,
System will charge next cycle on 1 June 2020 and so on.
mandatory if recurring is set to true
recurringCount	N 5	C	Specify the number of cycles of recurring payment that the system will charge.
mandatory if recurring is set to true
chargeNextDate	N 8	O	The next date of recurring payment
format: ddMMyyyy
Only required if RPP with recurringInterval is set.
If RPP is using chargeOnDate then chargeNextDate is optional. If chargeNextDate is not set, chargeOnDate Date and Month will be used.
chargeOnDate	N 4	C	Indicate Recurring payment charge on specific day of every month.
Date format is ddMM.
dd represents which day of every month that the recurring payment is charged.
MM is only used if field chargeNextDate is not set.
If this value is set, recurringInterval will not be valid.
mandatory if recurring is set to true
paymentExpiry	C 19	O	Duration of time which the customer is allowed to complete payment. Payment completed after the expiry date/time will be rejected.
Date Format : yyyy-MM-dd HH:mm:ss
Default value is 30 minutes
promotionCode	AN 20	O	Promotion code for the payment.

paymentRouteID	C 255	O	Payment routing rules based on custom configuration
fxProviderCode	AN 20	O	Forex provided code used to enable multiple currency payments
fxRateId	C 50	O	Forex rate id
Refer to Exchange Rate API.
originalAmount	D 12,5	O	original currency amount
immediatePayment	B	O	To trigger payment immediately
userDefined1	C 150	O	For merchant to submit merchant's specific data.
userDefined2	C 150	O	For merchant to submit merchant's specific data.
userDefined3	C 150	O	For merchant to submit merchant's specific data.
userDefined4	C 150	O	For merchant to submit merchant's specific data.
userDefined5	C 150	O	For merchant to submit merchant's specific data.
statementDescriptor	AN 20	O	Dynamic statement description
protocolVersion	AN 10	O	3DsProtocol version
Default value is "2.1.0"
eci	N 2	C	Required if parameter protocolVersion or cavv or dsTransactionId is pass in.
cavv	AN 40	C	Required if parameter protocolVersion or cavv or dsTransactionIdis pass in.
protocolVersion	C 36	O	Required if parameter protocolVersion or eci or cavv is pass in.
subMerchants	-	O	Sub merchant list
 	merchantID	AN 25	M	Unique sub merchant ID that is registered with 2C2P
 	invoiceNo	AN 20	M	Unique merchant order number.
Example: 00000000010000091203
*Limited to 12 numericals when requesting for APM payment for Myanmar.
 	amount	D (12,5)	M	Example: 000000002500.90000
 	description	C 255	M	Product description. HTML Encode is required if it contains special characters.
 	loyaltyPoints	 -	O	 List of loyalty points
 	 	providerID	AN 20	O	Loyalty point provider id
 	 	externalMerchantId	AN 50	C	External mercahnt id
 	 	redeemAmount	D 12,5	O	Amount
 	 	rewards	-	O	List of rewards
 	 	 	quantity	N	O	Rewards quantity
locale	C 10	O	Specify payment page and API response localization
Refer to Initialization API for the language code list.
frontendReturnUrl	C 255	O	Specify the return url to be redirected to merchant after payment is completed via 2C2P.
backendReturnUrl	C 255	O	Specify backend url to be notified after payment is completed via 2C2P.
nonceStr	C 32	O	Nonce random string
uiParams	-	O	Extra information parameters
 	userInfo	-	O	Pre-fill User information by merchant
 	 	name	C 50	O	User name
 	 	email	C 150	O	User email
 	 	mobileNo	N 15	O	User mobile no
 	 	countryCode	A 2	O	Address country in A2 of ISO3166 format
 	 	mobileNoPrefix	N 2	O	User mobile no prefix
 	 	currencyCode	A 3	O	User currency code
customerAddress	 -	 O	 Address information array
 	billing	- 	 O	 Billing address object
 	 	 address1	C 255 	M	 Address line 1
 	 	 address2	C 255	O	 Address line 2
 	 	address3	C 255 	O	 Address line 3
 	 	city	C 255 	M	 Address city
 	 	state	C 255	O	 Address state
 	 	postalCode	AN 10 	M	 Address postal code
 	 	countryCode	 A 2	M	Address country in A2 of ISO3166 format
 	shipping	- 	 O	 Shipping address object
 	 	address1	C 255 	M	 Address line 1
 	 	address2	C 255 	O	 Address line 2
 	 	address3	 C 255	O	 Address line 3
 	 	city	C 255 	M	 Address city
 	 	state	C 255 	O	 Address state
 	 	postalCode	AN 10 	M	 Address postal code
 	 	countryCode	 A 2	M	 Address country in A2 of ISO3166 format
3DSecure2Params	- 	O	 3DS 2 Parameters Objects
 	payer	- 	 O	 Payer information
 	 	accountCreateDate	C 8 	O	 Format: YYYYMMDD
 	 	accountAgeIndicator	 C 2	O	 Account Age Indicator
• 01 = No account (guest check-out)
• 02 = Created during this transaction
• 03 = Less than 30 days
• 04 = 30-60 days
• 05 = More than 60 days
 	 	accountChangeDate	C 8 	O	 Format: YYYYMMDD
 	 	accountChangeIndicator	C 2 	O	 Account Change Indicator
• 01 = Changed during this transaction
• 02 = Less than 30 days
• 03 = 30-60 days
• 04 = More than 60 days
 	 	accountPasswordChangeDate	C 8 	O	 Format: YYYYMMDD
 	 	accountPasswordChangeIndicator	 C 2	O	 Account Password Change Indicator
• 01 = No change
• 02 = Changed during this transaction
• 03 = Less than 30 days
• 04 = 30-60 days
• 05 = More than 60 days
 	 	paymentAccountCreateDate	 C 8 	O	 Format: YYYYMMDD
 	 	paymentAccountAgeIndicator	C 2 	O	 Payment Account Age Indicator
• 01 = No account (guest check-out)
• 02 = During this transaction
• 03 = Less than 30 days
• 04 = 30-60 days
• 05 = More than 60 days
 	 	suspiciousAccountActivity	C 2 	O	 Suspicious Account Activity
• 01 = No suspicious activity has been observed
• 02 = Suspicious activity has been observed
 	 	purchaseCountLast6Months	C 4 	O	 Number of purchases with this cardholder account during the previous six months.
maximum is 9999.
 	 	transactionCountLast24Hours	C 3 	O	Number of transactions (successful and abandoned) for this cardholder account with the 3DS Requestor across all payment accounts in the previous 24 hours.
 	 	transactionCountLastYear	 C 3	O	Number of transactions (successful and abandoned) for this cardholder account with the 3DS Requestor across all payment accounts in the previous year.
 	 	provisionAttemptCountLast24Hours	 C 3	O	Number of Add Card attempts in the last 24 hours.
 	 	shippingAddressCreateDate	 C 2	O	Indicates when the shipping address used for this transaction was first used with the 3DS Requestor.
Values accepted:
• 01 = This transaction
• 02 = Less than 30 days
• 03 = 30-60 days
• 04 = More than 60 days
 	 	shippingAddressAgeIndicator	C 2 	O	Indicates when the shipping address used for this transaction was first used with the 3DS Requestor.
Values accepted:
• 01 = This transaction
• 02 = Less than 30 days
• 03 = 30-60 days
• 04 = More than 60 days
 	 	shippingNameIndicator	C 2 	O	Ship Name Indicator
• 01 = Account Name identical to shipping Name
• 02 = Account Name different than shipping Name
 	 	contact	- 	O	 Contact Information
 	 	 	home	 -	 O	 For Home Contact
 	 	 	 	countryCode	 A 2	 O	 Home phone country code.
 	 	 	 	subscriberNo	C 15 	 O	 Home phone subscriber number.
 	 	 	mobile	- 	 O	 For Mobile Contact
 	 	 	 	countryCode	 A 2	 O	 Mobile phone country code.
 	 	 	 	subscriberNo	 C 15	 O	 Mobile phone subscriber number.
 	 	 	work	- 	 O	 For Work Contact
 	 	 	 	countryCode	 A 2	 O	 Workphone country code.
 	 	 	 	subscriberNo	 C 15	 O	 Work phone subscriber number.
 	order	 -	 O	 Merchant Risk Info
 	 	deliveryEmailAddress	 C 254	 O	 Email address
 	 	deliveryTimeframe	 C 2	 O	 deliveryTimeFrame
• 01 = Electronic Delivery
• 02 = Same day shipping
• 03 = Overnight shipping
• 04 = Two-day or more shipping
 	 	giftCardAmount	 C 15	 O	For prepaid or gift card purchase, the purchase amount total of prepaid or gift card(s) in major units (for example, USD 123.45 is 123).
 	 	giftCardCount	 C 2	 O	giftCardCount
valid values 00-99
 	 	giftCardCurrencyCode	 A 3	 O	giftCardCurrency
Currency code in 3 alphabetical values as specified in ISO 4217.
 	 	preOrderDate	 C 8	 O	 Format: YYYYMMDD
 	 	preOrderPurchaseIndicator	 C 2	 O	 Preorder Purchase Indicator
• 01 = Merchandise available
• 02 = Future availability
 	 	reorderItemsIndicator	 C 2	 O	Reorder Items Indicator
• 01 = First time ordered
• 02 = Reordered
 	 	shippingIndicator	C 2 	 O	 Shipping Indicator
• 01 = Ship to cardholder’s billing address
• 02 = Ship to another verified address on file with merchant
• 03 = Ship to address that is different than the cardholder’s billing address
• 04 = “Ship to Store” / Pick-up at local store (Store address shall be populated in shipping address fields)
• 05 = Digital goods (includes online services, electronic gift cards and redemption codes)
• 06 = Travel and Event tickets, not shipped
• 07 = Other (for example, gaming, digital services not shipped, emedia subscriptions, etc.)
loyaltyPoints	 -	 O	List of loyalty points
 	providerID	AN 20	O	Loyalty points provider id
 	accountNo	AN  255	O	Account no
 	externalMerchantID	AN 50	C	External Loyalty Merchant ID
 	redeemAmount	D 12,5	O	Amount
 	rewards	Array	O	List of rewards
 	 	quantity	N	O	Rewards quantity


     */

    public $version = '';

    //merchantID	AN 25	M	Unique merchant ID that is registered with 2C2P
    public $merchantID = '';

    // invoiceNo	AN 50	M	Unique merchant order number.
    // Example: 00000000010000091203
    // Pass through invoice no restriction :
    // *Limited to length of 12 numerals when requesting for APM payment for Myanmar.
    // *Limited to length of 20 alphanumeric when requesting for QR Payment.
    public $invoiceNo = '';

    //idempotencyID	C 100	O	Unique value generated by the merchant that is used to recognize subsequent retries of the same request.
    public $idempotencyID = '';

    public $secretkey = '';


    //description	C 255	M	Product description. HTML Encode is required if it contains special characters.
    public $description = '';

    //amount	D (12,5)	M	Example: 000000002500.90000
    public $amount = '';

    //currencyCode	A 3	M	Transaction currency code in 3 alphabetical values as specified in ISO 4217.
    //If the value is empty, the system
    //will use the merchant's base currency
    public $currencyCode = '';

    //paymentChannel	Array AN 1-6	O	Payment channel required to enable. If this value is empty, "ALL" payment channels will be used.
    //Value must be in string array if multiple payment channels are requested.
    //Example : ["CC", "IPP", "APM", "QR"]
    //Refer to Payment Channel List.
    public $paymentChannel = '';

    //request3DS	A1	O	Specify enable/disable 3ds authentication
    //•	Y – Enable 3ds (default)
    //•	F – Force 3ds
    //•	N – Disable 3ds
    public $request3DS = '';

    public $order_id = '';


    public $customer_email = '';
    public $pay_category_id = '';
    public $promotion = '';
    public $user_defined_1 = '';
    public $user_defined_2 = '';
    public $user_defined_3 = '';
    public $user_defined_4 = '';
    public $user_defined_5 = '';
    public $result_url_1 = '';
    public $result_url_2 = '';
    public $payment_option = '';
    public $enable_store_card = '';
    public $stored_card_unique_id = '';
    public $default_lang = '';
    public $statement_descriptor = '';
    public $hash_value = '';
    public $frontendReturnUrl = '';


    function __construct() {
// @todo?        $this->idempotencyID = random_string(100);
    }
    /**
     * @param $secretkey
     * @param $payloadResponse
     * @return array
     */
    public static function getDecodedPayloadJWT($payloadResponse, $secretkey): array
    {
        $decodedPayload = JWT::decode($payloadResponse, $secretkey, array('HS256'));
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    public static function getDecodedPayload64($payloadResponse, $secretkey = ""): array
    {
        $decodedPayloadString = base64_decode($payloadResponse);
        $decodedPayload = json_decode($decodedPayloadString);
        $decoded_array = (array)$decodedPayload;
        return $decoded_array;
    }

    /**
     * @return string
     */
    public function getJwtData(): string
    {
        $payload = array(
            "merchantID" => $this->merchantID,
            "invoiceNo" => $this->invoiceNo,
            "description" => $this->description,
            "amount" => $this->amount,
            "currencyCode" => $this->currencyCode,
            "frontendReturnUrl" => $this->frontendReturnUrl,
        );

        $jwt = JWT::encode($payload, $this->secretkey);

        $data = '{"payload":"' . $jwt . '"}';

        return $data;
    }

    /**
     * @param $secretkey
     * @param $response
     */
    public static function getDecodedTokenResponse($secretkey, $response, $responsetype = 'payload'): array
    {
//        CRM_Core_Error::debug_var('response', $response);
//        CRM_Core_Error::debug_var('paymentProcessor', $this->_paymentProcessor);

        $decoded = json_decode($response, true);
//        CRM_Core_Error::debug_var('decoded', $decoded);
        if (isset($decoded[$responsetype])) {
            $payloadResponse = $decoded[$responsetype];
            if ($responsetype == 'payload') {
                $decoded_array = self::getDecodedPayloadJWT($payloadResponse, $secretkey);
            }
            if ($responsetype == 'paymentResponse') {
                $decoded_array = self::getDecodedPayload64($payloadResponse);
            }
            return $decoded_array;
        } else {
            return $decoded;
        }
    }


    public static function getEncodedTokenResponse(string $url, string $paymentToken): string
    {
        $client = new Client();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Guzzle';


        $response = $client->request('POST', $url, [
            'body' => $paymentToken,
            'user_agent' => $user_agent,
            'headers' => [
                'Accept' => 'text/plain',
                'Content-Type' => 'application/*+json',
                'X-VPS-Timeout' => '45',
                'X-VPS-VIT-Integration-Product' => 'CiviCRM',
                'X-VPS-Request-ID' => strval(rand(1, 1000000000)),
            ],
        ]);
        return $response->getBody();
    }


    function random_string($length = 12) {

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

        $string = substr(str_shuffle($chars), 0, $length);

        return $string;

    }
}