<?php
require __DIR__ . '/config.php';

$items = cartProducts();
if (!$items) {
    redirect('carrinho.php');
}

$errors = [];
$data = [
    'customer_name' => '',
    'customer_phone' => '',
    'city' => '',
    'delivery_place' => '',
    'desired_datetime' => '',
    'ribbon_message' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($data as $key => $value) {
        $data[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($data['customer_name'] === '') {
        $errors[] = 'Informe o nome de quem está fazendo o pedido.';
    }
    if ($data['delivery_place'] === '') {
        $errors[] = 'Informe o local da homenagem/entrega.';
    }

    if (!$errors) {
        $pdo = db();
        $total = cartTotal();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO orders (customer_name, customer_phone, city, delivery_place, desired_datetime, ribbon_message, notes, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['customer_name'],
                $data['customer_phone'],
                $data['city'],
                $data['delivery_place'],
                $data['desired_datetime'],
                $data['ribbon_message'],
                $data['notes'],
                $total,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $itemStmt->execute([
                    $orderId,
                    (int) $item['id'],
                    $item['name'],
                    (int) $item['qty'],
                    (float) $item['price'],
                    (float) $item['subtotal'],
                ]);
            }

            $pdo->commit();

            $lines = [
                'Olá! Gostaria de confirmar um pedido na Flora Camily.',
                '',
                '*Pedido #' . $orderId . '*',
                '*Cliente:* ' . $data['customer_name'],
            ];

            if ($data['customer_phone'] !== '') $lines[] = '*Telefone:* ' . $data['customer_phone'];
            if ($data['city'] !== '') $lines[] = '*Cidade:* ' . $data['city'];
            $lines[] = '*Local da homenagem/entrega:* ' . $data['delivery_place'];
            if ($data['desired_datetime'] !== '') $lines[] = '*Data/horário desejado:* ' . $data['desired_datetime'];
            $lines[] = '';
            $lines[] = '*Itens:*';

            foreach ($items as $item) {
                $lines[] = '- ' . $item['qty'] . 'x ' . $item['name'] . ' — ' . money((float) $item['subtotal']);
            }

            $lines[] = '';
            $lines[] = '*Total do pedido:* ' . money($total);

            if ($data['ribbon_message'] !== '') {
                $lines[] = '';
                $lines[] = '*Mensagem da faixa:* ' . $data['ribbon_message'];
            }
            if ($data['notes'] !== '') {
                $lines[] = '*Observações:* ' . $data['notes'];
            }

            $lines[] = '';
            $lines[] = 'Podem confirmar disponibilidade, prazo de entrega e forma de pagamento?';

            $_SESSION['cart'] = [];
            $url = 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode(implode("\n", $lines));
            redirect($url);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Não foi possível registrar o pedido. Tente novamente em alguns instantes.';
        }
    }
}

$pageTitle = 'Finalizar pedido | Flora Camily';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero py-5">
    <div class="container py-lg-3">
        <span class="eyebrow">Última etapa</span>
        <h1 class="mb-2">Dados da homenagem</h1>
        <p class="section-subtitle mb-0">Preencha as informações essenciais. Ao finalizar, o WhatsApp abrirá com o resumo do pedido pronto.</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <?php if ($errors): ?>
                    <div class="alert alert-danger rounded-4">
                        <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="product-card p-4 p-lg-5">
                    <?= csrfField() ?>
                    <h2 class="h3 mb-4">Informações para atendimento</h2>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Seu nome *</label>
                            <input type="text" name="customer_name" class="form-control" maxlength="160" required value="<?= e($data['customer_name']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Telefone</label>
                            <input type="text" name="customer_phone" class="form-control" maxlength="40" placeholder="(32) 99999-9999" value="<?= e($data['customer_phone']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Cidade</label>
                            <input type="text" name="city" class="form-control" maxlength="120" value="<?= e($data['city']) ?>">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Data e horário desejados</label>
                            <input type="text" name="desired_datetime" class="form-control" maxlength="100" placeholder="Ex.: hoje às 17h" value="<?= e($data['desired_datetime']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Local da homenagem/entrega *</label>
                            <input type="text" name="delivery_place" class="form-control" maxlength="255" required placeholder="Ex.: capela, endereço ou nome do local" value="<?= e($data['delivery_place']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Mensagem da faixa</label>
                            <input type="text" name="ribbon_message" class="form-control" maxlength="255" placeholder="Ex.: Com carinho, família e amigos" value="<?= e($data['ribbon_message']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Observações</label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Cores preferidas, referências ou outras informações importantes."><?= e($data['notes']) ?></textarea>
                        </div>
                    </div>
                    <div class="whatsapp-note rounded-4 p-3 my-4 small">
                        <i class="bi bi-whatsapp me-1"></i> O envio deste formulário não conclui o pagamento. A equipe confirmará disponibilidade, composição, prazo, entrega e pagamento pelo WhatsApp.
                    </div>
                    <button type="submit" class="btn btn-brand btn-lg w-100"><i class="bi bi-whatsapp me-2"></i>Finalizar pelo WhatsApp</button>
                </form>
            </div>

            <div class="col-lg-5">
                <div class="summary-card p-4">
                    <h2 class="h3 mb-4">Resumo do pedido</h2>
                    <div class="vstack gap-3">
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex justify-content-between gap-3 small">
                                <span><?= (int) $item['qty'] ?>x <?= e($item['name']) ?></span>
                                <strong class="text-nowrap"><?= money((float) $item['subtotal']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-end"><strong>Total</strong><strong class="fs-4"><?= money(cartTotal()) ?></strong></div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
