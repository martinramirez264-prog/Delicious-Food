<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);

$usuario=trim($_POST['usuario']??'');
$correo=strtolower(trim($_POST['correo']??''));
$clave=$_POST['clave']??'';
$terminos=!empty($_POST['terminos']);

if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/',$usuario)) responder(['ok'=>false,'mensaje'=>'El usuario debe tener entre 3 y 30 caracteres.'],422);
if (!filter_var($correo,FILTER_VALIDATE_EMAIL)) responder(['ok'=>false,'mensaje'=>'El correo no es válido.'],422);
if (strlen($clave)<6) responder(['ok'=>false,'mensaje'=>'La contraseña debe tener al menos 6 caracteres.'],422);
if (!$terminos) responder(['ok'=>false,'mensaje'=>'Debés aceptar los términos y condiciones.'],422);

$stmt=$pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario=? OR correo=? LIMIT 1');
$stmt->execute([$usuario,$correo]);
if ($stmt->fetch()) responder(['ok'=>false,'mensaje'=>'El usuario o correo ya está registrado.'],409);

$rol=$pdo->query("SELECT id_rol FROM roles WHERE nombre='cliente'")->fetchColumn();
$hash=password_hash($clave,PASSWORD_DEFAULT);

$stmt=$pdo->prepare('INSERT INTO usuarios(id_rol,usuario,correo,contrasena,activo,acepta_terminos) VALUES(?,?,?, ?,1,1)');
$stmt->execute([$rol,$usuario,$correo,$hash]);

responder(['ok'=>true,'mensaje'=>'Cuenta creada correctamente.']);
