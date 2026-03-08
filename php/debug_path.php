<?php
// c:\Users\Carlos\FTP\php\debug_path.php
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico de Archivos y Rutas</h1>";

$current_dir = __DIR__;
echo "<p><strong>Directorio actual del script (PHP):</strong> $current_dir</p>";

// Rutas a probar
$rutas_a_probar = [
    $current_dir . '/../Archivos_SDCards/uploads',
    $current_dir . '/../ArchivosSDCards/uploads',
    $_SERVER['DOCUMENT_ROOT'] . '/Archivos_SDCards/uploads'
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Ruta Probada</th><th>Existe?</th><th>¿Es Directorio?</th><th>¿Legible?</th><th>Archivos Encontrados</th></tr>";

foreach ($rutas_a_probar as $ruta) {
    $existe = file_exists($ruta) ? '✅ SÍ' : '❌ NO';
    $es_dir = is_dir($ruta) ? '✅ SÍ' : '❌ NO';
    $legible = is_readable($ruta) ? '✅ SÍ' : '❌ NO';
    
    $archivos = "N/A";
    if (is_dir($ruta)) {
        $scan = scandir($ruta);
        $archivos = "Total: " . count($scan) . "<br>";
        // Mostrar los primeros 5 archivos
        $archivos .= implode("<br>", array_slice($scan, 0, 7));
    }

    echo "<tr>";
    echo "<td>$ruta</td>";
    echo "<td>$existe</td>";
    echo "<td>$es_dir</td>";
    echo "<td>$legible</td>";
    echo "<td>$archivos</td>";
    echo "</tr>";
}
echo "</table>";
?>
