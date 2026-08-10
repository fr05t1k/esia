<?php

declare(strict_types=1);

namespace tests\unit\Support;

/**
 * Mints signed JWT fixtures from the committed test key
 * (`tests/_data/jwt-signing-key.pem`) so the token/claim-validation paths can
 * be exercised deterministically and fully offline (no live ESIA).
 *
 * The signing material is committed; tokens are minted at call time so that
 * time-based claims (exp/nbf/iat) stay meaningful and never rot.
 */
final class JwtFixture
{
    public const ISSUER = 'http://esia.gosuslugi.ru/';
    public const AUDIENCE = 'INSP03211';
    public const SUBJECT_ID = 1000599454;

    public static function privateKey(): string
    {
        return (string) file_get_contents(self::dir() . '/jwt-signing-key.pem');
    }

    public static function publicKeyPath(): string
    {
        return self::dir() . '/jwt-signing-pub.pem';
    }

    /**
     * A fully valid token for the default issuer/audience/subject.
     */
    public static function valid(): string
    {
        return self::make();
    }

    public static function expired(): string
    {
        $now = time();

        return self::make([
            'iat' => $now - 7200,
            'nbf' => $now - 7200,
            'exp' => $now - 3600,
        ]);
    }

    public static function notYetValid(): string
    {
        return self::make(['nbf' => time() + 3600]);
    }

    public static function wrongAudience(): string
    {
        return self::make(['aud' => 'SOMEONE_ELSE']);
    }

    public static function wrongIssuer(): string
    {
        return self::make(['iss' => 'http://evil.example/']);
    }

    /**
     * A valid token whose signature has been corrupted.
     */
    public static function tampered(): string
    {
        [$header, $payload] = explode('.', self::make());

        return $header . '.' . $payload . '.' . self::base64UrlEncode('forged-signature');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function make(array $overrides = []): string
    {
        $now = time();
        $claims = $overrides + [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            'urn:esia:sbj_id' => self::SUBJECT_ID,
        ];

        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $payload = self::base64UrlEncode((string) json_encode($claims));
        openssl_sign($header . '.' . $payload, $signature, self::privateKey(), OPENSSL_ALGO_SHA256);

        return $header . '.' . $payload . '.' . self::base64UrlEncode($signature);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function dir(): string
    {
        return dirname(__DIR__, 2) . '/_data';
    }
}
