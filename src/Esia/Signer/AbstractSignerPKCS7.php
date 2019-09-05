<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\CannotReadCertificateException;
use Esia\Signer\Exceptions\CannotReadPrivateKeyException;
use Esia\Signer\Exceptions\NoSuchCertificateFileException;
use Esia\Signer\Exceptions\NoSuchKeyFileException;
use Esia\Signer\Exceptions\NoSuchTmpDirException;
use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;

class AbstractSignerPKCS7
{
    use LoggerAwareTrait;

    /**
     * Path to the certificate
     *
     * @var string
     */
    protected $certPath;

    /**
     * Path to the protected key
     *
     * @var string
     */
    protected $protectedKeyPath;

    /**
     * Password for the protected key
     *
     * @var string
     */
    protected $protectedKeyPassword;

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
     *
     * @return string
     */
    protected function getRandomString(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Url safe for base64
     *
     * @param string $string
     * @return string
     */
    protected function urlSafe($string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
