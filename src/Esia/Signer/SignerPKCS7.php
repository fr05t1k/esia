<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\CannotReadCertificateException;
use Esia\Signer\Exceptions\CannotReadPrivateKeyException;
use Esia\Signer\Exceptions\SignFailException;

class SignerPKCS7 extends AbstractSignerPKCS7 implements SignerInterface
{
    private $pkcs7Flags = PKCS7_DETACHED;

    public function addPKCS7Flag(int $pkcs7Flag): void
    {
        $this->pkcs7Flags |= $pkcs7Flag;
    }

    /**
     * @throws SignFailException
     */
    public function sign(string $message): string
    {
        $this->checkFilesExists();

        $certContent = file_get_contents($this->certPath);
        $keyContent = file_get_contents($this->privateKeyPath);

        $cert = openssl_x509_read($certContent);

        if ($cert === false) {
            throw new CannotReadCertificateException('Cannot read the certificate: ' . openssl_error_string());
        }

        $this->logger->debug('Cert: ' . print_r($cert, true), ['cert' => $cert]);

        $privateKey = openssl_pkey_get_private($keyContent, $this->privateKeyPassword);

        if ($privateKey === false) {
            throw new CannotReadPrivateKeyException('Cannot read the private key: ' . openssl_error_string());
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
            [],
            $this->pkcs7Flags
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
}
