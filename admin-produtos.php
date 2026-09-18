<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
$flashType = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);

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
            $_SESSION['admin_flash_type'] = 'success';
            redirect('admin-produtos');
        }
    } catch (Throwable $e) {
        appLog('product.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
        $flashType = 'danger';
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$featuredFilter = trim((string) ($_GET['featured'] ?? ''));

if (!in_array($statusFilter, ['', 'active', 'hidden'], true)) {
    $statusFilter = '';
}
if (!in_array($featuredFilter, ['', '1', '0'], true)) {
    $featuredFilter = '';
}

$categoryOptions = catalogCategories(false);
$selectedCategoryIds = [];

if ($categoryId > 0) {
    foreach ($categoryOptions as $category) {
        if ((int) $category['id'] === $categoryId) {
            $selectedCategoryIds = categoryIdsForSlug((string) $category['slug'], false);
            break;
        }
    }

    if (!$selectedCategoryIds) {
        $selectedCategoryIds = [$categoryId];
    }
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.category LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

if ($selectedCategoryIds) {
    $placeholders = implode(',', array_fill(0, count($selectedCategoryIds), '?'));
    $where[] = "p.category_id IN ($placeholders)";
    foreach ($selectedCategoryIds as $id) {
        $params[] = (int) $id;
    }
}

if ($statusFilter === 'active') {
    $where[] = 'p.active = 1';
} elseif ($statusFilter === 'hidden') {
    $where[] = 'p.active = 0';
}

if ($featuredFilter === '1') {
    $where[] = 'p.featured = 1';
} elseif ($featuredFilter === '0') {
    $where[] = 'p.featured = 0';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = db()->prepare(
    'SELECT COUNT(*)
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id' . $whereSql
);
$countStmt->execute($params);
$totalProducts = (int) $countStmt->fetchColumn();

$perPage = 10;
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = db()->prepare(
    'SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id' .
    $whereSql .
    ' ORDER BY p.featured DESC, p.created_at DESC
      LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$listStmt->execute($params);
$products = $listStmt->fetchAll();

$activeFilters = ($search !== '' || $categoryId > 0 || $statusFilter !== '' || $featuredFilter !== '');

$queryBase = [
    'q' => $search,
    'category_id' => $categoryId ?: '',
    'status' => $statusFilter,
    'featured' => $featuredFilter,
];
$queryBase = array_filter($queryBase, static fn ($value): bool => $value !== '' && $value !== 0);

$from = $totalProducts > 0 ? $offset + 1 : 0;
$to = min($offset + $perPage, $totalProducts);

$adminPage = 'produtos';
$adminTitle = 'Produtos';
$adminSubtitle = 'Cadastre e organize as homenagens disponíveis no site.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= e($flashType) ?> rounded-4 admin-feedback-alert">
        <i class="bi <?= $flashType === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?> me-2"></i>
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<div class="admin-card p-4">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="h4 mb-0">Catálogo</h2>
                <span class="badge text-bg-light border rounded-pill"><?= $totalProducts ?></span>
            </div>
            <p class="small text-secondary mb-0">
                <?= $activeFilters ? 'Resultados conforme os filtros selecionados.' : 'Produtos cadastrados no sistema.' ?>
            </p>
        </div>

        <a href="admin-produto" class="btn btn-brand px-4">
            <i class="bi bi-plus-lg me-1"></i>Novo produto
        </a>
    </div>

    <form method="get" action="admin-produtos" class="admin-product-filters mb-4">
        <div class="admin-product-filter-search">
            <i class="bi bi-search"></i>
            <input
                type="search"
                name="q"
                class="form-control"
                value="<?= e($search) ?>"
                placeholder="Buscar por nome, descrição ou categoria..."
                aria-label="Buscar produtos"
            >
        </div>

        <select name="category_id" class="form-select" aria-label="Filtrar por categoria">
            <option value="">Todas as categorias</option>
            <?php foreach ($categoryOptions as $category): ?>
                <option
                    value="<?= (int) $category['id'] ?>"
                    <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>
                >
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-select" aria-label="Filtrar por status">
            <option value="">Todos os status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
            <option value="hidden" <?= $statusFilter === 'hidden' ? 'selected' : '' ?>>Ocultos</option>
        </select>

        <select name="featured" class="form-select" aria-label="Filtrar por destaque">
            <option value="">Todos</option>
            <option value="1" <?= $featuredFilter === '1' ? 'selected' : '' ?>>Em destaque</option>
            <option value="0" <?= $featuredFilter === '0' ? 'selected' : '' ?>>Sem destaque</option>
        </select>

        <button type="submit" class="btn btn-brand admin-product-filter-button">
            <i class="bi bi-funnel me-1"></i>Filtrar
        </button>

        <?php if ($activeFilters): ?>
            <a href="admin-produtos" class="btn btn-light border admin-product-filter-clear" title="Limpar filtros">
                <i class="bi bi-x-lg"></i>
            </a>
        <?php endif; ?>
    </form>

    <?php if (!$products): ?>
        <div class="admin-empty-state">
            <span><i class="bi bi-flower1"></i></span>
            <h3 class="h5 mb-2"><?= $activeFilters ? 'Nenhum produto encontrado' : 'Nenhum produto cadastrado' ?></h3>
            <p class="text-secondary mb-3">
                <?= $activeFilters ? 'Tente alterar ou limpar os filtros aplicados.' : 'Cadastre a primeira homenagem para começar a montar o catálogo.' ?>
            </p>
            <?php if ($activeFilters): ?>
                <a href="admin-produtos" class="btn btn-light border">Limpar filtros</a>
            <?php else: ?>
                <a href="admin-produto" class="btn btn-brand">
                    <i class="bi bi-plus-lg me-1"></i>Criar produto
                </a>
            <?php endif; ?>
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
                                    href="admin-produto?id=<?= (int) $product['id'] ?>"
                                    class="btn btn-sm btn-light border"
                                    title="Editar produto"
                                >
                                    <i class="bi bi-pencil"></i>
                                </a>

                                <?php if ((int) $product['active'] === 1): ?>
                                    <a
                                        href="produto?id=<?= (int) $product['id'] ?>"
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

        <div class="admin-product-pagination">
            <div class="admin-product-pagination-info">
                Exibindo <strong><?= $from ?>–<?= $to ?></strong> de <strong><?= $totalProducts ?></strong> produtos
            </div>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginação de produtos">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                            $prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $page - 1)]));
                            $nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $page + 1)]));
                        ?>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="admin-produtos?<?= e($prevQuery) ?>" aria-label="Página anterior">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($page <= 3) $endPage = min($totalPages, 5);
                            if ($page >= $totalPages - 2) $startPage = max(1, $totalPages - 4);
                        ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php $pageQuery = http_build_query(array_merge($queryBase, ['page' => $i])); ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="admin-produtos?<?= e($pageQuery) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="admin-produtos?<?= e($nextQuery) ?>" aria-label="Próxima página">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
