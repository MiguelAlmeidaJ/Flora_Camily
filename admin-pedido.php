<?php
require __DIR__ . '/config.php';
requireAdmin();

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($orderId <= 0) {
    redirect('admin-pedidos.php');
}

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        $status = (string) ($_POST['status'] ?? 'novo');
        $shippingRaw = trim((string) ($_POST['shipping_fee'] ?? ''));

        if (!array_key_exists($status, orderStatusOptions())) {
            throw new RuntimeException('Status inválido.');
        }

        $stmt = db()->prepare('SELECT products_total FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $productsTotal = $stmt->fetchColumn();

        if ($productsTotal === false) {
            throw new RuntimeException('Pedido não encontrado.');
        }

        $shippingFee = null;
        if ($shippingRaw !== '') {
            if (str_contains($shippingRaw, ',')) {
                $shippingRaw = str_replace('.', '', $shippingRaw);
                $shippingRaw = str_replace(',', '.', $shippingRaw);
            }

            $shippingFee = (float) $shippingRaw;
            if ($shippingFee < 0) {
                throw new RuntimeException('O frete não pode ser negativo.');
            }
        }

        $total = (float) $productsTotal + ($shippingFee ?? 0.0);

        db()->prepare(
            'UPDATE orders
             SET status = ?, shipping_fee = ?, total = ?, is_read = 1
             WHERE id = ?'
        )->execute([$status, $shippingFee, $total, $orderId]);

        appLog('order.update', [
            'order_id' => $orderId,
            'status' => $status,
            'shipping_fee' => $shippingFee,
            'total' => $total,
        ]);

        $_SESSION['admin_flash'] = 'Pedido atualizado com sucesso.';
        redirect('admin-pedido.php?id=' . $orderId);
    } catch (Throwable $e) {
        appLog('order.error', [
            'order_id' => $orderId,
            'message' => $e->getMessage(),
        ], 'error');

        $flash = $e->getMessage();
    }
}

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['admin_flash'] = 'Pedido não encontrado.';
    redirect('admin-pedidos.php');
}

if (!(int) $order['is_read']) {
    db()->prepare('UPDATE orders SET is_read = 1 WHERE id = ?')->execute([$orderId]);
    $order['is_read'] = 1;
}

$itemsStmt = db()->prepare(
    'SELECT oi.*, p.image AS product_image
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC'
);
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$progressSteps = [
    'novo' => ['label' => 'Recebido', 'icon' => 'receipt'],
    'em_preparacao' => ['label' => 'Em preparação', 'icon' => 'flower1'],
    'em_entrega' => ['label' => 'Em entrega', 'icon' => 'truck'],
    'entregue' => ['label' => 'Entregue', 'icon' => 'check2-circle'],
];

$progressOrder = array_keys($progressSteps);
$currentProgressIndex = array_search((string) $order['status'], $progressOrder, true);
$isAdjusted = $order['status'] === 'aguardando_ajuste';
$isCancelled = $order['status'] === 'cancelado';

$adminPage = 'pedidos';
$adminTitle = 'Pedido #' . (int) $order['id'];
$adminSubtitle = 'Visualize os dados, ajuste o frete e acompanhe o andamento.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-danger rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<div class="order-page">
    <div class="order-page-toolbar">
        <a href="admin-pedidos.php" class="admin-back-link">
            <i class="bi bi-arrow-left"></i>
            Voltar para pedidos
        </a>

        <div class="d-flex flex-wrap gap-2">
            <a
                href="<?= e(customerWhatsAppUrl((string) $order['customer_phone'], (int) $order['id'])) ?>"
                target="_blank"
                rel="noopener"
                class="btn btn-success"
            >
                <i class="bi bi-whatsapp me-1"></i>Falar com cliente
            </a>
        </div>
    </div>

    <div class="order-detail-hero admin-card">
        <div class="order-detail-hero-main">
            <div>
                <div class="order-detail-eyebrow">Pedido</div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <h2 class="order-detail-number mb-0">#<?= (int) $order['id'] ?></h2>
                    <span class="order-status-badge order-status-<?= e((string) $order['status']) ?>">
                        <?= e(orderStatusLabel((string) $order['status'])) ?>
                    </span>
                </div>
                <div class="order-detail-created">
                    Recebido em <?= date('d/m/Y', strtotime((string) $order['created_at'])) ?>
                    às <?= date('H:i', strtotime((string) $order['created_at'])) ?>
                </div>
            </div>

            <div class="order-detail-total-block">
                <span>Total atual</span>
                <strong><?= money((float) $order['total']) ?></strong>
                <small><?= $order['shipping_fee'] === null ? 'Frete ainda não definido' : 'Frete incluído no total' ?></small>
            </div>
        </div>

        <?php if ($isCancelled): ?>
            <div class="order-progress-alert is-cancelled">
                <i class="bi bi-x-circle"></i>
                <div>
                    <strong>Pedido cancelado</strong>
                    <span>Este pedido está fora do fluxo de preparação e entrega.</span>
                </div>
            </div>
        <?php elseif ($isAdjusted): ?>
            <div class="order-progress-alert is-adjustment">
                <i class="bi bi-chat-left-text"></i>
                <div>
                    <strong>Aguardando ajuste</strong>
                    <span>O pedido está aguardando alinhamento com o cliente antes da preparação.</span>
                </div>
            </div>
        <?php else: ?>
            <div class="order-progress">
                <?php foreach ($progressSteps as $stepKey => $step): ?>
                    <?php
                        $stepIndex = array_search($stepKey, $progressOrder, true);
                        $done = $currentProgressIndex !== false && $stepIndex <= $currentProgressIndex;
                        $current = $currentProgressIndex !== false && $stepIndex === $currentProgressIndex;
                    ?>
                    <div class="order-progress-step <?= $done ? 'is-done' : '' ?> <?= $current ? 'is-current' : '' ?>">
                        <span class="order-progress-icon"><i class="bi bi-<?= e($step['icon']) ?>"></i></span>
                        <span class="order-progress-label"><?= e($step['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4 align-items-start mt-0">
        <div class="col-xl-8">
            <div class="row g-4">
                <div class="col-md-6">
                    <section class="admin-card order-info-card h-100">
                        <div class="order-card-head">
                            <span><i class="bi bi-person"></i></span>
                            <div>
                                <h3>Cliente</h3>
                                <p>Dados para contato e confirmação.</p>
                            </div>
                        </div>

                        <div class="order-info-list">
                            <div>
                                <span>Nome</span>
                                <strong><?= e($order['customer_name']) ?></strong>
                            </div>
                            <div>
                                <span>Telefone</span>
                                <strong><?= e($order['customer_phone']) ?></strong>
                            </div>
                            <div>
                                <span>E-mail</span>
                                <strong><?= e($order['customer_email'] ?: 'Não informado') ?></strong>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-md-6">
                    <section class="admin-card order-info-card h-100">
                        <div class="order-card-head">
                            <span><i class="bi bi-truck"></i></span>
                            <div>
                                <h3>Entrega</h3>
                                <p>Destino e horário informados pelo cliente.</p>
                            </div>
                        </div>

                        <div class="order-info-list">
                            <div>
                                <span>Cidade</span>
                                <strong>
                                    <?= e($order['city'] ?: 'Não informada') ?>
                                    <?= $order['state'] ? '/' . e((string) $order['state']) : '' ?>
                                </strong>
                            </div>
                            <div>
                                <span>Local</span>
                                <strong><?= e($order['delivery_place'] ?: 'Não informado') ?></strong>
                            </div>
                            <div>
                                <span>Data e hora</span>
                                <strong>
                                    <?= $order['delivery_date'] ? date('d/m/Y', strtotime((string) $order['delivery_date'])) : 'A confirmar' ?>
                                    <?= $order['delivery_time'] ? ' às ' . substr((string) $order['delivery_time'], 0, 5) : '' ?>
                                </strong>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-12">
                    <section class="admin-card order-info-card">
                        <div class="order-card-head">
                            <span><i class="bi bi-flower1"></i></span>
                            <div>
                                <h3>Homenagem</h3>
                                <p>Informações que acompanham a preparação do pedido.</p>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="order-highlight-field">
                                    <span>Homenageado(a)</span>
                                    <strong><?= e($order['honoree_name'] ?: 'Não informado') ?></strong>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="order-highlight-field">
                                    <span>Mensagem da faixa</span>
                                    <strong><?= $order['ribbon_message'] ? nl2br(e((string) $order['ribbon_message'])) : 'Sem mensagem informada' ?></strong>
                                </div>
                            </div>

                            <?php if (!empty($order['notes'])): ?>
                                <div class="col-12">
                                    <div class="order-note-box">
                                        <span>Observações do cliente</span>
                                        <p><?= nl2br(e((string) $order['notes'])) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <div class="col-12">
                    <section class="admin-card order-items-card">
                        <div class="order-card-head order-card-head-with-count">
                            <span><i class="bi bi-bag"></i></span>
                            <div>
                                <h3>Itens do pedido</h3>
                                <p>Produtos solicitados pelo cliente.</p>
                            </div>
                            <span class="badge text-bg-light border rounded-pill ms-auto"><?= count($items) ?></span>
                        </div>

                        <div class="order-items-list">
                            <?php foreach ($items as $item): ?>
                                <div class="order-item-row">
                                    <img
                                        src="<?= e(productImage($item['product_image'] ?? null)) ?>"
                                        alt="<?= e($item['product_name']) ?>"
                                        class="order-item-image"
                                    >
                                    <div class="order-item-main">
                                        <strong><?= e($item['product_name']) ?></strong>
                                        <span>
                                            <?= (int) $item['quantity'] ?> × <?= money((float) $item['unit_price']) ?>
                                        </span>
                                    </div>
                                    <strong class="order-item-subtotal"><?= money((float) $item['subtotal']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <form method="post" class="admin-card order-management-card sticky-xl-top">
                <?= csrfField() ?>

                <div class="order-management-head">
                    <div>
                        <span class="orders-kicker">Gerenciar pedido</span>
                        <h3 class="h5 mb-0 mt-1">Atualização</h3>
                    </div>
                    <i class="bi bi-sliders"></i>
                </div>

                <div class="order-management-body">
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="orderStatus">Status</label>
                        <select name="status" id="orderStatus" class="form-select">
                            <?php foreach (orderStatusOptions() as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $order['status'] === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="shippingFee">Frete manual</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input
                                type="number"
                                name="shipping_fee"
                                id="shippingFee"
                                class="form-control"
                                min="0"
                                step="0.01"
                                placeholder="A confirmar"
                                value="<?= $order['shipping_fee'] !== null ? e(number_format((float) $order['shipping_fee'], 2, '.', '')) : '' ?>"
                            >
                        </div>
                        <div class="form-text">Deixe vazio enquanto o valor ainda não estiver confirmado.</div>
                    </div>

                    <div class="order-summary-lines">
                        <div>
                            <span>Produtos</span>
                            <strong><?= money((float) $order['products_total']) ?></strong>
                        </div>
                        <div>
                            <span>Frete</span>
                            <strong><?= $order['shipping_fee'] === null ? 'A confirmar' : money((float) $order['shipping_fee']) ?></strong>
                        </div>
                        <div class="order-summary-total">
                            <span>Total</span>
                            <strong><?= money((float) $order['total']) ?></strong>
                        </div>
                    </div>

                    <button class="btn btn-brand w-100 mt-4" type="submit">
                        <i class="bi bi-check2 me-1"></i>Salvar atualização
                    </button>

                    <a
                        href="<?= e(customerWhatsAppUrl((string) $order['customer_phone'], (int) $order['id'])) ?>"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-light border w-100 mt-2"
                    >
                        <i class="bi bi-whatsapp me-1"></i>Conversar no WhatsApp
                    </a>
                </div>

                <div class="order-management-foot">
                    <div>
                        <span>E-mail da loja</span>
                        <strong><?= (int) $order['email_notified'] === 1 ? 'Notificado' : 'Sem confirmação' ?></strong>
                    </div>
                    <div>
                        <span>Última atualização</span>
                        <strong><?= !empty($order['updated_at']) ? date('d/m/Y H:i', strtotime((string) $order['updated_at'])) : '—' ?></strong>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
