<?php

declare(strict_types=1);

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\CryptoProSigner;
use Esia\Signer\Exceptions\SignFailException;
use Esia\Signer\SignerInterface;

/**
 * The CryptoPro PHP extension is proprietary and unavailable in CI, so the only
 * behaviour we can assert here without it is the guard that throws a clear
 * exception when the extension is missing.
 *
 * @coversNothing
 */
class CryptoProSignerTest extends Unit
{
    public function testIsSigner(): void
    {
        $signer = new CryptoProSigner('745187e5c161cd2e3130d886f9df4492fa270685');
        self::assertInstanceOf(SignerInterface::class, $signer);
    }

    public function testSignFailsWithoutExtension(): void
    {
        if (class_exists(\CPStore::class)) {
            $this->markTestSkipped('The CryptoPro PHP extension is installed');
        }

        $signer = new CryptoProSigner('745187e5c161cd2e3130d886f9df4492fa270685', 'pin');

        $this->expectException(SignFailException::class);
        $this->expectExceptionMessage('CryptoPro PHP extension is not available');
        $signer->sign('message');
    }
}
