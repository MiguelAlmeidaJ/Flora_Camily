<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        db()->exec("UPDATE orders SET is_read = 1 WHERE is_read = 0");
        appLog('notifications.mark_all_read');
        $_SESSION['admin_flash'] = 'Notificações marcadas como lidas.';
        redirect('admin-notificacoes.php');
    }
}

$notifications = db()->query("SELECT id, customer_name, customer_phone, city, state, total, status, is_read, email_notified, created_at FROM orders ORDER BY created_at DESC LIMIT 80")->fetchAll();
$unread = 0;
$emailFailures = 0;
foreach ($notifications as $notification) {
    if (!(int) $notification['is_read'] && $notification['status'] === 'novo') $unread++;
    if (!(int) $notification['email_notified']) $emailFailures++;
}

$adminPage = 'notificacoes';
$adminTitle = 'Notificações';
$adminSubtitle = 'Novos pedidos e alertas de envio de e-mail.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-md-6"><div class="admin-kpi-card"><span class="admin-kpi-icon danger"><i class="bi bi-bell"></i></span><div><small>Novos não lidos</small><strong><?= $unread ?></strong></div></div></div>
    <div class="col-md-6"><div class="admin-kpi-card"><span class="admin-kpi-icon <?= $emailFailures > 0 ? 'danger' : 'success' ?>"><i class="bi bi-envelope"></i></span><div><small>E-mails sem confirmação</small><strong><?= $emailFailures ?></strong></div></div></div>
</div>

<div class="admin-card p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div><h2 class="h4 mb-1">Central de notificações</h2><p class="small text-secondary mb-0">Os pedidos mais recentes aparecem primeiro.</p></div>
        <?php if ($unread > 0): ?><form method="post"><?= csrfField() ?><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-light border" type="submit"><i class="bi bi-check2-all me-1"></i>Marcar tudo como lido</button></form><?php endif; ?>
    </div>
    <div class="vstack gap-2">
        <?php if (!$notifications): ?><div class="text-center text-secondary py-5">Nenhuma notificação ainda.</div><?php endif; ?>
        <?php foreach ($notifications as $item): ?>
            <a href="admin-pedido.php?id=<?= (int) $item['id'] ?>" class="admin-notification-item text-decoration-none <?= !(int) $item['is_read'] && $item['status'] === 'novo' ? 'unread' : '' ?>">
                <span class="admin-notification-icon"><i class="bi bi-receipt"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between gap-3"><strong>Pedido #<?= (int) $item['id'] ?> · <?= e($item['customer_name']) ?></strong><small class="text-secondary text-nowrap"><?= date('d/m H:i', strtotime($item['created_at'])) ?></small></div>
                    <div class="small text-secondary mt-1"><?= e($item['city']) ?>/<?= e($item['state']) ?> · <?= money((float) $item['total']) ?> · <?= e(orderStatusLabel((string) $item['status'])) ?></div>
                    <?php if (!(int) $item['email_notified']): ?><div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1"></i>O servidor não confirmou a notificação por e-mail.</div><?php endif; ?>
                </div>
                <?php if (!(int) $item['is_read'] && $item['status'] === 'novo'): ?><span class="admin-notification-dot"></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
