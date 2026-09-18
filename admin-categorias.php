<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'reorder_categories') {
            $parentRaw = trim((string) ($_POST['parent_id'] ?? ''));
            $parentId = $parentRaw === '' ? null : (int) $parentRaw;
            $rawIds = trim((string) ($_POST['ids'] ?? ''));

            $ids = array_values(array_unique(array_filter(
                array_map('intval', explode(',', $rawIds)),
                static fn (int $id): bool => $id > 0
            )));

            if (categoriesHaveHierarchy()) {
                if ($parentId === null) {
                    $expectedStmt = db()->query('SELECT id FROM categories WHERE parent_id IS NULL');
                } else {
                    $expectedStmt = db()->prepare('SELECT id FROM categories WHERE parent_id = ?');
                    $expectedStmt->execute([$parentId]);
                }
            } else {
                $expectedStmt = db()->query('SELECT id FROM categories');
            }

            $expectedIds = array_map('intval', $expectedStmt->fetchAll(PDO::FETCH_COLUMN));
            $submittedIds = $ids;

            sort($expectedIds);
            sort($submittedIds);

            if ($submittedIds !== $expectedIds) {
                throw new RuntimeException('A lista de categorias mudou. Atualize a página e tente novamente.');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
            foreach ($ids as $index => $id) {
                $stmt->execute([($index + 1) * 10, $id]);
            }

            $pdo->commit();

            appLog('category.reorder', [
                'parent_id' => $parentId,
                'category_ids' => $ids,
            ]);

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'save_category') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name);
            $active = isset($_POST['active']) ? 1 : 0;
            $parentId = categoriesHaveHierarchy() ? (int) ($_POST['parent_id'] ?? 0) : 0;
            $parentId = $parentId > 0 ? $parentId : null;

            if ($name === '' || $slug === '') {
                throw new RuntimeException('Informe um nome válido para a categoria.');
            }

            if ($parentId !== null && $parentId === $id) {
                throw new RuntimeException('Uma categoria não pode ser subcategoria dela mesma.');
            }

            $check = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1');
            $check->execute([$slug, $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Já existe uma categoria com este slug.');
            }

            if ($parentId !== null) {
                $parentStmt = db()->prepare(
                    categoriesHaveHierarchy()
                        ? 'SELECT id, parent_id FROM categories WHERE id = ? LIMIT 1'
                        : 'SELECT id, NULL AS parent_id FROM categories WHERE id = ? LIMIT 1'
                );
                $parentStmt->execute([$parentId]);
                $parent = $parentStmt->fetch();

                if (!$parent) {
                    throw new RuntimeException('A categoria principal selecionada não existe.');
                }

                if ($parent['parent_id'] !== null) {
                    throw new RuntimeException('Escolha uma categoria principal, não outra subcategoria.');
                }
            }

            if ($id > 0) {
                $exists = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
                $exists->execute([$id]);

                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Categoria não encontrada.');
                }

                if (categoriesHaveHierarchy() && $parentId !== null) {
                    $childrenStmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
                    $childrenStmt->execute([$id]);
                    if ((int) $childrenStmt->fetchColumn() > 0) {
                        throw new RuntimeException('Uma categoria com subcategorias não pode virar subcategoria.');
                    }
                }

                if (categoriesHaveHierarchy()) {
                    db()->prepare('UPDATE categories SET name = ?, slug = ?, parent_id = ?, active = ? WHERE id = ?')
                        ->execute([$name, $slug, $parentId, $active, $id]);
                } else {
                    db()->prepare('UPDATE categories SET name = ?, slug = ?, active = ? WHERE id = ?')
                        ->execute([$name, $slug, $active, $id]);
                }

                db()->prepare('UPDATE products SET category = ? WHERE category_id = ?')
                    ->execute([$name, $id]);

                appLog('category.update', [
                    'category_id' => $id,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'active' => $active,
                ]);

                $_SESSION['admin_flash'] = 'Categoria atualizada com sucesso.';
            } else {
                if (categoriesHaveHierarchy()) {
                    if ($parentId === null) {
                        $sortStmt = db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories WHERE parent_id IS NULL');
                    } else {
                        $sortStmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories WHERE parent_id = ?');
                        $sortStmt->execute([$parentId]);
                    }
                    $sortOrder = (int) $sortStmt->fetchColumn();

                    db()->prepare(
                        'INSERT INTO categories (name, slug, parent_id, sort_order, active) VALUES (?, ?, ?, ?, ?)'
                    )->execute([$name, $slug, $parentId, $sortOrder, $active]);
                } else {
                    $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories')->fetchColumn();
                    db()->prepare(
                        'INSERT INTO categories (name, slug, sort_order, active) VALUES (?, ?, ?, ?)'
                    )->execute([$name, $slug, $sortOrder, $active]);
                }

                $id = (int) db()->lastInsertId();

                appLog('category.create', [
                    'category_id' => $id,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                ]);

                $_SESSION['admin_flash'] = 'Categoria criada com sucesso.';
            }

            redirect('admin-categorias.php');
        }

        if ($action === 'delete_category') {
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = db()->prepare('SELECT name FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();

            if ($name === false) {
                throw new RuntimeException('Categoria não encontrada.');
            }

            $countStmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $countStmt->execute([$id]);
            if ((int) $countStmt->fetchColumn() > 0) {
                throw new RuntimeException('Esta categoria está vinculada a produtos. Mova os produtos antes de excluí-la.');
            }

            if (categoriesHaveHierarchy()) {
                $childrenStmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
                $childrenStmt->execute([$id]);

                if ((int) $childrenStmt->fetchColumn() > 0) {
                    throw new RuntimeException('Esta categoria possui subcategorias. Remova ou mova as subcategorias antes de excluí-la.');
                }
            }

            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            appLog('category.delete', ['category_id' => $id, 'name' => $name]);

            $_SESSION['admin_flash'] = 'Categoria excluída.';
            redirect('admin-categorias.php');
        }
    } catch (Throwable $e) {
        if ($action === 'reorder_categories') {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            appLog('category.reorder_error', ['message' => $e->getMessage()], 'error');
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        appLog('category.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$allCategories = catalogCategories(false);
$categoryTree = categoryTree(false);

$productCountsStmt = db()->query('SELECT category_id, COUNT(*) AS total FROM products WHERE category_id IS NOT NULL GROUP BY category_id');
$productCounts = [];
foreach ($productCountsStmt->fetchAll() as $row) {
    $productCounts[(int) $row['category_id']] = (int) $row['total'];
}

$rootCategories = [];
foreach ($categoryTree as $root) {
    if ((int) ($root['id'] ?? 0) > 0) {
        $rootCategories[] = $root;
    }
}

$adminPage = 'categorias';
$adminTitle = 'Categorias';
$adminSubtitle = 'Organize categorias principais e subcategorias do catálogo.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-info rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<div class="admin-card p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="h4 mb-0">Estrutura do catálogo</h2>
                <span class="badge text-bg-light border rounded-pill"><?= count($allCategories) ?></span>
            </div>
            <p class="small text-secondary mb-0">
                Categorias principais aparecem no menu. Subcategorias organizam os produtos dentro delas.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-brand px-4"
            data-bs-toggle="modal"
            data-bs-target="#categoryModal"
            data-category-new
        >
            <i class="bi bi-plus-lg me-1"></i>Nova categoria
        </button>
    </div>

    <?php if (!$rootCategories): ?>
        <div class="admin-empty-state">
            <span><i class="bi bi-diagram-3"></i></span>
            <h3 class="h5 mb-2">Nenhuma categoria cadastrada</h3>
            <p class="text-secondary mb-3">Crie uma categoria principal para começar a estruturar o catálogo.</p>
            <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#categoryModal" data-category-new>
                <i class="bi bi-plus-lg me-1"></i>Criar categoria
            </button>
        </div>
    <?php else: ?>
        <div class="category-order-hint mb-3">
            <i class="bi bi-grip-vertical"></i>
            <span>Arraste categorias principais ou subcategorias para alterar a ordem dentro do mesmo nível.</span>
        </div>

        <div class="category-tree-list" id="rootCategorySortable" data-parent-id="">
            <?php foreach ($rootCategories as $root): ?>
                <?php
                    $rootId = (int) $root['id'];
                    $children = $root['children'] ?? [];
                    $rootProducts = $productCounts[$rootId] ?? 0;
                ?>
                <section class="category-root-block" data-category-id="<?= $rootId ?>">
                    <div class="category-root-row">
                        <button type="button" class="category-drag-handle root-handle" aria-label="Arrastar categoria principal">
                            <i class="bi bi-grip-vertical"></i>
                        </button>

                        <div class="category-tree-icon"><i class="bi bi-folder2-open"></i></div>

                        <div class="category-tree-main">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <strong><?= e($root['name']) ?></strong>
                                <span class="category-level-badge">Categoria principal</span>
                                <span class="badge rounded-pill <?= (int) $root['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= (int) $root['active'] ? 'Ativa' : 'Oculta' ?>
                                </span>
                            </div>
                            <small><?= e($root['slug']) ?> · <?= count($children) ?> subcategoria<?= count($children) === 1 ? '' : 's' ?></small>
                        </div>

                        <div class="category-tree-products">
                            <span>Produtos</span>
                            <strong><?= $rootProducts ?></strong>
                        </div>

                        <div class="category-tree-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-light border js-edit-category"
                                data-bs-toggle="modal"
                                data-bs-target="#categoryModal"
                                data-id="<?= $rootId ?>"
                                data-name="<?= e($root['name']) ?>"
                                data-slug="<?= e($root['slug']) ?>"
                                data-parent-id=""
                                data-active="<?= (int) $root['active'] ?>"
                                title="Editar categoria"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>

                            <form method="post" class="d-inline" onsubmit="return confirm('Excluir esta categoria?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= $rootId ?>">
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-light border text-danger"
                                    <?= ($rootProducts > 0 || count($children) > 0) ? 'disabled' : '' ?>
                                    title="<?= count($children) > 0 ? 'Remova as subcategorias antes de excluir' : ($rootProducts > 0 ? 'Mova os produtos antes de excluir' : 'Excluir categoria') ?>"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="subcategory-list <?= !$children ? 'is-empty' : '' ?>" data-parent-id="<?= $rootId ?>">
                        <?php foreach ($children as $category): ?>
                            <?php
                                $categoryId = (int) $category['id'];
                                $productsCount = $productCounts[$categoryId] ?? 0;
                            ?>
                            <div class="subcategory-row" data-category-id="<?= $categoryId ?>">
                                <button type="button" class="category-drag-handle child-handle" aria-label="Arrastar subcategoria">
                                    <i class="bi bi-grip-vertical"></i>
                                </button>

                                <div class="subcategory-connector"></div>
                                <div class="category-tree-icon is-child"><i class="bi bi-tag"></i></div>

                                <div class="category-tree-main">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <strong><?= e($category['name']) ?></strong>
                                        <span class="badge rounded-pill <?= (int) $category['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $category['active'] ? 'Ativa' : 'Oculta' ?>
                                        </span>
                                    </div>
                                    <small><?= e($category['slug']) ?></small>
                                </div>

                                <div class="category-tree-products">
                                    <span>Produtos</span>
                                    <strong><?= $productsCount ?></strong>
                                </div>

                                <div class="category-tree-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light border js-edit-category"
                                        data-bs-toggle="modal"
                                        data-bs-target="#categoryModal"
                                        data-id="<?= $categoryId ?>"
                                        data-name="<?= e($category['name']) ?>"
                                        data-slug="<?= e($category['slug']) ?>"
                                        data-parent-id="<?= $rootId ?>"
                                        data-active="<?= (int) $category['active'] ?>"
                                        title="Editar subcategoria"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <form method="post" class="d-inline" onsubmit="return confirm('Excluir esta subcategoria?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?= $categoryId ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-light border text-danger"
                                            <?= $productsCount > 0 ? 'disabled' : '' ?>
                                            title="<?= $productsCount > 0 ? 'Mova os produtos antes de excluir' : 'Excluir subcategoria' ?>"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$children): ?>
                            <div class="subcategory-empty">
                                <i class="bi bi-arrow-return-right"></i>
                                Nenhuma subcategoria cadastrada.
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="eyebrow">Organização do catálogo</span>
                    <h2 class="modal-title h4 mt-2" id="categoryModalLabel">Nova categoria</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form method="post" id="categoryForm">
                <div class="modal-body pt-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_category">
                    <input type="hidden" name="id" id="categoryId" value="0">

                    <div class="mb-3">
                        <label for="categoryName" class="form-label fw-semibold">Nome</label>
                        <input
                            type="text"
                            name="name"
                            id="categoryName"
                            class="form-control"
                            maxlength="120"
                            required
                            placeholder="Ex.: Coroas"
                        >
                    </div>

                    <?php if (categoriesHaveHierarchy()): ?>
                        <div class="mb-3">
                            <label for="categoryParent" class="form-label fw-semibold">Categoria principal</label>
                            <select name="parent_id" id="categoryParent" class="form-select">
                                <option value="0">Nenhuma — categoria principal</option>
                                <?php foreach ($rootCategories as $root): ?>
                                    <option value="<?= (int) $root['id'] ?>"><?= e($root['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecione uma categoria principal para criar uma subcategoria.</div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="categorySlug" class="form-label fw-semibold">Slug</label>
                        <input
                            type="text"
                            name="slug"
                            id="categorySlug"
                            class="form-control"
                            maxlength="140"
                            placeholder="Gerado automaticamente se ficar vazio"
                        >
                    </div>

                    <div class="category-visibility-option">
                        <div>
                            <strong>Exibir no site</strong>
                            <small>Categorias principais aparecem no menu e subcategorias dentro delas.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="active" id="categoryActive" checked>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand px-4" type="submit">
                        <i class="bi bi-check2 me-1"></i>Salvar categoria
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="category-save-toast" id="categorySaveToast" role="status" aria-live="polite">
    <i class="bi bi-check2-circle"></i>
    <span>Ordem salva</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const idField = document.getElementById('categoryId');
    const nameField = document.getElementById('categoryName');
    const slugField = document.getElementById('categorySlug');
    const parentField = document.getElementById('categoryParent');
    const activeField = document.getElementById('categoryActive');
    const title = document.getElementById('categoryModalLabel');
    const toast = document.getElementById('categorySaveToast');
    const csrfToken = <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function prepareNewCategory() {
        title.textContent = 'Nova categoria';
        form.reset();
        idField.value = '0';
        if (parentField) parentField.value = '0';
        activeField.checked = true;
        window.setTimeout(() => nameField.focus(), 180);
    }

    document.querySelectorAll('[data-category-new]').forEach((button) => {
        button.addEventListener('click', prepareNewCategory);
    });

    document.querySelectorAll('.js-edit-category').forEach((button) => {
        button.addEventListener('click', () => {
            title.textContent = 'Editar categoria';
            idField.value = button.dataset.id || '0';
            nameField.value = button.dataset.name || '';
            slugField.value = button.dataset.slug || '';
            activeField.checked = button.dataset.active === '1';

            if (parentField) {
                parentField.value = button.dataset.parentId || '0';
                Array.from(parentField.options).forEach((option) => {
                    option.disabled = option.value === button.dataset.id;
                });
            }

            window.setTimeout(() => nameField.focus(), 180);
        });
    });

    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            form.reset();
            idField.value = '0';
            activeField.checked = true;

            if (parentField) {
                parentField.value = '0';
                Array.from(parentField.options).forEach((option) => option.disabled = false);
            }
        });
    }

    function showToast(message, error = false) {
        if (!toast) return;

        toast.querySelector('span').textContent = message;
        toast.classList.toggle('is-error', error);
        toast.classList.add('show');

        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 2200);
    }

    async function saveOrder(container, parentId) {
        const ids = Array.from(container.querySelectorAll(':scope > [data-category-id]'))
            .map((row) => row.dataset.categoryId);

        const body = new URLSearchParams();
        body.set('csrf_token', csrfToken);
        body.set('action', 'reorder_categories');
        body.set('parent_id', parentId || '');
        body.set('ids', ids.join(','));

        container.classList.add('is-saving');

        try {
            const response = await fetch('admin-categorias.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body.toString()
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Não foi possível salvar a ordem.');
            }

            showToast('Ordem salva');
        } catch (error) {
            showToast(error.message || 'Erro ao salvar a ordem', true);
            window.setTimeout(() => window.location.reload(), 900);
        } finally {
            container.classList.remove('is-saving');
        }
    }

    const rootContainer = document.getElementById('rootCategorySortable');
    if (rootContainer && typeof Sortable !== 'undefined') {
        new Sortable(rootContainer, {
            animation: 180,
            handle: '.root-handle',
            draggable: '.category-root-block',
            ghostClass: 'category-sort-ghost',
            chosenClass: 'category-sort-chosen',
            dragClass: 'category-sort-drag',
            onEnd: () => saveOrder(rootContainer, '')
        });
    }

    document.querySelectorAll('.subcategory-list[data-parent-id]').forEach((container) => {
        if (typeof Sortable === 'undefined') return;

        new Sortable(container, {
            animation: 180,
            handle: '.child-handle',
            draggable: '.subcategory-row',
            ghostClass: 'category-sort-ghost',
            chosenClass: 'category-sort-chosen',
            dragClass: 'category-sort-drag',
            onEnd: () => saveOrder(container, container.dataset.parentId)
        });
    });
})();
</script>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
