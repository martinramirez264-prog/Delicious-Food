<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

$u = exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query(
        "SELECT c.id_comida,c.nombre,c.descripcion,c.ingredientes,c.preparacion,
                c.precio,c.imagen,c.disponible,c.id_categoria,cat.nombre AS categoria
         FROM comidas c
         JOIN categorias cat ON cat.id_categoria=c.id_categoria
         ORDER BY c.id_comida ASC"
    );
    responder(['ok'=>true,'comidas'=>$stmt->fetchAll()]);
}

exigir_rol('jefe');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'listar_categorias') {
    $stmt = $pdo->query("SELECT id_categoria,nombre FROM categorias WHERE activo=1 ORDER BY nombre");
    responder(['ok'=>true,'categorias'=>$stmt->fetchAll()]);
}

if ($accion === 'crear' || $accion === 'editar') {
    $id = (int)($_POST['id_comida'] ?? 0);
    $idCategoria = (int)($_POST['id_categoria'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $ingredientes = trim($_POST['ingredientes'] ?? '');
    $preparacion = trim($_POST['preparacion'] ?? '');
    $precio = (float)($_POST['precio'] ?? 0);
    $imagen = trim($_POST['imagen'] ?? '');
    $disponible = isset($_POST['disponible']) ? (int)!!$_POST['disponible'] : 1;

    if ($idCategoria <= 0 || $nombre === '' || $precio < 0) {
        responder(['ok'=>false,'mensaje'=>'Completá categoría, nombre y precio válido.'],422);
    }

    $q = $pdo->prepare("SELECT id_categoria FROM categorias WHERE id_categoria=? AND activo=1");
    $q->execute([$idCategoria]);
    if (!$q->fetchColumn()) responder(['ok'=>false,'mensaje'=>'La categoría no existe.'],422);

    if ($accion === 'crear') {
        $stmt = $pdo->prepare(
            "INSERT INTO comidas
             (id_categoria,nombre,descripcion,ingredientes,preparacion,precio,imagen,disponible)
             VALUES(?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([$idCategoria,$nombre,$descripcion,$ingredientes,$preparacion,$precio,$imagen,$disponible]);
        responder(['ok'=>true,'mensaje'=>'Comida creada correctamente.','id_comida'=>(int)$pdo->lastInsertId()]);
    }

    $stmt = $pdo->prepare(
        "UPDATE comidas SET id_categoria=?,nombre=?,descripcion=?,ingredientes=?,
                preparacion=?,precio=?,imagen=?,disponible=? WHERE id_comida=?"
    );
    $stmt->execute([$idCategoria,$nombre,$descripcion,$ingredientes,$preparacion,$precio,$imagen,$disponible,$id]);
    responder(['ok'=>true,'mensaje'=>'Comida actualizada correctamente.']);
}

if ($accion === 'eliminar') {
    $id = (int)($_POST['id_comida'] ?? 0);
    if ($id <= 0) responder(['ok'=>false,'mensaje'=>'Comida no válida.'],422);

    // No borramos físicamente: se desactiva para no romper pedidos históricos.
    $stmt = $pdo->prepare("UPDATE comidas SET disponible=0 WHERE id_comida=?");
    $stmt->execute([$id]);
    responder(['ok'=>true,'mensaje'=>'Comida desactivada.']);
}

if ($accion === 'reactivar') {
    $id = (int)($_POST['id_comida'] ?? 0);
    if ($id <= 0) responder(['ok'=>false,'mensaje'=>'Comida no válida.'],422);
    $stmt = $pdo->prepare("UPDATE comidas SET disponible=1 WHERE id_comida=?");
    $stmt->execute([$id]);
    responder(['ok'=>true,'mensaje'=>'Comida reactivada.']);
}

responder(['ok'=>false,'mensaje'=>'Acción no reconocida.'],400);
