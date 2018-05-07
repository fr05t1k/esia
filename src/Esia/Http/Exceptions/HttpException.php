<?php

namespace Esia\Http\Exceptions;

use Psr\Http\Client\ClientException;

class HttpException  extends \RuntimeException implements ClientException
{
}
