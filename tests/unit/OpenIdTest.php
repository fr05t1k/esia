<?php

namespace tests\unit;

use AspectMock\Proxy\InstanceProxy;
use AspectMock\Test as test;
use Codeception\Util\Stub;
use esia\exceptions\RequestFailException;
use esia\exceptions\SignFailException;
use esia\OpenId;
use esia\Request;
use esia\transport\Curl;

class OpenIdTest extends \Codeception\TestCase\Test
{
    public $config;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        $this->config = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => __DIR__ . '/../_data/server.key',
            'privateKeyPassword' => 'test',
            'certPath' => __DIR__ . '/../_data/server.crt',
            'tmpPath' => __DIR__ . '/../tmp',
        ];

        // define function for push them from global namespace
        test::func('esia\transport', 'curl_init', false);
        test::func('esia\transport', 'curl_exec', false);
        test::func('esia', 'openssl_pkcs7_sign', false);
        test::func('esia', 'openssl_pkey_get_private', false);
        test::func('esia', 'openssl_x509_read', false);
        test::clean();

        parent::__construct($name, $data, $dataName);
    }


    /**
     * @var OpenId
     */
    public $openId;

    public function testGetPersonUrl()
    {
        $personUrl = $this->openId->getPersonUrl();

        $this->assertNotFalse(filter_var($personUrl, FILTER_VALIDATE_URL));

        $domain = 'http://google.com';
        $query = '?tokenUrl';
        $this->openId->portalUrl = $domain;
        $this->openId->personUrl = $query;

        $this->assertEquals($domain . $query, $this->openId->getPersonUrl());
    }

    public function testGetToken()
    {


        test::func('esia\transport', 'curl_init', false);
        $this->assertFalse($this->openId->getToken('test'));
        test::clean();

        $oid = 123;
        $oidBase64 = $this->urlSafe(base64_encode('{ "urn:esia:sbj_id" : ' . $oid . '}'));

        $curlResult = json_encode([
            'access_token' => 'test.' . $oidBase64 . '.test',
            'expires_in' => 3600,
        ]);

        test::func('esia\transport', 'curl_exec', $curlResult);
        $token = $this->openId->getToken($curlResult);
        $this->assertNotFalse($token);
        $this->assertEquals($oid, $this->openId->oid);
        test::clean();

        test::double($this->openId, ['signPKCS7' => false]);
        try {
            $this->openId->getToken('test');
            $this->fail('Exception dont rise');
        } catch (SignFailException $e) {
            $this->assertEquals($e->getCode(), SignFailException::CODE_SIGN_FAIL, 'Exception rise with wrong code');
        }
    }

    public function testSignPKCS7()
    {
        $openId = $this->prepareOpenId();

        $checkException = function ($func, $expectedCode) use ($openId) {
            try {
                test::func('esia', $func, false);
                $openId = $this->prepareOpenId();
                $openId->signPKCS7('message');
                $errorMessage = sprintf('Exception %s isn\'t rise with code %d', SignFailException::class,
                    $expectedCode);
                $this->fail($errorMessage);
            } catch (SignFailException $e) {
                $message = 'Exception rise with wrong code';
                $this->assertEquals($e->getCode(), $expectedCode, $message);
            }
            test::clean();
        };

        $checkException('openssl_x509_read', SignFailException::CODE_CANT_READ_CERT);
        $checkException('openssl_pkey_get_private', SignFailException::CODE_CANT_READ_PRIVATE_KEY);
        $checkException('openssl_pkcs7_sign', SignFailException::CODE_SIGN_FAIL);

        // check correct call
        $result = $openId->signPKCS7('message');

        $this->assertInternalType('string', $result);

    }

    public function testBuildRequest()
    {
        try {
            $this->openId->buildRequest();
        } catch (RequestFailException $e) {
            $expectedCode = RequestFailException::CODE_TOKEN_IS_EMPTY;
            $message = 'Exception rise with wrong code';
            $this->assertEquals($e->getCode(), $expectedCode, $message);
        }

        $this->openId->token = '123';
        $this->assertInstanceOf(Request::class, $this->openId->buildRequest());
    }

    public function testCorrectUrl()
    {
        $call = function ($url) {
            $this->assertNotFalse(filter_var('htpp://google.com/' . $url, FILTER_VALIDATE_URL));
        };
        $request = Stub::make(Request::class, [
            'call' => $call,
        ]);

        /** @var OpenId $openId */
        $openId = test::double($this->openId, [
            'buildRequest' => $request,
        ]);

        $openId->token = '123';

        $openId->getPersonInfo();
        $openId->getContactInfo();
        $openId->getAddressInfo();
    }

    public function testGetInfoWithNotEmptyElements()
    {
        $request = Stub::make(Request::class, [
            'call' => json_decode(
                json_encode([
                    'size' => 3,
                    'elements' => [1, 2, 3],
                ])
            ),
        ]);

        /** @var OpenId|InstanceProxy $openId */
        $openId = test::double($this->openId, [
            'buildRequest' => $request,
        ]);

        $openId->token = 'test';
        $result = $openId->getAddressInfo();
        $openId->verifyInvokedOnce('collectArrayElements');
        $this->assertCount(3, $result, 'Must return 3 element');

        $result = $openId->getContactInfo();
        $this->assertCount(3, $result, 'Must return 3 element');


    }

    public function testGetInfoWithEmptyElements()
    {
        $request = Stub::make(Request::class, [
            'call' => json_decode(
                json_encode([
                    'size' => 0,
                    'elements' => [],
                ])
            ),
        ]);

        /** @var OpenId|InstanceProxy $openId */
        $openId = test::double($this->openId, [
            'buildRequest' => $request,
            'collectArrayElements' => false,
        ]);

        $openId->token = 'test';
        $openId->getAddressInfo();
        $openId->verifyInvokedMultipleTimes('collectArrayElements', 0);

        $openId->getContactInfo();
        $openId->verifyInvokedMultipleTimes('collectArrayElements', 0);
    }


    protected function _before()
    {
        $this->openId = $this->prepareOpenId();
        parent::_before();
    }

    protected function _after()
    {
        test::clean();
        parent::_after();
    }


    public function prepareOpenId()
    {
        return new OpenId($this->config, new Curl());
    }


    public function testConstruct()
    {
        $openId = $this->prepareOpenId();

        foreach ($this->config as $k => $v) {
            $this->assertAttributeEquals($v, $k, $openId);
        }

    }

    public function testGetUrl()
    {
        $url = $this->openId->getUrl();
        $this->assertNotFalse(filter_var($url, FILTER_VALIDATE_URL));

        test::double($this->openId, ['signPKCS7' => false]);
        $url = $this->openId->getUrl();
        $this->assertFalse(filter_var($url, FILTER_VALIDATE_URL));
        test::clean();
    }

    public function testGetTokenUrl()
    {
        $tokenUrl = $this->openId->getTokenUrl();

        $this->assertNotFalse(filter_var($tokenUrl, FILTER_VALIDATE_URL));

        $domain = 'http://google.com';
        $query = '?tokenUrl';
        $this->openId->portalUrl = $domain;
        $this->openId->tokenUrl = $query;

        $this->assertEquals($domain . $query, $this->openId->getTokenUrl());
    }

    /**
     * Url safe for base64
     *
     * @param string $string
     *
     * @return string
     */
    private function urlSafe($string)
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }

}