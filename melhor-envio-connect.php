<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}
$clientId = trim(setting('melhor_envio_client_id'));
$secret = trim(setting('melhor_envio_client_secret'));
$redirectUri = trim(setting('melhor_envio_redirect_uri', 'https://store.406rec.com/melhor-envio-callback.php'));
if ($clientId === '' || $secret === '') {
    header('Location: admin.php#integracoes');
    exit;
}
$state = bin2hex(random_bytes(24));
$_SESSION['melhor_envio_oauth_state'] = $state;
$_SESSION['melhor_envio_oauth_started_at'] = time();
$query = http_build_query([
    'client_id' => $clientId,
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'state' => $state,
    'scope' => 'shipping-calculate',
]);
header('Location: https://melhorenvio.com.br/oauth/authorize?' . $query);
exit;
