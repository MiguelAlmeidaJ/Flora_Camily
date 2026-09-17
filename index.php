<?php
$pageTitle = 'Flora Camily | Homenagens florais com delicadeza';
require __DIR__ . '/includes/header.php';

$stmt = db()->query('SELECT * FROM products WHERE active = 1 ORDER BY featured DESC, created_at DESC LIMIT 6');
$products = $stmt->fetchAll();
?>
<section class="hero py-5 py-lg-6">
    <div class="container py-4 py-lg-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="eyebrow"><i class="bi bi-flower1"></i> Flores que fazem histórias</span>
                <h1 class="mt-3 mb-4">Uma homenagem feita com cuidado em cada detalhe.</h1>
                <p class="mb-4">Escolha sua homenagem floral com tranquilidade, informe os dados da entrega e envie o pedido para nossa equipe analisar e preparar tudo com cuidado.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start">
                    <a href="loja.php" class="btn btn-brand btn-lg px-4">Ver homenagens</a>
                    <a href="https://wa.me/<?= e(WHATSAPP_NUMBER) ?>" target="_blank" rel="noopener" class="btn btn-outline-brand btn-lg px-4"><i class="bi bi-whatsapp me-2"></i>Falar com a equipe</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-logo-wrap">
                    <img src="assets/img/logo.svg" class="hero-logo" alt="Logo Flora Camily">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="info-strip py-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4 d-flex gap-3 align-items-center">
                <div class="info-icon"><i class="bi bi-clipboard-check"></i></div>
                <div><strong>Análise pela equipe</strong><div class="small opacity-75">Cada pedido é conferido antes da preparação</div></div>
            </div>
            <div class="col-md-4 d-flex gap-3 align-items-center">
                <div class="info-icon"><i class="bi bi-flower2"></i></div>
                <div><strong>Homenagens personalizadas</strong><div class="small opacity-75">Mensagem de faixa e observações</div></div>
            </div>
            <div class="col-md-4 d-flex gap-3 align-items-center">
                <div class="info-icon"><i class="bi bi-truck"></i></div>
                <div><strong>Entrega própria</strong><div class="small opacity-75">A Flora Camily cuida da entrega até o destino</div></div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container py-lg-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
                <span class="eyebrow">Seleção especial</span>
                <h2 class="section-title mb-2">Homenagens em destaque</h2>
                <p class="section-subtitle mb-0">Opções preparadas para expressar carinho, respeito e presença.</p>
            </div>
            <a href="loja.php" class="btn btn-outline-brand align-self-start align-self-md-auto">Ver todas</a>
        </div>

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
                            <h3 class="product-title mb-2"><a class="text-decoration-none" href="produto.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3>
                            <p class="text-secondary small mb-3"><?= e(excerpt((string) $product['description'], 115)) ?></p>
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <span class="product-price"><?= money((float) $product['price']) ?></span>
                                <a href="produto.php?id=<?= (int) $product['id'] ?>" class="btn btn-sm btn-outline-brand">Detalhes</a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="como-funciona" class="soft-section py-5">
    <div class="container py-lg-4">
        <div class="text-center mb-5">
            <span class="eyebrow">Simples e acolhedor</span>
            <h2 class="section-title">Como funciona</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-1-circle"></i>
                    <h3 class="h4 mt-3">Escolha a homenagem</h3>
                    <p class="text-secondary mb-0">Veja os produtos disponíveis e adicione ao carrinho.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-2-circle"></i>
                    <h3 class="h4 mt-3">Informe os detalhes</h3>
                    <p class="text-secondary mb-0">Preencha local, data, horário, mensagem da faixa e observações.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <i class="bi bi-3-circle"></i>
                    <h3 class="h4 mt-3">Envie seu pedido</h3>
                    <p class="text-secondary mb-0">A equipe analisa a solicitação, confirma o frete e entra em contato apenas se algum ajuste for necessário.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
