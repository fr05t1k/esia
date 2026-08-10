<?php

declare(strict_types=1);

namespace Esia\Exceptions;

/**
 * Thrown when the JWT signature cannot be verified against the ESIA certificate.
 */
class SignatureInvalidException extends InvalidTokenException
{
}
