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
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

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
     * @throws AbstractEsiaException
     */
    public function testGetRoles(): void
    {
        $config = new Config($this->config);
        $config->setOid('123');
        $config->setToken('test');

        $elements = [
            ['oid' => 1, 'shortName' => 'ORG1', 'chief' => true],
            ['oid' => 2, 'shortName' => 'ORG2', 'chief' => false],
        ];
        $client = $this->buildClientWithResponses([
            new Response(200, [], json_encode(['size' => 2, 'elements' => $elements])),
        ]);
        $openId = new OpenId($config, $client);

        $roles = $openId->getRoles();
        self::assertSame($elements, $roles);
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetRolesReturnsEmptyWhenNoOrganizations(): void
    {
        $config = new Config($this->config);
        $config->setOid('123');
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 0, "elements": []}'),
        ]);
        $openId = new OpenId($config, $client);

        self::assertSame([], $openId->getRoles());
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetRolesHandlesMalformedPayload(): void
    {
        $config = new Config($this->config);
        $config->setOid('123');
        $config->setToken('test');

        // size present but elements missing must not raise a notice.
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 3}'),
        ]);
        $openId = new OpenId($config, $client);

        self::assertSame([], $openId->getRoles());
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetOrganizations(): void
    {
        $config = new Config($this->config);
        $config->setOid('123');
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 2, "elements": ["org-link-1", "org-link-2"]}'),
            new Response(200, [], '{"oid": 1, "shortName": "ORG1"}'),
            new Response(200, [], '{"oid": 2, "shortName": "ORG2"}'),
        ]);
        $openId = new OpenId($config, $client);

        $organizations = $openId->getOrganizations();
        self::assertSame(
            [
                ['oid' => 1, 'shortName' => 'ORG1'],
                ['oid' => 2, 'shortName' => 'ORG2'],
            ],
            $organizations
        );
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetOrganizationsReturnsEmptyWhenNone(): void
    {
        $config = new Config($this->config);
        $config->setOid('123');
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(200, [], '{"size": 0, "elements": []}'),
        ]);
        $openId = new OpenId($config, $client);

        self::assertSame([], $openId->getOrganizations());
    }

    /**
     * @throws InvalidConfigurationException
     * @throws SignFailException
     */
    public function testBuildUrlExposesGeneratedState(): void
    {
        $config = new Config($this->config);
        $openId = new OpenId($config);

        self::assertSame('', $config->getState());
        $openId->buildUrl();

        $state = $config->getState();
        self::assertNotEmpty($state);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $state
        );

        // A subsequent call reuses the same persisted state.
        $openId->buildUrl();
        self::assertSame($state, $config->getState());
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetTokenUsesInjectedState(): void
    {
        $injectedState = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $config = new Config($this->config + ['state' => $injectedState]);

        $oid = '123';
        $oidBase64 = base64_encode('{ "urn:esia:sbj_id" : ' . $oid . '}');
        $client = $this->buildClientWithResponses([
            new Response(200, [], '{ "access_token": "test.' . $oidBase64 . '.test"}'),
        ]);
        $openId = new OpenId($config, $client);

        $openId->getToken('test');
        self::assertSame($injectedState, $config->getState());
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
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testForbiddenResponseThrows(): void
    {
        $config = new Config($this->config);
        $config->setOid('123');
        $config->setToken('test');

        $client = $this->buildClientWithResponses([
            new Response(403, [], ''),
        ]);
        $openId = new OpenId($config, $client);

        $this->expectException(\Esia\Exceptions\ForbiddenException::class);
        $openId->getPersonInfo();
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetTokenValidatesJwtWhenValidatorSet(): void
    {
        $keyResource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyResource, $privateKey);
        $certificate = openssl_pkey_get_details($keyResource)['key'];

        $oid = 456;
        $token = $this->signJwt($privateKey, [
            'iss' => 'http://esia.gosuslugi.ru/',
            'aud' => 'INSP03211',
            'exp' => time() + 3600,
            'urn:esia:sbj_id' => $oid,
        ]);

        $config = new Config($this->config);
        $client = $this->buildClientWithResponses([
            new Response(200, [], json_encode(['access_token' => $token])),
        ]);
        $openId = new OpenId($config, $client);
        $openId->setSigner($this->stubSigner());
        $openId->setTokenValidator(new \Esia\Token\JwtValidator(
            new \Esia\Token\OpenSslSignatureVerifier($certificate),
            'http://esia.gosuslugi.ru/',
            'INSP03211'
        ));

        self::assertSame($token, $openId->getToken('code'));
        self::assertSame((string) $oid, $openId->getConfig()->getOid());
    }

    /**
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetTokenRejectsTamperedJwtWhenValidatorSet(): void
    {
        $keyResource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyResource, $privateKey);
        $certificate = openssl_pkey_get_details($keyResource)['key'];

        $token = $this->signJwt($privateKey, [
            'iss' => 'http://esia.gosuslugi.ru/',
            'aud' => 'INSP03211',
            'exp' => time() + 3600,
            'urn:esia:sbj_id' => 456,
        ]);
        [$header, $payload] = explode('.', $token);
        $tampered = $header . '.' . $payload . '.' . rtrim(strtr(base64_encode('forged'), '+/', '-_'), '=');

        $config = new Config($this->config);
        $client = $this->buildClientWithResponses([
            new Response(200, [], json_encode(['access_token' => $tampered])),
        ]);
        $openId = new OpenId($config, $client);
        $openId->setSigner($this->stubSigner());
        $openId->setTokenValidator(new \Esia\Token\JwtValidator(
            new \Esia\Token\OpenSslSignatureVerifier($certificate),
            'http://esia.gosuslugi.ru/',
            'INSP03211'
        ));

        $this->expectException(\Esia\Exceptions\SignatureInvalidException::class);
        $openId->getToken('code');
    }

    /**
     * Exercises the advertised opt-in path where validation is enabled purely
     * through Config (esiaCertPath / esiaTokenIssuer), with no manual
     * setTokenValidator() call.
     *
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetTokenValidatesViaEsiaCertPathConfig(): void
    {
        [$certPath, $privateKey] = $this->writeCertFixture();

        $oid = 789;
        $token = $this->signJwt($privateKey, [
            'iss' => 'http://esia.gosuslugi.ru/',
            'aud' => 'INSP03211',
            'exp' => time() + 3600,
            'urn:esia:sbj_id' => $oid,
        ]);

        $config = new Config($this->config + [
            'esiaCertPath' => $certPath,
            'esiaTokenIssuer' => 'http://esia.gosuslugi.ru/',
            'tokenLeeway' => 30,
        ]);
        $client = $this->buildClientWithResponses([
            new Response(200, [], json_encode(['access_token' => $token])),
        ]);
        $openId = new OpenId($config, $client);
        $openId->setSigner($this->stubSigner());

        self::assertSame($token, $openId->getToken('code'));
        self::assertSame((string) $oid, $openId->getConfig()->getOid());
    }

    /**
     * The audience for the auto-configured validator comes from clientId, so a
     * token minted for a different audience must be rejected.
     *
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetTokenViaConfigRejectsWrongAudience(): void
    {
        [$certPath, $privateKey] = $this->writeCertFixture();

        $token = $this->signJwt($privateKey, [
            'iss' => 'http://esia.gosuslugi.ru/',
            'aud' => 'SOMEONE_ELSE',
            'exp' => time() + 3600,
            'urn:esia:sbj_id' => 1,
        ]);

        $config = new Config($this->config + [
            'esiaCertPath' => $certPath,
            'esiaTokenIssuer' => 'http://esia.gosuslugi.ru/',
        ]);
        $client = $this->buildClientWithResponses([
            new Response(200, [], json_encode(['access_token' => $token])),
        ]);
        $openId = new OpenId($config, $client);
        $openId->setSigner($this->stubSigner());

        $this->expectException(\Esia\Exceptions\InvalidClaimException::class);
        $openId->getToken('code');
    }

    /**
     * A validated token that lacks the ESIA subject id must be rejected instead
     * of storing an empty oid.
     *
     * @throws InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function testGetTokenRejectsTokenWithoutSubjectId(): void
    {
        [$certPath, $privateKey] = $this->writeCertFixture();

        $token = $this->signJwt($privateKey, [
            'iss' => 'http://esia.gosuslugi.ru/',
            'aud' => 'INSP03211',
            'exp' => time() + 3600,
        ]);

        $config = new Config($this->config + [
            'esiaCertPath' => $certPath,
            'esiaTokenIssuer' => 'http://esia.gosuslugi.ru/',
        ]);
        $client = $this->buildClientWithResponses([
            new Response(200, [], json_encode(['access_token' => $token])),
        ]);
        $openId = new OpenId($config, $client);
        $openId->setSigner($this->stubSigner());

        $this->expectException(\Esia\Exceptions\InvalidClaimException::class);
        $openId->getToken('code');
    }

    private function stubSigner(): \Esia\Signer\SignerInterface
    {
        return new class () implements \Esia\Signer\SignerInterface {
            public function sign(string $message): string
            {
                return 'signed';
            }
        };
    }

    /**
     * Generate an RSA key pair, write its public key (PEM) to the output dir
     * and return [publicKeyPath, privateKeyPem]. A bare public key is enough
     * for {@see OpenSslSignatureVerifier} and avoids CSR/X.509 signing, which
     * is unavailable under the GOST-configured OpenSSL used in CI.
     *
     * @return array{string, string}
     */
    private function writeCertFixture(): array
    {
        $keyResource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($keyResource, $privateKey);
        $publicKey = openssl_pkey_get_details($keyResource)['key'];

        $certPath = codecept_output_dir('esia-pub-' . uniqid('', true) . '.pem');
        file_put_contents($certPath, $publicKey);

        return [$certPath, $privateKey];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function signJwt(string $privateKey, array $claims): string
    {
        $encode = static fn (string $data): string => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $header = $encode((string) json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $payload = $encode((string) json_encode($claims));
        openssl_sign($header . '.' . $payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $header . '.' . $payload . '.' . $encode($signature);
    }

    /**
     * Client with prepared responses
     *
     * @param ResponseInterface[] $responses
     * @return ClientInterface
     */
    protected function buildClientWithResponses(array $responses): ClientInterface
    {
        $client = new MockClient();
        foreach ($responses as $response) {
            $client->addResponse($response);
        }

        return $client;
    }
}
