<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';
require __DIR__ . '/shipping.php';

function page(string $title, string $text, bool $ok = false): never {
    http_response_code($ok ? 200 : 400);
    $color = $ok ? '#16a34a' : '#dc2626';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>406 Store</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#090807;color:#f1e8dc;font-family:Arial,sans-serif}.card{width:min(460px,88vw);padding:34px;background:#14110f;border:1px solid #362b26}h1{margin:0 0 12px;color:' . $color . '}p{line-height:1.55;color:#b6aaa0}a{display:inline-block;margin-top:18px;padding:13px 16px;background:#d1272f;color:white;text-decoration:none;font-weight:bold;font-size:12px}</style><main class="card"><h1>' . $safeTitle . '</h1><p>' . $safeText . '</p><a href="admin.php#integracoes">VOLTAR AO PAINEL</a></main>';
    exit;
}
if (!isset($_SESSION['admin_id'])) page('Sessão expirada', 'Entre no painel e reinicie a conexão do Melhor Envio.');
$expected = (string)($_SESSION['melhor_envio_oauth_state'] ?? '');
$started = (int)($_SESSION['melhor_envio_oauth_started_at'] ?? 0);
$state = (string)($_GET['state'] ?? '');
$code = trim((string)($_GET['code'] ?? ''));
unset($_SESSION['melhor_envio_oauth_state'], $_SESSION['melhor_envio_oauth_started_at']);
if ($code === '' || $expected === '' || !hash_equals($expected, $state) || $started < time() - 900) {
    page('Autorização inválida', 'A solicitação expirou ou não corresponde à conexão iniciada no painel.');
}
$redirectUri = trim(setting('melhor_envio_redirect_uri', 'https://store.406rec.com/melhor-envio-callback.php'));
$result = melhor_envio_request_token([
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirectUri,
    'code' => $code,
]);
if (empty($result['ok'])) page('Não foi possível conectar', (string)($result['message'] ?? 'Tente novamente pelo painel.'));
page('Melhor Envio conectado', 'A conta foi autorizada. O token foi salvo com segurança e será renovado automaticamente.', true);
