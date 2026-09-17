<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

function adminUploadProductImage(?string $currentImage = null): ?string
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
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
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
            $description = trim((string) ($_POST['description'] ?? ''));
            $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $active = isset($_POST['active']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;

            if ($name === '' || $price < 0) {
                throw new RuntimeException('Preencha nome e preço corretamente.');
            }

            $categoryName = 'Homenagens florais';
            if ($categoryId > 0) {
                $stmt = db()->prepare('SELECT name FROM categories WHERE id = ?');
                $stmt->execute([$categoryId]);
                $categoryName = (string) ($stmt->fetchColumn() ?: 'Homenagens florais');
            }

            $currentImage = null;
            if ($id > 0) {
                $stmt = db()->prepare('SELECT image FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $currentImage = $stmt->fetchColumn() ?: null;
            }
            $image = adminUploadProductImage($currentImage);

            if ($id > 0) {
                db()->prepare('UPDATE products SET name=?, category=?, category_id=?, description=?, price=?, image=?, active=?, featured=? WHERE id=?')
                    ->execute([$name, $categoryName, $categoryId ?: null, $description, $price, $image, $active, $featured, $id]);
                appLog('product.update', ['product_id' => $id, 'name' => $name]);
                $_SESSION['admin_flash'] = 'Produto atualizado com sucesso.';
            } else {
                db()->prepare('INSERT INTO products (name, category, category_id, description, price, image, active, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$name, $categoryName, $categoryId ?: null, $description, $price, $image, $active, $featured]);
                $id = (int) db()->lastInsertId();
                appLog('product.create', ['product_id' => $id, 'name' => $name]);
                $_SESSION['admin_flash'] = 'Produto criado com sucesso.';
            }
            redirect('admin-produtos.php');
        }

        if ($action === 'delete_product') {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = db()->prepare('SELECT image, name FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException('Produto não encontrado.');
            }
            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            if ($product['image'] && str_starts_with((string) $product['image'], 'uploads/') && is_file(__DIR__ . '/' . $product['image'])) {
                @unlink(__DIR__ . '/' . $product['image']);
            }
            appLog('product.delete', ['product_id' => $id, 'name' => $product['name']]);
            $_SESSION['admin_flash'] = 'Produto excluído.';
            redirect('admin-produtos.php');
        }
    } catch (Throwable $e) {
        appLog('product.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$editProduct = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editProduct = $stmt->fetch() ?: null;
}

$categories = crownCategories(false);
$products = db()->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.created_at DESC')->fetchAll();

$adminPage = 'produtos';
$adminTitle = 'Produtos';
$adminSubtitle = 'Cadastre e organize as homenagens disponíveis no site.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-4 align-items-start">
    <div class="col-xl-4">
        <div class="admin-card p-4 sticky-xl-top" style="top:24px">
            <h2 class="h4 mb-4"><?= $editProduct ? 'Editar produto' : 'Novo produto' ?></h2>
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="id" value="<?= (int) ($editProduct['id'] ?? 0) ?>">
                <div class="mb-3"><label class="form-label fw-semibold">Nome</label><input type="text" name="name" class="form-control" required maxlength="160" value="<?= e($editProduct['name'] ?? '') ?>"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Categoria</label><select name="category_id" class="form-select"><option value="0">Sem categoria específica</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($editProduct['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?><?= !(int) $category['active'] ? ' (oculta)' : '' ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label fw-semibold">Descrição</label><textarea name="description" class="form-control" rows="4"><?= e($editProduct['description'] ?? '') ?></textarea></div>
                <div class="mb-3"><label class="form-label fw-semibold">Preço</label><input type="number" name="price" class="form-control" min="0" step="0.01" required value="<?= e(isset($editProduct['price']) ? (string) $editProduct['price'] : '') ?>"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Imagem</label><input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG ou WEBP. Máximo 4 MB.</div></div>
                <?php if ($editProduct && $editProduct['image']): ?><div class="mb-3"><img src="<?= e(productImage($editProduct['image'])) ?>" alt="" class="admin-product-preview"></div><?php endif; ?>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="active" id="productActive" <?= !isset($editProduct['active']) || (int) $editProduct['active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="productActive">Produto ativo</label></div>
                <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="featured" id="productFeatured" <?= (int) ($editProduct['featured'] ?? 0) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="productFeatured">Destaque na home</label></div>
                <button class="btn btn-brand w-100" type="submit"><i class="bi bi-check2 me-1"></i>Salvar produto</button>
                <?php if ($editProduct): ?><a href="admin-produtos.php" class="btn btn-light border w-100 mt-2">Cancelar edição</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Catálogo</h2><div class="small text-secondary">Produtos cadastrados no sistema</div></div><span class="badge text-bg-light border"><?= count($products) ?> itens</span></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Status</th><th class="text-end">Ações</th></tr></thead><tbody>
            <?php foreach ($products as $product): ?><tr><td><div class="d-flex align-items-center gap-3"><img src="<?= e(productImage($product['image'])) ?>" alt="" class="admin-table-thumb"><strong><?= e($product['name']) ?></strong></div></td><td><?= e($product['category_name'] ?: $product['category']) ?></td><td class="text-nowrap"><?= money((float) $product['price']) ?></td><td><span class="badge <?= (int) $product['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $product['active'] ? 'Ativo' : 'Oculto' ?></span></td><td class="text-end text-nowrap"><a href="admin-produtos.php?edit=<?= (int) $product['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline" onsubmit="return confirm('Excluir este produto?');"><?= csrfField() ?><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><button class="btn btn-sm btn-light border text-danger" type="submit"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
