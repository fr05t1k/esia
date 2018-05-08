<?php

namespace Esia\Exceptions;

class ForbiddenException extends AbstractEsiaException
{
    protected function getMessageForCode(int $code): string
    {
        return 'Forbidden';
    }
}
