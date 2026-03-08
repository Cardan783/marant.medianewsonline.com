<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$mac = isset($_GET['mac']) ? trim($_GET['mac']) : '';

if (empty($mac)) {
    http_response_code(400);
    echo json_encode(["error" => "MAC no proporcionada"]);
    exit;
}

// Formatear MAC: AA:BB:CC -> AA-BB-CC para coincidir con el nombre del archivo
$mac_archivo = str_replace(':', '-', $mac);
$nombre_archivo = "MAC=" . $mac_archivo . "_ajax.txt";

// Directorios donde buscar el archivo
$directorios = [
    __DIR__ . '/../Archivos_SDCards',
    __DIR__ . '/../Archivos_SDCards/uploads'
];

$ruta_final = null;

foreach ($directorios as $dir) {
    $ruta_temp = $dir . '/' . $nombre_archivo;
    if (file_exists($ruta_temp)) {
        $ruta_final = $ruta_temp;
        break;
    }
}

if ($ruta_final) {
    readfile($ruta_final);
} else {
    // Devolvemos 200 OK para evitar el error rojo en la consola del navegador
    http_response_code(200);
    echo json_encode(["status" => "offline", "error" => "Archivo no encontrado"]);
}
?>