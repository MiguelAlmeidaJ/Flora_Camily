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
            $rawIds = trim((string) ($_POST['ids'] ?? ''));
            $ids = array_values(array_unique(array_filter(
                array_map('intval', explode(',', $rawIds)),
                static fn (int $id): bool => $id > 0
            )));

            $existingIds = array_map(
                'intval',
                db()->query('SELECT id FROM categories')->fetchAll(PDO::FETCH_COLUMN)
            );

            sort($existingIds);
            $submittedIds = $ids;
            sort($submittedIds);

            if (!$ids || $submittedIds !== $existingIds) {
                throw new RuntimeException('A lista de categorias mudou. Atualize a página e tente novamente.');
            }

            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE categories SET sort_order = ? WHERE id = ?');
            foreach ($ids as $index => $id) {
                $stmt->execute([($index + 1) * 10, $id]);
            }

            $pdo->commit();
            appLog('category.reorder', ['category_ids' => $ids]);

            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'save_category') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name);
            $active = isset($_POST['active']) ? 1 : 0;

            if ($name === '' || $slug === '') {
                throw new RuntimeException('Informe um nome válido para a categoria.');
            }

            $check = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1');
            $check->execute([$slug, $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Já existe uma categoria com este slug.');
            }

            if ($id > 0) {
                $exists = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Categoria não encontrada.');
                }

                db()->prepare('UPDATE categories SET name = ?, slug = ?, active = ? WHERE id = ?')
                    ->execute([$name, $slug, $active, $id]);
                db()->prepare('UPDATE products SET category = ? WHERE category_id = ?')
                    ->execute([$name, $id]);

                appLog('category.update', [
                    'category_id' => $id,
                    'name' => $name,
                    'slug' => $slug,
                    'active' => $active,
                ]);
                $_SESSION['admin_flash'] = 'Categoria atualizada com sucesso.';
            } else {
                $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM categories')->fetchColumn();

                db()->prepare('INSERT INTO categories (name, slug, sort_order, active) VALUES (?, ?, ?, ?)')
                    ->execute([$name, $slug, $sortOrder, $active]);

                $id = (int) db()->lastInsertId();
                appLog('category.create', [
                    'category_id' => $id,
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

$categories = db()->query(
    'SELECT c.*, COUNT(p.id) AS products_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id
     ORDER BY c.sort_order ASC, c.name ASC'
)->fetchAll();

$adminPage = 'categorias';
$adminTitle = 'Categorias';
$adminSubtitle = 'Organize as coroas e controle o que aparece no menu do site.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-info rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<div class="admin-card p-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h2 class="h4 mb-0">Categorias cadastradas</h2>
                <span class="badge text-bg-light border rounded-pill"><?= count($categories) ?></span>
            </div>
            <p class="small text-secondary mb-0">
                Arraste as categorias para definir a ordem exibida no menu “Coroas”.
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

    <?php if (!$categories): ?>
        <div class="admin-empty-state">
            <span><i class="bi bi-tags"></i></span>
            <h3 class="h5 mb-2">Nenhuma categoria cadastrada</h3>
            <p class="text-secondary mb-3">Crie a primeira categoria para começar a organizar as coroas.</p>
            <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#categoryModal" data-category-new>
                <i class="bi bi-plus-lg me-1"></i>Criar categoria
            </button>
        </div>
    <?php else: ?>
        <div class="category-order-hint mb-3">
            <i class="bi bi-grip-vertical"></i>
            <span>Segure o ícone e arraste para cima ou para baixo. A ordem é salva automaticamente.</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 admin-category-table">
                <thead>
                    <tr>
                        <th class="category-drag-column" aria-label="Ordenar"></th>
                        <th>Categoria</th>
                        <th class="text-center">Produtos</th>
                        <th>Status</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody id="categorySortable">
                    <?php foreach ($categories as $category): ?>
                        <tr data-category-id="<?= (int) $category['id'] ?>">
                            <td class="category-drag-column">
                                <button type="button" class="category-drag-handle" aria-label="Arrastar categoria" title="Arraste para reordenar">
                                    <i class="bi bi-grip-vertical"></i>
                                </button>
                            </td>
                            <td>
                                <div class="category-name"><?= e($category['name']) ?></div>
                                <div class="small text-secondary mt-1"><?= e($category['slug']) ?></div>
                            </td>
                            <td class="text-center">
                                <span class="category-products-count"><?= (int) $category['products_count'] ?></span>
                            </td>
                            <td>
                                <span class="badge rounded-pill <?= (int) $category['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                    <?= (int) $category['active'] ? 'Ativa' : 'Oculta' ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light border js-edit-category"
                                    data-bs-toggle="modal"
                                    data-bs-target="#categoryModal"
                                    data-id="<?= (int) $category['id'] ?>"
                                    data-name="<?= e($category['name']) ?>"
                                    data-slug="<?= e($category['slug']) ?>"
                                    data-active="<?= (int) $category['active'] ?>"
                                    title="Editar categoria"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>

                                <form method="post" class="d-inline" onsubmit="return confirm('Excluir esta categoria?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-light border text-danger"
                                        <?= (int) $category['products_count'] > 0 ? 'disabled' : '' ?>
                                        title="<?= (int) $category['products_count'] > 0 ? 'Mova os produtos antes de excluir' : 'Excluir categoria' ?>"
                                    >
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
                            placeholder="Ex.: Coroa de Flores Luxo"
                        >
                    </div>

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
                        <div class="form-text">Usado na URL e internamente pelo catálogo.</div>
                    </div>

                    <div class="category-visibility-option">
                        <div>
                            <strong>Exibir no site</strong>
                            <small>Quando desativada, a categoria deixa de aparecer no menu “Coroas”.</small>
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
    const activeField = document.getElementById('categoryActive');
    const title = document.getElementById('categoryModalLabel');
    const toast = document.getElementById('categorySaveToast');

    function prepareNewCategory() {
        title.textContent = 'Nova categoria';
        form.reset();
        idField.value = '0';
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
            window.setTimeout(() => nameField.focus(), 180);
        });
    });

    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            form.reset();
            idField.value = '0';
            activeField.checked = true;
        });
    }

    const sortableElement = document.getElementById('categorySortable');
    if (!sortableElement || typeof Sortable === 'undefined') return;

    const csrfToken = <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    let previousOrder = [];

    function currentOrder() {
        return Array.from(sortableElement.querySelectorAll('[data-category-id]'))
            .map((row) => row.dataset.categoryId);
    }

    function showToast(message, error = false) {
        if (!toast) return;
        toast.querySelector('span').textContent = message;
        toast.classList.toggle('is-error', error);
        toast.classList.add('show');
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 2200);
    }

    new Sortable(sortableElement, {
        animation: 180,
        handle: '.category-drag-handle',
        ghostClass: 'category-sort-ghost',
        chosenClass: 'category-sort-chosen',
        dragClass: 'category-sort-drag',
        onStart: function () {
            previousOrder = currentOrder();
        },
        onEnd: async function () {
            const ids = currentOrder();

            if (ids.join(',') === previousOrder.join(',')) return;

            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);
            body.set('action', 'reorder_categories');
            body.set('ids', ids.join(','));

            sortableElement.classList.add('is-saving');

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
                const rows = new Map(
                    Array.from(sortableElement.querySelectorAll('[data-category-id]'))
                        .map((row) => [row.dataset.categoryId, row])
                );

                previousOrder.forEach((id) => {
                    if (rows.has(id)) sortableElement.appendChild(rows.get(id));
                });

                showToast(error.message || 'Erro ao salvar a ordem', true);
            } finally {
                sortableElement.classList.remove('is-saving');
            }
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
