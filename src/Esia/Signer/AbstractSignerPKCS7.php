<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\CannotReadCertificateException;
use Esia\Signer\Exceptions\CannotReadPrivateKeyException;
use Esia\Signer\Exceptions\NoSuchCertificateFileException;
use Esia\Signer\Exceptions\NoSuchKeyFileException;
use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class AbstractSignerPKCS7
{
    use LoggerAwareTrait;

    /**
     * Path to the certificate
     *
     * @var string
     */
    protected $certPath;

    /**
     * Path to the private key
     *
     * @var string
     */
    protected $privateKeyPath;

    /**
     * Password for the private key
     *
     * @var string
     */
    protected $privateKeyPassword;

    /**
     * SignerPKCS7 constructor.
     */
    public function __construct(
        string $certPath,
        string $privateKeyPath,
        ?string $privateKeyPassword,
        string $tmpPath
    ) {
        $this->certPath = $certPath;
        $this->privateKeyPath = $privateKeyPath;
        $this->privateKeyPassword = $privateKeyPassword;
        $this->tmpPath = $tmpPath;
        $this->logger = new NullLogger();
    }

    /**
     * Temporary directory for message signing (must me writable)
     *
     * @var string
     */
    protected $tmpPath;

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
     */
    protected function getRandomString(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Url safe for base64
     */
    protected function urlSafe(string $string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
