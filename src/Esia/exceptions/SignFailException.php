<?php

namespace Esia\exceptions;

class SignFailException extends BaseException
{
    const CODE_CANT_READ_CERT = 500;
    const CODE_CANT_READ_PRIVATE_KEY = 501;
    const CODE_SIGN_FAIL = 502;
    const CODE_NO_SUCH_CERT_FILE = 504;
    const CODE_NO_SUCH_KEY_FILE = 505;
    const CODE_NO_TEMP_DIRECTORY = 506;

    protected static $codeLabels = [
        self::CODE_CANT_READ_CERT => 'Can\'t read a certificate',
        self::CODE_CANT_READ_PRIVATE_KEY => 'Can\'t read a private key',
        self::CODE_SIGN_FAIL => 'Sign fail',
        self::CODE_NO_SUCH_CERT_FILE => 'There is no such certificate',
        self::CODE_NO_SUCH_KEY_FILE => 'There is no such key file',
        self::CODE_NO_TEMP_DIRECTORY => 'We need temporary directory, but we don\'t have one',
    ];


}