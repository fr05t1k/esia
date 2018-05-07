<?php

namespace Esia;

use Esia\Exceptions\AbstractEsiaException;
use Esia\Exceptions\ForbiddenException;
use Esia\Exceptions\RequestFailException;
use Esia\Exceptions\SignFailException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Class OpenId
 */
class OpenId
{
    use LoggerAwareTrait;

    /**
     * Http Client
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * Config
     *
     * @var Config
     */
    private $config;

    public function __construct(Config $config, ClientInterface $client = null)
    {
        $this->config = $config;
        $this->client = $client ?? new GuzzleHttpClient(new Client());
        $this->logger = new NullLogger();
    }

    /**
     * Return an url for authentication
     *
     * ```php
     *     <a href="<?=$esia->buildUrl()?>">Login</a>
     * ```
     *
     * @return string|false
     * @throws SignFailException
     */
    public function buildUrl()
    {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();
        $message = $this->config->getScope()
            . $timestamp
            . $this->config->getClientId()
            . $state;

        $clientSecret = $this->signPKCS7($message);

        if ($clientSecret === false) {
            return false;
        }

        $url = $this->getCodeUrl() . '?%s';

        $params = [
            'client_id' => $this->config->getClientId(),
            'client_secret' => $clientSecret,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScope(),
            'response_type' => $this->config->getResponseType(),
            'state' => $state,
            'access_type' => $this->config->getAccessType(),
            'timestamp' => $timestamp,
        ];

        $request = http_build_query($params);

        return sprintf($url, $request);
    }

    /**
     * Method collect a token with given code
     *
     * @param string $code
     * @return string
     * @throws RequestFailException
     * @throws SignFailException
     * @throws AbstractEsiaException
     */
    public function getToken(string $code): string
    {
        $timestamp = $this->getTimeStamp();
        $state = $this->buildState();

        $clientSecret = $this->signPKCS7(
            $this->config->getScope()
            . $timestamp
            . $this->config->getClientId()
            . $state
        );

        if ($clientSecret === false) {
            throw new SignFailException(SignFailException::CODE_SIGN_FAIL);
        }

        $body = [
            'client_id' => $this->config->getClientId(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_secret' => $clientSecret,
            'state' => $state,
            'redirect_uri' => $this->config->getRedirectUrl(),
            'scope' => $this->config->getScope(),
            'timestamp' => $timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $state,
        ];

        $payload = $this->sendRequest(
            new Request(
                'POST',
                $this->getTokenUrl(),
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                http_build_query($body)
            )
        );

        $this->logger->debug('Payload: ', $payload);

        $token = $payload['access_token'];
        $this->config->setToken($token);

        # get object id from token
        $chunks = explode('.', $token);
        $payload = json_decode($this->base64UrlSafeDecode($chunks[1]), true);
        $this->config->setOid($payload['urn:esia:sbj_id']);

        return $token;
    }


    /**
     * Algorithm for singing message which
     * will be send in client_secret param
     *
     * @param string $message
     * @return string
     * @throws SignFailException
     */
    public function signPKCS7($message): string
    {
        $this->checkFilesExists();

        $certContent = file_get_contents($this->config->getCertPath());
        $keyContent = file_get_contents($this->config->getPrivateKeyPath());

        $cert = openssl_x509_read($certContent);

        if ($cert === false) {
            throw new SignFailException(SignFailException::CODE_CANT_READ_CERT);
        }

        $this->logger->debug('Cert: ' . print_r($cert, true), ['cert' => $cert]);

        $privateKey = openssl_pkey_get_private($keyContent, $this->config->getPrivateKeyPassword());

        if ($privateKey === false) {
            throw new SignFailException(SignFailException::CODE_CANT_READ_PRIVATE_KEY);
        }

        $this->logger->debug('Private key: : ' . print_r($privateKey, true), ['privateKey' => $privateKey]);

        // random unique directories for sign
        $messageFile = $this->config->getTmpPath() . DIRECTORY_SEPARATOR . $this->getRandomString();
        $signFile = $this->config->getTmpPath() . DIRECTORY_SEPARATOR . $this->getRandomString();
        file_put_contents($messageFile, $message);

        $signResult = openssl_pkcs7_sign(
            $messageFile,
            $signFile,
            $cert,
            $privateKey,
            []
        );

        if ($signResult) {
            $this->logger->debug('Sign success');
        } else {
            $this->logger->error('Sign fail');
            $this->logger->error('SSL error: ' . openssl_error_string());
            throw new SignFailException(SignFailException::CODE_SIGN_FAIL);
        }

        $signed = file_get_contents($signFile);

        # split by section
        $signed = explode("\n\n", $signed);

        # get third section which contains sign and join into one line
        $sign = str_replace("\n", '', $this->urlSafe($signed[3]));

        unlink($signFile);
        unlink($messageFile);

        return $sign;
    }

    /**
     * Fetch person info from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws \Exception
     * @return null|array
     */
    public function getPersonInfo(): array
    {
        $url = $this->config->getPortalUrl() . $this->config->getPersonUrl() . '/' . $this->config->getOid();

        return $this->sendRequest(new Request('GET', $url));
    }

    /**
     * Fetch contact info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws \Exception
     * @return array
     */
    public function getContactInfo(): array
    {
        $url = $this->config->getPortalUrl() . $this->config->getPersonUrl() . '/' . $this->config->getOid() . '/ctts';
        $payload = $this->sendRequest(new Request('GET', $url));

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
     * @throws \Exception
     * @return array
     */
    public function getAddressInfo(): array
    {
        $url = $this->config->getPortalUrl() . $this->config->getPersonUrl() . '/' . $this->config->getOid() . '/addrs';
        $payload = $this->sendRequest(new Request('GET', $url));

        if ($payload['size'] > 0) {
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
     * @throws \Exception
     * @return array
     */
    public function getDocInfo(): array
    {
        $url = $this->config->getPortalUrl() . $this->config->getPersonUrl() . '/' . $this->config->getOid() . '/docs';

        $payload = $this->sendRequest(new Request('GET', $url));

        if ($payload && $payload['size'] > 0) {
            return $this->collectArrayElements($payload['elements']);
        }

        return $payload;
    }

    /**
     * This method can iterate on each element
     * and fetch entities from esia by url
     *
     *
     * @param $elements array of urls
     * @return array
     * @throws \Exception
     */
    private function collectArrayElements($elements): array
    {
        $result = [];
        foreach ($elements as $elementUrl) {
            $elementPayload = $this->sendRequest(new Request('GET', $elementUrl));

            if ($elementPayload) {
                $result[] = $elementPayload;
            }

        }

        return $result;
    }

    /**
     * @throws SignFailException
     */
    private function checkFilesExists(): void
    {
        if (!file_exists($this->config->getCertPath())) {
            throw new SignFailException(SignFailException::CODE_NO_SUCH_CERT_FILE);
        }
        if (!file_exists($this->config->getPrivateKeyPath())) {
            throw new SignFailException(SignFailException::CODE_NO_SUCH_KEY_FILE);
        }
        if (!file_exists($this->config->getTmpPath())) {
            throw new SignFailException(SignFailException::CODE_NO_TEMP_DIRECTORY);
        }
    }

    /**
     * @param RequestInterface $request
     * @return array
     * @throws AbstractEsiaException
     */
    private function sendRequest(RequestInterface $request): array
    {
        try {
            if ($this->config->getToken()) {
                $request = $request->withHeader('Authorization', 'Bearer ' . $this->config->getToken());
            }
            $response = $this->client->sendRequest($request);
            $responseBody = json_decode($response->getBody()->getContents(), true);

            if (!is_array($responseBody)) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot decode response body. JSON error (%d): %s',
                        json_last_error(),
                        json_last_error_msg()
                    )
                );
            }

            return $responseBody;
        } catch (ClientException $e) {
            $this->logger->error('Request was failed', ['exception' => $e]);
            if ($e->getResponse() !== null && $e->getResponse()->getStatusCode() === 403) {
                throw new ForbiddenException(0, $e);
            }

            throw new RequestFailException(RequestFailException::CODE_REQUEST_FAILED, $e);
        } catch (GuzzleException $e) {
            $this->logger->error('Request was failed', ['exception' => $e]);
            throw new RequestFailException(RequestFailException::CODE_REQUEST_FAILED, $e);
        } catch (\RuntimeException $e) {
            $this->logger->error('Cannot read body', ['exception' => $e]);
            throw new RequestFailException(RequestFailException::CODE_REQUEST_FAILED, $e);
        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Wrong header', ['exception' => $e]);
            throw new RequestFailException(RequestFailException::CODE_REQUEST_FAILED, $e);
        }
    }

    /**
     * Return an url for request to get an access token
     */
    private function getTokenUrl(): string
    {
        return $this->config->getPortalUrl() . $this->config->getTokenUrl();
    }

    /**
     * Return an url for request to get an authorization code
     */
    private function getCodeUrl(): string
    {
        return $this->config->getPortalUrl() . $this->config->getCodeUrl();
    }

    /**
     * @return string
     */
    private function getTimeStamp(): string
    {
        return date('Y.m.d H:i:s O');
    }


    /**
     * Generate state with uuid
     *
     * @return string
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
        } catch (\Exception $e) {
            throw new SignFailException(SignFailException::CODE_CANNOT_GENERATE_RANDOM_INT);
        }
    }

    /**
     * Url safe for base64
     *
     * @param string $string
     * @return string
     */
    private function urlSafe($string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }


    /**
     * Url safe for base64
     *
     * @param string $string
     * @return string
     */
    private function base64UrlSafeDecode($string): string
    {
        $base64 = strtr($string, '-_', '+/');

        return base64_decode($base64);
    }

    /**
     * Generate random unique string
     *
     * @return string
     */
    private function getRandomString(): string
    {
        return md5(uniqid(mt_rand(), true));
    }
}
