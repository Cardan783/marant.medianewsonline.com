<?php
// Permitir el acceso desde cualquier origen (CORS)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once 'conexion.php';

// Verificar conexión
if (!isset($conn)) {
    // Si la conexión falla, se devuelve un JSON con el error 500
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión a la base de datos"]);
    die();
}

// --- Consulta para obtener el último registro ---
// Se ordena por el campo 'No' (asumiendo que es el ID auto-incremental) en orden descendente y se limita a 1
$sql = "SELECT Temperatura, Presion, Voltaje 
        FROM Ajax_2seg
        LIMIT 1";

$stmt = $conn->query($sql); 

$datos_actuales = [
    "temperatura" => null,
    "presion" => null,
    "voltaje" => null
];

if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Extraer los valores
    $datos_actuales["temperatura"] = (float)$row['Temperatura'];
    $datos_actuales["presion"] = (float)$row['Presion'];
    $datos_actuales["voltaje"] = (float)$row['Voltaje'];
 
} else {
    // Si no hay datos, devolvemos un mensaje de advertencia
    $datos_actuales["error"] = "No se encontraron registros en la tabla 'sensores'.";
}

// Cerrar la conexión
$conn = null;

// Devolver los datos en formato JSON
echo json_encode($datos_actuales);

?>