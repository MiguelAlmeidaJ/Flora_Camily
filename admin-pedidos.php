<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_order') {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'novo');
            $shippingRaw = trim((string) ($_POST['shipping_fee'] ?? ''));

            if ($orderId <= 0 || !array_key_exists($status, orderStatusOptions())) {
                throw new RuntimeException('Pedido ou status inválido.');
            }

            $stmt = db()->prepare('SELECT products_total FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $productsTotal = $stmt->fetchColumn();
            if ($productsTotal === false) {
                throw new RuntimeException('Pedido não encontrado.');
            }

            $shippingFee = null;
            if ($shippingRaw !== '') {
                $shippingFee = (float) str_replace(',', '.', $shippingRaw);
                if ($shippingFee < 0) {
                    throw new RuntimeException('O frete não pode ser negativo.');
                }
            }

            $total = (float) $productsTotal + ($shippingFee ?? 0.0);
            db()->prepare('UPDATE orders SET status = ?, shipping_fee = ?, total = ?, is_read = 1 WHERE id = ?')
                ->execute([$status, $shippingFee, $total, $orderId]);
            appLog('order.update', ['order_id' => $orderId, 'status' => $status, 'shipping_fee' => $shippingFee, 'total' => $total]);
            $_SESSION['admin_flash'] = 'Pedido atualizado com sucesso.';
            redirect('admin-pedidos.php?order=' . $orderId);
        }
    } catch (Throwable $e) {
        appLog('order.error', ['message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$selectedOrder = null;
$selectedOrderItems = [];
if (!empty($_GET['order'])) {
    $orderId = (int) $_GET['order'];
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $selectedOrder = $stmt->fetch() ?: null;
    if ($selectedOrder) {
        db()->prepare('UPDATE orders SET is_read = 1 WHERE id = ?')->execute([$orderId]);
        $selectedOrder['is_read'] = 1;
        $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
        $itemsStmt->execute([$orderId]);
        $selectedOrderItems = $itemsStmt->fetchAll();
    }
}

$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && array_key_exists($statusFilter, orderStatusOptions())) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([$statusFilter]);
    $orders = $stmt->fetchAll();
} else {
    $statusFilter = '';
    $orders = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 100')->fetchAll();
}

$adminPage = 'pedidos';
$adminTitle = 'Pedidos';
$adminSubtitle = 'Acompanhe cada venda da análise até a entrega.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="admin-pedidos.php" class="btn btn-sm <?= $statusFilter === '' ? 'btn-brand' : 'btn-light border' ?>">Todos</a>
    <?php foreach (orderStatusOptions() as $value => $label): ?>
        <a href="admin-pedidos.php?status=<?= urlencode($value) ?>" class="btn btn-sm <?= $statusFilter === $value ? 'btn-brand' : 'btn-light border' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="row g-4 align-items-start">
    <div class="<?= $selectedOrder ? 'col-xxl-7' : 'col-12' ?>">
        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h2 class="h4 mb-1">Lista de pedidos</h2><div class="small text-secondary"><?= count($orders) ?> registro(s) exibido(s)</div></div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 admin-orders-table">
                    <thead><tr><th>Pedido</th><th>Cliente</th><th>Entrega</th><th>Status</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?><tr><td colspan="6" class="text-center py-5 text-secondary">Nenhum pedido encontrado.</td></tr><?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <tr class="<?= !(int) $order['is_read'] && $order['status'] === 'novo' ? 'order-unread' : '' ?>">
                            <td><strong>#<?= (int) $order['id'] ?></strong><div class="small text-secondary"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div></td>
                            <td><strong><?= e($order['customer_name']) ?></strong><div class="small text-secondary"><?= e($order['customer_phone']) ?></div></td>
                            <td><?= e($order['city']) ?>/<?= e($order['state']) ?><div class="small text-secondary"><?= $order['delivery_date'] ? date('d/m/Y', strtotime($order['delivery_date'])) : '—' ?><?= $order['delivery_time'] ? ' · ' . substr((string) $order['delivery_time'], 0, 5) : '' ?></div></td>
                            <td><span class="badge <?= e(orderStatusClass((string) $order['status'])) ?>"><?= e(orderStatusLabel((string) $order['status'])) ?></span></td>
                            <td class="text-nowrap fw-semibold"><?= money((float) $order['total']) ?></td>
                            <td class="text-end"><a href="admin-pedidos.php?order=<?= (int) $order['id'] ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="btn btn-sm btn-light border">Ver</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($selectedOrder): ?>
        <div class="col-xxl-5">
            <div class="admin-card order-detail-card sticky-xl-top" style="top:24px">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-start gap-3">
                    <div><div class="small text-secondary">Pedido</div><h2 class="h3 mb-1">#<?= (int) $selectedOrder['id'] ?></h2><span class="badge <?= e(orderStatusClass((string) $selectedOrder['status'])) ?>"><?= e(orderStatusLabel((string) $selectedOrder['status'])) ?></span></div>
                    <a href="admin-pedidos.php" class="btn btn-sm btn-light border"><i class="bi bi-x-lg"></i></a>
                </div>
                <div class="p-4 border-bottom">
                    <h3 class="h6 text-uppercase text-secondary">Cliente</h3>
                    <div class="fw-bold"><?= e($selectedOrder['customer_name']) ?></div>
                    <div class="small text-secondary"><?= e($selectedOrder['customer_email']) ?></div>
                    <div class="small text-secondary mb-3"><?= e($selectedOrder['customer_phone']) ?></div>
                    <a href="<?= e(customerWhatsAppUrl((string) $selectedOrder['customer_phone'], (int) $selectedOrder['id'])) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm rounded-pill"><i class="bi bi-whatsapp me-1"></i>Chamar cliente</a>
                </div>
                <div class="p-4 border-bottom">
                    <h3 class="h6 text-uppercase text-secondary">Entrega e homenagem</h3>
                    <dl class="row small mb-0 order-dl">
                        <dt class="col-5">Homenageado(a)</dt><dd class="col-7"><?= e($selectedOrder['honoree_name']) ?></dd>
                        <dt class="col-5">Destino</dt><dd class="col-7"><?= e($selectedOrder['city']) ?>/<?= e($selectedOrder['state']) ?></dd>
                        <dt class="col-5">Local</dt><dd class="col-7"><?= e($selectedOrder['delivery_place']) ?></dd>
                        <dt class="col-5">Entrega</dt><dd class="col-7"><?= $selectedOrder['delivery_date'] ? date('d/m/Y', strtotime($selectedOrder['delivery_date'])) : '—' ?><?= $selectedOrder['delivery_time'] ? ' às ' . substr((string) $selectedOrder['delivery_time'], 0, 5) : '' ?></dd>
                    </dl>
                    <?php if ($selectedOrder['ribbon_message']): ?><div class="mt-3 p-3 bg-light rounded-3 small"><strong>Faixa:</strong><br><?= nl2br(e($selectedOrder['ribbon_message'])) ?></div><?php endif; ?>
                    <?php if ($selectedOrder['notes']): ?><div class="mt-3 small"><strong>Observações:</strong><br><?= nl2br(e($selectedOrder['notes'])) ?></div><?php endif; ?>
                </div>
                <div class="p-4 border-bottom">
                    <h3 class="h6 text-uppercase text-secondary">Itens</h3>
                    <div class="vstack gap-2"><?php foreach ($selectedOrderItems as $item): ?><div class="d-flex justify-content-between gap-3 small"><span><?= (int) $item['quantity'] ?>x <?= e($item['product_name']) ?></span><strong><?= money((float) $item['subtotal']) ?></strong></div><?php endforeach; ?></div>
                    <hr>
                    <div class="d-flex justify-content-between small mb-2"><span>Produtos</span><strong><?= money((float) $selectedOrder['products_total']) ?></strong></div>
                    <div class="d-flex justify-content-between small mb-2"><span>Frete</span><strong><?= $selectedOrder['shipping_fee'] === null ? 'A confirmar' : money((float) $selectedOrder['shipping_fee']) ?></strong></div>
                    <div class="d-flex justify-content-between fs-5"><strong>Total</strong><strong><?= money((float) $selectedOrder['total']) ?></strong></div>
                </div>
                <form method="post" class="p-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_order">
                    <input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
                    <div class="mb-3"><label class="form-label fw-semibold">Frete</label><div class="input-group"><span class="input-group-text">R$</span><input type="number" name="shipping_fee" class="form-control" min="0" step="0.01" placeholder="A confirmar" value="<?= $selectedOrder['shipping_fee'] !== null ? e(number_format((float) $selectedOrder['shipping_fee'], 2, '.', '')) : '' ?>"></div></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Status</label><select name="status" class="form-select"><?php foreach (orderStatusOptions() as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedOrder['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                    <button class="btn btn-brand w-100 rounded-3" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Atualizar pedido</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
