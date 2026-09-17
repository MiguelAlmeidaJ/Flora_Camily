<?php
$pageTitle = 'Homenagens | Flora Camily';
require __DIR__ . '/includes/header.php';

$category = trim((string) ($_GET['categoria'] ?? ''));

$categories = db()->query("SELECT DISTINCT category FROM products WHERE active = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

if ($category !== '') {
    $stmt = db()->prepare('SELECT * FROM products WHERE active = 1 AND category = ? ORDER BY featured DESC, created_at DESC');
    $stmt->execute([$category]);
} else {
    $stmt = db()->query('SELECT * FROM products WHERE active = 1 ORDER BY featured DESC, created_at DESC');
}
$products = $stmt->fetchAll();
?>
<section class="page-hero py-5">
    <div class="container py-lg-3">
        <span class="eyebrow">Catálogo</span>
        <h1 class="mb-2">Homenagens florais</h1>
        <p class="section-subtitle mb-0">Escolha com calma. A confirmação do pedido, prazo e entrega é feita diretamente com nossa equipe.</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if ($categories): ?>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="loja.php" class="btn btn-sm <?= $category === '' ? 'btn-brand' : 'btn-outline-brand' ?>">Todas</a>
                <?php foreach ($categories as $item): ?>
                    <a href="loja.php?categoria=<?= urlencode((string) $item) ?>" class="btn btn-sm <?= $category === $item ? 'btn-brand' : 'btn-outline-brand' ?>"><?= e((string) $item) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php foreach ($products as $product): ?>
                <?php $fallback = empty($product['image']); ?>
                <div class="col-md-6 col-xl-4">
                    <article class="product-card">
                        <a href="produto.php?id=<?= (int) $product['id'] ?>" class="text-decoration-none">
                            <img src="<?= e(productImage($product['image'])) ?>" alt="<?= e($product['name']) ?>" class="product-image <?= $fallback ? 'logo-fallback' : '' ?>">
                        </a>
                        <div class="p-4">
                            <div class="product-category mb-2"><?= e($product['category']) ?></div>
                            <h2 class="product-title mb-2"><a class="text-decoration-none" href="produto.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h2>
                            <p class="text-secondary small mb-3"><?= e(mb_strimwidth((string) $product['description'], 0, 120, '...')) ?></p>
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <span class="product-price"><?= money((float) $product['price']) ?></span>
                                <a href="produto.php?id=<?= (int) $product['id'] ?>" class="btn btn-sm btn-outline-brand">Ver detalhes</a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$products): ?>
            <div class="text-center py-5">
                <i class="bi bi-flower1 display-4 text-secondary"></i>
                <h2 class="h3 mt-3">Nenhuma homenagem encontrada</h2>
                <p class="text-secondary">Experimente visualizar todas as categorias.</p>
                <a href="loja.php" class="btn btn-brand">Ver catálogo completo</a>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
