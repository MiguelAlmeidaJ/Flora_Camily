<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

const SITE_NAME = 'Flora Camily';
const SITE_TAGLINE = 'Flores que fazem histórias';

$settings = [
    'whatsapp_number' => '5532999999999',
    'store_email' => '',
    'from_email' => '',
    'smtp' => [
        'enabled' => false,
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'auth' => true,
        'username' => '',
        'password' => '',
        'from_name' => 'Flora Camily',
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'flora_camily',
        'user' => 'root',
        'pass' => '',
    ],
];

$localConfigFile = __DIR__ . '/config.local.php';
if (is_file($localConfigFile)) {
    $localSettings = require $localConfigFile;
    if (is_array($localSettings)) {
        $settings = array_replace_recursive($settings, $localSettings);
    }
}

define('WHATSAPP_NUMBER', (string) preg_replace('/\D+/', '', (string) $settings['whatsapp_number']));
define('STORE_EMAIL', trim((string) $settings['store_email']));
define('FROM_EMAIL', trim((string) $settings['from_email']));
define('SMTP_ENABLED', (bool) ($settings['smtp']['enabled'] ?? false));
define('SMTP_HOST', trim((string) ($settings['smtp']['host'] ?? '')));
define('SMTP_PORT', (int) ($settings['smtp']['port'] ?? 587));
define('SMTP_ENCRYPTION', strtolower(trim((string) ($settings['smtp']['encryption'] ?? 'tls'))));
define('SMTP_AUTH', (bool) ($settings['smtp']['auth'] ?? true));
define('SMTP_USERNAME', trim((string) ($settings['smtp']['username'] ?? '')));
define('SMTP_PASSWORD', (string) ($settings['smtp']['password'] ?? ''));
define('SMTP_FROM_NAME', trim((string) ($settings['smtp']['from_name'] ?? SITE_NAME)) ?: SITE_NAME);
define('DB_HOST', (string) $settings['db']['host']);
define('DB_NAME', (string) $settings['db']['name']);
define('DB_USER', (string) $settings['db']['user']);
define('DB_PASS', (string) $settings['db']['pass']);
unset($settings, $localSettings, $localConfigFile);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Não foi possível conectar ao banco de dados. Verifique o arquivo config.local.php.');
    }

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function money(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function excerpt(?string $text, int $limit = 115): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit
            ? rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '...'
            : $text;
    }

    return strlen($text) > $limit ? rtrim(substr($text, 0, $limit)) . '...' : $text;
}

function slugify(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $normalized = $text;
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $normalized = $converted;
        }
    }

    $normalized = strtolower($normalized);
    $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
    return trim($normalized, '-');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function appSetting(string $key, string $default = ''): string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value === false || $value === null ? $default : (string) $value;
    } catch (Throwable $e) {
        $cache[$key] = $default;
    }

    return $cache[$key];
}

function setAppSetting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$key, $value]);
}

function siteLogo(): string
{
    $path = appSetting('site_logo', 'assets/img/logo.svg');
    return is_file(__DIR__ . '/' . ltrim($path, '/')) ? $path : 'assets/img/logo.svg';
}

function siteFavicon(): string
{
    $path = appSetting('site_favicon', 'assets/img/logo.svg');
    return is_file(__DIR__ . '/' . ltrim($path, '/')) ? $path : 'assets/img/logo.svg';
}

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cartCount(): int
{
    return array_sum(array_map(static fn ($q) => (int) $q, cart()));
}

function cartProducts(): array
{
    $cart = cart();
    if (!$cart) {
        return [];
    }

    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), static fn ($id) => $id > 0));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id IN ($placeholders) AND p.active = 1"
    );
    $stmt->execute($ids);

    $products = [];
    foreach ($stmt->fetchAll() as $product) {
        $qty = max(1, (int) ($cart[$product['id']] ?? 1));
        $product['qty'] = $qty;
        $product['subtotal'] = (float) $product['price'] * $qty;
        $products[] = $product;
    }

    return $products;
}

function cartTotal(): float
{
    return array_reduce(
        cartProducts(),
        static fn (float $total, array $item) => $total + (float) $item['subtotal'],
        0.0
    );
}

function productCategoryName(array $product): string
{
    $name = trim((string) ($product['category_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return trim((string) ($product['category'] ?? 'Homenagens florais')) ?: 'Homenagens florais';
}

function crownCategories(bool $onlyActive = true): array
{
    static $activeCache = null;
    static $allCache = null;

    if ($onlyActive && is_array($activeCache)) {
        return $activeCache;
    }
    if (!$onlyActive && is_array($allCache)) {
        return $allCache;
    }

    try {
        $sql = 'SELECT * FROM categories';
        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        $rows = db()->query($sql)->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }

    if ($onlyActive) {
        $activeCache = $rows;
    } else {
        $allCache = $rows;
    }

    return $rows;
}

function adminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

function adminRole(): string
{
    if (!adminLoggedIn()) {
        return '';
    }

    if (!empty($_SESSION['admin_role'])) {
        return (string) $_SESSION['admin_role'];
    }

    try {
        $stmt = db()->prepare('SELECT role FROM admin_users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['admin_id']]);
        $role = (string) ($stmt->fetchColumn() ?: 'admin');
        $_SESSION['admin_role'] = $role;
        return $role;
    } catch (Throwable $e) {
        return 'admin';
    }
}

function isDev(): bool
{
    return adminRole() === 'dev';
}

function requireAdmin(): void
{
    if (!adminLoggedIn()) {
        redirect('admin.php');
    }
}

function requireDev(): void
{
    requireAdmin();
    if (!isDev()) {
        http_response_code(403);
        exit('Acesso restrito ao perfil dev.');
    }
}

function appLog(string $action, array $context = [], string $level = 'info'): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO app_logs (level, action, context_json, actor_user_id, actor_username, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            substr($level, 0, 20),
            substr($action, 0, 120),
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            !empty($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null,
            !empty($_SESSION['admin_username']) ? (string) $_SESSION['admin_username'] : null,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);
    } catch (Throwable $e) {
        // Logging must never interrupt the store flow.
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function productImage(?string $image): string
{
    if ($image && is_file(__DIR__ . '/' . $image)) {
        return $image;
    }

    return siteLogo();
}

function storeWhatsAppUrl(string $message = 'Olá! Gostaria de comprar uma homenagem floral na Flora Camily.'): string
{
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
}

function orderStatusOptions(): array
{
    return [
        'novo' => 'Novo pedido',
        'aguardando_ajuste' => 'Aguardando ajuste',
        'em_preparacao' => 'Em preparação',
        'em_entrega' => 'Em entrega',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ];
}

function orderStatusLabel(string $status): string
{
    return orderStatusOptions()[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function orderStatusClass(string $status): string
{
    return match ($status) {
        'novo' => 'text-bg-danger',
        'aguardando_ajuste' => 'text-bg-warning',
        'em_preparacao' => 'text-bg-primary',
        'em_entrega' => 'text-bg-info',
        'entregue' => 'text-bg-success',
        'cancelado' => 'text-bg-secondary',
        default => 'text-bg-light',
    };
}

function customerWhatsAppUrl(string $phone, int $orderId): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if ($digits === '') {
        return '#';
    }

    if (!str_starts_with($digits, '55')) {
        $digits = '55' . $digits;
    }

    $message = 'Olá! Somos da Flora Camily e estamos entrando em contato sobre o pedido #' . $orderId . '.';
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function smtpConfigured(): bool
{
    if (!SMTP_ENABLED || SMTP_HOST === '' || SMTP_PORT <= 0) {
        return false;
    }

    if (SMTP_AUTH && (SMTP_USERNAME === '' || SMTP_PASSWORD === '')) {
        return false;
    }

    $from = FROM_EMAIL !== '' ? FROM_EMAIL : SMTP_USERNAME;
    return $from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false;
}

function sendAppEmail(string $to, string $subject, string $body, bool $isHtml = false): bool
{
    if (
        !filter_var($to, FILTER_VALIDATE_EMAIL) ||
        !smtpConfigured() ||
        !class_exists(\PHPMailer\PHPMailer\PHPMailer::class)
    ) {
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';

        if (SMTP_AUTH) {
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
        }

        if (SMTP_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $fromEmail = FROM_EMAIL !== '' ? FROM_EMAIL : SMTP_USERNAME;
        $mail->setFrom($fromEmail, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;

        if ($isHtml) {
            $mail->isHTML(true);
            $mail->Body = $body;
            $mail->AltBody = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        } else {
            $mail->isHTML(false);
            $mail->Body = $body;
        }

        return $mail->send();
    } catch (Throwable $e) {
        appLog('email.smtp_error', [
            'to' => $to,
            'subject' => $subject,
            'message' => $e->getMessage(),
        ], 'warning');
        return false;
    }
}

function sendNewOrderEmail(array $order, array $items): bool
{
    if (STORE_EMAIL === '' || !filter_var(STORE_EMAIL, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $orderId = (int) ($order['id'] ?? 0);
    $subject = 'Novo pedido #' . $orderId . ' - Flora Camily';

    $lines = [
        'Um novo pedido foi realizado no site da Flora Camily.',
        '',
        'Pedido: #' . $orderId,
        'Cliente: ' . ($order['customer_name'] ?? ''),
        'Telefone: ' . ($order['customer_phone'] ?? ''),
        'E-mail: ' . ($order['customer_email'] ?? ''),
        'Homenageado(a): ' . ($order['honoree_name'] ?? ''),
        'Entrega: ' . trim(($order['city'] ?? '') . '/' . ($order['state'] ?? '')),
        'Local: ' . ($order['delivery_place'] ?? ''),
        'Data: ' . ($order['delivery_date'] ?? ''),
        'Hora: ' . ($order['delivery_time'] ?? ''),
        '',
        'Itens:',
    ];

    foreach ($items as $item) {
        $lines[] = '- ' . (int) $item['qty'] . 'x ' . $item['name'] . ' — ' . money((float) $item['subtotal']);
    }

    $lines[] = '';
    $lines[] = 'Subtotal dos produtos: ' . money((float) ($order['products_total'] ?? 0));
    $lines[] = 'Frete: a definir pela equipe';
    $lines[] = '';
    $lines[] = 'Acesse o painel administrativo para analisar e atualizar o pedido.';

    return sendAppEmail(STORE_EMAIL, $subject, implode("\r\n", $lines), false);
}
