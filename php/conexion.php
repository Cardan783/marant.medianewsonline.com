<?php
date_default_timezone_set('America/Caracas');
// Evitar que los warnings ensucien la salida JSON
error_reporting(0); 

$path = __DIR__ . '/.env';

if (!file_exists($path)) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "Archivo .env no encontrado"]));
}

$variables = parse_ini_file($path);

if (!$variables) {
    header('Content-Type: application/json');
    die(json_encode(["error" => "Error de formato en el archivo .env (no uses <?php ni $)"]));
}

try {
    $dsn = "mysql:host=" . $variables['DB_HOST'] . ";dbname=" . $variables['DB_NAME'] . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $conn = new PDO($dsn, $variables['DB_USER'], $variables['DB_PASS'], $options);
    $conn->exec("SET time_zone = '-04:00'");
    // Se comenta para evitar que ensucie la salida HTML de otros scripts
    // header('Content-Type: application/json');
    // echo json_encode(["status" => "success", "message" => "Conectado a " . $variables['DB_NAME']]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(["error" => "Fallo de conexion", "detalles" => $e->getMessage()]);
    exit();
}