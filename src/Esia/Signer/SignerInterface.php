<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;

interface SignerInterface
{
    /**
     * @throws SignFailException
     */
    public function sign(string $message): string;
}
