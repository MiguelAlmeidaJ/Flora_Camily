<?php
require __DIR__ . '/config.php';
requireDev();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_user') {
            $id = (int) ($_POST['id'] ?? 0);
            $username = trim((string) ($_POST['username'] ?? ''));
            $role = (string) ($_POST['role'] ?? 'admin');
            $password = (string) ($_POST['password'] ?? '');

            if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $username)) {
                throw new RuntimeException('Use um usuário com 3 a 80 caracteres, contendo letras, números, ponto, traço ou underline.');
            }
            if (!in_array($role, ['admin', 'dev'], true)) {
                throw new RuntimeException('Perfil inválido.');
            }
            if ($id === 0 && strlen($password) < 8) {
                throw new RuntimeException('A senha inicial precisa ter pelo menos 8 caracteres.');
            }
            if ($password !== '' && strlen($password) < 8) {
                throw new RuntimeException('A senha precisa ter pelo menos 8 caracteres.');
            }

            $check = db()->prepare('SELECT id FROM admin_users WHERE username = ? AND id <> ? LIMIT 1');
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Este nome de usuário já está em uso.');
            }

            if ($id > 0) {
                $currentStmt = db()->prepare('SELECT username, role FROM admin_users WHERE id = ?');
                $currentStmt->execute([$id]);
                $currentUser = $currentStmt->fetch();
                if (!$currentUser) {
                    throw new RuntimeException('Usuário não encontrado.');
                }

                if ($currentUser['role'] === 'dev' && $role !== 'dev') {
                    $devCount = (int) db()->query("SELECT COUNT(*) FROM admin_users WHERE role = 'dev'")->fetchColumn();
                    if ($devCount <= 1) {
                        throw new RuntimeException('É necessário manter pelo menos um usuário DEV.');
                    }
                }

                if ($password !== '') {
                    db()->prepare('UPDATE admin_users SET username = ?, role = ?, password_hash = ? WHERE id = ?')
                        ->execute([$username, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    db()->prepare('UPDATE admin_users SET username = ?, role = ? WHERE id = ?')
                        ->execute([$username, $role, $id]);
                }

                if ($id === (int) $_SESSION['admin_id']) {
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_role'] = $role;
                }
                appLog('user.update', ['user_id' => $id, 'username' => $username, 'role' => $role, 'password_changed' => $password !== '']);
                $_SESSION['admin_flash'] = 'Usuário atualizado.';
            } else {
                db()->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                $id = (int) db()->lastInsertId();
                appLog('user.create', ['user_id' => $id, 'username' => $username, 'role' => $role]);
                $_SESSION['admin_flash'] = 'Usuário criado com sucesso.';
            }

            redirect('admin-dev.php#usuarios');
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $_SESSION['admin_id']) {
                throw new RuntimeException('Você não pode excluir o usuário com o qual está conectado.');
            }

            $stmt = db()->prepare('SELECT username, role FROM admin_users WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new RuntimeException('Usuário não encontrado.');
            }

            if ($user['role'] === 'dev') {
                $devCount = (int) db()->query("SELECT COUNT(*) FROM admin_users WHERE role = 'dev'")->fetchColumn();
                if ($devCount <= 1) {
                    throw new RuntimeException('É necessário manter pelo menos um usuário DEV.');
                }
            }

            db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
            appLog('user.delete', ['user_id' => $id, 'username' => $user['username'], 'role' => $user['role']]);
            $_SESSION['admin_flash'] = 'Usuário excluído.';
            redirect('admin-dev.php#usuarios');
        }

        if ($action === 'clear_logs') {
            $count = (int) db()->query('SELECT COUNT(*) FROM app_logs')->fetchColumn();
            db()->exec('DELETE FROM app_logs');
            appLog('logs.clear', ['removed' => $count]);
            $_SESSION['admin_flash'] = 'Logs limpos.';
            redirect('admin-dev.php#logs');
        }

        if ($action === 'test_email') {
            if (STORE_EMAIL === '' || !filter_var(STORE_EMAIL, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Configure store_email no config.local.php antes de testar.');
            }
            $headers = ['MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8'];
            if (FROM_EMAIL !== '' && filter_var(FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'From: Flora Camily <' . FROM_EMAIL . '>';
            }
            $sent = @mail(STORE_EMAIL, 'Teste de e-mail - Flora Camily', 'Se você recebeu esta mensagem, o mail() do servidor está funcionando.', implode("\r\n", $headers));
            appLog('tools.email_test', ['success' => $sent, 'destination' => STORE_EMAIL], $sent ? 'info' : 'warning');
            $_SESSION['admin_flash'] = $sent ? 'E-mail de teste enviado.' : 'O servidor não confirmou o envio do e-mail.';
            redirect('admin-dev.php#ferramentas');
        }
    } catch (Throwable $e) {
        appLog('dev.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$editUser = null;
if (!empty($_GET['edit_user'])) {
    $stmt = db()->prepare('SELECT id, username, role, created_at FROM admin_users WHERE id = ?');
    $stmt->execute([(int) $_GET['edit_user']]);
    $editUser = $stmt->fetch() ?: null;
}

$users = db()->query('SELECT id, username, role, created_at FROM admin_users ORDER BY role DESC, username ASC')->fetchAll();
$logs = db()->query('SELECT * FROM app_logs ORDER BY id DESC LIMIT 200')->fetchAll();
$dbVersion = (string) db()->query('SELECT VERSION()')->fetchColumn();
$serverSoftware = (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'Não informado');
$uploadWritable = is_writable(__DIR__ . '/uploads');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Área DEV | Flora Camily</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-shell">
<nav class="navbar bg-white border-bottom sticky-top admin-navbar">
    <div class="container py-2">
        <a href="admin.php" class="navbar-brand d-flex align-items-center gap-2">
            <img src="assets/img/logo.svg" class="brand-logo" alt="Flora Camily">
            <span class="brand-name">Flora Camily</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge rounded-pill text-bg-dark">DEV</span>
            <a href="admin-categorias.php" class="btn btn-light border rounded-pill"><i class="bi bi-tags me-1"></i>Categorias</a>
            <a href="admin.php" class="btn btn-light border rounded-pill"><i class="bi bi-arrow-left me-1"></i>Painel</a>
        </div>
    </div>
</nav>

<div class="container py-4 py-lg-5">
    <?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>

    <div class="mb-4">
        <span class="eyebrow">Acesso avançado</span>
        <h1 class="h2 mb-1">Ferramentas DEV</h1>
        <p class="text-secondary mb-0">Usuários, diagnóstico do ambiente e logs internos do sistema.</p>
    </div>

    <div class="row g-3 mb-5" id="ferramentas">
        <div class="col-md-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon bg-primary-subtle text-primary"><i class="bi bi-code-slash"></i></span><div><small>PHP</small><strong class="fs-6"><?= e(PHP_VERSION) ?></strong></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon bg-success-subtle text-success"><i class="bi bi-database"></i></span><div><small>MySQL/MariaDB</small><strong class="fs-6"><?= e($dbVersion) ?></strong></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon <?= $uploadWritable ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>"><i class="bi bi-folder-check"></i></span><div><small>Uploads</small><strong class="fs-6"><?= $uploadWritable ? 'Gravável' : 'Sem permissão' ?></strong></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon <?= STORE_EMAIL !== '' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning' ?>"><i class="bi bi-envelope"></i></span><div><small>E-mail da loja</small><strong class="fs-6 text-truncate" style="max-width:180px"><?= e(STORE_EMAIL !== '' ? STORE_EMAIL : 'Não configurado') ?></strong></div></div></div>
    </div>

    <div class="admin-card p-4 mb-5">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div>
                <h2 class="h4 mb-1">Diagnóstico rápido</h2>
                <div class="small text-secondary">Servidor: <?= e($serverSoftware) ?> · upload_max_filesize: <?= e((string) ini_get('upload_max_filesize')) ?> · post_max_size: <?= e((string) ini_get('post_max_size')) ?></div>
            </div>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="test_email">
                <button class="btn btn-outline-brand" type="submit"><i class="bi bi-send me-1"></i>Testar e-mail</button>
            </form>
        </div>
    </div>

    <section id="usuarios" class="scroll-margin-top mb-5">
        <div class="row g-4 align-items-start">
            <div class="col-xl-4">
                <div class="admin-card p-4 sticky-xl-top" style="top:95px">
                    <h2 class="h4 mb-4"><?= $editUser ? 'Editar usuário' : 'Novo usuário' ?></h2>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_user">
                        <input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
                        <div class="mb-3"><label class="form-label fw-semibold">Usuário</label><input type="text" name="username" class="form-control" required maxlength="80" value="<?= e($editUser['username'] ?? '') ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Perfil</label><select name="role" class="form-select"><option value="admin" <?= ($editUser['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Admin</option><option value="dev" <?= ($editUser['role'] ?? '') === 'dev' ? 'selected' : '' ?>>Dev</option></select><div class="form-text">Admin gerencia loja e pedidos. Dev também acessa usuários, diagnóstico e logs.</div></div>
                        <div class="mb-4"><label class="form-label fw-semibold"><?= $editUser ? 'Nova senha (opcional)' : 'Senha inicial' ?></label><input type="password" name="password" class="form-control" minlength="8" <?= $editUser ? '' : 'required' ?>><div class="form-text">Mínimo de 8 caracteres.</div></div>
                        <button class="btn btn-dark w-100" type="submit"><i class="bi bi-person-check me-1"></i>Salvar usuário</button>
                        <?php if ($editUser): ?><a href="admin-dev.php#usuarios" class="btn btn-light border rounded-pill w-100 mt-2">Cancelar edição</a><?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="admin-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mb-0">Usuários</h2><span class="badge text-bg-light border"><?= count($users) ?> contas</span></div>
                    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Usuário</th><th>Perfil</th><th>Criado</th><th class="text-end">Ações</th></tr></thead><tbody>
                    <?php foreach ($users as $user): ?><tr><td><strong><?= e($user['username']) ?></strong><?= (int) $user['id'] === (int) $_SESSION['admin_id'] ? ' <span class="badge text-bg-light border">você</span>' : '' ?></td><td><span class="badge <?= $user['role'] === 'dev' ? 'text-bg-dark' : 'text-bg-secondary' ?>"><?= strtoupper(e($user['role'])) ?></span></td><td class="small text-secondary"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td><td class="text-end text-nowrap"><a href="admin-dev.php?edit_user=<?= (int) $user['id'] ?>#usuarios" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <?php if ((int) $user['id'] !== (int) $_SESSION['admin_id']): ?><form method="post" class="d-inline" onsubmit="return confirm('Excluir este usuário?');"><?= csrfField() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-sm btn-light border text-danger" type="submit"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>
    </section>

    <section id="logs" class="scroll-margin-top">
        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div><h2 class="h4 mb-1">Logs do sistema</h2><div class="small text-secondary">Últimos 200 eventos registrados pela aplicação.</div></div>
                <form method="post" onsubmit="return confirm('Limpar todos os logs?');"><?= csrfField() ?><input type="hidden" name="action" value="clear_logs"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash me-1"></i>Limpar logs</button></form>
            </div>
            <div class="table-responsive"><table class="table table-sm align-middle dev-log-table mb-0"><thead><tr><th>Data</th><th>Nível</th><th>Ação</th><th>Usuário</th><th>Contexto</th></tr></thead><tbody>
            <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-secondary py-4">Nenhum log registrado.</td></tr><?php endif; ?>
            <?php foreach ($logs as $log): ?><tr><td class="text-nowrap small"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td><td><span class="badge <?= $log['level'] === 'error' ? 'text-bg-danger' : ($log['level'] === 'warning' ? 'text-bg-warning' : 'text-bg-light border') ?>"><?= e($log['level']) ?></span></td><td><code><?= e($log['action']) ?></code></td><td class="small"><?= e($log['actor_username'] ?? 'sistema') ?></td><td class="small dev-log-context"><code><?= e($log['context_json'] ?? '') ?></code></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
