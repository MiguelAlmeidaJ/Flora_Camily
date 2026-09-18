</main>

<footer class="site-footer">
    <div class="container py-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <div class="footer-brand-block">
                    <img src="<?= e(siteLogo()) ?>" alt="Flora Camily" class="footer-logo">
                    <p class="footer-text mb-0">
                        Homenagens florais preparadas com cuidado, respeito e atenção a cada detalhe.
                    </p>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <h6>Navegação</h6>
                <a href="index.php">Início</a>
                <a href="loja.php">Catálogo</a>
                <?php if (cartCount() > 0): ?><a href="carrinho.php">Carrinho</a><?php endif; ?>
            </div>

            <div class="col-6 col-lg-4">
                <h6>Atendimento</h6>
                <a href="<?= e(storeWhatsAppUrl()) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp me-1"></i> Falar pelo WhatsApp
                </a>
                <span class="footer-text small d-block mt-2">
                    A equipe acompanha cada pedido da análise até a entrega.
                </span>
            </div>
        </div>

        <hr class="my-4">

        <div class="footer-bottom">
            <span>© <?= date('Y') ?> Flora Camily. Todos os direitos reservados.</span>

            <span class="footer-credits">
                Desenvolvido por
                <a href="https://www.nivel3ti.com.br" target="_blank" rel="noopener">Nivel 3</a>
                <span>+</span>
                <a href="https://www.instagram.com/miguelalmeida.dev" target="_blank" rel="noopener">Miguel Almeida</a>
            </span>
        </div>
    </div>
</footer>

<div class="floating-actions" aria-label="Ações rápidas">
    <?php if (cartCount() > 0): ?>
        <a
            href="carrinho.php"
            class="floating-cart"
            aria-label="Abrir carrinho com <?= cartCount() ?> item<?= cartCount() === 1 ? '' : 's' ?>"
            title="Ver carrinho"
        >
            <span class="floating-cart-icon">
                <i class="bi bi-bag-heart"></i>
                <span><?= cartCount() ?></span>
            </span>
        </a>
    <?php endif; ?>

    <a
        href="<?= e(storeWhatsAppUrl()) ?>"
        target="_blank"
        rel="noopener"
        class="floating-whatsapp"
        aria-label="Comprar pelo WhatsApp"
        title="Comprar pelo WhatsApp"
    >
        <i class="bi bi-whatsapp"></i>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
