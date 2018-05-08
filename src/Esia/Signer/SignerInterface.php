<?php

namespace Esia\Signer;

use Esia\Signer\Exceptions\SignFailException;

interface SignerInterface
{
    /**
     * @param string $message
     * @return string
     * @throws SignFailException
     */
    public function sign(string $message): string;
}
