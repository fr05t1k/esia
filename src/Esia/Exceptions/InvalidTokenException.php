<?php

declare(strict_types=1);

namespace Esia\Exceptions;

/**
 * Base exception for any failure while validating a JWT received from ESIA.
 */
class InvalidTokenException extends AbstractEsiaException
{
}
