<?php

namespace esia\exceptions;

class SignFailException extends BaseException
{
    const CODE_CANT_READ_CERT = 500;
    const CODE_CANT_READ_PRIVATE_KEY = 501;
    const CODE_SIGN_FAIL = 502;

    protected static $codeLabels = [
        self::CODE_CANT_READ_CERT => 'Can\'t read a certificate',
        self::CODE_CANT_READ_PRIVATE_KEY => 'Can\'t read a private key',
        self::CODE_SIGN_FAIL => 'Sign fail',
    ];


}