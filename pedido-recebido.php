<?php
require __DIR__ . '/config.php';

$orderId = (int) ($_SESSION['last_order_id'] ?? 0);
if ($orderId <= 0) {
    redirect('index.php');
}

$stmt = db()->prepare('SELECT id, customer_name, status, created_at FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    unset($_SESSION['last_order_id']);
    redirect('index.php');
}

$pageTitle = 'Pedido recebido | Flora Camily';
require __DIR__ . '/includes/header.php';
?>
<section class="soft-section py-5">
    <div class="container py-lg-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="checkout-card p-4 p-md-5 text-center">
                    <div class="success-icon mx-auto mb-4"><i class="bi bi-check2"></i></div>
                    <span class="eyebrow">Pedido recebido</span>
                    <h1 class="section-title mt-2 mb-3">Recebemos seu pedido.</h1>
                    <p class="lead text-secondary mb-2">Obrigado, <?= e($order['customer_name']) ?>.</p>
                    <p class="text-secondary mb-4">O pedido <strong>#<?= (int) $order['id'] ?></strong> já está no painel da Flora Camily para análise.</p>

                    <div class="order-flow text-start my-5">
                        <div class="order-flow-item active">
                            <span><i class="bi bi-receipt"></i></span>
                            <div><strong>Pedido recebido</strong><small>A equipe foi notificada e irá analisar os detalhes.</small></div>
                        </div>
                        <div class="order-flow-item">
                            <span><i class="bi bi-flower1"></i></span>
                            <div><strong>Em preparação</strong><small>Após a confirmação da venda, iniciaremos a preparação.</small></div>
                        </div>
                        <div class="order-flow-item">
                            <span><i class="bi bi-truck"></i></span>
                            <div><strong>Em entrega</strong><small>A Flora Camily realiza a entrega diretamente.</small></div>
                        </div>
                        <div class="order-flow-item">
                            <span><i class="bi bi-check-circle"></i></span>
                            <div><strong>Pedido entregue</strong><small>O pedido é finalizado após a entrega no local informado.</small></div>
                        </div>
                    </div>

                    <div class="alert alert-light border rounded-4 text-start mb-4">
                        <i class="bi bi-chat-heart me-2"></i>
                        Se for necessário ajustar produto, endereço, horário ou frete, nossa equipe entrará em contato pelo WhatsApp informado no pedido.
                    </div>

                    <a href="loja.php" class="btn btn-brand px-4">Voltar para a loja</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
