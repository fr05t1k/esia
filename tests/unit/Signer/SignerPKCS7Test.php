<?php

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\SignerPKCS7;

/**
 * Class SignerPKCS7Test
 *
 * @coversDefaultClass \Esia\Signer\SignerPKCS7
 */
class SignerPKCS7Test extends Unit
{
    /**
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testSign(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir()
        );

        $sign = $signer->sign('test');
        $this->assertNotEmpty($sign);
    }

    /**
     * @expectedException  \Esia\Signer\Exceptions\NoSuchCertificateFileException
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testSignCertDoesNotExists(): void
    {
        $signer = new SignerPKCS7(
            '/test',
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir()
        );

        $signer->sign('test');
    }

    /**
     * @expectedException  \Esia\Signer\Exceptions\NoSuchKeyFileException
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testPrivateKeyDoesNotExists(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            '/test',
            'test',
            codecept_log_dir()
        );

        $signer->sign('test');
    }

    /**
     * @expectedException  \Esia\Signer\Exceptions\NoSuchTmpDirException
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testTmpDirDoesNotExists(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('server.key'),
            'test',
            '/'
        );

        $signer->sign('test');
    }

    /**
     * @expectedException  \Esia\Signer\Exceptions\NoSuchTmpDirException
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testTmpDirIsNotWritable(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir('non_writable_directory')
        );

        $signer->sign('test');
    }

    /**
     * @expectedException  \Esia\Signer\Exceptions\CannotReadCertificateException
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testCertificateIsNotReadable(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('non_readable_file'),
            codecept_data_dir('server.key'),
            'test',
            codecept_log_dir()
        );

        $signer->sign('test');
    }

    /**
     * @expectedException  \Esia\Signer\Exceptions\CannotReadPrivateKeyException
     * @throws \Esia\Signer\Exceptions\SignFailException
     */
    public function testPrivateKeyIsNotReadable(): void
    {
        $signer = new SignerPKCS7(
            codecept_data_dir('server.crt'),
            codecept_data_dir('non_readable_file'),
            'test',
            codecept_log_dir()
        );

        $signer->sign('test');
    }
}
