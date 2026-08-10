<?php

declare(strict_types=1);

namespace tests\unit\Signer;

use Codeception\Test\Unit;
use Esia\Signer\CliSignerPKCS7;

/**
 * Signs a known message with the production CLI signer and verifies that the
 * produced detached GOST PKCS#7 signature round-trips through `openssl smime -verify`.
 *
 * Requires the GOST OpenSSL engine (see .github/workflows/ci.yml and tests/openssl.cnf).
 *
 * @group gost
 * @coversNothing
 */
class CliSignerRoundTripTest extends Unit
{
    private function signer(): CliSignerPKCS7
    {
        return new CliSignerPKCS7(
            codecept_data_dir('server-gost.crt'),
            codecept_data_dir('server-gost.key'),
            'test',
            codecept_log_dir()
        );
    }

    /**
     * Verify a detached GOST PKCS#7 signature via openssl.
     *
     * @return array{0:int,1:string} exit code and combined output
     */
    private function verify(string $derPath, string $messagePath): array
    {
        $command = 'openssl smime -engine gost -verify -binary -inform DER -noverify '
            . '-in ' . escapeshellarg($derPath) . ' '
            . '-content ' . escapeshellarg($messagePath) . ' 2>&1';

        exec($command, $output, $code);

        return [$code, implode("\n", $output)];
    }

    public function testSignatureRoundTrips(): void
    {
        $message = 'esia-round-trip-' . bin2hex(random_bytes(8));
        $signature = $this->signer()->sign($message);
        self::assertNotEmpty($signature);

        // Reverse CliSignerPKCS7::urlSafe() back to standard base64, then to DER.
        $der = base64_decode(strtr($signature, '-_', '+/'), true);
        self::assertNotFalse($der, 'Signature is not valid base64');

        $derPath = codecept_log_dir('roundtrip.der');
        $messagePath = codecept_log_dir('roundtrip.msg');
        file_put_contents($derPath, $der);
        file_put_contents($messagePath, $message);

        [$code, $output] = $this->verify($derPath, $messagePath);

        unlink($derPath);
        unlink($messagePath);

        self::assertSame(0, $code, 'openssl could not verify the signature: ' . $output);
    }

    public function testVerificationFailsForTamperedMessage(): void
    {
        $message = 'esia-round-trip-' . bin2hex(random_bytes(8));
        $signature = $this->signer()->sign($message);

        $der = base64_decode(strtr($signature, '-_', '+/'), true);
        self::assertNotFalse($der);

        $derPath = codecept_log_dir('roundtrip-tampered.der');
        $messagePath = codecept_log_dir('roundtrip-tampered.msg');
        file_put_contents($derPath, $der);
        file_put_contents($messagePath, $message . '-tampered');

        [$code] = $this->verify($derPath, $messagePath);

        unlink($derPath);
        unlink($messagePath);

        self::assertNotSame(0, $code, 'Verification unexpectedly succeeded for a tampered message');
    }
}
