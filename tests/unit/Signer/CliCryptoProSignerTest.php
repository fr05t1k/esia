<?php

declare(strict_types=1);

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\CliCryptoProSigner;
use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;

/**
 * The `csptest` utility (CryptoPro CSP) is proprietary and unavailable in CI,
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
            'test-container',
            null,
            'csptest',
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
                'test-container',
                null,
                'csptest',
                $readOnlyDir
            );
        } finally {
            rmdir($readOnlyDir);
        }
    }

    public function testSignFailsWhenToolIsMissing(): void
    {
        $signer = new CliCryptoProSigner(
            'test-container',
            null,
            '/nonexistent/csptest-binary'
        );

        $this->expectException(SignFailException::class);
        $signer->sign('message');
    }

    public function testSign(): void
    {
        $container = getenv('ESIA_CRYPTOPRO_CONTAINER');
        if ($container === false || $container === '') {
            $this->markTestSkipped(
                'Set ESIA_CRYPTOPRO_CONTAINER (and optionally ESIA_CRYPTOPRO_CSPTEST/'
                . 'ESIA_CRYPTOPRO_PASSWORD) to run the live csptest signing test'
            );
        }

        $tool = getenv('ESIA_CRYPTOPRO_CSPTEST') ?: 'csptest';
        $password = getenv('ESIA_CRYPTOPRO_PASSWORD') ?: null;

        $output = [];
        $resultCode = 0;
        exec(escapeshellarg($tool) . ' 2>&1', $output, $resultCode);
        // csptest with no arguments prints its banner/usage; a missing binary
        // yields a shell error (127). Skip only when the tool cannot be run.
        if ($resultCode === 127) {
            $this->markTestSkipped(sprintf('The %s utility is not available', $tool));
        }

        $message = 'esia-cryptopro-' . bin2hex(random_bytes(8));
        $signer = new CliCryptoProSigner($container, $password, $tool);

        $signature = $signer->sign($message);
        self::assertNotEmpty($signature);

        // ESIA expects the raw GOST signature, byte-reversed and base64url
        // encoded. Decoding must succeed and yield the fixed GOST-2012-256
        // signature length (64 bytes).
        $raw = base64_decode(strtr($signature, '-_', '+/'), true);
        self::assertNotFalse($raw, 'Signature is not valid url-safe base64');
        self::assertSame(64, strlen($raw), 'Unexpected GOST-2012-256 signature length');
    }
}
