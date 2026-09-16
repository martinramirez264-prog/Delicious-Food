<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function responder(array $data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function exigir_login(): array {
    if (empty($_SESSION['usuario'])) responder(['ok'=>false,'mensaje'=>'Sesión no iniciada.'],401);
    return $_SESSION['usuario'];
}
function exigir_rol(string $rol): array {
    $u=exigir_login();
    if (($u['rol']??'') !== $rol) responder(['ok'=>false,'mensaje'=>'Sin permisos.'],403);
    return $u;
}
