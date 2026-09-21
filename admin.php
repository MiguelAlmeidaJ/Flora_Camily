<?php
require __DIR__ . '/config.php';

if (isset($_GET['logout'])) {
    if (adminLoggedIn()) {
        appLog('auth.logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect('admin.php');
}

$loginError = '';
if (!adminLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, (string) $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = (string) $user['username'];
        $_SESSION['admin_role'] = (string) ($user['role'] ?? 'admin');
        appLog('auth.login', ['role' => $_SESSION['admin_role']]);
        redirect('admin.php');
    }

    $loginError = 'Usuário ou senha inválidos.';
}

if (!adminLoggedIn()):
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Entrar | Flora Camily</title>
    <link rel="icon" href="<?= e(siteFavicon()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin-panel.css">
</head>
<body class="admin-login-page">
    <main class="admin-login-shell">
        <section class="admin-login-wrap">
            <div class="admin-login-brand-panel">
                <div class="admin-login-brand-top">
                    <img src="<?= e(siteLogo()) ?>" alt="Flora Camily" class="admin-login-brand-logo">
                </div>

                <div class="admin-login-brand-copy">
                    <span class="admin-login-eyebrow">Flora Camily</span>
                    <h1>Gestão simples para cuidar de cada pedido.</h1>
                    <p>Produtos, pedidos, entregas e atendimento reunidos em um único lugar.</p>
                </div>


            </div>

            <div class="admin-login-form-panel">
                <div class="admin-login-form-inner">
                    <div class="admin-login-heading">
                        <span class="admin-login-mobile-logo">
                            <img src="<?= e(siteLogo()) ?>" alt="Flora Camily">
                        </span>
                        <span class="admin-login-kicker">Bem-vindo de volta</span>
                        <h2>Acessar painel</h2>
                        <p>Entre com seu usuário e senha para continuar.</p>
                    </div>

                    <?php if ($loginError): ?>
                        <div class="alert alert-danger admin-login-alert">
                            <i class="bi bi-exclamation-circle me-2"></i><?= e($loginError) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="admin-login-form">
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label class="form-label" for="loginUsername">Usuário</label>
                            <div class="admin-login-field">
                                <i class="bi bi-person"></i>
                                <input
                                    id="loginUsername"
                                    type="text"
                                    name="username"
                                    class="form-control"
                                    autocomplete="username"
                                    required
                                    autofocus
                                    placeholder="Digite seu usuário"
                                >
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="loginPassword">Senha</label>
                            <div class="admin-login-field">
                                <i class="bi bi-lock"></i>
                                <input
                                    id="loginPassword"
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Digite sua senha"
                                >
                                <button type="button" class="admin-login-password-toggle" data-password-toggle aria-label="Mostrar senha">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button class="btn admin-login-submit w-100" type="submit">
                            Entrar
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </form>

                    <div class="admin-login-back">
                        <a href="index.php">
                            <i class="bi bi-arrow-left"></i>
                            Voltar para o site
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
            const input = document.getElementById('loginPassword');
            const icon = this.querySelector('i');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            this.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
        });
    </script>
</body>
</html>
<?php
exit;
endif;

if (!empty($_GET['order'])) {
    redirect('admin-pedidos.php?order=' . (int) $_GET['order']);
}
if (!empty($_GET['edit'])) {
    redirect('admin-produtos.php?edit=' . (int) $_GET['edit']);
}

$adminPage = 'inicio';
$adminTitle = 'Início';
$adminSubtitle = isDev() ? 'Visão geral técnica e acesso completo ao sistema.' : 'Resumo da operação da loja hoje.';

if (isDev()) {
    $userCount = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    $logCount = 0;
    try { $logCount = (int) db()->query('SELECT COUNT(*) FROM app_logs')->fetchColumn(); } catch (Throwable $e) {}
    $orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pendingMigrations = 0;
    try {
        $applied = db()->query('SELECT migration_name FROM migration_history')->fetchAll(PDO::FETCH_COLUMN);
        $migrationFiles = array_map('basename', glob(__DIR__ . '/migrations/*.sql') ?: []);
        $pendingMigrations = count(array_diff($migrationFiles, $applied));
    } catch (Throwable $e) {}
} else {
    $statusCounts = array_fill_keys(array_keys(orderStatusOptions()), 0);
    foreach (db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll() as $row) {
        if (isset($statusCounts[$row['status']])) {
            $statusCounts[$row['status']] = (int) $row['total'];
        }
    }
    $monthRevenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'entregue' AND YEAR(created_at)=YEAR(CURRENT_DATE()) AND MONTH(created_at)=MONTH(CURRENT_DATE())")->fetchColumn();
    $recentOrders = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 6')->fetchAll();
}

require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if (isDev()): ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon"><i class="bi bi-people"></i></span><div><small>Usuários</small><strong><?= $userCount ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon"><i class="bi bi-journal-text"></i></span><div><small>Logs</small><strong><?= $logCount ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon"><i class="bi bi-receipt"></i></span><div><small>Pedidos</small><strong><?= $orderCount ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon"><i class="bi bi-database-gear"></i></span><div><small>Migrations pendentes</small><strong><?= $pendingMigrations ?></strong></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="admin-card p-4 h-100">
                <span class="eyebrow">Ambiente</span>
                <h2 class="h4 mt-2 mb-4">Diagnóstico rápido</h2>
                <div class="row g-3">
                    <div class="col-md-6"><div class="admin-info-tile"><small>PHP</small><strong><?= e(PHP_VERSION) ?></strong></div></div>
                    <div class="col-md-6"><div class="admin-info-tile"><small>Banco</small><strong><?= e((string) db()->query('SELECT VERSION()')->fetchColumn()) ?></strong></div></div>
                    <div class="col-md-6"><div class="admin-info-tile"><small>Uploads</small><strong><?= is_writable(__DIR__ . '/uploads') ? 'Gravável' : 'Sem permissão' ?></strong></div></div>
                    <div class="col-md-6"><div class="admin-info-tile"><small>E-mail</small><strong><?= STORE_EMAIL !== '' ? e(STORE_EMAIL) : 'Não configurado' ?></strong></div></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="admin-card p-4 h-100">
                <span class="eyebrow">Acesso completo</span>
                <h2 class="h4 mt-2 mb-3">Gestão da loja</h2>
                <p class="text-secondary small">Mesmo com um menu DEV mais limpo, este perfil continua com acesso integral às rotinas operacionais.</p>
                <div class="d-grid gap-2">
                    <a href="admin-pedidos.php" class="btn btn-light border text-start"><i class="bi bi-receipt me-2"></i>Pedidos</a>
                    <a href="admin-produtos.php" class="btn btn-light border text-start"><i class="bi bi-flower1 me-2"></i>Produtos</a>
                    <a href="admin-categorias.php" class="btn btn-light border text-start"><i class="bi bi-tags me-2"></i>Categorias</a>
                    <a href="admin-financeiro.php" class="btn btn-light border text-start"><i class="bi bi-cash-coin me-2"></i>Financeiro</a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon danger"><i class="bi bi-bell"></i></span><div><small>Novos pedidos</small><strong><?= $statusCounts['novo'] ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon primary"><i class="bi bi-flower2"></i></span><div><small>Em preparação</small><strong><?= $statusCounts['em_preparacao'] ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon info"><i class="bi bi-truck"></i></span><div><small>Em entrega</small><strong><?= $statusCounts['em_entrega'] ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-kpi-card"><span class="admin-kpi-icon success"><i class="bi bi-cash-coin"></i></span><div><small>Faturado no mês</small><strong class="admin-kpi-money"><?= money($monthRevenue) ?></strong></div></div></div>
    </div>

    <div class="admin-card p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div><h2 class="h4 mb-1">Pedidos recentes</h2><p class="small text-secondary mb-0">Acompanhe rapidamente o que acabou de entrar.</p></div>
            <a href="admin-pedidos.php" class="btn btn-outline-brand">Ver todos</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>#</th><th>Cliente</th><th>Entrega</th><th>Status</th><th>Total</th><th></th></tr></thead>
                <tbody>
                <?php if (!$recentOrders): ?><tr><td colspan="6" class="text-center py-5 text-secondary">Nenhum pedido registrado.</td></tr><?php endif; ?>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><strong>#<?= (int) $order['id'] ?></strong></td>
                        <td><?= e($order['customer_name']) ?><div class="small text-secondary"><?= e($order['customer_phone']) ?></div></td>
                        <td><?= e($order['city']) ?>/<?= e($order['state']) ?><div class="small text-secondary"><?= $order['delivery_date'] ? date('d/m/Y', strtotime($order['delivery_date'])) : '—' ?></div></td>
                        <td><span class="badge <?= e(orderStatusClass((string) $order['status'])) ?>"><?= e(orderStatusLabel((string) $order['status'])) ?></span></td>
                        <td class="text-nowrap"><?= money((float) $order['total']) ?></td>
                        <td class="text-end"><a href="admin-pedido?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-light border">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
