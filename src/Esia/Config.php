<?php

namespace Esia;

class Config
{

    private $clientId;
    private $redirectUrl;
    private $portalUrl = 'https://esia-portal1.test.gosuslugi.ru/';
    private $tokenUrl = 'aas/oauth2/te';
    private $codeUrl = 'aas/oauth2/ac';
    private $personUrl = 'rs/prns';
    private $privateKeyPath;
    private $privateKeyPassword;
    private $certPath;
    private $oid = '';

    private $scope = 'fullname birthdate gender email mobile id_doc snils inn';

    private $clientSecret = null;
    private $responseType = 'code';
    private $state = null;
    private $timestamp = null;
    private $accessType = 'offline';
    private $tmpPath;

    private $token = null;

    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }

    /**
     * @return string
     */
    public function getPortalUrl(): string
    {
        return $this->portalUrl;
    }

    /**
     * @return string
     */
    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    /**
     * @return string
     */
    public function getCodeUrl(): string
    {
        return $this->codeUrl;
    }

    /**
     * @return string
     */
    public function getPersonUrl(): string
    {
        return $this->personUrl;
    }

    /**
     * @return mixed
     */
    public function getPrivateKeyPath()
    {
        return $this->privateKeyPath;
    }

    /**
     * @return mixed
     */
    public function getPrivateKeyPassword()
    {
        return $this->privateKeyPassword;
    }

    /**
     * @return mixed
     */
    public function getCertPath()
    {
        return $this->certPath;
    }

    /**
     * @return string
     */
    public function getOid(): string
    {
        return $this->oid;
    }

    /**
     * @param string $oid
     */
    public function setOid(string $oid): void
    {
        $this->oid = $oid;
    }

    /**
     * @return string
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @return null
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * @return string
     */
    public function getResponseType(): string
    {
        return $this->responseType;
    }

    /**
     * @return null
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return null
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @return string
     */
    public function getAccessType(): string
    {
        return $this->accessType;
    }

    /**
     * @return mixed
     */
    public function getTmpPath()
    {
        return $this->tmpPath;
    }

    /**
     * @return null|string
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @param string $token
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @return mixed
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }
}
