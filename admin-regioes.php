<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_state') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $uf = strtoupper(trim((string) ($_POST['uf'] ?? '')));
            $active = isset($_POST['active']) ? 1 : 0;

            if ($name === '' || !preg_match('/^[A-Z]{2}$/', $uf)) {
                throw new RuntimeException('Informe o nome do estado e uma UF válida com 2 letras.');
            }

            $check = db()->prepare('SELECT id FROM service_states WHERE uf = ? AND id <> ? LIMIT 1');
            $check->execute([$uf, $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Já existe um estado cadastrado com esta UF.');
            }

            if ($id > 0) {
                $exists = db()->prepare('SELECT id FROM service_states WHERE id = ? LIMIT 1');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Estado não encontrado.');
                }

                db()->prepare('UPDATE service_states SET name = ?, uf = ?, active = ? WHERE id = ?')
                    ->execute([$name, $uf, $active, $id]);

                appLog('region.state_update', [
                    'state_id' => $id,
                    'name' => $name,
                    'uf' => $uf,
                    'active' => $active,
                ]);

                $_SESSION['admin_flash'] = 'Estado atualizado com sucesso.';
            } else {
                $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM service_states')->fetchColumn();

                db()->prepare(
                    'INSERT INTO service_states (name, uf, active, sort_order) VALUES (?, ?, ?, ?)'
                )->execute([$name, $uf, $active, $sortOrder]);

                $id = (int) db()->lastInsertId();

                appLog('region.state_create', [
                    'state_id' => $id,
                    'name' => $name,
                    'uf' => $uf,
                ]);

                $_SESSION['admin_flash'] = 'Estado criado com sucesso.';
            }

            redirect('admin-regioes.php');
        }

        if ($action === 'delete_state') {
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = db()->prepare('SELECT name, uf FROM service_states WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $state = $stmt->fetch();

            if (!$state) {
                throw new RuntimeException('Estado não encontrado.');
            }

            $countStmt = db()->prepare('SELECT COUNT(*) FROM service_cities WHERE state_id = ?');
            $countStmt->execute([$id]);
            if ((int) $countStmt->fetchColumn() > 0) {
                throw new RuntimeException('Este estado possui cidades vinculadas. Remova ou mova as cidades antes de excluí-lo.');
            }

            db()->prepare('DELETE FROM service_states WHERE id = ?')->execute([$id]);

            appLog('region.state_delete', [
                'state_id' => $id,
                'name' => $state['name'],
                'uf' => $state['uf'],
            ]);

            $_SESSION['admin_flash'] = 'Estado excluído.';
            redirect('admin-regioes.php');
        }

        if ($action === 'save_city') {
            $id = (int) ($_POST['id'] ?? 0);
            $stateId = (int) ($_POST['state_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $active = isset($_POST['active']) ? 1 : 0;

            if ($stateId <= 0 || $name === '') {
                throw new RuntimeException('Selecione o estado e informe o nome da cidade.');
            }

            $stateStmt = db()->prepare('SELECT id FROM service_states WHERE id = ? LIMIT 1');
            $stateStmt->execute([$stateId]);
            if (!$stateStmt->fetchColumn()) {
                throw new RuntimeException('O estado selecionado não existe.');
            }

            $check = db()->prepare(
                'SELECT id FROM service_cities WHERE state_id = ? AND LOWER(name) = LOWER(?) AND id <> ? LIMIT 1'
            );
            $check->execute([$stateId, $name, $id]);
            if ($check->fetch()) {
                throw new RuntimeException('Esta cidade já está cadastrada neste estado.');
            }

            if ($id > 0) {
                $exists = db()->prepare('SELECT id FROM service_cities WHERE id = ? LIMIT 1');
                $exists->execute([$id]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Cidade não encontrada.');
                }

                db()->prepare('UPDATE service_cities SET state_id = ?, name = ?, active = ? WHERE id = ?')
                    ->execute([$stateId, $name, $active, $id]);

                appLog('region.city_update', [
                    'city_id' => $id,
                    'state_id' => $stateId,
                    'name' => $name,
                    'active' => $active,
                ]);

                $_SESSION['admin_flash'] = 'Cidade atualizada com sucesso.';
            } else {
                $sortStmt = db()->prepare(
                    'SELECT COALESCE(MAX(sort_order), 0) + 10 FROM service_cities WHERE state_id = ?'
                );
                $sortStmt->execute([$stateId]);
                $sortOrder = (int) $sortStmt->fetchColumn();

                db()->prepare(
                    'INSERT INTO service_cities (state_id, name, active, sort_order) VALUES (?, ?, ?, ?)'
                )->execute([$stateId, $name, $active, $sortOrder]);

                $id = (int) db()->lastInsertId();

                appLog('region.city_create', [
                    'city_id' => $id,
                    'state_id' => $stateId,
                    'name' => $name,
                ]);

                $_SESSION['admin_flash'] = 'Cidade criada com sucesso.';
            }

            redirect('admin-regioes.php');
        }

        if ($action === 'delete_city') {
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = db()->prepare('SELECT state_id, name FROM service_cities WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $city = $stmt->fetch();

            if (!$city) {
                throw new RuntimeException('Cidade não encontrada.');
            }

            db()->prepare('DELETE FROM service_cities WHERE id = ?')->execute([$id]);

            appLog('region.city_delete', [
                'city_id' => $id,
                'state_id' => $city['state_id'],
                'name' => $city['name'],
            ]);

            $_SESSION['admin_flash'] = 'Cidade excluída.';
            redirect('admin-regioes.php');
        }
    } catch (Throwable $e) {
        appLog('region.error', [
            'action' => $action,
            'message' => $e->getMessage(),
        ], 'error');

        $flash = $e->getMessage();
    }
}

$states = db()->query(
    'SELECT s.*,
            COUNT(c.id) AS cities_count,
            SUM(CASE WHEN c.active = 1 THEN 1 ELSE 0 END) AS active_cities_count
     FROM service_states s
     LEFT JOIN service_cities c ON c.state_id = s.id
     GROUP BY s.id
     ORDER BY s.sort_order ASC, s.name ASC'
)->fetchAll();

$cities = db()->query(
    'SELECT c.*, s.name AS state_name, s.uf AS state_uf, s.active AS state_active
     FROM service_cities c
     INNER JOIN service_states s ON s.id = c.state_id
     ORDER BY s.sort_order ASC, s.name ASC, c.sort_order ASC, c.name ASC'
)->fetchAll();

$adminPage = 'regioes';
$adminTitle = 'Regiões';
$adminSubtitle = 'Defina exatamente os estados e cidades atendidos pela Flora Camily.';
require __DIR__ . '/includes/admin-shell-start.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-info rounded-4"><?= e($flash) ?></div>
<?php endif; ?>

<div class="regions-page">
    <section class="regions-hero-card">
        <div class="regions-hero-copy">
            <span class="regions-hero-kicker">Área de atendimento</span>
            <h2>Onde a Flora Camily atende</h2>
            <p>Gerencie os estados e cidades disponíveis para entrega. O checkout usa somente as regiões ativas nesta página.</p>

            <div class="regions-hero-stats">
                <div>
                    <span>Estados</span>
                    <strong><?= count($states) ?></strong>
                </div>
                <div>
                    <span>Cidades</span>
                    <strong><?= count($cities) ?></strong>
                </div>
                <div>
                    <span>Ativas</span>
                    <strong><?= count(array_filter($cities, static fn (array $city): bool => (int) $city['active'] === 1 && (int) $city['state_active'] === 1)) ?></strong>
                </div>
            </div>
        </div>

        <div class="regions-hero-actions">
            <button
                type="button"
                class="btn btn-light border"
                data-bs-toggle="modal"
                data-bs-target="#stateModal"
                data-state-new
            >
                <i class="bi bi-plus-lg me-1"></i>Novo estado
            </button>

            <button
                type="button"
                class="btn btn-brand"
                data-bs-toggle="modal"
                data-bs-target="#cityModal"
                data-city-new
            >
                <i class="bi bi-geo-alt me-1"></i>Nova cidade
            </button>
        </div>

        <div class="regions-hero-map-mark" aria-hidden="true">
            <i class="bi bi-geo-alt"></i>
        </div>
    </section>

    <section class="admin-card regions-states-panel">
        <div class="regions-panel-head">
            <div>
                <span class="regions-panel-kicker">Cobertura</span>
                <h3>Estados atendidos</h3>
                <p>Desativar um estado oculta todas as cidades vinculadas no checkout.</p>
            </div>
            <span class="regions-panel-count"><?= count($states) ?></span>
        </div>

        <?php if (!$states): ?>
            <div class="regions-empty">
                <i class="bi bi-map"></i>
                <strong>Nenhum estado cadastrado</strong>
                <span>Cadastre o primeiro estado atendido pela loja.</span>
            </div>
        <?php else: ?>
            <div class="regions-state-grid">
                <?php foreach ($states as $state): ?>
                    <article class="regions-state-card-v2">
                        <div class="regions-state-card-top">
                            <span class="regions-state-uf-v2"><?= e($state['uf']) ?></span>
                            <span class="regions-status-dot <?= (int) $state['active'] ? 'is-active' : '' ?>">
                                <?= (int) $state['active'] ? 'Ativo' : 'Oculto' ?>
                            </span>
                        </div>

                        <div class="regions-state-card-body">
                            <strong><?= e($state['name']) ?></strong>
                            <span>
                                <?= (int) $state['active_cities_count'] ?> de <?= (int) $state['cities_count'] ?>
                                cidade<?= (int) $state['cities_count'] === 1 ? '' : 's' ?> ativa<?= (int) $state['active_cities_count'] === 1 ? '' : 's' ?>
                            </span>
                        </div>

                        <div class="regions-state-progress">
                            <?php
                                $stateTotalCities = max(1, (int) $state['cities_count']);
                                $stateActivePercent = min(100, round(((int) $state['active_cities_count'] / $stateTotalCities) * 100));
                            ?>
                            <span style="width: <?= $stateActivePercent ?>%"></span>
                        </div>

                        <div class="regions-state-card-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-light border js-edit-state"
                                data-bs-toggle="modal"
                                data-bs-target="#stateModal"
                                data-id="<?= (int) $state['id'] ?>"
                                data-name="<?= e($state['name']) ?>"
                                data-uf="<?= e($state['uf']) ?>"
                                data-active="<?= (int) $state['active'] ?>"
                            >
                                <i class="bi bi-pencil me-1"></i>Editar
                            </button>

                            <form method="post" class="d-inline" onsubmit="return confirm('Excluir este estado?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_state">
                                <input type="hidden" name="id" value="<?= (int) $state['id'] ?>">
                                <button
                                    type="submit"
                                    class="btn btn-sm btn-light border text-danger"
                                    <?= (int) $state['cities_count'] > 0 ? 'disabled' : '' ?>
                                    title="<?= (int) $state['cities_count'] > 0 ? 'Remova as cidades antes de excluir' : 'Excluir estado' ?>"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-card regions-cities-panel">
        <div class="regions-panel-head">
            <div>
                <span class="regions-panel-kicker">Entregas</span>
                <h3>Cidades atendidas</h3>
                <p>Estas são as cidades que o cliente pode selecionar ao finalizar um pedido.</p>
            </div>
            <span class="regions-panel-count"><?= count($cities) ?></span>
        </div>

        <?php if (!$cities): ?>
            <div class="regions-empty large">
                <i class="bi bi-geo-alt"></i>
                <strong>Nenhuma cidade cadastrada</strong>
                <span>Cadastre as cidades onde a Flora Camily realiza entregas.</span>
            </div>
        <?php else: ?>
            <div class="regions-city-grid">
                <?php foreach ($cities as $city): ?>
                    <?php $cityVisible = (int) $city['active'] === 1 && (int) $city['state_active'] === 1; ?>
                    <article class="regions-city-card <?= $cityVisible ? '' : 'is-muted' ?>">
                        <div class="regions-city-card-icon">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>

                        <div class="regions-city-card-main">
                            <div class="regions-city-card-title">
                                <strong><?= e($city['name']) ?></strong>
                                <span class="regions-status-dot <?= $cityVisible ? 'is-active' : '' ?>">
                                    <?= $cityVisible ? 'Ativa' : 'Oculta' ?>
                                </span>
                            </div>
                            <span class="regions-city-location">
                                <?= e($city['state_name']) ?> · <?= e($city['state_uf']) ?>
                            </span>
                        </div>

                        <div class="regions-city-card-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-light border js-edit-city"
                                data-bs-toggle="modal"
                                data-bs-target="#cityModal"
                                data-id="<?= (int) $city['id'] ?>"
                                data-state-id="<?= (int) $city['state_id'] ?>"
                                data-name="<?= e($city['name']) ?>"
                                data-active="<?= (int) $city['active'] ?>"
                                title="Editar cidade"
                            >
                                <i class="bi bi-pencil"></i>
                            </button>

                            <form method="post" class="d-inline" onsubmit="return confirm('Excluir esta cidade?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_city">
                                <input type="hidden" name="id" value="<?= (int) $city['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-light border text-danger" title="Excluir cidade">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="modal fade" id="stateModal" tabindex="-1" aria-labelledby="stateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="eyebrow">Área de atendimento</span>
                    <h2 class="modal-title h4 mt-2" id="stateModalLabel">Novo estado</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form method="post" id="stateForm">
                <div class="modal-body pt-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_state">
                    <input type="hidden" name="id" id="stateId" value="0">

                    <div class="mb-3">
                        <label for="stateName" class="form-label fw-semibold">Estado</label>
                        <input type="text" name="name" id="stateName" class="form-control" maxlength="120" required placeholder="Ex.: Minas Gerais">
                    </div>

                    <div class="mb-3">
                        <label for="stateUf" class="form-label fw-semibold">UF</label>
                        <input type="text" name="uf" id="stateUf" class="form-control text-uppercase" minlength="2" maxlength="2" required placeholder="MG">
                    </div>

                    <div class="category-visibility-option">
                        <div>
                            <strong>Atender neste estado</strong>
                            <small>Se desativado, nenhuma cidade deste estado aparecerá no checkout.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="active" id="stateActive" checked>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand px-4" type="submit">Salvar estado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="cityModal" tabindex="-1" aria-labelledby="cityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="eyebrow">Área de atendimento</span>
                    <h2 class="modal-title h4 mt-2" id="cityModalLabel">Nova cidade</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form method="post" id="cityForm">
                <div class="modal-body pt-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_city">
                    <input type="hidden" name="id" id="cityId" value="0">

                    <div class="mb-3">
                        <label for="cityState" class="form-label fw-semibold">Estado</label>
                        <select name="state_id" id="cityState" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?= (int) $state['id'] ?>"><?= e($state['name']) ?> (<?= e($state['uf']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="cityName" class="form-label fw-semibold">Cidade</label>
                        <input type="text" name="name" id="cityName" class="form-control" maxlength="140" required placeholder="Ex.: Conselheiro Lafaiete">
                    </div>

                    <div class="category-visibility-option">
                        <div>
                            <strong>Atender nesta cidade</strong>
                            <small>Somente cidades ativas ficam disponíveis para o cliente no checkout.</small>
                        </div>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="active" id="cityActive" checked>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand px-4" type="submit">Salvar cidade</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const stateForm = document.getElementById('stateForm');
    const stateTitle = document.getElementById('stateModalLabel');
    const stateId = document.getElementById('stateId');
    const stateName = document.getElementById('stateName');
    const stateUf = document.getElementById('stateUf');
    const stateActive = document.getElementById('stateActive');

    document.querySelectorAll('[data-state-new]').forEach((button) => {
        button.addEventListener('click', () => {
            stateForm.reset();
            stateTitle.textContent = 'Novo estado';
            stateId.value = '0';
            stateActive.checked = true;
        });
    });

    document.querySelectorAll('.js-edit-state').forEach((button) => {
        button.addEventListener('click', () => {
            stateForm.reset();
            stateTitle.textContent = 'Editar estado';
            stateId.value = button.dataset.id || '0';
            stateName.value = button.dataset.name || '';
            stateUf.value = button.dataset.uf || '';
            stateActive.checked = button.dataset.active === '1';
        });
    });

    const cityForm = document.getElementById('cityForm');
    const cityTitle = document.getElementById('cityModalLabel');
    const cityId = document.getElementById('cityId');
    const cityState = document.getElementById('cityState');
    const cityName = document.getElementById('cityName');
    const cityActive = document.getElementById('cityActive');

    document.querySelectorAll('[data-city-new]').forEach((button) => {
        button.addEventListener('click', () => {
            cityForm.reset();
            cityTitle.textContent = 'Nova cidade';
            cityId.value = '0';
            cityActive.checked = true;
        });
    });

    document.querySelectorAll('.js-edit-city').forEach((button) => {
        button.addEventListener('click', () => {
            cityForm.reset();
            cityTitle.textContent = 'Editar cidade';
            cityId.value = button.dataset.id || '0';
            cityState.value = button.dataset.stateId || '';
            cityName.value = button.dataset.name || '';
            cityActive.checked = button.dataset.active === '1';
        });
    });

    stateUf.addEventListener('input', () => {
        stateUf.value = stateUf.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
    });
})();
</script>

<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
