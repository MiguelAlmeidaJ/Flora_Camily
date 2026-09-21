<?php
require __DIR__ . '/config.php';
requireAdmin();

$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);

function uploadSystemAsset(string $field, string $prefix): ?string
{
    if (empty($_FILES[$field]['name'])) return null;

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do arquivo.');
    }
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('O arquivo deve ter no máximo 3 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Use JPG, PNG, WEBP ou ICO.');
    }

    $dir = __DIR__ . '/uploads/system';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível preparar a pasta de arquivos do sistema.');
    }

    $filename = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        throw new RuntimeException('Não foi possível salvar o arquivo.');
    }

    return 'uploads/system/' . $filename;
}

function streamDatabaseBackup(): never
{
    $filename = 'flora_camily_backup_' . date('Y-m-d_H-i-s') . '.sql';
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    echo "-- Backup Flora Camily\n-- Gerado em " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";

    $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', (string) $table);
        $createRow = db()->query('SHOW CREATE TABLE `' . $safeTable . '`')->fetch(PDO::FETCH_NUM);
        if (!$createRow) continue;

        echo "DROP TABLE IF EXISTS `{$safeTable}`;\n";
        echo $createRow[1] . ";\n\n";

        $rows = db()->query('SELECT * FROM `' . $safeTable . '`');
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map(static fn ($column) => '`' . str_replace('`', '``', (string) $column) . '`', array_keys($row));
            $values = array_map(static fn ($value) => $value === null ? 'NULL' : db()->quote((string) $value), array_values($row));
            echo 'INSERT INTO `' . $safeTable . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n";
        }
        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

function resetDatabaseForProduction(): array
{
    $pdo = db();

    $productImages = [];
    try {
        $productImages = $pdo->query(
            "SELECT image FROM products WHERE image IS NOT NULL AND image <> ''"
        )->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $productImages = [];
    }

    $tablesToClear = [
        'order_items',
        'orders',
        'products',
        'service_cities',
        'service_states',
        'app_logs',
    ];

    $existingTables = array_map(
        'strval',
        $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
    );

    $cleared = [];
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    try {
        foreach ($tablesToClear as $table) {
            if (!in_array($table, $existingTables, true)) continue;
            $safeTable = str_replace('`', '``', $table);
            $pdo->exec('DELETE FROM `' . $safeTable . '`');
            $cleared[] = $table;
        }

        foreach ($cleared as $table) {
            $safeTable = str_replace('`', '``', $table);
            try {
                $pdo->exec('ALTER TABLE `' . $safeTable . '` AUTO_INCREMENT = 1');
            } catch (Throwable $e) {
                // Algumas tabelas podem não possuir AUTO_INCREMENT.
            }
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    $removedImages = 0;
    foreach ($productImages as $image) {
        $image = (string) $image;
        if (
            preg_match('#^uploads/produto_[A-Za-z0-9._-]+$#', $image) === 1 &&
            is_file(__DIR__ . '/' . $image) &&
            @unlink(__DIR__ . '/' . $image)
        ) {
            $removedImages++;
        }
    }

    unset($_SESSION['cart']);

    return [
        'tables' => $cleared,
        'images_removed' => $removedImages,
    ];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_branding') {
            $logo = uploadSystemAsset('site_logo', 'logo');
            $favicon = uploadSystemAsset('site_favicon', 'favicon');
            if ($logo !== null) setAppSetting('site_logo', $logo);
            if ($favicon !== null) setAppSetting('site_favicon', $favicon);
            if ($logo === null && $favicon === null) throw new RuntimeException('Selecione uma nova logo ou favicon.');
            appLog('settings.branding', ['logo_changed' => $logo !== null, 'favicon_changed' => $favicon !== null]);
            $_SESSION['admin_flash'] = 'Identidade do site atualizada.';
            redirect('admin-configuracoes.php');
        }

        if ($action === 'reset_branding') {
            setAppSetting('site_logo', 'assets/img/logo.svg');
            setAppSetting('site_favicon', 'assets/img/logo.svg');
            appLog('settings.branding_reset');
            $_SESSION['admin_flash'] = 'Logo e favicon restaurados para o padrão.';
            redirect('admin-configuracoes.php');
        }

        if ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            if (strlen($new) < 8) throw new RuntimeException('A nova senha precisa ter pelo menos 8 caracteres.');
            $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id=?');
            $stmt->execute([(int) $_SESSION['admin_id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($current, $hash)) throw new RuntimeException('A senha atual está incorreta.');
            db()->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
            appLog('user.password_change');
            $_SESSION['admin_flash'] = 'Senha alterada com sucesso.';
            redirect('admin-configuracoes.php#seguranca');
        }

        if (isDev() && $action === 'run_migration') {
            $migration = basename((string) ($_POST['migration'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9._-]+\.sql$/', $migration)) throw new RuntimeException('Migration inválida.');
            $path = __DIR__ . '/migrations/' . $migration;
            if (!is_file($path)) throw new RuntimeException('Arquivo de migration não encontrado.');
            $check = db()->prepare('SELECT COUNT(*) FROM migration_history WHERE migration_name=?');
            $check->execute([$migration]);
            if ((int) $check->fetchColumn() > 0) throw new RuntimeException('Esta migration já foi aplicada.');
            $sql = file_get_contents($path);
            if ($sql === false || trim($sql) === '') throw new RuntimeException('Migration vazia ou ilegível.');
            db()->exec($sql);
            db()->prepare('INSERT INTO migration_history (migration_name, applied_by) VALUES (?, ?)')->execute([$migration, (string) $_SESSION['admin_username']]);
            appLog('migration.run', ['migration' => $migration]);
            $_SESSION['admin_flash'] = 'Migration aplicada: ' . $migration;
            redirect('admin-configuracoes.php#migrations');
        }

        if (isDev() && $action === 'clear_cache') {
            $cacheDir = __DIR__ . '/storage/cache';
            $removed = 0;
            if (is_dir($cacheDir)) {
                foreach (glob($cacheDir . '/*') ?: [] as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep' && @unlink($file)) $removed++;
                }
            }
            if (function_exists('opcache_reset')) @opcache_reset();
            appLog('system.clear_cache', ['files_removed' => $removed]);
            $_SESSION['admin_flash'] = 'Cache limpo com sucesso.';
            redirect('admin-configuracoes.php#sistema');
        }

        if (isDev() && $action === 'backup_database') {
            appLog('system.database_backup');
            streamDatabaseBackup();
        }
        if (isDev() && $action === 'reset_database_production') {
            $confirmation = strtoupper(trim((string) ($_POST['confirmation'] ?? '')));

            if ($confirmation !== 'PRODUCAO') {
                throw new RuntimeException('Digite PRODUCAO para confirmar a limpeza do banco.');
            }

            $result = resetDatabaseForProduction();

            appLog('system.production_reset', [
                'tables_cleared' => $result['tables'],
                'product_images_removed' => $result['images_removed'],
                'preserved' => ['admin_users', 'categories', 'app_settings', 'migration_history'],
            ], 'warning');

            $_SESSION['admin_flash'] = 'Banco preparado para produção. Usuários, categorias e metadados técnicos foram preservados.';
            redirect('admin-configuracoes#sistema');
        }

        if (isDev() && $action === 'test_email') {
            if (STORE_EMAIL === '' || !filter_var(STORE_EMAIL, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Configure store_email no config.local.php antes de testar.');
            }
            if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                throw new RuntimeException('PHPMailer não está instalado. Execute composer install no servidor.');
            }
            if (!smtpConfigured()) {
                throw new RuntimeException('SMTP ainda não está configurado corretamente no config.local.php.');
            }

            $sent = sendAppEmail(
                STORE_EMAIL,
                'Teste SMTP - Flora Camily',
                "Teste de SMTP da Flora Camily.\r\n\r\nSe você recebeu esta mensagem, a configuração está funcionando.",
                false
            );

            appLog('tools.email_test', [
                'success' => $sent,
                'destination' => STORE_EMAIL,
                'transport' => 'smtp',
                'host' => SMTP_HOST,
                'port' => SMTP_PORT,
            ], $sent ? 'info' : 'warning');

            $_SESSION['admin_flash'] = $sent
                ? 'O servidor SMTP aceitou a mensagem para envio. Isso não garante a entrega na caixa de destino; confira também spam e autenticação SPF/DKIM.'
                : 'Não foi possível enviar o teste. Consulte os Logs do DEV para ver o erro SMTP.';
            redirect('admin-configuracoes.php#sistema');
        }
    } catch (Throwable $e) {
        appLog('settings.error', ['action' => $action, 'message' => $e->getMessage()], 'error');
        $flash = $e->getMessage();
    }
}

$migrations = [];
if (isDev()) {
    $applied = [];
    try { $applied = db()->query('SELECT migration_name, applied_at, applied_by FROM migration_history')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC); } catch (Throwable $e) {}
    foreach (glob(__DIR__ . '/migrations/*.sql') ?: [] as $file) {
        $name = basename($file);
        $migrations[] = ['name' => $name, 'applied' => isset($applied[$name]), 'meta' => $applied[$name] ?? null];
    }
}

$adminPage = 'configuracoes';
$adminTitle = 'Configurações';
$adminSubtitle = isDev() ? 'Identidade, segurança e manutenção técnica do sistema.' : 'Personalize o site e gerencie sua conta.';
require __DIR__ . '/includes/admin-shell-start.php';
?>
<?php if ($flash): ?><div class="alert alert-info rounded-4"><?= e($flash) ?></div><?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="admin-card p-4 h-100">
            <span class="eyebrow">Aparência</span>
            <h2 class="h4 mt-2 mb-1">Logo e favicon</h2>
            <p class="small text-secondary mb-4">Altere a identidade exibida no site e no painel sem editar arquivos manualmente.</p>
            <div class="row g-4 align-items-center mb-4">
                <div class="col-sm-6"><div class="admin-brand-preview"><small>Logo atual</small><img src="<?= e(siteLogo()) ?>" alt="Logo atual"></div></div>
                <div class="col-sm-6"><div class="admin-brand-preview"><small>Favicon atual</small><img src="<?= e(siteFavicon()) ?>" alt="Favicon atual" class="favicon-preview"></div></div>
            </div>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <?= csrfField() ?><input type="hidden" name="action" value="save_branding">
                <div class="col-md-6"><label class="form-label fw-semibold">Nova logo</label><input type="file" name="site_logo" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG ou WEBP.</div></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Novo favicon</label><input type="file" name="site_favicon" class="form-control" accept="image/png,image/x-icon,image/webp"><div class="form-text">PNG, WEBP ou ICO.</div></div>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-brand" type="submit"><i class="bi bi-cloud-arrow-up me-1"></i>Salvar identidade</button></form><form method="post" onsubmit="return confirm('Restaurar logo e favicon padrão?');"><?= csrfField() ?><input type="hidden" name="action" value="reset_branding"><button class="btn btn-light border" type="submit">Restaurar padrão</button></form></div>
        </div>
    </div>
    <div class="col-xl-5" id="seguranca">
        <div class="admin-card p-4 h-100">
            <span class="eyebrow">Conta</span>
            <h2 class="h4 mt-2 mb-4">Alterar senha</h2>
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="change_password">
                <div class="mb-3"><label class="form-label fw-semibold">Senha atual</label><input type="password" name="current_password" class="form-control" required></div>
                <div class="mb-4"><label class="form-label fw-semibold">Nova senha</label><input type="password" name="new_password" class="form-control" minlength="8" required><div class="form-text">Mínimo de 8 caracteres.</div></div>
                <button class="btn btn-outline-brand w-100" type="submit"><i class="bi bi-shield-lock me-1"></i>Alterar senha</button>
            </form>
        </div>
    </div>
</div>

<?php if (isDev()): ?>
<div class="admin-card p-4 mb-4" id="sistema">
    <span class="eyebrow">Sistema</span>
    <h2 class="h4 mt-2 mb-1">Ferramentas técnicas</h2>
    <p class="small text-secondary mb-4">Ações restritas ao perfil DEV.</p>
    <div class="row g-3">
        <div class="col-md-6 col-xl-3"><div class="admin-system-tool"><span><i class="bi bi-database-down"></i></span><div><strong>Backup do banco</strong><small>Baixa um arquivo SQL completo.</small></div><form method="post" class="mt-auto w-100"><?= csrfField() ?><input type="hidden" name="action" value="backup_database"><button class="btn btn-dark btn-sm w-100" type="submit">Gerar backup</button></form></div></div>
        <div class="col-md-6 col-xl-3"><div class="admin-system-tool"><span><i class="bi bi-lightning"></i></span><div><strong>Limpar cache</strong><small>Limpa cache local e OPcache quando disponível.</small></div><form method="post" class="mt-auto w-100"><?= csrfField() ?><input type="hidden" name="action" value="clear_cache"><button class="btn btn-light border btn-sm w-100" type="submit">Limpar cache</button></form></div></div>
        <div class="col-md-6 col-xl-3"><div class="admin-system-tool"><span><i class="bi bi-envelope-check"></i></span><div><strong>SMTP</strong><small><?php if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)): ?>PHPMailer não instalado<?php elseif (!smtpConfigured()): ?>Configuração pendente<?php else: ?><?= e(SMTP_HOST) ?>:<?= SMTP_PORT ?> · <?= strtoupper(e(SMTP_ENCRYPTION ?: 'sem criptografia')) ?><?php endif; ?></small></div><form method="post" class="mt-auto w-100"><?= csrfField() ?><input type="hidden" name="action" value="test_email"><button class="btn btn-light border btn-sm w-100" type="submit"><i class="bi bi-send me-1"></i>Testar SMTP</button></form></div></div>
        <div class="col-md-6 col-xl-3"><div class="admin-system-tool"><span><i class="bi bi-server"></i></span><div><strong>Ambiente</strong><small>PHP <?= e(PHP_VERSION) ?><br><?= e((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'Servidor')) ?></small></div><a href="admin-logs.php" class="btn btn-light border btn-sm w-100 mt-auto">Ver logs</a></div></div>
    </div>

    <div class="admin-production-reset mt-4">
        <div class="admin-production-reset-copy">
            <span class="admin-production-reset-icon"><i class="bi bi-database-x"></i></span>
            <div>
                <span class="admin-production-reset-kicker">Zona de risco</span>
                <strong>Preparar banco para produção</strong>
                <p>Remove pedidos, produtos, regiões e logs. Usuários, categorias, configurações do sistema e histórico de migrations são preservados.</p>
            </div>
        </div>

        <button type="button" class="btn btn-outline-danger admin-production-reset-button" data-bs-toggle="modal" data-bs-target="#productionResetModal">
            <i class="bi bi-exclamation-triangle me-1"></i>Resetar para produção
        </button>
    </div>
</div>

<div class="modal fade" id="productionResetModal" tabindex="-1" aria-labelledby="productionResetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content admin-modal-content admin-production-reset-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="admin-production-reset-kicker">Ação irreversível</span>
                    <h2 class="modal-title h4 mt-2" id="productionResetModalLabel">Resetar banco para produção</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form method="post" id="productionResetForm">
                <div class="modal-body pt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reset_database_production">

                    <div class="production-reset-warning">
                        <i class="bi bi-exclamation-octagon"></i>
                        <div>
                            <strong>Os dados removidos não poderão ser recuperados por esta tela.</strong>
                            <span>Faça um backup antes se houver qualquer dado que precise ser mantido.</span>
                        </div>
                    </div>

                    <div class="production-reset-columns">
                        <div class="production-reset-preserve">
                            <span>Será mantido</span>
                            <strong><i class="bi bi-check2"></i> Usuários</strong>
                            <strong><i class="bi bi-check2"></i> Categorias</strong>
                            <strong><i class="bi bi-check2"></i> Configurações técnicas</strong>
                            <strong><i class="bi bi-check2"></i> Histórico de migrations</strong>
                        </div>
                        <div class="production-reset-remove">
                            <span>Será apagado</span>
                            <strong>Pedidos e itens</strong>
                            <strong>Produtos e imagens</strong>
                            <strong>Estados e cidades</strong>
                            <strong>Logs do sistema</strong>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="productionResetConfirmation" class="form-label fw-semibold">Digite <code>PRODUCAO</code> para confirmar</label>
                        <input type="text" name="confirmation" id="productionResetConfirmation" class="form-control" autocomplete="off" required placeholder="PRODUCAO">
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="productionResetSubmit" disabled>
                        <i class="bi bi-database-x me-1"></i>Apagar dados e preparar produção
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('productionResetConfirmation');
    const submit = document.getElementById('productionResetSubmit');
    const form = document.getElementById('productionResetForm');

    if (!input || !submit || !form) return;

    const updateState = () => {
        submit.disabled = input.value.trim().toUpperCase() !== 'PRODUCAO';
    };

    input.addEventListener('input', updateState);

    form.addEventListener('submit', (event) => {
        if (input.value.trim().toUpperCase() !== 'PRODUCAO') {
            event.preventDefault();
            return;
        }

        if (!window.confirm('Confirmar reset definitivo do banco para produção?')) {
            event.preventDefault();
        }
    });
})();
</script>

<div class="admin-card p-4" id="migrations">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><div><span class="eyebrow">Banco de dados</span><h2 class="h4 mt-2 mb-1">Migrations</h2><p class="small text-secondary mb-0">Execute somente migrations pendentes e mantenha backup antes de alterações estruturais.</p></div><span class="badge text-bg-dark rounded-pill"><?= count($migrations) ?> arquivos</span></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Arquivo</th><th>Status</th><th>Aplicada em</th><th class="text-end">Ação</th></tr></thead><tbody>
    <?php if (!$migrations): ?><tr><td colspan="4" class="text-center py-4 text-secondary">Nenhuma migration encontrada.</td></tr><?php endif; ?>
    <?php foreach ($migrations as $migration): ?><tr><td><code><?= e($migration['name']) ?></code></td><td><span class="badge <?= $migration['applied'] ? 'text-bg-success' : 'text-bg-warning' ?>"><?= $migration['applied'] ? 'Aplicada' : 'Pendente' ?></span></td><td class="small text-secondary"><?= $migration['applied'] && !empty($migration['meta']['applied_at']) ? date('d/m/Y H:i', strtotime($migration['meta']['applied_at'])) : '—' ?></td><td class="text-end"><?php if (!$migration['applied']): ?><form method="post" class="d-inline" onsubmit="return confirm('Executar esta migration? Faça backup antes de continuar.');"><?= csrfField() ?><input type="hidden" name="action" value="run_migration"><input type="hidden" name="migration" value="<?= e($migration['name']) ?>"><button class="btn btn-sm btn-dark" type="submit"><i class="bi bi-play-fill me-1"></i>Executar</button></form><?php else: ?><span class="text-success"><i class="bi bi-check2-circle"></i></span><?php endif; ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/admin-shell-end.php'; ?>
