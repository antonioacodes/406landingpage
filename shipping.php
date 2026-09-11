<?php
declare(strict_types=1);

function store_config(): array {
    static $config;
    if ($config !== null) return $config;
    $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    return is_array($config) ? $config : [];
}
function digits(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }

function melhor_envio_user_agent(): string {
    $config = store_config();
    return trim(setting('melhor_envio_user_agent', (string)($config['melhor_envio_user_agent'] ?? '406 Store (vendas@store.406rec.com)')));
}
function melhor_envio_token_url(): string {
    $config = store_config();
    return trim((string)($config['melhor_envio_token_endpoint'] ?? 'https://melhorenvio.com.br/oauth/token'));
}
function melhor_envio_save_credentials(array $data): bool {
    $accessToken = trim((string)($data['access_token'] ?? ''));
    if ($accessToken === '') return false;
    save_setting('melhor_envio_token', $accessToken);
    if (trim((string)($data['refresh_token'] ?? '')) !== '') {
        save_setting('melhor_envio_refresh_token', trim((string)$data['refresh_token']));
    }
    $expiresIn = max(60, (int)($data['expires_in'] ?? 2592000));
    save_setting('melhor_envio_token_expires_at', (string)(time() + $expiresIn));
    return true;
}
function melhor_envio_request_token(array $fields): array {
    $config = store_config();
    $clientId = trim(setting('melhor_envio_client_id', (string)($config['melhor_envio_client_id'] ?? '')));
    $secret = trim(setting('melhor_envio_client_secret', (string)($config['melhor_envio_client_secret'] ?? '')));
    if ($clientId === '' || $secret === '') return ['ok' => false, 'message' => 'Client ID ou Client Secret não configurado.'];
    $fields['client_id'] = $clientId;
    $fields['client_secret'] = $secret;
    $curl = curl_init(melhor_envio_token_url());
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: ' . melhor_envio_user_agent(),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = (string)curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data) || !melhor_envio_save_credentials($data)) {
        error_log('Melhor Envio OAuth error: HTTP ' . $status . ' / ' . $error . ' / ' . $raw);
        return ['ok' => false, 'message' => 'O Melhor Envio não aceitou a autorização. Confira Client ID, Secret e URL de retorno.', 'status' => $status];
    }
    return ['ok' => true];
}
function melhor_envio_refresh_token(bool $force = false): bool {
    $expires = (int)setting('melhor_envio_token_expires_at', '0');
    if (!$force && $expires > time() + 300) return setting('melhor_envio_token') !== '';
    $refresh = trim(setting('melhor_envio_refresh_token'));
    if ($refresh === '') return false;
    return !empty(melhor_envio_request_token(['grant_type' => 'refresh_token', 'refresh_token' => $refresh])['ok']);
}
function melhor_envio_token(): string {
    $config = store_config();
    melhor_envio_refresh_token();
    return trim(setting('melhor_envio_token', (string)($config['melhor_envio_token'] ?? '')));
}
function melhor_envio_api_request(string $endpoint, array $payload, string $token): array {
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . melhor_envio_user_agent(),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $raw = (string)curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    return [$status, $raw, $error];
}
function melhor_envio_quote(string $destinationCep): array {
    $config = store_config();
    $token = melhor_envio_token();
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
    [$status, $raw, $curlError] = melhor_envio_api_request($endpoint, $payload, $token);
    if (($status === 401 || $status === 403) && melhor_envio_refresh_token(true)) {
        [$status, $raw, $curlError] = melhor_envio_api_request($endpoint, $payload, melhor_envio_token());
    }
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        error_log('Melhor Envio quote error: HTTP ' . $status . ' / ' . $curlError . ' / ' . $raw);
        if ($status === 401 || $status === 403) $message = 'O token do Melhor Envio foi recusado ou não possui permissão para cotação.';
        elseif ($status === 422) $message = 'O Melhor Envio recusou os dados da cotação. Confira CEP de origem e medidas do produto.';
        elseif ($status === 0) $message = 'A VPS não conseguiu se conectar ao Melhor Envio.';
        else $message = 'O Melhor Envio não respondeu à cotação neste momento.';
        return ['ok' => false, 'message' => $message, 'http_status' => $status];
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
        $quotes[] = ['code' => (string)($service['id'] ?? ''), 'name' => $name, 'cents' => $cents, 'days' => is_numeric($days) ? (int)$days : null];
    }
    return $quotes ? ['ok' => true, 'quotes' => $quotes] : ['ok' => false, 'message' => 'Nenhuma modalidade ficou disponível para este CEP.'];
}
function selected_shipping(string $cep, string $code): ?array {
    $result = melhor_envio_quote($cep);
    if (!$result['ok']) return null;
    foreach ($result['quotes'] as $quote) if (hash_equals($quote['code'], $code)) return $quote;
    return null;
}
