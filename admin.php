<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';
$pdo = db();
$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($adminCount === 0) { header('Location: install.php'); exit; }
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }
if (!isset($_SESSION['admin_id'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email=?');
        $stmt->execute([strtolower(trim($_POST['email'] ?? ''))]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify($_POST['password'] ?? '', $admin['password_hash'])) $_SESSION['admin_id'] = $admin['id'];
        else $error = 'E-mail ou senha incorretos.';
    }
    if (!isset($_SESSION['admin_id'])) { ?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>406 — Admin</title>
<style>body{margin:0;background:#0a0908;color:#eee;font-family:Arial;display:grid;place-items:center;min-height:100vh}form{width:min(390px,88vw);display:grid;gap:14px}input,button{padding:14px;border:1px solid #444;background:#151210;color:#fff}button{background:#b31820;border:0;font-weight:bold;cursor:pointer}.err{color:#ff8c8c}</style>
<form method="post"><h1>406 / ADMIN</h1><p>Gestão da loja</p><input name="email" type="email" placeholder="E-mail" required autofocus><input name="password" type="password" placeholder="Senha" required><button>ENTRAR</button><p class="err"><?=htmlspecialchars($error)?></p></form></html><?php exit; }
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'settings') {
        foreach (['product_name','product_description','measurements_title','measurements'] as $key) save_setting($key, trim($_POST[$key] ?? ''));
        save_setting('price_cents', (string) max(0, (int)round(((float)str_replace(',', '.', $_POST['price'] ?? '0')) * 100)));
        $promo = trim($_POST['promo_price'] ?? '');
        save_setting('promo_price_cents', $promo === '' ? '' : (string)max(0, (int)round(((float)str_replace(',', '.', $promo))*100)));
        $message='Produto atualizado.';
    }
    if ($action === 'size') {
        $pdo->prepare('INSERT INTO sizes(name,stock,measurements,active) VALUES(?,?,?,1)')->execute([strtoupper(trim($_POST['name'])), max(0,(int)$_POST['stock']), trim($_POST['size_measurements'])]);
        $message='Tamanho cadastrado.';
    }
    if ($action === 'stock') {
        $pdo->prepare('UPDATE sizes SET stock=?, active=? WHERE id=?')->execute([max(0,(int)$_POST['stock']), isset($_POST['active'])?1:0,(int)$_POST['id']]);
        $message='Estoque atualizado.';
    }
    if ($action === 'coupon') {
        $pdo->prepare('INSERT INTO coupons(code,kind,value,active) VALUES(?,?,?,1)')->execute([strtoupper(trim($_POST['code'])), $_POST['kind']==='fixed'?'fixed':'percent', max(0,(int)$_POST['value'])]);
        $message='Cupom criado.';
    }
}
$sizes=$pdo->query('SELECT * FROM sizes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$coupons=$pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
function val($key){return htmlspecialchars(setting($key));}
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>406 — Admin</title>
<style>body{margin:0;background:#0b0908;color:#f1e8dc;font:15px Arial}header,main{width:min(1040px,92vw);margin:auto}header{padding:26px 0;border-bottom:1px solid #3a302b;display:flex;justify-content:space-between}a{color:#f4c3bd}section{background:#15110f;padding:24px;margin:24px 0;border:1px solid #332721}h2{margin-top:0;color:#e95759}form{display:grid;gap:12px}input,textarea,select,button{font:inherit;padding:10px;background:#0b0908;border:1px solid #4b3c35;color:#fff}textarea{min-height:100px}button{background:#bc2229;border:0;font-weight:bold;cursor:pointer}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #382e29;text-align:left}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}.note{color:#91d89a}@media(max-width:600px){.row{grid-template-columns:1fr}table{font-size:12px}}</style>
<header><strong>406 / PAINEL</strong><a href="?logout=1">Sair</a></header><main>
<?php if($message):?><p class="note"><?=htmlspecialchars($message)?></p><?php endif;?>
<section><h2>Produto, preço e medidas</h2><form method="post"><input type="hidden" name="action" value="settings"><div class="row"><label>Nome<input name="product_name" value="<?=val('product_name')?>"></label><label>Preço normal (R$)<input name="price" inputmode="decimal" value="<?=number_format((int)setting('price_cents')/100,2,',','')?>"></label></div><label>Preço promocional (R$ — deixe vazio para desligar)<input name="promo_price" inputmode="decimal" value="<?=setting('promo_price_cents')!==''?number_format((int)setting('promo_price_cents')/100,2,',',''):''?>"></label><label>Descrição<textarea name="product_description"><?=val('product_description')?></textarea></label><label>Título das medidas<input name="measurements_title" value="<?=val('measurements_title')?>"></label><label>Guia geral de medidas (ex.: largura e comprimento por tamanho)<textarea name="measurements"><?=val('measurements')?></textarea></label><button>SALVAR ALTERAÇÕES</button></form></section>
<section><h2>Tamanhos e disponibilidade</h2><form method="post"><input type="hidden" name="action" value="size"><div class="row"><input name="name" placeholder="Ex.: P, M, G, GG" required><input name="stock" type="number" min="0" placeholder="Quantidade disponível" required></div><textarea name="size_measurements" placeholder="Medidas específicas deste tamanho (opcional)"></textarea><button>ADICIONAR TAMANHO</button></form><table><tr><th>Tamanho</th><th>Disponível</th><th>Ativo</th><th></th></tr><?php foreach($sizes as $s):?><tr><form method="post"><input type="hidden" name="action" value="stock"><input type="hidden" name="id" value="<?=$s['id']?>"><td><?=htmlspecialchars($s['name'])?></td><td><input name="stock" type="number" min="0" value="<?=$s['stock']?>"></td><td><input name="active" type="checkbox" <?=$s['active']?'checked':''?>></td><td><button>Salvar</button></td></form></tr><?php endforeach;?></table></section>
<section><h2>Cupons</h2><form method="post"><input type="hidden" name="action" value="coupon"><div class="row"><input name="code" placeholder="CÓDIGO" required><select name="kind"><option value="percent">% de desconto</option><option value="fixed">R$ de desconto (centavos)</option></select></div><input name="value" type="number" min="1" placeholder="Valor (ex.: 10 para 10%)" required><button>CRIAR CUPOM</button></form><table><tr><th>Código</th><th>Tipo</th><th>Valor</th></tr><?php foreach($coupons as $c):?><tr><td><?=htmlspecialchars($c['code'])?></td><td><?=$c['kind']==='percent'?'Percentual':'Fixo (centavos)'?></td><td><?=$c['value']?></td></tr><?php endforeach;?></table></section>
</main></html>