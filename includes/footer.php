</main>
<footer class="site-footer mt-5">
    <div class="container py-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="assets/img/logo.svg" alt="Flora Camily" class="footer-logo">
                    <div>
                        <div class="footer-brand">Flora Camily</div>
                        <small>Flores que fazem histórias</small>
                    </div>
                </div>
                <p class="mb-0 footer-text">Homenagens florais preparadas com cuidado, respeito e atenção a cada detalhe.</p>
            </div>
            <div class="col-6 col-lg-3">
                <h6>Navegação</h6>
                <a href="index.php">Início</a>
                <a href="loja.php">Homenagens</a>
                <?php if (cartCount() > 0): ?><a href="carrinho.php">Carrinho</a><?php endif; ?>
            </div>
            <div class="col-6 col-lg-4">
                <h6>Atendimento</h6>
                <a href="<?= e(storeWhatsAppUrl()) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1"></i> Falar pelo WhatsApp</a>
                <span class="footer-text small d-block mt-2">A equipe acompanha cada pedido da análise até a entrega.</span>
            </div>
        </div>
        <hr class="my-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 small footer-text">
            <span>© <?= date('Y') ?> Flora Camily. Todos os direitos reservados.</span>
            <span>Entrega própria e acompanhamento humano em todas as etapas.</span>
        </div>
    </div>
</footer>

<div class="floating-actions" aria-label="Ações rápidas">
    <?php if (cartCount() > 0): ?>
        <a href="carrinho.php" class="floating-cart" aria-label="Abrir carrinho com <?= cartCount() ?> item<?= cartCount() === 1 ? '' : 's' ?>">
            <span class="floating-cart-icon"><i class="bi bi-bag-heart"></i><span><?= cartCount() ?></span></span>
            <span class="floating-cart-text">Ver carrinho</span>
        </a>
    <?php endif; ?>
    <a href="<?= e(storeWhatsAppUrl()) ?>" target="_blank" rel="noopener" class="floating-whatsapp" aria-label="Comprar pelo WhatsApp" title="Comprar pelo WhatsApp">
        <i class="bi bi-whatsapp"></i>
    </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
