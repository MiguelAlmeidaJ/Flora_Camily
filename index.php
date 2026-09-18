<?php
$pageTitle = 'Flora Camily | Homenagens florais com delicadeza';
require __DIR__ . '/includes/header.php';

$stmt = db()->query(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.active = 1
     ORDER BY p.featured DESC, p.created_at DESC
     LIMIT 12'
);
$products = $stmt->fetchAll();

$heroProduct = null;
foreach ($products as $candidate) {
    if (!empty($candidate['image']) && is_file(__DIR__ . '/' . ltrim((string) $candidate['image'], '/'))) {
        $heroProduct = $candidate;
        break;
    }
}
?>

<section class="home-hero">
    <div class="container">
        <div class="row align-items-center g-5 g-xl-6">
            <div class="col-lg-6">
                <div class="home-hero-copy">
                    <span class="eyebrow">
                        <span class="eyebrow-dot"></span>
                        Flores que fazem histórias
                    </span>

                    <h1>Homenagens florais feitas com presença e delicadeza.</h1>

                    <p class="home-hero-lead">
                        Escolha uma homenagem com tranquilidade. Nossa equipe acompanha cada pedido,
                        confirma os detalhes e cuida da entrega com atenção em cada etapa.
                    </p>

                    <div class="home-hero-actions">
                        <a href="loja.php" class="btn btn-brand btn-lg px-4">
                            Ver homenagens
                            <i class="bi bi-arrow-right ms-2"></i>
                        </a>

                        <a
                            href="<?= e(storeWhatsAppUrl()) ?>"
                            target="_blank"
                            rel="noopener"
                            class="btn home-whatsapp-link btn-lg px-4"
                        >
                            <i class="bi bi-whatsapp me-2"></i>
                            Falar com a equipe
                        </a>
                    </div>

                    <div class="home-hero-trust">
                        <div>
                            <span><i class="bi bi-flower1"></i></span>
                            <div>
                                <strong>Preparação cuidadosa</strong>
                                <small>Cada pedido passa pela nossa equipe.</small>
                            </div>
                        </div>
                        <div>
                            <span><i class="bi bi-truck"></i></span>
                            <div>
                                <strong>Entrega própria</strong>
                                <small>Acompanhamento até o destino.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="home-hero-visual">
                    <div class="hero-botanical hero-botanical-one"></div>
                    <div class="hero-botanical hero-botanical-two"></div>

                    <div class="hero-brand-panel">
                        <div class="hero-brand-panel-top">
                            <span>Flora Camily</span>
                            <small>Homenagens florais</small>
                        </div>

                        <div class="hero-brand-mark">
                            <img src="<?= e(siteLogo()) ?>" alt="Flora Camily">
                        </div>

                        <div class="hero-brand-panel-bottom">
                            <span>cuidado</span>
                            <i class="bi bi-flower1"></i>
                            <span>presença</span>
                            <i class="bi bi-flower1"></i>
                            <span>respeito</span>
                        </div>
                    </div>

                    <?php if ($heroProduct): ?>
                        <a href="produto.php?id=<?= (int) $heroProduct['id'] ?>" class="hero-product-float">
                            <img src="<?= e(productImage($heroProduct['image'])) ?>" alt="<?= e($heroProduct['name']) ?>">
                            <div>
                                <small>Em destaque</small>
                                <strong><?= e($heroProduct['name']) ?></strong>
                                <span><?= money((float) $heroProduct['price']) ?></span>
                            </div>
                            <i class="bi bi-arrow-up-right"></i>
                        </a>
                    <?php else: ?>
                        <div class="hero-note-float">
                            <span><i class="bi bi-heart"></i></span>
                            <div>
                                <strong>Atendimento humano</strong>
                                <small>Você fala diretamente com nossa equipe.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="hero-seal-float">
                        <i class="bi bi-check2"></i>
                        <span>Pedido analisado<br>antes da preparação</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="home-assurance">
    <div class="container">
        <div class="home-assurance-grid">
            <div class="home-assurance-item">
                <span class="home-assurance-icon"><i class="bi bi-clipboard-check"></i></span>
                <div>
                    <strong>Análise pela equipe</strong>
                    <small>Conferimos os detalhes antes da preparação.</small>
                </div>
            </div>

            <div class="home-assurance-item">
                <span class="home-assurance-icon"><i class="bi bi-chat-heart"></i></span>
                <div>
                    <strong>Homenagem personalizada</strong>
                    <small>Mensagem de faixa e observações do cliente.</small>
                </div>
            </div>

            <div class="home-assurance-item">
                <span class="home-assurance-icon"><i class="bi bi-truck"></i></span>
                <div>
                    <strong>Entrega acompanhada</strong>
                    <small>Cuidado da confirmação até o destino final.</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="home-products-section">
    <div class="container">
        <div class="home-section-head">
            <div>
                <span class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    Seleção especial
                </span>
                <h2>Homenagens em destaque</h2>
                <p>Conheça algumas opções do catálogo. Os produtos em destaque aparecem sempre primeiro.</p>
            </div>

            <div class="home-carousel-head-actions">
                <a href="loja.php" class="home-section-link">
                    Ver todas
                    <i class="bi bi-arrow-right"></i>
                </a>

                <?php if (count($products) > 4): ?>
                    <div class="home-carousel-controls" aria-label="Navegação do carrossel">
                        <button type="button" class="home-carousel-button" data-carousel-prev aria-label="Produtos anteriores">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <button type="button" class="home-carousel-button" data-carousel-next aria-label="Próximos produtos">
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$products): ?>
            <div class="home-empty-products">
                <i class="bi bi-flower1"></i>
                <h3>Novas homenagens em breve</h3>
                <p>Estamos preparando nosso catálogo.</p>
            </div>
        <?php else: ?>
            <div class="home-products-carousel" data-product-carousel>
                <div class="home-products-viewport">
                    <div class="home-products-track">
                        <?php foreach ($products as $product): ?>
                            <?php $fallback = empty($product['image']); ?>
                            <div class="home-product-slide">
                                <article class="home-product-card is-simple">
                                    <a href="produto.php?id=<?= (int) $product['id'] ?>" class="home-product-media">
                                        <?php if ($fallback): ?>
                                            <div class="home-product-placeholder">
                                                <span class="placeholder-orbit orbit-one"></span>
                                                <span class="placeholder-orbit orbit-two"></span>
                                                <img src="<?= e(siteLogo()) ?>" alt="Flora Camily">
                                            </div>
                                        <?php else: ?>
                                            <img
                                                src="<?= e(productImage($product['image'])) ?>"
                                                alt="<?= e($product['name']) ?>"
                                                class="home-product-image"
                                            >
                                        <?php endif; ?>

                                        <?php if ((int) $product['featured'] === 1): ?>
                                            <span class="home-product-featured">Destaque</span>
                                        <?php endif; ?>
                                    </a>

                                    <div class="home-product-body">
                                        <div class="home-product-copy">
                                            <span class="home-product-category-text">
                                                <?= e(productCategoryName($product)) ?>
                                            </span>

                                            <h3>
                                                <a href="produto.php?id=<?= (int) $product['id'] ?>">
                                                    <?= e($product['name']) ?>
                                                </a>
                                            </h3>
                                        </div>

                                        <div class="home-product-price-block">
                                            <small>A partir de</small>
                                            <strong><?= money((float) $product['price']) ?></strong>
                                        </div>

                                        <a href="produto.php?id=<?= (int) $product['id'] ?>" class="home-product-shop-button">
                                            Ver produto
                                        </a>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<section id="como-funciona" class="home-how-section">
    <div class="container">
        <div class="home-how-head text-center">
            <span class="eyebrow">
                <span class="eyebrow-dot"></span>
                Simples e acolhedor
            </span>
            <h2>Como funciona</h2>
            <p>Um processo direto para que você possa cuidar da homenagem com tranquilidade.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="home-step-card">
                    <span class="home-step-number">01</span>
                    <div class="home-step-icon"><i class="bi bi-flower1"></i></div>
                    <h3>Escolha a homenagem</h3>
                    <p>Veja as opções disponíveis e escolha a composição mais adequada para o momento.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="home-step-card">
                    <span class="home-step-number">02</span>
                    <div class="home-step-icon"><i class="bi bi-card-text"></i></div>
                    <h3>Informe os detalhes</h3>
                    <p>Preencha local, data, horário, mensagem da faixa e qualquer observação importante.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="home-step-card">
                    <span class="home-step-number">03</span>
                    <div class="home-step-icon"><i class="bi bi-check2-circle"></i></div>
                    <h3>Nós cuidamos do restante</h3>
                    <p>A equipe analisa o pedido, confirma o frete e acompanha a preparação até a entrega.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
