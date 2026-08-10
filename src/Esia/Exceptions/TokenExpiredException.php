<?php

declare(strict_types=1);

namespace Esia\Exceptions;

/**
 * Thrown when the JWT is expired (exp) or not yet valid (nbf).
 */
class TokenExpiredException extends InvalidTokenException
{
}
