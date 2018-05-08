<?php

namespace Esia;

use Esia\Exceptions\InvalidConfigurationException;

class Config
{
    private $clientId;
    private $redirectUrl;
    private $privateKeyPath;
    private $certPath;

    private $portalUrl = 'http://esia-portal1.test.gosuslugi.ru/';
    private $tokenUrl = 'aas/oauth2/te';
    private $codeUrl = 'aas/oauth2/ac';
    private $personUrl = 'rs/prns';
    private $privateKeyPassword = '';

    /**
     * @var string[]
     */
    private $scope = [
        'fullname',
        'birthdate',
        'gender',
        'email',
        'mobile',
        'id_doc',
        'snils',
        'inn',
    ];

    private $tmpPath = '/var/tmp';

    private $responseType = 'code';
    private $accessType = 'offline';

    private $token = '';
    private $oid = '';

    /**
     * Config constructor.
     *
     * @param array $config
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
        $this->tokenUrl = $config['tokenUrl'] ?? $this->tokenUrl;
        $this->codeUrl = $config['codeUrl'] ?? $this->codeUrl;
        $this->personUrl = $config['personUrl'] ?? $this->personUrl;
        $this->privateKeyPassword = $config['privateKeyPassword'] ?? $this->privateKeyPassword;
        $this->oid = $config['oid'] ?? $this->oid;
        $this->scope = $config['scope'] ?? $this->scope;
        if (!is_array($this->scope)) {
            throw new InvalidConfigurationException('scope must be array of strings');
        }

        $this->responseType = $config['responseType'] ?? $this->responseType;
        $this->accessType = $config['accessType'] ?? $this->accessType;
        $this->tmpPath = $config['tmpPath'] ?? $this->tmpPath;
        $this->token = $config['token'] ?? $this->token;
    }

    public function getPortalUrl(): string
    {
        return $this->portalUrl;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    public function getCodeUrl(): string
    {
        return $this->codeUrl;
    }

    public function getPersonUrl(): string
    {
        return $this->personUrl;
    }

    public function getPrivateKeyPath(): string
    {
        return $this->privateKeyPath;
    }

    public function getPrivateKeyPassword(): string
    {
        return $this->privateKeyPassword;
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
}
