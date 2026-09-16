<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
$dato=trim($_POST['cuenta']??'');
$nueva=$_POST['nuevaClave']??'';
$confirmar=$_POST['confirmarClave']??'';

if ($dato==='' || strlen($nueva)<6) responder(['ok'=>false,'mensaje'=>'La contraseña debe tener al menos 6 caracteres.'],422);
if ($nueva!==$confirmar) responder(['ok'=>false,'mensaje'=>'Las contraseñas no coinciden.'],422);

$stmt=$pdo->prepare('SELECT id_usuario FROM usuarios WHERE usuario=? OR LOWER(correo)=LOWER(?) LIMIT 1');
$stmt->execute([$dato,$dato]);
$u=$stmt->fetch();
if (!$u) responder(['ok'=>false,'mensaje'=>'Usuario o correo no encontrado.'],404);

$hash=password_hash($nueva,PASSWORD_DEFAULT);
$stmt=$pdo->prepare('UPDATE usuarios SET contrasena=? WHERE id_usuario=?');
$stmt->execute([$hash,$u['id_usuario']]);
responder(['ok'=>true,'mensaje'=>'Contraseña actualizada correctamente.']);
