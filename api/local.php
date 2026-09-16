<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

if ($_SERVER['REQUEST_METHOD']==='GET') {
 $stmt=$pdo->query('SELECT * FROM locales WHERE activo=1 ORDER BY id_local LIMIT 1');
 responder(['ok'=>true,'local'=>$stmt->fetch()]);
}
exigir_rol('jefe');
if ($_SERVER['REQUEST_METHOD']!=='POST') responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);

$stmt=$pdo->prepare('UPDATE locales SET nombre=?,direccion=?,telefono=?,correo=?,horario=?,estado=? WHERE id_local=?');
$stmt->execute([trim($_POST['nombre']??''),trim($_POST['direccion']??''),trim($_POST['telefono']??''),trim($_POST['correo']??''),trim($_POST['horario']??''),$_POST['estado']??'abierto',(int)($_POST['id_local']??1)]);
responder(['ok'=>true,'mensaje'=>'Información del local actualizada.']);
