<?php

declare(strict_types=1);

namespace Esia;

use Esia\Exceptions\InvalidConfigurationException;

class Config
{
    private ?string $clientId = null;
    private ?string $redirectUrl = null;
    private ?string $privateKeyPath = null;
    private ?string $certPath = null;

    private string $portalUrl = 'https://esia-portal1.test.gosuslugi.ru/';
    private string $tokenUrlPath = 'aas/oauth2/v3/te';
    private string $codeUrlPath = 'aas/oauth2/v2/ac';
    private string $personUrlPath = 'rs/prns';
    private string $logoutUrlPath = 'idp/ext/Logout';
    private string $privateKeyPassword = '';
    private string $clientCertificateHash = '';

    /**
     * Path to the ESIA signing certificate used to validate the JWT access
     * token. When set, the token is validated automatically after it is
     * fetched. When null, token validation is skipped (backward compatible).
     */
    private ?string $esiaCertPath = null;

    /**
     * Expected `iss` claim of the access token. Null skips the issuer check.
     */
    private ?string $esiaTokenIssuer = null;

    /**
     * Allowed clock skew (in seconds) when validating time-based claims.
     */
    private int $tokenLeeway = 60;

    /**
     * @var string[]
     */
    private array $scope = [
        'fullname',
        'birthdate',
        'gender',
        'email',
        'mobile',
        'id_doc',
        'snils',
        'inn',
    ];

    private string $tmpPath = '/var/tmp';

    private string $responseType = 'code';
    private string $accessType = 'offline';

    private string $token = '';
    private string $oid = '';

    /**
     * Config constructor.
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(array $config = [])
    {
        // Required params
        $this->clientId = $config['clientId'] ?? $this->clientId;
        if (!$this->clientId) {
            throw new InvalidConfigurationException('Please provide clientId');
        }

        $this->redirectUrl = $config['redirectUrl'] ?? $this->redirectUrl;
        if (!$this->redirectUrl) {
            throw new InvalidConfigurationException('Please provide redirectUrl');
        }

        $this->privateKeyPath = $config['privateKeyPath'] ?? $this->privateKeyPath;
        if (!$this->privateKeyPath) {
            throw new InvalidConfigurationException('Please provide privateKeyPath');
        }
        $this->certPath = $config['certPath'] ?? $this->certPath;
        if (!$this->certPath) {
            throw new InvalidConfigurationException('Please provide certPath');
        }

        $this->portalUrl = $config['portalUrl'] ?? $this->portalUrl;
        $this->tokenUrlPath = $config['tokenUrlPath'] ?? $this->tokenUrlPath;
        $this->codeUrlPath = $config['codeUrlPath'] ?? $this->codeUrlPath;
        $this->personUrlPath = $config['personUrlPath'] ?? $this->personUrlPath;
        $this->logoutUrlPath = $config['logoutUrlPath'] ?? $this->logoutUrlPath;
        $this->privateKeyPassword = $config['privateKeyPassword'] ?? $this->privateKeyPassword;
        $this->clientCertificateHash = $config['clientCertificateHash'] ?? $this->clientCertificateHash;
        $this->esiaCertPath = $config['esiaCertPath'] ?? $this->esiaCertPath;
        $this->esiaTokenIssuer = $config['esiaTokenIssuer'] ?? $this->esiaTokenIssuer;
        $this->tokenLeeway = (int) ($config['tokenLeeway'] ?? $this->tokenLeeway);
        $this->oid = $config['oid'] ?? $this->oid;
        $scope = $config['scope'] ?? $this->scope;
        if (!is_array($scope)) {
            throw new InvalidConfigurationException('scope must be array of strings');
        }
        foreach ($scope as $scopeItem) {
            if (!is_string($scopeItem)) {
                throw new InvalidConfigurationException('scope must be array of strings');
            }
        }
        $this->scope = $scope;

        $this->responseType = $config['responseType'] ?? $this->responseType;
        $this->accessType = $config['accessType'] ?? $this->accessType;
        $this->tmpPath = $config['tmpPath'] ?? $this->tmpPath;
        $this->token = $config['token'] ?? $this->token;
    }

    public function getPortalUrl(): string
    {
        return $this->portalUrl;
    }

    public function getPrivateKeyPath(): string
    {
        return $this->privateKeyPath;
    }

    public function getPrivateKeyPassword(): string
    {
        return $this->privateKeyPassword;
    }

    public function getClientCertificateHash(): string
    {
        return $this->clientCertificateHash;
    }

    public function getEsiaCertPath(): ?string
    {
        return $this->esiaCertPath;
    }

    public function getEsiaTokenIssuer(): ?string
    {
        return $this->esiaTokenIssuer;
    }

    public function getTokenLeeway(): int
    {
        return $this->tokenLeeway;
    }

    public function getCertPath(): string
    {
        return $this->certPath;
    }
    
    public function getOid(): string
    {
        return $this->oid;
    }

    public function setOid(string $oid): void
    {
        $this->oid = $oid;
    }

    public function getScope(): array
    {
        return $this->scope;
    }

    public function getScopeString(): string
    {
        return implode(' ', $this->scope);
    }

    public function getResponseType(): string
    {
        return $this->responseType;
    }

    public function getAccessType(): string
    {
        return $this->accessType;
    }

    public function getTmpPath(): string
    {
        return $this->tmpPath;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    /**
     * Return an url for request to get an access token
     */
    public function getTokenUrl(): string
    {
        return $this->portalUrl . $this->tokenUrlPath;
    }

    /**
     * Return an url for request to get an authorization code
     */
    public function getCodeUrl(): string
    {
        return $this->portalUrl . $this->codeUrlPath;
    }

    /**
     * @return string
     * @throws InvalidConfigurationException
     */
    public function getPersonUrl(): string
    {
        if (!$this->oid) {
            throw new InvalidConfigurationException('Please provide oid');
        }
        return $this->portalUrl . $this->personUrlPath . '/' . $this->oid;
    }

    /**
     * Return an url for logout
     */
    public function getLogoutUrl(): string
    {
        return $this->portalUrl . $this->logoutUrlPath;
    }
}
