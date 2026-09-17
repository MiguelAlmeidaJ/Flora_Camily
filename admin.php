<?php
require __DIR__ . '/config.php';

$loginError = '';
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if (isset($_GET['logout']) && adminLoggedIn()) {
    appLog('auth.logout');
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
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
                    <img src="assets/img/logo.svg" alt="Flora Camily" class="admin-logo mb-3">
                    <h1 class="h2 mb-1">Área administrativa</h1>
                    <p class="text-secondary small mb-4">Gerencie pedidos, entregas e produtos.</p>
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
            appLog('order.update', ['order_id' => $orderId, 'status' => $status, 'shipping_fee' => $shippingFee]);

            $_SESSION['admin_flash'] = $status === 'em_preparacao'
                ? 'Venda fechada. Pedido movido para Em preparação.'
                : 'Pedido atualizado com sucesso.';
            redirect('admin.php?order=' . $orderId . '#pedido-' . $orderId);
        }

        if ($action === 'save_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;

            if ($name === '' || $price < 0) {
                throw new RuntimeException('Preencha nome e preço corretamente.');
            }

            $categoryName = 'Homenagens florais';
            $categoryDbValue = null;
            if ($categoryId > 0) {
                $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = ? LIMIT 1');
                $stmt->execute([$categoryId]);
                $category = $stmt->fetch();
                if (!$category) {
                    throw new RuntimeException('Categoria inválida.');
                }
                $categoryDbValue = (int) $category['id'];
                $categoryName = (string) $category['name'];
            } elseif ($id > 0) {
                $stmt = db()->prepare('SELECT category FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $existingCategory = trim((string) $stmt->fetchColumn());
                if ($existingCategory !== '') {
                    $categoryName = $existingCategory;
                }
            }

            $currentImage = null;
            if ($id > 0) {
                $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $currentImage = $stmt->fetchColumn() ?: null;
            }
            $image = adminUploadImage($currentImage);

            if ($id > 0) {
                $stmt = db()->prepare('UPDATE products SET name = ?, category = ?, category_id = ?, description = ?, price = ?, image = ?, active = ?, featured = ? WHERE id = ?');
                $stmt->execute([$name, $categoryName, $categoryDbValue, $description, $price, $image, $active, $featured, $id]);
                appLog('product.update', ['product_id' => $id, 'name' => $name, 'category_id' => $categoryDbValue]);
                $_SESSION['admin_flash'] = 'Produto atualizado com sucesso.';
            } else {
                $stmt = db()->prepare('INSERT INTO products (name, category, category_id, description, price, image, active, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $categoryName, $categoryDbValue, $description, $price, $image, $active, $featured]);
                $id = (int) db()->lastInsertId();
                appLog('product.create', ['product_id' => $id, 'name' => $name, 'category_id' => $categoryDbValue]);
                $_SESSION['admin_flash'] = 'Produto criado com sucesso.';
            }
            redirect('admin.php#produtos');
        }

        if ($action === 'delete_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT name, image FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if ($product) {
                db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                if (!empty($product['image']) && str_starts_with((string) $product['image'], 'uploads/') && is_file(__DIR__ . '/' . $product['image'])) {
                    @unlink(__DIR__ . '/' . $product['image']);
                }
                appLog('product.delete', ['product_id' => $id, 'name' => $product['name']]);
            }
            $_SESSION['admin_flash'] = 'Produto excluído.';
            redirect('admin.php#produtos');
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

            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
            appLog('user.password_change');
            $_SESSION['admin_flash'] = 'Senha alterada com sucesso.';
            redirect('admin.php#seguranca');
        }
    } catch (Throwable $e) {
        appLog('admin.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$categories = crownCategories(false);

$editProduct = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editProduct = $stmt->fetch() ?: null;
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

$products = db()->query(
    'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.created_at DESC'
)->fetchAll();
$orders = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 50')->fetchAll();
$unreadOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE is_read = 0 AND status = 'novo'")->fetchColumn();
$statusCounts = array_fill_keys(array_keys(orderStatusOptions()), 0);
foreach (db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll() as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['total'];
    }
}
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
<nav class="navbar bg-white border-bottom sticky-top admin-navbar">
    <div class="container py-2">
        <a href="admin.php" class="navbar-brand d-flex align-items-center gap-2">
            <img src="assets/img/logo.svg" class="brand-logo" alt="Flora Camily">
            <span class="brand-name">Flora Camily</span>
        </a>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <span class="badge rounded-pill <?= isDev() ? 'text-bg-dark' : 'text-bg-secondary' ?>"><?= isDev() ? 'DEV' : 'ADMIN' ?></span>
            <a href="admin.php#pedidos" class="btn btn-light border rounded-pill position-relative" title="Novos pedidos">
                <i class="bi bi-bell"></i>
                <?php if ($unreadOrders > 0): ?><span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= $unreadOrders ?></span><?php endif; ?>
            </a>
            <a href="admin-categorias.php" class="btn btn-light border rounded-pill"><i class="bi bi-tags"></i><span class="d-none d-md-inline ms-1">Categorias</span></a>
            <?php if (isDev()): ?><a href="admin-dev.php" class="btn btn-dark rounded-pill"><i class="bi bi-terminal"></i><span class="d-none d-md-inline ms-1">Dev</span></a><?php endif; ?>
            <a href="index.php" target="_blank" class="btn btn-light border rounded-pill"><i class="bi bi-box-arrow-up-right"></i><span class="d-none d-sm-inline ms-1">Ver site</span></a>
            <a href="admin.php?logout=1" class="btn btn-outline-danger rounded-pill"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Sair</span></a>
        </div>
    </div>
</nav>

<div class="container py-4 py-lg-5">
    <?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div>
            <span class="eyebrow">Painel da loja</span>
            <h1 class="h2 mb-1">Gestão de pedidos</h1>
            <p class="text-secondary mb-0">Acompanhe cada venda da análise até a entrega.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="#pedidos" class="btn btn-brand"><i class="bi bi-receipt me-1"></i>Pedidos</a>
            <a href="#produtos" class="btn btn-light border rounded-pill"><i class="bi bi-flower1 me-1"></i>Produtos</a>
            <a href="admin-categorias.php" class="btn btn-light border rounded-pill"><i class="bi bi-tags me-1"></i>Categorias</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon bg-danger-subtle text-danger"><i class="bi bi-bell"></i></span><div><small>Novos</small><strong><?= $statusCounts['novo'] ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon bg-primary-subtle text-primary"><i class="bi bi-flower2"></i></span><div><small>Em preparação</small><strong><?= $statusCounts['em_preparacao'] ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon bg-info-subtle text-info"><i class="bi bi-truck"></i></span><div><small>Em entrega</small><strong><?= $statusCounts['em_entrega'] ?></strong></div></div></div>
        <div class="col-6 col-xl-3"><div class="admin-stat-card"><span class="admin-stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></span><div><small>Entregues</small><strong><?= $statusCounts['entregue'] ?></strong></div></div></div>
    </div>

    <section id="pedidos" class="mb-5 scroll-margin-top">
        <div class="row g-4 align-items-start">
            <div class="<?= $selectedOrder ? 'col-xl-7' : 'col-12' ?>">
                <div class="admin-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div><h2 class="h3 mb-1">Pedidos</h2><div class="small text-secondary">Os pedidos mais recentes aparecem primeiro.</div></div>
                        <?php if ($unreadOrders > 0): ?><span class="badge text-bg-danger rounded-pill"><?= $unreadOrders ?> novo<?= $unreadOrders === 1 ? '' : 's' ?></span><?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle admin-orders-table mb-0">
                            <thead><tr><th>Pedido</th><th>Cliente</th><th>Entrega</th><th>Status</th><th>Total</th><th></th></tr></thead>
                            <tbody>
                            <?php if (!$orders): ?><tr><td colspan="6" class="text-secondary py-5 text-center">Nenhum pedido registrado.</td></tr><?php endif; ?>
                            <?php foreach ($orders as $order): ?>
                                <tr class="<?= !(int) $order['is_read'] && $order['status'] === 'novo' ? 'order-unread' : '' ?>">
                                    <td><strong>#<?= (int) $order['id'] ?></strong><div class="small text-secondary"><?= date('d/m H:i', strtotime($order['created_at'])) ?></div></td>
                                    <td><strong><?= e($order['customer_name']) ?></strong><div class="small text-secondary"><?= e($order['customer_phone']) ?></div></td>
                                    <td><div><?= e($order['city']) ?>/<?= e($order['state']) ?></div><div class="small text-secondary"><?= $order['delivery_date'] ? date('d/m/Y', strtotime($order['delivery_date'])) : 'Data não informada' ?><?= $order['delivery_time'] ? ' · ' . substr((string) $order['delivery_time'], 0, 5) : '' ?></div></td>
                                    <td><span class="badge <?= e(orderStatusClass((string) $order['status'])) ?>"><?= e(orderStatusLabel((string) $order['status'])) ?></span></td>
                                    <td class="text-nowrap fw-semibold"><?= money((float) $order['total']) ?></td>
                                    <td class="text-end"><a href="admin.php?order=<?= (int) $order['id'] ?>#pedido-<?= (int) $order['id'] ?>" class="btn btn-sm btn-light border">Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($selectedOrder): ?>
                <div class="col-xl-5" id="pedido-<?= (int) $selectedOrder['id'] ?>">
                    <div class="admin-card order-detail-card sticky-xl-top" style="top:95px">
                        <div class="p-4 border-bottom d-flex justify-content-between align-items-start gap-3">
                            <div><span class="small text-secondary">Pedido</span><h2 class="h3 mb-1">#<?= (int) $selectedOrder['id'] ?></h2><span class="badge <?= e(orderStatusClass((string) $selectedOrder['status'])) ?>"><?= e(orderStatusLabel((string) $selectedOrder['status'])) ?></span></div>
                            <a href="admin.php#pedidos" class="btn btn-sm btn-light border"><i class="bi bi-x-lg"></i></a>
                        </div>
                        <div class="p-4 border-bottom">
                            <h3 class="h6 text-uppercase text-secondary">Cliente</h3>
                            <div class="fw-bold"><?= e($selectedOrder['customer_name']) ?></div>
                            <div class="small text-secondary mb-3"><?= e($selectedOrder['customer_email']) ?></div>
                            <?php if ($selectedOrder['customer_phone']): ?><a href="<?= e(customerWhatsAppUrl((string) $selectedOrder['customer_phone'], (int) $selectedOrder['id'])) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm rounded-pill"><i class="bi bi-whatsapp me-1"></i>Chamar cliente</a><?php endif; ?>
                        </div>
                        <div class="p-4 border-bottom">
                            <h3 class="h6 text-uppercase text-secondary">Dados do velório</h3>
                            <dl class="row small mb-0 order-dl">
                                <dt class="col-5">Homenageado(a)</dt><dd class="col-7"><?= e($selectedOrder['honoree_name']) ?></dd>
                                <dt class="col-5">Cidade</dt><dd class="col-7"><?= e($selectedOrder['city']) ?>/<?= e($selectedOrder['state']) ?></dd>
                                <dt class="col-5">Local</dt><dd class="col-7"><?= e($selectedOrder['delivery_place']) ?></dd>
                                <dt class="col-5">Entrega</dt><dd class="col-7"><?= $selectedOrder['delivery_date'] ? date('d/m/Y', strtotime($selectedOrder['delivery_date'])) : '—' ?><?= $selectedOrder['delivery_time'] ? ' às ' . substr((string) $selectedOrder['delivery_time'], 0, 5) : '' ?></dd>
                            </dl>
                            <?php if ($selectedOrder['ribbon_message']): ?><div class="mt-3 p-3 bg-light rounded-3 small"><strong>Faixa:</strong><br><?= nl2br(e($selectedOrder['ribbon_message'])) ?></div><?php endif; ?>
                            <?php if ($selectedOrder['notes']): ?><div class="mt-2 small"><strong>Observações:</strong><br><?= nl2br(e($selectedOrder['notes'])) ?></div><?php endif; ?>
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
                            <input type="hidden" name="action" value="update_order"><input type="hidden" name="order_id" value="<?= (int) $selectedOrder['id'] ?>">
                            <div class="mb-3"><label class="form-label fw-semibold">Frete</label><div class="input-group"><span class="input-group-text">R$</span><input type="number" name="shipping_fee" class="form-control" min="0" step="0.01" placeholder="A confirmar" value="<?= $selectedOrder['shipping_fee'] !== null ? e(number_format((float) $selectedOrder['shipping_fee'], 2, '.', '')) : '' ?>"></div><div class="form-text">Deixe vazio enquanto o valor ainda não estiver definido.</div></div>
                            <div class="mb-3"><label class="form-label fw-semibold">Status do pedido</label><select name="status" class="form-select"><?php foreach (orderStatusOptions() as $value => $label): ?><option value="<?= e($value) ?>" <?= $selectedOrder['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                            <button class="btn btn-brand w-100 rounded-3" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Atualizar pedido</button>
                            <div class="small text-secondary mt-3">Ao fechar a venda, altere para <strong>Em preparação</strong>. Depois use <strong>Em entrega</strong> e, por fim, <strong>Entregue</strong>.</div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="produtos" class="scroll-margin-top mb-5">
        <div class="row g-4 align-items-start">
            <div class="col-xl-4">
                <div class="admin-card p-4 sticky-xl-top" style="top:95px">
                    <h2 class="h3 mb-4"><?= $editProduct ? 'Editar produto' : 'Novo produto' ?></h2>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
                        <div class="mb-3"><label class="form-label fw-semibold">Nome</label><input type="text" name="name" class="form-control" required maxlength="160" value="<?= e($editProduct['name'] ?? '') ?>"></div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Categoria de coroa</label>
                            <select name="category_id" class="form-select">
                                <option value="">Sem categoria de coroas</option>
                                <?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editProduct['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?><?= (int) $category['active'] ? '' : ' (oculta)' ?></option><?php endforeach; ?>
                            </select>
                            <div class="form-text"><a href="admin-categorias.php">Administrar categorias</a></div>
                        </div>
                        <div class="mb-3"><label class="form-label fw-semibold">Descrição</label><textarea name="description" class="form-control" rows="4"><?= e($editProduct['description'] ?? '') ?></textarea></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Preço</label><input type="number" name="price" class="form-control" min="0" step="0.01" required value="<?= e(isset($editProduct['price']) ? (string) $editProduct['price'] : '') ?>"></div>
                        <div class="mb-3"><label class="form-label fw-semibold">Imagem</label><input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG ou WEBP. Máximo 4 MB.</div></div>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="active" id="active" <?= !isset($editProduct['active']) || (int) $editProduct['active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="active">Produto ativo</label></div>
                        <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="featured" id="featured" <?= (int) ($editProduct['featured'] ?? 0) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="featured">Destaque na home</label></div>
                        <button class="btn btn-brand w-100" type="submit"><i class="bi bi-check2 me-1"></i>Salvar produto</button>
                        <?php if ($editProduct): ?><a href="admin.php#produtos" class="btn btn-light border rounded-pill w-100 mt-2">Cancelar edição</a><?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="admin-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h3 mb-0">Produtos</h2><span class="badge text-bg-light border"><?= count($products) ?> itens</span></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0"><thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th class="text-end">Ações</th></tr></thead><tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><strong><?= e($product['name']) ?></strong></td>
                                <td><span class="small"><?= e(productCategoryName($product)) ?></span></td>
                                <td class="text-nowrap"><?= money((float) $product['price']) ?></td>
                                <td><span class="badge <?= (int) $product['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $product['active'] ? 'Ativo' : 'Oculto' ?></span></td>
                                <td class="text-end text-nowrap"><a href="admin.php?edit=<?= (int) $product['id'] ?>#produtos" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline" onsubmit="return confirm('Excluir este produto?');"><?= csrfField() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><button class="btn btn-sm btn-light border text-danger" type="submit"><i class="bi bi-trash"></i></button></form></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="seguranca" class="scroll-margin-top">
        <div class="admin-card p-4">
            <h2 class="h3 mb-3">Segurança</h2>
            <form method="post" class="row g-3">
                <?= csrfField() ?><input type="hidden" name="action" value="change_password">
                <div class="col-md-5"><label class="form-label fw-semibold">Senha atual</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="col-md-5"><label class="form-label fw-semibold">Nova senha</label><input type="password" name="new_password" class="form-control" minlength="8" required></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-brand w-100" type="submit">Alterar</button></div>
            </form>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
