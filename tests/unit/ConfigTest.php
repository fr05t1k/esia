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
                    'tokenUrl' => 'test',
                    'codeUrl' => 'test',
                    'personUrl' => 'test',
                    'privateKeyPassword' => 'test',
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
}
