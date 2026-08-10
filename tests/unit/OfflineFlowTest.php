<?php

declare(strict_types=1);

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Exceptions\InvalidClaimException;
use Esia\Exceptions\RequestFailException;
use Esia\Exceptions\SignatureInvalidException;
use Esia\Exceptions\TokenExpiredException;
use Esia\OpenId;
use Esia\Signer\SignerInterface;
use Esia\Token\JwtValidator;
use Esia\Token\OpenSslSignatureVerifier;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use tests\unit\Support\JwtFixture;

/**
 * End-to-end offline coverage of the OpenId flow using an in-memory PSR-18
 * mock client and committed JWT fixtures. No network / live ESIA required.
 */
class OfflineFlowTest extends Unit
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'clientId' => JwtFixture::AUDIENCE,
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server.crt'),
            'tmpPath' => codecept_log_dir(),
        ];
    }

    public function testBuildAuthUrlContainsExpectedParams(): void
    {
        $openId = $this->openId();
        $url = $openId->buildUrl();

        self::assertStringStartsWith($openId->getConfig()->getCodeUrl() . '?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        self::assertSame(JwtFixture::AUDIENCE, $params['client_id']);
        self::assertSame('code', $params['response_type']);
        self::assertSame('offline', $params['access_type']);
        self::assertSame($openId->getConfig()->getScopeString(), $params['scope']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $params['state']
        );
        self::assertMatchesRegularExpression(
            '/^\d{4}\.\d{2}\.\d{2} \d{2}:\d{2}:\d{2} [+-]\d{4}$/',
            $params['timestamp']
        );
    }

    public function testGetTokenSendsWellFormedTokenRequest(): void
    {
        $client = $this->clientWith([
            new Response(200, [], (string) json_encode(['access_token' => JwtFixture::valid()])),
        ]);
        $openId = $this->openId($client);

        $openId->getToken('AUTH_CODE');

        $request = $client->getRequests()[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame($openId->getConfig()->getTokenUrl(), (string) $request->getUri());
        self::assertStringContainsString(
            'application/x-www-form-urlencoded',
            $request->getHeaderLine('Content-Type')
        );

        parse_str((string) $request->getBody(), $body);
        self::assertSame('authorization_code', $body['grant_type']);
        self::assertSame('AUTH_CODE', $body['code']);
        self::assertSame(JwtFixture::AUDIENCE, $body['client_id']);
    }

    public function testGetPersonInfoReturnsEmptyOnEmptyPayload(): void
    {
        $openId = $this->authenticatedOpenId([new Response(200, [], '{}')]);

        self::assertSame([], $openId->getPersonInfo());
    }

    /**
     * @dataProvider collectionMethodProvider
     */
    public function testCollectionMethodHandlesEmptyPayload(string $method): void
    {
        // The "empty" ESIA collection response — this is the shape that used to
        // crash getAddressInfo() before the payload guard (#67).
        $openId = $this->authenticatedOpenId([new Response(200, [], '{}')]);

        self::assertSame([], $openId->{$method}());
    }

    /**
     * @dataProvider collectionMethodProvider
     */
    public function testCollectionMethodCollectsElements(string $method): void
    {
        $openId = $this->authenticatedOpenId([
            new Response(200, [], '{"size": 2, "elements": ["u1", "u2"]}'),
            new Response(200, [], '{"a": 1}'),
            new Response(200, [], '{"b": 2}'),
        ]);

        self::assertSame([['a' => 1], ['b' => 2]], $openId->{$method}());
    }

    /**
     * @return array<string, array{string}>
     */
    public function collectionMethodProvider(): array
    {
        return [
            'contact' => ['getContactInfo'],
            'address' => ['getAddressInfo'],
            'doc' => ['getDocInfo'],
        ];
    }

    public function testServerErrorThrowsRequestFailException(): void
    {
        $openId = $this->authenticatedOpenId([new Response(500, [], 'Internal Server Error')]);

        $this->expectException(RequestFailException::class);
        $openId->getPersonInfo();
    }

    public function testMalformedJsonThrowsRequestFailException(): void
    {
        $openId = $this->authenticatedOpenId([new Response(200, [], 'not-json{')]);

        $this->expectException(RequestFailException::class);
        $openId->getPersonInfo();
    }

    public function testGetTokenAcceptsValidFixtureAndExtractsOid(): void
    {
        $validToken = JwtFixture::valid();
        $client = $this->clientWith([
            new Response(200, [], (string) json_encode(['access_token' => $validToken])),
        ]);
        $openId = $this->openId($client);
        $openId->setTokenValidator($this->fixtureValidator());

        $token = $openId->getToken('code');

        self::assertSame($validToken, $token);
        self::assertSame((string) JwtFixture::SUBJECT_ID, $openId->getConfig()->getOid());
    }

    /**
     * @dataProvider invalidFixtureProvider
     *
     * @param class-string<\Throwable> $expected
     */
    public function testGetTokenRejectsInvalidFixtures(string $fixtureToken, string $expected): void
    {
        $client = $this->clientWith([
            new Response(200, [], (string) json_encode(['access_token' => $fixtureToken])),
        ]);
        $openId = $this->openId($client);
        $openId->setTokenValidator($this->fixtureValidator());

        $this->expectException($expected);
        $openId->getToken('code');
    }

    /**
     * @return array<string, array{string, class-string<\Throwable>}>
     */
    public function invalidFixtureProvider(): array
    {
        return [
            'expired' => [JwtFixture::expired(), TokenExpiredException::class],
            'not-yet-valid' => [JwtFixture::notYetValid(), TokenExpiredException::class],
            'tampered' => [JwtFixture::tampered(), SignatureInvalidException::class],
            'wrong-audience' => [JwtFixture::wrongAudience(), InvalidClaimException::class],
            'wrong-issuer' => [JwtFixture::wrongIssuer(), InvalidClaimException::class],
        ];
    }

    private function fixtureValidator(): JwtValidator
    {
        return new JwtValidator(
            OpenSslSignatureVerifier::fromFile(JwtFixture::publicKeyPath()),
            JwtFixture::ISSUER,
            JwtFixture::AUDIENCE
        );
    }

    /**
     * @param Response[] $responses
     */
    private function authenticatedOpenId(array $responses): OpenId
    {
        $openId = $this->openId($this->clientWith($responses));
        $openId->getConfig()->setOid('1');
        $openId->getConfig()->setToken('token');

        return $openId;
    }

    private function openId(?MockClient $client = null): OpenId
    {
        $openId = new OpenId(new Config($this->config), $client ?? new MockClient());
        $openId->setSigner($this->stubSigner());

        return $openId;
    }

    /**
     * @param Response[] $responses
     */
    private function clientWith(array $responses): MockClient
    {
        $client = new MockClient();
        foreach ($responses as $response) {
            $client->addResponse($response);
        }

        return $client;
    }

    private function stubSigner(): SignerInterface
    {
        return new class () implements SignerInterface {
            public function sign(string $message): string
            {
                return 'signed';
            }
        };
    }
}
