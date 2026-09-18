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
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

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

    if (
        $currentImage &&
        str_starts_with($currentImage, 'uploads/') &&
        is_file(__DIR__ . '/' . $currentImage)
    ) {
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
            $priceInput = str_replace(['.', ','], ['', '.'], trim((string) ($_POST['price'] ?? '0')));
            if (preg_match('/^\d+\.\d{3}\.\d{2}$/', trim((string) ($_POST['price'] ?? '')))) {
                $priceInput = str_replace('.', '', trim((string) $_POST['price']));
            }
            $price = (float) $priceInput;
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
                db()->prepare(
                    'UPDATE products
                     SET name=?, category=?, category_id=?, description=?, price=?, image=?, active=?, featured=?
                     WHERE id=?'
                )->execute([
                    $name,
                    $categoryName,
                    $categoryId ?: null,
                    $description,
                    $price,
                    $image,
                    $active,
                    $featured,
                    $id,
                ]);

                appLog('product.update', ['product_id' => $id, 'name' => $name]);
                $_SESSION['admin_flash'] = 'Produto atualizado com sucesso.';
            } else {
                db()->prepare(
                    'INSERT INTO products (name, category, category_id, description, price, image, active, featured)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $name,
                    $categoryName,
                    $categoryId ?: null,
                    $description,
                    $price,
                    $image,
                    $active,
                    $featured,
                ]);

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

$categories = crownCategories(false);
$products = db()->query(
    'SELECT p.*, c.name AS category_name
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC'
)->fetchAll();

$productModalData = [];
foreach ($products as $product) {
    $productModalData[(string) $product['id']] = [
        'id' => (int) $product['id'],
        'name' => (string) $product['name'],
        'category_id' => (int) ($product['category_id'] ?? 0),
        'description' => (string) ($product['description'] ?? ''),
        'price' => number_format((float) $product['price'], 2, '.', ''),
        'image' => productImage($product['image']),
        'has_image' => !empty($product['image']),
        'active' => (int) $product['active'] === 1,
        'featured' => (int) $product['featured'] === 1,
    ];
}

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

        <button
            type="button"
            class="btn btn-brand px-4"
            data-bs-toggle="modal"
            data-bs-target="#productModal"
            data-product-new
        >
            <i class="bi bi-plus-lg me-1"></i>Novo produto
        </button>
    </div>

    <?php if (!$products): ?>
        <div class="admin-empty-state">
            <span><i class="bi bi-flower1"></i></span>
            <h3 class="h5 mb-2">Nenhum produto cadastrado</h3>
            <p class="text-secondary mb-3">Cadastre a primeira homenagem para começar a montar o catálogo.</p>
            <button
                type="button"
                class="btn btn-brand"
                data-bs-toggle="modal"
                data-bs-target="#productModal"
                data-product-new
            >
                <i class="bi bi-plus-lg me-1"></i>Criar produto
            </button>
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
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light border js-edit-product"
                                    data-product-id="<?= (int) $product['id'] ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#productModal"
                                    title="Editar produto"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>

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

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content admin-modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="eyebrow">Catálogo</span>
                    <h2 class="modal-title h4 mt-2" id="productModalLabel">Novo produto</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form method="post" enctype="multipart/form-data" id="productForm">
                <div class="modal-body pt-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" id="productId" value="0">

                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="mb-3">
                                <label for="productName" class="form-label fw-semibold">Nome</label>
                                <input
                                    type="text"
                                    name="name"
                                    id="productName"
                                    class="form-control"
                                    required
                                    maxlength="160"
                                    placeholder="Ex.: Coroa Serenidade"
                                >
                            </div>

                            <div class="mb-3">
                                <label for="productCategory" class="form-label fw-semibold">Categoria</label>
                                <select name="category_id" id="productCategory" class="form-select">
                                    <option value="0">Sem categoria específica</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>">
                                            <?= e($category['name']) ?><?= !(int) $category['active'] ? ' (oculta)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="productDescription" class="form-label fw-semibold">Descrição</label>
                                <textarea
                                    name="description"
                                    id="productDescription"
                                    class="form-control"
                                    rows="5"
                                    placeholder="Descreva o produto de forma breve e acolhedora."
                                ></textarea>
                            </div>

                            <div class="mb-0">
                                <label for="productPrice" class="form-label fw-semibold">Preço</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input
                                        type="number"
                                        name="price"
                                        id="productPrice"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        required
                                        placeholder="0,00"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="product-image-field">
                                <label for="productImage" class="form-label fw-semibold">Imagem</label>

                                <div class="product-image-preview" id="productImagePreview">
                                    <div class="product-image-placeholder" id="productImagePlaceholder">
                                        <i class="bi bi-image"></i>
                                        <span>Prévia da imagem</span>
                                    </div>
                                    <img src="" alt="Prévia do produto" id="productPreviewImage" hidden>
                                </div>

                                <input
                                    type="file"
                                    name="image"
                                    id="productImage"
                                    class="form-control mt-3"
                                    accept="image/jpeg,image/png,image/webp"
                                >
                                <div class="form-text">JPG, PNG ou WEBP. Máximo 4 MB.</div>
                            </div>

                            <div class="product-options-card mt-3">
                                <div class="product-option-row">
                                    <div>
                                        <strong>Produto ativo</strong>
                                        <small>Disponível para compra no site.</small>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="active" id="productActive" checked>
                                    </div>
                                </div>

                                <div class="product-option-row">
                                    <div>
                                        <strong>Destaque na home</strong>
                                        <small>Exibir entre as homenagens em destaque.</small>
                                    </div>
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" name="featured" id="productFeatured">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand px-4" type="submit">
                        <i class="bi bi-check2 me-1"></i>Salvar produto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const products = <?= json_encode(
        $productModalData,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;

    const form = document.getElementById('productForm');
    const title = document.getElementById('productModalLabel');
    const idField = document.getElementById('productId');
    const nameField = document.getElementById('productName');
    const categoryField = document.getElementById('productCategory');
    const descriptionField = document.getElementById('productDescription');
    const priceField = document.getElementById('productPrice');
    const imageField = document.getElementById('productImage');
    const activeField = document.getElementById('productActive');
    const featuredField = document.getElementById('productFeatured');
    const previewImage = document.getElementById('productPreviewImage');
    const previewPlaceholder = document.getElementById('productImagePlaceholder');
    const modal = document.getElementById('productModal');

    let objectUrl = null;

    function clearObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function setPreview(src) {
        clearObjectUrl();

        if (src) {
            previewImage.src = src;
            previewImage.hidden = false;
            previewPlaceholder.hidden = true;
        } else {
            previewImage.removeAttribute('src');
            previewImage.hidden = true;
            previewPlaceholder.hidden = false;
        }
    }

    function prepareNewProduct() {
        title.textContent = 'Novo produto';
        form.reset();
        idField.value = '0';
        categoryField.value = '0';
        activeField.checked = true;
        featuredField.checked = false;
        imageField.value = '';
        setPreview('');
        window.setTimeout(() => nameField.focus(), 180);
    }

    document.querySelectorAll('[data-product-new]').forEach((button) => {
        button.addEventListener('click', prepareNewProduct);
    });

    document.querySelectorAll('.js-edit-product').forEach((button) => {
        button.addEventListener('click', () => {
            const product = products[button.dataset.productId];
            if (!product) return;

            title.textContent = 'Editar produto';
            form.reset();
            idField.value = product.id;
            nameField.value = product.name;
            categoryField.value = String(product.category_id || 0);
            descriptionField.value = product.description || '';
            priceField.value = product.price;
            activeField.checked = !!product.active;
            featuredField.checked = !!product.featured;
            imageField.value = '';
            setPreview(product.has_image ? product.image : '');
            window.setTimeout(() => nameField.focus(), 180);
        });
    });

    imageField.addEventListener('change', () => {
        const file = imageField.files && imageField.files[0];
        if (!file) return;

        clearObjectUrl();
        objectUrl = URL.createObjectURL(file);
        previewImage.src = objectUrl;
        previewImage.hidden = false;
        previewPlaceholder.hidden = true;
    });

    if (modal) {
        modal.addEventListener('hidden.bs.modal', () => {
            clearObjectUrl();
            form.reset();
            idField.value = '0';
            activeField.checked = true;
            featuredField.checked = false;
            setPreview('');
        });
    }
})();
</script>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
