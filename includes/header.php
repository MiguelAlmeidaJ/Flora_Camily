<?php
require_once __DIR__ . '/../config.php';

$pageTitle = $pageTitle ?? SITE_NAME;
$headerCategoryTree = categoryTree();

$seoScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$pageDescription = trim((string) ($pageDescription ?? 'Homenagens florais preparadas com cuidado, respeito e delicadeza pela Flora Camily.'));
$robotsMeta = trim((string) ($robotsMeta ?? 'index, follow, max-image-preview:large'));
$canonicalUrl = trim((string) ($canonicalUrl ?? canonicalUrlFromRequest()));
$seoType = trim((string) ($seoType ?? 'website'));
$seoImage = trim((string) ($seoImage ?? absoluteUrl(siteLogo())));
$structuredData = $structuredData ?? [];
$structuredData = is_array($structuredData) ? $structuredData : [];

if (
    http_response_code() >= 400
    || in_array($seoScript, ['carrinho.php', 'checkout.php', 'pedido-recebido.php'], true)
) {
    $robotsMeta = 'noindex, nofollow';
}

if ($seoScript === 'index.php') {
    $canonicalUrl = absoluteUrl('/');

    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => SITE_NAME,
        'url' => $canonicalUrl,
        'logo' => absoluteUrl(siteLogo()),
    ];

    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => SITE_NAME,
        'url' => $canonicalUrl,
        'inLanguage' => 'pt-BR',
    ];
}

if ($seoScript === 'loja.php') {
    $canonicalUrl = absoluteUrl('/loja');
    $categorySlug = trim((string) ($_GET['categoria'] ?? ''));
    $seoCategory = null;
    $seoParentCategory = null;

    if ($categorySlug !== '') {
        foreach ($headerCategoryTree as $rootCategory) {
            if ((string) ($rootCategory['slug'] ?? '') === $categorySlug) {
                $seoCategory = $rootCategory;
                break;
            }

            foreach ($rootCategory['children'] ?? [] as $childCategory) {
                if ((string) ($childCategory['slug'] ?? '') === $categorySlug) {
                    $seoCategory = $childCategory;
                    $seoParentCategory = $rootCategory;
                    break 2;
                }
            }
        }

        if ($seoCategory) {
            $categoryName = trim((string) ($seoCategory['name'] ?? ''));
            $pageTitle = $categoryName . ' | ' . SITE_NAME;
            $pageDescription = 'Encontre ' . $categoryName . ' na Flora Camily. Homenagens florais preparadas com cuidado e acompanhamento até a entrega.';
            $canonicalUrl = absoluteUrl('/loja?categoria=' . rawurlencode((string) $seoCategory['slug']));

            $breadcrumbItems = [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Início',
                    'item' => absoluteUrl('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Catálogo',
                    'item' => absoluteUrl('/loja'),
                ],
            ];

            if ($seoParentCategory) {
                $breadcrumbItems[] = [
                    '@type' => 'ListItem',
                    'position' => count($breadcrumbItems) + 1,
                    'name' => (string) $seoParentCategory['name'],
                    'item' => absoluteUrl('/loja?categoria=' . rawurlencode((string) $seoParentCategory['slug'])),
                ];
            }

            $breadcrumbItems[] = [
                '@type' => 'ListItem',
                'position' => count($breadcrumbItems) + 1,
                'name' => $categoryName,
                'item' => $canonicalUrl,
            ];

            $structuredData[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $breadcrumbItems,
            ];
        } else {
            $robotsMeta = 'noindex, follow';
        }
    }
}

if (
    $seoScript === 'produto.php'
    && isset($product)
    && is_array($product)
    && !empty($product['id'])
) {
    $canonicalUrl = absoluteUrl('/produto?id=' . (int) $product['id']);
    $seoType = 'product';

    $productDescription = excerpt((string) ($product['description'] ?? ''), 155);
    if ($productDescription === '') {
        $productDescription = 'Homenagem floral preparada pela Flora Camily com cuidado, respeito e acompanhamento até a entrega.';
    }
    $pageDescription = $productDescription;

    $productImagePath = trim((string) ($product['image'] ?? ''));
    $hasProductImage = $productImagePath !== ''
        && is_file(__DIR__ . '/../' . ltrim($productImagePath, '/'));

    if ($hasProductImage) {
        $seoImage = absoluteUrl($productImagePath);
    }

    $productSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => (string) $product['name'],
        'description' => $productDescription,
        'sku' => (string) $product['id'],
        'category' => productCategoryName($product),
        'offers' => [
            '@type' => 'Offer',
            'url' => $canonicalUrl,
            'priceCurrency' => 'BRL',
            'price' => number_format((float) $product['price'], 2, '.', ''),
            'availability' => 'https://schema.org/InStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
            ],
        ],
    ];

    if ($hasProductImage) {
        $productSchema['image'] = [$seoImage];
    }

    $structuredData[] = $productSchema;

    $breadcrumbItems = [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Início',
            'item' => absoluteUrl('/'),
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Catálogo',
            'item' => absoluteUrl('/loja'),
        ],
    ];

    $productCategorySlug = trim((string) ($product['category_slug'] ?? ''));
    if ($productCategorySlug !== '') {
        $breadcrumbItems[] = [
            '@type' => 'ListItem',
            'position' => count($breadcrumbItems) + 1,
            'name' => productCategoryName($product),
            'item' => absoluteUrl('/loja?categoria=' . rawurlencode($productCategorySlug)),
        ];
    }

    $breadcrumbItems[] = [
        '@type' => 'ListItem',
        'position' => count($breadcrumbItems) + 1,
        'name' => (string) $product['name'],
        'item' => $canonicalUrl,
    ];

    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $breadcrumbItems,
    ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="robots" content="<?= e($robotsMeta) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <link rel="icon" href="<?= e(siteFavicon()) ?>">

    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="<?= e($seoType) ?>">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($seoImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e($seoImage) ?>">

    <?php foreach ($structuredData as $schema): ?>
        <script type="application/ld+json"><?= json_encode(
            $schema,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?></script>
    <?php endforeach; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top border-bottom py-2">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php" aria-label="Flora Camily - Início">
            <img src="<?= e(siteLogo()) ?>" alt="Flora Camily" class="brand-logo">
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainMenu" aria-controls="mainMenu" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="index.php">Início</a></li>
                <?php foreach ($headerCategoryTree as $rootCategory): ?>
                    <?php if (!empty($rootCategory['children'])): ?>
                        <li class="nav-item dropdown">
                            <a
                                class="nav-link dropdown-toggle"
                                href="loja.php?categoria=<?= urlencode((string) $rootCategory['slug']) ?>"
                                role="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <?= e($rootCategory['name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-4 p-2">
                                <li>
                                    <a class="dropdown-item rounded-3" href="loja.php?categoria=<?= urlencode((string) $rootCategory['slug']) ?>">
                                        <i class="bi bi-grid me-2"></i>Ver todas em <?= e($rootCategory['name']) ?>
                                    </a>
                                </li>
                                <?php foreach ($rootCategory['children'] as $category): ?>
                                    <li>
                                        <a class="dropdown-item rounded-3" href="loja.php?categoria=<?= urlencode((string) $category['slug']) ?>">
                                            <i class="bi bi-flower1 me-2"></i><?= e($category['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="loja.php?categoria=<?= urlencode((string) $rootCategory['slug']) ?>">
                                <?= e($rootCategory['name']) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="nav-item"><a class="nav-link" href="index.php#como-funciona">Como funciona</a></li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-brand" href="<?= e(storeWhatsAppUrl()) ?>" target="_blank" rel="noopener">
                        <i class="bi bi-whatsapp me-1"></i> Comprar pelo WhatsApp
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<main>
