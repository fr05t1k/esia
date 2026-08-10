<?php

declare(strict_types=1);

namespace Esia\Signer;

use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Signs messages with CryptoPro CSP via its command-line tool `cryptcp`.
 *
 * Runtime requirements:
 *  - CryptoPro CSP installed with the `cryptcp` utility available.
 *  - A GOST R 34.10-2012 certificate + private key present in the CSP key store,
 *    referenced by its SHA-1 thumbprint.
 *
 * @see https://docs.cryptopro.ru/cades/utilities/cryptcp
 */
final class CliCryptoProSigner implements SignerInterface
{
    use LoggerAwareTrait;
    use UrlSafeSignatureTrait;

    private string $tempDir;

    /**
     * @param string $toolPath Path to (or name of) the `cryptcp` executable
     * @param string $thumbprint SHA-1 thumbprint of the signing certificate in the CSP store
     * @param string|null $pin PIN/password for the private key container, if any
     * @param string|null $tempDir Writable directory for temporary files (defaults to the system temp dir)
     *
     * @throws NoSuchTmpDirException
     */
    public function __construct(
        private string $toolPath,
        private string $thumbprint,
        private ?string $pin = null,
        ?string $tempDir = null
    ) {
        $this->tempDir = $tempDir ?? sys_get_temp_dir();
        $this->logger = new NullLogger();

        if (!file_exists($this->tempDir)) {
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
        $messageFile = tempnam($this->tempDir, 'cryptcp');
        if ($messageFile === false) {
            throw new SignFailException('Cannot create a temporary file for signing');
        }
        file_put_contents($messageFile, $message);

        try {
            return $this->signFile($messageFile);
        } finally {
            if (file_exists($messageFile)) {
                unlink($messageFile);
            }
        }
    }

    /**
     * @throws SignFailException
     */
    private function signFile(string $messageFile): string
    {
        $command = escapeshellarg($this->toolPath)
            . ' -signf -dir ' . escapeshellarg($this->tempDir)
            . ' -cert -thumbprint ' . escapeshellarg($this->thumbprint);
        if ($this->pin !== null && $this->pin !== '') {
            $command .= ' -pin ' . escapeshellarg($this->pin);
        }
        $command .= ' ' . escapeshellarg($messageFile) . ' 2>&1';

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            $errors = implode("\n", $output);
            $this->logger->error('Sign fail');
            $this->logger->error('cryptcp error: ' . $errors);
            throw new SignFailException('Failure signing: ' . $errors);
        }

        $signatureFile = $messageFile . '.sgn';
        $signed = file_get_contents($signatureFile);
        if ($signed === false || $signed === '') {
            throw new SignFailException(sprintf('Cannot read the signature file %s', $signatureFile));
        }
        unlink($signatureFile);

        return $this->normalizeBase64Signature($signed);
    }
}
