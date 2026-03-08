<?php
// Habilitar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para loguear debug
function log_debug($msg) {
    file_put_contents(__DIR__ . "/debug_log.txt", date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// 1. Obtener el nombre del archivo (desde GET o Headers)
$filename = isset($_GET['filename']) ? basename($_GET['filename']) : null;

if (!$filename) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    if (isset($headers['file-name'])) {
        $filename = basename($headers['file-name']);
    }
}

// Fallback por si no hay nombre
if (!$filename) {
    $filename = "archivo_desconocido_" . time() . ".bin";
}

// --- LÓGICA DE EXTRACCIÓN DE MAC Y DIRECTORIO ---

$base_directory = __DIR__ . "/uploads/";

// Si viene de SPIFFS, cambiamos la base
if (isset($_GET['source']) && $_GET['source'] === 'spiffs') {
    $base_directory = __DIR__ . "/uploads_spiffs/";
}

// Extraer la MAC (Ejemplo: MAC=9C-9C-1F-C6-30-66_Domingo... -> 9C-9C-1F-C6-30-66)
// Buscamos lo que esté entre "MAC=" y el primer guion bajo "_"
if (preg_match('/MAC=([a-fA-F0-9-]{17})/', $filename, $matches)) {
    $mac_folder = $matches[1]; // Contiene solo la MAC
    $upload_directory = $base_directory . $mac_folder . "/";
} else {
    // Si no tiene el formato esperado, lo ponemos en una carpeta 'otros' o en la base
    $upload_directory = $base_directory . "sin_mac/";
}

// Crear el directorio específico de la MAC si no existe
if (!is_dir($upload_directory)) {
    if (!mkdir($upload_directory, 0755, true)) {
        log_debug("Error: No se pudo crear el directorio: $upload_directory");
        http_response_code(500);
        echo "Error: Permisos de escritura en servidor.";
        exit;
    }
}

// --- PROCESO DE GUARDADO ---

$target_path = $upload_directory . $filename;
log_debug("Iniciando carga en carpeta de MAC: $filename");

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
        $msg = "Exito: $filename guardado en $upload_directory ($bytes_written bytes).";
        log_debug($msg);
        echo $msg;
    } else {
        http_response_code(200); 
        $msg = "Alerta: Archivo vacio en $upload_directory.";
        log_debug($msg);
        echo $msg;
    }
} else {
    http_response_code(500);
    log_debug("Error al abrir flujos. Ruta: $target_path");
    echo "Error de servidor al guardar.";
}
?>