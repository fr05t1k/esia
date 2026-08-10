<?php

declare(strict_types=1);

namespace Esia;

use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\ForbiddenException;
use Esia\Exceptions\RequestFailException;
use Esia\Signer\Exceptions\CannotGenerateRandomIntException;
use Esia\Signer\Exceptions\SignFailException;
use Esia\Signer\SignerInterface;
use Esia\Signer\SignerPKCS7;
use Esia\Token\JwtValidator;
use Esia\Token\OpenSslSignatureVerifier;
use Esia\Token\TokenValidatorInterface;
use Exception;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Class OpenId
 */
class OpenId
{
    use LoggerAwareTrait;

    private SignerInterface $signer;

    /**
     * Validator for the JWT access token. Null means validation is disabled
     * (backward compatible default).
     */
    private ?TokenValidatorInterface $tokenValidator = null;

    /**
     * Http Client
     */
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    /**
     * Config
     */
    private Config $config;

    public function __construct(
        Config $config,
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->config = $config;
        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->logger = new NullLogger();
        $this->signer = new SignerPKCS7(
            $config->getCertPath(),
            $config->getPrivateKeyPath(),
            $config->getPrivateKeyPassword(),
            $config->getTmpPath()
        );

        $esiaCertPath = $config->getEsiaCertPath();
        if ($esiaCertPath !== null && $esiaCertPath !== '') {
            $this->tokenValidator = new JwtValidator(
                OpenSslSignatureVerifier::fromFile($esiaCertPath),
                $config->getEsiaTokenIssuer(),
                $config->getClientId(),
                $config->getTokenLeeway()
            );
        }
    }

    /**
     * Replace the default JWT token validator or enable validation.
     *
     * When a validator is set, {@see OpenId::getToken()} verifies the token
     * signature and claims before accepting it. This makes validation
     * pluggable so integrators can supply the current ESIA certificate.
     */
    public function setTokenValidator(?TokenValidatorInterface $tokenValidator): void
    {
        $this->tokenValidator = $tokenValidator;
    }

    /**
     * Replace default signer
     */
    public function setSigner(SignerInterface $signer): void
    {
        $this->signer = $signer;
    }

    /**
     * Get config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Return an url for authentication
     *
     * ```php
     *     <a href="<?=$esia->buildUrl()?>">Login</a>
     * ```
     *
     * @return string
     * @throws SignFailException
     */
    public function buildUrl()
    {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();
        $message = $this->config->getScopeString()
            . $timestamp
            . $this->config->getClientId()
            . $state;

        $clientSecret = $this->signer->sign($message);

        $url = $this->config->getCodeUrl() . '?%s';

        $params = [
            'client_id' => $this->config->getClientId(),
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScopeString(),
            'response_type' => $this->config->getResponseType(),
            'state' => $state,
            'access_type' => $this->config->getAccessType(),
            'timestamp' => $timestamp,
        ];

        $clientCertificateHash = $this->config->getClientCertificateHash();
        if ($clientCertificateHash !== '') {
            $params['client_certificate_hash'] = $clientCertificateHash;
        }

        $request = http_build_query($params);

        return sprintf($url, $request);
    }

    /**
     * Return an url for logout
     */
    public function buildLogoutUrl(?string $redirectUrl = null): string
    {
        $url = $this->config->getLogoutUrl() . '?%s';
        $params = [
            'client_id' => $this->config->getClientId(),
        ];

        if ($redirectUrl) {
            $params['redirect_url'] = $redirectUrl;
        }

        $request = http_build_query($params);

        return sprintf($url, $request);
    }

    /**
     * Method collect a token with given code
     *
     * @throws SignFailException
     * @throws AbstractEsiaException
     */
    public function getToken(string $code): string
    {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();

        $clientSecret = $this->signer->sign(
            $this->config->getScopeString()
            . $timestamp
            . $this->config->getClientId()
            . $state
        );

        $body = [
            'client_id' => $this->config->getClientId(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_secret' => $clientSecret,
            'state' => $state,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScopeString(),
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $state,
        ];

        $clientCertificateHash = $this->config->getClientCertificateHash();
        if ($clientCertificateHash !== '') {
            $body['client_certificate_hash'] = $clientCertificateHash;
        }

        $request = $this->requestFactory
            ->createRequest('POST', $this->config->getTokenUrl())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream(http_build_query($body)));

        $payload = $this->sendRequest($request);

        $this->logger->debug('Payload: ', $payload);

        $token = $payload['access_token'];
        $this->config->setToken($token);

        # get object id from token
        $chunks = explode('.', $token);
        if ($this->tokenValidator !== null) {
            $claims = $this->tokenValidator->validate($token);
        } else {
            $claims = json_decode($this->base64UrlSafeDecode($chunks[1]), true);
        }
        $this->config->setOid((string) $claims['urn:esia:sbj_id']);

        return $token;
    }

    /**
     * Fetch person info from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws AbstractEsiaException
     */
    public function getPersonInfo(): array
    {
        $url = $this->config->getPersonUrl();

        return $this->sendRequest($this->requestFactory->createRequest('GET', $url));
    }

    /**
     * Fetch contact info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws Exceptions\InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function getContactInfo(): array
    {
        $url = $this->config->getPersonUrl() . '/ctts';
        $payload = $this->sendRequest($this->requestFactory->createRequest('GET', $url));

        if ($payload && $payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }


    /**
     * Fetch address from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws Exceptions\InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function getAddressInfo(): array
    {
        $url = $this->config->getPersonUrl() . '/addrs';
        $payload = $this->sendRequest($this->requestFactory->createRequest('GET', $url));

        if ($payload && $payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }

    /**
     * Fetch documents info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws Exceptions\InvalidConfigurationException
     * @throws AbstractEsiaException
     */
    public function getDocInfo(): array
    {
        $url = $this->config->getPersonUrl() . '/docs';

        $payload = $this->sendRequest($this->requestFactory->createRequest('GET', $url));

        if ($payload && $payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }

    /**
     * This method can iterate on each element
     * and fetch entities from esia by url
     *
     * @param string[] $elements
     * @throws AbstractEsiaException
     */
    private function collectArrayElements(array $elements): array
    {
        $result = [];
        foreach ($elements as $elementUrl) {
            $elementPayload = $this->sendRequest($this->requestFactory->createRequest('GET', $elementUrl));

            if ($elementPayload) {
                $result[] = $elementPayload;
            }
        }

        return $result;
    }

    /**
     * @throws AbstractEsiaException
     */
    private function sendRequest(RequestInterface $request): array
    {
        try {
            if ($this->config->getToken()) {
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $request = $request->withHeader('Authorization', 'Bearer ' . $this->config->getToken());
            }
            $response = $this->client->sendRequest($request);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 403) {
                throw new ForbiddenException('Request is forbidden');
            }
            if ($statusCode >= 400) {
                throw new RequestFailException(
                    sprintf('Request is failed with status code %d', $statusCode)
                );
            }

            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (!is_array($responseBody)) {
                throw new RuntimeException(
                    sprintf(
                        'Cannot decode response body. JSON error (%d): %s',
                        json_last_error(),
                        json_last_error_msg()
                    )
                );
            }

            return $responseBody;
        } catch (ClientExceptionInterface $e) {
            $this->logger->error('Request was failed', ['exception' => $e]);
            throw new RequestFailException('Request is failed', 0, $e);
        } catch (RuntimeException $e) {
            $this->logger->error('Cannot read body', ['exception' => $e]);
            throw new RequestFailException('Cannot read body', 0, $e);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Wrong header', ['exception' => $e]);
            throw new RequestFailException('Wrong header', 0, $e);
        }
    }

    private function getTimeStamp(): string
    {
        return date('Y.m.d H:i:s O');
    }

    /**
     * Generate state with uuid
     *
     * @throws SignFailException
     */
    private function buildState(): string
    {
        try {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
        } catch (Exception $e) {
            throw new CannotGenerateRandomIntException('Cannot generate random integer', 0, $e);
        }
    }

    /**
     * Url safe for base64
     */
    private function base64UrlSafeDecode(string $string): string
    {
        $base64 = strtr($string, '-_', '+/');

        return base64_decode($base64);
    }
}
