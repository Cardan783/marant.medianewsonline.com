<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Cabeceras para evitar que el navegador guarde la página en caché (Seguridad botón Atrás)
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Incluir conexión a la base de datos
require_once 'php/conexion.php';

$user_id = $_SESSION['user_id'];

// 1. Obtener todos los equipos del usuario
$stmt = $conn->prepare("SELECT id, nombre_equipo, mac_address FROM equipos WHERE usuario_id = ? ORDER BY nombre_equipo ASC");
$stmt->execute([$user_id]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Recorrer equipos para buscar sus últimos datos y configuración de alarmas
foreach ($equipos as &$equipo) {
    // Obtener última lectura de sensores
    $stmt_s = $conn->prepare("SELECT temperatura, presion, voltaje, created_at FROM sensores WHERE equipo_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_s->execute([$equipo['id']]);
    $equipo['datos'] = $stmt_s->fetch(PDO::FETCH_ASSOC);

    // Obtener umbrales de alarma
    $stmt_a = $conn->prepare("SELECT Temp_advertencia, Temperatura as Temp_critica FROM alarmas WHERE equipo_id = ? LIMIT 1");
    $stmt_a->execute([$equipo['id']]);
    $equipo['alarmas'] = $stmt_a->fetch(PDO::FETCH_ASSOC);
}
unset($equipo); // Romper referencia del foreach
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel General - Mis Equipos</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        

        /* Estilos de las Tarjetas */
        .equipment-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        .equipment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        .card-header {
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .data-row:last-child { border-bottom: none; }
        .data-value { font-weight: bold; font-size: 1.1rem; }
        .last-update { font-size: 0.8rem; color: #6c757d; text-align: right; margin-top: 10px; }

        /* Estados de Alarma */
        .status-normal { border-top: 5px solid #198754; }
        .status-warning { border-top: 5px solid #ffc107; }
        .status-danger { border-top: 5px solid #dc3545; }
        
        .bg-status-normal { background-color: #198754; color: white; }
        .bg-status-warning { background-color: #ffc107; color: black; }
        .bg-status-danger { background-color: #dc3545; color: white; }

        /* --- Estilos Modo Oscuro --- */
        body.dark-mode { background-color: #121212; color: #e0e0e0; }
        body.dark-mode .text-dark { color: #e0e0e0 !important; } /* Título */
        body.dark-mode .equipment-card { background-color: #1e1e1e; color: #e0e0e0; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        body.dark-mode .equipment-card:hover { background-color: #252525; box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        body.dark-mode .data-row { border-bottom-color: #333; }
        body.dark-mode .text-secondary { color: #a0a0a0 !important; }
        body.dark-mode .last-update { color: #888; }
        body.dark-mode .btn-outline-primary { color: #6ea8fe; border-color: #6ea8fe; }
        body.dark-mode .btn-outline-primary:hover { background-color: #6ea8fe; color: #000; }
        body.dark-mode .btn-outline-secondary { color: #adb5bd; border-color: #adb5bd; }
        body.dark-mode .btn-outline-secondary:hover { background-color: #adb5bd; color: #000; }
        body.dark-mode .card-header.bg-secondary { background-color: #495057 !important; }
    </style>
</head>
<body>

    <?php $base_path = ''; include 'php/sidebar.php'; include 'php/navbar.php'; ?>

    <div class="container pt-5 pt-lg-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark"><i class="bi bi-speedometer2 me-2"></i>Estado de la Flota</h2>
            <button onclick="location.reload()" class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise me-1"></i>Actualizar</button>
        </div>

        <?php if (count($equipos) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($equipos as $eq): 
                    // Determinar estado
                    $temp = $eq['datos']['temperatura'] ?? null;
                    $crit = $eq['alarmas']['Temp_critica'] ?? 90;
                    $adv = $eq['alarmas']['Temp_advertencia'] ?? 85;
                    
                    $statusClass = 'status-normal';
                    $headerClass = 'bg-status-normal';
                    $icon = 'bi-check-circle-fill';
                    $estadoTexto = 'Normal';

                    if ($temp !== null) {
                        if ($temp >= $crit) {
                            $statusClass = 'status-danger';
                            $headerClass = 'bg-status-danger';
                            $icon = 'bi-exclamation-octagon-fill';
                            $estadoTexto = 'Crítico';
                        } elseif ($temp >= $adv) {
                            $statusClass = 'status-warning';
                            $headerClass = 'bg-status-warning';
                            $icon = 'bi-exclamation-triangle-fill';
                            $estadoTexto = 'Alerta';
                        }
                    } else {
                        $statusClass = 'border-secondary'; // Sin datos
                        $headerClass = 'bg-secondary';
                        $icon = 'bi-question-circle-fill';
                        $estadoTexto = 'Sin Conexión';
                    }
                ?>
                <div class="col">
                    <div class="card equipment-card h-100 shadow-sm <?php echo $statusClass; ?>">
                        <div class="card-header <?php echo $headerClass; ?>">
                            <span><?php echo htmlspecialchars($eq['nombre_equipo']); ?></span>
                            <i class="bi <?php echo $icon; ?>"></i>
                        </div>
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-muted small"><i class="bi bi-cpu me-1"></i>MAC: <?php echo htmlspecialchars($eq['mac_address']); ?></h6>
                            
                            <div class="data-row">
                                <span class="text-secondary"><i class="fa-solid fa-temperature-half me-2"></i>Temperatura</span>
                                <span class="data-value"><?php echo $temp !== null ? number_format($temp, 1) . ' °C' : '--'; ?></span>
                            </div>
                            <div class="data-row">
                                <span class="text-secondary"><i class="fa-solid fa-gauge me-2"></i>Presión</span>
                                <span class="data-value"><?php echo isset($eq['datos']['presion']) ? number_format($eq['datos']['presion'], 1) . ' hPa' : '--'; ?></span>
                            </div>
                            <div class="data-row">
                                <span class="text-secondary"><i class="fa-solid fa-bolt me-2"></i>Voltaje</span>
                                <span class="data-value"><?php echo isset($eq['datos']['voltaje']) ? number_format($eq['datos']['voltaje'], 2) . ' V' : '--'; ?></span>
                            </div>

                            <div class="last-update">
                                <i class="bi bi-clock-history me-1"></i> <?php echo isset($eq['datos']['created_at']) ? date('d/m/Y H:i', strtotime($eq['datos']['created_at'])) : 'Sin registros'; ?>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <a href="Graficas.php?equipo_id=<?php echo $eq['id']; ?>" class="btn btn-outline-primary btn-sm">Ver Gráfica Detallada</a>
                                <a href="tabla.php?equipo_id=<?php echo $eq['id']; ?>" class="btn btn-outline-secondary btn-sm mt-2">Ver Historial de Datos</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h4><i class="bi bi-info-circle me-2"></i>No tienes equipos registrados.</h4>
                <p>Ve a la sección de <a href="php/configuracion.php">Configuración</a> para agregar tu primer equipo.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Auto-refrescar la página cada 60 segundos para mantener los datos actualizados
        setTimeout(function(){
           location.reload();
        }, 60000);
    </script>
</body>
</html>