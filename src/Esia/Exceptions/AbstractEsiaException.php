<?php

namespace Esia\Exceptions;

use Exception;

abstract class AbstractEsiaException extends Exception
{
    public function __construct(int $code = 0, Exception $previous = null)
    {
        parent::__construct($this->getMessageForCode($code), $code, $previous);
    }

    abstract protected function getMessageForCode(int $code): string;
}
