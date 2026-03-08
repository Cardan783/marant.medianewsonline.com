<?php
header('Content-Type: application/json');

// Obtener la MAC enviada por parámetro
$mac = isset($_GET['mac']) ? trim($_GET['mac']) : '';

if (empty($mac)) {
    echo json_encode(['files' => [], 'debug' => ['error' => 'MAC no proporcionada']]);
    exit;
}

$debug = [];
$debug['mac'] = $mac;

$dir = __DIR__ . '/../Archivos_SDCards/uploads';
$debug['ruta_1'] = $dir;
if (!is_dir($dir)) {
    $dir = __DIR__ . '/../ArchivosSDCards/uploads';
    $debug['ruta_2'] = $dir;
}
$files = [];
// Adaptar formato: Reemplazar ':' por '-' para coincidir con el nuevo formato de archivos
$mac_archivo = str_replace(':', '-', $mac);
$prefix = "MAC=" . $mac_archivo; // El prefijo que buscamos

if (is_dir($dir)) {
    $allFiles = scandir($dir);
    $debug['archivos_totales'] = count($allFiles);
    $debug['muestra_archivos'] = array_slice($allFiles, 0, 5);
    // Filtrar archivos que empiecen con el prefijo y sean .txt
    $files = array_values(array_filter($allFiles, function($file) use ($prefix) {
        return stripos($file, $prefix) === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'txt';
    }));
}

echo json_encode(['files' => $files, 'debug' => $debug]);
?>