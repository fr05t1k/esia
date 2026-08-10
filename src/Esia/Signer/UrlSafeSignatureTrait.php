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

    /**
     * Normalize a base64 (possibly multi-line, PEM-wrapped) signature produced
     * by an external tool into the url-safe base64 form ESIA expects.
     */
    protected function normalizeBase64Signature(string $signature): string
    {
        $signature = preg_replace('/-----(BEGIN|END)[^-]*-----/', '', $signature) ?? $signature;
        $signature = str_replace(["\r", "\n", ' '], '', $signature);

        return $this->urlSafe($signature);
    }
}
