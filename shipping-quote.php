<?php
declare(strict_types=1);
require __DIR__ . '/database.php';
require __DIR__ . '/shipping.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    echo json_encode(melhor_envio_quote((string)($_GET['cep'] ?? '')), JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('Shipping quote failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Não foi possível consultar o frete. Confira o token e as integrações no painel.'
    ], JSON_UNESCAPED_UNICODE);
}
