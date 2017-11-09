<?php

namespace esia\exceptions;

use Exception;

/**
 * Class BaseException
 *
 * @package esia\exceptions
 */
class BaseException extends Exception
{
    /**
     * @var array
     */
    protected static $codeLabels = [];

    /**
     * BaseException constructor.
     *
     * @param int $code
     * @param Exception|null $previous
     */
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