<?php

declare(strict_types=1);

namespace Esia\Signer;

use Esia\Signer\Exceptions\CannotReadCertificateException;
use Esia\Signer\Exceptions\CannotReadPrivateKeyException;
use Esia\Signer\Exceptions\NoSuchCertificateFileException;
use Esia\Signer\Exceptions\NoSuchKeyFileException;
use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class AbstractSignerPKCS7
{
    use LoggerAwareTrait;
    use UrlSafeSignatureTrait;

    /**
     * @param string $certPath Path to the certificate
     * @param string $privateKeyPath Path to the private key
     * @param string|null $privateKeyPassword Password for the private key
     * @param string $tmpPath Temporary directory for message signing (must be writable)
     */
    public function __construct(
        protected string $certPath,
        protected string $privateKeyPath,
        protected ?string $privateKeyPassword,
        protected string $tmpPath
    ) {
        $this->logger = new NullLogger();
    }

    /**
     * @throws SignFailException
     */
    protected function checkFilesExists(): void
    {
        if (!file_exists($this->certPath)) {
            throw new NoSuchCertificateFileException('Certificate does not exist');
        }
        if (!is_readable($this->certPath)) {
            throw new CannotReadCertificateException('Cannot read the certificate');
        }
        if (!file_exists($this->privateKeyPath)) {
            throw new NoSuchKeyFileException('Private key does not exist');
        }
        if (!is_readable($this->privateKeyPath)) {
            throw new CannotReadPrivateKeyException('Cannot read the private key');
        }
        if (!file_exists($this->tmpPath)) {
            throw new NoSuchTmpDirException('Temporary folder is not found');
        }
        if (!is_writable($this->tmpPath)) {
            throw new NoSuchTmpDirException('Temporary folder is not writable');
        }
    }

    /**
     * Generate random unique string
     *
     * @throws SignFailException
     */
    protected function getRandomString(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $e) {
            throw new SignFailException('Cannot generate random string', 0, $e);
        }
    }
}
