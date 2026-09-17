<?php
require __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('loja.php');
}

$stmt = db()->prepare(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.id = ? AND p.active = 1
     LIMIT 1'
);
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Produto não encontrado | Flora Camily';
    require __DIR__ . '/includes/header.php';
    echo '<section class="py-5"><div class="container text-center py-5"><h1>Homenagem não encontrada</h1><p class="text-secondary">Este item pode não estar mais disponível.</p><a class="btn btn-brand" href="loja.php">Voltar ao catálogo</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'] . ' | Flora Camily';
require __DIR__ . '/includes/header.php';
$fallback = empty($product['image']);
?>
<section class="py-5">
    <div class="container py-lg-4">
        <div class="row g-5 align-items-start">
            <div class="col-lg-6">
                <div class="product-card p-3">
                    <img src="<?= e(productImage($product['image'])) ?>" alt="<?= e($product['name']) ?>" class="product-image rounded-4 <?= $fallback ? 'logo-fallback' : '' ?>">
                </div>
            </div>
            <div class="col-lg-6">
                <a href="loja.php" class="small text-decoration-none text-secondary"><i class="bi bi-arrow-left me-1"></i>Voltar ao catálogo</a>
                <div class="product-category mt-4 mb-2"><?= e(productCategoryName($product)) ?></div>
                <h1 class="section-title mb-3"><?= e($product['name']) ?></h1>
                <div class="product-price fs-4 mb-4"><?= money((float) $product['price']) ?></div>
                <p class="text-secondary lh-lg mb-4"><?= nl2br(e((string) $product['description'])) ?></p>

                <form action="carrinho.php" method="post" class="row g-3 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                    <div class="col-sm-4">
                        <label for="qty" class="form-label fw-semibold">Quantidade</label>
                        <input id="qty" type="number" name="qty" class="form-control" min="1" max="20" value="1">
                    </div>
                    <div class="col-sm-8">
                        <div class="product-buy-actions d-grid d-md-flex gap-2">
                            <button type="submit" name="action" value="add" class="btn btn-outline-brand btn-lg flex-fill">
                                <i class="bi bi-bag-plus me-2"></i>Adicionar ao carrinho
                            </button>
                            <button type="submit" name="action" value="buy_now" class="btn btn-brand btn-lg flex-fill">
                                <i class="bi bi-lightning-charge me-2"></i>Finalizar compra
                            </button>
                        </div>
                    </div>
                </form>

                <div class="whatsapp-note rounded-4 p-3 mt-4 small text-secondary">
                    <i class="bi bi-info-circle me-1"></i>
                    O pedido é enviado para análise da equipe. O frete é calculado manualmente e, se necessário, entraremos em contato pelo WhatsApp para ajustar os detalhes.
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
