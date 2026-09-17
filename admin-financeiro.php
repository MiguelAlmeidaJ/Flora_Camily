<?php
require __DIR__ . '/config.php';
requireAdmin();

$monthRevenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='entregue' AND YEAR(created_at)=YEAR(CURRENT_DATE()) AND MONTH(created_at)=MONTH(CURRENT_DATE())")->fetchColumn();
$totalRevenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='entregue'")->fetchColumn();
$openRevenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('em_preparacao','em_entrega')")->fetchColumn();
$cancelledRevenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status='cancelado'")->fetchColumn();
$deliveredCount = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status='entregue'")->fetchColumn();
$ticketAverage = $deliveredCount > 0 ? $totalRevenue / $deliveredCount : 0.0;
$recentDelivered = db()->query("SELECT * FROM orders WHERE status='entregue' ORDER BY updated_at DESC LIMIT 20")->fetchAll();

$adminPage = 'financeiro';
$adminTitle = 'Financeiro';
$adminSubtitle = 'Acompanhe faturamento, ticket médio e pedidos concluídos.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon success"><i class="bi bi-calendar-check"></i></span><div><small>Faturado no mês</small><strong class="admin-kpi-money"><?= money($monthRevenue) ?></strong></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon"><i class="bi bi-wallet2"></i></span><div><small>Faturamento total</small><strong class="admin-kpi-money"><?= money($totalRevenue) ?></strong></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon primary"><i class="bi bi-hourglass-split"></i></span><div><small>Em andamento</small><strong class="admin-kpi-money"><?= money($openRevenue) ?></strong></div></div></div>
    <div class="col-md-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon info"><i class="bi bi-receipt"></i></span><div><small>Ticket médio</small><strong class="admin-kpi-money"><?= money($ticketAverage) ?></strong></div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="admin-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Pedidos entregues</h2><p class="small text-secondary mb-0">Últimas vendas concluídas</p></div><span class="badge text-bg-success rounded-pill"><?= $deliveredCount ?> entregues</span></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Total</th><th></th></tr></thead><tbody>
            <?php if (!$recentDelivered): ?><tr><td colspan="5" class="text-center py-5 text-secondary">Ainda não há pedidos entregues.</td></tr><?php endif; ?>
            <?php foreach ($recentDelivered as $order): ?><tr><td><strong>#<?= (int) $order['id'] ?></strong></td><td><?= e($order['customer_name']) ?></td><td><?= date('d/m/Y H:i', strtotime($order['updated_at'] ?: $order['created_at'])) ?></td><td class="fw-semibold text-nowrap"><?= money((float) $order['total']) ?></td><td class="text-end"><a href="admin-pedidos.php?order=<?= (int) $order['id'] ?>" class="btn btn-sm btn-light border">Ver</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card p-4 h-100">
            <span class="eyebrow">Visão financeira</span>
            <h2 class="h4 mt-2 mb-4">Indicadores</h2>
            <div class="admin-finance-row"><span>Pedidos entregues</span><strong><?= $deliveredCount ?></strong></div>
            <div class="admin-finance-row"><span>Ticket médio</span><strong><?= money($ticketAverage) ?></strong></div>
            <div class="admin-finance-row"><span>Pedidos em andamento</span><strong><?= money($openRevenue) ?></strong></div>
            <div class="admin-finance-row text-danger"><span>Valor cancelado</span><strong><?= money($cancelledRevenue) ?></strong></div>
            <div class="alert alert-light border small mt-4 mb-0">O financeiro considera como faturamento apenas pedidos marcados como <strong>Entregue</strong>.</div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
