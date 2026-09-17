<?php
require_once __DIR__ . '/../config.php';
requireAdmin();

$adminPage = $adminPage ?? 'inicio';
$adminTitle = $adminTitle ?? 'Painel';
$adminSubtitle = $adminSubtitle ?? '';
$adminRole = adminRole();

try {
    $adminUnreadOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE is_read = 0 AND status = 'novo'")->fetchColumn();
} catch (Throwable $e) {
    $adminUnreadOrders = 0;
}

$adminMenu = isDev()
    ? [
        ['key' => 'inicio', 'label' => 'Início', 'icon' => 'house-door', 'href' => 'admin.php'],
        ['key' => 'usuarios', 'label' => 'Usuários', 'icon' => 'people', 'href' => 'admin-usuarios.php'],
        ['key' => 'logs', 'label' => 'Logs', 'icon' => 'journal-text', 'href' => 'admin-logs.php'],
        ['key' => 'configuracoes', 'label' => 'Configurações', 'icon' => 'gear', 'href' => 'admin-configuracoes.php'],
    ]
    : [
        ['key' => 'inicio', 'label' => 'Início', 'icon' => 'house-door', 'href' => 'admin.php'],
        ['key' => 'financeiro', 'label' => 'Financeiro', 'icon' => 'cash-coin', 'href' => 'admin-financeiro.php'],
        ['key' => 'pedidos', 'label' => 'Pedidos', 'icon' => 'receipt', 'href' => 'admin-pedidos.php'],
        ['key' => 'produtos', 'label' => 'Produtos', 'icon' => 'flower1', 'href' => 'admin-produtos.php'],
        ['key' => 'categorias', 'label' => 'Categorias', 'icon' => 'tags', 'href' => 'admin-categorias.php'],
        ['key' => 'notificacoes', 'label' => 'Notificações', 'icon' => 'bell', 'href' => 'admin-notificacoes.php'],
        ['key' => 'configuracoes', 'label' => 'Configurações', 'icon' => 'gear', 'href' => 'admin-configuracoes.php'],
    ];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($adminTitle) ?> | Flora Camily</title>
    <link rel="icon" href="<?= e(siteFavicon()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-panel.css">
</head>
<body class="admin-shell admin-panel-shell">
<div class="admin-panel-layout">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <a href="admin.php" class="d-flex align-items-center gap-3 text-decoration-none">
                <img src="<?= e(siteLogo()) ?>" alt="Flora Camily" class="admin-sidebar-logo">
                <div class="min-w-0">
                    <strong>Flora Camily</strong>
                    <small><?= isDev() ? 'Painel DEV' : 'Painel administrativo' ?></small>
                </div>
            </a>
            <button class="btn admin-sidebar-close d-lg-none" type="button" data-admin-sidebar-close aria-label="Fechar menu"><i class="bi bi-x-lg"></i></button>
        </div>

        <nav class="admin-sidebar-nav">
            <?php foreach ($adminMenu as $item): ?>
                <a href="<?= e($item['href']) ?>" class="admin-sidebar-link <?= $adminPage === $item['key'] ? 'active' : '' ?>">
                    <span class="admin-sidebar-icon"><i class="bi bi-<?= e($item['icon']) ?>"></i></span>
                    <span><?= e($item['label']) ?></span>
                    <?php if ($item['key'] === 'notificacoes' && $adminUnreadOrders > 0): ?>
                        <span class="badge rounded-pill text-bg-danger ms-auto"><?= $adminUnreadOrders ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if (isDev()): ?>
            <div class="admin-sidebar-dev-access">
                <div class="small text-uppercase fw-bold mb-2">Acesso completo</div>
                <a href="admin-pedidos.php" class="small"><i class="bi bi-shop me-2"></i>Abrir gestão da loja</a>
            </div>
        <?php endif; ?>

        <div class="admin-sidebar-footer">
            <div class="admin-user-pill">
                <span class="admin-user-avatar"><i class="bi bi-person"></i></span>
                <div class="min-w-0 flex-grow-1">
                    <strong class="text-truncate d-block"><?= e((string) ($_SESSION['admin_username'] ?? 'Usuário')) ?></strong>
                    <small><?= strtoupper(e($adminRole)) ?></small>
                </div>
            </div>
            <div class="d-grid gap-2 mt-3">
                <a href="index.php" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-box-arrow-up-right me-1"></i>Ver site</a>
                <a href="admin.php?logout=1" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Sair</a>
            </div>
        </div>
    </aside>

    <div class="admin-sidebar-backdrop" data-admin-sidebar-close></div>

    <div class="admin-panel-main">
        <header class="admin-panel-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light border d-lg-none" type="button" data-admin-sidebar-open aria-label="Abrir menu"><i class="bi bi-list"></i></button>
                <div>
                    <h1 class="admin-panel-title mb-0"><?= e($adminTitle) ?></h1>
                    <?php if ($adminSubtitle !== ''): ?><div class="admin-panel-subtitle"><?= e($adminSubtitle) ?></div><?php endif; ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (!isDev()): ?>
                    <a href="admin-notificacoes.php" class="btn btn-light border position-relative" title="Notificações">
                        <i class="bi bi-bell"></i>
                        <?php if ($adminUnreadOrders > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= $adminUnreadOrders ?></span><?php endif; ?>
                    </a>
                <?php else: ?>
                    <span class="badge rounded-pill text-bg-dark px-3 py-2">DEV</span>
                <?php endif; ?>
            </div>
        </header>

        <main class="admin-panel-content">
