<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;
use Psr\Log\LoggerAwareTrait;
use Esia\Http\GuzzleHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Exception;

class SignerCryptoProDSS extends AbstractSignerPKCS7 implements SignerInterface
{
    use LoggerAwareTrait;

    /**
     * @param string $message
     * @return string
     * @throws SignFailException
     */
    public function sign(string $message): string
    {
        if (!isset($this->additionalData) || !$this->additionalData) {
            throw new SignFailException('Empty Additional Data');
        }
        if (!isset($this->additionalData['oauthPath']) || !$this->additionalData['oauthPath']) {
            throw new SignFailException('Empty Oauth Path');
        }
        if (!isset($this->additionalData['oauthData']) || !$this->additionalData['oauthData']) {
            throw new SignFailException('Empty Oauth Data');
        }
        try {
            $sign = $this->signRequest($message);
        } catch (Exception $ex) {
            $this->logger->error('Sign fail');
            $this->logger->error('Sign error: ' . $ex->getMessage());
            throw new SignFailException($ex->getMessage());
        }
        $this->logger->debug('Sign success');
        return $sign;
    }
    
    /**
     * @param string $message
     * @return string
     * @throws Exception
     */
    public function signRequest($message) {
        $token = $this->getOauthToken();
        if (!$token) {
            throw new Exception('Token not found');
        }
        $client = new GuzzleHttpClient(new Client([
            'verify' => false
        ]));
        $body = [
            'Content' => base64_encode($message),
            'Signature' => [
                'Type' =>'CAdES',
                'Parameters' => [
                    'Hash' => 'False',
                    'CADESType' => 'BES',
                    'IsDetached' => 'True',
                ],
                'CertificateId' => $this->privateKeyPath,
                'PinCode' => $this->privateKeyPassword,
            ]
        ];
        $request = new Request(
            'POST',
            $this->certPath,
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                
            ],
            json_encode($body)
        );
        $response = $client->sendRequest($request);
        if (!$response) {
            throw new Exception('Empty response');
        }
        $json = json_decode($response->getBody()->getContents(), true);
        if (isset($json['Message'])) {
            throw new Exception($json['Message']);
        }
        if (!is_string($json)) {
            throw new Exception('Wrong response');
        }
        return $json;
    }
    
    /**
     * @return string|boolean
     */
    public function getOauthToken(){
        $client = new GuzzleHttpClient(new Client([
            'verify' => false
        ]));
        $request = new Request(
            'POST',
            $this->additionalData['oauthPath'],
            [],
            http_build_query($this->additionalData['oauthData'])
        );
        $response = $client->sendRequest($request);
        if (!$response) {
            return false;
        }
        try {
            $json = json_decode($response->getBody()->getContents(), true);
            if (isset($json['access_token'])) {
                return $json['access_token'];
            }
        } catch (Exception $ex) {
        }
        return false;
    }
}
