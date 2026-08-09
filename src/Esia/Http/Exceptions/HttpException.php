<?php

declare(strict_types=1);

namespace Esia\Http\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

class HttpException extends RuntimeException implements ClientExceptionInterface
{
}
