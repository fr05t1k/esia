<?php
namespace esia;

use esia\exceptions\RequestFailException;
use esia\exceptions\SignFailException;

/**
 * Class OpenId
 * @package esia
 */
class OpenId
{
    public $clientId;
    public $redirectUrl;

    /**
     * @var callable|null
     */
    public $log = null;
    public $portalUrl = 'https://esia-portal1.test.gosuslugi.ru/';
    public $tokenUrl = 'aas/oauth2/te';
    public $codeUrl = 'aas/oauth2/ac';
    public $personUrl = 'rs/prns';
    public $privateKeyPath;
    public $privateKeyPassword;
    public $certPath;
    public $oid = null;

    protected $scope = 'fullname birthdate gender email mobile id_doc snils inn';

    protected $clientSecret = null;
    protected $responseType = 'code';
    protected $state = null;
    protected $timestamp = null;
    protected $accessType = 'offline';
    protected $tmpPath;

    private $url = null;
    public $token = null;

    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
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
        $this->clientSecret = $this->scope . $this->timestamp . $this->clientId . $this->state;
        $this->clientSecret = $this->signPKCS7($this->clientSecret);

        if ($this->clientSecret === false) {
            return false;
        }

        $url = $this->getCodeUrl() . '?%s';

        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUrl,
            'scope' => $this->scope,
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
     * Method collect a token with given code
     *
     * @param $code
     * @return false|string
     * @throws SignFailException
     */
    public function getToken($code)
    {
        $this->timestamp = $this->getTimeStamp();
        $this->state = $this->getState();

        $clientSecret = $this->signPKCS7($this->scope . $this->timestamp . $this->clientId . $this->state);

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
            'scope' => $this->scope,
            'timestamp' => $this->timestamp,
            'token_type' => 'Bearer',
            'refresh_token' => $this->state,
        ];

        $curl = curl_init();

        if ($curl === false) {
            return false;
        }

        $options = [
            CURLOPT_URL => $this->getTokenUrl(),
            CURLOPT_POSTFIELDS => http_build_query($request),
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
        ];

        curl_setopt_array($curl, $options);

        $result = curl_exec($curl);
        $result = json_decode($result);

        $this->writeLog(print_r($result, true));

        $this->token = $result->access_token;

        # get object id from token
        $chunks = explode('.', $this->token);
        $payload = json_decode($this->base64UrlSafeDecode($chunks[1]));
        $this->oid = $payload->{'urn:esia:sbj_id'};

        $this->writeLog(var_export($payload, true));

        return $this->token;
    }


    /**
     * Algorithm for singing message which
     * will be send in client_secret param
     *
     * @param string $message
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
     * @return null|\stdClass
     */
    public function getContactInfo()
    {
        $url = $this->personUrl . '/' . $this->oid . '/ctts';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && $result->size > 0) {
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
     * @return null|\stdClass
     */
    public function getAddressInfo()
    {
        $url = $this->personUrl . '/' . $this->oid . '/addrs';
        $request = $this->buildRequest();
        $result = $request->call($url);

        if ($result && $result->size > 0) {
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

        return new Request($this->portalUrl, $this->token);
    }

    /**
     * @throws SignFailException
     */
    protected function checkFilesExists()
    {
        if (! file_exists($this->certPath)) {
            throw new SignFailException(SignFailException::CODE_NO_SUCH_CERT_FILE);
        }
        if (! file_exists($this->privateKeyPath)) {
            throw new SignFailException(SignFailException::CODE_NO_SUCH_KEY_FILE);
        }
        if (! file_exists($this->tmpPath)) {
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
    private function writeLog($message)
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

