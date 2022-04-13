<?php

use CRM_Payment2c2p_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

/**
 * FIXME - Add test description.
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class CRM_Core_Payment_Payment2c2pTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface
{

    use \Civi\Test\GuzzleTestTrait;
    use \Civi\Test\Api3TestTrait;

    public function setUpHeadless()
    {
        // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
        // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
        return \Civi\Test::headless()
            ->installMe(__DIR__)
            ->apply();
    }

    public function setUp(): void
    {
        $this->setUpPayment2c2pProcessor();
        $this->processor = \Civi\Payment\System::singleton()->getById($this->ids['PaymentProcessor']['Payment2c2p']);
        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->callAPISuccess('PaymentProcessor', 'delete', ['id' => $this->ids['PaymentProcessor']['Payment2c2p']]);
        parent::tearDown();
    }

    public function _testVersion()
    {
        $version = $this->processor->getCurrentVersion();
        $this->assertSame('8.5', $this->processor->getCurrentVersion());
        return $version;
    }

    public function testGetPaymentToken()
    {
        $merchantId = 'JT01';        //Get MerchantID when opening account with 2C2P
        $secretKey = 'ECC4E54DBA738857B84A7EBC6B5DC7187B8DA68750E88AB53AAA41F548D6F2D9';    //Get SecretKey from 2C2P PGW Dashboard
        $invoiceNo = '1523953661';
        $description = 'item 1';
        $amount = 10.00;
        $currencyCode = 'SGD';
        $payload = $this->processor->createPaymentTokenRequest($secretKey, $merchantId, $invoiceNo, $description, $amount, $currencyCode);
        $this->assertSame('{"payload":"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJtZXJjaGFudElEIjoiSlQwMSIsImludm9pY2VObyI6IjE1MjM5NTM2NjEiLCJkZXNjcmlwdGlvbiI6Iml0ZW0gMSIsImFtb3VudCI6MTAsImN1cnJlbmN5Q29kZSI6IlNHRCIsImZyb250ZW5kUmV0dXJuVXJsIjoiaHR0cDpcL1wvbG9jYWxob3N0OjMzMDZcLyJ9.PmmU8oNf4dwxFPXh9Q6MeidyX4X2XdM1GZAcQ1tSKHY"}', $payload);
        return $payload;
    }

    public function _testDecodedTokenResponse()
    {
        $response = '{"paymentResponse":"eyJsb2NhbGUiOm51bGwsImludm9pY2VObyI6IjE2NDk3MjUzMzQiLCJjaGFubmVsQ29kZSI6IkNDIiwicmVzcENvZGUiOiIyMDAwIiwicmVzcERlc2MiOiJUcmFuc2FjdGlvbiBpcyBjb21wbGV0ZWQsIHBsZWFzZSBkbyBwYXltZW50IGlucXVpcnkgcmVxdWVzdCBmb3IgZnVsbCBwYXltZW50IGluZm9ybWF0aW9uLiJ9"}';
        $secretKey = 'ECC4E54DBA738857B84A7EBC6B5DC7187B8DA68750E88AB53AAA41F548D6F2D9';    //Get SecretKey from 2C2P PGW Dashboard
        $payload = $this->processor->getDecodedTokenResponse($response, $secretKey, 'paymentResponse');
        print_r($payload);
        $this->assertSame('0000', $payload['respCode']);
        return $payload;
    }

    public function _testPaymentTokenRequestResponse()
    {
        $merchantId = 'JT01';        //Get MerchantID when opening account with 2C2P
        $secretKey = 'ECC4E54DBA738857B84A7EBC6B5DC7187B8DA68750E88AB53AAA41F548D6F2D9';    //Get SecretKey from 2C2P PGW Dashboard
        $invoiceNo = time();
        $description = 'item 1';
        $amount = 10.00;
        $currencyCode = 'SGD';
        $processor_name = 'Payment2c2p';
        $params = [];
        $params['qfKey'] = '123';
        $params['participantID'] = '323';
        $params['eventID'] = '232';
        $params['invoiceID'] = $invoiceNo;
        $returnUrl = $this->processor->getReturnUrl($processor_name, $params, $component = 'contribute');

        $frontendReturnUrl = $returnUrl;
        $url = 'https://sandbox-pgw.2c2p.com/payment/4.1/PaymentToken';
        $payload = $this->processor->getPaymentPayload($secretKey, $merchantId, $invoiceNo, $description, $amount, $currencyCode, $frontendReturnUrl);
//        print_r($payload);
        $encodedTokenResponse = $this->processor->getEncodedTokenResponse($url, $payload);
//        print_r($encodedTokenResponse);
        $decodedTokenResponse = $this->processor->getDecodedTokenResponse($secretKey, $encodedTokenResponse);
        print_r($decodedTokenResponse);
        $this->assertSame('0000', $decodedTokenResponse['respCode']);
        return $decodedTokenResponse;
    }

    public function _testGetReturnUrl()
    {
        $processor_name = 'demoPayment2c2p';
        $params = [];
        $params['qfKey'] = '123';
        $params['participantID'] = '323';
        $params['eventID'] = '232';
        $params['invoiceID'] = '131';
        $returnUrl = $this->processor->getReturnUrl($processor_name, $params, $component = 'contribute');
        print "\n";
        print $returnUrl;
        $this->assertSame('http://localhost:3306/civicrm/payment/ipn?processor_name=demoPayment2c2p&md=contribute&qfKey=123&inId=131', $returnUrl);
    }
    /**
     * Test making a call to 2c2p
     */
//  public function testSinglePayment(): void {
//    $this->setupMockHandler();
//    $params = $this->getBillingParams();
//    $params['amount'] = 20.00;
//    $params['currency'] = 'AUD';
//    $params['description'] = 'Test Contribution';
//    $params['invoiceID'] = 'xyz';
//    $params['email'] = 'unittesteway@civicrm.org';
//    $params['ip_address'] = '127.0.0.1';
//    foreach ($params as $key => $value) {
//      // Paypal is super special and requires this. Leaving out of the more generic
//      // get billing params for now to make it more obvious.
//      // When/if PropertyBag supports all the params paypal needs we can convert & simplify this.
//      $params[str_replace('-5', '', str_replace('billing_', '', $key))] = $value;
//    }
//    $params['state_province'] = 'NSW';
//    $params['country'] = 'AUS';
//    $params['contributionType_accounting_code'] = 4200;
//    $params['installments'] = 1;
//    $this->processor->doPayment($params);
//    $this->assertEquals($this->getExpectedSinglePaymentRequests(), $this->getRequestBodies());
//  }

    /**
     * Test making a once off payment
     */
    public function _testSinglePayment(): void
    {
        $this->setupMockHandler(); //?
        $params = $this->getBillingParams();
        $params['amount'] = 20.00;
        $params['currency'] = 'SGD';
        $params['description'] = 'Test Contribution';
        $params['invoiceID'] = '123123123123xyz';
        $params['email'] = 'unittesteway@civicrm.org';
        $params['qfKey'] = '123';
        $params['participantID'] = '323';
        $params['eventID'] = '232';

        foreach ($params as $key => $value) {
            // Paypal is super special and requires this. Leaving out of the more generic
            // get billing params for now to make it more obvious.
            // When/if PropertyBag supports all the params paypal needs we can convert & simplify this.
            $params[str_replace('-5', '', str_replace('billing_', '', $key))] = $value;
        }
        $params['state_province'] = 'NSW';
        $params['country'] = 'AUS';
        $params['installments'] = 1;
        $this->processor->doPayment($params);
        $this->assertEquals($this->getExpectedSinglePaymentRequests(), $this->getRequestBodies());
    }

    /**
     * Test making a recurring payment
     */
//  public function testRecuringPayment(): void {
//    $this->setupMockHandler(NULL, FALSE, TRUE);
//    $params = $this->getBillingParams();
//    $params['amount'] = 20.00;
//    $params['currency'] = 'AUD';
//    $params['description'] = 'Test Contribution';
//    $params['invoiceID'] = 'xyz';
//    $params['email'] = 'unittesteway@civicrm.org';
//    $params['ip_address'] = '127.0.0.1';
//    foreach ($params as $key => $value) {
//      // Paypal is super special and requires this. Leaving out of the more generic
//      // get billing params for now to make it more obvious.
//      // When/if PropertyBag supports all the params paypal needs we can convert & simplify this.
//      $params[str_replace('-5', '', str_replace('billing_', '', $key))] = $value;
//    }
//    $params['state_province'] = 'NSW';
//    $params['country'] = 'AUS';
//    $params['contributionType_accounting_code'] = 4200;
//    $params['installments'] = 13;
//    $params['is_recur'] = 1;
//    $params['frequency_unit'] = 'month';
//    $params['frequency_interval'] = 1;
//    $this->processor->doPayment($params);
//    $this->assertEquals($this->getExpectedRecuringPaymentRequests(), $this->getRequestBodies());
//  }

    /**
     * Test making a failed once off payment
     */
//  public function testErrorSinglePayment(): void {
//    $this->setupMockHandler(NULL, TRUE);
//    $params = $this->getBillingParams();
//    $params['amount'] = 2220.00;
//    $params['currency'] = 'AUD';
//    $params['description'] = 'Test Contribution';
//    $params['invoiceID'] = 'xyz';
//    $params['email'] = 'unittesteway@civicrm.org';
//    $params['ip_address'] = '127.0.0.1';
//    foreach ($params as $key => $value) {
//      // Paypal is super special and requires this. Leaving out of the more generic
//      // get billing params for now to make it more obvious.
//      // When/if PropertyBag supports all the params paypal needs we can convert & simplify this.
//      $params[str_replace('-5', '', str_replace('billing_', '', $key))] = $value;
//    }
//    $params['state_province'] = 'NSW';
//    $params['country'] = 'AUS';
//    $params['contributionType_accounting_code'] = 4200;
//    $params['installments'] = 1;
//    try {
//      $this->processor->doPayment($params);
//      $this->fail('Test was meant to throw an exception');
//    }
//    catch (\Civi\Payment\Exception\PaymentProcessorException $e) {
//      $this->assertEquals('Your transaction was declined   ', $e->getMessage());
//      $this->assertEquals(9009, $e->getErrorCode());
//    }
//  }

    /**
     * Get some basic billing parameters.
     *
     * These are what are entered by the form-filler.
     *
     * @return array
     */
    protected function getBillingParams(): array
    {
        return [
            'billing_first_name' => 'John',
            'billing_middle_name' => '',
            'billing_last_name' => "O'Connor",
            'billing_street_address-5' => '8 Hobbitton Road',
            'billing_city-5' => 'The Shire',
            'billing_state_province_id-5' => 1012,
            'billing_postal_code-5' => 5010,
            'billing_country_id-5' => 1228,
            'credit_card_number' => '4111111111111111',
            'cvv2' => 123,
            'credit_card_exp_date' => [
                'M' => 9,
                'Y' => 2025,
            ],
            'credit_card_type' => 'Visa',
            'year' => 2022,
            'month' => 10,
        ];
    }

    public function setUpPayment2c2pProcessor(): void
    {
        $paymentProcessorType = $this->callAPISuccess('PaymentProcessorType', 'get', ['name' => 'Payment2c2p']);
        $this->callAPISuccess('PaymentProcessorType', 'create', ['id' => $paymentProcessorType['id'], 'is_active' => 1]);
        $params = [
            'name' => 'demoPayment2c2p',
            'domain_id' => CRM_Core_Config::domainID(),
            'payment_processor_type_id' => 'Payment2c2p',
            'is_active' => 1,
            'is_default' => 0,
            'is_test' => 0,
            'user_name' => 'JT01',
            'password' => 'ECC4E54DBA738857B84A7EBC6B5DC7187B8DA68750E88AB53AAA41F548D6F2D9',
            'url_site' => 'https://sandbox-pgw.2c2p.com/payment/4.1/PaymentToken',
            'class_name' => 'Payment_Payment2c2p',
            'billing_mode' => 4,
            'financial_type_id' => 1,
            'financial_account_id' => 12,
            // Credit card = 1 so can pass 'by accident'.
            'payment_instrument_id' => 'Debit Card',
            'signature' => 'Payment2c2p',
        ];
        if (!is_numeric($params['payment_processor_type_id'])) {
            // really the api should handle this through getoptions but it's not exactly api call so lets just sort it
            //here
            $params['payment_processor_type_id'] = $this->callAPISuccess('payment_processor_type', 'getvalue', [
                'name' => $params['payment_processor_type_id'],
                'return' => 'id',
            ], 'integer');
        }

        $paymentProcessorId = $this->checkPaymentProcessorIsPresent();

        if (is_numeric($paymentProcessorId)) {
            $processorID = $this->activatePaymentProcessor($paymentProcessorId);
        }

        if (!is_numeric($paymentProcessorId)) {
            $processorID = $this->createPaymentProcessor($params);
        }
        $this->setupMockHandler($processorID);
        $this->ids['PaymentProcessor']['Payment2c2p'] = $processorID;
    }

    /**
     * Add a mock handler to the Payflow Pro processor for testing.
     *
     * @param int|null $id
     * @param bool $error
     * @param bool $recurring
     *
     * @throws \CiviCRM_API3_Exception
     */
    protected function setupMockHandler($id = NULL, $error = FALSE, $recurring = FALSE): void
    {
        if ($id) {
            $this->processor = Civi\Payment\System::singleton()->getById($id);
        }
        $responses = $error ?
            $this->getExpectedSinglePaymentErrorResponses() :
            ($recurring
                ? $this->getExpectedRecurringPaymentResponses()
                : $this->getExpectedSinglePaymentResponses());
        // Comment the next line out when trying to capture the response.
        // see https://github.com/civicrm/civicrm-core/pull/18350
//    $this->createMockHandler($responses);
        $this->setUpClientWithHistoryContainer();
        $this->processor->setGuzzleClient($this->getGuzzleClient());
    }

    /**
     * Get the expected response from Payflow Pro for a single payment.
     *
     * @return array
     */
    public function getExpectedSinglePaymentResponses(): array
    {
        return [
            'RESULT=0&PNREF=A80N0E942869&RESPMSG=Approved&AUTHCODE=028703&AVSADDR=Y&AVSZIP=Y&CVV2MATCH=Y&HOSTCODE=000&RESPTEXT=AP&PROCAVS=Y&PROCCVV2=M&IAVS=N',
        ];
    }

    public function getExpectedRecurringPaymentResponses(): array
    {
        return [
            'RESULT=0&RPREF=R3V53AE13D76&PROFILEID=RT0000000003&RESPMSG=Approved&TRXRESULT=0&TRXPNREF=A40N0DAB30B0&TRXRESPMSG=Approved&AUTHCODE=008917&AVSADDR=Y&AVSZIP=Y&CVV2MATCH=Y&HOSTCODE=000&RESPTEXT=AP&PROCAVS=Y&PROCCVV2=M&IAVS=N',
        ];
    }

    public function getExpectedSinglePaymentErrorResponses(): array
    {
        return [
            'RESULT=12&PNREF=A80N0E94337E&RESPMSG=Declined&AVSADDR=Y&AVSZIP=Y&CVV2MATCH=Y&HOSTCODE=005&RESPTEXT=DECLINE&PROCAVS=Y&PROCCVV2=M&IAVS=N',
        ];
    }

    /**
     *  Get the expected request from Payflow Pro.
     *
     * @return array
     */
    public function getExpectedSinglePaymentRequests(): array
    {
        return [
            'USER[4]=test&VENDOR[4]=test&PARTNER[6]=PayPal&PWD[8]=test1234&TENDER[1]=C&TRXTYPE[1]=S&ACCT[16]=4111111111111111&CVV2[3]=123&EXPDATE[4]=1022&ACCTTYPE[4]=Visa&AMT[5]=20.00&CURRENCY[3]=AUD&FIRSTNAME[4]=John&LASTNAME[8]=O\'Connor&STREET[16]=8 Hobbitton Road&CITY[9]=The+Shire&STATE[3]=NSW&ZIP[4]=5010&COUNTRY[3]=AUS&EMAIL[24]=unittesteway@civicrm.org&CUSTIP[9]=127.0.0.1&COMMENT1[4]=4200&COMMENT2[4]=live&INVNUM[3]=xyz&ORDERDESC[17]=Test+Contribution&VERBOSITY[6]=MEDIUM&BILLTOCOUNTRY[3]=AUS',
        ];
    }

    public function getExpectedRecuringPaymentRequests(): array
    {
        return [
            'USER[4]=test&VENDOR[4]=test&PARTNER[6]=PayPal&PWD[8]=test1234&TENDER[1]=C&TRXTYPE[1]=R&ACCT[16]=4111111111111111&CVV2[3]=123&EXPDATE[4]=1022&ACCTTYPE[4]=Visa&AMT[5]=20.00&CURRENCY[3]=AUD&FIRSTNAME[4]=John&LASTNAME[8]=O\'Connor&STREET[16]=8 Hobbitton Road&CITY[9]=The+Shire&STATE[3]=NSW&ZIP[4]=5010&COUNTRY[3]=AUS&EMAIL[24]=unittesteway@civicrm.org&CUSTIP[9]=127.0.0.1&COMMENT1[4]=4200&COMMENT2[4]=live&INVNUM[3]=xyz&ORDERDESC[17]=Test+Contribution&VERBOSITY[6]=MEDIUM&BILLTOCOUNTRY[3]=AUS&OPTIONALTRX[1]=S&OPTIONALTRXAMT[5]=20.00&ACTION[1]=A&PROFILENAME[19]=RegularContribution&TERM[2]=12&START[8]=' . date('mdY', mktime(0, 0, 0, date("m") + 1, date("d"), date("Y"))) . '&PAYPERIOD[4]=MONT',
        ];
    }

    /**
     * @param $paymentProcessorId
     * @return mixed
     */
    public function activatePaymentProcessor($paymentProcessorId)
    {
        $this->civicrm_api('PaymentProcessor', 'create', ['id' => $paymentProcessorId, 'is_active' => 1]);
        $processorID = $paymentProcessorId;
        return $processorID;
    }

    /**
     * @param array $params
     * @return mixed
     */
    public function createPaymentProcessor(array $params)
    {
        $paymentProcessor = $this->callAPISuccess('payment_processor', 'create', $params);
        $processorID = $paymentProcessor['id'];
        return $processorID;
    }

    /**
     * @return bool
     */
    public function checkPaymentProcessorIsPresent()
    {
        $paymentProcessorId = false;
        $paymentProcessor = $this->civicrm_api('payment_processor', 'get', [
            'name' => 'demoPayment2c2p'
        ]);
        if (sizeof($paymentProcessor) !== 0) {
            $paymentProcessorId = $paymentProcessor['id'];
        }
        return $paymentProcessorId;
    }

}
