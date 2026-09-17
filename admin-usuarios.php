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
                if (!$currentUser) throw new RuntimeException('Usuário não encontrado.');

                if ($currentUser['role'] === 'dev' && $role !== 'dev') {
                    $devCount = (int) db()->query("SELECT COUNT(*) FROM admin_users WHERE role='dev'")->fetchColumn();
                    if ($devCount <= 1) throw new RuntimeException('É necessário manter pelo menos um usuário DEV.');
                }

                if ($password !== '') {
                    db()->prepare('UPDATE admin_users SET username=?, role=?, password_hash=? WHERE id=?')->execute([$username, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    db()->prepare('UPDATE admin_users SET username=?, role=? WHERE id=?')->execute([$username, $role, $id]);
                }
                if ($id === (int) $_SESSION['admin_id']) {
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_role'] = $role;
                }
                appLog('user.update', ['user_id' => $id, 'username' => $username, 'role' => $role, 'password_changed' => $password !== '']);
                $_SESSION['admin_flash'] = 'Usuário atualizado.';
            } else {
                db()->prepare('INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, ?)')->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
                $id = (int) db()->lastInsertId();
                appLog('user.create', ['user_id' => $id, 'username' => $username, 'role' => $role]);
                $_SESSION['admin_flash'] = 'Usuário criado com sucesso.';
            }
            redirect('admin-usuarios.php');
        }

        if ($action === 'delete_user') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id === (int) $_SESSION['admin_id']) throw new RuntimeException('Você não pode excluir o usuário atual.');

            $stmt = db()->prepare('SELECT username, role FROM admin_users WHERE id=?');
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) throw new RuntimeException('Usuário não encontrado.');

            if ($user['role'] === 'dev') {
                $devCount = (int) db()->query("SELECT COUNT(*) FROM admin_users WHERE role='dev'")->fetchColumn();
                if ($devCount <= 1) throw new RuntimeException('É necessário manter pelo menos um usuário DEV.');
            }
            db()->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);
            appLog('user.delete', ['user_id' => $id, 'username' => $user['username'], 'role' => $user['role']]);
            $_SESSION['admin_flash'] = 'Usuário excluído.';
            redirect('admin-usuarios.php');
        }
    } catch (Throwable $e) {
        appLog('user.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$editUser = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT id, username, role, created_at FROM admin_users WHERE id=?');
    $stmt->execute([(int) $_GET['edit']]);
    $editUser = $stmt->fetch() ?: null;
}
$users = db()->query('SELECT id, username, role, created_at FROM admin_users ORDER BY role DESC, username ASC')->fetchAll();

$adminPage = 'usuarios';
$adminTitle = 'Usuários';
$adminSubtitle = 'Controle quem acessa o painel e o nível de permissão.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-4 align-items-start">
    <div class="col-xl-4">
        <div class="admin-card p-4 sticky-xl-top" style="top:24px">
            <h2 class="h4 mb-4"><?= $editUser ? 'Editar usuário' : 'Novo usuário' ?></h2>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" value="<?= (int) ($editUser['id'] ?? 0) ?>">
                <div class="mb-3"><label class="form-label fw-semibold">Usuário</label><input type="text" name="username" class="form-control" required maxlength="80" value="<?= e($editUser['username'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Perfil</label><select name="role" class="form-select"><option value="admin" <?= ($editUser['role'] ?? 'admin') === 'admin' ? 'selected' : '' ?>>Admin</option><option value="dev" <?= ($editUser['role'] ?? '') === 'dev' ? 'selected' : '' ?>>Dev</option></select><div class="form-text">Admin gerencia a loja. Dev possui acesso total e ferramentas técnicas.</div></div>
                <div class="mb-4"><label class="form-label fw-semibold"><?= $editUser ? 'Nova senha (opcional)' : 'Senha inicial' ?></label><input type="password" name="password" class="form-control" minlength="8" <?= $editUser ? '' : 'required' ?>></div>
                <button class="btn btn-dark w-100" type="submit"><i class="bi bi-person-check me-1"></i>Salvar usuário</button>
                <?php if ($editUser): ?><a href="admin-usuarios.php" class="btn btn-light border w-100 mt-2">Cancelar edição</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Contas do sistema</h2><div class="small text-secondary">Usuários administrativos e técnicos</div></div><span class="badge text-bg-light border"><?= count($users) ?> contas</span></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Usuário</th><th>Perfil</th><th>Criado em</th><th class="text-end">Ações</th></tr></thead><tbody>
            <?php foreach ($users as $user): ?><tr><td><strong><?= e($user['username']) ?></strong><?= (int) $user['id'] === (int) $_SESSION['admin_id'] ? ' <span class="badge text-bg-light border">você</span>' : '' ?></td><td><span class="badge <?= $user['role'] === 'dev' ? 'text-bg-dark' : 'text-bg-secondary' ?>"><?= strtoupper(e($user['role'])) ?></span></td><td class="small text-secondary"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td><td class="text-end text-nowrap"><a href="admin-usuarios.php?edit=<?= (int) $user['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <?php if ((int) $user['id'] !== (int) $_SESSION['admin_id']): ?><form method="post" class="d-inline" onsubmit="return confirm('Excluir este usuário?');"><?= csrfField() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $user['id'] ?>"><button class="btn btn-sm btn-light border text-danger" type="submit"><i class="bi bi-trash"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
