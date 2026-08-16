<?php

declare(strict_types=1);

namespace Esia\Signer;

use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Signs messages with CryptoPro CSP via its command-line utility `csptest`.
 *
 * Unlike a PKCS#7/CMS container, ESIA expects the *raw* GOST signature value,
 * byte-reversed and then base64url-encoded (see the official methodology
 * "Методические рекомендации по интеграции с ЕСИА",
 * @see https://digital.gov.ru/ru/documents/6186/).
 *
 * The implementation follows the approach proven in production by
 * {@link https://github.com/ilimurzin/esia}.
 *
 * Runtime requirements:
 *  - CryptoPro CSP installed with the `csptest` utility available.
 *  - A GOST R 34.10-2012 key container holding the signing private key.
 */
final class CliCryptoProSigner implements SignerInterface
{
    use LoggerAwareTrait;
    use UrlSafeSignatureTrait;

    private string $tempDir;

    /**
     * @param string $container Name of the CSP key container (`-container`)
     * @param string|null $password Container password, if any (`-password`)
     * @param string $toolPath Path to (or name of) the `csptest` executable
     * @param string|null $tempDir Writable directory for temporary files (defaults to the system temp dir)
     *
     * @throws NoSuchTmpDirException
     */
    public function __construct(
        private string $container,
        #[\SensitiveParameter]
        private ?string $password = null,
        private string $toolPath = 'csptest',
        ?string $tempDir = null
    ) {
        $this->tempDir = $tempDir ?? sys_get_temp_dir();
        $this->logger = new NullLogger();

        if (!is_dir($this->tempDir)) {
            throw new NoSuchTmpDirException('Temporary folder is not found');
        }
        if (!is_writable($this->tempDir)) {
            throw new NoSuchTmpDirException('Temporary folder is not writable');
        }
    }

    /**
     * @throws SignFailException
     */
    public function sign(string $message): string
    {
        $messageFile = tempnam($this->tempDir, 'cprocsp');
        if ($messageFile === false) {
            throw new SignFailException('Cannot create a temporary file for the message');
        }
        $signatureFile = tempnam($this->tempDir, 'cprocsp');
        if ($signatureFile === false) {
            unlink($messageFile);
            throw new SignFailException('Cannot create a temporary file for the signature');
        }

        try {
            $bytes = file_put_contents($messageFile, $message);
            if ($bytes === false || $bytes !== strlen($message)) {
                throw new SignFailException('Cannot write the message to the temporary file');
            }

            $signature = $this->signFile($messageFile, $signatureFile);
        } finally {
            if (file_exists($messageFile)) {
                unlink($messageFile);
            }
            if (file_exists($signatureFile)) {
                unlink($signatureFile);
            }
        }

        // Methodology (digital.gov.ru/6186): byte-reverse the raw signature,
        // then base64url-encode it.
        return $this->urlSafe(base64_encode(strrev($signature)));
    }

    /**
     * @throws SignFailException
     */
    private function signFile(string $messageFile, string $signatureFile): string
    {
        $command = escapeshellarg($this->toolPath)
            . ' -keyset -sign GOST12_256'
            . ' -container ' . escapeshellarg($this->container)
            . ' -keytype exchange'
            . ' -in ' . escapeshellarg($messageFile)
            . ' -out ' . escapeshellarg($signatureFile);
        if ($this->password !== null && $this->password !== '') {
            $command .= ' -password ' . escapeshellarg($this->password);
        }
        $command .= ' 2>&1';

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            $errors = implode("\n", $output);
            $this->logger->error('Sign fail');
            $this->logger->error('csptest error: ' . $errors);
            throw new SignFailException('Failure signing: ' . $errors);
        }

        $signature = file_get_contents($signatureFile);
        if ($signature === false || $signature === '') {
            throw new SignFailException(sprintf('Cannot read the signature file %s', $signatureFile));
        }

        return $signature;
    }
}
