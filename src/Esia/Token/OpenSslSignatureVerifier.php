<?php

declare(strict_types=1);

namespace Esia\Token;

use Esia\Exceptions\SignatureInvalidException;
use OpenSSLAsymmetricKey;

/**
 * OpenSSL based JWT signature verifier.
 *
 * Supports RSA (RS256/RS384/RS512) out of the box and the GOST 34.10-2012
 * algorithms used by ESIA in production when the OpenSSL build provides the
 * GOST engine (e.g. via `libengine-gost-openssl1.1`).
 */
class OpenSslSignatureVerifier implements SignatureVerifierInterface
{
    /**
     * Map of JWT "alg" values to OpenSSL digest algorithms.
     *
     * RSA algorithms use the OPENSSL_ALGO_* integer constants; GOST algorithms
     * use the digest method name resolved by the loaded engine.
     *
     * @var array<string, int|string>
     */
    private const ALGORITHMS = [
        'RS256' => OPENSSL_ALGO_SHA256,
        'RS384' => OPENSSL_ALGO_SHA384,
        'RS512' => OPENSSL_ALGO_SHA512,
        'GOST3410_2012_256' => 'md_gost12_256',
        'GOST3410_2012_512' => 'md_gost12_512',
    ];

    private string $certificate;

    /**
     * @param string $certificate The ESIA signing certificate or public key in
     *                            PEM format (the certificate contents, not a path).
     */
    public function __construct(string $certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Build a verifier from a certificate file path.
     *
     * @throws SignatureInvalidException When the file cannot be read.
     */
    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new SignatureInvalidException(
                sprintf('Cannot read the ESIA signing certificate: %s', $path)
            );
        }

        return new self($contents);
    }

    public function verify(string $signingInput, string $signature, string $algorithm): void
    {
        if (!isset(self::ALGORITHMS[$algorithm])) {
            throw new SignatureInvalidException(
                sprintf('Unsupported JWT signature algorithm: %s', $algorithm)
            );
        }

        $publicKey = $this->resolvePublicKey();

        $result = openssl_verify(
            $signingInput,
            $signature,
            $publicKey,
            self::ALGORITHMS[$algorithm]
        );

        if ($result !== 1) {
            throw new SignatureInvalidException('JWT signature verification failed');
        }
    }

    /**
     * @throws SignatureInvalidException
     */
    private function resolvePublicKey(): OpenSSLAsymmetricKey
    {
        $publicKey = openssl_pkey_get_public($this->certificate);
        if ($publicKey === false) {
            throw new SignatureInvalidException(
                'The ESIA signing certificate is not a valid public key or certificate'
            );
        }

        return $publicKey;
    }
}
