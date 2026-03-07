<?php
// Silenciar errores de E_NOTICE para no interferir con la respuesta al ESP32
error_reporting(0);
header("Content-Type: application/json");

// 1. Incluir el archivo de conexión a la base de datos
require_once 'conexion.php';

// 2. Obtener la dirección MAC del parámetro GET
$mac_address = isset($_GET['mac']) ? trim($_GET['mac']) : '';

// 3. Validar que la dirección MAC fue proporcionada
if (empty($mac_address)) {
    echo json_encode(["flag" => 0]);
    exit();
}

try {
    // 4. Preparar la consulta SQL para obtener el 'flag' más reciente
    // Se une la tabla de equipos con la de desactivaciones para encontrar el equipo por su MAC
    // y se ordena por ID de desactivación de forma descendente para obtener el último estado.
    $sql = "SELECT d.flag 
            FROM equipos e 
            JOIN desactivaciones d ON e.id = d.equipo_id 
            WHERE e.mac_address = ? 
            ORDER BY d.id DESC 
            LIMIT 1"; // Solo nos interesa el registro más reciente

    $stmt = $conn->prepare($sql);
    $stmt->execute([$mac_address]);

    // 5. Obtener el resultado
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    // 6. Enviar la respuesta al dispositivo
    if ($resultado) {
        // Si se encuentra un registro, se devuelve el valor del flag (ej: '0' o '1')
        echo json_encode(["flag" => (int)$resultado['flag']]);
    } else {
        // Si no se encuentra el equipo o no tiene registros de desactivación,
        // se devuelve 0 (desactivado) por seguridad.
        echo json_encode(["flag" => 0]);
    }

} catch (PDOException $e) {
    // En caso de un error con la base de datos, se registra para depuración
    // y se envía 0.
    echo json_encode(["flag" => 0]);
    exit();
}

?>
