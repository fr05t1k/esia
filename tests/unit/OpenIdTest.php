<?php

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\InvalidConfigurationException;
use Esia\Http\GuzzleHttpClient;
use Esia\OpenId;
use Esia\Signer\Exceptions\SignFailException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

class OpenIdTest extends Unit
{
    public $config;

    /**
     * @var OpenId
     */
    public $openId;

    /**
     * @throws InvalidConfigurationException
     */
    public function setUp(): void
    {
        $this->config = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server.crt'),
            'tmpPath' => codecept_log_dir(),
        ];

        $config = new Config($this->config);

        $this->openId = new OpenId($config);
    }

    /**
     * @throws SignFailException
     * @throws AbstractEsiaException
     * @throws InvalidConfigurationException
     */
    public function testGetToken(): void
    {
        $config = new Config($this->config);

        $oid = '123';
        $oidBase64 = base64_encode('{ "urn:esia:sbj_id" : ' . $oid . '}');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{ "access_token": "test.' . $oidBase64 . '.test"}'),
        ]);
        $openId = new OpenId($config, $client);

        $token = $openId->getToken('test');
        self::assertNotEmpty($token);
        self::assertSame($oid, $openId->getConfig()->getOid());
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetPersonInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"username": "test"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getPersonInfo();
        self::assertNotEmpty($info);
        self::assertSame(['username' => 'test'], $info);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetContactInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getContactInfo();
        self::assertNotEmpty($info);
        self::assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetAddressInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getAddressInfo();
        self::assertNotEmpty($info);
        self::assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetDocInfo(): void
    {
        $config = new Config($this->config);
        $oid = '123';
        $config->setOid($oid);
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["phone", "email"]}'),
            new Response(200, [], '{"phone": "555 555 555"}'),
            new Response(200, [], '{"email": "test@gmail.com"}'),
        ]);
        $openId = new OpenId($config, $client);

        $info = $openId->getDocInfo();
        self::assertNotEmpty($info);
        self::assertSame([['phone' => '555 555 555'], ['email' => 'test@gmail.com']], $info);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testBuildLogoutUrl(): void
    {
        $config = $this->openId->getConfig();

        $url = $config->getLogoutUrl() . '?client_id=' . $config->getClientId();
        $logoutUrl = $this->openId->buildLogoutUrl();
        self::assertSame($url, $logoutUrl);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testBuildLogoutUrlWithRedirect(): void
    {
        $config = $this->openId->getConfig();

        $redirectUrl = 'test.example.com';
        $url = $config->getLogoutUrl() . '?client_id=' . $config->getClientId() . '&redirect_url=' . $redirectUrl;
        $logoutUrl = $this->openId->buildLogoutUrl($redirectUrl);
        self::assertSame($url, $logoutUrl);
    }

    /**
     * Client with prepared responses
     *
     * @param array $responses
     * @return ClientInterface
     */
    protected function buildClientWithResponses(array $responses): ClientInterface
    {
        $mock = new MockHandler($responses);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);

        return new GuzzleHttpClient($guzzleClient);
    }
}
