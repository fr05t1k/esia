<?php

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\InvalidConfigurationException;
use Esia\OpenId;
use Esia\Signer\Exceptions\SignFailException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;

/**
 * The current ESIA token endpoint (aas/oauth2/v3/te) requires the
 * client_certificate_hash parameter. These tests assert it is forwarded when
 * configured and omitted otherwise (backward compatible).
 *
 * @see https://github.com/fr05t1k/esia/issues/59
 */
class ClientCertificateHashTest extends Unit
{
    /**
     * @var array<string, mixed>
     */
    private array $baseConfig;

    public function setUp(): void
    {
        $this->baseConfig = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server.crt'),
            'tmpPath' => codecept_log_dir(),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{0: OpenId, 1: MockClient}
     * @throws InvalidConfigurationException
     */
    private function buildOpenId(array $config): array
    {
        $oidBase64 = base64_encode('{ "urn:esia:sbj_id" : 123}');
        $client = new MockClient();
        $client->addResponse(
            new Response(200, [], '{ "access_token": "test.' . $oidBase64 . '.test"}')
        );

        return [new OpenId(new Config($config), $client), $client];
    }

    /**
     * @throws SignFailException
     * @throws AbstractEsiaException
     * @throws InvalidConfigurationException
     */
    public function testTokenRequestSendsClientCertificateHashWhenConfigured(): void
    {
        [$openId, $client] = $this->buildOpenId(
            $this->baseConfig + ['clientCertificateHash' => 'ABCDEF0123456789']
        );

        $openId->getToken('test');

        parse_str((string) $client->getLastRequest()->getBody(), $body);
        self::assertSame('ABCDEF0123456789', $body['client_certificate_hash'] ?? null);
    }

    /**
     * @throws SignFailException
     * @throws AbstractEsiaException
     * @throws InvalidConfigurationException
     */
    public function testTokenRequestOmitsClientCertificateHashByDefault(): void
    {
        [$openId, $client] = $this->buildOpenId($this->baseConfig);

        $openId->getToken('test');

        parse_str((string) $client->getLastRequest()->getBody(), $body);
        self::assertArrayNotHasKey('client_certificate_hash', $body);
    }

    /**
     * @throws SignFailException
     * @throws InvalidConfigurationException
     */
    public function testBuildUrlSendsClientCertificateHashWhenConfigured(): void
    {
        [$openId] = $this->buildOpenId(
            $this->baseConfig + ['clientCertificateHash' => 'ABCDEF0123456789']
        );

        $url = $openId->buildUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('ABCDEF0123456789', $query['client_certificate_hash'] ?? null);
    }

    /**
     * @throws SignFailException
     * @throws InvalidConfigurationException
     */
    public function testBuildUrlOmitsClientCertificateHashByDefault(): void
    {
        [$openId] = $this->buildOpenId($this->baseConfig);

        $url = $openId->buildUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        self::assertArrayNotHasKey('client_certificate_hash', $query);
    }
}
