<?php
// 1. Manejo de la ruta del archivo de conexión
if (file_exists('../conexion.php')) {
    require_once '../conexion.php';
} else {
    require_once 'conexion.php';
}

header('Content-Type: application/json');

if (!isset($conn)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit();
}

/**
 * Adaptación para la nueva tabla:
 * - Usamos 'created_at' como nuestra fuente única de tiempo.
 * - Quitamos la columna 'equipo_id' (MAC) de la respuesta.
 */
$sql = "SELECT 
            created_at as fecha, 
            temperatura as temperatura, 
            presion as presion, 
            voltaje as voltaje 
        FROM sensores 
        WHERE 1=1";
$params = [];

// 1. Filtro de Fechas (Adaptado para la columna created_at)
if (isset($_GET['fecha_inicio']) && isset($_GET['fecha_fin'])) {
    // Agregamos horas extremas para cubrir el día completo (00:00:00 a 23:59:59)
    $sql .= " AND created_at BETWEEN ? AND ?";
    $params[] = $_GET['fecha_inicio'] . " 00:00:00";
    $params[] = $_GET['fecha_fin'] . " 23:59:59";
} else {
    // Si no hay filtro, mostramos solo los registros de HOY por defecto
    $sql .= " AND DATE(created_at) = CURDATE()";
}

// 1.1 Filtro por Equipo (ID)
if (isset($_GET['equipo_id']) && !empty($_GET['equipo_id'])) {
    $sql .= " AND equipo_id = ?";
    $params[] = $_GET['equipo_id'];
} elseif (isset($_GET['mac']) && !empty($_GET['mac'])) {
    $sql .= " AND equipo_id = ?";
    $params[] = $_GET['mac'];
}

// 2. Filtro "Solo Alertas"
if (isset($_GET['solo_alertas']) && $_GET['solo_alertas'] === 'true') {
    $sql .= " AND temperatura >= ?";
    $params[] = 85; 
} else {
    // 3. Filtros de Temperatura
    if (isset($_GET['temp_min']) && is_numeric($_GET['temp_min'])) {
        $sql .= " AND temperatura >= ?";
        $params[] = (float)$_GET['temp_min'];
    }
    if (isset($_GET['temp_max']) && is_numeric($_GET['temp_max'])) {
        $sql .= " AND temperatura <= ?";
        $params[] = (float)$_GET['temp_max'];
    }
}

// 4. Filtros de Presión
if (isset($_GET['presion_min']) && is_numeric($_GET['presion_min'])) {
    $sql .= " AND presion >= ?";
    $params[] = (float)$_GET['presion_min'];
}
if (isset($_GET['presion_max']) && is_numeric($_GET['presion_max'])) {
    $sql .= " AND presion <= ?";
    $params[] = (float)$_GET['presion_max'];
}

// 5. Ordenar y Limitar
// Se prioriza el límite que venga por GET, sino, se usa un default.
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 2000;

$sql .= " ORDER BY created_at DESC LIMIT " . $limit;

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Enviamos los datos listos para el frontend
    echo json_encode($resultados);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la consulta: ' . $e->getMessage()]);
}
?>
