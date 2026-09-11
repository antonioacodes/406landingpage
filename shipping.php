<?php
declare(strict_types=1);

function store_config(): array {
    static $config;
    if ($config !== null) return $config;
    $config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    return is_array($config) ? $config : [];
}
function digits(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }
function shipping_services(): array {
    $services = store_config()['correios_services'] ?? ['03298' => 'PAC', '03220' => 'SEDEX'];
    return is_array($services) ? $services : [];
}
function correios_quote(string $destinationCep): array {
    $config = store_config();
    $token = trim((string)($config['correios_token'] ?? ''));
    $origin = digits((string)($config['correios_origin_cep'] ?? ''));
    $destination = digits($destinationCep);
    if ($token === '' || strlen($origin) !== 8) {
        return ['ok' => false, 'message' => 'O cálculo de frete ainda não foi configurado pela loja.'];
    }
    if (strlen($destination) !== 8) return ['ok' => false, 'message' => 'Informe um CEP válido.'];
    $weight = max(1, (int)($config['correios_weight_g'] ?? 350));
    $length = max(15, (int)($config['correios_length_cm'] ?? 32));
    $width = max(10, (int)($config['correios_width_cm'] ?? 25));
    $height = max(1, (int)($config['correios_height_cm'] ?? 4));
    $quotes = [];
    foreach (shipping_services() as $code => $name) {
        $query = http_build_query([
            'cepOrigem' => $origin, 'cepDestino' => $destination, 'psObjeto' => $weight,
            'tpObjeto' => 2, 'comprimento' => $length, 'largura' => $width, 'altura' => $height,
        ]);
        $url = 'https://api.correios.com.br/preco/v1/nacional/' . rawurlencode((string)$code) . '?' . $query;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $raw = (string)curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $data = json_decode($raw, true);
        $price = $data['pcFinal'] ?? $data['preco'] ?? $data['valor'] ?? null;
        if ($status >= 200 && $status < 300 && $price !== null) {
            $normalized = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string)$price) ?? '');
            $cents = (int)round((float)$normalized * 100);
            if ($cents > 0) $quotes[] = ['code'=>(string)$code,'name'=>(string)$name,'cents'=>$cents];
        }
    }
    return $quotes ? ['ok' => true, 'quotes' => $quotes] : ['ok' => false, 'message' => 'Não foi possível cotar este CEP nos Correios agora.'];
}
function selected_shipping(string $cep, string $code): ?array {
    $result = correios_quote($cep);
    if (!$result['ok']) return null;
    foreach ($result['quotes'] as $quote) if (hash_equals($quote['code'], $code)) return $quote;
    return null;
}
