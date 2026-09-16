<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);

$dato=trim($_POST['usuario']??'');
$clave=$_POST['clave']??'';
if ($dato==='' || $clave==='') responder(['ok'=>false,'mensaje'=>'Completá usuario y contraseña.'],422);

$stmt=$pdo->prepare(
 'SELECT u.id_usuario,u.usuario,u.correo,u.contrasena,r.nombre AS rol
  FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol
  WHERE (u.usuario=? OR LOWER(u.correo)=LOWER(?)) AND u.activo=1 LIMIT 1'
);
$stmt->execute([$dato,$dato]);
$cuenta=$stmt->fetch();

if (!$cuenta || !password_verify($clave,$cuenta['contrasena']))
    responder(['ok'=>false,'mensaje'=>'Usuario/correo o contraseña incorrectos.'],401);

session_regenerate_id(true);
$_SESSION['usuario']=[
 'id_usuario'=>(int)$cuenta['id_usuario'],
 'usuario'=>$cuenta['usuario'],
 'correo'=>$cuenta['correo'],
 'rol'=>$cuenta['rol']
];

$up=$pdo->prepare('UPDATE usuarios SET ultimo_acceso=NOW() WHERE id_usuario=?');
$up->execute([$cuenta['id_usuario']]);

responder([
 'ok'=>true,
 'mensaje'=>'Inicio de sesión correcto.',
 'usuario'=>$_SESSION['usuario'],
 'redirect'=>$cuenta['rol']==='jefe'?'../jefe.html':'../homenuevo.html'
]);
