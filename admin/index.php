<?php
session_start();
require_once '../php/conexion.php';

// Verificar sesión de ADMIN
if (!isset($_SESSION['admin_id']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

$nombre = $_SESSION['admin_nombre'];
$rol = $_SESSION['admin_rol'];
$mensaje_perfil = '';

// --- Lógica: Actualizar Perfil Propio ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_user = trim($_POST['usuario']);
    $new_pass = $_POST['password'];
    $id = $_SESSION['admin_id'];
    
    try {
        if (!empty($new_pass)) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE administrador SET usuario = ?, password = ? WHERE id = ?");
            $stmt->execute([$new_user, $hash, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE administrador SET usuario = ? WHERE id = ?");
            $stmt->execute([$new_user, $id]);
        }
        $mensaje_perfil = "Perfil actualizado correctamente.";
        $_SESSION['admin_nombre'] = $new_user; // Actualizar sesión
        $nombre = $new_user;
    } catch (PDOException $e) {
        $mensaje_perfil = "Error al actualizar: " . $e->getMessage();
    }
}

// --- Lógica: Estadísticas para Dashboard ---
try {
    // Total Clientes
    $total_clientes = $conn->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    
    // Equipos Activos (flag = 1)
    $equipos_activos = $conn->query("SELECT COUNT(*) FROM desactivaciones WHERE flag = 1")->fetchColumn();
    
    // Equipos Inactivos (flag = 0)
    $equipos_inactivos = $conn->query("SELECT COUNT(*) FROM desactivaciones WHERE flag = 0")->fetchColumn();
    
    $total_equipos = $equipos_activos + $equipos_inactivos;

} catch (PDOException $e) {
    $total_clientes = 0; $equipos_activos = 0; $equipos_inactivos = 0;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SAMPATV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #212529; }
        .nav-link { color: rgba(255,255,255,.75); margin-bottom: 5px; }
        .nav-link:hover, .nav-link.active { color: #fff; background-color: rgba(255,255,255,.1); border-radius: 5px; }
        .card-stat { border: none; border-radius: 10px; transition: transform 0.3s; }
        .card-stat:hover { transform: translateY(-5px); }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <div class="d-flex flex-column flex-shrink-0 p-3 text-white sidebar" style="width: 260px;">
        <a href="#" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none">
            <i class="fa-solid fa-shield-halved fs-4 me-2"></i>
            <span class="fs-4 fw-bold">SAMPATV Admin</span>
        </a>
        <hr>
        <!-- Botón Editar Perfil -->
        <div class="mb-3">
            <button type="button" class="btn btn-outline-light w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#perfilModal">
                <i class="fa-solid fa-user-pen me-2"></i> Editar Mi Perfil
            </button>
        </div>
        <div class="mb-3 px-2">
            <small class="text-muted text-uppercase" style="font-size: 0.75rem;">Usuario</small>
            <div class="d-flex align-items-center mt-1">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                    <?php echo strtoupper(substr($nombre, 0, 1)); ?>
                </div>
                <div style="line-height: 1.2;">
                    <strong><?php echo htmlspecialchars($nombre); ?></strong><br>
                    <span class="badge <?php echo $rol === 'superadmin' ? 'bg-danger' : 'bg-info'; ?>"><?php echo ucfirst($rol); ?></span>
                </div>
            </div>
        </div>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="index.php" class="nav-link active" aria-current="page"><i class="fa-solid fa-gauge me-2"></i> Dashboard</a>
            </li>
            
            <!-- Solo visible para SUPERADMIN -->
            <?php if($rol === 'superadmin'): ?>
            <li>
                <a href="gestion_admins.php" class="nav-link"><i class="fa-solid fa-users-gear me-2"></i> Gestión Administradores</a>
            </li>
            <?php endif; ?>

            <li>
                <a href="reporte_usuarios.php" class="nav-link"><i class="fa-solid fa-users me-2"></i> Reporte Usuarios</a>
            </li>
            <li>
                <a href="gestion_equipos.php" class="nav-link"><i class="fa-solid fa-server me-2"></i> Equipos / Inventario</a>
            </li>
        </ul>
        <hr>
        <div class="mb-2">
            <a href="../index.php" class="d-flex align-items-center text-white text-decoration-none p-2 bg-secondary rounded justify-content-center" target="_blank">
                <i class="fa-solid fa-globe me-2"></i> Ver Sitio Web
            </a>
        </div>
        <div class="dropdown">
            <a href="logout.php" class="d-flex align-items-center text-white text-decoration-none p-2 bg-danger rounded justify-content-center">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4 bg-light">
        <h2>Bienvenido al Panel de Control</h2>
        <p class="text-muted">Resumen general del sistema.</p>

        <?php if($mensaje_perfil): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <?php echo $mensaje_perfil; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mt-2">
            <!-- Tarjeta Clientes -->
            <div class="col-md-4">
                <div class="card card-stat bg-primary text-white p-3">
                    <h3><i class="fa-solid fa-users"></i> <?php echo $total_clientes; ?></h3>
                    <p class="fs-5">Gestión de usuarios finales</p>
                </div>
            </div>
            
            <!-- Tarjeta Equipos Activos -->
            <div class="col-md-4">
                <div class="card card-stat bg-success text-white p-3">
                    <h3><i class="fa-solid fa-wifi"></i> <?php echo $equipos_activos; ?></h3>
                    <p class="fs-5">Equipos Activos</p>
                </div>
            </div>

            <!-- Tarjeta Equipos Inactivos -->
            <div class="col-md-4">
                <div class="card card-stat bg-danger text-white p-3">
                    <h3><i class="fa-solid fa-ban"></i> <?php echo $equipos_inactivos; ?></h3>
                    <p class="fs-5">Equipos Suspendidos</p>
                </div>
            </div>
        </div>

        <!-- Gráficas -->
        <div class="row mt-5">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">Estado de Equipos</div>
                    <div class="card-body">
                        <canvas id="equiposChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Perfil -->
<div class="modal fade" id="perfilModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Mi Perfil</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="update_profile" value="1">
            <div class="mb-3">
                <label class="form-label">Usuario</label>
                <input type="text" name="usuario" class="form-control" value="<?php echo htmlspecialchars($nombre); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Nueva Contraseña</label>
                <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Configuración de la Gráfica
    const ctx = document.getElementById('equiposChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Activos', 'Inactivos'],
            datasets: [{
                data: [<?php echo $equipos_activos; ?>, <?php echo $equipos_inactivos; ?>],
                backgroundColor: ['#198754', '#dc3545'],
                hoverOffset: 4
            }]
        }
    });
</script>
</body>
</html>