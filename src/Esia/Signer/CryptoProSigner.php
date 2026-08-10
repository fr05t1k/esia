<?php

declare(strict_types=1);

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Signs messages with CryptoPro CSP through the CryptoPro PHP extension
 * (the `\CPStore` / `\CPSigner` / `\CPSignedData` COM-like classes).
 *
 * Runtime requirements:
 *  - The proprietary CryptoPro PHP extension must be installed and enabled.
 *  - A GOST R 34.10-2012 certificate with a private key must be present in the
 *    current user's "My" store, referenced by its SHA-1 thumbprint.
 *
 * The extension is not available in this repository's test environment, so this
 * signer is intentionally excluded from static analysis (see phpstan.neon).
 *
 * @see https://docs.cryptopro.ru/cades/plugin/plugin-methods
 */
final class CryptoProSigner implements SignerInterface
{
    use LoggerAwareTrait;
    use UrlSafeSignatureTrait;

    /**
     * @param string $thumbprint SHA-1 thumbprint of the signing certificate
     * @param string|null $pin PIN/password for the private key container, if any
     */
    public function __construct(
        private string $thumbprint,
        private ?string $pin = null
    ) {
        $this->logger = new NullLogger();
    }

    /**
     * @throws SignFailException
     */
    public function sign(string $message): string
    {
        if (!class_exists(\CPStore::class)) {
            throw new SignFailException('The CryptoPro PHP extension is not available');
        }

        $store = new \CPStore();
        $store->Open(CURRENT_USER_STORE, 'My', STORE_OPEN_READ_ONLY);

        $certificates = $store->get_Certificates();
        $found = $certificates->Find(CERTIFICATE_FIND_SHA1_HASH, $this->thumbprint, 0);
        $certificate = $found->Count() > 0 ? $found->Item(1) : null;
        if ($certificate === null) {
            throw new SignFailException('Cannot find the certificate by thumbprint');
        }
        if ($certificate->HasPrivateKey() === false) {
            throw new SignFailException('The certificate has no associated private key');
        }

        $signer = new \CPSigner();
        $signer->set_Certificate($certificate);
        if ($this->pin !== null && $this->pin !== '') {
            $signer->set_KeyPin($this->pin);
        }

        $signedData = new \CPSignedData();
        $signedData->set_ContentEncoding(BASE64_TO_BINARY);
        $signedData->set_Content(base64_encode($message));

        $signature = $signedData->SignCades($signer, CADES_BES, true, ENCODE_BASE64);

        return $this->normalizeBase64Signature($signature);
    }
}
