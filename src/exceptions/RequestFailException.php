<?php

namespace esia\exceptions;

/**
 * Class RequestFailException
 *
 * @package esia\exceptions
 */
class RequestFailException extends BaseException
{
    const CODE_TOKEN_IS_EMPTY = 500;
    const CODE_RESPONSE_NOT_JSON = 400;

    /**
     * @var array
     */
    protected static $codeLabels = [
        self::CODE_TOKEN_IS_EMPTY => 'Token is empty. Please set the token before',
        self::CODE_RESPONSE_NOT_JSON => 'Response answer is not JSON string',
    ];


}