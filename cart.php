<?php
declare(strict_types=1);
session_start(); require __DIR__ . '/database.php'; $pdo=db();
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['size_id'])) {
  $sizeId=(int)$_POST['size_id']; $st=$pdo->prepare('SELECT id,stock FROM sizes WHERE id=? AND active=1'); $st->execute([$sizeId]); $size=$st->fetch(PDO::FETCH_ASSOC);
  if($size && $size['stock']>0){ if(empty($_SESSION['cart_token'])) $_SESSION['cart_token']=bin2hex(random_bytes(20)); $token=$_SESSION['cart_token'];
    $pdo->prepare('INSERT OR IGNORE INTO carts(token) VALUES(?)')->execute([$token]); $cartId=(int)$pdo->prepare('SELECT id FROM carts WHERE token=?')->execute([$token]);
    $q=$pdo->prepare('SELECT id FROM carts WHERE token=?');$q->execute([$token]);$cartId=(int)$q->fetchColumn();
    $pdo->prepare('INSERT INTO cart_items(cart_id,size_id,quantity) VALUES(?,?,1)')->execute([$cartId,$sizeId]);
  } header('Location: cart.php'); exit;
}
$token=$_SESSION['cart_token']??''; $items=[];
if($token){$q=$pdo->prepare('SELECT ci.id,ci.quantity,s.name,s.stock FROM cart_items ci JOIN carts c ON c.id=ci.cart_id JOIN sizes s ON s.id=ci.size_id WHERE c.token=?');$q->execute([$token]);$items=$q->fetchAll(PDO::FETCH_ASSOC);}
$price=current_price();$subtotal=array_sum(array_map(fn($i)=>$i['quantity']*$price,$items));
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Carrinho — 406</title><style>body{margin:0;background:#0b0908;color:#eee;font-family:Arial}main{width:min(760px,90vw);margin:80px auto}a{color:#fff}article{border-top:1px solid #463a34;padding:18px 0;display:flex;justify-content:space-between}.total{font-size:24px;font-weight:bold}.btn{display:inline-block;background:#b71922;color:white;padding:15px 20px;text-decoration:none;border:0;margin-top:18px}input{padding:12px;background:#18120f;border:1px solid #53433b;color:white}</style><main><a href="index.php">← continuar comprando</a><h1>SEU CARRINHO</h1><?php if(!$items):?><p>Seu carrinho está vazio.</p><?php else:?><?php foreach($items as $item):?><article><span><?=htmlspecialchars(setting('product_name'))?> / <?=htmlspecialchars($item['name'])?> × <?=$item['quantity']?></span><strong><?=money($item['quantity']*$price)?></strong></article><?php endforeach;?><p class="total">Total: <?=money($subtotal)?></p><form action="checkout.php" method="post"><label>Cupom <input name="coupon" placeholder="CÓDIGO"></label><button class="btn">IR PARA PAGAMENTO</button></form><?php endif;?></main></html>