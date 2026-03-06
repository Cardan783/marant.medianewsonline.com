<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'conexion.php';

// --- Procesar formulario de agregar equipo ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mac_address_nuevo'])) {
    $mac = trim($_POST['mac_address_nuevo']);
    $nombre = trim($_POST['nombre_equipo_nuevo']);
    $codigo_validacion = trim($_POST['codigo_validacion_nuevo'] ?? '');
    $usuario_id = $_SESSION['user_id'];
    
    if (empty($mac) || empty($nombre) || empty($codigo_validacion)) {
        header('Location: configuracion.php?status=error&msg=' . urlencode('Todos los campos son obligatorios, incluido el código de registro.'));
        exit();
    }

    try {
        $conn->beginTransaction();

        // 0. VALIDACIÓN DE SEGURIDAD (Inventario)
        // Verificar que la MAC y el Código coincidan y estén disponibles
        $sql_check = "SELECT id FROM inventario_equipos WHERE mac_address = ? AND codigo_registro = ? AND estado = 'disponible'";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([$mac, $codigo_validacion]);
        
        if ($stmt_check->rowCount() == 0) {
            // Si no hay coincidencia, puede ser código incorrecto, mac incorrecta o ya usado
            header('Location: configuracion.php?status=error&msg=' . urlencode('Validación fallida: El código no corresponde a la MAC o el equipo ya ha sido activado.'));
            exit();
        }

        // 1. Insertar en la tabla equipos vinculado al usuario actual
        $sql_equipos = "INSERT INTO equipos (usuario_id, mac_address, nombre_equipo) VALUES (?, ?, ?)";
        $stmt_equipos = $conn->prepare($sql_equipos);
        $stmt_equipos->execute([$usuario_id, $mac, $nombre]);
        $equipo_id = $conn->lastInsertId();
        
        // 2. Obtener datos del usuario para registrar en desactivaciones
        $stmt_user = $conn->prepare("SELECT nombre, identificacion FROM usuarios WHERE id = ?");
        $stmt_user->execute([$usuario_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data && !empty($user_data['identificacion'])) {
            // Separar nombre y apellido (estimación simple)
            $parts = explode(' ', $user_data['nombre'], 2);
            $nombre_u = $parts[0];
            $apellido_u = isset($parts[1]) ? $parts[1] : '';

            // 3. Insertar en desactivaciones con flag = 1
            $sql_des = "INSERT INTO desactivaciones (nombre, apellido, identificacion, telefono, direccion, mac, flag) VALUES (?, ?, ?, '', '', ?, 1)";
            $stmt_des = $conn->prepare($sql_des);
            $stmt_des->execute([$nombre_u, $apellido_u, $user_data['identificacion'], $mac]);

            // 4. Insertar configuración de alarmas por defecto para el nuevo equipo
            // Valores por defecto: Temp=95, Advertencia=88, Presion=0.5, VMax=15, VMin=11.5
            $sql_alarmas = "INSERT INTO alarmas (equipo_id, Temperatura, Temp_advertencia, Presion, Voltaje_Max, Voltaje_Min, cambio_pendiente) VALUES (?, 95.0, 88.0, 0.5, 15.0, 11.5, 1)";
            $stmt_alarmas = $conn->prepare($sql_alarmas);
            $stmt_alarmas->execute([$equipo_id]);

            // 5. Insertar configuración por defecto en la tabla `config`
            $sql_config = "INSERT INTO config (
                equipo_id, over_run, alarma_car, alarma_sis, 
                nombre_BLE, pin_BLE, usuario, contraseña, 
                wifi_ssid, wifi_password, nombre_mDNS, 
                Notificacion_perdida_wifi, notificacion_solo_led, notificacion_solo_led_alarm_temp,
                funcion_1, funcion_2, funcion_3, funcion_4, funcion_5
            ) VALUES (?, 1, 0, 0, 'SAMPATV', '230201', 'SAMPATV', '12345', 'SAMPATV', 'SAMPATV23', 'Archivos', 1, 1, 0, 0, 0, 0, 0, 0)";
            $stmt_config = $conn->prepare($sql_config);
            $stmt_config->execute([$equipo_id]);
        } else {
            $conn->rollBack();
            header('Location: configuracion.php?status=error&msg=' . urlencode('El usuario actual no tiene una identificación configurada. No se puede registrar el equipo.'));
            exit();
        }

        // 6. Marcar el equipo como ACTIVADO en el inventario para que el código no se pueda reusar
        $sql_update_inv = "UPDATE inventario_equipos SET estado = 'activado' WHERE mac_address = ?";
        $stmt_update_inv = $conn->prepare($sql_update_inv);
        $stmt_update_inv->execute([$mac]);

        $conn->commit();
        header('Location: configuracion.php?status=equipo_success_swal&new_equipo=' . urlencode($nombre));
    } catch (PDOException $e) {
        $conn->rollBack();
        if ($e->getCode() == 23000) { // Código de error para duplicados
            header('Location: configuracion.php?status=error&msg=' . urlencode('Esa dirección MAC ya está registrada en el sistema.'));
        } else {
            header('Location: configuracion.php?status=error&msg=' . urlencode('Error al guardar el equipo: ' . $e->getMessage()));
        }
    }
    exit();
}

// 1. Obtener el equipo seleccionado y su configuración
$selected_equipo_id = isset($_GET['equipo_id']) ? (int)$_GET['equipo_id'] : null;
$is_equipo_selected = !is_null($selected_equipo_id);
$config = [];
$error_db = "";

if ($is_equipo_selected) {
    try {
        $sql = "SELECT * FROM config WHERE equipo_id = :equipo_id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':equipo_id', $selected_equipo_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($fila = $stmt->fetch()) {
            $config = $fila;
        } else {
            // Si no existe configuración, inicializamos con valores por defecto
            $sql_insert = "INSERT INTO config (
                equipo_id, over_run, alarma_car, alarma_sis, 
                nombre_BLE, pin_BLE, usuario, contraseña, 
                wifi_ssid, wifi_password, nombre_mDNS, 
                Notificacion_perdida_wifi, notificacion_solo_led, notificacion_solo_led_alarm_temp,
                funcion_1, funcion_2, funcion_3, funcion_4, funcion_5
            ) VALUES (
                :equipo_id, 1, 0, 0, 
                'SAMPATV', '230201', 'SAMPATV', '12345', 
                'SAMPATV', 'SAMPATV23', 'Archivos', 
                1, 1, 0,
                0, 0, 0, 0, 0
            )";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->execute([':equipo_id' => $selected_equipo_id]);
            
            // Volvemos a consultar para obtener los datos recién insertados
            $stmt->execute();
            $config = $stmt->fetch();
        }
    } catch (PDOException $e) {
        $error_db = "Error al leer la configuración: " . $e->getMessage();
    }
}

// La conexión a la BD se cierra al final del script, después de renderizar el HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Configuración</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        
        /* Variables CSS dinámicas para el tema */
        :root {
            --bs-primary: #0d6efd;
            --bs-primary-rgb: 13, 110, 253;
            --placeholder-color: #6c757d;
        }
        .btn-primary {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
        }
        .text-primary {
            color: var(--bs-primary) !important;
        }
        /* Forzar color del tema en etiquetas y textos importantes */
        .form-label, .form-check-label, .fw-bold {
            color: var(--bs-primary) !important;
            transition: color 0.3s ease;
        }
        body.dark-mode .form-label, body.dark-mode .form-check-label, body.dark-mode .fw-bold {
            color: var(--bs-primary) !important;
        }

        /* Estilo para Placeholders */
        ::placeholder {
            color: var(--placeholder-color) !important;
            opacity: 0.7;
        }
        
        .card { margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-header { background-color: var(--bs-primary); color: white; font-weight: bold; }

        /* Estilos de la Paleta de Colores */
        .color-circle {
            width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s; border: 2px solid rgba(0, 0, 0, 0.1); margin-right: 8px;
        }
        .color-circle:hover { transform: scale(1.1); }
        .color-circle.selected { box-shadow: 0 0 0 2px white, 0 0 0 4px var(--bs-primary); }

        /* Estilos para Modo Oscuro */
        body.dark-mode { background-color: #121212; color: #e0e0e0; }
        body.dark-mode .card { background-color: #1e1e1e; border-color: #333; }
        body.dark-mode .card-header { background-color: #333; border-bottom: 1px solid #444; }
        body.dark-mode .form-control { background-color: #2c2c2c; border-color: #444; color: #fff; }
        body.dark-mode .form-control:focus { background-color: #3a3a3a; border-color: var(--bs-primary); color: #fff; }
        body.dark-mode .modal-content { background-color: #1e1e1e; }
        body.dark-mode .modal-header { border-bottom-color: #333; }
        body.dark-mode .modal-footer { border-top-color: #333; }
        /* Bootstrap 5.3+ handles btn-close in dark mode with data-bs-theme, but we use a class, so a filter is a good fallback */
        body.dark-mode .btn-close { filter: invert(1) grayscale(100) brightness(200%); }
        
        /* Estilo Neón para Inputs (simulando botones) */
        .neon-input {
            border: 2px solid var(--bs-primary);
            text-align: center;
            font-weight: bold;
            background-color: transparent;
        }
        .neon-input:focus {
            box-shadow: 0 0 15px var(--bs-primary);
            border-color: var(--bs-primary);
            outline: none;
        }
        .neon-input-alt {
            /* Hereda el color primario para mantener consistencia con la paleta */
        }
        .neon-input-alt:focus {
            /* Hereda el foco primario */
        }
        
        /* Etiquetas compactas */
        .compact-label { font-size: 0.8rem; display: block; text-align: center; margin-bottom: 2px; opacity: 0.8; }
        .btn .spinner-border { margin-right: 0.5rem; }
        .toast-container {
            z-index: 1100; /* Asegura que esté por encima de otros elementos */
        }
        .bg-lime-green {
            background-color: #76c043 !important; /* Verde Lima de la paleta */
        }
        .text-lime-green {
            color: #76c043 !important;
        }
        @keyframes glowRed {
            0% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); border-color: #dc3545; }
            50% { box-shadow: 0 0 20px rgba(220, 53, 69, 0.8); border-color: #dc3545; }
            100% { box-shadow: 0 0 5px rgba(220, 53, 69, 0.5); border-color: #dc3545; }
        }
        .error-oscillation {
            animation: glowRed 1.0s infinite alternate;
        }
    </style>
</head>
<body>

    <?php $base_path = '../'; include 'sidebar.php'; ?>

    <div class="toast-container position-fixed top-0 end-0 p-3">
      <!-- Las notificaciones push (toasts) se añadirán aquí -->
    </div>

    <div class="container pt-5 pt-lg-4">
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-outline-secondary btn-sm" id="darkModeToggle">
                <i class="bi bi-moon-stars-fill"></i> Modo Oscuro
            </button>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>¡Error de Base de Datos!</strong> No se pudo cargar la configuración.<br>
                <small><?php echo htmlspecialchars($error_db); ?></small>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'success'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>¡Éxito!</strong> La configuración se ha guardado correctamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($_GET['status'] == 'error'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>¡Error!</strong> No se pudo guardar la configuración. <?php echo isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : ''; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($_GET['status'] == 'nochange'): ?>
                 <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>Información:</strong> No se enviaron datos nuevos para actualizar.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($_GET['status'] == 'reset_success'): ?>
                 <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>¡Restablecido!</strong> La configuración ha vuelto a los valores de fábrica.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($_GET['status'] == 'reset_fail'): ?>
                 <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>¡Error de Restablecimiento!</strong> La contraseña de sistema ingresada es incorrecta.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Selector de Equipo (MAC) -->
        <div class="card mb-4">
            <div class="card-body d-flex align-items-center">
                <label for="selectMacAddress" class="form-label fw-bold me-3 mb-0">Seleccionar Equipo:</label>
                <select id="selectMacAddress" class="form-select w-auto flex-grow-1">
                    <option value="" disabled selected>-- Seleccionar Unidad --</option>
                </select>
            </div>
        </div>

        <!-- Tarjeta de Personalización -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                    <span class="fw-bold me-3"><i class="bi bi-palette me-2"></i>Color de Tema:</span>
                    <div class="d-flex" id="colorPalette"></div>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <span class="fw-bold me-3"><i class="bi bi-input-cursor-text me-2"></i>Color Placeholder:</span>
                    <div class="d-flex" id="placeholderPalette"></div>
                </div>
            </div>
        </div>

        <form action="guardar_config.php" method="POST" onsubmit="return validateForm()">
            <!-- Campo oculto para identificar que se envió este formulario -->
            <input type="hidden" name="is_config_form" value="1">
            <!-- Campo oculto para el ID del equipo seleccionado -->
            <input type="hidden" name="equipo_id" value="<?php echo htmlspecialchars($selected_equipo_id ?? ''); ?>">

            <!-- Modal de Acceso y Conectividad -->
            <div class="modal fade" id="accesoModal" tabindex="-1" aria-labelledby="accesoModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-lg">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="accesoModalLabel"><i class="bi bi-wifi me-2"></i>Acceso y Conectividad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <!-- Contenido del formulario movido aquí -->
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" value="<?php echo htmlspecialchars($config['usuario'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="contraseña" class="form-label">Nueva Contraseña de Sistema</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="contraseña" name="contraseña" placeholder="Dejar en blanco para no cambiar">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('contraseña')"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="wifi_ssid" class="form-label">Nombre de WiFi (SSID)</label>
                        <input type="text" class="form-control" id="wifi_ssid" name="wifi_ssid" value="<?php echo htmlspecialchars($config['wifi_ssid'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="wifi_password" class="form-label">Nueva Contraseña de WiFi</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="wifi_password" name="wifi_password" placeholder="Dejar en blanco para no cambiar">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('wifi_password')"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="nombre_mDNS" class="form-label">Nombre mDNS</label>
                        <input type="text" class="form-control" id="nombre_mDNS" name="nombre_mDNS" value="<?php echo htmlspecialchars($config['nombre_mDNS'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="nombre_BLE" class="form-label">Nombre BLE</label>
                        <input type="text" class="form-control" id="nombre_BLE" name="nombre_BLE" maxlength="10" value="<?php echo htmlspecialchars($config['nombre_BLE'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="pin_BLE" class="form-label">PIN Personalizado</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="pin_BLE" name="pin_BLE" maxlength="6" placeholder="Dejar en blanco para no cambiar">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('pin_BLE')"><i class="bi bi-eye"></i></button>
                        </div>
                        <div id="pin_validation_msg" class="mt-1 fw-bold text-lime-green" style="display: none;"></div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger" onclick="resetModalFields()" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="row">
                <!-- Columna Izquierda: Parámetros, Funciones, Notificaciones -->
                <div class="col-lg-6 mb-4">
                    <div class="card">
                        <div class="card-header"><i class="bi bi-sliders me-2"></i>Parámetros del Sistema</div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="over_run" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="over_run" name="over_run" value="1" <?php if (!$is_equipo_selected) echo 'disabled'; ?> <?php echo ($config['over_run'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="over_run">Over Run</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="alarma_car" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="alarma_car" name="alarma_car" value="1" <?php if (!$is_equipo_selected) echo 'disabled'; ?> <?php echo ($config['alarma_car'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="alarma_car">Alarma Car</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input type="hidden" name="alarma_sis" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="alarma_sis" name="alarma_sis" value="1" <?php if (!$is_equipo_selected) echo 'disabled'; ?> <?php echo ($config['alarma_sis'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="alarma_sis">Alarma Sis</label>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><i class="bi bi-grid-3x3-gap me-2"></i>Funciones Auxiliares</div>
                        <div class="card-body" <?php if (!$is_equipo_selected) echo 'style="opacity: 0.5; pointer-events: none;"'; ?>>
                            <div class="row g-2 row-cols-3 row-cols-sm-5">
                                <?php for($i=1; $i<=5; $i++): ?>
                                <div class="col">
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="funcion_<?php echo $i; ?>" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="funcion_<?php echo $i; ?>" name="funcion_<?php echo $i; ?>" value="1" <?php echo ($config['funcion_'.$i] ?? '') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="funcion_<?php echo $i; ?>">F<?php echo $i; ?></label>
                                    </div>
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Botones de Acción -->
                <div class="col-lg-6 d-flex flex-column">
                    <div class="d-grid gap-3 mb-3">
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#accesoModal" <?php if (!$is_equipo_selected) echo 'disabled'; ?>>
                            <i class="bi bi-wifi me-2"></i>Acceso y Conectividad
                        </button>
                        <button type="button" id="btnConfigAlarmas" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#alarmasModal" <?php if (!$is_equipo_selected) echo 'disabled'; ?>>
                            <i class="bi bi-speedometer me-2"></i>Configurar Alarmas
                        </button>
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#nuevoEquipoModal">
                            <i class="bi bi-hdd-stack me-2"></i>Ingresar Equipo Nuevo
                        </button>
                        <button type="submit" id="saveButton" class="btn btn-success btn-lg" <?php if (!$is_equipo_selected) echo 'disabled'; ?>>
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <span class="button-text">Guardar Configuración</span>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-lg" onclick="confirmReset()">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Restablecer Fábrica
                        </button>
                    </div>
                    <div class="card">
                        <div class="card-header"><i class="bi bi-bell me-2"></i>Notificaciones</div>
                        <div class="card-body">
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="notificacion_solo_led" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="notificacion_solo_led" name="notificacion_solo_led" value="1" <?php if (!$is_equipo_selected) echo 'disabled'; ?> <?php echo ($config['notificacion_solo_led'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notificacion_solo_led">Notificación Solo LED</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="notificacion_solo_led_alarm_temp" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="notificacion_solo_led_alarm_temp" name="notificacion_solo_led_alarm_temp" value="1" <?php if (!$is_equipo_selected) echo 'disabled'; ?> <?php echo ($config['notificacion_solo_led_alarm_temp'] ?? '') == '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notificacion_solo_led_alarm_temp">Notificación Solo LED Alarma Temp</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="Notificacion_perdida_wifi" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="Notificacion_perdida_wifi" name="Notificacion_perdida_wifi" value="1" <?php if (!$is_equipo_selected) echo 'disabled'; ?> <?php echo (($config['Notificacion_perdida_wifi'] ?? $config['notificacion_perdida_wifi'] ?? '') == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="Notificacion_perdida_wifi">Notificación Pérdida WiFi</label>
                            </div>
                            <hr>
                            <div class="form-check form-switch mb-3">
                                <!-- Este switch no envía datos al servidor, se maneja con JS y localStorage -->
                                <input class="form-check-input" type="checkbox" role="switch" id="showWelcomeMessageSwitch">
                                <label class="form-check-label" for="showWelcomeMessageSwitch">Mostrar bienvenida al iniciar sesión</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Modal de Configuración de Alarmas -->
    <form id="alarmasForm" action="Actualizar_alarmas.php" method="POST">
        <!-- Campo oculto para el ID del equipo -->
        <input type="hidden" name="equipo_id" id="equipo_id_alarmas" value="<?php echo htmlspecialchars($selected_equipo_id ?? ''); ?>">
        <div class="modal fade" id="alarmasModal" tabindex="-1" aria-labelledby="alarmasModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="alarmasModalLabel"><i class="bi bi-speedometer me-2"></i>Configuración de Alarmas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                    <label for="temperatura" class="form-label">Alarma Temperatura</label>
                    <input type="number" step="0.1" class="form-control" id="temperatura" name="temperatura" placeholder="Ingrese temperatura máxima">
                </div>
                <div class="mb-3">
                    <label for="temp_advertencia" class="form-label">Advertencia Temperatura</label>
                    <input type="number" step="0.1" class="form-control" id="temp_advertencia" name="temp_advertencia" placeholder="Ingrese advertencia temperatura">
                </div>
                <div class="mb-3">
                    <label for="presion" class="form-label">Alarma Presión de Aceite</label>
                    <input type="number" step="0.1" class="form-control" id="presion" name="presion" placeholder="Ingrese presión mínima">
                </div>
                <div class="mb-3">
                    <label for="voltaje_max" class="form-label">Alarma Voltaje Máximo</label>
                    <input type="number" step="0.1" class="form-control" id="voltaje_max" name="voltaje_max" placeholder="Ingrese voltaje máximo">
                </div>
                <div class="mb-3">
                    <label for="voltaje_min" class="form-label">Alarma Voltaje Mínimo</label>
                    <input type="number" step="0.1" class="form-control" id="voltaje_min" name="voltaje_min" placeholder="Ingrese voltaje mínimo">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Alarmas</button>
              </div>
            </div>
          </div>
        </div>
    </form>

    <!-- Modal de Nuevo Equipo -->
    <form id="nuevoEquipoForm" action="configuracion.php" method="POST">
        <div class="modal fade" id="nuevoEquipoModal" tabindex="-1" aria-labelledby="nuevoEquipoModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="nuevoEquipoModalLabel"><i class="bi bi-hdd-stack me-2"></i>Registrar Nuevo Equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                    <label for="nombre_equipo_nuevo" class="form-label">Nombre del Equipo (Alias)</label>
                    <input type="text" class="form-control" id="nombre_equipo_nuevo" name="nombre_equipo_nuevo" placeholder="Ej: Motor Principal" required>
                </div>
                <div class="mb-3">
                    <label for="mac_address_nuevo" class="form-label">Dirección MAC</label>
                    <input type="text" class="form-control" id="mac_address_nuevo" name="mac_address_nuevo" placeholder="Ej: A1:B2:C3:D4:E5:F6" required>
                </div>
                <div class="mb-3">
                    <label for="codigo_validacion_nuevo" class="form-label">Código de Registro (Manual de Usuario)</label>
                    <input type="text" class="form-control" id="codigo_validacion_nuevo" name="codigo_validacion_nuevo" placeholder="Ingrese el código único del equipo" required>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Registrar Equipo</button>
              </div>
            </div>
          </div>
        </div>
    </form>

    <form id="resetForm" action="guardar_config.php" method="POST">
        <input type="hidden" name="reset_factory" value="1">
        <input type="hidden" name="equipo_id" value="<?php echo htmlspecialchars($selected_equipo_id ?? ''); ?>">
        <input type="hidden" name="reset_password" id="reset_password_input">
    </form>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- Lógica de Modo Oscuro ---
        const toggleBtn = document.getElementById('darkModeToggle');
        
        // Función para aplicar tema
        const applyTheme = (isDark) => {
            if (isDark) {
                document.body.classList.add('dark-mode');
                toggleBtn.innerHTML = '<i class="bi bi-sun-fill"></i> Modo Claro';
            } else {
                document.body.classList.remove('dark-mode');
                toggleBtn.innerHTML = '<i class="bi bi-moon-stars-fill"></i> Modo Oscuro';
            }
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        };

        // Cargar preferencia guardada
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') applyTheme(true);

        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault(); // Evita que el botón haga submit si está dentro del form (aunque está fuera)
            applyTheme(!document.body.classList.contains('dark-mode'));
        });

        // --- Lógica de Paleta de Colores ---
        const colors = [
            { hex: "#0d6efd", rgb: "13, 110, 253" }, // Azul (Default)
            { hex: "#76c043", rgb: "118, 192, 67" }, // Verde Lima
            { hex: "#ffc107", rgb: "255, 193, 7" }, // Amarillo
            { hex: "#6f42c1", rgb: "111, 66, 193" }, // Morado
            { hex: "#fd7e14", rgb: "253, 126, 20" }, // Naranja
        ];
        const paletteContainer = document.getElementById('colorPalette');
        
        const setColor = (color) => {
            document.documentElement.style.setProperty('--bs-primary', color.hex);
            document.documentElement.style.setProperty('--bs-primary-rgb', color.rgb);
            localStorage.setItem('themeColor', JSON.stringify(color));
            
            // Actualizar UI de selección
            document.querySelectorAll('.color-circle').forEach(c => c.classList.remove('selected'));
            const selectedCircle = Array.from(document.querySelectorAll('.color-circle')).find(c => c.dataset.hex === color.hex);
            if (selectedCircle) selectedCircle.classList.add('selected');
        };

        // Renderizar círculos
        colors.forEach(color => {
            const circle = document.createElement('div');
            circle.className = 'color-circle';
            circle.style.backgroundColor = color.hex;
            circle.dataset.hex = color.hex;
            circle.onclick = () => setColor(color);
            paletteContainer.appendChild(circle);
        });

        // Cargar color guardado
        const savedColor = localStorage.getItem('themeColor');
        if (savedColor) {
            setColor(JSON.parse(savedColor));
        } else {
            setColor(colors[0]); // Default
        }

        // --- Lógica de Paleta de Placeholders ---
        const placeholderContainer = document.getElementById('placeholderPalette');
        const setPlaceholderColor = (color) => {
            document.documentElement.style.setProperty('--placeholder-color', color.hex);
            localStorage.setItem('placeholderColor', JSON.stringify(color));
            
            document.querySelectorAll('#placeholderPalette .color-circle').forEach(c => c.classList.remove('selected'));
            const selectedCircle = Array.from(document.querySelectorAll('#placeholderPalette .color-circle')).find(c => c.dataset.hex === color.hex);
            if (selectedCircle) selectedCircle.classList.add('selected');
        };

        colors.forEach(color => {
            const circle = document.createElement('div');
            circle.className = 'color-circle';
            circle.style.backgroundColor = color.hex;
            circle.dataset.hex = color.hex;
            circle.onclick = () => setPlaceholderColor(color);
            placeholderContainer.appendChild(circle);
        });

        const savedPlaceholder = localStorage.getItem('placeholderColor');
        if (savedPlaceholder) setPlaceholderColor(JSON.parse(savedPlaceholder));

        // --- Mostrar/Ocultar Contraseña ---
        window.togglePassword = (fieldId) => {
            const input = document.getElementById(fieldId);
            const icon = input.nextElementSibling.querySelector('i');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        };

        // --- Función para mostrar notificaciones Push (Toasts) ---
        function showToast(message, type = 'danger') {
            const toastContainer = document.querySelector('.toast-container');
            const toastId = 'toast-' + Date.now();

            const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                delay: 5000 // 5 segundos
            });

            toast.show();
            
            toastElement.addEventListener('hidden.bs.toast', () => {
                toastElement.remove();
            });
        }

        // --- Validación de Formulario ---
        function validateForm() {
            const ssidInput = document.getElementById('wifi_ssid');
            if (ssidInput.value.trim() === '') {
                showToast('El campo "Nombre de WiFi (SSID)" no puede estar vacío.');
                
                // Abrir modal y resaltar error
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('accesoModal'));
                modal.show();
                ssidInput.classList.add('error-oscillation');
                setTimeout(() => ssidInput.classList.remove('error-oscillation'), 3000);
                ssidInput.focus();
                return false; // Previene el envío del formulario
            }

            const pinInput = document.getElementById('pin_BLE');
            // Validar que contenga exactamente 6 dígitos si no está vacío
            if (pinInput.value.trim() !== '' && !/^\d{6}$/.test(pinInput.value)) {
                showToast('El campo "PIN Personalizado" debe contener exactamente 6 números.');
                
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('accesoModal'));
                modal.show();
                pinInput.classList.add('error-oscillation');
                setTimeout(() => pinInput.classList.remove('error-oscillation'), 3000);
                pinInput.focus();
                return false;
            }

            const mDNSInput = document.getElementById('nombre_mDNS');
            // Validar que no contenga espacios
            if (mDNSInput.value.includes(' ')) {
                showToast('El campo "Nombre mDNS" no puede contener espacios.');
                
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('accesoModal'));
                modal.show();
                mDNSInput.classList.add('error-oscillation');
                setTimeout(() => mDNSInput.classList.remove('error-oscillation'), 3000);
                mDNSInput.focus();
                return false;
            }

            // Si la validación es exitosa, mostrar estado de "Guardando..."
            const saveButton = document.getElementById('saveButton');
            const buttonText = saveButton.querySelector('.button-text');
            const spinner = saveButton.querySelector('.spinner-border');

            saveButton.disabled = true;
            buttonText.textContent = 'Guardando...';
            spinner.classList.remove('d-none');

            return true; // Permite el envío del formulario
        }

        function confirmReset() {
            const confirmation = confirm('¿Estás seguro de que deseas restablecer la configuración a los valores de fábrica?\n\nEsta acción borrará tus ajustes actuales y no se puede deshacer.');
            
            if (confirmation) {
                const password = prompt('Para confirmar, por favor ingresa la contraseña de sistema actual:');
                
                if (password !== null) { // Si el usuario no presiona "Cancelar"
                    document.getElementById('reset_password_input').value = password;
                    document.getElementById('resetForm').submit();
                }
            }
        }

        // --- Función para limpiar campos del modal ---
        function resetModalFields() {
            const modal = document.getElementById('accesoModal');
            const inputs = modal.querySelectorAll('input');
            inputs.forEach(input => {
                input.value = input.defaultValue;
                // Si es un campo de contraseña y está visible, ocultarlo de nuevo por seguridad y orden
                if ((input.id === 'contraseña' || input.id === 'wifi_password' || input.id === 'pin_BLE') && input.type === 'text') {
                    togglePassword(input.id);
                }
            });
        }

        // --- Auto-ocultar alerta de éxito ---
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            setTimeout(() => {
                const alertInstance = new bootstrap.Alert(successAlert);
                alertInstance.close();
            }, 3000); // 3000 milisegundos = 3 segundos
        }

        // --- Limpiar URL para evitar que el mensaje reaparezca al actualizar ---
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            if (url.searchParams.has('status')) {
                url.searchParams.delete('status');
                url.searchParams.delete('msg'); // También limpiamos mensajes de error si los hay
                url.searchParams.delete('new_equipo'); // Y el nombre del equipo nuevo
                window.history.replaceState(null, '', url.toString());
            }
        }

        // --- Validación en tiempo real para el PIN (solo números) ---
        const pinRealtimeInput = document.getElementById('pin_BLE');
        const pinMsg = document.getElementById('pin_validation_msg');
        let pinErrorTimeout;

        pinRealtimeInput.addEventListener('input', function(event) {
            const originalValue = event.target.value;
            // Reemplaza cualquier caracter que no sea un número con una cadena vacía
            const sanitizedValue = originalValue.replace(/[^0-9]/g, '');

            if (originalValue !== sanitizedValue) {
                // Mostrar mensaje debajo del input y activar oscilación roja
                pinMsg.textContent = 'Intente colocar solo números.';
                pinMsg.style.display = 'block';
                event.target.classList.add('error-oscillation');
                event.target.value = sanitizedValue;

                // Limpiar temporizador anterior y establecer uno nuevo para ocultar el aviso
                clearTimeout(pinErrorTimeout);
                pinErrorTimeout = setTimeout(() => {
                    pinMsg.style.display = 'none';
                    event.target.classList.remove('error-oscillation');
                }, 3000);
            }
        });

        // --- Lógica de Selección de Equipo ---
        const selectMac = document.getElementById('selectMacAddress');
        const btnConfigAlarmas = document.getElementById('btnConfigAlarmas');

        // Cargar equipos al iniciar
        fetch('obtener_equipos.php')
            .then(response => response.json())
            .then(data => {
                const urlParams = new URLSearchParams(window.location.search);
                const equipoIdFromUrl = urlParams.get('equipo_id');

                data.forEach(equipo => {
                    const option = document.createElement('option');
                    option.value = equipo.id;
                    option.textContent = equipo.nombre_equipo;
                    selectMac.appendChild(option);
                });

                if (equipoIdFromUrl) {
                    selectMac.value = equipoIdFromUrl;
                }
            })
            .catch(error => console.error('Error cargando equipos:', error));

        selectMac.addEventListener('change', function() {
            const selectedId = this.value;
            // Recargar la página con el ID del equipo seleccionado o sin él si se deselecciona
            window.location.href = selectedId ? `configuracion.php?equipo_id=${selectedId}` : 'configuracion.php';
        });

        // --- Validación para el formulario de alarmas (Voltaje Min < Voltaje Max) ---
        const alarmasForm = document.getElementById('alarmasForm');
        if (alarmasForm) {
            alarmasForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Prevenir el envío tradicional del formulario para usar AJAX

                const tempMaxInput = document.getElementById('temperatura');
                const tempAdvInput = document.getElementById('temp_advertencia');

                // Validar Temperatura Advertencia < Temperatura Crítica
                if (tempMaxInput.value.trim() !== '' && tempAdvInput.value.trim() !== '') {
                    const tempMax = parseFloat(tempMaxInput.value);
                    const tempAdv = parseFloat(tempAdvInput.value);

                    if (tempAdv >= tempMax) {
                        showToast('La Temperatura de Advertencia debe ser menor que la Temperatura Crítica.');

                        tempAdvInput.classList.add('error-oscillation');
                        tempMaxInput.classList.add('error-oscillation');

                        setTimeout(() => {
                            tempAdvInput.classList.remove('error-oscillation');
                            tempMaxInput.classList.remove('error-oscillation');
                        }, 3000);
                        return;
                    }
                }

                const voltajeMaxInput = document.getElementById('voltaje_max');
                const voltajeMinInput = document.getElementById('voltaje_min');

                // Solo validar si ambos campos tienen un valor
                if (voltajeMaxInput.value.trim() !== '' && voltajeMinInput.value.trim() !== '') {
                    const voltajeMax = parseFloat(voltajeMaxInput.value);
                    const voltajeMin = parseFloat(voltajeMinInput.value);

                    if (voltajeMin >= voltajeMax) {
                        showToast('El Voltaje Mínimo debe ser menor que el Voltaje Máximo.');

                        // Resaltar los campos con error
                        voltajeMinInput.classList.add('error-oscillation');
                        voltajeMaxInput.classList.add('error-oscillation');

                        setTimeout(() => {
                            voltajeMinInput.classList.remove('error-oscillation');
                            voltajeMaxInput.classList.remove('error-oscillation');
                        }, 3000);
                        return; // Detener ejecución si hay error
                    }
                }

                // Envío AJAX
                const formData = new FormData(alarmasForm);
                const submitBtn = alarmasForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Guardando...';

                fetch(alarmasForm.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Alarmas actualizadas correctamente.', 'success');

                        // Actualizar inputs con los valores devueltos por el servidor si existen
                        // Busca en propiedades comunes como data.data, data.values o directamente en la raíz
                        const responseData = data.data || data.values || data;
                        const fields = ['temperatura', 'temp_advertencia', 'presion', 'voltaje_max', 'voltaje_min'];
                        
                        fields.forEach(field => {
                            if (responseData[field] !== undefined) {
                                const input = document.getElementById(field);
                                if (input) input.value = responseData[field];
                            }
                        });

                        // Cerrar el modal si es necesario
                        const modalEl = document.getElementById('alarmasModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    } else {
                        // Mostrar error devuelto por el servidor o mensaje genérico
                        showToast('Error: ' + (data.error || 'No se pudo actualizar.'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Error de conexión o respuesta inválida.', 'danger');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            });
        }

        // --- Cargar datos de alarmas al abrir el modal (desde enviar_alarmas.php) ---
        const alarmasModalEl = document.getElementById('alarmasModal');
        if (alarmasModalEl) {
            alarmasModalEl.addEventListener('show.bs.modal', function () {
                const equipoId = document.getElementById('equipo_id_alarmas').value;
                if (!equipoId) return; // No debería pasar si el botón está deshabilitado

                fetch(`enviar_alarmas.php?equipo_id=${equipoId}`)
                    .then(response => response.text())
                    .then(data => {
                        // Se espera formato CSV: Temperatura,Temp_advertencia,Presion,Voltaje_Max,Voltaje_Min
                        const values = data.split(',');
                        if (values.length >= 5) {
                            document.getElementById('temperatura').value = values[0].trim();
                            document.getElementById('temp_advertencia').value = values[1].trim();
                            document.getElementById('presion').value = values[2].trim();
                            document.getElementById('voltaje_max').value = values[3].trim();
                            document.getElementById('voltaje_min').value = values[4].trim();
                        } else {
                            // Si no hay datos (nuevo equipo), limpiar o poner defaults
                            document.getElementById('temperatura').value = '';
                            document.getElementById('temp_advertencia').value = '';
                            document.getElementById('presion').value = '';
                            document.getElementById('voltaje_max').value = '';
                            document.getElementById('voltaje_min').value = '';
                        }
                    })
                    .catch(error => console.error('Error cargando alarmas:', error));
            });
        }

        // --- SweetAlert2 para Bienvenida de Equipo ---
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'equipo_success_swal') {
                const nombreEquipo = urlParams.get('new_equipo') || 'Nuevo Equipo';
                
                Swal.fire({
                    title: '¡Bienvenido a la Familia SAMPATV!',
                    text: `El equipo "${nombreEquipo}" ha sido validado y activado exitosamente.`,
                    icon: 'success',
                    confirmButtonText: 'Comenzar a Configurar',
                    confirmButtonColor: '#0d6efd',
                    backdrop: `
                        rgba(0,0,123,0.4)
                        url("https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExbXp1Z2J6aHl5bHl5bHl5bHl5bHl5bHl5bHl5bHl5bHl5bHl5/26tOZ42Mg6pbTUPDa/giphy.gif")
                        left top
                        no-repeat
                    `
                });
            }

            // --- Lógica para el switch de bienvenida ---
            const welcomeSwitch = document.getElementById('showWelcomeMessageSwitch');
            if (welcomeSwitch) {
                // 1. Establecer el estado inicial del switch desde localStorage
                welcomeSwitch.checked = (localStorage.getItem('showWelcomeMessage') === null || localStorage.getItem('showWelcomeMessage') === 'true');
                // 2. Guardar la preferencia cuando el switch cambie
                welcomeSwitch.addEventListener('change', function() {
                    localStorage.setItem('showWelcomeMessage', this.checked);
                    showToast(`Mensaje de bienvenida ${this.checked ? 'activado' : 'desactivado'}.`, 'info');
                });
            }
        });

        // --- Confirmación de Salida (Logout) ---
        function confirmLogout(event) {
            event.preventDefault(); // Evita que el enlace navegue inmediatamente
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
                    window.location.href = 'logout.php';
                }
            });
        }
    </script>
</body>
</html>
<?php
// La conexión PDO se cierra automáticamente al finalizar el script
?>