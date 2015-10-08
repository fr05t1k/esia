<?php


namespace esia\exceptions;


use Exception;

class BaseException extends Exception
{
    protected static $codeLabels = [];

    public function __construct($code = 0, Exception $previous = null)
    {
        if (isset(static::$codeLabels[$code])) {
            $message = static::$codeLabels[$code];
        } else {
            $message = 'Unknown error';
        }

        parent::__construct($message, $code, $previous);
    }
}