<?php

namespace Esia\Signer\Exceptions;

use Esia\Exceptions\AbstractEsiaException;

class SignFailException extends AbstractEsiaException
{
    protected function getMessageForCode(int $code): string
    {
        return 'Signing is failed';
    }
}
