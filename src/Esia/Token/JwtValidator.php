<?php

declare(strict_types=1);

namespace Esia\Token;

use Esia\Exceptions\InvalidClaimException;
use Esia\Exceptions\SignatureInvalidException;
use Esia\Exceptions\TokenExpiredException;

/**
 * Default JWT validator for tokens issued by ESIA.
 *
 * It verifies the token signature (via a {@see SignatureVerifierInterface})
 * and validates the standard claims: `exp`, `nbf`, `iat` (with a configurable
 * leeway for clock skew), the issuer (`iss`) and the audience
 * (`aud`/`client_id`).
 */
class JwtValidator implements TokenValidatorInterface
{
    private SignatureVerifierInterface $signatureVerifier;

    private ?string $expectedIssuer;

    private ?string $expectedAudience;

    private int $leeway;

    /**
     * @param SignatureVerifierInterface $signatureVerifier Verifier bound to the ESIA certificate.
     * @param string|null                $expectedIssuer    Expected `iss` claim, or null to skip.
     * @param string|null                $expectedAudience  Expected audience/`client_id`, or null to skip.
     * @param int                        $leeway            Allowed clock skew in seconds for time claims.
     */
    public function __construct(
        SignatureVerifierInterface $signatureVerifier,
        ?string $expectedIssuer = null,
        ?string $expectedAudience = null,
        int $leeway = 60
    ) {
        $this->signatureVerifier = $signatureVerifier;
        $this->expectedIssuer = $expectedIssuer;
        $this->expectedAudience = $expectedAudience;
        $this->leeway = $leeway;
    }

    public function validate(string $token): array
    {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            throw new InvalidClaimException('The token is not a well-formed JWT');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $header = $this->decodeSegment($encodedHeader, 'header');
        $payload = $this->decodeSegment($encodedPayload, 'payload');
        $signature = self::base64UrlDecode($encodedSignature);

        if ($signature === '') {
            throw new SignatureInvalidException('The token has no signature');
        }

        $algorithm = $header['alg'] ?? '';
        if (!is_string($algorithm) || $algorithm === '' || strcasecmp($algorithm, 'none') === 0) {
            throw new SignatureInvalidException('The token does not specify a signing algorithm');
        }

        $this->signatureVerifier->verify($encodedHeader . '.' . $encodedPayload, $signature, $algorithm);

        $this->validateClaims($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws TokenExpiredException
     * @throws InvalidClaimException
     */
    private function validateClaims(array $payload): void
    {
        $now = time();

        $exp = $this->numericClaim($payload, 'exp');
        if ($exp !== null && $now >= ($exp + $this->leeway)) {
            throw new TokenExpiredException('The token has expired');
        }

        $nbf = $this->numericClaim($payload, 'nbf');
        if ($nbf !== null && $now < ($nbf - $this->leeway)) {
            throw new TokenExpiredException('The token is not valid yet (nbf)');
        }

        $iat = $this->numericClaim($payload, 'iat');
        if ($iat !== null && ($iat - $this->leeway) > $now) {
            throw new InvalidClaimException('The token was issued in the future (iat)');
        }

        if ($this->expectedIssuer !== null) {
            $issuer = $payload['iss'] ?? null;
            if ($issuer !== $this->expectedIssuer) {
                throw new InvalidClaimException(
                    sprintf('Unexpected token issuer: %s', is_string($issuer) ? $issuer : 'none')
                );
            }
        }

        if ($this->expectedAudience !== null) {
            $this->assertAudience($payload);
        }
    }

    /**
     * Read a JWT NumericDate claim, rejecting non-numeric values.
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidClaimException
     */
    private function numericClaim(array $payload, string $name): ?int
    {
        if (!isset($payload[$name])) {
            return null;
        }

        $value = $payload[$name];
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new InvalidClaimException(
                sprintf('The "%s" claim is not a valid numeric date', $name)
            );
        }

        return (int) $value;
    }

    /**
     * ESIA tokens may carry the audience either in the standard `aud` claim or
     * in a `client_id` claim. Accept both.
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidClaimException
     */
    private function assertAudience(array $payload): void
    {
        $audience = $payload['aud'] ?? $payload['client_id'] ?? null;

        $audiences = is_array($audience) ? $audience : [$audience];
        foreach ($audiences as $value) {
            if (is_string($value) && $value === $this->expectedAudience) {
                return;
            }
        }

        throw new InvalidClaimException('The token audience does not match the client id');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidClaimException
     */
    private function decodeSegment(string $segment, string $name): array
    {
        $decoded = json_decode(self::base64UrlDecode($segment), true);
        if (!is_array($decoded)) {
            throw new InvalidClaimException(sprintf('Cannot decode the JWT %s', $name));
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function base64UrlDecode(string $string): string
    {
        $base64 = strtr($string, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding > 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);

        return $decoded === false ? '' : $decoded;
    }
}
