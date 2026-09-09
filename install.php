<?php
declare(strict_types=1);
require __DIR__ . '/database.php';
$pdo = db();
if ((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0) { header('Location: admin.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $password=$_POST['password']??'';
    if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<10) $error='Preencha nome, e-mail e uma senha de pelo menos 10 caracteres.';
    else { $pdo->prepare('INSERT INTO admins(name,email,password_hash) VALUES(?,?,?)')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]); header('Location: admin.php'); exit; }
}
?><!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalar painel 406</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b0908;color:#eee;font-family:Arial}form{display:grid;gap:13px;width:min(430px,88vw)}input,button{padding:14px;background:#161210;color:white;border:1px solid #453530}button{background:#b71922;border:0;font-weight:bold}.err{color:#ff9a9a}</style><form method="post"><h1>406 / PRIMEIRO ACESSO</h1><p>Crie o administrador da loja. Depois disso, esta página deixa de permitir cadastros.</p><input name="name" placeholder="Seu nome" required autofocus><input name="email" type="email" placeholder="E-mail administrativo" required><input name="password" type="password" placeholder="Senha (10 ou mais caracteres)" minlength="10" required><button>CRIAR ACESSO ADMIN</button><p class="err"><?=htmlspecialchars($error)?></p></form></html>