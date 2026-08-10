<?php

declare(strict_types=1);

namespace Esia\Exceptions;

/**
 * Thrown when a standard claim (iss, aud/client_id, iat) does not match
 * the expected value.
 */
class InvalidClaimException extends InvalidTokenException
{
}
