<?php
require __DIR__ . '/config.php';

$items = cartProducts();
if (!$items) {
    redirect('carrinho.php');
}

$states = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
    'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
    'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
    'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
    'SE' => 'Sergipe', 'TO' => 'Tocantins',
];

$ribbonSuggestions = [
    'Com carinho e saudade, de seus familiares e amigos.',
    'Descanse em paz. Sua lembrança permanecerá para sempre.',
    'Com amor e eterna saudade.',
    'Nossa homenagem e carinho neste momento de despedida.',
    'Que as boas lembranças tragam conforto a todos.',
];

$errors = [];
$data = [
    'customer_name' => '',
    'customer_phone' => '',
    'customer_email' => '',
    'honoree_name' => '',
    'state' => 'MG',
    'city' => '',
    'delivery_place' => '',
    'delivery_date' => '',
    'delivery_time' => '',
    'ribbon_suggestion' => '',
    'ribbon_message' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    foreach ($data as $key => $value) {
        $data[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($data['customer_name'] === '') {
        $errors[] = 'Informe seu nome.';
    }
    if ($data['customer_phone'] === '' || strlen((string) preg_replace('/\D+/', '', $data['customer_phone'])) < 10) {
        $errors[] = 'Informe um telefone/WhatsApp válido.';
    }
    if ($data['customer_email'] !== '' && !filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido ou deixe o campo vazio.';
    }
    if ($data['honoree_name'] === '') {
        $errors[] = 'Informe o nome da pessoa homenageada.';
    }
    if (!isset($states[$data['state']])) {
        $errors[] = 'Selecione um estado válido.';
    }
    if ($data['city'] === '') {
        $errors[] = 'Informe a cidade da entrega.';
    }
    if ($data['delivery_place'] === '') {
        $errors[] = 'Informe o local da entrega.';
    }
    if ($data['delivery_date'] === '') {
        $errors[] = 'Informe a data da entrega.';
    } elseif ($data['delivery_date'] < date('Y-m-d')) {
        $errors[] = 'A data de entrega não pode estar no passado.';
    }
    if ($data['delivery_time'] === '') {
        $errors[] = 'Informe o horário desejado para a entrega.';
    }

    $ribbonMessage = $data['ribbon_message'] !== '' ? $data['ribbon_message'] : $data['ribbon_suggestion'];
    if (strlen($ribbonMessage) > 255) {
        $errors[] = 'A frase da coroa é muito longa.';
    }

    if (!$errors) {
        $pdo = db();
        $productsTotal = cartTotal();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO orders (
                    customer_name, customer_phone, customer_email, honoree_name, state, city,
                    delivery_place, delivery_date, delivery_time, ribbon_message, notes,
                    products_total, shipping_fee, total, status, is_read
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 0)'
            );
            $stmt->execute([
                $data['customer_name'],
                $data['customer_phone'],
                $data['customer_email'] !== '' ? $data['customer_email'] : null,
                $data['honoree_name'],
                $data['state'],
                $data['city'],
                $data['delivery_place'],
                $data['delivery_date'],
                $data['delivery_time'],
                $ribbonMessage !== '' ? $ribbonMessage : null,
                $data['notes'] !== '' ? $data['notes'] : null,
                $productsTotal,
                $productsTotal,
                'novo',
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

            $orderForEmail = [
                'id' => $orderId,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'],
                'honoree_name' => $data['honoree_name'],
                'state' => $data['state'],
                'city' => $data['city'],
                'delivery_place' => $data['delivery_place'],
                'delivery_date' => $data['delivery_date'],
                'delivery_time' => $data['delivery_time'],
                'products_total' => $productsTotal,
            ];

            if (sendNewOrderEmail($orderForEmail, $items)) {
                $pdo->prepare('UPDATE orders SET email_notified = 1 WHERE id = ?')->execute([$orderId]);
            }

            $_SESSION['cart'] = [];
            $_SESSION['last_order_id'] = $orderId;
            redirect('pedido-recebido.php');
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
<section class="checkout-page py-4 py-lg-5">
    <div class="container">
        <div class="mb-4">
            <span class="eyebrow">Finalização do pedido</span>
            <h1 class="checkout-title mt-2 mb-2">Dados da homenagem</h1>
            <p class="section-subtitle mb-0">Preencha os dados abaixo. A equipe da Flora Camily receberá o pedido para análise e confirmação do frete.</p>
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger rounded-4 mb-4">
                <strong>Revise os dados:</strong>
                <?php foreach ($errors as $error): ?><div class="mt-1"><?= e($error) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-4 align-items-start" id="checkoutForm">
            <?= csrfField() ?>

            <div class="col-lg-8">
                <div class="checkout-card mb-4">
                    <div class="checkout-card-header">
                        <h2 class="h5 mb-1">Seus dados</h2>
                        <p class="mb-0 small text-secondary">Usaremos estes dados caso seja necessário ajustar algum detalhe do pedido.</p>
                    </div>
                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Seu nome <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" maxlength="160" required autocomplete="name" value="<?= e($data['customer_name']) ?>" placeholder="Nome completo">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">WhatsApp <span class="text-danger">*</span></label>
                                <input type="tel" name="customer_phone" class="form-control" maxlength="40" required autocomplete="tel" value="<?= e($data['customer_phone']) ?>" placeholder="(32) 99999-9999">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">E-mail</label>
                                <input type="email" name="customer_email" class="form-control" maxlength="160" autocomplete="email" value="<?= e($data['customer_email']) ?>" placeholder="seuemail@exemplo.com">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="checkout-card">
                    <div class="checkout-card-header">
                        <h2 class="h5 mb-1">Dados do Velório</h2>
                        <p class="mb-0 small text-secondary">Onde e quando entregar, e a frase que vai na coroa.</p>
                    </div>

                    <div class="p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Nome da pessoa homenageada <span class="text-danger">*</span></label>
                                <input type="text" name="honoree_name" class="form-control" maxlength="160" required value="<?= e($data['honoree_name']) ?>" placeholder="Nome completo">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Estado <span class="text-danger">*</span></label>
                                <select name="state" class="form-select" required>
                                    <?php foreach ($states as $uf => $stateName): ?>
                                        <option value="<?= e($uf) ?>" <?= $data['state'] === $uf ? 'selected' : '' ?>><?= e($stateName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cidade <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" maxlength="120" required value="<?= e($data['city']) ?>" placeholder="Cidade da entrega">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Local de entrega (Cemitério, Velório, Igreja, etc.) <span class="text-danger">*</span></label>
                                <input type="text" name="delivery_place" class="form-control" maxlength="255" required value="<?= e($data['delivery_place']) ?>" placeholder="Ex.: Cemitério da Paz - Rua das Flores, 123">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Data da entrega <span class="text-danger">*</span></label>
                                <input type="date" name="delivery_date" class="form-control" min="<?= date('Y-m-d') ?>" required value="<?= e($data['delivery_date']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Hora da entrega <span class="text-danger">*</span></label>
                                <input type="time" name="delivery_time" class="form-control" required value="<?= e($data['delivery_time']) ?>">
                            </div>

                            <div class="col-12 mt-4">
                                <div class="checkout-subsection">
                                    <label class="form-label fw-semibold mb-0">Frase para a coroa</label>
                                    <div class="small text-secondary mb-3">Escolha uma sugestão ou escreva a sua. É o que vai impresso na faixa.</div>

                                    <select name="ribbon_suggestion" class="form-select mb-3">
                                        <option value="">Selecione uma frase sugerida...</option>
                                        <?php foreach ($ribbonSuggestions as $suggestion): ?>
                                            <option value="<?= e($suggestion) ?>" <?= $data['ribbon_suggestion'] === $suggestion ? 'selected' : '' ?>><?= e($suggestion) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label class="form-label fw-semibold small">Ou escreva sua própria frase</label>
                                    <textarea name="ribbon_message" id="ribbonMessage" class="form-control" rows="3" maxlength="150" placeholder="Digite sua mensagem personalizada..."><?= e($data['ribbon_message']) ?></textarea>
                                    <div class="text-end small text-secondary mt-1"><span id="ribbonCount"><?= strlen($data['ribbon_message']) ?></span>/150 caracteres</div>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <label class="form-label fw-semibold">Observações</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Informações adicionais sobre a entrega..."><?= e($data['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="checkout-submit p-4 pt-0">
                        <button type="submit" class="btn btn-brand btn-lg w-100 rounded-3 py-3">
                            <i class="bi bi-check2-circle me-2"></i>Finalizar pedido
                        </button>
                        <p class="small text-secondary text-center mt-3 mb-0">Seu pedido será enviado para análise. O frete é realizado pela própria Flora Camily e será confirmado pela equipe.</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="checkout-card checkout-summary sticky-lg-top">
                    <div class="checkout-card-header">
                        <h2 class="h5 mb-0">Resumo do Pedido</h2>
                    </div>
                    <div class="p-4">
                        <div class="vstack gap-3">
                            <?php foreach ($items as $item): ?>
                                <?php $fallback = empty($item['image']); ?>
                                <div class="d-flex gap-3 align-items-center">
                                    <img src="<?= e(productImage($item['image'])) ?>" alt="<?= e($item['name']) ?>" class="checkout-thumb <?= $fallback ? 'logo-fallback' : '' ?>">
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold text-dark"><?= e($item['name']) ?></div>
                                        <div class="small text-secondary">Qtd. <?= (int) $item['qty'] ?></div>
                                        <div class="fw-bold mt-1 product-price"><?= money((float) $item['subtotal']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="delivery-own mt-4">
                            <i class="bi bi-truck me-2"></i>
                            <div><strong>Entrega própria</strong><small>Frete confirmado pela equipe após análise.</small></div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-secondary">Subtotal</span>
                            <strong><?= money(cartTotal()) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-secondary">Frete</span>
                            <strong class="text-success">A confirmar</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-end">
                            <strong>Total dos produtos</strong>
                            <strong class="fs-4 product-price"><?= money(cartTotal()) ?></strong>
                        </div>
                        <div class="small text-secondary mt-2">O valor final será atualizado pela equipe caso haja cobrança de frete.</div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
<script>
(function () {
    const field = document.getElementById('ribbonMessage');
    const count = document.getElementById('ribbonCount');
    if (!field || !count) return;
    const update = () => count.textContent = field.value.length;
    field.addEventListener('input', update);
    update();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
