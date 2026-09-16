<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';
$u=exigir_login();

if ($_SERVER['REQUEST_METHOD']==='GET') {
 if ($u['rol']==='jefe') {
  $stmt=$pdo->query('SELECT p.id_pedido,p.fecha_pedido,u.usuario,e.nombre AS estado,p.subtotal,p.costo_envio,p.total FROM pedidos p JOIN usuarios u ON u.id_usuario=p.id_usuario JOIN estados_pedido e ON e.id_estado=p.id_estado ORDER BY p.id_pedido DESC');
 } else {
  $stmt=$pdo->prepare('SELECT p.id_pedido,p.fecha_pedido,e.nombre AS estado,p.subtotal,p.costo_envio,p.total FROM pedidos p JOIN estados_pedido e ON e.id_estado=p.id_estado WHERE p.id_usuario=? ORDER BY p.id_pedido DESC');
  $stmt->execute([$u['id_usuario']]);
 }
 responder(['ok'=>true,'pedidos'=>$stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD']!=='POST') responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
$items=json_decode($_POST['items']??'[]',true);
if (!is_array($items)||!$items) responder(['ok'=>false,'mensaje'=>'El pedido no contiene productos.'],422);

$pdo->beginTransaction();
try {
 $estado=$pdo->query("SELECT id_estado FROM estados_pedido WHERE nombre='pendiente'")->fetchColumn();
 $subtotal=0;
 foreach($items as $item){
  $q=$pdo->prepare('SELECT precio FROM comidas WHERE id_comida=? AND disponible=1');
  $q->execute([(int)$item['id_comida']]);
  $precio=$q->fetchColumn();
  if($precio===false) throw new Exception();
  $subtotal+=(float)$precio*max(1,(int)$item['cantidad']);
 }
 $stmt=$pdo->prepare('INSERT INTO pedidos(id_usuario,id_estado,direccion_entrega,telefono_contacto,observaciones,subtotal,total) VALUES(?,?,?,?,?,?,?)');
 $stmt->execute([$u['id_usuario'],$estado,trim($_POST['direccion']??''),trim($_POST['telefono']??''),trim($_POST['observaciones']??''),$subtotal,$subtotal]);
 $id=$pdo->lastInsertId();

 $det=$pdo->prepare('INSERT INTO detalle_pedido(id_pedido,id_comida,cantidad,precio_unitario,subtotal) VALUES(?,?,?,?,?)');
 foreach($items as $item){
  $q=$pdo->prepare('SELECT precio FROM comidas WHERE id_comida=? AND disponible=1');
  $q->execute([(int)$item['id_comida']]);
  $precio=(float)$q->fetchColumn();
  $cantidad=max(1,(int)$item['cantidad']);
  $det->execute([$id,(int)$item['id_comida'],$cantidad,$precio,$precio*$cantidad]);
 }
 $pdo->commit();
 responder(['ok'=>true,'mensaje'=>'Pedido creado.','id_pedido'=>(int)$id]);
} catch(Throwable $e) {
 $pdo->rollBack();
 responder(['ok'=>false,'mensaje'=>'No se pudo crear el pedido.'],500);
}
