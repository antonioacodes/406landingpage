<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';

$orderNsu = trim((string)($_GET['order_nsu'] ?? ''));
$transactionNsu = trim((string)($_GET['transaction_nsu'] ?? ''));
$slug = trim((string)($_GET['slug'] ?? ''));
$receiptUrl = filter_var((string)($_GET['receipt_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
$paid = false;
$order = null;

if ($orderNsu !== '') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_nsu=? LIMIT 1');
    $stmt->execute([$orderNsu]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($order && $transactionNsu !== '' && $slug !== '') {
    $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $handle = ltrim(trim((string)($config['infinitepay_handle'] ?? 'f406')), '$') ?: 'f406';
    $payload = ['handle'=>$handle,'order_nsu'=>$orderNsu,'transaction_nsu'=>$transactionNsu,'slug'=>$slug];
    $curl = curl_init('https://api.checkout.infinitepay.io/payment_check');
    curl_setopt_array($curl, [
        CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>15,
    ]);
    $response = json_decode((string)curl_exec($curl), true);
    curl_close($curl);
    $paid = !empty($response['success']) && !empty($response['paid'])
        && (int)($response['amount'] ?? 0) === (int)$order['total_cents'];

    if ($paid) {
        $pdo = db();
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
        unset($_SESSION['cart_token']);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pedido 406</title>
<style>@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=Inter:wght@400;600;800&display=swap');*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at 70% 20%,#4a0c12,transparent 30%),#090807;color:#f1e8dc;font-family:Inter,Arial}.card{width:min(590px,91vw);padding:42px;background:#14110f;border:1px solid #3b2f29;box-shadow:0 30px 90px #0008}.eyebrow{font:10px "DM Mono";letter-spacing:.15em;color:#e96d70}h1{font:clamp(54px,10vw,88px)/.9 "Bebas Neue";margin:15px 0}.card p{color:#afa299;line-height:1.6}.order{padding:15px;margin:25px 0;background:#0b0908;border:1px solid #3b2f29;font:11px "DM Mono";letter-spacing:.05em}a{display:inline-block;padding:14px 18px;margin:8px 8px 0 0;background:#c32229;color:#fff;text-decoration:none;font:700 10px "DM Mono";letter-spacing:.09em}.secondary{background:#2b231f}</style></head>
<body><main class="card"><div class="eyebrow">406 STORE / PAGAMENTO</div>
<?php if ($paid): ?><h1>PAGAMENTO<br>CONFIRMADO.</h1><p>Seu pedido foi recebido. Enviaremos as próximas informações para o e-mail cadastrado.</p>
<?php else: ?><h1>PEDIDO EM<br>PROCESSAMENTO.</h1><p>A confirmação ainda não chegou. Se você já pagou, aguarde alguns instantes: o status será atualizado automaticamente.</p><?php endif; ?>
<?php if ($orderNsu): ?><div class="order">PEDIDO: <?=htmlspecialchars($orderNsu)?></div><?php endif; ?>
<a href="index.php">VOLTAR À LOJA</a><?php if ($receiptUrl): ?><a class="secondary" href="<?=htmlspecialchars($receiptUrl)?>" target="_blank" rel="noopener">VER COMPROVANTE</a><?php endif; ?>
</main></body></html>