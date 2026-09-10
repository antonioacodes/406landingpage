<?php
declare(strict_types=1);
require __DIR__ . '/database.php';

function infinitepay_config(): array {
    $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $handle = ltrim(trim((string)($config['infinitepay_handle'] ?? 'f406')), '$');
    return ['handle' => $handle !== '' ? $handle : 'f406'];
}
function verify_payment(string $orderNsu, string $transactionNsu, string $slug): array {
    $payload = [
        'handle' => infinitepay_config()['handle'],
        'order_nsu' => $orderNsu,
        'transaction_nsu' => $transactionNsu,
        'slug' => $slug,
    ];
    $curl = curl_init('https://api.checkout.infinitepay.io/payment_check');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = (string)curl_exec($curl);
    $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $data = json_decode($raw, true);
    return ($code >= 200 && $code < 300 && is_array($data)) ? $data : [];
}

$body = json_decode((string)file_get_contents('php://input'), true);
$orderNsu = trim((string)($body['order_nsu'] ?? ''));
$transactionNsu = trim((string)($body['transaction_nsu'] ?? ''));
$slug = trim((string)($body['invoice_slug'] ?? $body['slug'] ?? ''));

if ($orderNsu === '' || $transactionNsu === '' || $slug === '') {
    http_response_code(400);
    exit('invalid payload');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, cart_id, total_cents, status FROM orders WHERE order_nsu=? LIMIT 1');
$stmt->execute([$orderNsu]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    http_response_code(400);
    exit('order not found');
}

$verification = verify_payment($orderNsu, $transactionNsu, $slug);
$amount = (int)($verification['amount'] ?? 0);
if (empty($verification['success']) || empty($verification['paid']) || $amount !== (int)$order['total_cents']) {
    http_response_code(400);
    exit('payment not confirmed');
}

$pdo->beginTransaction();
$paidUpdate = $pdo->prepare("UPDATE orders SET status='paid' WHERE id=? AND status!='paid'");
$paidUpdate->execute([(int)$order['id']]);
if ($paidUpdate->rowCount() > 0 && !empty($order['cart_id'])) {
    $pdo->prepare(
        'UPDATE sizes SET stock = MAX(0, stock - COALESCE((
            SELECT SUM(ci.quantity) FROM cart_items ci
            WHERE ci.cart_id=? AND ci.size_id=sizes.id
        ),0)) WHERE id IN (SELECT size_id FROM cart_items WHERE cart_id=?)'
    )->execute([(int)$order['cart_id'], (int)$order['cart_id']]);
}
if (!empty($order['cart_id'])) {
    $pdo->prepare("UPDATE carts SET status='converted', updated_at=CURRENT_TIMESTAMP WHERE id=?")
        ->execute([(int)$order['cart_id']]);
}
$pdo->commit();

http_response_code(200);
echo 'ok';
