<?php
// Configuracion segura: no guardes credenciales reales en GitHub.
// En desarrollo puedes crear config/database.local.php usando el ejemplo.
$localConfig = __DIR__ . '/database.local.php';
if (is_file($localConfig)) {
    require $localConfig; // Debe definir $host, $db, $user y $pass.
} else {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';
}
$charset = 'utf8mb4';

if ($db === '' || $user === '') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => 'Falta configurar el acceso a la base de datos.']);
    exit;
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Error de conexion a MySQL: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar con MySQL.']);
    exit;
}
