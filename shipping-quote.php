<?php
declare(strict_types=1);
require __DIR__ . '/database.php';
require __DIR__ . '/shipping.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(melhor_envio_quote((string)($_GET['cep'] ?? '')), JSON_UNESCAPED_UNICODE);
