<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';

$pdo = db();
$token = (string)($_SESSION['cart_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'], $_POST['item_id']) && $token !== '') {
    $stmt = $pdo->prepare(
        'DELETE FROM cart_items WHERE id=? AND cart_id=(SELECT id FROM carts WHERE token=?)'
    );
    $stmt->execute([(int)$_POST['item_id'], $token]);
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['size_id'])) {
    $sizeId = (int)$_POST['size_id'];
    $stmt = $pdo->prepare('SELECT id,stock FROM sizes WHERE id=? AND active=1');
    $stmt->execute([$sizeId]);
    $size = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($size && (int)$size['stock'] > 0) {
        if ($token === '') {
            $token = bin2hex(random_bytes(20));
            $_SESSION['cart_token'] = $token;
        }
        $pdo->prepare('INSERT OR IGNORE INTO carts(token) VALUES(?)')->execute([$token]);
        $cartStmt = $pdo->prepare('SELECT id FROM carts WHERE token=?');
        $cartStmt->execute([$token]);
        $cartId = (int)$cartStmt->fetchColumn();
        $existing = $pdo->prepare('SELECT id,quantity FROM cart_items WHERE cart_id=? AND size_id=? LIMIT 1');
        $existing->execute([$cartId, $sizeId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newQuantity = min((int)$size['stock'], (int)$row['quantity'] + 1);
            $pdo->prepare('UPDATE cart_items SET quantity=? WHERE id=?')->execute([$newQuantity, (int)$row['id']]);
        } else {
            $pdo->prepare('INSERT INTO cart_items(cart_id,size_id,quantity) VALUES(?,?,1)')->execute([$cartId, $sizeId]);
        }
        $pdo->prepare('UPDATE carts SET updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$cartId]);
    }
    header('Location: cart.php');
    exit;
}

$items = [];
if ($token !== '') {
    $stmt = $pdo->prepare(
        'SELECT ci.id,ci.quantity,s.name,s.stock
         FROM cart_items ci JOIN carts c ON c.id=ci.cart_id JOIN sizes s ON s.id=ci.size_id
         WHERE c.token=?'
    );
    $stmt->execute([$token]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$price = current_price();
$subtotal = array_sum(array_map(static fn(array $item): int => (int)$item['quantity'] * $price, $items));
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Carrinho — 406 Montana</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap');
:root{--red:#d1272f;--cream:#f1e8dc;--line:#392e29;--muted:#9f9389}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 80% 15%,#4a0c12,transparent 28%),#090807;color:var(--cream);font-family:Inter,Arial}a{color:inherit;text-decoration:none}.top{width:min(1080px,92vw);height:80px;margin:auto;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #ffffff22}.brand{font:42px "Bebas Neue";letter-spacing:.08em}.brand em{color:var(--red);font-style:normal}.back{font:10px "DM Mono";letter-spacing:.1em;color:#c5b8ae}main{width:min(1080px,92vw);margin:52px auto 80px}.eyebrow{font:10px "DM Mono";letter-spacing:.15em;color:#eb6b6f}h1{font:clamp(62px,9vw,112px)/.9 "Bebas Neue";margin:12px 0 34px}.cart-layout{display:grid;grid-template-columns:1fr 360px;gap:32px}.items,.summary{border:1px solid var(--line);background:#14110f}.item{display:grid;grid-template-columns:120px 1fr auto;gap:20px;align-items:center;padding:20px;border-bottom:1px solid var(--line)}.item:last-child{border-bottom:0}.item img{width:120px;height:130px;object-fit:contain;background:#ded5c9}.item-name{font-weight:800}.item-meta{margin-top:7px;color:var(--muted);font:11px "DM Mono"}.item-price{text-align:right;font-weight:800}.remove{margin-top:12px;padding:0;background:transparent;border:0;color:#d97679;font:9px "DM Mono";letter-spacing:.08em;cursor:pointer}.summary{padding:26px;height:max-content;position:sticky;top:25px}.summary h2{font:34px "Bebas Neue";margin:0 0 24px}.sum-row{display:flex;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--line);color:#b8aca2}.sum-row.total{color:#fff;font-size:20px;font-weight:800;border:0}.coupon{display:grid;gap:8px;margin:22px 0;color:#9f9389;font:10px "DM Mono";letter-spacing:.08em}.coupon input{padding:13px;background:#0b0908;border:1px solid #493b35;color:#fff}.button{display:block;width:100%;border:0;background:var(--red);color:#fff;padding:17px;text-align:center;font:700 11px "DM Mono";letter-spacing:.1em;cursor:pointer}.empty{padding:50px;border:1px solid var(--line);background:#14110f;color:#aca097}.empty .button{width:max-content;margin-top:25px}.limited{color:#df696d;font:10px "DM Mono";letter-spacing:.1em;margin-top:18px}@media(max-width:760px){.cart-layout{grid-template-columns:1fr}.summary{position:static}.item{grid-template-columns:85px 1fr}.item img{width:85px;height:100px}.item-price{grid-column:2;text-align:left}.top{height:68px}.brand{font-size:34px}}

/* Shared store identity and compact purchase controls. */
.brand{display:flex;align-items:center;justify-content:center;width:88px;height:54px;overflow:hidden}
.brand img{display:block;width:100%;height:100%;object-fit:contain;transform:scale(1.55)}
h1{font:600 clamp(30px,4vw,46px)/1.15 Inter,Arial,sans-serif;letter-spacing:-.04em;margin:14px 0 24px}
.summary{border-radius:8px;box-shadow:none}
.summary h2,.summary-head strong{font:600 20px/1.3 Inter,Arial,sans-serif;letter-spacing:-.02em}
.item img,.product-card img{background:transparent}
input,select{font-size:16px;border-radius:4px;min-width:0}
.button,.pay-button{min-height:52px;height:auto;border-radius:4px;font:600 13px/1.5 Inter,Arial,sans-serif;letter-spacing:0}
.checkout-form{align-content:start;grid-auto-rows:max-content}
.lead{font-size:14px;line-height:1.7}
.back{font:500 11px/1.5 Inter,Arial,sans-serif;letter-spacing:0}
.product-card>div,.item>div{min-width:0;overflow-wrap:anywhere}
@media(max-width:820px){.summary{order:0}.brand{height:48px;width:78px}h1{font-size:32px}.top{gap:16px}.back{text-align:right}.summary-head{gap:14px;flex-wrap:wrap}}

</style></head>
<body>
<header class="top"><a class="brand" href="index.php" aria-label="406 REC — voltar à loja"><img src="assets/logo406.png" alt="406 REC"></a><a class="back" href="index.php">← CONTINUAR COMPRANDO</a></header>
<main><div class="eyebrow">DROP 001 / PEÇAS LIMITADAS</div><h1>Seu carrinho</h1>
<?php if (!$items): ?><section class="empty"><strong>Seu carrinho está vazio.</strong><p>Escolha o tamanho da sua 406 Montana para continuar.</p><a class="button" href="index.php#lista">VER A PEÇA</a></section>
<?php else: ?><div class="cart-layout"><section class="items">
<?php foreach ($items as $item): ?><article class="item"><img src="assets/shirt.png" alt="Camisa 406 Montana"><div><div class="item-name"><?=htmlspecialchars(setting('product_name'))?></div><div class="item-meta">TAMANHO <?=htmlspecialchars((string)$item['name'])?> / QUANTIDADE <?=(int)$item['quantity']?></div><form method="post"><input type="hidden" name="item_id" value="<?=(int)$item['id']?>"><button class="remove" name="remove_item" value="1">REMOVER</button></form></div><div class="item-price"><?=money((int)$item['quantity'] * $price)?></div></article><?php endforeach; ?>
</section><aside class="summary"><h2>RESUMO</h2><div class="sum-row"><span>Subtotal</span><strong><?=money($subtotal)?></strong></div><div class="sum-row"><span>Entrega</span><span>Calculada depois</span></div><div class="sum-row total"><span>Total</span><strong><?=money($subtotal)?></strong></div><form action="checkout.php" method="post"><label class="coupon">CUPOM DE DESCONTO<input name="coupon" placeholder="DIGITE O CÓDIGO"></label><button class="button">IR PARA O CHECKOUT</button></form><div class="limited">ESTOQUE LIMITADO POR TAMANHO</div></aside></div><?php endif; ?>
</main></body></html>