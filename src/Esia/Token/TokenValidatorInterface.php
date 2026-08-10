<?php

declare(strict_types=1);

namespace Esia\Token;

use Esia\Exceptions\InvalidTokenException;

/**
 * Validates a JWT (access token) received from ESIA.
 *
 * Implementations must verify the token signature against the ESIA signing
 * certificate and validate the standard claims (iss, aud/client_id, exp, nbf,
 * iat). This makes validation pluggable so integrators can supply the current
 * ESIA certificate or a custom validation strategy.
 */
interface TokenValidatorInterface
{
    /**
     * Validate the given JWT and return its decoded claims (the payload).
     *
     * @param string $token The raw JWT (header.payload.signature).
     *
     * @return array<string, mixed> The decoded claims.
     *
     * @throws InvalidTokenException When the signature or any claim is invalid.
     */
    public function validate(string $token): array;
}
