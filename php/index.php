<?php
session_start();

// Verificar sesión de ADMIN
if (!isset($_SESSION['admin_id']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

$nombre = $_SESSION['admin_nombre'];
$rol = $_SESSION['admin_rol'];
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
                <a href="#" class="nav-link"><i class="fa-solid fa-users-gear me-2"></i> Gestión Administradores</a>
            </li>
            <?php endif; ?>

            <li>
                <a href="#" class="nav-link"><i class="fa-solid fa-users me-2"></i> Clientes SAMPATV</a>
            </li>
            <li>
                <a href="#" class="nav-link"><i class="fa-solid fa-server me-2"></i> Equipos / Inventario</a>
            </li>
        </ul>
        <hr>
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

        <div class="row g-4 mt-2">
            <div class="col-md-4">
                <div class="card card-stat bg-primary text-white p-3">
                    <h3><i class="fa-solid fa-users"></i> Clientes</h3>
                    <p class="fs-5">Gestión de usuarios finales</p>
                </div>
            </div>
            <!-- Más tarjetas aquí -->
        </div>
    </div>
</div>
</body>
</html>