<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/session.php';

$u = exigir_login();

// ==========================================
// 1. PETICIÓN GET: Consulta de Pedidos
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($u['rol'] === 'jefe') {
        $stmt = $pdo->query(
            'SELECT p.id_pedido, p.fecha_pedido, u.usuario, e.nombre AS estado, 
                    p.direccion_entrega, p.telefono_contacto, p.observaciones, 
                    p.subtotal, p.costo_envio, p.total 
             FROM pedidos p 
             JOIN usuarios u ON u.id_usuario = p.id_usuario 
             JOIN estados_pedido e ON e.id_estado = p.id_estado 
             ORDER BY p.id_pedido DESC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT p.id_pedido, p.fecha_pedido, e.nombre AS estado, 
                    p.direccion_entrega, p.telefono_contacto, p.observaciones, 
                    p.subtotal, p.costo_envio, p.total 
             FROM pedidos p 
             JOIN estados_pedido e ON e.id_estado = p.id_estado 
             WHERE p.id_usuario = ? 
             ORDER BY p.id_pedido DESC'
        );
        $stmt->execute([$u['id_usuario']]);
    }

    $pedidos = $stmt->fetchAll();

    // Cargar el detalle de cada pedido (platillos comprados)
    if ($pedidos) {
        $ids = array_column($pedidos, 'id_pedido');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $det = $pdo->prepare(
            "SELECT d.id_pedido, d.id_comida, d.cantidad, d.precio_unitario, d.subtotal,
                    c.nombre, c.imagen
             FROM detalle_pedido d
             JOIN comidas c ON c.id_comida = d.id_comida
             WHERE d.id_pedido IN ($placeholders)
             ORDER BY d.id_pedido DESC, d.id_detalle ASC"
        );
        $det->execute($ids);

        $itemsPorPedido = [];
        foreach ($det->fetchAll() as $item) {
            $itemsPorPedido[$item['id_pedido']][] = $item;
        }

        foreach ($pedidos as &$pedido) {
            $pedido['items'] = $itemsPorPedido[$pedido['id_pedido']] ?? [];
        }
        unset($pedido);
    }

    responder(['ok' => true, 'pedidos' => $pedidos]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$accion = $_POST['accion'] ?? '';

// ==========================================
// 2. ACCIÓN: Cancelar Pedido (Cliente)
// ==========================================
if ($accion === 'cancelar_pedido') {
    if ($u['rol'] === 'jefe') {
        responder(['ok' => false, 'mensaje' => 'La cancelación desde esta acción es para clientes.'], 403);
    }

    $idPedido = (int)($_POST['id_pedido'] ?? 0);
    if ($idPedido <= 0) responder(['ok' => false, 'mensaje' => 'Pedido inválido.'], 422);

    try {
        $stmt = $pdo->prepare(
            "SELECT p.id_pedido
             FROM pedidos p
             JOIN estados_pedido e ON e.id_estado = p.id_estado
             WHERE p.id_pedido = ? AND p.id_usuario = ? AND e.nombre = 'pendiente'
             LIMIT 1"
        );
        $stmt->execute([$idPedido, $u['id_usuario']]);

        if (!$stmt->fetch()) {
            responder(['ok' => false, 'mensaje' => 'El pedido no existe, no pertenece a tu cuenta o ya no está pendiente.'], 409);
        }

        $estadoCancelado = $pdo->query("SELECT id_estado FROM estados_pedido WHERE nombre = 'cancelado' LIMIT 1")->fetchColumn();

        if ($estadoCancelado === false || $estadoCancelado === null) {
            responder(['ok' => false, 'mensaje' => 'No está configurado el estado cancelado en la base de datos.'], 500);
        }

        $stmt = $pdo->prepare("UPDATE pedidos SET id_estado = ? WHERE id_pedido = ? AND id_usuario = ?");
        $stmt->execute([$estadoCancelado, $idPedido, $u['id_usuario']]);

        responder(['ok' => true, 'mensaje' => 'Pedido cancelado.', 'id_pedido' => $idPedido]);
    } catch (Throwable $e) {
        responder(['ok' => false, 'mensaje' => 'No se pudo cancelar el pedido.'], 500);
    }
}

// ==========================================
// 3. ACCIÓN: Cambiar Estado de Pedido (Jefe / Admin)
// ==========================================
if ($accion === 'cambiar_estado') {
    exigir_rol('jefe');

    $idPedido = (int)($_POST['id_pedido'] ?? 0);
    $idEstado = (int)($_POST['id_estado'] ?? 0);

    if ($idPedido <= 0 || $idEstado <= 0) {
        responder(['ok' => false, 'mensaje' => 'Datos de pedido o estado no válidos.'], 422);
    }

    $q = $pdo->prepare("SELECT id_estado FROM estados_pedido WHERE id_estado = ?");
    $q->execute([$idEstado]);
    if (!$q->fetchColumn()) {
        responder(['ok' => false, 'mensaje' => 'El estado indicado no existe.'], 422);
    }

    $stmt = $pdo->prepare("UPDATE pedidos SET id_estado = ? WHERE id_pedido = ?");
    $stmt->execute([$idEstado, $idPedido]);

    responder(['ok' => true, 'mensaje' => 'Estado del pedido actualizado correctamente.']);
}

// ==========================================
// 4. ACCIÓN: Crear Nuevo Pedido (Cliente)
// ==========================================
$items = json_decode($_POST['items'] ?? '[]', true);
if (!is_array($items) || !$items) {
    responder(['ok' => false, 'mensaje' => 'El pedido no contiene productos.'], 422);
}

$direccion = trim($_POST['direccion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
if ($direccion === '' || $telefono === '') {
    responder(['ok' => false, 'mensaje' => 'Completá la dirección y el teléfono.'], 422);
}

// Normaliza el carrito: IDs válidos, cantidades entre 1 y 99
$lineasPedido = [];
foreach ($items as $item) {
    if (!is_array($item)) responder(['ok' => false, 'mensaje' => 'El pedido contiene un producto inválido.'], 422);
    $idComida = (int)($item['id_comida'] ?? 0);
    $cantidad = (int)($item['cantidad'] ?? 0);
    if ($idComida <= 0 || $cantidad < 1) responder(['ok' => false, 'mensaje' => 'El pedido contiene un producto inválido.'], 422);
    $lineasPedido[$idComida] = min(99, ($lineasPedido[$idComida] ?? 0) + min(99, $cantidad));
}

$pdo->beginTransaction();
try {
    $estado = $pdo->query("SELECT id_estado FROM estados_pedido WHERE nombre = 'pendiente'")->fetchColumn();

    // El precio se calcula siempre desde la base de datos (seguridad)
    $q = $pdo->prepare('SELECT precio FROM comidas WHERE id_comida = ? AND disponible = 1');
    $detalle = [];
    $subtotal = 0;
    foreach ($lineasPedido as $idComida => $cantidad) {
        $q->execute([$idComida]);
        $precio = $q->fetchColumn();
        if ($precio === false) throw new RuntimeException('no_disponible');
        $precio = (float)$precio;
        $detalle[] = [$idComida, $cantidad, $precio, $precio * $cantidad];
        $subtotal += $precio * $cantidad;
    }

    $stmt = $pdo->prepare('INSERT INTO pedidos(id_usuario, id_estado, direccion_entrega, telefono_contacto, observaciones, subtotal, total) VALUES(?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$u['id_usuario'], $estado, $direccion, $telefono, trim($_POST['observaciones'] ?? ''), $subtotal, $subtotal]);
    $id = $pdo->lastInsertId();

    $det = $pdo->prepare('INSERT INTO detalle_pedido(id_pedido, id_comida, cantidad, precio_unitario, subtotal) VALUES(?, ?, ?, ?, ?)');
    foreach ($detalle as $l) {
        $det->execute([$id, $l[0], $l[1], $l[2], $l[3]]);
    }

    $pdo->commit();
    responder(['ok' => true, 'mensaje' => 'Pedido creado.', 'id_pedido' => (int)$id]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof RuntimeException && $e->getMessage() === 'no_disponible') {
        responder(['ok' => false, 'mensaje' => 'Alguna de las comidas ya no está disponible.'], 409);
    }
    responder(['ok' => false, 'mensaje' => 'No se pudo crear el pedido.'], 500);
}