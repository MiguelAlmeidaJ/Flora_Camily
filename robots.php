<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=UTF-8');

$lines = [
    'User-agent: *',
    'Allow: /',
    'Disallow: /admin',
    'Disallow: /config',
    'Disallow: /includes/',
    'Disallow: /migrations/',
    'Disallow: /database.sql',
    '',
    'Sitemap: ' . absoluteUrl('/sitemap.xml'),
];

echo implode("\n", $lines) . "\n";
