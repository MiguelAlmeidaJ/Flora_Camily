<?php
require __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('loja');
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
    echo '<section class="py-5"><div class="container text-center py-5"><h1>Homenagem não encontrada</h1><p class="text-secondary">Este item pode não estar mais disponível.</p><a class="btn btn-brand" href="loja">Voltar ao catálogo</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'] . ' | Flora Camily';
require __DIR__ . '/includes/header.php';
$fallback = empty($product['image']);
?>
<section class="product-detail-page">
    <div class="container">
        <div class="product-detail-grid">
            <div class="product-detail-media-column">
                <div class="product-detail-media-card">
                    <?php if ((int) $product['featured'] === 1): ?>
                        <span class="product-detail-featured">
                            <i class="bi bi-stars"></i>Destaque
                        </span>
                    <?php endif; ?>

                    <img
                        src="<?= e(productImage($product['image'])) ?>"
                        alt="<?= e($product['name']) ?>"
                        class="product-detail-image <?= $fallback ? 'logo-fallback' : '' ?>"
                    >
                </div>

            </div>

            <div class="product-detail-main">
                <a href="loja" class="product-detail-back">
                    <i class="bi bi-arrow-left"></i>Voltar ao catálogo
                </a>

                <span class="product-detail-category"><?= e(productCategoryName($product)) ?></span>
                <h1><?= e($product['name']) ?></h1>

                <div class="product-detail-price">
                    <small>A partir de</small>
                    <strong><?= money((float) $product['price']) ?></strong>
                </div>

                <div class="product-detail-info-grid">
                    <div>
                        <span><i class="bi bi-tag"></i></span>
                        <div>
                            <small>Categoria</small>
                            <strong><?= e(productCategoryName($product)) ?></strong>
                        </div>
                    </div>
                    <div>
                        <span><i class="bi bi-chat-heart"></i></span>
                        <div>
                            <small>Personalização</small>
                            <strong>Mensagem de faixa</strong>
                        </div>
                    </div>
                    <div>
                        <span><i class="bi bi-geo-alt"></i></span>
                        <div>
                            <small>Entrega</small>
                            <strong>Regiões atendidas</strong>
                        </div>
                    </div>
                    <div>
                        <span><i class="bi bi-wallet2"></i></span>
                        <div>
                            <small>Frete</small>
                            <strong>Confirmado pela equipe</strong>
                        </div>
                    </div>
                </div>

                <form action="carrinho.php" method="post" class="product-detail-purchase">
                    <?= csrfField() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

                    <div class="product-detail-quantity">
                        <label for="qty">Quantidade</label>
                        <div class="product-quantity-control">
                            <button type="button" data-qty-minus aria-label="Diminuir quantidade">
                                <i class="bi bi-dash"></i>
                            </button>
                            <input id="qty" type="number" name="qty" min="1" max="20" value="1">
                            <button type="button" data-qty-plus aria-label="Aumentar quantidade">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="product-detail-actions">
                        <button type="submit" name="action" value="add" class="btn product-add-cart">
                            <i class="bi bi-bag-plus"></i>
                            Adicionar ao carrinho
                        </button>

                        <button type="submit" name="action" value="buy_now" class="btn product-buy-now">
                            <i class="bi bi-lightning-charge"></i>
                            Finalizar compra
                        </button>
                    </div>
                </form>

                <div class="product-detail-order-note">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Seu pedido passa por confirmação</strong>
                        <span>Após o envio, a equipe confere os detalhes, confirma o frete e entra em contato pelo WhatsApp somente se algum ajuste for necessário.</span>
                    </div>
                </div>
            </div>
        </div>

        <section class="product-about-section">
            <div class="product-about-main">
                <span class="eyebrow">
                    <span class="eyebrow-dot"></span>
                    Sobre esta homenagem
                </span>
                <h2>Detalhes do produto</h2>

                <div class="product-description-card">
                    <?php if (trim((string) $product['description']) !== ''): ?>
                        <p><?= nl2br(e((string) $product['description'])) ?></p>
                    <?php else: ?>
                        <p>Uma homenagem floral preparada para expressar cuidado e respeito. Os detalhes do pedido são confirmados pela equipe antes da preparação.</p>
                    <?php endif; ?>
                </div>
            </div>

            <aside class="product-service-card">
                <span class="product-service-kicker">Do pedido à entrega</span>
                <h3>Como funciona</h3>

                <div class="product-service-steps">
                    <div>
                        <span>01</span>
                        <div>
                            <strong>Escolha a homenagem</strong>
                            <small>Adicione ao carrinho ou finalize a compra diretamente.</small>
                        </div>
                    </div>
                    <div>
                        <span>02</span>
                        <div>
                            <strong>Informe os detalhes</strong>
                            <small>No checkout, preencha local, data, horário e mensagem da faixa.</small>
                        </div>
                    </div>
                    <div>
                        <span>03</span>
                        <div>
                            <strong>Nossa equipe confirma</strong>
                            <small>O pedido e o frete são analisados antes da preparação.</small>
                        </div>
                    </div>
                    <div>
                        <span>04</span>
                        <div>
                            <strong>Entrega acompanhada</strong>
                            <small>Você acompanha o andamento até a conclusão do pedido.</small>
                        </div>
                    </div>
                </div>
            </aside>
        </section>

        <section class="product-help-strip">
            <div>
                <span><i class="bi bi-whatsapp"></i></span>
                <div>
                    <strong>Precisa tirar uma dúvida antes de comprar?</strong>
                    <small>Fale diretamente com a equipe da Flora Camily.</small>
                </div>
            </div>

            <a href="<?= e(storeWhatsAppUrl()) ?>" target="_blank" rel="noopener" class="btn product-help-button">
                Falar pelo WhatsApp
                <i class="bi bi-arrow-up-right"></i>
            </a>
        </section>


<script>
(function () {
    const input = document.getElementById('qty');
    const minus = document.querySelector('[data-qty-minus]');
    const plus = document.querySelector('[data-qty-plus]');

    if (!input || !minus || !plus) return;

    const clamp = (value) => Math.max(1, Math.min(20, Number(value) || 1));

    minus.addEventListener('click', () => {
        input.value = clamp(Number(input.value) - 1);
    });

    plus.addEventListener('click', () => {
        input.value = clamp(Number(input.value) + 1);
    });

    input.addEventListener('change', () => {
        input.value = clamp(input.value);
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
