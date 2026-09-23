<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=UTF-8');

function sitemapXml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sitemapDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

$entries = [
    ['loc' => absoluteUrl('/')],
    ['loc' => absoluteUrl('/loja')],
];

try {
    $categories = db()->query(
        'SELECT slug, updated_at
         FROM categories
         WHERE active = 1
         ORDER BY sort_order ASC, name ASC'
    )->fetchAll();

    foreach ($categories as $category) {
        $slug = trim((string) ($category['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $entries[] = [
            'loc' => absoluteUrl('/loja?categoria=' . rawurlencode($slug)),
            'lastmod' => sitemapDate((string) ($category['updated_at'] ?? '')),
        ];
    }
} catch (Throwable $e) {
    // O sitemap continua válido com as URLs estáticas se as categorias estiverem indisponíveis.
}

try {
    $products = db()->query(
        'SELECT id, image, updated_at
         FROM products
         WHERE active = 1
         ORDER BY updated_at DESC, id DESC'
    )->fetchAll();

    foreach ($products as $product) {
        $id = (int) ($product['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $entry = [
            'loc' => absoluteUrl('/produto?id=' . $id),
            'lastmod' => sitemapDate((string) ($product['updated_at'] ?? '')),
        ];

        $image = trim((string) ($product['image'] ?? ''));
        if ($image !== '' && is_file(__DIR__ . '/' . ltrim($image, '/'))) {
            $entry['image'] = absoluteUrl($image);
        }

        $entries[] = $entry;
    }
} catch (Throwable $e) {
    // O sitemap continua válido com as demais URLs se os produtos estiverem indisponíveis.
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

foreach ($entries as $entry) {
    echo "  <url>\n";
    echo '    <loc>' . sitemapXml((string) $entry['loc']) . "</loc>\n";

    if (!empty($entry['lastmod'])) {
        echo '    <lastmod>' . sitemapXml((string) $entry['lastmod']) . "</lastmod>\n";
    }

    if (!empty($entry['image'])) {
        echo "    <image:image>\n";
        echo '      <image:loc>' . sitemapXml((string) $entry['image']) . "</image:loc>\n";
        echo "    </image:image>\n";
    }

    echo "  </url>\n";
}

echo "</urlset>\n";
