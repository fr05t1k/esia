<?php
/**
 * Created by PhpStorm.
 * User: eLFuvo
 * Date: 11.10.2016
 * Time: 12:04
 */

namespace esia\transport;

use Exception;
use esia\exceptions\HttpException;

class Curl
{
    /**
     * @var array
     */
    protected $headers = [
        'Accept: */*',
        //'Content-type: application/x-www-form-urlencoded'
    ];

    /**
     * @param $url
     * @param $params
     * @param array $headers
     *
     * @return mixed
     * @throws HttpException
     */
    public function get($url, $params, $headers = [])
    {
        $url = $url . (empty($params) ? '' : (strstr($url, '?') ? '&' : '?') . http_build_query($params));

        $headers = array_merge([
            'Content-Type: text/plain',
            'Connection: close',
        ], $headers, $this->headers);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new HttpException(curl_errno($ch), new Exception(curl_error($ch)));
        }

        return $result;
    }

    /**
     * @param $url
     * @param $params
     * @param array $headers
     *
     * @return mixed
     * @throws HttpException
     */
    public function post($url, $params, $headers = [])
    {
        // TODO: make tests
        $urlparts = parse_url($url);
        $headers = array_merge([
            'POST ' . $urlparts['path'] . " HTTP/1.0",
            'Content-Type: application/x-www-form-urlencoded',
            'Content-length: ' . strlen(http_build_query($params)),
            'Connection: close'
        ], $headers, $this->headers);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new HttpException(curl_errno($ch), new Exception(curl_error($ch)));
        }


        return $result;
    }
}