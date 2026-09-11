<?php
declare(strict_types=1);

function store_config(): array {
    static $config;
    if ($config !== null) return $config;
    $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    return is_array($config) ? $config : [];
}
function digits(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }

function melhor_envio_quote(string $destinationCep): array {
    $config = store_config();
    $token = trim(setting('melhor_envio_token', (string)($config['melhor_envio_token'] ?? '')));
    $origin = digits(setting('melhor_envio_origin_cep', (string)($config['melhor_envio_origin_cep'] ?? '')));
    $destination = digits($destinationCep);
    if ($token === '' || strlen($origin) !== 8) {
        return ['ok' => false, 'message' => 'O cálculo de frete ainda não foi configurado pela loja.'];
    }
    if (strlen($destination) !== 8) return ['ok' => false, 'message' => 'Informe um CEP válido.'];

    $price = current_price() / 100;
    $payload = [
        'from' => ['postal_code' => $origin],
        'to' => ['postal_code' => $destination],
        'products' => [[
            'id' => '406-montana',
            'width' => max(1, (int)($config['melhor_envio_width_cm'] ?? 25)),
            'height' => max(1, (int)($config['melhor_envio_height_cm'] ?? 4)),
            'length' => max(1, (int)($config['melhor_envio_length_cm'] ?? 32)),
            'weight' => max(0.01, (float)($config['melhor_envio_weight_kg'] ?? 0.35)),
            'insurance_value' => $price,
            'quantity' => 1,
        ]],
        'options' => ['receipt' => false, 'own_hand' => false],
    ];
    $endpoint = trim((string)($config['melhor_envio_endpoint'] ?? 'https://melhorenvio.com.br/api/v2/me/shipment/calculate'));
    $agent = trim((string)($config['melhor_envio_user_agent'] ?? '406 Store (vendas@store.406rec.com)'));

    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . $agent,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = (string)curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        error_log('Melhor Envio quote error: HTTP ' . $status . ' / ' . $curlError . ' / ' . $raw);
        return ['ok' => false, 'message' => 'Não foi possível cotar este CEP agora.'];
    }

    $quotes = [];
    foreach ($data as $service) {
        if (!is_array($service) || !empty($service['error'])) continue;
        $rawPrice = $service['custom_price'] ?? $service['price'] ?? null;
        if ($rawPrice === null) continue;
        $cents = (int)round((float)str_replace(',', '.', (string)$rawPrice) * 100);
        if ($cents < 1) continue;
        $carrier = trim((string)($service['company']['name'] ?? ''));
        if (stripos($carrier, 'correios') === false) continue;
        $name = trim($carrier . ' — ' . (string)($service['name'] ?? 'Entrega'), ' —');
        $days = $service['custom_delivery_time'] ?? $service['delivery_time'] ?? null;
        $quotes[] = [
            'code' => (string)($service['id'] ?? ''),
            'name' => $name,
            'cents' => $cents,
            'days' => is_numeric($days) ? (int)$days : null,
        ];
    }
    return $quotes ? ['ok' => true, 'quotes' => $quotes] : ['ok' => false, 'message' => 'Nenhuma modalidade ficou disponível para este CEP.'];
}

function selected_shipping(string $cep, string $code): ?array {
    $result = melhor_envio_quote($cep);
    if (!$result['ok']) return null;
    foreach ($result['quotes'] as $quote) {
        if (hash_equals($quote['code'], $code)) return $quote;
    }
    return null;
}
