<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_category') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $slug = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name);
            $sortOrder = (int) ($_POST['sort_order'] ?? 0);
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
                db()->prepare('UPDATE categories SET name = ?, slug = ?, sort_order = ?, active = ? WHERE id = ?')->execute([$name, $slug, $sortOrder, $active, $id]);
                db()->prepare('UPDATE products SET category = ? WHERE category_id = ?')->execute([$name, $id]);
                appLog('category.update', ['category_id' => $id, 'name' => $name, 'slug' => $slug, 'active' => $active]);
                $_SESSION['admin_flash'] = 'Categoria atualizada com sucesso.';
            } else {
                db()->prepare('INSERT INTO categories (name, slug, sort_order, active) VALUES (?, ?, ?, ?)')->execute([$name, $slug, $sortOrder, $active]);
                $id = (int) db()->lastInsertId();
                appLog('category.create', ['category_id' => $id, 'name' => $name, 'slug' => $slug]);
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
        appLog('category.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$editCategory = null;
if (!empty($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editCategory = $stmt->fetch() ?: null;
}

$categories = db()->query('SELECT c.*, COUNT(p.id) AS products_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.sort_order ASC, c.name ASC')->fetchAll();

$adminPage = 'categorias';
$adminTitle = 'Categorias';
$adminSubtitle = 'Organize as coroas e controle o que aparece no menu do site.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-4 align-items-start">
    <div class="col-xl-4">
        <div class="admin-card p-4 sticky-xl-top" style="top:24px">
            <h2 class="h4 mb-4"><?= $editCategory ? 'Editar categoria' : 'Nova categoria' ?></h2>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="id" value="<?= (int) ($editCategory['id'] ?? 0) ?>">
                <div class="mb-3"><label class="form-label fw-semibold">Nome</label><input type="text" name="name" class="form-control" maxlength="120" required value="<?= e($editCategory['name'] ?? '') ?>" placeholder="Ex.: Coroa de Flores Luxo"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Slug</label><input type="text" name="slug" class="form-control" maxlength="140" value="<?= e($editCategory['slug'] ?? '') ?>" placeholder="Gerado automaticamente se vazio"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Ordem no menu</label><input type="number" name="sort_order" class="form-control" value="<?= e(isset($editCategory['sort_order']) ? (string) $editCategory['sort_order'] : '0') ?>"></div>
                <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="active" id="categoryActive" <?= !isset($editCategory['active']) || (int) $editCategory['active'] === 1 ? 'checked' : '' ?>><label class="form-check-label" for="categoryActive">Exibir no site</label></div>
                <button class="btn btn-brand w-100" type="submit"><i class="bi bi-check2 me-1"></i>Salvar categoria</button>
                <?php if ($editCategory): ?><a href="admin-categorias.php" class="btn btn-light border w-100 mt-2">Cancelar edição</a><?php endif; ?>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="admin-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h4 mb-1">Categorias cadastradas</h2><div class="small text-secondary">Estas categorias alimentam o menu “Coroas”.</div></div><span class="badge text-bg-light border"><?= count($categories) ?> categorias</span></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Categoria</th><th>Produtos</th><th>Ordem</th><th>Status</th><th class="text-end">Ações</th></tr></thead><tbody>
            <?php foreach ($categories as $category): ?><tr><td><strong><?= e($category['name']) ?></strong><div class="small text-secondary"><?= e($category['slug']) ?></div></td><td><?= (int) $category['products_count'] ?></td><td><?= (int) $category['sort_order'] ?></td><td><span class="badge <?= (int) $category['active'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $category['active'] ? 'Ativa' : 'Oculta' ?></span></td><td class="text-end text-nowrap"><a href="admin-categorias.php?edit=<?= (int) $category['id'] ?>" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a> <form method="post" class="d-inline" onsubmit="return confirm('Excluir esta categoria?');"><?= csrfField() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int) $category['id'] ?>"><button type="submit" class="btn btn-sm btn-light border text-danger" <?= (int) $category['products_count'] > 0 ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
