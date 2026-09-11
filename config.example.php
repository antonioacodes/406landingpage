<?php
// Copie este arquivo para config.php no servidor.
// A InfiniteTag deve ser informada sem o símbolo $.
return [
    'infinitepay_handle' => 'f406',
    'webhook_url' => 'https://store.406rec.com/webhook.php',
    'redirect_url' => 'https://store.406rec.com/payment-return.php',

    // Configure estes dados pelo painel em /admin.php > Integrações.
    // Mantidos aqui somente como alternativa de emergência.
    'melhor_envio_token' => '',
    'melhor_envio_origin_cep' => '',
];
