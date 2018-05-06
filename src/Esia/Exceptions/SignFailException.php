<?php

namespace Esia\Exceptions;

class SignFailException extends AbstractEsiaException
{
    public const CODE_CANT_READ_CERT = 500;
    public const CODE_CANT_READ_PRIVATE_KEY = 501;
    public const CODE_SIGN_FAIL = 502;
    public const CODE_NO_SUCH_CERT_FILE = 504;
    public const CODE_NO_SUCH_KEY_FILE = 505;
    public const CODE_NO_TEMP_DIRECTORY = 506;
    public const CODE_CANNOT_GENERATE_RANDOM_INT = 507;

    private const MESSAGES = [
        self::CODE_CANT_READ_CERT => 'Can\'t read a certificate',
        self::CODE_CANT_READ_PRIVATE_KEY => 'Can\'t read a private key',
        self::CODE_SIGN_FAIL => 'Sign fail',
        self::CODE_NO_SUCH_CERT_FILE => 'There is no such certificate',
        self::CODE_NO_SUCH_KEY_FILE => 'There is no such key file',
        self::CODE_NO_TEMP_DIRECTORY => 'We need temporary directory, but we don\'t have one',
        self::CODE_CANNOT_GENERATE_RANDOM_INT => 'It was not possible to gather sufficient entropy'
    ];

    protected function getMessageForCode(int $code): string
    {
        return self::MESSAGES[$code] ?? 'Unknown message';
    }
}
