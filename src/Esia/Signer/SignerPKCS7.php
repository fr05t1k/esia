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

class SignerPKCS7 implements SignerInterface
{
    use LoggerAwareTrait;

    /**
     * Path to the certificate
     *
     * @var string
     */
    private $certPath;

    /**
     * Path to the private key
     *
     * @var string
     */
    private $privateKeyPath;

    /**
     * Password for the private key
     *
     * @var string
     */
    private $privateKeyPassword;

    /**
     * Temporary directory for message signing (must me writable)
     *
     * @var string
     */
    private $tmpPath;

    /**
     * SignerPKCS7 constructor.
     * @param string $certPath
     * @param string $privateKeyPath
     * @param string $privateKeyPassword
     * @param string $tmpPath
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
     * @param string $message
     * @return string
     * @throws SignFailException
     */
    public function sign(string $message): string
    {
        $this->checkFilesExists();

        $certContent = file_get_contents($this->certPath);
        $keyContent = file_get_contents($this->privateKeyPath);

        $cert = openssl_x509_read($certContent);

        if ($cert === false) {
            throw new CannotReadCertificateException('Cannot read the certificate');
        }

        $this->logger->debug('Cert: ' . print_r($cert, true), ['cert' => $cert]);

        $privateKey = openssl_pkey_get_private($keyContent, $this->privateKeyPassword);

        if ($privateKey === false) {
            throw new CannotReadCertificateException();
        }

        $this->logger->debug('Private key: : ' . print_r($privateKey, true), ['privateKey' => $privateKey]);

        // random unique directories for sign
        $messageFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        $signFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        file_put_contents($messageFile, $message);

        $signResult = openssl_pkcs7_sign(
            $messageFile,
            $signFile,
            $cert,
            $privateKey,
            []
        );

        if ($signResult) {
            $this->logger->debug('Sign success');
        } else {
            $this->logger->error('Sign fail');
            $this->logger->error('SSL error: ' . openssl_error_string());
            throw new SignFailException('Cannot sign the message');
        }

        $signed = file_get_contents($signFile);

        # split by section
        $signed = explode("\n\n", $signed);

        # get third section which contains sign and join into one line
        $sign = str_replace("\n", '', $this->urlSafe($signed[3]));

        unlink($signFile);
        unlink($messageFile);

        return $sign;
    }

    /**
     * @throws SignFailException
     */
    private function checkFilesExists(): void
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
    private function getRandomString(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Url safe for base64
     *
     * @param string $string
     * @return string
     */
    private function urlSafe($string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
