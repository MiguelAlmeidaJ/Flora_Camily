<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const SITE_NAME = 'Flora Camily';
const SITE_TAGLINE = 'Flores que fazem histórias';
const WHATSAPP_NUMBER = '5532999999999'; // Altere para o WhatsApp real, somente números.

const DB_HOST = 'localhost';
const DB_NAME = 'flora_camily';
const DB_USER = 'root';
const DB_PASS = '';

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
        exit('Não foi possível conectar ao banco de dados. Verifique o arquivo config.php.');
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

function asset(string $path): string
{
    return ltrim($path, '/');
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
    $stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND active = 1");
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

function adminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
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

    return 'assets/img/logo.png';
}
