<?php
// Copie este arquivo para config.php no servidor.
// A InfiniteTag deve ser informada sem o símbolo $.
return [
    'infinitepay_handle' => 'f406',
    'webhook_url' => 'https://store.406rec.com/webhook.php',
    'redirect_url' => 'https://store.406rec.com/payment-return.php',

    // Correios: token de produção, CEP de origem em Belo Horizonte e medidas da embalagem.
    'correios_token' => '',
    'correios_origin_cep' => '',
    'correios_weight_g' => 350,
    'correios_length_cm' => 32,
    'correios_width_cm' => 25,
    'correios_height_cm' => 4,
    'correios_services' => ['03298' => 'PAC', '03220' => 'SEDEX'],

    // Remetente dos avisos de rastreio. A VPS precisa ter envio de e-mail configurado.
    'mail_from' => 'vendas@store.406rec.com',
];
