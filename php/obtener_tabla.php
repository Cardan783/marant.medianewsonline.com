<?php
// Permitir el acceso desde cualquier origen (CORS). 
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// --- Detalles de la conexión a la base de datos ---
$servername = "fdb1034.awardspace.net";
$username = "4698095_marant"; 
$password = "nU1GxhcPCFkG98@";     
$dbname = "4698095_marant"; 


// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["data" => [], "message" => "Conexión fallida: " . $conn->connect_error]);
  die();
}

// Función auxiliar para validar y formatear fechas
function validateAndFormatDate($date, $format = 'd-m-Y', $outputFormat = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date ? $d->format($outputFormat) : null;
}

// --- Preparar la consulta a la base de datos ---
$sql = "SELECT Fecha, Hora, Temperatura, Presion, Voltaje FROM sensores";
$params = [];
$types = "";
$where_clauses = [];

// 1. Filtros de Fecha (usan 's' string)
if (isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio'])) {
    $fecha_inicio_ddmmyyyy = $_GET['fecha_inicio'];
    $fecha_inicio = validateAndFormatDate($fecha_inicio_ddmmyyyy, 'd-m-Y');
    
    if ($fecha_inicio) {
        $where_clauses[] = "Fecha >= ?";
        $params[] = $fecha_inicio;
        $types .= "s";
    }
}

if (isset($_GET['fecha_fin']) && !empty($_GET['fecha_fin'])) {
    $fecha_fin_ddmmyyyy = $_GET['fecha_fin'];
    $fecha_fin = validateAndFormatDate($fecha_fin_ddmmyyyy, 'd-m-Y');
    
    if ($fecha_fin) {
        $where_clauses[] = "Fecha <= ?";
        $params[] = $fecha_fin;
        $types .= "s";
    }
}

// 2. Filtros de Sensores (usan 'd' double/float)

// Filtro de Temperatura Mínima (Temperatura >= X)
if (isset($_GET['temp_min']) && is_numeric($_GET['temp_min'])) {
    $temp_min = (float)$_GET['temp_min'];
    $where_clauses[] = "Temperatura >= ?"; 
    $params[] = $temp_min;
    $types .= "d"; 
}

// Filtro de Presión Máxima (Presion <= X)
if (isset($_GET['presion_max']) && is_numeric($_GET['presion_max'])) {
    $presion_max = (float)$_GET['presion_max'];
    $where_clauses[] = "Presion <= ?"; // CONDICIÓN: Menor o igual a la presión máxima enviada
    $params[] = $presion_max;
    $types .= "d";
}

// Filtro de Voltaje Mínimo (Voltaje >= X)
if (isset($_GET['voltaje_min']) && is_numeric($_GET['voltaje_min'])) {
    $voltaje_min = (float)$_GET['voltaje_min'];
    $where_clauses[] = "Voltaje >= ?"; 
    $params[] = $voltaje_min;
    $types .= "d";
}


// Combinar todos los filtros en la cláusula WHERE
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Ordenar por fecha y hora (más reciente primero)
$sql .= " ORDER BY Fecha DESC, Hora DESC";


$datos_tabla = [];
$result = false;

// Ejecutar la consulta preparada
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    
    if (!empty($types)) {
        // Enlazar los tipos y los parámetros dinámicamente
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
    }

} else {
    // Si no hay filtros, usar consulta simple
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Formatear la salida para DataTables: array de arrays
        $datos_tabla[] = [
            $row['Fecha'] . ' ' . $row['Hora'],
            $row['Temperatura'] . ' °C',
            $row['Presion'] . ' hPa',
            $row['Voltaje'] . ' V'
        ];
    }
}

$conn->close();

// Devolver los datos en formato DataTables
echo json_encode(["data" => $datos_tabla]);
?>