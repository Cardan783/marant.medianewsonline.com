<?php
// Configuración de la zona horaria (ajusta según tu ubicación si es necesario)
date_default_timezone_set('America/Caracas'); // Ejemplo: Bogotá/Lima/Quito

// Definir la ruta al directorio 'Archivos_SDCards'.
// Usamos __DIR__ para obtener la ruta de este script y '..' para subir un nivel,
// asumiendo que este script está en la carpeta '/php/' y 'Archivos_SDCards' está en la raíz.
$directorio_destino = __DIR__ . '/../Archivos_SDCards';
$nombre_archivo = 'Confirmaciones_Nube.txt';
$ruta_completa = $directorio_destino . '/' . $nombre_archivo;

// 1. Verificar si el directorio 'Archivos_SDCards' existe
if (!is_dir($directorio_destino)) {
    // Si no existe, intentamos crearlo con permisos 0755
    if (mkdir($directorio_destino, 0755, true)) {
        echo "Directorio 'Archivos_SDCards' creado correctamente.<br>";
    } else {
        die("Error: No se pudo crear el directorio 'Archivos_SDCards'. Verifique los permisos del servidor.");
    }
}

// 2. Verificar si el archivo .txt existe
if (!file_exists($ruta_completa)) {
    // El archivo no existe, lo creamos con un encabezado inicial
    $contenido_inicial = "REGISTRO DIARIO DE ARCHIVOS SUBIDOS - CONFIRMACIONES NUBE\n";
    $contenido_inicial .= "Fecha de creación del log: " . date('Y-m-d H:i:s') . "\n";
    $contenido_inicial .= "Ubicación: " . $ruta_completa . "\n";
    $contenido_inicial .= "------------------------------------------------------------------\n";
    
    if (file_put_contents($ruta_completa, $contenido_inicial) !== false) {
        echo "El archivo <strong>$nombre_archivo</strong> ha sido creado exitosamente en:<br> $ruta_completa";
    } else {
        echo "Error: No se pudo crear el archivo. Verifique los permisos de escritura en la carpeta.";
    }
} else {
    echo "El archivo <strong>$nombre_archivo</strong> ya existe en:<br> $ruta_completa";
}

// --- Función para registrar una nueva subida ---
// Puedes llamar a esta función desde tu script de carga de archivos.
function registrar_subida($nombre_archivo_subido) {
    // Definimos la ruta al log (asegurando que sea la misma que arriba)
    $archivo_log = __DIR__ . '/../Archivos_SDCards/Confirmaciones_Nube.txt';
    
    // Obtenemos la fecha actual
    $fecha = date('Y-m-d H:i:s');
    
    // Creamos la línea con formato: [Fecha] Mensaje
    $linea = "[$fecha] Archivo subido exitosamente: $nombre_archivo_subido" . PHP_EOL;
    
    // Escribimos al final del archivo (FILE_APPEND) usando bloqueo exclusivo (LOCK_EX) para evitar errores
    file_put_contents($archivo_log, $linea, FILE_APPEND | LOCK_EX);
}
?>