<?php

namespace esia;


class Request
{
    public $url;
    public $token;

    function __construct($url, $token)
    {
        $this->url = $url;
        $this->token = $token;
    }

    /**
     * @param string $method url
     * @param bool $withScheme if we need request with scheme
     * @return mixed
     */
    public function call($method, $withScheme = false)
    {

        $ch = $this->prepareAuthCurl();

        $url = $withScheme ? $method : $this->url . $method;
        curl_setopt($ch, CURLOPT_URL, $url);

        return json_decode(curl_exec($ch));
    }

    /**
     * @return resource
     */
    protected function prepareAuthCurl()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->token]);

        return $ch;
    }


}