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
        $output = [];
        $resultCode = 0;
        exec('cryptcp -help 2>&1', $output, $resultCode);
        if ($resultCode !== 0) {
            $this->markTestSkipped('The cryptcp utility is not available');
        }

        $signer = new CliCryptoProSigner(
            'cryptcp',
            '745187e5c161cd2e3130d886f9df4492fa270685',
            'test'
        );

        $signature = $signer->sign('test');
        self::assertNotEmpty($signature);
    }
}
