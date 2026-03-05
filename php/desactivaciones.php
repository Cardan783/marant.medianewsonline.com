<?php
require_once 'conexion.php';

$cliente = null;
$clientes_macs = []; // Array para guardar todas las MACs encontradas
$mensaje_busqueda = '';

// Lógica de búsqueda
if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $busqueda = $_GET['busqueda'];
    
    // 1. Buscar el usuario en la tabla USUARIOS
    $sql = "SELECT * FROM usuarios WHERE identificacion = ? LIMIT 1";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([$busqueda]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            // Mapeamos los datos del usuario para la vista
            $cliente = $usuario;
            
            // 2. Buscar los equipos asociados en la tabla EQUIPOS
            $sql_equipos = "SELECT mac_address as mac, nombre_equipo FROM equipos WHERE usuario_id = ?";
            $stmt_eq = $conn->prepare($sql_equipos);
            $stmt_eq->execute([$usuario['id']]);
            $equipos_encontrados = $stmt_eq->fetchAll(PDO::FETCH_ASSOC);

            // 3. Para cada equipo, verificar su estado en la tabla DESACTIVACIONES
            foreach ($equipos_encontrados as $eq) {
                $stmt_flag = $conn->prepare("SELECT flag FROM desactivaciones WHERE mac = ? LIMIT 1");
                $stmt_flag->execute([$eq['mac']]);
                $flag_row = $stmt_flag->fetch(PDO::FETCH_ASSOC);
                
                // Si existe en desactivaciones usamos su flag, sino asumimos 1 (Activo)
                $eq['flag'] = $flag_row ? $flag_row['flag'] : '1';
                $clientes_macs[] = $eq;
            }
        } else {
            $mensaje_busqueda = "No se encontró ningún usuario con esa identificación.";
        }
    } catch (PDOException $e) {
        $mensaje_busqueda = "Error en la consulta: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desactivaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        :root { --bs-primary: #0d6efd; }
        .card { box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-header { background-color: var(--bs-primary); color: white; font-weight: bold; }
        .form-label { color: var(--bs-primary); font-weight: 500; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">Control de Desactivaciones</span>
            <div>
                <a href="nuevo_cliente.php" class="btn btn-outline-light btn-sm">Ir a Nuevo Cliente</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($_GET['status']) && $_GET['status'] == 'updated'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>¡Actualizado!</strong> El estado del cliente se ha modificado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Buscador -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="desactivaciones.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="busqueda" class="form-label">Buscar Cliente</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Ingrese Cédula, Pasaporte o DNI" value="<?php echo htmlspecialchars($_GET['busqueda'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-2"></i>Buscar</button>
                    </div>
                    <div class="col-md-2">
                        <a href="desactivaciones.php" class="btn btn-secondary w-100"><i class="bi bi-x-circle me-2"></i>Limpiar</a>
                    </div>
                </form>
                <?php if ($mensaje_busqueda): ?>
                    <div class="alert alert-warning mt-3 mb-0"><?php echo $mensaje_busqueda; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($cliente): ?>
        <!-- Formulario de Edición (Solo Flag) -->
        <div class="card">
            <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Datos del Cliente</div>
            <div class="card-body">
                <form action="ingreso_nuevo_cliente.php" method="POST">
                    <input type="hidden" name="accion" value="actualizar">
                    <!-- Identificación oculta para el WHERE del update -->
                    <input type="hidden" name="identificacion" value="<?php echo htmlspecialchars($cliente['identificacion']); ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" readonly disabled>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Identificación</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($cliente['identificacion']); ?>" readonly disabled>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Dirección MAC</label>
                            <!-- Selector de MACs asociadas -->
                            <select class="form-select" name="mac" id="mac_selector" onchange="actualizarEstadoFlag()">
                                <option value="" disabled selected>-- Seleccione un equipo --</option>
                                <?php foreach ($clientes_macs as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c['mac']); ?>" data-flag="<?php echo htmlspecialchars($c['flag']); ?>">
                                        <?php echo htmlspecialchars($c['mac']) . " - " . htmlspecialchars($c['nombre_equipo'] ?? 'Sin Nombre'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label for="flag" class="form-label fw-bold">Estado del Servicio (Flag)</label>
                        <select class="form-select form-select-lg" id="flag" name="flag">
                            <option value="1">Activo</option>
                            <option value="0">Desactivo</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-arrow-repeat me-2"></i>Actualizar Datos
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para actualizar el select de Estado según la MAC seleccionada
        function actualizarEstadoFlag() {
            const macSelect = document.getElementById('mac_selector');
            const flagSelect = document.getElementById('flag');
            
            // Obtener el flag del atributo data-flag de la opción seleccionada
            const selectedOption = macSelect.options[macSelect.selectedIndex];
            const flagValue = selectedOption.getAttribute('data-flag');
            
            // Actualizar el valor del select de estado
            flagSelect.value = flagValue;
        }

        // Auto-ocultar alertas después de 2 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            const urlParams = new URLSearchParams(window.location.search);
            const isUpdated = urlParams.get('status') === 'updated';

            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();

                    // Si se actualizó correctamente, limpiar la pantalla (redirigir al inicio)
                    if (isUpdated) {
                        window.location.href = 'desactivaciones.php';
                    }
                }, 2000);
            });

            // Inicializar el estado correcto al cargar la página
            if(document.getElementById('mac_selector')) {
                actualizarEstadoFlag();
            }
        });
    </script>
</body>
</html>
<?php
$conn = null;
?>