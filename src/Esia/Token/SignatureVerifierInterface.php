<?php

declare(strict_types=1);

namespace Esia\Token;

use Esia\Exceptions\SignatureInvalidException;

/**
 * Verifies the cryptographic signature of a JWT.
 *
 * Implementations are algorithm specific. The default
 * {@see OpenSslSignatureVerifier} covers RSA (RS256/384/512) and, when the
 * OpenSSL GOST engine is available, the GOST 34.10-2012 algorithms used by
 * ESIA in production. Integrators may provide their own implementation (e.g.
 * a CryptoPro-backed verifier) to support other environments.
 */
interface SignatureVerifierInterface
{
    /**
     * @param string $signingInput The JWT signing input ("header.payload").
     * @param string $signature    The raw (already base64url-decoded) signature.
     * @param string $algorithm    The value of the JWT "alg" header.
     *
     * @throws SignatureInvalidException When the signature is invalid or the
     *                                   algorithm is not supported.
     */
    public function verify(string $signingInput, string $signature, string $algorithm): void;
}
