<?php

namespace tests\unit;

use Esia\Config;
use Esia\OpenId;


class OpenIdCliOpensslTest extends OpenIdTest {
    /**
     * @throws \Esia\Exceptions\InvalidConfigurationException
     */
    public function setUp()
    {
        $this->config = [
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => 'https://esia-portal1.test.gosuslugi.ru/',
            'privateKeyPath' => codecept_data_dir('server-gost.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server-gost.crt'),
            'tmpPath' => codecept_log_dir(),
            'signerClass' => '\Esia\Signer\CliSignerPKCS7',
        ];

        $config = new Config($this->config);

        $this->openId = new OpenId($config);
    }
}
