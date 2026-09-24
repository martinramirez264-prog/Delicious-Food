<?php
$host = 'sql309.infinityfree.com';
$db   = 'if0_42960890_deliciousfood';
$user = 'if0_42960890';
$pass = 's3GUuJ3QRN0u';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'mensaje'=>'No se pudo conectar con MySQL.']);
    exit;
}
