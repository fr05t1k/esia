<?php

namespace Esia\Exceptions;

class RequestFailException extends AbstractEsiaException
{
    public const CODE_TOKEN_IS_EMPTY = 500;
    public const CODE_REQUEST_FAILED = 501;

    private const MESSAGES = [
        self::CODE_TOKEN_IS_EMPTY => 'Token is empty. Please set the token before',
        self::CODE_REQUEST_FAILED => 'Request failed. See previous exception'
    ];

    protected function getMessageForCode(int $code): string
    {
        return self::MESSAGES[$code] ?? 'Unknown Error';
    }
}
