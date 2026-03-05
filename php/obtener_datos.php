<?php
header('Content-Type: application/json');
require_once 'conexion.php';

// 1. Requerir el ID del equipo
if (!isset($_GET['equipo_id']) || !is_numeric($_GET['equipo_id'])) {
    http_response_code(400); // Bad Request
    die(json_encode(['error' => 'Falta el ID del equipo.']));
}
$equipo_id = (int)$_GET['equipo_id'];

// --- Verificación de permisos (Opcional pero recomendado) ---
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['error' => 'No autorizado.']));
}

// Verificar que el equipo pertenezca al usuario
$stmt_check = $conn->prepare("SELECT id FROM equipos WHERE id = ? AND usuario_id = ?");
$stmt_check->execute([$equipo_id, $_SESSION['user_id']]);
if ($stmt_check->rowCount() === 0) {
    http_response_code(403);
    die(json_encode(['error' => 'Este equipo no te pertenece.']));
}
// --- Fin de la verificación ---


$fecha_inicio_str = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
$fecha_fin_str = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

$where_clauses = ["equipo_id = ?"];
$params = [$equipo_id];

if ($fecha_inicio_str && $fecha_fin_str) {
    try {
        $fecha_inicio = DateTime::createFromFormat('d-m-Y', $fecha_inicio_str)->format('Y-m-d 00:00:00');
        $fecha_fin = DateTime::createFromFormat('d-m-Y', $fecha_fin_str)->format('Y-m-d 23:59:59');
        $where_clauses[] = "created_at BETWEEN ? AND ?";
        array_push($params, $fecha_inicio, $fecha_fin);
    } catch (Exception $e) {
        // Fechas inválidas, no se aplican
    }
} else {
    // Por defecto, mostrar las últimas 24 horas si no hay fecha
    $where_clauses[] = "created_at >= NOW() - INTERVAL 24 HOUR";
}

$sql = "SELECT temperatura, presion, voltaje, DATE_FORMAT(created_at, '%H:%i') as hora FROM sensores WHERE " . implode(' AND ', $where_clauses) . " ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$temperatura = [];
$presion = [];
$voltaje = [];

foreach ($resultados as $fila) {
    $labels[] = $fila['hora'];
    $temperatura[] = $fila['temperatura'];
    $presion[] = $fila['presion'];
    $voltaje[] = $fila['voltaje'];
}

// Obtener el último valor registrado para las tarjetas
$ultimo_sensor = end($resultados);

// Obtener estadísticas del rango de tiempo
$sql_stats = "SELECT 
                MAX(temperatura) as max_temp_rango,
                MIN(presion) as min_presion_rango,
                MAX(voltaje) as max_voltaje_rango,
                MIN(voltaje) as min_voltaje_rango
              FROM sensores WHERE " . implode(' AND ', $where_clauses);

$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->execute($params);
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);


$response = [
    'labels' => $labels,
    'temperatura' => $temperatura,
    'presion' => $presion,
    'voltaje' => $voltaje,
    'temperatura_actual' => $ultimo_sensor['temperatura'] ?? null,
    'presion_actual' => $ultimo_sensor['presion'] ?? null,
    'voltaje_actual' => $ultimo_sensor['voltaje'] ?? null,
    'max_temp_rango' => $stats['max_temp_rango'],
    'min_presion_rango' => $stats['min_presion_rango'],
    'max_voltaje_rango' => $stats['max_voltaje_rango'],
    'min_voltaje_rango' => $stats['min_voltaje_rango'],
];

echo json_encode($response);
?>