<?php
header('Content-Type: application/json');

// Obtener la MAC enviada por parámetro
$mac = isset($_GET['mac']) ? trim($_GET['mac']) : '';

if (empty($mac)) {
    echo json_encode([]);
    exit;
}

$dir = __DIR__ . '/../Archivos_SDCards/uploads';
$files = [];
$prefix = "MAC=" . $mac; // El prefijo que buscamos

if (is_dir($dir)) {
    $allFiles = scandir($dir);
    // Filtrar archivos que empiecen con el prefijo y sean .txt
    $files = array_values(array_filter($allFiles, function($file) use ($prefix) {
        return strpos($file, $prefix) === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'txt';
    }));
}

echo json_encode($files);
?>