<?php

namespace Esia;

use Esia\Exceptions\RequestFailException;
use Esia\Exceptions\SignFailException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Class OpenId
 */
class OpenId
{
    use LoggerAwareTrait;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * Config
     *
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = new Client();
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
     * Return an url for request to get an access token
     */
    private function getTokenUrl(): string
    {
        return $this->config->getPortalUrl() . $this->config->getTokenUrl();
    }

    /**
     * Return an url for request to get an authorization code
     *
     * @return string
     */
    private function getCodeUrl(): string
    {
        return $this->config->getPortalUrl() . $this->config->getCodeUrl();
    }

    /**
     * Method collect a token with given code
     *
     * @param $code
     * @return false|string
     * @throws SignFailException
     * @throws RequestFailException
     */
    public function getToken($code)
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

        try {
            $response = $this->client->post(
                $this->getTokenUrl(),
                ['form_params' => $body]
            );
        } catch (ClientException $e) {
            $this->logger->debug($e->getResponse()->getBody()->getContents());
            throw new RequestFailException(RequestFailException::CODE_REQUEST_FAILED, $e);
        }

        $responseBody = $response->getBody()->getContents();
        $this->logger->debug('Response: ' . $responseBody);
        $payload = json_decode($responseBody, true);

        $this->logger->debug('Payload: ', $payload);

        $this->config->setToken($payload['access_token']);

        # get object id from token
        $chunks = explode('.', $this->config->getToken());
        $payload = json_decode($this->base64UrlSafeDecode($chunks[1]), true);
        $this->config->setOid($payload['urn:esia:sbj_id']);

        return $this->config->getToken();
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
    public function getPersonInfo()
    {
        $url = $this->config->getPersonUrl() . '/' . $this->config->getOid();

        $request = $this->buildRequest();
        return $request->call($url);
    }

    /**
     * Fetch contact info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws \Exception
     * @return null|\stdClass
     */
    public function getContactInfo()
    {
        $url = $this->config->getPersonUrl() . '/' . $this->config->getOid() . '/ctts';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && $result['size'] > 0) {
            return $this->collectArrayElements($result['elements']);
        }

        return $result;
    }


    /**
     * Fetch address from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws \Exception
     * @return null|array
     */
    public function getAddressInfo()
    {
        $url = $this->config->getPersonUrl() . '/' . $this->config->getOid() . '/addrs';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && $result['size'] > 0) {
            return $this->collectArrayElements($result['elements']);
        }

        return null;
    }

    /**
     * Fetch documents info about current person
     *
     * You must collect token person before
     * calling this method
     *
     * @throws \Exception
     * @return null|array
     */
    public function getDocInfo(): ?array
    {
        $url = $this->config->getPersonUrl() . '/' . $this->config->getOid() . '/docs';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && $result['size'] > 0) {
            return $this->collectArrayElements($result['elements']);
        }

        return $result;
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
    protected function collectArrayElements($elements): array
    {
        $result = [];
        foreach ($elements as $element) {
            $request = $this->buildRequest();
            $source = $request->call($element, true);

            if ($source) {
                $result[] = $source;
            }

        }

        return $result;
    }

    /**
     * @return Request
     * @throws RequestFailException
     */
    public function buildRequest(): Request
    {
        if (!$this->config->getToken()) {
            throw new RequestFailException(RequestFailException::CODE_TOKEN_IS_EMPTY);
        }


        return new Request($this->config->getPortalUrl(), $this->config->getToken());
    }

    /**
     * @throws SignFailException
     */
    protected function checkFilesExists(): void
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
