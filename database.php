<?php
declare(strict_types=1);

const STORE_DB = __DIR__ . '/data/store.sqlite';

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    if (!is_dir(dirname(STORE_DB))) mkdir(dirname(STORE_DB), 0755, true);
    $pdo = new PDO('sqlite:' . STORE_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS sizes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL,
        stock INTEGER NOT NULL DEFAULT 0, measurements TEXT NOT NULL DEFAULT "", active INTEGER NOT NULL DEFAULT 1
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT UNIQUE NOT NULL,
        kind TEXT NOT NULL CHECK(kind IN ("percent","fixed")), value INTEGER NOT NULL,
        starts_at TEXT, ends_at TEXT, active INTEGER NOT NULL DEFAULT 1
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL,
        phone TEXT, password_hash TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS carts (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT UNIQUE NOT NULL, customer_id INTEGER,
        status TEXT NOT NULL DEFAULT "open", created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS cart_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT, cart_id INTEGER NOT NULL, size_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL DEFAULT 1, FOREIGN KEY(cart_id) REFERENCES carts(id), FOREIGN KEY(size_id) REFERENCES sizes(id)
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT, order_nsu TEXT UNIQUE NOT NULL, customer_id INTEGER,
        cart_id INTEGER, subtotal_cents INTEGER NOT NULL, discount_cents INTEGER NOT NULL DEFAULT 0,
        total_cents INTEGER NOT NULL, coupon_code TEXT, gateway_url TEXT, status TEXT NOT NULL DEFAULT "pending",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS shipments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER UNIQUE NOT NULL,
        service_code TEXT NOT NULL,
        service_name TEXT NOT NULL,
        shipping_cents INTEGER NOT NULL DEFAULT 0,
        destination_cep TEXT NOT NULL,
        address_line TEXT,
        address_number TEXT,
        address_extra TEXT,
        neighborhood TEXT,
        city TEXT,
        state TEXT,
        tracking_code TEXT,
        shipped_at TEXT,
        tracking_email_sent_at TEXT,
        FOREIGN KEY(order_id) REFERENCES orders(id)
    )');
    $defaults = [
        'product_name' => 'Camiseta 406 Montana',
        'price_cents' => '18990',
        'promo_price_cents' => '',
        'product_description' => 'Drop 001 — 406 Montana',
        'measurements_title' => 'Medidas da camiseta'
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)');
    foreach ($defaults as $key => $value) $stmt->execute([$key, $value]);
    return $pdo;
}
function setting(string $key, string $fallback = ''): string {
    $stmt = db()->prepare('SELECT value FROM settings WHERE key=?');
    $stmt->execute([$key]);
    return (string)($stmt->fetchColumn() ?: $fallback);
}
function save_setting(string $key, string $value): void {
    db()->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$key, $value]);
}
function money(int $cents): string { return 'R$ ' . number_format($cents / 100, 2, ',', '.'); }
function current_price(): int {
    $promo = (int) setting('promo_price_cents');
    return $promo > 0 ? $promo : (int) setting('price_cents', '0');
}
