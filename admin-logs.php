<?php
require __DIR__ . '/config.php';
requireDev();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'clear_logs') {
        $count = (int) db()->query('SELECT COUNT(*) FROM app_logs')->fetchColumn();
        db()->exec('DELETE FROM app_logs');
        appLog('logs.clear', ['removed' => $count]);
        $_SESSION['admin_flash'] = 'Logs limpos.';
        redirect('admin-logs.php');
    }
}

$level = trim((string) ($_GET['level'] ?? ''));
if ($level !== '') {
    $stmt = db()->prepare('SELECT * FROM app_logs WHERE level = ? ORDER BY id DESC LIMIT 300');
    $stmt->execute([$level]);
    $logs = $stmt->fetchAll();
} else {
    $logs = db()->query('SELECT * FROM app_logs ORDER BY id DESC LIMIT 300')->fetchAll();
}

$adminPage = 'logs';
$adminTitle = 'Logs';
$adminSubtitle = 'Eventos técnicos e ações importantes realizadas no sistema.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>
<div class="admin-card p-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="h4 mb-1">Logs internos</h2>
            <p class="small text-secondary mb-0">Exibindo no máximo os 300 registros mais recentes.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="admin-logs.php" class="btn btn-sm <?= $level === '' ? 'btn-dark' : 'btn-light border' ?>">Todos</a>
            <?php foreach (['info','warning','error'] as $filter): ?><a href="admin-logs.php?level=<?= $filter ?>" class="btn btn-sm <?= $level === $filter ? 'btn-dark' : 'btn-light border' ?>"><?= ucfirst($filter) ?></a><?php endforeach; ?>
            <form method="post" onsubmit="return confirm('Limpar todos os logs?');"><?= csrfField() ?><input type="hidden" name="action" value="clear_logs"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Limpar</button></form>
        </div>
    </div>
    <div class="table-responsive"><table class="table align-middle admin-log-table mb-0"><thead><tr><th>Data</th><th>Nível</th><th>Ação</th><th>Usuário</th><th>IP</th><th>Contexto</th></tr></thead><tbody>
    <?php if (!$logs): ?><tr><td colspan="6" class="text-center py-5 text-secondary">Nenhum log encontrado.</td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?><tr><td class="text-nowrap small"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td><td><span class="badge <?= $log['level'] === 'error' ? 'text-bg-danger' : ($log['level'] === 'warning' ? 'text-bg-warning' : 'text-bg-secondary') ?>"><?= e($log['level']) ?></span></td><td><code><?= e($log['action']) ?></code></td><td><?= e($log['actor_username'] ?: 'sistema') ?></td><td class="small text-secondary"><?= e($log['ip_address']) ?></td><td><pre class="admin-log-context mb-0"><?= e($log['context_json'] ?: '—') ?></pre></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
