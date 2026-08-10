<?php

declare(strict_types=1);

namespace tests\unit\Token;

use Codeception\Test\Unit;
use Esia\Exceptions\InvalidClaimException;
use Esia\Exceptions\SignatureInvalidException;
use Esia\Exceptions\TokenExpiredException;
use Esia\Token\JwtValidator;
use Esia\Token\OpenSslSignatureVerifier;

class JwtValidatorTest extends Unit
{
    private const ISSUER = 'http://esia.gosuslugi.ru/';
    private const AUDIENCE = 'INSP03211';

    private string $certificate;

    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        self::assertNotFalse($resource, 'Unable to generate an RSA key for the test');

        openssl_pkey_export($resource, $privateKey);
        $this->privateKey = $privateKey;

        $details = openssl_pkey_get_details($resource);
        self::assertIsArray($details);
        $this->certificate = $details['key'];
    }

    private function validator(int $leeway = 60): JwtValidator
    {
        return new JwtValidator(
            new OpenSslSignatureVerifier($this->certificate),
            self::ISSUER,
            self::AUDIENCE,
            $leeway
        );
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function makeToken(array $claims, string $alg = 'RS256', bool $tamper = false): string
    {
        $algos = [
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
        ];

        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => $alg]));
        $payload = self::base64UrlEncode((string) json_encode($claims));

        openssl_sign($header . '.' . $payload, $signature, $this->privateKey, $algos[$alg]);
        if ($tamper) {
            $signature = strrev($signature);
        }

        return $header . '.' . $payload . '.' . self::base64UrlEncode($signature);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validClaims(array $overrides = []): array
    {
        $now = time();

        return $overrides + [
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            'urn:esia:sbj_id' => 123,
        ];
    }

    public function testValidTokenReturnsClaims(): void
    {
        $token = $this->makeToken($this->validClaims());

        $claims = $this->validator()->validate($token);

        self::assertSame(123, $claims['urn:esia:sbj_id']);
        self::assertSame(self::ISSUER, $claims['iss']);
    }

    /**
     * @dataProvider rsaAlgorithmProvider
     */
    public function testValidatesEachRsaAlgorithm(string $alg): void
    {
        $token = $this->makeToken($this->validClaims(), $alg);

        $claims = $this->validator()->validate($token);

        self::assertSame(123, $claims['urn:esia:sbj_id']);
    }

    /**
     * @return array<string, array{string}>
     */
    public function rsaAlgorithmProvider(): array
    {
        return [
            'RS256' => ['RS256'],
            'RS384' => ['RS384'],
            'RS512' => ['RS512'],
        ];
    }

    /**
     * A signature made with a different digest than the header advertises must
     * be rejected (guards the alg→digest mapping).
     */
    public function testRejectsAlgorithmMismatch(): void
    {
        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'RS256']));
        $payload = self::base64UrlEncode((string) json_encode($this->validClaims()));
        openssl_sign($header . '.' . $payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA512);
        $token = $header . '.' . $payload . '.' . self::base64UrlEncode($signature);

        $this->expectException(SignatureInvalidException::class);
        $this->validator()->validate($token);
    }

    public function testRejectsUnsupportedAlgorithm(): void
    {
        $token = $this->makeToken($this->validClaims());
        // Swap the header to an unsupported algorithm while keeping a signature.
        [, $payload, $signature] = explode('.', $token);
        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $forged = $header . '.' . $payload . '.' . $signature;

        $this->expectException(SignatureInvalidException::class);
        $this->validator()->validate($forged);
    }

    public function testAcceptsClientIdAsAudience(): void
    {
        $claims = $this->validClaims();
        unset($claims['aud']);
        $claims['client_id'] = self::AUDIENCE;

        $result = $this->validator()->validate($this->makeToken($claims));

        self::assertSame(self::AUDIENCE, $result['client_id']);
    }

    public function testExpiredTokenThrows(): void
    {
        $token = $this->makeToken($this->validClaims([
            'exp' => time() - 3600,
            'iat' => time() - 7200,
            'nbf' => time() - 7200,
        ]));

        $this->expectException(TokenExpiredException::class);
        $this->validator()->validate($token);
    }

    public function testNotYetValidTokenThrows(): void
    {
        $token = $this->makeToken($this->validClaims(['nbf' => time() + 3600]));

        $this->expectException(TokenExpiredException::class);
        $this->validator()->validate($token);
    }

    public function testTamperedSignatureThrows(): void
    {
        $token = $this->makeToken($this->validClaims(), 'RS256', true);

        $this->expectException(SignatureInvalidException::class);
        $this->validator()->validate($token);
    }

    public function testTamperedPayloadThrows(): void
    {
        $token = $this->makeToken($this->validClaims());
        [$header, , $signature] = explode('.', $token);
        $forged = self::base64UrlEncode((string) json_encode($this->validClaims(['urn:esia:sbj_id' => 999])));
        $tampered = $header . '.' . $forged . '.' . $signature;

        $this->expectException(SignatureInvalidException::class);
        $this->validator()->validate($tampered);
    }

    public function testWrongAudienceThrows(): void
    {
        $token = $this->makeToken($this->validClaims(['aud' => 'SOMEONE_ELSE']));

        $this->expectException(InvalidClaimException::class);
        $this->validator()->validate($token);
    }

    public function testWrongIssuerThrows(): void
    {
        $token = $this->makeToken($this->validClaims(['iss' => 'http://evil.example/']));

        $this->expectException(InvalidClaimException::class);
        $this->validator()->validate($token);
    }

    /**
     * @dataProvider nonNumericTimeClaimProvider
     */
    public function testNonNumericTimeClaimThrows(string $claim): void
    {
        // Sign a properly-signed token whose time claim is a non-numeric string.
        $token = $this->makeToken($this->validClaims([$claim => 'invalid']));

        $this->expectException(InvalidClaimException::class);
        $this->validator()->validate($token);
    }

    /**
     * @return array<string, array{string}>
     */
    public function nonNumericTimeClaimProvider(): array
    {
        return [
            'exp' => ['exp'],
            'nbf' => ['nbf'],
            'iat' => ['iat'],
        ];
    }

    public function testAcceptsNumericStringTimeClaims(): void
    {
        $now = time();
        $token = $this->makeToken($this->validClaims([
            'exp' => (string) ($now + 3600),
            'nbf' => (string) $now,
            'iat' => (string) $now,
        ]));

        $claims = $this->validator()->validate($token);

        self::assertSame(123, $claims['urn:esia:sbj_id']);
    }

    public function testUnsignedTokenIsRejected(): void
    {
        $header = self::base64UrlEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = self::base64UrlEncode((string) json_encode($this->validClaims()));
        $token = $header . '.' . $payload . '.';

        $this->expectException(SignatureInvalidException::class);
        $this->validator()->validate($token);
    }

    public function testMalformedTokenIsRejected(): void
    {
        $this->expectException(InvalidClaimException::class);
        $this->validator()->validate('not-a-jwt');
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
