<?php
// ajax.php - Puente para recibir datos críticos del ESP32

// Establecer la zona horaria a Venezuela
date_default_timezone_set('America/Caracas');

// Habilitar CORS para que tu página web pueda leer el archivo generado desde cualquier dominio
header("Access-Control-Allow-Origin: *");

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Recibir los datos enviados por el ESP32
    $mac = isset($_POST['mac']) ? $_POST['mac'] : '';
    
    // --- DEBUG: Guardar log de intentos ---
    file_put_contents("debug_ajax.log", date("Y-m-d H:i:s") . " - Recibido POST de MAC: " . $mac . "\n", FILE_APPEND);
    // --------------------------------------

    $temp = isset($_POST['temp']) ? $_POST['temp'] : '0.00';
    $pres = isset($_POST['pres']) ? $_POST['pres'] : '0.00';
    $volt = isset($_POST['volt']) ? $_POST['volt'] : '0.00';

    // 2. Validación básica
    if (empty($mac)) {
        http_response_code(400); // Bad Request
        echo "Error: MAC Address requerida";
        exit;
    }

    // 3. Preparar los datos para guardar
    // Usamos JSON porque es muy fácil de leer con JavaScript en tu web
    $data = [
        'timestamp' => time(),          // Hora del servidor (Unix)
        'fecha' => date("Y-m-d H:i:s"), // Hora legible
        'mac' => $mac,
        'temp' => number_format((float)$temp, 2, '.', ''),
        'pres' => number_format((float)$pres, 2, '.', ''),
        'volt' => number_format((float)$volt, 2, '.', '')
    ];

    $json_content = json_encode($data);

    // 4. Definir el nombre del archivo puente
    // Ejemplo: MAC=AABBCCDDEEFF_ajax.txt
    // Se guardará en el mismo directorio donde está este script.
    // Reemplazamos ':' por '-' para asegurar compatibilidad con Windows y consistencia con otros scripts
    $mac_safe = str_replace(':', '-', $mac);
    $filename = "MAC=" . $mac_safe . "_ajax.txt";

    // 5. Guardar el archivo (Sobrescribe el anterior para tener siempre el último dato)
    if (file_put_contents($filename, $json_content) !== false) {
        echo "OK"; // Respuesta que espera el ESP32 (aunque no la valida estrictamente)
    } else {
        http_response_code(500);
        echo "Error: No se pudo escribir el archivo. Verifique permisos de carpeta.";
    }

} else {
    echo "Script AJAX activo. Esperando datos POST...";
}
?>