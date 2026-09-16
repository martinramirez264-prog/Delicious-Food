<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    responder(['ok'=>false,'mensaje'=>'Comida no válida.'],422);
}

$stmt = $pdo->prepare(
    'SELECT c.id_comida,c.nombre,c.descripcion,c.ingredientes,c.preparacion,
            c.precio,c.imagen,c.disponible,cat.nombre AS categoria
     FROM comidas c
     JOIN categorias cat ON cat.id_categoria=c.id_categoria
     WHERE c.id_comida=? AND c.disponible=1
     LIMIT 1'
);
$stmt->execute([$id]);
$comida = $stmt->fetch();

if (!$comida) {
    responder(['ok'=>false,'mensaje'=>'La comida no existe o no está disponible.'],404);
}

responder(['ok'=>true,'comida'=>$comida]);
