<?php
$pageTitle = 'Catálogo | Flora Camily';
require __DIR__ . '/includes/header.php';

$categorySlug = trim((string) ($_GET['categoria'] ?? ''));
$categoryTree = categoryTree();

$selectedCategory = null;
$selectedParent = null;

foreach ($categoryTree as $root) {
    if ((string) $root['slug'] === $categorySlug) {
        $selectedCategory = $root;
        break;
    }

    foreach ($root['children'] ?? [] as $child) {
        if ((string) $child['slug'] === $categorySlug) {
            $selectedCategory = $child;
            $selectedParent = $root;
            break 2;
        }
    }
}

if ($categorySlug !== '') {
    $categoryIds = categoryIdsForSlug($categorySlug);

    if ($categoryIds) {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $stmt = db()->prepare(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.active = 1
               AND p.category_id IN ($placeholders)
             ORDER BY p.featured DESC, p.created_at DESC"
        );
        $stmt->execute($categoryIds);
    } else {
        $stmt = db()->query(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE 1 = 0'
        );
    }
} else {
    $stmt = db()->query(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.active = 1
         ORDER BY p.featured DESC, p.created_at DESC'
    );
}

$products = $stmt->fetchAll();

$heroTitle = $selectedCategory['name'] ?? 'Catálogo Flora Camily';
$heroDescription = $selectedCategory
    ? 'Encontre a homenagem ideal dentro desta seleção. Nossa equipe acompanha cada pedido até a entrega.'
    : 'Escolha com tranquilidade entre nossas homenagens florais. Cada pedido é analisado e acompanhado pela nossa equipe.';
?>
<section class="catalog-hero">
    <div class="container">
        <div class="catalog-hero-inner">
            <div class="catalog-hero-copy">
                <div class="catalog-breadcrumb">
                    <a href="index.php">Início</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="loja.php">Catálogo</a>
                    <?php if ($selectedParent): ?>
                        <i class="bi bi-chevron-right"></i>
                        <a href="loja.php?categoria=<?= urlencode((string) $selectedParent['slug']) ?>">
                            <?= e($selectedParent['name']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($selectedCategory): ?>
                        <i class="bi bi-chevron-right"></i>
                        <span><?= e($selectedCategory['name']) ?></span>
                    <?php endif; ?>
                </div>

                <span class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    <?= $selectedCategory ? 'Seleção do catálogo' : 'Homenagens florais' ?>
                </span>

                <h1><?= e($heroTitle) ?></h1>
                <p><?= e($heroDescription) ?></p>
            </div>

            <div class="catalog-hero-meta">
                <div class="catalog-hero-count">
                    <strong><?= count($products) ?></strong>
                    <span><?= count($products) === 1 ? 'produto disponível' : 'produtos disponíveis' ?></span>
                </div>

                <div class="catalog-hero-service">
                    <i class="bi bi-flower1"></i>
                    <div>
                        <strong>Atendimento humano</strong>
                        <span>Do pedido até a entrega</span>
                    </div>
                </div>
            </div>

            <span class="catalog-hero-orbit orbit-a"></span>
            <span class="catalog-hero-orbit orbit-b"></span>
        </div>
    </div>
</section>

<section class="catalog-section">
    <div class="container">
        <?php if ($categoryTree): ?>
            <div class="catalog-filter-panel">
                <div class="catalog-filter-head">
                    <div>
                        <span class="catalog-filter-kicker">Navegue por</span>
                        <h2>Categorias</h2>
                    </div>

                    <?php if ($categorySlug !== ''): ?>
                        <a href="loja.php" class="catalog-clear-filter">
                            Limpar filtro
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="catalog-filter-groups">
                    <a href="loja.php" class="catalog-filter-pill catalog-filter-all <?= $categorySlug === '' ? 'active' : '' ?>">
                        <i class="bi bi-grid"></i>
                        <span>Todos</span>
                    </a>

                    <?php foreach ($categoryTree as $root): ?>
                        <div class="catalog-filter-group">
                            <a
                                href="loja.php?categoria=<?= urlencode((string) $root['slug']) ?>"
                                class="catalog-filter-root <?= $categorySlug === $root['slug'] ? 'active' : '' ?>"
                            >
                                <i class="bi bi-folder2-open"></i>
                                <span><?= e($root['name']) ?></span>
                            </a>

                            <?php if (!empty($root['children'])): ?>
                                <div class="catalog-filter-children">
                                    <?php foreach ($root['children'] as $child): ?>
                                        <a
                                            href="loja.php?categoria=<?= urlencode((string) $child['slug']) ?>"
                                            class="catalog-filter-child <?= $categorySlug === $child['slug'] ? 'active' : '' ?>"
                                        >
                                            <?= e($child['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="catalog-results-head">
            <div>
                <span class="catalog-results-kicker">
                    <?= $selectedCategory ? e($selectedCategory['name']) : 'Todos os produtos' ?>
                </span>
                <h2><?= count($products) === 1 ? '1 homenagem encontrada' : count($products) . ' homenagens encontradas' ?></h2>
            </div>

            <span class="catalog-results-note">
                <i class="bi bi-truck"></i>
                Frete confirmado pela equipe
            </span>
        </div>

        <?php if ($products): ?>
            <div class="catalog-product-grid">
                <?php foreach ($products as $product): ?>
                    <?php $fallback = empty($product['image']); ?>
                    <article class="catalog-product-card">
                        <a href="produto.php?id=<?= (int) $product['id'] ?>" class="catalog-product-media">
                            <img
                                src="<?= e(productImage($product['image'])) ?>"
                                alt="<?= e($product['name']) ?>"
                                class="<?= $fallback ? 'logo-fallback' : '' ?>"
                            >

                            <?php if ((int) $product['featured'] === 1): ?>
                                <span class="catalog-product-featured">
                                    <i class="bi bi-stars"></i>
                                    Destaque
                                </span>
                            <?php endif; ?>

                            <span class="catalog-product-arrow">
                                <i class="bi bi-arrow-up-right"></i>
                            </span>
                        </a>

                        <div class="catalog-product-content">
                            <span class="catalog-product-category"><?= e(productCategoryName($product)) ?></span>

                            <h3>
                                <a href="produto.php?id=<?= (int) $product['id'] ?>">
                                    <?= e($product['name']) ?>
                                </a>
                            </h3>

                            <p><?= e(excerpt((string) $product['description'], 95)) ?></p>

                            <div class="catalog-product-bottom">
                                <div class="catalog-product-price">
                                    <small>A partir de</small>
                                    <strong><?= money((float) $product['price']) ?></strong>
                                </div>

                                <a href="produto.php?id=<?= (int) $product['id'] ?>" class="catalog-product-cta">
                                    Ver produto
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="catalog-empty-state">
                <span><i class="bi bi-flower1"></i></span>
                <h2>Nenhum produto nesta categoria</h2>
                <p>Veja o catálogo completo ou escolha outra categoria.</p>
                <a href="loja.php" class="btn btn-brand">Ver catálogo completo</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
