<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Cabeceras para evitar que el navegador guarde la página en caché (Seguridad botón Atrás)
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Incluir conexión a la base de datos
require_once 'conexion.php';

// Generar Token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$mensaje = '';
$tipo_alerta = '';

// ---------------------------------------------------------
// 1. OBTENER LISTA DE EQUIPOS (Para el selector)
// ---------------------------------------------------------
$lista_equipos = [];
try {
    $stmt = $conn->prepare("SELECT id, nombre_equipo, mac_address FROM equipos WHERE usuario_id = ? ORDER BY nombre_equipo ASC");
    $stmt->execute([$user_id]);
    $lista_equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar la lista de equipos: " . $e->getMessage();
    $tipo_alerta = "danger";
}

// ---------------------------------------------------------
// 2. DETERMINAR EQUIPO SELECCIONADO
// ---------------------------------------------------------
$equipo_seleccionado_id = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $equipo_seleccionado_id = $_GET['id'];
} elseif (isset($_POST['equipo_id']) && !empty($_POST['equipo_id'])) {
    $equipo_seleccionado_id = $_POST['equipo_id'];
}

// ---------------------------------------------------------
// 3. PROCESAR FORMULARIO (GUARDAR DATOS)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Verificar Token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error de seguridad: Token CSRF inválido.");
    }

    if ($_POST['accion'] === 'guardar' && $equipo_seleccionado_id) {
        try {
            $conn->beginTransaction();

            // A. Actualizar Alias en tabla 'equipos'
            $sql_equipos = "UPDATE equipos SET nombre_equipo = ? WHERE id = ? AND usuario_id = ?";
            $stmt_eq = $conn->prepare($sql_equipos);
            $stmt_eq->execute([trim($_POST['nombre_equipo']), $equipo_seleccionado_id, $user_id]);

            // A.1 Actualizar Datos Personales en tabla 'usuarios'
            // Concatenar tipo y número de identificación
            $identificacion_final = (isset($_POST['cliente_tipo_identificacion']) ? $_POST['cliente_tipo_identificacion'] : '') . 
                                    (isset($_POST['cliente_identificacion_numero']) ? trim($_POST['cliente_identificacion_numero']) : '');

            // VERIFICACIÓN DE SEGURIDAD: Si cambia el email, verificar contraseña
            $stmt_current = $conn->prepare("SELECT email, password FROM usuarios WHERE id = ?");
            $stmt_current->execute([$user_id]);
            $current_user_data = $stmt_current->fetch(PDO::FETCH_ASSOC);
            
            if (trim($_POST['email_usuario']) !== $current_user_data['email']) {
                $password_confirm = $_POST['confirm_password'] ?? '';
                if (empty($password_confirm)) {
                    throw new Exception("Para cambiar el correo electrónico, es obligatorio confirmar su contraseña actual.");
                }
                if (!password_verify($password_confirm, $current_user_data['password'])) {
                    throw new Exception("La contraseña actual ingresada es incorrecta. No se pudo actualizar el perfil.");
                }
            }

            // 1. Actualizar Estado del Usuario (Login al sistema)
            // Ahora es independiente del equipo. Recibimos 'estado_usuario_input'
            $estado_usuario = (isset($_POST['estado_usuario_input']) && $_POST['estado_usuario_input'] == 'activo') ? 'activo' : 'suspendido';

            $sql_user = "UPDATE usuarios SET nombre = ?, apellido = ?, identificacion = ?, telefono = ?, direccion = ?, email = ?, estado = ? WHERE id = ?";
            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->execute([
                $_POST['cliente_nombre'], $_POST['cliente_apellido'], $identificacion_final,
                $_POST['cliente_telefono'], $_POST['cliente_direccion'], $_POST['email_usuario'], $estado_usuario, $user_id
            ]);

            // B. Actualizar Alarmas en tabla 'alarmas'
            // Verificamos si existe registro, si no, lo creamos
            $stmt_check_al = $conn->prepare("SELECT id FROM alarmas WHERE equipo_id = ?");
            $stmt_check_al->execute([$equipo_seleccionado_id]);
            
            if ($stmt_check_al->rowCount() > 0) {
                $sql_alarmas = "UPDATE alarmas SET 
                                Temperatura = ?, Temp_advertencia = ?, Presion = ?, 
                                Voltaje_Max = ?, Voltaje_Min = ?, cambio_pendiente = 1 
                                WHERE equipo_id = ?";
                $stmt_al = $conn->prepare($sql_alarmas);
                $stmt_al->execute([
                    $_POST['temperatura'], $_POST['temp_advertencia'], $_POST['presion'],
                    $_POST['voltaje_max'], $_POST['voltaje_min'], $equipo_seleccionado_id
                ]);
            } else {
                $sql_alarmas = "INSERT INTO alarmas (equipo_id, Temperatura, Temp_advertencia, Presion, Voltaje_Max, Voltaje_Min, cambio_pendiente) 
                                VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt_al = $conn->prepare($sql_alarmas);
                $stmt_al->execute([
                    $equipo_seleccionado_id, $_POST['temperatura'], $_POST['temp_advertencia'], 
                    $_POST['presion'], $_POST['voltaje_max'], $_POST['voltaje_min']
                ]);
            }

            // C. Actualizar Configuración WiFi en tabla 'config'
            $stmt_check_conf = $conn->prepare("SELECT equipo_id FROM config WHERE equipo_id = ?");
            $stmt_check_conf->execute([$equipo_seleccionado_id]);

            if ($stmt_check_conf->rowCount() > 0) {
                $sql_config = "UPDATE config SET wifi_ssid = ?, wifi_password = ? WHERE equipo_id = ?";
                $stmt_conf = $conn->prepare($sql_config);
                $stmt_conf->execute([$_POST['wifi_ssid'], $_POST['wifi_password'], $equipo_seleccionado_id]);
            } else {
                // Insertar configuración por defecto si no existe
                $sql_config = "INSERT INTO config (equipo_id, wifi_ssid, wifi_password, over_run, alarma_car, alarma_sis, nombre_BLE, pin_BLE, usuario, contraseña, nombre_mDNS, Notificacion_perdida_wifi, notificacion_solo_led, notificacion_solo_led_alarm_temp, funcion_1, funcion_2, funcion_3, funcion_4, funcion_5) 
                               VALUES (?, ?, ?, 1, 0, 0, 'SAMPATV', '230201', 'SAMPATV', '12345', 'Archivos', 1, 1, 0, 0, 0, 0, 0, 0)";
                $stmt_conf = $conn->prepare($sql_config);
                $stmt_conf->execute([$equipo_seleccionado_id, $_POST['wifi_ssid'], $_POST['wifi_password']]);
            }

            // D. Actualizar Flag en tabla 'desactivaciones' (Estado del Equipo)
            $flag_estado = isset($_POST['estado_equipo_input']) ? $_POST['estado_equipo_input'] : 1;
            $aplicar_a_todos = isset($_POST['aplicar_todos']) ? true : false;

            if ($aplicar_a_todos) {
                // OPCIÓN MASIVA: Actualizar TODOS los equipos de este usuario
                // Primero aseguramos que existan registros en desactivaciones para todos los equipos del usuario
                // (Insertamos los que falten)
                $sql_insert_missing = "INSERT INTO desactivaciones (equipo_id, flag) 
                                       SELECT id, ? FROM equipos WHERE usuario_id = ? 
                                       AND id NOT IN (SELECT equipo_id FROM desactivaciones WHERE equipo_id IS NOT NULL)";
                $stmt_missing = $conn->prepare($sql_insert_missing);
                $stmt_missing->execute([$flag_estado, $user_id]);

                // Ahora actualizamos el flag de todos
                $sql_update_all = "UPDATE desactivaciones d 
                                   JOIN equipos e ON d.equipo_id = e.id 
                                   SET d.flag = ? 
                                   WHERE e.usuario_id = ?";
                $stmt_all = $conn->prepare($sql_update_all);
                $stmt_all->execute([$flag_estado, $user_id]);

            } else {
                // OPCIÓN INDIVIDUAL: Solo el equipo actual
                // Usamos INSERT ... ON DUPLICATE KEY UPDATE para simplificar (o verificar existencia como antes)
                $stmt_check_des = $conn->prepare("SELECT id FROM desactivaciones WHERE equipo_id = ?");
                $stmt_check_des->execute([$equipo_seleccionado_id]);

                if ($stmt_check_des->rowCount() > 0) {
                    $sql_des = "UPDATE desactivaciones SET flag = ? WHERE equipo_id = ?";
                    $stmt_des = $conn->prepare($sql_des);
                    $stmt_des->execute([$flag_estado, $equipo_seleccionado_id]);
                } else {
                    $sql_des = "INSERT INTO desactivaciones (equipo_id, flag) VALUES (?, ?)";
                    $stmt_des = $conn->prepare($sql_des);
                    $stmt_des->execute([$equipo_seleccionado_id, $flag_estado]);
                }
            }

            $conn->commit();
            // Redirigir para refrescar datos y evitar reenvío de formulario
            header("Location: gestion_equipos.php?id=" . urlencode($equipo_seleccionado_id) . "&status=success");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            $mensaje = "Error al guardar los datos: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    } elseif ($_POST['accion'] === 'eliminar' && $equipo_seleccionado_id) {
        try {
            $conn->beginTransaction();
            
            // Eliminar datos relacionados
            $conn->prepare("DELETE FROM alarmas WHERE equipo_id = ?")->execute([$equipo_seleccionado_id]);
            $conn->prepare("DELETE FROM config WHERE equipo_id = ?")->execute([$equipo_seleccionado_id]);
            $conn->prepare("DELETE FROM desactivaciones WHERE equipo_id = ?")->execute([$equipo_seleccionado_id]);
            $conn->prepare("DELETE FROM sensores WHERE equipo_id = ?")->execute([$equipo_seleccionado_id]);
            
            // Eliminar el equipo
            $conn->prepare("DELETE FROM equipos WHERE id = ? AND usuario_id = ?")->execute([$equipo_seleccionado_id, $user_id]);
            
            $conn->commit();
            header("Location: gestion_equipos.php?status=deleted");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            $mensaje = "Error al eliminar el equipo: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// ---------------------------------------------------------
// 4. OBTENER DATOS ACTUALES DEL EQUIPO SELECCIONADO
// ---------------------------------------------------------
$datos = null;
if ($equipo_seleccionado_id) {
    try {
        $sql_datos = "SELECT 
                        e.nombre_equipo, e.mac_address,
                        a.Temperatura, a.Temp_advertencia, a.Presion, a.Voltaje_Max, a.Voltaje_Min,
                        c.wifi_ssid, c.wifi_password,
                        u.nombre as cli_nombre, u.apellido as cli_apellido, u.identificacion as cli_identificacion, u.telefono as cli_telefono, u.direccion as cli_direccion, u.email as user_email, u.estado as user_estado,
                        d.flag
                      FROM equipos e
                      LEFT JOIN alarmas a ON e.id = a.equipo_id
                      LEFT JOIN config c ON e.id = c.equipo_id
                      LEFT JOIN usuarios u ON e.usuario_id = u.id
                      LEFT JOIN desactivaciones d ON e.id = d.equipo_id
                      WHERE e.id = ? AND e.usuario_id = ?";
        $stmt_datos = $conn->prepare($sql_datos);
        $stmt_datos->execute([$equipo_seleccionado_id, $user_id]);
        $datos = $stmt_datos->fetch(PDO::FETCH_ASSOC);

        if (!$datos) {
            $mensaje = "No se encontraron datos para el equipo seleccionado.";
            $tipo_alerta = "warning";
            $equipo_seleccionado_id = null;
        }
    } catch (PDOException $e) {
        $mensaje = "Error al leer datos del equipo: " . $e->getMessage();
        $tipo_alerta = "danger";
    }

    // Procesar identificación para mostrar en el formulario (Separar Tipo y Número)
    $tipo_doc_val = 'V'; // Valor por defecto
    $num_doc_val = '';
    if (isset($datos['cli_identificacion']) && !empty($datos['cli_identificacion'])) {
        $first_char = strtoupper(substr($datos['cli_identificacion'], 0, 1));
        if (ctype_alpha($first_char)) {
            $tipo_doc_val = $first_char;
            $rest = substr($datos['cli_identificacion'], 1);
            // Si por casualidad se guardó con guión (V-123), lo quitamos para mostrar solo el número
            $num_doc_val = ltrim($rest, '-');
        } else {
            $num_doc_val = $datos['cli_identificacion'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            --placeholder-color-rgb: 108, 117, 125;
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

        /* Borde de inputs al enfocar */
        .form-control:focus, .form-select:focus {
            border-color: var(--placeholder-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(var(--placeholder-color-rgb), 0.25) !important;
            outline: 0 !important;
        }
        
        /* Estilo específico para que el input de MAC (readonly) se vea igual a los demás */
        #mac_address_display[readonly] {
            background-color: #fff;
        }
        body.dark-mode #mac_address_display[readonly] {
            background-color: #2c2c2c;
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
        body.dark-mode .form-control, body.dark-mode .form-select { background-color: #2c2c2c; border-color: #444; color: #fff; }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus { background-color: #3a3a3a; border-color: var(--bs-primary); color: #fff; }
        body.dark-mode .btn-close { filter: invert(1) grayscale(100) brightness(200%); }

        /* Spinner Overlay */
        #loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8); z-index: 9999;
            display: flex; justify-content: center; align-items: center;
        }
        body.dark-mode #loading-overlay { background: rgba(0, 0, 0, 0.8); }
    </style>
</head>
<body>
    <?php $base_path = '../'; include 'sidebar.php'; include 'navbar.php'; ?>

    <div class="container pt-5 pt-lg-4"></div></div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="bi bi-tools me-2"></i>Gestión de Equipos</h2>
        </div>

        <!-- Mensajes de Alerta -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Spinner de Carga -->
        <div id="loading-overlay" class="d-none">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Cargando...</span>
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

        <!-- Selector de Equipo -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <label for="selector_equipo" class="form-label fw-bold">Seleccione un Equipo (Alias):</label>
                <select class="form-select form-select-lg" id="selector_equipo" onchange="cambiarEquipo(this.value)">
                    <option value="" disabled <?php echo !$equipo_seleccionado_id ? 'selected' : ''; ?>>-- Seleccionar --</option>
                    <?php foreach ($lista_equipos as $eq): ?>
                        <option value="<?php echo $eq['id']; ?>" <?php echo ($equipo_seleccionado_id == $eq['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($eq['nombre_equipo']); ?> (<?php echo htmlspecialchars($eq['mac_address']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Formulario de Edición -->
        <?php if ($datos): ?>
        <form id="form-gestion" method="POST" action="gestion_equipos.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="equipo_id" value="<?php echo htmlspecialchars($equipo_seleccionado_id); ?>">
            <!-- Campos ocultos para validación de email -->
            <input type="hidden" id="original_email" value="<?php echo htmlspecialchars($datos['user_email'] ?? ''); ?>">
            <input type="hidden" name="confirm_password" id="confirm_password">

            <div class="row">
                <!-- Columna 1: Datos Básicos y WiFi -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header"><i class="bi bi-hdd-network me-2"></i>Datos Generales</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Dirección MAC (Solo lectura)</label>
                                <input type="text" class="form-control" id="mac_address_display" value="<?php echo htmlspecialchars($datos['mac_address']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="nombre_equipo" class="form-label">Alias del Equipo</label>
                                <input type="text" class="form-control" id="nombre_equipo" name="nombre_equipo" value="<?php echo htmlspecialchars($datos['nombre_equipo']); ?>" required>
                            </div>
                            <hr>
                            <h6 class="text-primary">Conexión WiFi</h6>
                            <div class="mb-3">
                                <label for="wifi_ssid" class="form-label">Nombre WiFi (SSID)</label>
                                <input type="text" class="form-control" id="wifi_ssid" name="wifi_ssid" value="<?php echo htmlspecialchars($datos['wifi_ssid'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="wifi_password" class="form-label">Contraseña WiFi</label>
                                <input type="text" class="form-control" id="wifi_password" name="wifi_password" value="<?php echo htmlspecialchars($datos['wifi_password'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna 2: Datos del Cliente (Desactivaciones) -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header"><i class="bi bi-person-vcard me-2"></i>Información del Cliente</div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6 mb-2">
                                    <label class="form-label">Nombre</label>
                                    <input type="text" class="form-control" name="cliente_nombre" value="<?php echo htmlspecialchars($datos['cli_nombre'] ?? ''); ?>">
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label">Apellido</label>
                                    <input type="text" class="form-control" name="cliente_apellido" value="<?php echo htmlspecialchars($datos['cli_apellido'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Identificación (C.I./RIF)</label>
                                <div class="input-group">
                                    <select class="form-select" name="cliente_tipo_identificacion" style="max-width: 80px;">
                                        <option value="V" <?php echo ($tipo_doc_val == 'V') ? 'selected' : ''; ?>>V</option>
                                        <option value="E" <?php echo ($tipo_doc_val == 'E') ? 'selected' : ''; ?>>E</option>
                                        <option value="J" <?php echo ($tipo_doc_val == 'J') ? 'selected' : ''; ?>>J</option>
                                        <option value="G" <?php echo ($tipo_doc_val == 'G') ? 'selected' : ''; ?>>G</option>
                                        <option value="P" <?php echo ($tipo_doc_val == 'P') ? 'selected' : ''; ?>>P</option>
                                    </select>
                                    <input type="text" class="form-control" name="cliente_identificacion_numero" value="<?php echo htmlspecialchars($num_doc_val); ?>" placeholder="Ej: 12345678">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="cliente_telefono" value="<?php echo htmlspecialchars($datos['cli_telefono'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección Física</label>
                                <textarea class="form-control" name="cliente_direccion" rows="2"><?php echo htmlspecialchars($datos['cli_direccion'] ?? ''); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico (Cuenta)</label>
                                <input type="email" class="form-control" id="email_usuario" name="email_usuario" value="<?php echo htmlspecialchars($datos['user_email'] ?? ''); ?>">
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-primary">Acceso al Sistema (Usuario)</label>
                                <select class="form-select mb-2" name="estado_usuario_input">
                                    <option value="activo" <?php echo (isset($datos['user_estado']) && $datos['user_estado'] == 'activo') ? 'selected' : ''; ?>>Permitido (Activo)</option>
                                    <option value="suspendido" <?php echo (!isset($datos['user_estado']) || $datos['user_estado'] != 'activo') ? 'selected' : ''; ?>>Bloqueado (Suspendido)</option>
                                </select>
                                <small class="text-muted d-block mb-3">Controla si el cliente puede iniciar sesión.</small>

                                <label class="form-label fw-bold text-primary">Estado de ESTE Equipo</label>
                                <select class="form-select" name="estado_equipo_input">
                                    <option value="1" <?php echo (isset($datos['flag']) && $datos['flag'] == 1) ? 'selected' : ''; ?>>Activo (Funcionando)</option>
                                    <option value="0" <?php echo (isset($datos['flag']) && $datos['flag'] == 0) ? 'selected' : ''; ?>>Suspendido (Apagado)</option>
                                </select>
                                
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="aplicar_todos" name="aplicar_todos">
                                    <label class="form-check-label text-danger fw-bold" for="aplicar_todos">
                                        Aplicar estado de equipo a TODOS los equipos de este cliente
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna 3: Alarmas y Umbrales -->
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header"><i class="bi bi-speedometer2 me-2"></i>Configuración de Alarmas</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6"><label class="form-label">Temp. Crítica (°C)</label><input type="number" step="0.1" class="form-control" name="temperatura" value="<?php echo htmlspecialchars($datos['Temperatura'] ?? ''); ?>"></div>
                                <div class="col-6"><label class="form-label">Temp. Advertencia (°C)</label><input type="number" step="0.1" class="form-control" name="temp_advertencia" value="<?php echo htmlspecialchars($datos['Temp_advertencia'] ?? ''); ?>"></div>
                                
                                <div class="col-12"><label class="form-label">Presión Mínima (Bar/hPa)</label><input type="number" step="0.1" class="form-control" name="presion" value="<?php echo htmlspecialchars($datos['Presion'] ?? ''); ?>"></div>
                                
                                <div class="col-6"><label class="form-label">Voltaje Máximo (V)</label><input type="number" step="0.1" class="form-control" name="voltaje_max" value="<?php echo htmlspecialchars($datos['Voltaje_Max'] ?? ''); ?>"></div>
                                <div class="col-6"><label class="form-label">Voltaje Mínimo (V)</label><input type="number" step="0.1" class="form-control" name="voltaje_min" value="<?php echo htmlspecialchars($datos['Voltaje_Min'] ?? ''); ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mb-5">
                <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-save me-2"></i>Guardar Todos los Cambios</button>
                <button type="button" class="btn btn-danger btn-lg" onclick="confirmarEliminacion()"><i class="bi bi-trash me-2"></i>Eliminar Equipo</button>
            </div>
        </form>

        <!-- Formulario oculto para eliminar -->
        <form id="form-eliminar" method="POST" action="gestion_equipos.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="equipo_id" value="<?php echo htmlspecialchars($equipo_seleccionado_id); ?>">
        </form>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
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
            
            document.querySelectorAll('.color-circle').forEach(c => c.classList.remove('selected'));
            const selectedCircle = Array.from(document.querySelectorAll('.color-circle')).find(c => c.dataset.hex === color.hex);
            if (selectedCircle) selectedCircle.classList.add('selected');
        };

        colors.forEach(color => {
            const circle = document.createElement('div');
            circle.className = 'color-circle';
            circle.style.backgroundColor = color.hex;
            circle.dataset.hex = color.hex;
            circle.onclick = () => setColor(color);
            paletteContainer.appendChild(circle);
        });

        const savedColor = localStorage.getItem('themeColor');
        if (savedColor) setColor(JSON.parse(savedColor));
        else setColor(colors[0]);

        // --- Lógica de Paleta de Placeholders ---
        const placeholderContainer = document.getElementById('placeholderPalette');
        const setPlaceholderColor = (color) => {
            // Asegurar que tenemos valores válidos
            const hex = color.hex || '#6c757d';
            const rgb = color.rgb || '108, 117, 125';
            
            document.documentElement.style.setProperty('--placeholder-color', hex);
            document.documentElement.style.setProperty('--placeholder-color-rgb', rgb);
            localStorage.setItem('placeholderColor', JSON.stringify(color));
            
            document.querySelectorAll('#placeholderPalette .color-circle').forEach(c => c.classList.remove('selected'));
            const selectedCircle = Array.from(document.querySelectorAll('#placeholderPalette .color-circle')).find(c => c.dataset.hex === hex);
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

        // --- SweetAlert2 para mensajes de éxito (URL params) ---
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');

            if (status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'Los datos se han actualizado correctamente.',
                    confirmButtonColor: '#0d6efd',
                    timer: 2500,
                    showConfirmButton: false
                });
            } else if (status === 'deleted') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Eliminado!',
                    text: 'El equipo ha sido eliminado del sistema.',
                    confirmButtonColor: '#0d6efd',
                    timer: 2500,
                    showConfirmButton: false
                });
            }

            // Limpiar URL para evitar que el mensaje reaparezca al recargar
            if (status) {
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.delete('status');
                window.history.replaceState({}, document.title, newUrl.toString());
            }
        });

        // --- Función para mostrar spinner al cambiar equipo ---
        function cambiarEquipo(id) {
            document.getElementById('loading-overlay').classList.remove('d-none');
            window.location.href = 'gestion_equipos.php?id=' + encodeURIComponent(id);
        }

        // --- Función para confirmar eliminación ---
        function confirmarEliminacion() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Se eliminará el equipo y todo su historial de forma permanente. ¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('form-eliminar').submit();
                }
            });
        }

        // --- Validación de Email antes de enviar ---
        const formGestion = document.getElementById('form-gestion');
        if (formGestion) {
            formGestion.addEventListener('submit', function(event) {
                const emailInput = document.getElementById('email_usuario');
                const originalEmail = document.getElementById('original_email').value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (emailInput && !emailRegex.test(emailInput.value.trim())) {
                    event.preventDefault(); // Detener el envío del formulario
                    Swal.fire({
                        icon: 'error',
                        title: 'Correo Inválido',
                        text: 'El formato del correo electrónico no es correcto. Por favor verifíquelo.',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                // Verificar si el email ha cambiado
                if (emailInput.value.trim() !== originalEmail) {
                    event.preventDefault(); // Detener envío para pedir contraseña
                    
                    Swal.fire({
                        title: 'Confirmar Cambio de Correo',
                        html: `
                            <p>Ha modificado su dirección de correo. Por seguridad, ingrese su contraseña actual para guardar los cambios:</p>
                            <div class="input-group">
                                <input type="password" id="swal-password" class="form-control" placeholder="Contraseña actual" autocomplete="current-password">
                                <button class="btn btn-outline-secondary" type="button" id="toggle-swal-password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Confirmar y Guardar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#0d6efd',
                        didOpen: () => {
                            const input = Swal.getPopup().querySelector('#swal-password');
                            const btn = Swal.getPopup().querySelector('#toggle-swal-password');
                            const icon = btn.querySelector('i');
                            
                            input.focus();
                            
                            btn.addEventListener('click', () => {
                                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                                input.setAttribute('type', type);
                                icon.classList.toggle('bi-eye');
                                icon.classList.toggle('bi-eye-slash');
                            });
                        },
                        preConfirm: () => {
                            const password = Swal.getPopup().querySelector('#swal-password').value;
                            if (!password) {
                                Swal.showValidationMessage('Debe ingresar su contraseña');
                            }
                            return password;
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            document.getElementById('confirm_password').value = result.value;
                            formGestion.submit(); // Enviar formulario manualmente
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>