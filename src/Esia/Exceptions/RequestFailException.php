<?php

namespace Esia\Exceptions;

class RequestFailException extends AbstractEsiaException
{
    public const CODE_TOKEN_IS_EMPTY = 500;

    private const MESSAGES = [
        self::CODE_TOKEN_IS_EMPTY => 'Token is empty. Please set the token before',
    ];

    protected function getMessageForCode(int $code): string
    {
        return self::MESSAGES[$code] ?? 'Unknown Error';
    }
}
