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

class Curl implements EsiaTransportInterface
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

        if(!is_resource($ch)) {
            return null;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ];
        curl_setopt_array($ch, $options);

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
        $ch = curl_init();
        if(!is_resource($ch)) {
            return null;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers
        ];

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new HttpException(curl_errno($ch), new Exception(curl_error($ch)));
        }


        return $result;
    }
}