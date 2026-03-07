<?php
session_start(); // 1. Iniciar sesión
header('Content-Type: application/json');

// Obtener la MAC enviada por parámetro
$mac = isset($_GET['mac']) ? trim($_GET['mac']) : '';
$modo = isset($_GET['modo']) ? $_GET['modo'] : 'mes'; // 'mes' (default) o 'general'

if (empty($mac)) {
    echo json_encode(["error" => "MAC no proporcionada"]);
    exit;
}

// 2. SEGURIDAD: Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Acceso no autorizado. Inicie sesión."]);
    exit;
}

// 3. SEGURIDAD: Verificar que la MAC pertenezca al usuario logueado
require_once 'conexion.php'; // Asegúrate de tener este archivo accesible
$stmt_check = $conn->prepare("SELECT id FROM equipos WHERE mac_address = ? AND usuario_id = ?");
$stmt_check->execute([$mac, $_SESSION['user_id']]);

if ($stmt_check->rowCount() === 0) {
    echo json_encode(["error" => "Acceso denegado. Este equipo no le pertenece."]);
    exit;
}

// Directorio donde están los archivos (Mismo que en listar_archivos)
$dir = __DIR__ . '/../Archivos_SDCards/uploads';

// Adaptar formato MAC para el nombre del archivo (Reemplazar ':' por '-')
$mac_archivo = str_replace(':', '-', $mac);
$prefix = "MAC=" . $mac_archivo;
$suffix = "_Registro_normal_de_operacion.txt";

$archivo_encontrado = null;

// Buscar el archivo específico
if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        // Buscamos un archivo que empiece con la MAC y contenga el nombre específico
        // Esto maneja casos como MAC=XX-XX..._Registro_normal_de_operacion.txt
        if (strpos($file, $prefix) === 0 && strpos($file, $suffix) !== false) {
            $archivo_encontrado = $dir . '/' . $file;
            break;
        }
    }
}

if (!$archivo_encontrado || !file_exists($archivo_encontrado)) {
    echo json_encode(["error" => "No se encontró el archivo _Registro_normal_de_operacion.txt para esta MAC"]);
    exit;
}

// Leer contenido
$contenido = file_get_contents($archivo_encontrado);

// Separar por bloques de guiones
$bloques = explode('----------------------------------------', $contenido);
$datos = [];
$mes_actual = date('m');
$anio_actual = date('Y');

// Variables para análisis y recomendaciones
$alertas_temp = 0;
$alertas_presion = 0;
$alertas_voltaje_alto = 0;
$alertas_voltaje_bajo = 0;
$suma_voltaje_promedio = 0;
$conteo_voltaje = 0;

foreach ($bloques as $bloque) {
    $bloque = trim($bloque);
    if (empty($bloque)) continue;

    $item = [];

    // Extraer Fecha [DD-MM-YYYY]
    if (preg_match('/\[(\d{2}-\d{2}-\d{4})\]/', $bloque, $matches)) {
        $fecha_str = $matches[1];
        $fecha_dt = DateTime::createFromFormat('d-m-Y', $fecha_str);
        
        if (!$fecha_dt) continue;
        $fecha_dt->setTime(0, 0, 0); // Normalizar hora

        if ($modo === 'general') {
            // Filtrar: Últimos 2 meses (Mes actual y el anterior)
            $hoy = new DateTime();
            $inicio_rango = (clone $hoy)->modify('first day of last month')->setTime(0,0,0);
            $fin_rango = (clone $hoy)->modify('last day of this month')->setTime(23,59,59);

            if ($fecha_dt < $inicio_rango || $fecha_dt > $fin_rango) {
                continue;
            }
        } else {
            // Filtrar: Solo mes actual
            if ($fecha_dt->format('m') !== $mes_actual || $fecha_dt->format('Y') !== $anio_actual) {
                continue;
            }
        }
        $item['fecha'] = $fecha_str;
    } else {
        continue; // Si no hay fecha, saltamos
    }

    // --- Extraer Alarmas del Día (Nuevo Formato) ---
    if (preg_match('/Temperatura Cr[ií]tica\s*=\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['alarm_temp'] = floatval($matches[1]);
    }
    if (preg_match('/Presi[oó]n M[ií]nima\s*=\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['alarm_pres_min'] = floatval($matches[1]);
    }
    if (preg_match('/Voltaje M[aá]ximo\s*=\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['alarm_volt_max'] = floatval($matches[1]);
    }
    if (preg_match('/Voltaje M[ií]nimo\s*=\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['alarm_volt_min'] = floatval($matches[1]);
    }

    // Extraer Temperatura Max
    if (preg_match('/M[aá]xima temperatura alcanzada:\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['temp_max'] = floatval($matches[1]);
        // Análisis: Usar alarma del archivo o default 95
        $umbral = isset($item['alarm_temp']) ? $item['alarm_temp'] : 95;
        if ($item['temp_max'] > $umbral) $alertas_temp++;
    }

    // Extraer Presion Max y Min
    if (preg_match('/M[aá]xima presi[oó]n alcanzada:\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['pres_max'] = floatval($matches[1]);
    }
    if (preg_match('/M[ií]nima presi[oó]n alcanzada:\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['pres_min'] = floatval($matches[1]);
        // Análisis: Presión muy baja (ej. < 2 PSI/Bar mientras opera es peligroso, pero 0.00 puede ser apagado)
        // Asumimos que si hay registro de operación, la presión no debería ser crítica constantemente.
    }

    // Extraer Voltaje Max y Min
    if (preg_match('/M[aá]ximo voltaje alcanzado:\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['volt_max'] = floatval($matches[1]);
        $suma_voltaje_promedio += $item['volt_max'];
        $conteo_voltaje++;
    }
    if (preg_match('/M[ií]nimo voltaje alcanzado:\s*([\d\.]+)/u', $bloque, $matches)) {
        $item['volt_min'] = floatval($matches[1]);
    }

    $datos[] = $item;
}

// --- GENERACIÓN DE RECOMENDACIONES ---
$recomendaciones = [];

// 1. Determinar sistema (12V o 24V) basado en promedio
$sistema_24v = false;
if ($conteo_voltaje > 0) {
    $promedio_v = $suma_voltaje_promedio / $conteo_voltaje;
    if ($promedio_v > 18) $sistema_24v = true;
}

// 2. Analizar Voltajes según el sistema detectado
foreach ($datos as $d) {
    // Usar alarmas del archivo si existen, sino usar lógica detectada
    $limite_max = isset($d['alarm_volt_max']) ? $d['alarm_volt_max'] : ($sistema_24v ? 30.0 : 15.0);
    $limite_min = isset($d['alarm_volt_min']) ? $d['alarm_volt_min'] : ($sistema_24v ? 23.0 : 11.5);

    if (isset($d['volt_max']) && $d['volt_max'] > $limite_max) $alertas_voltaje_alto++;
    if (isset($d['volt_min']) && $d['volt_min'] < $limite_min) $alertas_voltaje_bajo++;
}

// Texto dinámico para las recomendaciones
$txt_periodo = ($modo === 'general') ? "en los últimos dos meses" : "este mes";

// 3. Redactar mensajes
if ($alertas_temp > 0) {
    $recomendaciones[] = ["tipo" => "danger", "icono" => "fa-temperature-arrow-up", "titulo" => "Sobrecalentamiento Detectado", "mensaje" => "Se detectaron $alertas_temp días con temperatura superior a 95°C $txt_periodo. Revise urgentemente el sistema de refrigeración, nivel de refrigerante y estado del radiador."];
}

if ($alertas_voltaje_alto > 0) {
    $recomendaciones[] = ["tipo" => "warning", "icono" => "fa-bolt", "titulo" => "Sobretensión en Generador", "mensaje" => "Se registraron $alertas_voltaje_alto eventos de voltaje alto. Posible fallo en el regulador de voltaje del alternador."];
}

if ($alertas_voltaje_bajo > 0) {
    $recomendaciones[] = ["tipo" => "warning", "icono" => "fa-car-battery", "titulo" => "Batería o Carga Baja", "mensaje" => "Se registraron $alertas_voltaje_bajo eventos de voltaje bajo. Verifique el estado de las baterías y la capacidad de carga del alternador."];
}

if (empty($recomendaciones) && count($datos) > 0) {
    $recomendaciones[] = ["tipo" => "success", "icono" => "fa-check-circle", "titulo" => "Operación Normal", "mensaje" => "El funcionamiento del motor y sistema eléctrico parece óptimo $txt_periodo."];
} else if (count($datos) == 0) {
    $recomendaciones[] = ["tipo" => "info", "icono" => "fa-info-circle", "titulo" => "Sin Datos", "mensaje" => "No se encontraron registros para el periodo seleccionado."];
}

echo json_encode(['datos' => $datos, 'recomendaciones' => $recomendaciones]);
?>