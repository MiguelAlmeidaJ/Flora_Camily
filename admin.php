<?php
require __DIR__ . '/config.php';

$loginError = '';
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username']);
    redirect('admin.php');
}

if (!adminLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    verifyCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $user['username'];
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
    <title>Administração | Flora Camily</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-shell d-flex align-items-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5 col-xl-4">
                <div class="admin-card p-4 p-md-5 shadow-sm text-center">
                    <img src="assets/img/logo.png" alt="Flora Camily" class="admin-logo mb-3">
                    <h1 class="h2 mb-1">Área administrativa</h1>
                    <p class="text-secondary small mb-4">Gerencie produtos e acompanhe solicitações.</p>
                    <?php if ($loginError): ?><div class="alert alert-danger text-start"><?= e($loginError) ?></div><?php endif; ?>
                    <form method="post" class="text-start">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Usuário</label>
                            <input type="text" class="form-control" name="username" required autocomplete="username">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Senha</label>
                            <input type="password" class="form-control" name="password" required autocomplete="current-password">
                        </div>
                        <button class="btn btn-brand w-100" type="submit">Entrar</button>
                    </form>
                    <a href="index.php" class="d-inline-block mt-4 small text-secondary text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Voltar ao site</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
exit;
endif;

function adminUploadImage(?string $currentImage = null): ?string
{
    if (empty($_FILES['image']['name'])) {
        return $currentImage;
    }

    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload da imagem.');
    }
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException('A imagem deve ter no máximo 4 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use uma imagem JPG, PNG ou WEBP.');
    }

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Não foi possível preparar a pasta de uploads.');
    }

    $filename = 'produto_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Não foi possível salvar a imagem.');
    }

    if ($currentImage && str_starts_with($currentImage, 'uploads/') && is_file(__DIR__ . '/' . $currentImage)) {
        @unlink(__DIR__ . '/' . $currentImage);
    }

    return 'uploads/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? '')) ?: 'Homenagens florais';
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
            $active = isset($_POST['active']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;

            if ($name === '' || $price < 0) {
                throw new RuntimeException('Preencha nome e preço corretamente.');
            }

            $currentImage = null;
            if ($id > 0) {
                $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $currentImage = $stmt->fetchColumn() ?: null;
            }
            $image = adminUploadImage($currentImage);

            if ($id > 0) {
                $stmt = db()->prepare('UPDATE products SET name = ?, category = ?, description = ?, price = ?, image = ?, active = ?, featured = ? WHERE id = ?');
                $stmt->execute([$name, $category, $description, $price, $image, $active, $featured, $id]);
                $_SESSION['admin_flash'] = 'Produto atualizado com sucesso.';
            } else {
                $stmt = db()->prepare('INSERT INTO products (name, category, description, price, image, active, featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $category, $description, $price, $image, $active, $featured]);
                $_SESSION['admin_flash'] = 'Produto criado com sucesso.';
            }
            redirect('admin.php');
        }

        if ($action === 'delete_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $image = $stmt->fetchColumn();
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            if ($image && str_starts_with((string) $image, 'uploads/') && is_file(__DIR__ . '/' . $image)) {
                @unlink(__DIR__ . '/' . $image);
            }
            $_SESSION['admin_flash'] = 'Produto excluído.';
            redirect('admin.php');
        }

        if ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            if (strlen($new) < 8) {
                throw new RuntimeException('A nova senha precisa ter pelo menos 8 caracteres.');
            }

            $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
            $stmt->execute([(int) $_SESSION['admin_id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($current, $hash)) {
                throw new RuntimeException('A senha atual está incorreta.');
            }

            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
            $_SESSION['admin_flash'] = 'Senha alterada com sucesso.';
            redirect('admin.php');
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
    }
}

$editProduct = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editProduct = $stmt->fetch() ?: null;
}

$products = db()->query('SELECT * FROM products ORDER BY created_at DESC')->fetchAll();
$orders = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 20')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel | Flora Camily</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="admin-shell">
<nav class="navbar bg-white border-bottom sticky-top">
    <div class="container py-2">
        <a href="admin.php" class="navbar-brand d-flex align-items-center gap-2"><img src="assets/img/logo.png" class="brand-logo" alt=""><span class="brand-name">Flora Camily</span></a>
        <div class="d-flex gap-2">
            <a href="index.php" target="_blank" class="btn btn-light border rounded-pill"><i class="bi bi-box-arrow-up-right"></i><span class="d-none d-sm-inline ms-1">Ver site</span></a>
            <a href="admin.php?logout=1" class="btn btn-outline-danger rounded-pill"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Sair</span></a>
        </div>
    </div>
</nav>

<div class="container py-4 py-lg-5">
    <?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="admin-card p-4 sticky-xl-top" style="top:95px">
                <h1 class="h3 mb-4"><?= $editProduct ? 'Editar produto' : 'Novo produto' ?></h1>
                <form method="post" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome</label>
                        <input type="text" name="name" class="form-control" required maxlength="160" value="<?= e($editProduct['name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Categoria</label>
                        <input type="text" name="category" class="form-control" maxlength="100" value="<?= e($editProduct['category'] ?? 'Homenagens florais') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descrição</label>
                        <textarea name="description" class="form-control" rows="4"><?= e($editProduct['description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Preço</label>
                        <input type="number" name="price" class="form-control" min="0" step="0.01" required value="<?= e(isset($editProduct['price']) ? (string) $editProduct['price'] : '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Imagem</label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text">JPG, PNG ou WEBP. Máximo 4 MB.</div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($editProduct['active']) || (int) $editProduct['active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Produto ativo</label>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="featured" id="featured" <?= (int) ($editProduct['featured'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="featured">Destaque na home</label>
                    </div>
                    <button class="btn btn-brand w-100" type="submit"><i class="bi bi-check2 me-1"></i>Salvar produto</button>
                    <?php if ($editProduct): ?><a href="admin.php" class="btn btn-light border rounded-pill w-100 mt-2">Cancelar edição</a><?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="admin-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h3 mb-0">Produtos</h2>
                    <span class="badge text-bg-light border"><?= count($products) ?> itens</span>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Produto</th><th>Preço</th><th>Status</th><th class="text-end">Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><strong><?= e($product['name']) ?></strong><div class="small text-secondary"><?= e($product['category']) ?></div></td>
                                <td class="text-nowrap"><?= money((float) $product['price']) ?></td>
                                <td><span class="badge <?= (int) $product['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $product['active'] ? 'Ativo' : 'Oculto' ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a href="admin.php?edit=<?= (int) $product['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Excluir este produto?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                        <button class="btn btn-sm btn-light border text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-card p-4 mb-4">
                <h2 class="h3 mb-3">Solicitações recentes</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>#</th><th>Cliente</th><th>Local</th><th>Total</th><th>Data</th></tr></thead>
                        <tbody>
                        <?php if (!$orders): ?><tr><td colspan="5" class="text-secondary py-4 text-center">Nenhuma solicitação registrada.</td></tr><?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= (int) $order['id'] ?></td>
                                <td><strong><?= e($order['customer_name']) ?></strong><div class="small text-secondary"><?= e($order['customer_phone']) ?></div></td>
                                <td><?= e($order['delivery_place']) ?></td>
                                <td class="text-nowrap"><?= money((float) $order['total']) ?></td>
                                <td class="text-nowrap small"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-card p-4">
                <h2 class="h3 mb-3">Segurança</h2>
                <form method="post" class="row g-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="col-md-5"><label class="form-label fw-semibold">Senha atual</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-md-5"><label class="form-label fw-semibold">Nova senha</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-brand w-100" type="submit">Alterar</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
