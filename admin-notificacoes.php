<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
$flashType = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (($_POST['action'] ?? '') === 'mark_all_read') {
        db()->exec("UPDATE orders SET is_read = 1 WHERE is_read = 0");
        appLog('notifications.mark_all_read');

        $_SESSION['admin_flash'] = 'Todas as notificações foram marcadas como lidas.';
        $_SESSION['admin_flash_type'] = 'success';
        redirect('admin-notificacoes');
    }
}

$filter = trim((string) ($_GET['filter'] ?? 'all'));
$allowedFilters = ['all', 'unread', 'email_failed', 'new_orders'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];

if ($filter === 'unread') {
    $where[] = "(is_read = 0 AND status = 'novo')";
} elseif ($filter === 'email_failed') {
    $where[] = 'email_notified = 0';
} elseif ($filter === 'new_orders') {
    $where[] = "status = 'novo'";
}

if ($search !== '') {
    $searchDigits = preg_replace('/\D+/', '', $search) ?: '';
    $clauses = [
        'customer_name LIKE ?',
        'customer_phone LIKE ?',
        'customer_email LIKE ?',
        'city LIKE ?',
    ];
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);

    if (ctype_digit($search)) {
        $clauses[] = 'id = ?';
        $params[] = (int) $search;
    } elseif ($searchDigits !== '') {
        $clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?";
        $params[] = '%' . $searchDigits . '%';
    }

    $where[] = '(' . implode(' OR ', $clauses) . ')';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare('SELECT COUNT(*) FROM orders' . $whereSql);
$countStmt->execute($params);
$totalFiltered = (int) $countStmt->fetchColumn();

$perPage = 20;
$totalPages = max(1, (int) ceil($totalFiltered / $perPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = db()->prepare(
    'SELECT id, customer_name, customer_phone, customer_email, city, state, total, status, is_read, email_notified, created_at
     FROM orders' .
    $whereSql .
    ' ORDER BY created_at DESC
      LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$listStmt->execute($params);
$notifications = $listStmt->fetchAll();

$summary = [
    'total' => 0,
    'unread' => 0,
    'email_failed' => 0,
    'new_orders' => 0,
];

try {
    $summaryRow = db()->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN is_read = 0 AND status = 'novo' THEN 1 ELSE 0 END) AS unread,
            SUM(CASE WHEN email_notified = 0 THEN 1 ELSE 0 END) AS email_failed,
            SUM(CASE WHEN status = 'novo' THEN 1 ELSE 0 END) AS new_orders
         FROM orders"
    )->fetch();

    if ($summaryRow) {
        foreach ($summary as $key => $value) {
            $summary[$key] = (int) ($summaryRow[$key] ?? 0);
        }
    }
} catch (Throwable $e) {
    $summary['total'] = $totalFiltered;
}

$filterLabels = [
    'all' => 'Todas',
    'unread' => 'Não lidas',
    'email_failed' => 'Falhas de e-mail',
    'new_orders' => 'Novos pedidos',
];

$queryBase = [];
if ($filter !== 'all') $queryBase['filter'] = $filter;
if ($search !== '') $queryBase['q'] = $search;

$from = $totalFiltered > 0 ? $offset + 1 : 0;
$to = min($offset + $perPage, $totalFiltered);

$adminPage = 'notificacoes';
$adminTitle = 'Notificações';
$adminSubtitle = 'Acompanhe novos pedidos e alertas importantes da operação.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flashType) ?> rounded-4 admin-feedback-alert">
        <i class="bi <?= $flashType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?> me-2"></i>
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="notifications-page">
    <section class="notifications-summary-grid">
        <a href="admin-notificacoes?filter=unread" class="notification-summary-card <?= $filter === 'unread' ? 'is-active' : '' ?>">
            <span class="notification-summary-icon is-unread"><i class="bi bi-bell"></i></span>
            <div>
                <small>Não lidas</small>
                <strong><?= $summary['unread'] ?></strong>
                <span>pedidos que ainda precisam ser vistos</span>
            </div>
        </a>

        <a href="admin-notificacoes?filter=new_orders" class="notification-summary-card <?= $filter === 'new_orders' ? 'is-active' : '' ?>">
            <span class="notification-summary-icon is-order"><i class="bi bi-receipt"></i></span>
            <div>
                <small>Novos pedidos</small>
                <strong><?= $summary['new_orders'] ?></strong>
                <span>aguardando análise da equipe</span>
            </div>
        </a>

        <a href="admin-notificacoes?filter=email_failed" class="notification-summary-card <?= $filter === 'email_failed' ? 'is-active' : '' ?>">
            <span class="notification-summary-icon <?= $summary['email_failed'] > 0 ? 'is-warning' : 'is-success' ?>">
                <i class="bi <?= $summary['email_failed'] > 0 ? 'bi-envelope-exclamation' : 'bi-envelope-check' ?>"></i>
            </span>
            <div>
                <small>Falhas de e-mail</small>
                <strong><?= $summary['email_failed'] ?></strong>
                <span><?= $summary['email_failed'] > 0 ? 'pedidos sem confirmação do servidor' : 'envios confirmados normalmente' ?></span>
            </div>
        </a>
    </section>

    <section class="notifications-panel">
        <div class="notifications-panel-head">
            <div>
                <span class="notifications-kicker">Central de atividade</span>
                <h2><?= e($filterLabels[$filter]) ?></h2>
                <p>
                    <?= $totalFiltered ?>
                    <?= $totalFiltered === 1 ? 'registro encontrado' : 'registros encontrados' ?>
                    <?= $search !== '' ? ' para “' . e($search) . '”' : '' ?>
                </p>
            </div>

            <?php if ($summary['unread'] > 0): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button class="btn btn-light border notifications-mark-read" type="submit">
                        <i class="bi bi-check2-all"></i>
                        Marcar tudo como lido
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="notifications-toolbar">
            <form method="get" action="admin-notificacoes" class="notifications-search">
                <?php if ($filter !== 'all'): ?>
                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <?php endif; ?>

                <i class="bi bi-search"></i>
                <input
                    type="search"
                    name="q"
                    class="form-control"
                    value="<?= e($search) ?>"
                    placeholder="Buscar pedido, cliente, telefone ou cidade..."
                >

                <?php if ($search !== ''): ?>
                    <?php
                        $clearSearchQuery = $filter !== 'all'
                            ? '?filter=' . urlencode($filter)
                            : '';
                    ?>
                    <a href="admin-notificacoes<?= e($clearSearchQuery) ?>" class="notifications-search-clear" title="Limpar busca">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>

            <div class="notifications-filter-tabs">
                <a href="admin-notificacoes<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>" class="<?= $filter === 'all' ? 'active' : '' ?>">
                    Todas
                    <span><?= $summary['total'] ?></span>
                </a>
                <a href="admin-notificacoes?<?= e(http_build_query(array_filter(['filter' => 'unread', 'q' => $search]))) ?>" class="<?= $filter === 'unread' ? 'active' : '' ?>">
                    Não lidas
                    <span><?= $summary['unread'] ?></span>
                </a>
                <a href="admin-notificacoes?<?= e(http_build_query(array_filter(['filter' => 'email_failed', 'q' => $search]))) ?>" class="<?= $filter === 'email_failed' ? 'active' : '' ?>">
                    E-mail
                    <span><?= $summary['email_failed'] ?></span>
                </a>
                <a href="admin-notificacoes?<?= e(http_build_query(array_filter(['filter' => 'new_orders', 'q' => $search]))) ?>" class="<?= $filter === 'new_orders' ? 'active' : '' ?>">
                    Novos
                    <span><?= $summary['new_orders'] ?></span>
                </a>
            </div>
        </div>

        <?php if (!$notifications): ?>
            <div class="notifications-empty-state">
                <span><i class="bi bi-bell-slash"></i></span>
                <h3>Nenhuma notificação encontrada</h3>
                <p>Ajuste os filtros ou a busca para visualizar outros registros.</p>
                <?php if ($filter !== 'all' || $search !== ''): ?>
                    <a href="admin-notificacoes" class="btn btn-light border">Limpar filtros</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $item): ?>
                    <?php
                        $isUnread = !(int) $item['is_read'] && $item['status'] === 'novo';
                        $emailFailed = !(int) $item['email_notified'];
                    ?>
                    <a
                        href="admin-pedido?id=<?= (int) $item['id'] ?>"
                        class="notification-row <?= $isUnread ? 'is-unread' : '' ?>"
                    >
                        <span class="notification-row-icon">
                            <i class="bi bi-receipt"></i>
                        </span>

                        <div class="notification-row-main">
                            <div class="notification-row-title">
                                <strong>Pedido #<?= (int) $item['id'] ?></strong>
                                <span>·</span>
                                <strong><?= e($item['customer_name']) ?></strong>
                                <?php if ($isUnread): ?>
                                    <span class="notification-new-badge">Novo</span>
                                <?php endif; ?>
                            </div>

                            <div class="notification-row-meta">
                                <span><i class="bi bi-geo-alt"></i><?= e($item['city']) ?>/<?= e($item['state']) ?></span>
                                <span><i class="bi bi-cash-stack"></i><?= money((float) $item['total']) ?></span>
                                <span class="order-status-badge order-status-<?= e((string) $item['status']) ?>">
                                    <?= e(orderStatusLabel((string) $item['status'])) ?>
                                </span>
                            </div>

                            <?php if ($emailFailed): ?>
                                <div class="notification-email-warning">
                                    <i class="bi bi-envelope-exclamation"></i>
                                    E-mail sem confirmação de envio
                                </div>
                            <?php else: ?>
                                <div class="notification-email-ok">
                                    <i class="bi bi-envelope-check"></i>
                                    E-mail confirmado
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="notification-row-side">
                            <time datetime="<?= e((string) $item['created_at']) ?>">
                                <strong><?= date('d/m/Y', strtotime((string) $item['created_at'])) ?></strong>
                                <span><?= date('H:i', strtotime((string) $item['created_at'])) ?></span>
                            </time>

                            <span class="notification-open-button">
                                Abrir pedido
                                <i class="bi bi-chevron-right"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="notifications-pagination">
                <span>
                    Exibindo <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $totalFiltered ?></strong>
                </span>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Paginação de notificações">
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                                $prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $page - 1)]));
                                $nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $page + 1)]));
                            ?>
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="admin-notificacoes?<?= e($prevQuery) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                            ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <?php $pageQuery = http_build_query(array_merge($queryBase, ['page' => $i])); ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="admin-notificacoes?<?= e($pageQuery) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="admin-notificacoes?<?= e($nextQuery) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
