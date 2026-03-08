<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top d-lg-none shadow">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?php echo $base_path; ?>panel_control.php">
            <i class="fa-solid fa-chart-line me-2"></i>SAMPATV
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNavbar" aria-controls="mobileNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mobileNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>panel_control.php"><i class="fa-solid fa-house me-2"></i>Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>panel_general.php"><i class="fa-solid fa-grip me-2"></i>Estado Flota</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>Graficas.php"><i class="fa-solid fa-chart-area me-2"></i>Gráficas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>tabla.php"><i class="fa-solid fa-table me-2"></i>Historial</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>analisis_operacional.php"><i class="fa-solid fa-sd-card me-2"></i>Análisis diario de operaciòn</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_path; ?>php/configuracion.php"><i class="fa-solid fa-gear me-2"></i>Configuración</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link darkModeToggleBtn" href="#"><i class="fa-solid fa-moon me-2"></i>Modo Oscuro</a>
                </li>
            </ul>
            <div class="d-flex align-items-center border-top pt-3 mt-2 pb-2">
                <div class="text-white me-3 ms-2">
                    <i class="fa-solid fa-user-circle fa-2x"></i>
                </div>
                <div>
                    <div class="text-white fw-bold mb-1"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Usuario'; ?></div>
                    <a href="<?php echo $base_path; ?>logout.php" onclick="confirmLogout(event)" class="btn btn-sm btn-danger"><i class="fa-solid fa-right-from-bracket me-1"></i>Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<!-- Espaciador para evitar que el contenido quede oculto detrás del navbar fijo -->
<div class="d-lg-none" style="height: 70px;"></div>

<script>
if (typeof confirmLogout !== 'function') {
    function confirmLogout(event) {
        event.preventDefault();
        var url = event.currentTarget.getAttribute('href');
        Swal.fire({
            title: '¿Cerrar Sesión?',
            text: "¿Estás seguro de que deseas salir del sistema?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, salir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
}
</script>