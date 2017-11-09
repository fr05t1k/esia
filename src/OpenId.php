<?php

namespace esia;

use esia\exceptions\RequestFailException;
use esia\exceptions\SignFailException;
use esia\transport\EsiaTransportInterface;

/**
 * Class OpenId
 *
 * @package esia
 */
class OpenId
{
    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $redirectUrl;

    /**
     * @var callable|null
     */
    public $log = null;
    public $portalUrl = 'https://esia-portal1.test.gosuslugi.ru/';
    public $tokenUrl = 'aas/oauth2/te';
    public $codeUrl = 'aas/oauth2/ac';
    public $personUrl = 'rs/prns';
    public $organizationUrl = 'rs/orgs';
    public $privateKeyPath;
    public $privateKeyPassword;
    public $certPath;
    public $oid = null;

    /**
     * @var EsiaTransportInterface
     */
    protected $transport;

    protected $personScope = 'http://esia.gosuslugi.ru/usr_inf';
    protected $organizationScope = 'http://esia.gosuslugi.ru/org_inf';

    protected $clientSecret = null;
    protected $responseType = 'code';
    protected $state = null;
    protected $timestamp = null;
    protected $accessType = 'offline';
    protected $tmpPath;

    /**
     * @var string
     */
    private $url = null;

    /**
     * @var string
     */
    public $token = null;

    /**
     * @var array
     */
    public $fullTokenData = [];

    /**
     * OpenId constructor.
     *
     * @param array $config
     * @param EsiaTransportInterface $transport
     */
    public function __construct(array $config = [], EsiaTransportInterface $transport)
    {
        foreach ($config as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
        $this->transport = $transport;
    }

    /**
     * Return an url for authentication
     *
     * ```
     *     <a href="<?=$esia->getUrl()?>">Login</a>
     * ```
     *
     * @return string|false
     */
    public function getUrl()
    {
        $this->timestamp = $this->getTimeStamp();
        $this->state = $this->getState();
        $this->clientSecret = $this->personScope . $this->timestamp . $this->clientId . $this->state;
        $this->clientSecret = $this->signPKCS7($this->clientSecret);

        if ($this->clientSecret === false) {
            return false;
        }

        $url = $this->getCodeUrl() . '?%s';

        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->personScope,
            'response_type' => $this->responseType,
            'state' => $this->state,
            'access_type' => $this->accessType,
            'timestamp' => $this->timestamp,
        ];

        $request = http_build_query($params);

        $this->url = sprintf($url, $request);

        return $this->url;
    }

    /**
     * @param $url
     */
    public function setRedirectUrl($url)
    {
        $this->redirectUrl = $url;
    }

    /**
     * @return bool|string
     */
    public function getGeneratedState()
    {
        return $this->state ? $this->state : false;
    }

    /**
     * Return an url for request to get an access token
     *
     * @return string
     */
    public function getTokenUrl()
    {
        return $this->portalUrl . $this->tokenUrl;
    }

    /**
     * Return an url for request to get an authorization code
     *
     * @return string
     */
    public function getCodeUrl()
    {
        return $this->portalUrl . $this->codeUrl;
    }

    /**
     * Return an url for request person information
     *
     * @return string
     */
    public function getPersonUrl()
    {
        return $this->portalUrl . $this->personUrl;
    }

    /**
     * Return an url for request person information
     *
     * @return string
     */
    public function getOrganisationUrl()
    {
        return $this->portalUrl . $this->organizationUrl;
    }

    /**
     * Method collect a token with given code
     *
     * @param $code
     *
     * @return false|string
     * @throws SignFailException
     */
    public function getToken($code)
    {
        $this->timestamp = $this->getTimeStamp();
        $this->state = $this->getState();

        $clientSecret = $this->signPKCS7($this->personScope . $this->timestamp . $this->clientId . $this->state);

        if ($clientSecret === false) {
            throw new SignFailException(SignFailException::CODE_SIGN_FAIL);
        }

        $request = [
            'client_id' => $this->clientId,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_secret' => $clientSecret,
            'state' => $this->state,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->personScope,
            'timestamp' => $this->timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $this->state,
        ];

        $resultTxt = $this->transport->post($this->getTokenUrl(), $request);

        $result = json_decode($resultTxt);
        if ($result) {
            $this->writeLog(print_r($result, true));

            $this->token = $result->access_token;

            # get object id from token
            $chunks = explode('.', $this->token);
            $payload = json_decode($this->base64UrlSafeDecode($chunks[1]));
            $this->oid = $payload->{'urn:esia:sbj_id'};

            $this->fullTokenData = (array)$result;
            $this->fullTokenData['expires'] = time() + $this->fullTokenData['expires_in'] - 1;
            $this->fullTokenData['oid'] = $this->oid;

            $this->writeLog(var_export($payload, true));

            return $this->token;
        } else {
            $this->writeLog('URL: ' . print_r($this->getTokenUrl(), true));
            $this->writeLog('POST: ' . print_r($request, true));
            $this->writeLog(print_r($resultTxt, true));
        }

        return false;
    }

    /**
     * token data must be set after getting token
     *
     * @return array
     */
    public function getFullTokenData()
    {
        return $this->fullTokenData;
    }

    /**
     * Method collect a token with given code
     *
     * @param $orgOid
     *
     * @return false|string
     * @throws SignFailException
     */
    public function getOrgToken($orgOid)
    {
        $this->timestamp = $this->getTimeStamp();
        $this->state = $this->getState();

        $scope = $this->organizationScope . '?org_oid=' . $orgOid;

        $clientSecret = $this->signPKCS7($scope . $this->timestamp . $this->clientId . $this->state);

        if ($clientSecret === false) {
            throw new SignFailException(SignFailException::CODE_SIGN_FAIL);
        }

        $request = [
            'client_id' => $this->clientId,
            'response_type' => 'token',
            'grant_type' => 'client_credentials',
            'scope' => $scope,
            'state' => $this->state,
            'timestamp' => $this->timestamp,
            'token_type' => 'Bearer',
            'client_secret' => $clientSecret,
        ];

        $result = $this->transport->post($this->getCodeUrl(), $request);
        $this->writeLog(print_r($request, true));

        $result = json_decode($result);
        if ($result) {
            $this->writeLog(print_r($result, true));

            $this->token = $result->access_token;

            # get object id from token
            $chunks = explode('.', $this->token);
            $payload = json_decode($this->base64UrlSafeDecode($chunks[1]));
            $this->oid = $payload->{'urn:esia:sbj_id'};

            $this->writeLog(var_export($payload, true));

            return $this->token;
        }

        return false;
    }

    /**
     * @param $refresh_token
     *
     * @return bool|null
     * @throws SignFailException
     */
    public function refreshToken($refresh_token)
    {
        $this->timestamp = $this->getTimeStamp();
        $this->state = $this->getState();

        $clientSecret = $this->signPKCS7($this->personScope . $this->timestamp . $this->clientId . $this->state);

        if ($clientSecret === false) {
            throw new SignFailException(SignFailException::CODE_SIGN_FAIL);
        }

        $request = [
            'client_id' => $this->clientId,
            'grant_type' => 'refresh_token',
            'client_secret' => $clientSecret,
            'state' => $this->state,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->personScope,
            'timestamp' => $this->timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $refresh_token,
        ];

        $resultTxt = $this->transport->post($this->getTokenUrl(), $request);

        // TODO: ensure that response is json encoded string
        $result = json_decode($resultTxt);
        if ($result) {
            $this->writeLog(print_r($result, true));

            $this->token = $result->access_token;

            # get object id from token
            $chunks = explode('.', $this->token);
            $payload = json_decode($this->base64UrlSafeDecode($chunks[1]));
            $this->oid = $payload->{'urn:esia:sbj_id'};

            $this->fullTokenData = (array)$result;
            $this->fullTokenData['expires'] = time() + $this->fullTokenData['expires_in'] - 1;
            $this->fullTokenData['oid'] = $this->oid;

            $this->writeLog(var_export($payload, true));

            return $this->token;
        } else {
            $this->writeLog('URL: ' . print_r($this->getTokenUrl(), true));
            $this->writeLog('POST: ' . print_r($request, true));
            $this->writeLog(print_r($resultTxt, true));
        }

        return false;
    }

    /**
     * @param $scope
     */
    public function setScope($scope)
    {
        $this->personScope = $scope;
    }

    /**
     * @param $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param $oid
     *
     * @return mixed
     */
    public function setOid($oid)
    {
        return $this->oid = $oid;
    }

    /**
     * @return null|string
     */
    public function getOid()
    {
        return $this->oid;
    }

    /**
     * Algorithm for singing message which
     * will be send in client_secret param
     *
     * @param string $message
     *
     * @return string
     * @throws SignFailException
     */
    public function signPKCS7($message)
    {
        $this->checkFilesExists();

        $certContent = file_get_contents($this->certPath);
        $keyContent = file_get_contents($this->privateKeyPath);

        $cert = openssl_x509_read($certContent);

        if ($cert === false) {
            throw new SignFailException(SignFailException::CODE_CANT_READ_CERT);
        }

        $this->writeLog('Cert: ' . print_r($cert, true));

        $privateKey = openssl_pkey_get_private($keyContent, $this->privateKeyPassword);

        if ($privateKey === false) {
            throw new SignFailException(SignFailException::CODE_CANT_READ_PRIVATE_KEY);
        }

        $this->writeLog('Private key: : ' . print_r($privateKey, true));

        // random unique directories for sign
        $messageFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        $signFile = $this->tmpPath . DIRECTORY_SEPARATOR . $this->getRandomString();
        file_put_contents($messageFile, $message);

        $signResult = openssl_pkcs7_sign(
            $messageFile,
            $signFile,
            $cert,
            $privateKey,
            []
        );

        if ($signResult) {
            $this->writeLog('Sign success');
        } else {
            $this->writeLog('Sign fail');
            $this->writeLog('SSH error: ' . openssl_error_string());
            throw new SignFailException(SignFailException::CODE_SIGN_FAIL);
        }

        $signed = file_get_contents($signFile);

        # split by section
        $signed = explode("\n\n", $signed);

        # get third section which contains sign and join into one line
        $sign = str_replace("\n", "", $this->urlSafe($signed[3]));

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
     * @return null|\stdClass
     */
    public function getPersonInfo()
    {
        $url = $this->personUrl . '/' . $this->oid;

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
     * @return null|\stdClass|array
     */
    public function getContactInfo()
    {
        $url = $this->personUrl . '/' . $this->oid . '/ctts';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && is_object($result) && $result->size > 0) {
            return $this->collectArrayElements($result->elements);
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
     * @return null|\stdClass|array
     */
    public function getAddressInfo()
    {
        $url = $this->personUrl . '/' . $this->oid . '/addrs';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && is_object($result) && $result->size > 0) {
            return $this->collectArrayElements($result->elements);
        }

        return null;
    }

    /**
     * @return null|\stdClass[]
     */
    public function getOrgRoles()
    {
        $url = $this->personUrl . '/' . $this->oid . '/roles';

        $request = $this->buildRequest();
        $result = $request->call($url);
        $this->writeLog(print_r($result, true));

        if ($result && is_object($result) && $result->size > 0) {
            return $result->elements;
        }

        return null;
    }

    /**
     * @param $orgId
     *
     * @return \stdClass
     */
    public function getOrgInfo($orgId)
    {
        $url = $this->organizationUrl . '/' . $orgId;

        $request = $this->buildRequest();
        $result = $request->call($url);
        $this->writeLog(print_r($result, true));

        if ($result && is_object($result) && $result->oid > 0) {
            return $result;
        }

        return null;
    }

    /**
     * @param $orgOid
     *
     * @return array|null
     */
    public function getOrgEmployers($orgOid)
    {

        $url = $this->organizationUrl . '/' . $orgOid . '/emps';

        $request = $this->buildRequest();
        $result = $request->call($url);
        $this->writeLog(print_r($result, true));

        if ($result && is_object($result) &&
            property_exists($result, 'elements') &&
            count($result->elements) > 0
        ) {
            return $this->collectArrayElements($result->elements);
        }

        return null;
    }

    /**
     * Fetch organizations from current person
     *
     * You must collect token person before
     * calling this method
     *
     * @param string $orgOid
     *
     * @throws \Exception
     * @return null|array
     */
    public function getOrgAddress($orgOid)
    {

        $url = $this->organizationUrl . '/' . $orgOid . '/addrs';

        $request = $this->buildRequest();
        $result = $request->call($url);
        $this->writeLog(print_r($result, true));

        if ($result && is_object($result) && $result->size > 0) {
            return $this->collectArrayElements($result->elements);
        }

        return null;
    }

    /**
     * Fetch organization contacts
     *
     * @param $orgOid
     *
     * @return array|null|\stdClass
     */
    public function getOrgContacts($orgOid)
    {
        $url = $this->organizationUrl . '/' . $orgOid . '/ctts';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && is_object($result) &&
            property_exists($result, 'elements') &&
            count($result->elements) > 0) {
            return $this->collectArrayElements($result->elements);
        }

        return null;
    }

    /**
     * This method can iterate on each element
     * and fetch entities from esia by url
     *
     *
     * @param $elements array of urls
     *
     * @return array
     * @throws \Exception
     */
    protected function collectArrayElements($elements)
    {
        $result = [];
        foreach ($elements as $element) {

            $request = $this->buildRequest();
            $source = $request->call($element, true);

            if ($source) {
                array_push($result, $source);
            }
            $this->writeLog(print_r($result, true));

        }

        return $result;
    }

    /**
     * @return Request
     * @throws RequestFailException
     */
    public function buildRequest()
    {
        if (!$this->token) {
            throw new RequestFailException(RequestFailException::CODE_TOKEN_IS_EMPTY);
        }

        return new Request($this->portalUrl, $this->token, $this->transport);
    }

    /**
     * @throws SignFailException
     */
    protected function checkFilesExists()
    {
        if (!file_exists($this->certPath)) {
            throw new SignFailException(SignFailException::CODE_NO_SUCH_CERT_FILE);
        }
        if (!file_exists($this->privateKeyPath)) {
            throw new SignFailException(SignFailException::CODE_NO_SUCH_KEY_FILE);
        }
        if (!file_exists($this->tmpPath)) {
            throw new SignFailException(SignFailException::CODE_NO_TEMP_DIRECTORY);
        }
    }

    /**
     * @return string
     */
    private function getTimeStamp()
    {
        return date("Y.m.d H:i:s O");
    }


    /**
     * Generate state with uuid
     *
     * @return string
     */
    private function getState()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Url safe for base64
     *
     * @param string $string
     *
     * @return string
     */
    private function urlSafe($string)
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }


    /**
     * Url safe for base64
     *
     * @param string $string
     *
     * @return string
     */
    private function base64UrlSafeDecode($string)
    {
        $base64 = strtr($string, '-_', '+/');

        return base64_decode($base64);
    }

    /**
     * Write log
     *
     * @param string $message
     */
    public function writeLog($message)
    {
        $log = $this->log;

        if (is_callable($log)) {
            $log($message);
        }
    }

    /**
     * Generate random unique string
     *
     * @return string
     */
    private function getRandomString()
    {
        return md5(uniqid(mt_rand(), true));
    }
}

