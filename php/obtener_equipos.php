<?php
session_start();
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acceso no autorizado.']);
    exit();
}

require_once 'conexion.php';
$usuario_id = $_SESSION['user_id'];

$equipos = [];
try {
    // Seleccionamos solo el id y el nombre para el selector
    $stmt = $conn->prepare("SELECT id, nombre_equipo, mac_address FROM equipos WHERE usuario_id = ? ORDER BY nombre_equipo ASC");
    $stmt->execute([$usuario_id]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($equipos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar la lista de equipos.']);
}
?>