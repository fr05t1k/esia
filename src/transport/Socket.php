<?php
/**
 * Created by PhpStorm.
 * User: eLFuvo
 * Date: 11.10.2016
 * Time: 12:04
 */

namespace common\components\esia\transport;

use Exception;
use esia\exceptions\HttpException;

class Socket
{
    /**
     * @var array
     */
    protected $headers = [
        'Accept: */*',
        //'Content-type' => 'application/x-www-form-urlencoded'
    ];

    /**
     * @param $url
     * @param $params
     * @param array $headers
     *
     * @return string
     * @throws HttpException
     */
    public function get($url, $params, $headers = [])
    {
        $url = parse_url($url);
        if ($url['scheme'] == 'https') {
            $url['port'] = array_key_exists('port', $url) ? $url['port'] : 443;
        } else {
            $url['port'] = array_key_exists('port', $url) ? $url['port'] : 80;
        }
        $content = http_build_query($params);

        $headers = array_merge([
            'GET ' . $url['path'] . ($params ? "?" . $content : "") . ' HTTP/1.0',
            'Content-Type: application/json',
            'Host: ' . $url['host'],
            //'Content-Length: ' . strlen($content),
            'Connection: close',
        ], $headers, $this->headers);

        $fp = fsockopen(($url['scheme'] == 'https' ? "ssl://" : "") . $url['host'], $url['port'], $errno, $error_str,
            60);
        if ($errno > 0) {
            throw new HttpException($errno, new Exception($error_str));
        }
        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n");

        $return = "";
        while (!feof($fp)) {
            $return .= fgets($fp, 4096);
        }
        fclose($fp);

        $return = explode("\r\n\r\n", $return);
        $out = end($return);

        return $out;
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
        $url = parse_url($url);
        if ($url['scheme'] == 'https') {
            $url['port'] = array_key_exists('port', $url) ? $url['port'] : 443;
        } else {
            $url['port'] = array_key_exists('port', $url) ? $url['port'] : 80;
        }

        $content = http_build_query($params);

        $headers = array_merge([
            'POST ' . $url['path'] . ' HTTP/1.0',
            'Host: ' . $url['host'],
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($content),
            'Connection: close',
            //  'Expect: ',
        ], $headers, $this->headers);

        $fp = fsockopen(($url['scheme'] == 'https' ? "ssl://" : "") . $url['host'], $url['port'], $errno, $error_str,
            30);
        if ($errno > 0) {
            throw new HttpException($errno, new Exception($error_str));
        }

        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n");
        fwrite($fp, $content . "\r\n\r\n");

        $return = "";
        while (!feof($fp)) {
            $return .= fgets($fp, 4096);
        }
        fclose($fp);

        $return = explode("\r\n\r\n", $return);
        $out = end($return);

        return $out;
    }
}