<?php

namespace esia;

use esia\exceptions\RequestFailException;
use esia\transport\EsiaTransportInterface;


/**
 * Class Request
 *
 * @package esia
 */
class Request
{
    /**
     * Url for calling request
     *
     * @var string
     */
    public $url;

    /**
     * Token for "Authorization" header
     *
     * @var string
     */
    public $token;

    /**
     * @var EsiaTransportInterface
     */
    public $transport;

    /**
     * Request constructor.
     *
     * @param string $url
     * @param string $token
     * @param $transport
     */
    function __construct($url, $token, EsiaTransportInterface $transport)
    {
        $this->url = $url;
        $this->token = $token;
        $this->transport = $transport;
    }

    /**
     * Call given method and return json decoded response
     *
     * if $withScheme equals false:
     * ````
     *     $request->url = 'https://esia-portal1.test.gosuslugi.ru/';
     *     $response = $request->call('/aas/oauth2/te');
     * ````
     * It will call https://esia-portal1.test.gosuslugi.ru/aas/oauth2/te
     *
     * if $withScheme equals true:
     * ````
     *     $request->call(https://esia-portal1.test.gosuslugi.ru/aas/oauth2/te, true);
     * ````
     * * It will call also https://esia-portal1.test.gosuslugi.ru/aas/oauth2/te
     *
     * @param string $method url
     * @param bool $withScheme if we need request with scheme
     *
     * @return null|\stdClass
     * @throws RequestFailException
     */
    public function call($method, $withScheme = false)
    {

        $url = $withScheme ? $method : $this->url . $method;
        $result = $this->transport->get($url, [], ['Authorization: Bearer ' . $this->token]);
        if ($result) {
            $return = json_decode($result);
            if (json_last_error() === JSON_ERROR_NONE && $return !== null) {
                return $return;
            }
        }

        return null;
    }

}
