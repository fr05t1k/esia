<?php

declare(strict_types=1);

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\CliCryptoProSigner;
use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;

/**
 * The `cryptcp` utility (CryptoPro CSP) is proprietary and unavailable in CI,
 * so the actual signing test is skipped unless the tool is present. The
 * constructor-validation tests run everywhere and give real coverage.
 *
 * @coversNothing
 */
class CliCryptoProSignerTest extends Unit
{
    public function testTempDirDoesNotExist(): void
    {
        $this->expectException(NoSuchTmpDirException::class);

        new CliCryptoProSigner(
            'cryptcp',
            '745187e5c161cd2e3130d886f9df4492fa270685',
            null,
            '/no/such/directory/for/esia'
        );
    }

    public function testTempDirIsNotWritable(): void
    {
        $readOnlyDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'esia_ro_' . bin2hex(random_bytes(4));
        mkdir($readOnlyDir, 0500);

        try {
            if (is_writable($readOnlyDir)) {
                $this->markTestSkipped('Cannot create a non-writable directory (running as root?)');
            }

            $this->expectException(NoSuchTmpDirException::class);

            new CliCryptoProSigner(
                'cryptcp',
                '745187e5c161cd2e3130d886f9df4492fa270685',
                null,
                $readOnlyDir
            );
        } finally {
            rmdir($readOnlyDir);
        }
    }

    public function testSignFailsWhenToolIsMissing(): void
    {
        $signer = new CliCryptoProSigner(
            '/nonexistent/cryptcp-binary',
            '745187e5c161cd2e3130d886f9df4492fa270685'
        );

        $this->expectException(SignFailException::class);
        $signer->sign('message');
    }

    public function testSign(): void
    {
        $thumbprint = getenv('ESIA_CRYPTOPRO_THUMBPRINT');
        if ($thumbprint === false || $thumbprint === '') {
            $this->markTestSkipped(
                'Set ESIA_CRYPTOPRO_THUMBPRINT (and optionally ESIA_CRYPTOPRO_CRYPTCP/'
                . 'ESIA_CRYPTOPRO_PIN/ESIA_CRYPTOPRO_CACERT) to run the live cryptcp signing test'
            );
        }

        $tool = getenv('ESIA_CRYPTOPRO_CRYPTCP') ?: 'cryptcp';
        $pin = getenv('ESIA_CRYPTOPRO_PIN') ?: null;

        $output = [];
        $resultCode = 0;
        exec(escapeshellarg($tool) . ' -help 2>&1', $output, $resultCode);
        if ($resultCode !== 0) {
            $this->markTestSkipped(sprintf('The %s utility is not available', $tool));
        }

        $message = 'esia-cryptopro-' . bin2hex(random_bytes(8));
        $signer = new CliCryptoProSigner($tool, $thumbprint, $pin);

        $signature = $signer->sign($message);
        self::assertNotEmpty($signature);

        // The signer must return a url-safe base64 detached PKCS#7 signature.
        $der = base64_decode(strtr($signature, '-_', '+/'), true);
        self::assertNotFalse($der, 'Signature is not valid url-safe base64');

        // When a CA certificate is provided, verify the detached signature
        // round-trips through openssl, proving it covers the exact message.
        $caCert = getenv('ESIA_CRYPTOPRO_CACERT');
        if ($caCert !== false && $caCert !== '') {
            $signaturePath = codecept_log_dir('cryptopro.der');
            $messagePath = codecept_log_dir('cryptopro.msg');
            file_put_contents($signaturePath, $der);
            file_put_contents($messagePath, $message);

            $command = 'openssl smime -verify -inform DER -noverify '
                . '-in ' . escapeshellarg($signaturePath) . ' '
                . '-content ' . escapeshellarg($messagePath) . ' '
                . '-CAfile ' . escapeshellarg($caCert) . ' 2>&1';
            $verifyOutput = [];
            $verifyCode = 0;
            exec($command, $verifyOutput, $verifyCode);

            self::assertSame(
                0,
                $verifyCode,
                'openssl verification failed: ' . implode("\n", $verifyOutput)
            );
        }
    }
}
