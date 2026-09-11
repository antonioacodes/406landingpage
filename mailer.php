<?php
declare(strict_types=1);

function smtp_read($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) break;
        $response .= $line;
        if (preg_match('/^\d{3} /', $line)) break;
    }
    return $response;
}
function smtp_command($socket, string $command, array $accepted = [250]): bool {
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    return in_array($code, $accepted, true);
}
function send_store_email(string $to, string $subject, string $html): bool {
    $host = trim(setting('smtp_host'));
    if ($host === '') return false;
    $port = max(1, (int)setting('smtp_port', '587'));
    $encryption = strtolower(setting('smtp_encryption', 'tls'));
    $username = trim(setting('smtp_username'));
    $password = setting('smtp_password');
    $from = trim(setting('smtp_from', $username));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) return false;

    $target = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($target . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$socket || (int)substr(smtp_read($socket), 0, 3) !== 220) return false;
    $domain = $_SERVER['HTTP_HOST'] ?? 'store.406rec.com';
    if (!smtp_command($socket, 'EHLO ' . $domain)) { fclose($socket); return false; }
    if ($encryption === 'tls') {
        if (!smtp_command($socket, 'STARTTLS', [220]) || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) || !smtp_command($socket, 'EHLO ' . $domain)) {
            fclose($socket); return false;
        }
    }
    if ($username !== '') {
        if (!smtp_command($socket, 'AUTH LOGIN', [334]) ||
            !smtp_command($socket, base64_encode($username), [334]) ||
            !smtp_command($socket, base64_encode($password), [235])) { fclose($socket); return false; }
    }
    if (!smtp_command($socket, 'MAIL FROM:<' . $from . '>') ||
        !smtp_command($socket, 'RCPT TO:<' . $to . '>', [250,251]) ||
        !smtp_command($socket, 'DATA', [354])) { fclose($socket); return false; }
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = "From: 406 REC <{$from}>\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nSubject: {$encodedSubject}\r\nTo: {$to}\r\n";
    $body = str_replace(["\r\n.\r\n", "\n.\n"], ["\r\n..\r\n", "\n..\n"], $html);
    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $ok = (int)substr(smtp_read($socket), 0, 3) === 250;
    smtp_command($socket, 'QUIT', [221]);
    fclose($socket);
    return $ok;
}
function tracking_email_html(string $orderNsu, string $tracking): array {
    $subject = setting('tracking_email_subject', 'Seu pedido 406 foi enviado');
    $html = setting('tracking_email_html', '<h1>Seu pedido foi enviado.</h1><p>Pedido: <strong>{{pedido}}</strong></p><p>Código de rastreio: <strong>{{rastreio}}</strong></p><p><a href="{{link_rastreio}}">Acompanhar entrega</a></p>');
    $link = 'https://rastreamento.correios.com.br/app/index.php';
    $replace = ['{{pedido}}'=>$orderNsu, '{{rastreio}}'=>$tracking, '{{link_rastreio}}'=>$link];
    return [strtr($subject, $replace), strtr($html, $replace)];
}
