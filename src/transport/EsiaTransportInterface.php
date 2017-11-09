<?php
/**
 * Created by PhpStorm.
 * User: elfuvo
 * Date: 09.11.17
 * Time: 11:02
 */

namespace esia\transport;


use esia\exceptions\HttpException;

interface EsiaTransportInterface
{
    /**
     * @param $url
     * @param $params
     * @param array $headers
     *
     * @return string
     * @throws HttpException
     */
    public function post($url, $params, $headers = []);

    /**
     * @param $url
     * @param $params
     * @param array $headers
     *
     * @return string
     * @throws HttpException
     */
    public function get($url, $params, $headers = []);
}