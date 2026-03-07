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

require_once 'php/conexion.php';

// --- SEGURIDAD: Verificar si el usuario sigue activo ---
$stmt_status = $conn->prepare("SELECT estado FROM usuarios WHERE id = ?");
$stmt_status->execute([$_SESSION['user_id']]);
$status = $stmt_status->fetchColumn();

if ($status !== 'activo') {
    session_destroy();
    header("Location: index.php?status=login_error&msg=" . urlencode("Su cuenta ha sido suspendida. Contacte al administrador."));
    exit();
}

// Verificar si el perfil está completo y calcular progreso
$perfil_incompleto = false;
$progreso = 0;
$total_campos = 5; // Nombre, Apellido, Teléfono, Dirección, Foto
$campos_completos = 0;

try {
    $stmt = $conn->prepare("SELECT nombre, apellido, telefono, direccion, foto FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($user_data['nombre'])) $campos_completos++;
    if (!empty($user_data['apellido'])) $campos_completos++;
    if (!empty($user_data['telefono'])) $campos_completos++;
    if (!empty($user_data['direccion'])) $campos_completos++;
    if (!empty($user_data['foto']) && $user_data['foto'] !== 'default.png') $campos_completos++;

    $progreso = round(($campos_completos / $total_campos) * 100);

    if ($progreso < 100) {
        $perfil_incompleto = true;
    }
} catch (PDOException $e) { /* Ignorar error silenciosamente en dashboard */ }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - SAMPATV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand { font-weight: bold; }
        .dashboard-card {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            background: white;
            height: 100%;
            text-decoration: none;
            color: inherit;
            display: block;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            color: inherit;
        }
        .card-body {
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
        }
        .bg-blue-light { background-color: #e7f1ff; color: #0d6efd; }
        .bg-green-light { background-color: #d1e7dd; color: #198754; }
        .bg-yellow-light { background-color: #fff3cd; color: #ffc107; }
        .bg-purple-light { background-color: #e2d9f3; color: #6f42c1; }
        .bg-cyan-light { background-color: #cff4fc; color: #0dcaf0; }
        
        .card-title { font-weight: 700; margin-bottom: 0.5rem; font-size: 1.25rem; }
        .card-text { color: #6c757d; font-size: 0.95rem; }
        
        .welcome-banner {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 15px;
            margin-bottom: 2.5rem;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }
    </style>
</head>
<body>
    <?php $base_path = ''; include 'php/sidebar.php'; include 'php/navbar.php'; ?>

    <div class="container pb-5 pt-5 pt-lg-4">
        
        <!-- Barra de Progreso del Perfil -->
        <div id="profile-progress-card" class="alert <?php echo $progreso == 100 ? 'alert-success' : 'alert-warning'; ?> <?php if ($perfil_incompleto) echo 'alert-dismissible'; ?> fade show" role="alert" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0"><i class="fa-solid fa-user-check me-2"></i>Completado del Perfil</h5>
                <span class="fw-bold"><?php echo $progreso; ?>%</span>
            </div>
            <div class="progress" style="height: 20px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated <?php echo $progreso == 100 ? 'bg-success' : 'bg-warning'; ?>" role="progressbar" style="width: <?php echo $progreso; ?>%;" aria-valuenow="<?php echo $progreso; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <?php if ($perfil_incompleto): ?>
                <div class="mt-2 text-end">
                    <a href="php/perfil.php" class="alert-link small fw-bold">Faltan datos por completar <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php endif; ?>
        </div>

        <div class="welcome-banner d-flex justify-content-between align-items-center">
            <div>
                <h1 class="fw-bold mb-2">Bienvenido al Panel de Control</h1>
                <p class="mb-0 opacity-75 fs-5">Seleccione una herramienta para comenzar a gestionar su flota.</p>
            </div>
            <i class="fa-solid fa-satellite-dish fa-4x opacity-25 d-none d-md-block"></i>
        </div>

        <div class="row g-4">
            <!-- Panel General -->
            <div class="col-md-6 col-lg-4">
                <a href="panel_general.php" class="dashboard-card">
                    <div class="card-body">
                        <div class="icon-wrapper bg-blue-light"><i class="fa-solid fa-grip"></i></div>
                        <h4 class="card-title">Estado de la Flota</h4>
                        <p class="card-text">Vista general de todos sus equipos con indicadores de estado en tiempo real.</p>
                    </div>
                </a>
            </div>
            <!-- Gráficas -->
            <div class="col-md-6 col-lg-4">
                <a href="Graficas.php" class="dashboard-card">
                    <div class="card-body">
                        <div class="icon-wrapper bg-green-light"><i class="fa-solid fa-chart-area"></i></div>
                        <h4 class="card-title">Monitor Gráfico</h4>
                        <p class="card-text">Análisis detallado con gráficas interactivas de temperatura, presión y voltaje.</p>
                    </div>
                </a>
            </div>
            <!-- Tablas -->
            <div class="col-md-6 col-lg-4">
                <a href="tabla.php" class="dashboard-card">
                    <div class="card-body">
                        <div class="icon-wrapper bg-yellow-light"><i class="fa-solid fa-table"></i></div>
                        <h4 class="card-title">Historial de Datos</h4>
                        <p class="card-text">Consulte registros históricos, filtre por fechas y exporte a Excel/PDF.</p>
                    </div>
                </a>
            </div>
            <!-- Configuración -->
            <div class="col-md-6 col-lg-4">
                <a href="php/configuracion.php" class="dashboard-card">
                    <div class="card-body">
                        <div class="icon-wrapper bg-purple-light"><i class="fa-solid fa-gear"></i></div>
                        <h4 class="card-title">Configuración</h4>
                        <p class="card-text">Agregue nuevos equipos, configure WiFi, parámetros y notificaciones.</p>
                    </div>
                </a>
            </div>
             <!-- Soporte -->
             <div class="col-md-6 col-lg-4">
                <a href="#" class="dashboard-card" onclick="alert('Próximamente disponible')">
                    <div class="card-body">
                        <div class="icon-wrapper bg-light text-secondary"><i class="fa-solid fa-book"></i></div>
                        <h4 class="card-title">Ayuda y Soporte</h4>
                        <p class="card-text">Manuales de usuario, guías de instalación y contacto con soporte técnico.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const profileCard = document.getElementById('profile-progress-card');
            const progreso = <?php echo $progreso; ?>;

            if (profileCard) {
                if (progreso === 100) {
                    profileCard.style.display = 'block'; // Mostrar para luego desvanecer
                    // Si el perfil está 100% completo, ocultar la alerta después de 2 segundos.
                    setTimeout(() => {
                        const alertInstance = bootstrap.Alert.getOrCreateInstance(profileCard);
                        if (alertInstance) {
                            alertInstance.close();
                        }
                    }, 2000);
                } else {
                    // Si está incompleto, verificar si el usuario ya lo cerró en esta sesión.
                    if (sessionStorage.getItem('hideProfileReminder') !== 'true') {
                        profileCard.style.display = 'block';
                    }
                    // Guardar en sessionStorage cuando el usuario cierre la alerta manualmente.
                    profileCard.addEventListener('close.bs.alert', function () {
                        sessionStorage.setItem('hideProfileReminder', 'true');
                    });
                }
            }
        });
    </script>
</body>
</html>