<?php
// Habilitar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para loguear debug
function log_debug($msg) {
    file_put_contents(__DIR__ . "/debug_log.txt", date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

$upload_directory = __DIR__ . "/uploads/";

// Verificar si el archivo viene de la memoria interna (SPIFFS)
if (isset($_GET['source']) && $_GET['source'] === 'spiffs') {
    $upload_directory = __DIR__ . "/uploads_spiffs/";
}

// Crear directorio
if (!is_dir($upload_directory)) {
    if (!mkdir($upload_directory, 0755, true)) {
        log_debug("Error: No se pudo crear el directorio uploads.");
        http_response_code(500);
        echo "Error: No se pudo crear directorio.";
        exit;
    }
}

// 1. Intentar obtener nombre por GET (más seguro)
$filename = isset($_GET['filename']) ? basename($_GET['filename']) : null;

// 2. Si no, intentar por Headers (manejando mayúsculas/minúsculas)
if (!$filename) {
    $headers = getallheaders();
    // Normalizar headers a minúsculas para buscar 'file-name'
    $headers_lower = array_change_key_case($headers, CASE_LOWER);
    if (isset($headers_lower['file-name'])) {
        $filename = basename($headers_lower['file-name']);
    }
}

// 3. Fallback
if (!$filename) {
    $filename = "archivo_desconocido_" . time() . ".bin";
    log_debug("Advertencia: No se detectó nombre de archivo, usando genérico.");
}

$target_path = $upload_directory . $filename;
log_debug("Iniciando carga: $filename");

// Leer entrada
$input = fopen("php://input", "rb");
$file = fopen($target_path, "wb");

if ($input && $file) {
    $bytes_written = 0;
    while ($data = fread($input, 4096)) {
        $bytes_written += fwrite($file, $data);
    }
    fclose($input);
    fclose($file);
    
    if ($bytes_written > 0) {
        http_response_code(200);
        $msg = "Exito: $filename guardado ($bytes_written bytes).";
        log_debug($msg);
        echo $msg;
    } else {
        // Si el archivo se creó pero tiene 0 bytes
        http_response_code(200); // Sigue siendo 200 OK HTTP, pero avisamos
        $msg = "Alerta: Archivo $filename creado pero vacio (0 bytes recibidos).";
        log_debug($msg);
        echo $msg;
    }
} else {
    http_response_code(500);
    $err = "Error al abrir flujos de archivo. Permisos?";
    log_debug($err);
    echo $err;
}
?>
