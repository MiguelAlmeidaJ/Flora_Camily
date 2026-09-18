<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && !array_key_exists($statusFilter, orderStatusOptions())) {
    $statusFilter = '';
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];

if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $searchDigits = preg_replace('/\D+/', '', $search) ?: '';
    $clauses = [
        'customer_name LIKE ?',
        'customer_email LIKE ?',
        'customer_phone LIKE ?',
        'honoree_name LIKE ?',
        'city LIKE ?',
    ];
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);

    if (ctype_digit($search)) {
        $clauses[] = 'id = ?';
        $params[] = (int) $search;
    } elseif ($searchDigits !== '' && $searchDigits !== $search) {
        $clauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?";
        $params[] = '%' . $searchDigits . '%';
    }

    $where[] = '(' . implode(' OR ', $clauses) . ')';
}

$sql = 'SELECT * FROM orders';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC LIMIT 150';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusCounts = array_fill_keys(array_keys(orderStatusOptions()), 0);
$totalOrders = 0;
try {
    $countRows = db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll();
    foreach ($countRows as $row) {
        $status = (string) $row['status'];
        $count = (int) $row['total'];
        if (array_key_exists($status, $statusCounts)) {
            $statusCounts[$status] = $count;
        }
        $totalOrders += $count;
    }
} catch (Throwable $e) {
    $totalOrders = count($orders);
}

$adminPage = 'pedidos';
$adminTitle = 'Pedidos';
$adminSubtitle = 'Acompanhe cada venda da análise até a entrega.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-info rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<div class="orders-page">
    <div class="admin-card orders-overview-card mb-4">
        <div class="orders-overview-head">
            <div>
                <span class="orders-kicker">Gestão de vendas</span>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <h2 class="h4 mb-0">Pedidos</h2>
                    <span class="badge text-bg-light border rounded-pill"><?= $totalOrders ?></span>
                </div>
                <p class="small text-secondary mb-0 mt-1">Filtre, localize e acompanhe os pedidos da loja.</p>
            </div>

            <form method="get" class="orders-search">
                <?php if ($statusFilter !== ''): ?>
                    <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
                <?php endif; ?>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input
                        type="search"
                        name="q"
                        class="form-control border-start-0"
                        value="<?= e($search) ?>"
                        placeholder="Pedido, cliente, telefone..."
                        aria-label="Buscar pedidos"
                    >
                    <?php if ($search !== ''): ?>
                        <a href="admin-pedidos.php<?= $statusFilter !== '' ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-light border">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="orders-status-tabs">
            <a
                href="admin-pedidos.php<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>"
                class="orders-status-tab <?= $statusFilter === '' ? 'active' : '' ?>"
            >
                <span>Todos</span>
                <strong><?= $totalOrders ?></strong>
            </a>

            <?php foreach (orderStatusOptions() as $value => $label): ?>
                <?php
                    $query = ['status' => $value];
                    if ($search !== '') $query['q'] = $search;
                ?>
                <a
                    href="admin-pedidos.php?<?= e(http_build_query($query)) ?>"
                    class="orders-status-tab <?= $statusFilter === $value ? 'active' : '' ?>"
                >
                    <span><?= e($label) ?></span>
                    <strong><?= (int) ($statusCounts[$value] ?? 0) ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="admin-card p-0 overflow-hidden">
        <div class="orders-list-head">
            <div>
                <h2 class="h5 mb-1"><?= $statusFilter !== '' ? e(orderStatusLabel($statusFilter)) : 'Todos os pedidos' ?></h2>
                <p class="small text-secondary mb-0">
                    <?= count($orders) ?> <?= count($orders) === 1 ? 'pedido encontrado' : 'pedidos encontrados' ?>
                    <?= $search !== '' ? ' para “' . e($search) . '”' : '' ?>
                </p>
            </div>
        </div>

        <?php if (!$orders): ?>
            <div class="orders-empty-state">
                <span><i class="bi bi-receipt"></i></span>
                <h3 class="h5 mb-2">Nenhum pedido encontrado</h3>
                <p class="text-secondary mb-0">Ajuste os filtros ou a busca para tentar novamente.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 admin-orders-table orders-table">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Entrega</th>
                            <th>Status</th>
                            <th class="text-end">Total</th>
                            <th class="orders-action-column"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php $isUnread = !(int) $order['is_read'] && $order['status'] === 'novo'; ?>
                            <tr class="<?= $isUnread ? 'order-unread' : '' ?>">
                                <td>
                                    <a href="admin-pedido.php?id=<?= (int) $order['id'] ?>" class="order-number-link">
                                        <?php if ($isUnread): ?><span class="order-unread-dot"></span><?php endif; ?>
                                        #<?= (int) $order['id'] ?>
                                    </a>
                                    <div class="order-table-meta">
                                        <?= date('d/m/Y', strtotime((string) $order['created_at'])) ?>
                                        <span>·</span>
                                        <?= date('H:i', strtotime((string) $order['created_at'])) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="order-customer-name"><?= e($order['customer_name']) ?></div>
                                    <div class="order-table-meta"><?= e($order['customer_phone']) ?></div>
                                </td>

                                <td>
                                    <div class="order-delivery-city">
                                        <?= e(trim((string) $order['city'])) ?: 'Destino não informado' ?>
                                        <?= $order['state'] ? '/' . e((string) $order['state']) : '' ?>
                                    </div>
                                    <div class="order-table-meta">
                                        <?php if ($order['delivery_date']): ?>
                                            <?= date('d/m/Y', strtotime((string) $order['delivery_date'])) ?>
                                            <?= $order['delivery_time'] ? ' · ' . substr((string) $order['delivery_time'], 0, 5) : '' ?>
                                        <?php else: ?>
                                            Data a confirmar
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="order-status-badge order-status-<?= e((string) $order['status']) ?>">
                                        <?= e(orderStatusLabel((string) $order['status'])) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <div class="order-total"><?= money((float) $order['total']) ?></div>
                                    <div class="order-table-meta">
                                        <?= $order['shipping_fee'] === null ? 'Frete a confirmar' : 'Frete ' . money((float) $order['shipping_fee']) ?>
                                    </div>
                                </td>

                                <td class="text-end">
                                    <a
                                        href="admin-pedido.php?id=<?= (int) $order['id'] ?>"
                                        class="btn btn-sm btn-light border orders-view-button"
                                        aria-label="Abrir pedido #<?= (int) $order['id'] ?>"
                                        title="Abrir pedido"
                                    >
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
