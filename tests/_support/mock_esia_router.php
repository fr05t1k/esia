<?php

declare(strict_types=1);

/**
 * Minimal offline stand-in for the ESIA test portal.
 *
 * Started via the PHP built-in web server by the e2e suite. It answers the
 * handful of endpoints the library talks to (token exchange + `rs/prns`
 * resources), returning fixture payloads that mirror the real response shapes
 * (collections with `size`/`elements`, absolute element links, an unsigned JWT
 * carrying `urn:esia:sbj_id`). It intentionally performs no signature checks —
 * its purpose is to exercise the full HTTP round-trip, not ESIA's crypto.
 */

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$base = 'http://' . $host;
$oid = '1000299944';

header('Content-Type: application/json; charset=UTF-8');

$b64url = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

$json = static function (array $data): bool {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);

    return true;
};

if ($method === 'POST' && $path === '/aas/oauth2/v3/te') {
    $headerSegment = $b64url('{"typ":"JWT","alg":"none"}');
    $payloadSegment = $b64url((string) json_encode(['urn:esia:sbj_id' => (int) $oid], JSON_UNESCAPED_UNICODE));
    $token = $headerSegment . '.' . $payloadSegment . '.' . $b64url('signature');

    return $json(['access_token' => $token]);
}

switch (true) {
    case $path === "/rs/prns/$oid":
        return $json([
            'stateFacts' => ['EntityRoot'],
            'firstName' => 'Иван',
            'lastName' => 'Иванов',
            'middleName' => 'Иванович',
            'trusted' => true,
            'citizenship' => 'RUS',
        ]);

    case $path === "/rs/prns/$oid/ctts":
        return $json([
            'stateFacts' => ['hasSize'],
            'size' => 2,
            'elements' => ["$base/rs/prns/$oid/ctts/16", "$base/rs/prns/$oid/ctts/17"],
        ]);

    case (bool) preg_match("#^/rs/prns/$oid/ctts/(\d+)$#", $path, $matches):
        $contacts = [
            '16' => ['type' => 'EML', 'vrfStu' => 'VERIFIED', 'value' => 'ivan@example.com'],
            '17' => ['type' => 'MBT', 'vrfStu' => 'VERIFIED', 'value' => '+7 900 000-00-00'],
        ];

        return $json($contacts[$matches[1]] ?? ['type' => 'UNKNOWN']);

    case $path === "/rs/prns/$oid/roles":
        return $json([
            'stateFacts' => ['hasSize'],
            'size' => 1,
            'elements' => [[
                'oid' => 111,
                'prnOid' => (int) $oid,
                'fullName' => 'ООО «Ромашка»',
                'shortName' => 'Ромашка',
                'ogrn' => '1027700000000',
                'chief' => true,
                'admin' => true,
            ]],
        ]);

    case $path === "/rs/prns/$oid/orgs":
        return $json([
            'stateFacts' => ['hasSize'],
            'size' => 1,
            'elements' => ["$base/rs/orgs/111"],
        ]);

    case $path === '/rs/orgs/111':
        return $json([
            'oid' => 111,
            'fullName' => 'ООО «Ромашка»',
            'shortName' => 'Ромашка',
            'ogrn' => '1027700000000',
            'type' => 'LEGAL',
        ]);
}

http_response_code(404);

return $json(['error' => 'not_found', 'path' => $path]);
