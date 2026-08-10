<?php

namespace tests\unit;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\Exceptions\InvalidConfigurationException;

/**
 * Class ConfigTest
 *
 * @coversDefaultClass \Esia\Config
 */
class ConfigTest extends Unit
{
    /**
     * Getter for scope string
     *
     * @throws \Esia\Exceptions\InvalidConfigurationException
     */
    public function testGetScopeString(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'scope' => ['test', 'test2', 'test3'],
        ]);

        $this->assertSame('test test2 test3', $config->getScopeString());
    }

    /**
     * Data provider for @see ConfigTest::testConstruct()
     *
     * @return array
     */
    public function dataProviderForConstructor(): array
    {
        return [
            'min' => [
                [
                    'clientId' => 'test',
                    'redirectUrl' => 'http://google.com',
                    'privateKeyPath' => '/tmp',
                    'certPath' => '/tmp',
                    'scope' => ['test', 'test2', 'test3'],
                ],
                null,
            ],
            'max' => [
                [
                    'clientId' => 'test',
                    'redirectUrl' => 'http://google.com',
                    'privateKeyPath' => '/tmp',
                    'certPath' => '/tmp',
                    'portalUrl' => 'google.com',
                    'tokenUrlPath' => 'test',
                    'codeUrlPath' => 'test',
                    'personUrlPath' => 'test',
                    'logoutUrlPath' => 'test',
                    'privateKeyPassword' => 'test',
                    'clientCertificateHash' => 'ABCDEF0123456789',
                    'oid' => 'test',
                    'responseType' => 'test',
                    'accessType' => 'test',
                    'tmpPath' => 'test',
                    'token' => 'test',
                    'scope' => ['test', 'test2', 'test3'],
                ],
                null,
            ],
            'No cert path' => [
                [
                    'clientId' => 'test',
                    'redirectUrl' => 'http://google.com',
                    'privateKeyPath' => '/tmp',
                    'scope' => ['test', 'test2', 'test3'],
                ],
                InvalidConfigurationException::class,
            ],
            'No private key path' => [
                [
                    'clientId' => 'test',
                    'redirectUrl' => 'http://google.com',
                    'certPath' => '/tmp',
                    'scope' => ['test', 'test2', 'test3'],
                ],
                InvalidConfigurationException::class,
            ],
            'No redirect url' => [
                [
                    'clientId' => 'test',
                    'privateKeyPath' => '/tmp',
                    'certPath' => '/tmp',
                    'scope' => ['test', 'test2', 'test3'],
                ],
                InvalidConfigurationException::class,
            ],
            'No client id' => [
                [
                    'redirectUrl' => 'http://google.com',
                    'privateKeyPath' => '/tmp',
                    'certPath' => '/tmp',
                    'scope' => ['test', 'test2', 'test3'],
                ],
                InvalidConfigurationException::class,
            ],
            'invalid scope' => [
                [
                    'redirectUrl' => 'http://google.com',
                    'privateKeyPath' => '/tmp',
                    'certPath' => '/tmp',
                    'scope' => 'test test2 test3',
                ],
                InvalidConfigurationException::class,
            ],
            'scope with non-string element' => [
                [
                    'clientId' => 'test',
                    'redirectUrl' => 'http://google.com',
                    'privateKeyPath' => '/tmp',
                    'certPath' => '/tmp',
                    'scope' => ['test', 123, 'test3'],
                ],
                InvalidConfigurationException::class,
            ],
        ];
    }

    /**
     * @param $config
     * @param string|null $expectedException
     * @throws \Esia\Exceptions\InvalidConfigurationException
     *
     * @dataProvider dataProviderForConstructor
     */
    public function testConstruct($config, string $expectedException = null): void
    {
        if ($expectedException) {
            $this->expectException($expectedException);
        }

        new Config($config);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testGetTokenUrl(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'portalUrl' => 'https://google.com/',
            'tokenUrlPath' => 'test',
            'scope' => ['test', 'test2', 'test3'],
        ]);

        $this->assertSame('https://google.com/test', $config->getTokenUrl());
    }

    /**
     * The default token endpoint must be the current ESIA v3 path over HTTPS.
     *
     * @throws InvalidConfigurationException
     */
    public function testGetTokenUrlDefault(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
        ]);

        $this->assertSame(
            'https://esia-portal1.test.gosuslugi.ru/aas/oauth2/v3/te',
            $config->getTokenUrl()
        );
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testGetCodeUrl(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'portalUrl' => 'https://google.com/',
            'codeUrlPath' => 'test',
            'scope' => ['test', 'test2', 'test3'],
        ]);

        $this->assertSame('https://google.com/test', $config->getCodeUrl());
    }

    /**
     * The default authorization-code endpoint must be the current ESIA v2 path over HTTPS.
     *
     * @throws InvalidConfigurationException
     */
    public function testGetCodeUrlDefault(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
        ]);

        $this->assertSame(
            'https://esia-portal1.test.gosuslugi.ru/aas/oauth2/v2/ac',
            $config->getCodeUrl()
        );
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testGetPersonUrl(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'portalUrl' => 'https://google.com/',
            'personUrlPath' => 'test',
            'oid' => 'test',
            'scope' => ['test', 'test2', 'test3'],
        ]);

        $this->assertSame('https://google.com/test/test', $config->getPersonUrl());
    }
    /**
     * @throws InvalidConfigurationException
     */
    public function testGetPersonUrlWithoutOid(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'portalUrl' => 'https://google.com/',
            'personUrlPath' => 'test',
            'scope' => ['test', 'test2', 'test3'],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->assertSame('https://google.com/test/test', $config->getPersonUrl());
    }
    /**
     * @throws InvalidConfigurationException
     */
    public function testGetLogoutUrl(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'portalUrl' => 'https://google.com/',
            'logoutUrlPath' => 'test',
            'scope' => ['test', 'test2', 'test3'],
        ]);

        $this->assertSame('https://google.com/test', $config->getLogoutUrl());
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testGetClientCertificateHash(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
            'clientCertificateHash' => 'ABCDEF0123456789',
        ]);

        $this->assertSame('ABCDEF0123456789', $config->getClientCertificateHash());
    }

    /**
     * @throws InvalidConfigurationException
     */
    public function testGetClientCertificateHashDefaultsToEmpty(): void
    {
        $config = new Config([
            'clientId' => 'test',
            'redirectUrl' => 'http://google.com',
            'privateKeyPath' => '/tmp',
            'certPath' => '/tmp',
        ]);

        $this->assertSame('', $config->getClientCertificateHash());
    }
}
