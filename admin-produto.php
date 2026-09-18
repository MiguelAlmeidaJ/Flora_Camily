<?php
require __DIR__ . '/config.php';
requireAdmin();

function adminProductUploadImage(?string $currentImage = null, bool $removeCurrent = false): ?string
{
    $hasNewImage = !empty($_FILES['image']['name']);

    if (!$hasNewImage) {
        if (
            $removeCurrent &&
            $currentImage &&
            str_starts_with($currentImage, 'uploads/') &&
            is_file(__DIR__ . '/' . $currentImage)
        ) {
            @unlink(__DIR__ . '/' . $currentImage);
            return null;
        }

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

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$product = null;
$flash = '';

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if (!$product) {
        $_SESSION['admin_flash'] = 'Produto não encontrado.';
        redirect('admin-produtos.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    try {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        $priceInput = trim((string) ($_POST['price'] ?? '0'));
        if (str_contains($priceInput, ',')) {
            $priceInput = str_replace('.', '', $priceInput);
            $priceInput = str_replace(',', '.', $priceInput);
        }
        $price = (float) $priceInput;

        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        $featured = isset($_POST['featured']) ? 1 : 0;
        $removeImage = isset($_POST['remove_image']);

        if ($name === '') {
            throw new RuntimeException('Informe o nome do produto.');
        }

        if ($price < 0) {
            throw new RuntimeException('Informe um preço válido.');
        }

        $categoryName = 'Homenagens florais';

        if ($categoryId > 0) {
            $stmt = db()->prepare('SELECT name FROM categories WHERE id = ? LIMIT 1');
            $stmt->execute([$categoryId]);
            $categoryNameFound = $stmt->fetchColumn();

            if ($categoryNameFound === false) {
                throw new RuntimeException('A categoria selecionada não existe mais.');
            }

            $categoryName = (string) $categoryNameFound;
        }

        $currentImage = null;

        if ($id > 0) {
            $stmt = db()->prepare('SELECT image FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $currentImage = $stmt->fetchColumn();

            if ($currentImage === false) {
                throw new RuntimeException('Produto não encontrado.');
            }

            $currentImage = $currentImage ?: null;
        }

        $image = adminProductUploadImage($currentImage, $removeImage);

        if ($id > 0) {
            db()->prepare(
                'UPDATE products
                 SET name = ?, category = ?, category_id = ?, description = ?, price = ?, image = ?, active = ?, featured = ?
                 WHERE id = ?'
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
    } catch (Throwable $e) {
        appLog('product.error', [
            'action' => $id > 0 ? 'update' : 'create',
            'product_id' => $id ?: null,
            'message' => $e->getMessage(),
        ], 'error');

        $flash = $e->getMessage();

        $product = [
            'id' => $id,
            'name' => $_POST['name'] ?? '',
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'description' => $_POST['description'] ?? '',
            'price' => $_POST['price'] ?? '',
            'image' => $product['image'] ?? null,
            'active' => isset($_POST['active']) ? 1 : 0,
            'featured' => isset($_POST['featured']) ? 1 : 0,
        ];
    }
}

$categories = crownCategories(false);
$isEditing = !empty($product['id']);

$adminPage = 'produtos';
$adminTitle = $isEditing ? 'Editar produto' : 'Novo produto';
$adminSubtitle = $isEditing
    ? 'Atualize as informações, imagem e publicação deste item.'
    : 'Cadastre uma nova homenagem no catálogo.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-danger rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="admin-product-form-page">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <a href="admin-produtos.php" class="admin-back-link">
                <i class="bi bi-arrow-left"></i>
                Voltar para produtos
            </a>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($isEditing && (int) ($product['active'] ?? 0) === 1): ?>
                <a
                    href="produto.php?id=<?= (int) $product['id'] ?>"
                    target="_blank"
                    class="btn btn-light border"
                >
                    <i class="bi bi-box-arrow-up-right me-1"></i>Ver no site
                </a>
            <?php endif; ?>

            <a href="admin-produtos.php" class="btn btn-light border">Cancelar</a>

            <button class="btn btn-brand px-4" type="submit">
                <i class="bi bi-check2 me-1"></i>
                <?= $isEditing ? 'Salvar alterações' : 'Cadastrar produto' ?>
            </button>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-8">
            <div class="admin-card p-4 mb-4">
                <div class="product-form-section-header">
                    <span class="product-form-section-icon"><i class="bi bi-info-circle"></i></span>
                    <div>
                        <h2 class="h5 mb-1">Informações principais</h2>
                        <p class="small text-secondary mb-0">Dados básicos exibidos no catálogo e na página do produto.</p>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-12">
                        <label for="productName" class="form-label fw-semibold">Nome do produto</label>
                        <input
                            type="text"
                            name="name"
                            id="productName"
                            class="form-control"
                            maxlength="160"
                            required
                            value="<?= e((string) ($product['name'] ?? '')) ?>"
                            placeholder="Ex.: Coroa Serenidade"
                        >
                    </div>

                    <div class="col-md-7">
                        <label for="productCategory" class="form-label fw-semibold">Categoria</label>
                        <select name="category_id" id="productCategory" class="form-select">
                            <option value="0">Sem categoria específica</option>
                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int) $category['id'] ?>"
                                    <?= (int) ($product['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($category['name']) ?><?= !(int) $category['active'] ? ' (oculta)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">As categorias também alimentam o menu “Coroas” do site.</div>
                    </div>

                    <div class="col-md-5">
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
                                value="<?= e(isset($product['price']) && $product['price'] !== '' ? number_format((float) $product['price'], 2, '.', '') : '') ?>"
                                placeholder="0,00"
                            >
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="productDescription" class="form-label fw-semibold">Descrição</label>
                        <textarea
                            name="description"
                            id="productDescription"
                            class="form-control product-description-field"
                            rows="9"
                            placeholder="Descreva a composição, estilo, ocasião e outros detalhes importantes."
                        ><?= e((string) ($product['description'] ?? '')) ?></textarea>
                        <div class="form-text">Esse espaço já está preparado para descrições maiores conforme o cadastro evoluir.</div>
                    </div>
                </div>
            </div>

            <div class="admin-card p-4">
                <div class="product-form-section-header">
                    <span class="product-form-section-icon"><i class="bi bi-card-image"></i></span>
                    <div>
                        <h2 class="h5 mb-1">Imagem do produto</h2>
                        <p class="small text-secondary mb-0">Use uma foto clara e com boa proporção para o catálogo.</p>
                    </div>
                </div>

                <div class="row g-4 align-items-start mt-1">
                    <div class="col-md-7">
                        <div class="product-image-preview product-page-image-preview" id="productImagePreview">
                            <div
                                class="product-image-placeholder"
                                id="productImagePlaceholder"
                                <?= !empty($product['image']) ? 'hidden' : '' ?>
                            >
                                <i class="bi bi-image"></i>
                                <span>Selecione uma imagem para visualizar a prévia</span>
                            </div>

                            <img
                                src="<?= !empty($product['image']) ? e(productImage($product['image'])) : '' ?>"
                                alt="Prévia do produto"
                                id="productPreviewImage"
                                <?= empty($product['image']) ? 'hidden' : '' ?>
                            >
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label for="productImage" class="form-label fw-semibold">Arquivo</label>
                        <input
                            type="file"
                            name="image"
                            id="productImage"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp"
                        >
                        <div class="form-text mb-3">JPG, PNG ou WEBP. Máximo 4 MB.</div>

                        <?php if (!empty($product['image'])): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_image" id="removeProductImage">
                                <label class="form-check-label text-danger" for="removeProductImage">
                                    Remover imagem atual
                                </label>
                            </div>
                        <?php endif; ?>

                        <div class="product-media-tip mt-4">
                            <i class="bi bi-lightbulb"></i>
                            <div>
                                <strong>Dica</strong>
                                <span>Prefira imagens do produto com fundo limpo e enquadramento central.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="admin-card product-publish-card sticky-xl-top">
                <div class="product-publish-head">
                    <div class="product-publish-head-icon"><i class="bi bi-eye"></i></div>
                    <div class="flex-grow-1">
                        <span class="product-publish-kicker">Visibilidade</span>
                        <h2 class="h5 mb-0">Publicação</h2>
                    </div>
                    <span class="product-publish-state <?= !isset($product['active']) || (int) $product['active'] === 1 ? 'is-active' : '' ?>" id="productPublishState">
                        <?= !isset($product['active']) || (int) $product['active'] === 1 ? 'Publicado' : 'Oculto' ?>
                    </span>
                </div>

                <div class="product-publish-body">
                    <div class="product-publish-options">
                        <label class="product-publish-option" for="productActive">
                            <strong>Produto ativo</strong>
                            <span class="form-check form-switch m-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="active"
                                    id="productActive"
                                    <?= !isset($product['active']) || (int) $product['active'] === 1 ? 'checked' : '' ?>
                                >
                            </span>
                        </label>

                        <label class="product-publish-option" for="productFeatured">
                            <strong>Destaque na home</strong>
                            <span class="form-check form-switch m-0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="featured"
                                    id="productFeatured"
                                    <?= (int) ($product['featured'] ?? 0) === 1 ? 'checked' : '' ?>
                                >
                            </span>
                        </label>
                    </div>

                <?php if ($isEditing): ?>
                    <div class="product-meta-box mt-4">
                        <div>
                            <span>ID do produto</span>
                            <strong>#<?= (int) $product['id'] ?></strong>
                        </div>
                        <?php if (!empty($product['created_at'])): ?>
                            <div>
                                <span>Criado em</span>
                                <strong><?= date('d/m/Y H:i', strtotime((string) $product['created_at'])) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2 mt-4">
                    <button class="btn btn-brand" type="submit">
                        <i class="bi bi-check2 me-1"></i>
                        <?= $isEditing ? 'Salvar alterações' : 'Cadastrar produto' ?>
                    </button>
                    <a href="admin-produtos.php" class="btn btn-light border">Cancelar</a>
                </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const input = document.getElementById('productImage');
    const image = document.getElementById('productPreviewImage');
    const placeholder = document.getElementById('productImagePlaceholder');
    const removeCheckbox = document.getElementById('removeProductImage');
    const activeCheckbox = document.getElementById('productActive');
    const publishState = document.getElementById('productPublishState');
    let objectUrl = null;

    function updatePublishState() {
        if (!activeCheckbox || !publishState) return;
        const active = activeCheckbox.checked;
        publishState.textContent = active ? 'Publicado' : 'Oculto';
        publishState.classList.toggle('is-active', active);
    }

    if (activeCheckbox) {
        activeCheckbox.addEventListener('change', updatePublishState);
        updatePublishState();
    }

    if (!input || !image || !placeholder) return;

    function clearObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];

        if (!file) return;

        clearObjectUrl();
        objectUrl = URL.createObjectURL(file);

        image.src = objectUrl;
        image.hidden = false;
        placeholder.hidden = true;

        if (removeCheckbox) {
            removeCheckbox.checked = false;
        }
    });

    if (removeCheckbox) {
        removeCheckbox.addEventListener('change', function () {
            if (removeCheckbox.checked) {
                image.hidden = true;
                placeholder.hidden = false;
                input.value = '';
                clearObjectUrl();
            } else if (image.getAttribute('src')) {
                image.hidden = false;
                placeholder.hidden = true;
            }
        });
    }

    window.addEventListener('beforeunload', clearObjectUrl);
})();
</script>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
