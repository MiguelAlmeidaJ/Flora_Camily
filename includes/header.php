<?php
require_once __DIR__ . '/../config.php';
$pageTitle = $pageTitle ?? SITE_NAME;
$headerCategoryTree = categoryTree();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Homenagens florais feitas com cuidado, respeito e delicadeza.">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="<?= e(siteFavicon()) ?>">
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
