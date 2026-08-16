<?php

declare(strict_types=1);

namespace Esia\Signer;

trait UrlSafeSignatureTrait
{
    /**
     * Url safe for base64
     */
    protected function urlSafe(string $string): string
    {
        return rtrim(strtr(trim($string), '+/', '-_'), '=');
    }
}
