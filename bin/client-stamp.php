<?php

/**
 * Stamps the TypeScript client with the openapi.json it was generated from:
 * writes the SHA-256 of openapi.json, with line endings normalized to LF, to
 * client/openapi.sha256. tests/Feature/OpenApiTest.php compares the stamp with
 * the committed document, so a client left stale fails `composer test`
 * without Node.
 *
 * Run as the last step of `composer client:generate`.
 */
$root = dirname(__DIR__);

$document = file_get_contents($root.'/openapi.json');

if ($document === false) {
    fwrite(STDERR, "openapi.json is missing: export it first.\n");

    exit(1);
}

$stamp = hash('sha256', str_replace("\r\n", "\n", $document));

file_put_contents($root.'/client/openapi.sha256', $stamp."\n");

echo "Stamped client/openapi.sha256 ({$stamp})\n";
