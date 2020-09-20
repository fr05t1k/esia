<?php

namespace Esia\Http\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;

class HttpException  extends \RuntimeException implements ClientExceptionInterface
{
}
