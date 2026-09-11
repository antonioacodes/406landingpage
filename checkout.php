<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';
require __DIR__ . '/shipping.php';

$pdo = db();
$token = (string)($_SESSION['cart_token'] ?? '');
$stmt = $pdo->prepare(
    'SELECT ci.quantity, s.name, s.measurements
     FROM cart_items ci
     JOIN carts c ON c.id = ci.cart_id
     JOIN sizes s ON s.id = ci.size_id
     WHERE c.token = ?'
);
$stmt->execute([$token]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    header('Location: index.php');
    exit;
}

$unitPrice = current_price();
$subtotal = array_sum(array_map(
    static fn(array $item): int => (int)$item['quantity'] * $unitPrice,
    $items
));

$coupon = strtoupper(trim((string)($_POST['coupon'] ?? '')));
$discount = 0;
$couponMessage = '';

if ($coupon !== '') {
    $couponStmt = $pdo->prepare(
        'SELECT * FROM coupons
         WHERE code = ? AND active = 1
         AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
         AND (ends_at IS NULL OR ends_at >= CURRENT_TIMESTAMP)'
    );
    $couponStmt->execute([$coupon]);
    $couponData = $couponStmt->fetch(PDO::FETCH_ASSOC);
    if ($couponData) {
        $discount = $couponData['kind'] === 'percent'
            ? min($subtotal, (int)round($subtotal * (int)$couponData['value'] / 100))
            : min($subtotal, (int)$couponData['value']);
        $couponMessage = 'Cupom aplicado: ' . $coupon;
    } else {
        $couponMessage = 'Cupom inválido ou expirado.';
        $coupon = '';
    }
}

$total = max(0, $subtotal - $discount);
$shippingEnabled = setting('shipping_enabled', '0') === '1';
$error = '';
$form = [
    'name' => trim((string)($_POST['name'] ?? '')),
    'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
    'phone' => trim((string)($_POST['phone'] ?? '')),
    'cep' => trim((string)($_POST['cep'] ?? '')),
    'address_line' => trim((string)($_POST['address_line'] ?? '')),
    'address_number' => trim((string)($_POST['address_number'] ?? '')),
    'address_extra' => trim((string)($_POST['address_extra'] ?? '')),
    'neighborhood' => trim((string)($_POST['neighborhood'] ?? '')),
    'city' => trim((string)($_POST['city'] ?? '')),
    'state' => strtoupper(trim((string)($_POST['state'] ?? ''))),
    'shipping_code' => trim((string)($_POST['shipping_code'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_order'])) {
    $password = (string)($_POST['password'] ?? '');

    $shipping = $shippingEnabled
        ? selected_shipping($form['cep'], $form['shipping_code'])
        : ['code' => 'manual', 'name' => 'Frete a combinar', 'cents' => 0];
    if ($form['name'] === '' || !filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
        $error = 'Preencha nome, e-mail válido e uma senha de pelo menos 8 caracteres.';
    } elseif ($form['phone'] === '') {
        $error = 'Informe o telefone para contato sobre o pedido.';
    } elseif ($form['address_line'] === '' || $form['address_number'] === '' || $form['neighborhood'] === '' || $form['city'] === '' || strlen($form['state']) !== 2) {
        $error = 'Preencha o endereço completo para entrega.';
    } elseif ($shippingEnabled && !$shipping) {
        $error = 'Escolha uma opção de frete válida. Informe o CEP e clique em calcular.';
    } else {
        $shippingCents = (int)$shipping['cents'];
        $total += $shippingCents;
        $pdo->prepare(
            'INSERT INTO customers(name,email,phone,password_hash)
             VALUES(?,?,?,?)
             ON CONFLICT(email) DO UPDATE SET
             name=excluded.name, phone=excluded.phone, password_hash=excluded.password_hash'
        )->execute([
            $form['name'],
            $form['email'],
            $form['phone'],
            password_hash($password, PASSWORD_DEFAULT)
        ]);

        $customerStmt = $pdo->prepare('SELECT id FROM customers WHERE email=?');
        $customerStmt->execute([$form['email']]);
        $customerId = (int)$customerStmt->fetchColumn();

        $cartStmt = $pdo->prepare('SELECT id FROM carts WHERE token=?');
        $cartStmt->execute([$token]);
        $cartId = (int)$cartStmt->fetchColumn();

        $orderNsu = '406-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        $pdo->prepare(
            'INSERT INTO orders(
                order_nsu,customer_id,cart_id,subtotal_cents,discount_cents,total_cents,coupon_code
             ) VALUES(?,?,?,?,?,?,?)'
        )->execute([
            $orderNsu, $customerId, $cartId, $subtotal, $discount, $total, $coupon ?: null
        ]);
        $orderId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO shipments(order_id,service_code,service_name,shipping_cents,destination_cep,address_line,address_number,address_extra,neighborhood,city,state) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([
            $orderId, $shipping['code'], $shipping['name'], $shippingCents, digits($form['cep']),
            $form['address_line'], $form['address_number'], $form['address_extra'],
            $form['neighborhood'], $form['city'], $form['state']
        ]);

        $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
        $handle = ltrim(trim((string)($config['infinitepay_handle'] ?? 'f406')), '$');
        if ($handle === '') {
            $handle = 'f406';
        }

        $baseUrl = 'https://store.406rec.com';
        $redirectUrl = trim((string)($config['redirect_url'] ?? ''));
        $webhookUrl = trim((string)($config['webhook_url'] ?? ''));
        if ($redirectUrl === '') {
            $redirectUrl = $baseUrl . '/payment-return.php';
        }
        if ($webhookUrl === '') {
            $webhookUrl = $baseUrl . '/webhook.php';
        }

        $phoneNumber = preg_replace('/\D+/', '', $form['phone']);
        if (strlen($phoneNumber) === 10 || strlen($phoneNumber) === 11) {
            $phoneNumber = '55' . $phoneNumber;
        }

        $payload = [
            'handle' => $handle,
            'order_nsu' => $orderNsu,
            'items' => array_values(array_filter([
                ['quantity' => 1, 'price' => max(0, $subtotal - $discount), 'description' => setting('product_name')],
                $shippingCents > 0 ? ['quantity' => 1, 'price' => $shippingCents, 'description' => 'Frete — ' . $shipping['name']] : null,
            ])),
            'redirect_url' => $redirectUrl,
            'webhook_url' => $webhookUrl,
            'customer' => [
                'name' => $form['name'],
                'email' => $form['email'],
            ],
            'address' => [
                'cep' => digits($form['cep']),
                'number' => $form['address_number'],
                'complement' => $form['address_extra'],
            ],
        ];

        if (preg_match('/^55[0-9]{10,11}$/', $phoneNumber)) {
            $payload['customer']['phone_number'] = '+' . $phoneNumber;
        }
        $curl = curl_init('https://api.checkout.infinitepay.io/links');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);
        $rawResponse = (string)curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);
        $response = json_decode($rawResponse, true);

        if ($httpCode >= 200 && $httpCode < 300 && !empty($response['url'])) {
            save_setting('checkout_last_error', '');
            $pdo->prepare('UPDATE orders SET gateway_url=? WHERE order_nsu=?')
                ->execute([$response['url'], $orderNsu]);
            $pdo->prepare('UPDATE carts SET status="checkout", updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$cartId]);
            header('Location: ' . $response['url']);
            exit;
        }

        $error = 'Não foi possível abrir o pagamento agora. Tente novamente em alguns instantes.';
        if ($httpCode === 0) {
            $reason = 'Falha de conexão com a InfinitePay. Verifique DNS, cURL e certificado TLS da VPS.';
        } elseif ($httpCode === 401 || $httpCode === 403) {
            $reason = 'Acesso recusado pela InfinitePay. Confira a InfiniteTag da conta em config.php.';
        } elseif ($httpCode === 404) {
            $reason = 'O Checkout Integrado não está habilitado para esta InfiniteTag. Ative-o no app da InfinitePay em Vendas > Checkout > Configurações.';
        } elseif ($httpCode === 400 || $httpCode === 422) {
            $reason = 'A InfinitePay recusou os dados enviados. Confira InfiniteTag, valor e dados do comprador.';
        } else {
            $reason = 'A InfinitePay retornou uma resposta inesperada.';
        }
        $reference = 'HTTP ' . $httpCode . ' / pedido ' . $orderNsu;
        save_setting('checkout_last_error', date('d/m/Y H:i') . ' — ' . $reference . ' — ' . $reason);
        $error .= ' Referência: ' . $orderNsu . ' (HTTP ' . $httpCode . ').';
        error_log('InfinitePay checkout error: ' . $reference . ' / cURL: ' . $curlError . ' / ' . $rawResponse);
    }
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Finalizar compra — 406 Montana</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Mono:wght@400;500&family=Inter:wght@400;500;600;700;800&display=swap');
        :root{--red:#d1272f;--cream:#f1e8dc;--ink:#090807;--panel:#13100e;--line:#362b27;--muted:#a69b91}
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;background:radial-gradient(circle at 80% 20%,#4a0c12 0,transparent 30%),#090807;color:var(--cream);font-family:Inter,Arial,sans-serif}
        body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.1;background-image:radial-gradient(#fff .6px,transparent .8px);background-size:4px 4px;mix-blend-mode:overlay}
        a{color:inherit;text-decoration:none}
        .top{width:min(1240px,92vw);height:82px;margin:auto;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #ffffff24}
        .brand{font:42px/1 "Bebas Neue";letter-spacing:.08em}.brand em{color:var(--red);font-style:normal}
        .back{font:500 11px "DM Mono";letter-spacing:.1em;color:#c7bbb0}
        main{width:min(1240px,92vw);margin:48px auto 80px;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(360px,.9fr);gap:54px;align-items:start}
        .eyebrow{font:500 11px "DM Mono";letter-spacing:.15em;color:#ef756d}
        h1{font:clamp(58px,7vw,96px)/.9 "Bebas Neue";letter-spacing:.02em;margin:12px 0 18px}
        .lead{max-width:620px;color:#b9ada3;line-height:1.65;margin-bottom:34px}
        .checkout-form{display:grid;gap:18px}
        .field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        label{display:grid;gap:8px;font:500 10px "DM Mono";letter-spacing:.1em;color:#c8bbb0}
        input{width:100%;border:1px solid var(--line);background:#120f0d;color:#fff;padding:16px 15px;outline:none;font:15px Inter;border-radius:2px}
        input:focus{border-color:#d85a60;box-shadow:0 0 0 3px #d1272f20}
        .coupon-row{display:grid;grid-template-columns:1fr auto;gap:10px}.coupon-row button{padding-inline:20px;background:#241d19}.shipping-fields{border:1px solid var(--line);padding:16px;margin:0;display:grid;gap:14px}.shipping-fields legend{padding:0 6px;font:500 10px "DM Mono";letter-spacing:.1em;color:#c8bbb0}.secondary{background:#241d19}
        button{border:0;background:var(--red);color:white;padding:18px;font:700 12px "DM Mono";letter-spacing:.1em;cursor:pointer;transition:.2s}
        button:hover{background:#ed3740;transform:translateY(-2px)}
        .pay-button{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:6px}
        .pix-mark{width:18px;height:18px;transform:rotate(45deg);border:2px solid currentColor;border-radius:3px}
        .error{padding:13px 15px;background:#6c151a;border:1px solid #ff7b81;color:#fff;font-size:13px;line-height:1.5}
        .coupon-message{margin:0;color:#d9b7a8;font-size:12px}
        .trust{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px;color:#8f857d;font-size:10px;line-height:1.4;text-align:center}
        .summary{position:sticky;top:24px;background:#ebe3d7;color:#17110f;padding:28px;box-shadow:0 30px 80px #0008}
        .summary-head{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #bcb0a4;padding-bottom:18px}
        .summary-head strong{font:34px "Bebas Neue";letter-spacing:.04em}.limited{font:700 9px "DM Mono";letter-spacing:.12em;color:#a4161d}
        .product-card{display:grid;grid-template-columns:150px 1fr;gap:20px;align-items:center;padding:24px 0;border-bottom:1px solid #bcb0a4}
        .product-card img{display:block;width:150px;height:170px;object-fit:contain;background:#d8cec1}
        .product-name{font-weight:800;line-height:1.25}.sizes{margin-top:8px;color:#6f635b;font:11px/1.6 "DM Mono"}
        .totals{padding-top:20px;display:grid;gap:12px}.total-row{display:flex;justify-content:space-between;color:#6d625a;font-size:13px}.total-row.final{border-top:1px solid #bcb0a4;padding-top:18px;color:#17110f;font-size:18px;font-weight:800}.total-row.final strong{font-size:25px}
        .payment-note{margin:22px 0 0;padding:14px;border:1px solid #bcb0a4;font-size:11px;line-height:1.5;color:#635850}
        @media(max-width:820px){main{grid-template-columns:1fr;gap:34px;margin-top:32px}.summary{position:static;order:-1}.field-row{grid-template-columns:1fr}.top{height:68px}.brand{font-size:34px}.trust{grid-template-columns:1fr}.product-card{grid-template-columns:110px 1fr}.product-card img{width:110px;height:130px}}
        @media(max-width:430px){.summary{padding:20px}main{width:min(94vw,1240px)}h1{font-size:58px}.coupon-row{grid-template-columns:1fr}.product-card{gap:14px}}
    
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

</style>
</head>
<body>
<header class="top">
    <a class="brand" href="index.php" aria-label="406 REC — voltar à loja"><img src="assets/logo406.png" alt="406 REC"></a>
    <a class="back" href="cart.php">← VOLTAR AO CARRINHO</a>
</header>
<main>
    <section>
        <div class="eyebrow">CHECKOUT / PEDIDO SEGURO</div>
        <h1>Finalize sua compra.</h1>
        <p class="lead">Informe seus dados e continue para o ambiente seguro da InfinitePay. O pagamento é confirmado automaticamente no seu pedido.</p>

        <form class="checkout-form" method="post" novalidate>
            <?php if ($error): ?><div class="error"><?=e($error)?></div><?php endif; ?>

            <div class="field-row">
                <label>NOME COMPLETO
                    <input name="name" autocomplete="name" value="<?=e($form['name'])?>" placeholder="Seu nome" required>
                </label>
                <label>TELEFONE
                    <input name="phone" autocomplete="tel" inputmode="tel" value="<?=e($form['phone'])?>" placeholder="(31) 99999-9999" required>
                </label>
            </div>

            <label>E-MAIL
                <input type="email" name="email" autocomplete="email" value="<?=e($form['email'])?>" placeholder="voce@email.com" required>
            </label>

            <fieldset class="shipping-fields">
                <legend>ENTREGA</legend>
                <label>CEP<input name="cep" inputmode="numeric" value="<?=e($form['cep'])?>" placeholder="00000-000" required></label>
                <?php if ($shippingEnabled): ?>
                <div class="field-row"><label>FRETE<select id="shipping-code" name="shipping_code" required><option value="">Informe o CEP primeiro</option></select></label><div class="quote-space"><button class="secondary" type="button" id="quote-shipping">CALCULAR FRETE</button></div></div>
                <p class="coupon-message" id="shipping-message">O frete é calculado pelos Correios a partir de Belo Horizonte.</p>
                <?php else: ?>
                <p class="coupon-message">O frete será combinado após a confirmação do pedido.</p>
                <?php endif; ?>
                <label>RUA / AVENIDA<input name="address_line" value="<?=e($form['address_line'])?>" required></label>
                <div class="field-row"><label>NÚMERO<input name="address_number" value="<?=e($form['address_number'])?>" required></label><label>COMPLEMENTO<input name="address_extra" value="<?=e($form['address_extra'])?>"></label></div>
                <div class="field-row"><label>BAIRRO<input name="neighborhood" value="<?=e($form['neighborhood'])?>" required></label><label>CIDADE<input name="city" value="<?=e($form['city'])?>" required></label></div>
                <label>UF<input name="state" maxlength="2" value="<?=e($form['state'])?>" placeholder="MG" required></label>
            </fieldset>
            <label>CRIE UMA SENHA
                <input name="password" type="password" autocomplete="new-password" minlength="8" placeholder="Mínimo de 8 caracteres" required>
            </label>

            <label>CUPOM DE DESCONTO
                <span class="coupon-row">
                    <input name="coupon" value="<?=e($coupon)?>" placeholder="DIGITE O CÓDIGO">
                    <button type="submit" name="apply_coupon" value="1" formnovalidate>APLICAR</button>
                </span>
            </label>
            <?php if ($couponMessage): ?><p class="coupon-message"><?=e($couponMessage)?></p><?php endif; ?>

            <button class="pay-button" type="submit" name="finish_order" value="1">
                <span class="pix-mark" aria-hidden="true"></span>
                CONTINUAR PARA O PAGAMENTO
            </button>
            <div class="trust"><span>AMBIENTE SEGURO</span><span>PAGAMENTO INFINITEPAY</span><span>CONFIRMAÇÃO AUTOMÁTICA</span></div>
        </form>
    </section>

    <aside class="summary">
        <div class="summary-head"><strong>SEU PEDIDO</strong><span class="limited">PEÇAS LIMITADAS</span></div>
        <div class="product-card">
            <img src="assets/shirt.png" alt="Camisa 406 Montana">
            <div>
                <div class="product-name"><?=e(setting('product_name'))?></div>
                <div class="sizes"><?php foreach ($items as $item): ?>TAMANHO <?=e((string)$item['name'])?> × <?=(int)$item['quantity']?><br><?php endforeach; ?></div>
            </div>
        </div>
        <div class="totals">
            <div class="total-row"><span>Subtotal</span><strong><?=money($subtotal)?></strong></div>
            <?php if ($discount > 0): ?><div class="total-row"><span>Desconto</span><strong>− <?=money($discount)?></strong></div><?php endif; ?>
            <div class="total-row final"><span>Total</span><strong><?=money($total)?></strong></div>
        </div>
        <p class="payment-note">Você será direcionado para o checkout da InfinitePay. Pix e demais opções disponibilizadas pela sua conta são processados no ambiente seguro do gateway.</p>
    </aside>
</main>
<?php if ($shippingEnabled): ?><script>
const cep=document.getElementById('cep'), shippingSelect=document.getElementById('shipping-code'), shippingTotal=document.getElementById('shipping-total'), shippingMessage=document.getElementById('shipping-message');
document.getElementById('quote-shipping').addEventListener('click', async () => {
  const value=cep.value.replace(/\D/g,'');
  if(value.length!==8){shippingMessage.textContent='Informe um CEP com 8 dígitos.';return}
  shippingMessage.textContent='Calculando frete…'; shippingSelect.innerHTML='<option value="">Calculando…</option>';
  try {
    const response=await fetch('shipping-quote.php?cep='+value,{cache:'no-store'}), data=await response.json();
    if(!data.ok) throw new Error(data.message||'Não foi possível calcular o frete.');
    shippingSelect.innerHTML='<option value="">Escolha o envio</option>';
    data.quotes.forEach(q=>{const o=document.createElement('option');o.value=q.code;o.dataset.cents=q.cents;o.textContent=q.name+' — R$ '+(q.cents/100).toLocaleString('pt-BR',{minimumFractionDigits:2});shippingSelect.appendChild(o)});
    shippingMessage.textContent='Escolha uma modalidade de envio.';
  } catch(error) { shippingSelect.innerHTML='<option value="">Frete indisponível</option>';shippingMessage.textContent=error.message; }
});
shippingSelect.addEventListener('change',()=>{const o=shippingSelect.selectedOptions[0];shippingTotal.textContent=o&&o.dataset.cents?'R$ '+(Number(o.dataset.cents)/100).toLocaleString('pt-BR',{minimumFractionDigits:2}):'Calcular no CEP';});
</script><?php endif; ?>
</body>
</html>