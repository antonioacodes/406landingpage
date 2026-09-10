<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';

$pdo = db();
$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($adminCount === 0) {
    header('Location: install.php');
    exit;
}
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email=?');
        $stmt->execute([strtolower(trim((string)($_POST['email'] ?? '')))]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && password_verify((string)($_POST['password'] ?? ''), $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            header('Location: admin.php');
            exit;
        }
        $error = 'E-mail ou senha incorretos.';
    }
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>406 — Admin</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap');
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 80% 20%,#491016,transparent 28%),#090807;color:#f3ece4;font-family:Inter,Arial;display:grid;place-items:center}
.login{width:min(430px,90vw);background:#15110f;border:1px solid #392d28;padding:38px;box-shadow:0 30px 90px #0008}.mark{font:64px "Bebas Neue";letter-spacing:.08em}.mark em{color:#d1272f;font-style:normal}.eyebrow{font:10px "DM Mono";letter-spacing:.15em;color:#e86b6d}.login h1{font:42px "Bebas Neue";margin:22px 0 5px}.login p{color:#9f9389;font-size:13px}.login form{display:grid;gap:13px;margin-top:28px}input,button{padding:15px;border:1px solid #493a34;background:#0c0a09;color:#fff;font:14px Inter}button{border:0;background:#c6222a;font:700 11px "DM Mono";letter-spacing:.1em;cursor:pointer}.err{color:#ff9a9d!important}

.mark,.logo{width:96px;height:60px;display:flex;align-items:center;overflow:hidden}
.mark img,.logo img{width:100%;height:100%;object-fit:contain;transform:scale(1.55)}
.panel,.stack,.grid,.metrics,.metric,.row,form,label,.table-wrap{min-width:0;max-width:100%}
input,textarea,select{font-size:16px;box-sizing:border-box}
input[type=checkbox]{width:22px;height:22px}
@media(max-width:760px){
.content{padding:22px 16px 50px}.sidebar{padding:12px 16px;gap:10px}
.nav{display:flex;grid-column:1/-1;grid-row:2;overflow-x:auto;gap:6px;max-width:100%;padding-bottom:4px}
.nav a{white-space:nowrap;padding:10px;font-size:10px}.logout{grid-column:2;grid-row:1}
.topbar h1{font:600 26px/1.15 Inter,Arial,sans-serif;overflow-wrap:anywhere}
.metric{padding:14px;min-height:95px}.metric strong{font:600 26px/1.2 Inter,Arial,sans-serif;overflow-wrap:anywhere}
.metric.featured{grid-column:1/-1}.metric span{line-height:1.5;font-size:9px}
.panel-head{flex-direction:column;gap:12px}.panel h2{font:600 20px/1.25 Inter,Arial,sans-serif}
.price-preview{width:100%}.table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
th,td{padding:12px 8px}button{min-height:44px}
}
</style>
</head>
<body><section class="login"><div class="mark"><img src="assets/logo406.png" alt="406 REC"></div><div class="eyebrow">PAINEL DA LOJA</div><h1>ENTRAR</h1><p>Gerencie produto, estoque, cupons e pedidos.</p><form method="post"><input name="email" type="email" placeholder="E-mail" required autofocus><input name="password" type="password" placeholder="Senha" required><button>ACESSAR DASHBOARD</button><?php if ($error): ?><p class="err"><?=htmlspecialchars($error)?></p><?php endif; ?></form></section></body>
</html><?php
    exit;
}

$message = '';
$messageType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'settings') {
            foreach (['product_name','product_description','measurements_title','measurements'] as $key) {
                save_setting($key, trim((string)($_POST[$key] ?? '')));
            }
            $price = max(0, (int)round(((float)str_replace(',', '.', (string)($_POST['price'] ?? '0'))) * 100));
            save_setting('price_cents', (string)$price);
            $promo = trim((string)($_POST['promo_price'] ?? ''));
            save_setting('promo_price_cents', $promo === '' ? '' : (string)max(0, (int)round(((float)str_replace(',', '.', $promo)) * 100)));
            $message = 'Produto atualizado com sucesso.';
        }
        if ($action === 'size') {
            $pdo->prepare('INSERT INTO sizes(name,stock,measurements,active) VALUES(?,?,?,1)')
                ->execute([strtoupper(trim((string)$_POST['name'])), max(0, (int)$_POST['stock']), trim((string)$_POST['size_measurements'])]);
            $message = 'Novo tamanho cadastrado.';
        }
        if ($action === 'stock') {
            $pdo->prepare('UPDATE sizes SET stock=?, active=? WHERE id=?')
                ->execute([max(0, (int)$_POST['stock']), isset($_POST['active']) ? 1 : 0, (int)$_POST['id']]);
            $message = 'Estoque atualizado.';
        }
        if ($action === 'coupon') {
            $kind = ($_POST['kind'] ?? '') === 'fixed' ? 'fixed' : 'percent';
            $pdo->prepare('INSERT INTO coupons(code,kind,value,active) VALUES(?,?,?,1)')
                ->execute([strtoupper(trim((string)$_POST['code'])), $kind, max(0, (int)$_POST['value'])]);
            $message = 'Cupom criado.';
        }
    } catch (Throwable $exception) {
        $message = 'Não foi possível salvar. Verifique se o código ou tamanho já existe.';
        $messageType = 'error';
    }
}

$stats = [
    'customers' => (int)$pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn(),
    'open_carts' => (int)$pdo->query("SELECT COUNT(*) FROM carts WHERE status='open'")->fetchColumn(),
    'orders' => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'paid' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn(),
    'revenue' => (int)$pdo->query("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE status='paid'")->fetchColumn(),
];
$sizes = $pdo->query('SELECT * FROM sizes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$coupons = $pdo->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$recentOrders = $pdo->query(
    'SELECT o.*, c.name AS customer_name, c.email AS customer_email
     FROM orders o LEFT JOIN customers c ON c.id=o.customer_id
     ORDER BY o.id DESC LIMIT 12'
)->fetchAll(PDO::FETCH_ASSOC);

function val(string $key): string {
    return htmlspecialchars(setting($key), ENT_QUOTES, 'UTF-8');
}
function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function status_label(string $status): string {
    return match ($status) {
        'paid' => 'Pago',
        'cancelled' => 'Cancelado',
        'checkout' => 'Checkout',
        default => 'Pendente',
    };
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>406 — Dashboard</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap');
:root{--red:#d1272f;--bg:#090807;--panel:#14110f;--panel2:#1b1613;--cream:#f1e8dc;--muted:#958a81;--line:#362b26}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#0a0908;color:var(--cream);font:14px Inter,Arial,sans-serif}a{color:inherit;text-decoration:none}
.layout{min-height:100vh;display:grid;grid-template-columns:245px 1fr}.sidebar{position:sticky;top:0;height:100vh;padding:30px 24px;background:#0e0c0b;border-right:1px solid var(--line);display:flex;flex-direction:column}.logo{font:58px/1 "Bebas Neue";letter-spacing:.08em}.logo em{color:var(--red);font-style:normal}.side-label{margin:4px 0 36px;color:#7f756e;font:10px "DM Mono";letter-spacing:.14em}.nav{display:grid;gap:8px}.nav a{padding:12px 13px;border-left:2px solid transparent;color:#a99d93;font:500 11px "DM Mono";letter-spacing:.08em}.nav a:hover{color:#fff;border-left-color:var(--red);background:#ffffff08}.logout{margin-top:auto;padding:13px;border:1px solid var(--line);text-align:center;font:10px "DM Mono";letter-spacing:.1em;color:#beaea1}
.content{min-width:0;padding:34px clamp(22px,4vw,58px) 70px}.topbar{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:28px}.eyebrow{font:500 10px "DM Mono";letter-spacing:.16em;color:#e96d70}.topbar h1{font:52px/1 "Bebas Neue";letter-spacing:.03em;margin:8px 0 0}.visit{padding:12px 15px;border:1px solid var(--line);font:10px "DM Mono";letter-spacing:.08em}
.notice{padding:13px 15px;margin-bottom:20px;border:1px solid #31593a;background:#142719;color:#a9e6b4;font-size:12px}.notice.error{border-color:#6d272c;background:#351419;color:#ffb2b5}
.metrics{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:13px;margin-bottom:24px}.metric{min-height:118px;padding:18px;background:linear-gradient(145deg,var(--panel2),var(--panel));border:1px solid var(--line);display:flex;flex-direction:column;justify-content:space-between}.metric span{font:10px "DM Mono";letter-spacing:.11em;color:#8f837a}.metric strong{font:38px "Bebas Neue";letter-spacing:.04em}.metric.featured{background:linear-gradient(145deg,#b51d24,#721218);border-color:#db3d44}
.grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(310px,.75fr);gap:20px;align-items:start}.stack{display:grid;gap:20px}.panel{background:var(--panel);border:1px solid var(--line);padding:24px}.panel-head{display:flex;align-items:start;justify-content:space-between;gap:20px;margin-bottom:22px}.panel h2{font:32px/1 "Bebas Neue";letter-spacing:.04em;margin:0}.panel .hint{color:var(--muted);font-size:11px;line-height:1.5;margin-top:5px}
form{display:grid;gap:13px}.row{display:grid;grid-template-columns:1fr 1fr;gap:13px}label{display:grid;gap:7px;color:#aa9e94;font:500 10px "DM Mono";letter-spacing:.08em}input,textarea,select{width:100%;padding:12px;border:1px solid #493a33;background:#0c0a09;color:#fff;font:14px Inter;outline:0}input:focus,textarea:focus,select:focus{border-color:#d55258}textarea{min-height:100px;resize:vertical}button{padding:13px 16px;border:0;background:var(--red);color:#fff;font:700 10px "DM Mono";letter-spacing:.1em;cursor:pointer}button:hover{background:#ec353e}.secondary{background:#2a211d}
.table-wrap{overflow:auto;margin-top:18px}table{width:100%;border-collapse:collapse;white-space:nowrap}th,td{padding:13px 10px;text-align:left;border-bottom:1px solid #332923}th{color:#83786f;font:500 9px "DM Mono";letter-spacing:.1em}td{font-size:12px}.table-input{min-width:80px;padding:8px}.status{display:inline-block;padding:6px 8px;border-radius:20px;background:#4b3515;color:#f1cd85;font:9px "DM Mono";text-transform:uppercase}.status.paid{background:#153c24;color:#93e6ae}.empty{padding:30px 0;text-align:center;color:#81766d;font-size:12px}.price-preview{padding:16px;background:#0d0b0a;border:1px solid var(--line)}.price-preview span{display:block;color:#8e8279;font:9px "DM Mono";letter-spacing:.1em}.price-preview strong{display:block;margin-top:6px;font:32px "Bebas Neue"}
@media(max-width:1120px){.metrics{grid-template-columns:repeat(3,1fr)}.grid{grid-template-columns:1fr}}
@media(max-width:760px){.layout{display:block}.sidebar{position:static;height:auto;padding:18px 5vw;display:grid;grid-template-columns:1fr auto;align-items:center}.logo{font-size:42px}.side-label,.nav{display:none}.logout{margin:0}.content{padding:25px 5vw 60px}.topbar{align-items:start}.topbar h1{font-size:42px}.metrics{grid-template-columns:1fr 1fr}.metric{min-height:100px}.row{grid-template-columns:1fr}.panel{padding:18px}.visit{display:none}}

.mark,.logo{width:96px;height:60px;display:flex;align-items:center;overflow:hidden}
.mark img,.logo img{width:100%;height:100%;object-fit:contain;transform:scale(1.55)}
.panel,.stack,.grid,.metrics,.metric,.row,form,label,.table-wrap{min-width:0;max-width:100%}
input,textarea,select{font-size:16px;box-sizing:border-box}
input[type=checkbox]{width:22px;height:22px}
@media(max-width:760px){
.content{padding:22px 16px 50px}.sidebar{padding:12px 16px;gap:10px}
.nav{display:flex;grid-column:1/-1;grid-row:2;overflow-x:auto;gap:6px;max-width:100%;padding-bottom:4px}
.nav a{white-space:nowrap;padding:10px;font-size:10px}.logout{grid-column:2;grid-row:1}
.topbar h1{font:600 26px/1.15 Inter,Arial,sans-serif;overflow-wrap:anywhere}
.metric{padding:14px;min-height:95px}.metric strong{font:600 26px/1.2 Inter,Arial,sans-serif;overflow-wrap:anywhere}
.metric.featured{grid-column:1/-1}.metric span{line-height:1.5;font-size:9px}
.panel-head{flex-direction:column;gap:12px}.panel h2{font:600 20px/1.25 Inter,Arial,sans-serif}
.price-preview{width:100%}.table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}
th,td{padding:12px 8px}button{min-height:44px}
}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar">
    <div><div class="logo"><img src="assets/logo406.png" alt="406 REC"></div><div class="side-label">STORE CONTROL</div></div>
    <nav class="nav"><a href="#dashboard">DASHBOARD</a><a href="#pedidos">PEDIDOS</a><a href="#produto">PRODUTO E PREÇO</a><a href="#estoque">TAMANHOS E ESTOQUE</a><a href="#cupons">CUPONS</a></nav>
    <a class="logout" href="?logout=1">SAIR DO PAINEL</a>
</aside>
<main class="content" id="dashboard">
    <header class="topbar"><div><div class="eyebrow">VISÃO GERAL</div><h1>DASHBOARD DA LOJA</h1></div><a class="visit" href="index.php" target="_blank">ABRIR LOJA ↗</a></header>
    <?php if ($message): ?><div class="notice <?=$messageType === 'error' ? 'error' : ''?>"><?=esc($message)?></div><?php endif; ?>

    <?php if (setting('checkout_last_error') !== ''): ?><div class="notice error" role="status"><strong>Última tentativa de pagamento:</strong> <?=esc(setting('checkout_last_error'))?></div><?php endif; ?>
    <section class="metrics">
        <article class="metric"><span>CADASTROS</span><strong><?=$stats['customers']?></strong></article>
        <article class="metric"><span>CARRINHOS ABERTOS</span><strong><?=$stats['open_carts']?></strong></article>
        <article class="metric"><span>PEDIDOS GERADOS</span><strong><?=$stats['orders']?></strong></article>
        <article class="metric"><span>PAGAMENTOS</span><strong><?=$stats['paid']?></strong></article>
        <article class="metric featured"><span>RECEITA CONFIRMADA</span><strong><?=money($stats['revenue'])?></strong></article>
    </section>

    <div class="grid">
        <div class="stack">
            <section class="panel" id="pedidos">
                <div class="panel-head"><div><h2>PEDIDOS RECENTES</h2><div class="hint">Últimos pagamentos gerados pelo checkout.</div></div></div>
                <?php if (!$recentOrders): ?><div class="empty">Nenhum pedido foi gerado ainda.</div><?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>PEDIDO</th><th>CLIENTE</th><th>TOTAL</th><th>STATUS</th><th>DATA</th></tr></thead><tbody>
                <?php foreach ($recentOrders as $order): ?><tr><td><?=esc((string)$order['order_nsu'])?></td><td><?=esc((string)($order['customer_name'] ?: $order['customer_email']))?></td><td><?=money((int)$order['total_cents'])?></td><td><span class="status <?=esc((string)$order['status'])?>"><?=esc(status_label((string)$order['status']))?></span></td><td><?=date('d/m/Y H:i', strtotime((string)$order['created_at']))?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            </section>

            <section class="panel" id="produto">
                <div class="panel-head"><div><h2>PRODUTO E PREÇO</h2><div class="hint">Tudo que aparece na página da peça.</div></div><div class="price-preview"><span>PREÇO ATUAL</span><strong><?=money(current_price())?></strong></div></div>
                <form method="post"><input type="hidden" name="action" value="settings">
                    <div class="row"><label>NOME DO PRODUTO<input name="product_name" value="<?=val('product_name')?>"></label><label>PREÇO NORMAL (R$)<input name="price" inputmode="decimal" value="<?=number_format((int)setting('price_cents')/100,2,',','')?>"></label></div>
                    <label>PREÇO PROMOCIONAL (DEIXE VAZIO PARA DESLIGAR)<input name="promo_price" inputmode="decimal" value="<?=setting('promo_price_cents') !== '' ? number_format((int)setting('promo_price_cents')/100,2,',','') : ''?>"></label>
                    <label>DESCRIÇÃO<textarea name="product_description"><?=val('product_description')?></textarea></label>
                    <label>TÍTULO DAS MEDIDAS<input name="measurements_title" value="<?=val('measurements_title')?>"></label>
                    <label>GUIA GERAL DE MEDIDAS<textarea name="measurements"><?=val('measurements')?></textarea></label>
                    <button>SALVAR PRODUTO</button>
                </form>
            </section>

            <section class="panel" id="estoque">
                <div class="panel-head"><div><h2>TAMANHOS E ESTOQUE</h2><div class="hint">Controle interno de disponibilidade por tamanho.</div></div></div>
                <form method="post"><input type="hidden" name="action" value="size"><div class="row"><label>TAMANHO<input name="name" placeholder="P, M, G, GG" required></label><label>QUANTIDADE DISPONÍVEL<input name="stock" type="number" min="0" required></label></div><label>MEDIDAS DESTE TAMANHO<textarea name="size_measurements" placeholder="Ex.: largura 58 cm / comprimento 74 cm"></textarea></label><button>ADICIONAR TAMANHO</button></form>
                <div class="table-wrap"><table><thead><tr><th>TAMANHO</th><th>ESTOQUE</th><th>ATIVO</th><th>AÇÃO</th></tr></thead><tbody>
                <?php foreach ($sizes as $size): $formId='size-'.$size['id']; ?><tr>
                    <td><?=esc((string)$size['name'])?></td>
                    <td><input class="table-input" form="<?=$formId?>" name="stock" type="number" min="0" value="<?=(int)$size['stock']?>"></td>
                    <td><input form="<?=$formId?>" name="active" type="checkbox" <?=$size['active'] ? 'checked' : ''?>></td>
                    <td><form id="<?=$formId?>" method="post"><input type="hidden" name="action" value="stock"><input type="hidden" name="id" value="<?=(int)$size['id']?>"></form><button class="secondary" form="<?=$formId?>">SALVAR</button></td>
                </tr><?php endforeach; ?></tbody></table></div>
            </section>
        </div>

        <div class="stack">
            <section class="panel" id="cupons">
                <div class="panel-head"><div><h2>CUPONS</h2><div class="hint">Crie descontos para campanhas.</div></div></div>
                <form method="post"><input type="hidden" name="action" value="coupon"><label>CÓDIGO<input name="code" placeholder="MONTANA10" required></label><label>TIPO<select name="kind"><option value="percent">Percentual (%)</option><option value="fixed">Valor fixo (centavos)</option></select></label><label>VALOR<input name="value" type="number" min="1" placeholder="10" required></label><button>CRIAR CUPOM</button></form>
                <div class="table-wrap"><table><thead><tr><th>CÓDIGO</th><th>TIPO</th><th>VALOR</th></tr></thead><tbody><?php foreach ($coupons as $couponRow): ?><tr><td><?=esc((string)$couponRow['code'])?></td><td><?=$couponRow['kind'] === 'percent' ? 'Percentual' : 'Fixo'?></td><td><?=$couponRow['kind'] === 'percent' ? (int)$couponRow['value'].'%' : money((int)$couponRow['value'])?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
        </div>
    </div>
</main>
</div>
</body>
</html>