<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'delete_product') {
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = db()->prepare('SELECT image, name FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new RuntimeException('Produto não encontrado.');
            }

            db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);

            if (
                $product['image'] &&
                str_starts_with((string) $product['image'], 'uploads/') &&
                is_file(__DIR__ . '/' . $product['image'])
            ) {
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

$products = db()->query(
    'SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC'
)->fetchAll();

$adminPage = 'produtos';
$adminTitle = 'Produtos';
$adminSubtitle = 'Cadastre e organize as homenagens disponíveis no site.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-info rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<div class="admin-card p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="h4 mb-0">Catálogo</h2>
                <span class="badge text-bg-light border rounded-pill"><?= count($products) ?></span>
            </div>
            <p class="small text-secondary mb-0">Produtos cadastrados no sistema.</p>
        </div>

        <a href="admin-produto.php" class="btn btn-brand px-4">
            <i class="bi bi-plus-lg me-1"></i>Novo produto
        </a>
    </div>

    <?php if (!$products): ?>
        <div class="admin-empty-state">
            <span><i class="bi bi-flower1"></i></span>
            <h3 class="h5 mb-2">Nenhum produto cadastrado</h3>
            <p class="text-secondary mb-3">Cadastre a primeira homenagem para começar a montar o catálogo.</p>
            <a href="admin-produto.php" class="btn btn-brand">
                <i class="bi bi-plus-lg me-1"></i>Criar produto
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 admin-products-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Preço</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <img
                                        src="<?= e(productImage($product['image'])) ?>"
                                        alt="<?= e($product['name']) ?>"
                                        class="admin-table-thumb admin-product-thumb"
                                    >
                                    <div class="min-w-0">
                                        <div class="fw-bold text-dark"><?= e($product['name']) ?></div>
                                        <?php if ((int) $product['featured'] === 1): ?>
                                            <span class="product-featured-label">
                                                <i class="bi bi-star-fill"></i>Destaque
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-secondary">
                                    <?= e($product['category_name'] ?: $product['category']) ?>
                                </span>
                            </td>
                            <td class="text-nowrap fw-semibold"><?= money((float) $product['price']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= (int) $product['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= (int) $product['active'] ? 'Ativo' : 'Oculto' ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a
                                    href="admin-produto.php?id=<?= (int) $product['id'] ?>"
                                    class="btn btn-sm btn-light border"
                                    title="Editar produto"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if ((int) $product['active'] === 1): ?>
                                    <a
                                        href="produto.php?id=<?= (int) $product['id'] ?>"
                                        class="btn btn-sm btn-light border"
                                        target="_blank"
                                        title="Visualizar no site"
                                    >
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                <?php endif; ?>

                                <form method="post" class="d-inline" onsubmit="return confirm('Excluir este produto?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                    <button class="btn btn-sm btn-light border text-danger" type="submit" title="Excluir produto">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
