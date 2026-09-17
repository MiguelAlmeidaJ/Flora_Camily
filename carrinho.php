<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if (in_array($action, ['add', 'buy_now'], true)) {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = max(1, min(20, (int) ($_POST['qty'] ?? 1)));

        $stmt = db()->prepare('SELECT id FROM products WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$productId]);
        if ($stmt->fetch()) {
            $_SESSION['cart'][$productId] = min(20, ((int) ($_SESSION['cart'][$productId] ?? 0)) + $qty);
        }

        if ($action === 'buy_now') {
            redirect('checkout.php');
        }
        redirect('carrinho.php');
    }

    if ($action === 'update') {
        foreach (($_POST['qty'] ?? []) as $productId => $qty) {
            $productId = (int) $productId;
            $qty = (int) $qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = min(20, $qty);
            }
        }
        redirect('carrinho.php');
    }

    if ($action === 'remove') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$productId]);
        redirect('carrinho.php');
    }
}

$pageTitle = 'Carrinho | Flora Camily';
require __DIR__ . '/includes/header.php';
$items = cartProducts();
?>
<section class="page-hero py-5">
    <div class="container py-lg-3">
        <span class="eyebrow">Seu pedido</span>
        <h1 class="mb-2">Carrinho</h1>
        <p class="section-subtitle mb-0">Revise as homenagens antes de informar os dados para atendimento.</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (!$items): ?>
            <div class="text-center py-5">
                <i class="bi bi-bag-heart display-4 text-secondary"></i>
                <h2 class="h3 mt-3">Seu carrinho está vazio</h2>
                <p class="text-secondary">Escolha uma homenagem para começar.</p>
                <a href="loja.php" class="btn btn-brand">Ver homenagens</a>
            </div>
        <?php else: ?>
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update">
                        <div class="vstack gap-3">
                            <?php foreach ($items as $item): ?>
                                <div class="cart-item p-3">
                                    <div class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                                        <img src="<?= e(productImage($item['image'])) ?>" alt="<?= e($item['name']) ?>" class="cart-thumb">
                                        <div class="flex-grow-1">
                                            <div class="product-category mb-1"><?= e(productCategoryName($item)) ?></div>
                                            <h2 class="h4 mb-1"><?= e($item['name']) ?></h2>
                                            <div class="small text-secondary"><?= money((float) $item['price']) ?> cada</div>
                                        </div>
                                        <div style="width:110px">
                                            <label class="form-label small mb-1">Qtd.</label>
                                            <input class="form-control" type="number" min="0" max="20" name="qty[<?= (int) $item['id'] ?>]" value="<?= (int) $item['qty'] ?>">
                                        </div>
                                        <div class="text-sm-end" style="min-width:120px">
                                            <div class="small text-secondary">Subtotal</div>
                                            <strong><?= money((float) $item['subtotal']) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
                            <a href="loja.php" class="btn btn-outline-brand"><i class="bi bi-arrow-left me-1"></i>Continuar escolhendo</a>
                            <button class="btn btn-light border rounded-pill px-4" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Atualizar carrinho</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-4">
                    <div class="summary-card p-4 sticky-lg-top" style="top:95px">
                        <h2 class="h3 mb-4">Resumo</h2>
                        <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Itens</span><span><?= cartCount() ?></span></div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-end mb-4">
                            <strong>Total dos produtos</strong>
                            <strong class="fs-4 text-nowrap"><?= money(cartTotal()) ?></strong>
                        </div>
                        <a href="checkout.php" class="btn btn-brand btn-lg w-100">Finalizar compra <i class="bi bi-arrow-right ms-1"></i></a>
                        <p class="small text-secondary mt-3 mb-0">O pedido será enviado para análise. O frete será definido manualmente pela equipe da Flora Camily.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
