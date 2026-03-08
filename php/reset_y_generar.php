<?php
// c:\Users\Carlos\FTP\php\reset_y_generar.php
header('Content-Type: text/html; charset=utf-8');

// 1. Definir directorio correcto
$dir = __DIR__ . '/../Archivos_SDCards/uploads';

// 2. Crear directorio si no existe
if (!is_dir($dir)) {
    if (!mkdir($dir, 0777, true)) {
        die("❌ Error crítico: No se pudo crear el directorio $dir. Verifique permisos.");
    }
}

// 3. LIMPIEZA: Borrar todos los archivos .txt existentes
$files = glob($dir . '/*.txt'); 
$borrados = 0;
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
        $borrados++;
    }
}

echo "<h1>🔄 Reinicio del Sistema de Archivos</h1>";
echo "<p>🗑️ Se eliminaron <strong>$borrados</strong> archivos antiguos.</p>";

// 4. GENERACIÓN: Crear nuevos archivos limpios
$macs = [
    'AA-00-00-00-00-11',
    'AA-00-00-00-00-22',
    'AA-00-00-00-00-33',
    'AA-00-00-00-00-44',
    'AA-00-00-00-00-55'
];

$creados = 0;
$fecha_hoy = new DateTime();

foreach ($macs as $mac) {
    // Nombre exacto que espera el sistema
    $filename = "MAC=" . $mac . "_Registro_normal_de_operacion.txt";
    $filepath = $dir . '/' . $filename;
    $content = "";

    // Generar 60 días de datos hacia atrás
    for ($i = 60; $i >= 0; $i--) {
        $fecha = clone $fecha_hoy;
        $fecha->modify("-$i days");
        $fecha_str = $fecha->format('d-m-Y');

        // Datos aleatorios realistas
        $temp = rand(80, 105) + (rand(0, 99) / 100);
        $pres = rand(2, 8) + (rand(0, 99) / 100);
        $volt = rand(22, 28) + (rand(0, 99) / 100);

        // Formato estricto
        $content .= "[$fecha_str]\n";
        $content .= "Alarmas establecidas:\n";
        $content .= "Temperatura Crítica = 100.00\n";
        $content .= "Presión Mínima = 1.50\n";
        $content .= "Voltaje Máximo = 30.00\n";
        $content .= "Voltaje Mínimo = 20.00\n";
        $content .= "Máxima temperatura alcanzada: " . number_format($temp, 2, '.', '') . "\n";
        $content .= "Máxima presión alcanzada: " . number_format($pres, 2, '.', '') . "\n";
        $content .= "Mínima presión alcanzada: 1.20\n";
        $content .= "Máximo voltaje alcanzado: " . number_format($volt, 2, '.', '') . "\n";
        $content .= "Mínimo voltaje alcanzado: 21.00\n";
        $content .= "----------------------------------------\n";
    }

    if (file_put_contents($filepath, $content)) {
        echo "<div style='color:green'>✅ Archivo creado: $filename</div>";
        $creados++;
    } else {
        echo "<div style='color:red'>❌ Error al crear: $filename</div>";
    }
}

echo "<hr><h3>🎉 Proceso completado. $creados archivos nuevos listos.</h3>";
echo "<a href='../analisis_operacional.php'><button style='padding:10px; font-size:16px; cursor:pointer;'>Ir a Análisis Operacional</button></a>";
?>