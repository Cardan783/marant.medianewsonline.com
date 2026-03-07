<?php
session_start();
require_once '../php/conexion.php';

// Verificar sesión de ADMIN
if (!isset($_SESSION['admin_id']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

$mensaje = '';
$tipo_alerta = '';

// --- VARIABLES DE BÚSQUEDA ---
$tipo_doc = $_GET['tipo_doc'] ?? 'V-';
$num_doc = $_GET['num_doc'] ?? '';
$usuario_encontrado = null;
$equipos_usuario = [];
$equipo_detalle = null;

// --- PROCESAR ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // 1. Actualizar Estado Global del Usuario
    if ($_POST['accion'] === 'actualizar_usuario_status') {
        try {
            $uid = $_POST['usuario_id'];
            $nuevo_estado = $_POST['estado_usuario'];
            
            $conn->beginTransaction();

            $stmt = $conn->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $uid]);

            // Sincronizar estado de equipos con el usuario (Activo -> 1, Suspendido -> 0)
            $nuevo_flag = ($nuevo_estado === 'activo') ? 1 : 0;
            $sql_equipos = "UPDATE desactivaciones d JOIN equipos e ON d.equipo_id = e.id SET d.flag = ? WHERE e.usuario_id = ?";
            $stmt_eq = $conn->prepare($sql_equipos);
            $stmt_eq->execute([$nuevo_flag, $uid]);

            $conn->commit();
            $mensaje = "Estado del usuario actualizado correctamente.";
            $tipo_alerta = "success";
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $mensaje = "Error: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
    
    // 2. Actualizar Estado de un Equipo (Rápido)
    if ($_POST['accion'] === 'actualizar_equipo_status') {
        try {
            $eid = $_POST['equipo_id'];
            $nuevo_flag = $_POST['flag_equipo'];
            
            // Verificar/Insertar/Actualizar en desactivaciones
            $stmt = $conn->prepare("SELECT id FROM desactivaciones WHERE equipo_id = ?");
            $stmt->execute([$eid]);
            if ($stmt->rowCount() > 0) {
                $conn->prepare("UPDATE desactivaciones SET flag = ? WHERE equipo_id = ?")->execute([$nuevo_flag, $eid]);
            } else {
                $conn->prepare("INSERT INTO desactivaciones (equipo_id, flag) VALUES (?, ?)")->execute([$eid, $nuevo_flag]);
            }
            $mensaje = "Estado del equipo actualizado.";
            $tipo_alerta = "success";
        } catch (PDOException $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }

    // 3. Guardar Detalles Técnicos (Alarmas/WiFi)
    if ($_POST['accion'] === 'guardar_detalle') {
        try {
            $eid = $_POST['equipo_id'];
            $conn->beginTransaction();
            
            // Alias
            $conn->prepare("UPDATE equipos SET nombre_equipo = ? WHERE id = ?")->execute([trim($_POST['nombre_equipo']), $eid]);
            
            // Alarmas
            $stmt_check = $conn->prepare("SELECT id FROM alarmas WHERE equipo_id = ?");
            $stmt_check->execute([$eid]);
            if ($stmt_check->rowCount() > 0) {
                $sql = "UPDATE alarmas SET Temperatura=?, Temp_advertencia=?, Presion=?, Voltaje_Max=?, Voltaje_Min=?, cambio_pendiente=1 WHERE equipo_id=?";
                $conn->prepare($sql)->execute([$_POST['temperatura'], $_POST['temp_advertencia'], $_POST['presion'], $_POST['voltaje_max'], $_POST['voltaje_min'], $eid]);
            } else {
                $sql = "INSERT INTO alarmas (equipo_id, Temperatura, Temp_advertencia, Presion, Voltaje_Max, Voltaje_Min, cambio_pendiente) VALUES (?, ?, ?, ?, ?, ?, 1)";
                $conn->prepare($sql)->execute([$eid, $_POST['temperatura'], $_POST['temp_advertencia'], $_POST['presion'], $_POST['voltaje_max'], $_POST['voltaje_min']]);
            }
            
            // Config WiFi
            $stmt_conf = $conn->prepare("SELECT equipo_id FROM config WHERE equipo_id = ?");
            $stmt_conf->execute([$eid]);
            if ($stmt_conf->rowCount() > 0) {
                $conn->prepare("UPDATE config SET wifi_ssid=?, wifi_password=? WHERE equipo_id=?")->execute([$_POST['wifi_ssid'], $_POST['wifi_password'], $eid]);
            } else {
                $conn->prepare("INSERT INTO config (equipo_id, wifi_ssid, wifi_password, over_run) VALUES (?, ?, ?, 1)")->execute([$eid, $_POST['wifi_ssid'], $_POST['wifi_password']]);
            }

            $conn->commit();
            $mensaje = "Detalles técnicos guardados.";
            $tipo_alerta = "success";
        } catch (Exception $e) {
            $conn->rollBack();
            $mensaje = "Error al guardar detalles: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// --- LÓGICA DE BÚSQUEDA ---
if ($num_doc) {
    // Intentar búsqueda flexible (con y sin guión)
    $id_search = $tipo_doc . $num_doc; // Ej: V-123456
    $id_search_simple = $tipo_doc . str_replace('-', '', $num_doc); // Ej: V123456
    
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE identificacion = ? OR identificacion = ?");
    $stmt->execute([$id_search, $id_search_simple]);
    $usuario_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario_encontrado) {
        // Buscar equipos del usuario
        $stmt_eq = $conn->prepare("
            SELECT e.id, e.nombre_equipo, e.mac_address, d.flag 
            FROM equipos e 
            LEFT JOIN desactivaciones d ON e.id = d.equipo_id 
            WHERE e.usuario_id = ?
        ");
        $stmt_eq->execute([$usuario_encontrado['id']]);
        $equipos_usuario = $stmt_eq->fetchAll(PDO::FETCH_ASSOC);
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $mensaje = "Usuario no encontrado con la identificación: " . htmlspecialchars($id_search);
        $tipo_alerta = "warning";
    }
}

// --- CARGAR DETALLE DE EQUIPO (Si se solicitó editar uno específico) ---
if (isset($_GET['editar_equipo'])) {
    $eid = $_GET['editar_equipo'];
    $stmt = $conn->prepare("
        SELECT e.*, a.Temperatura, a.Temp_advertencia, a.Presion, a.Voltaje_Max, a.Voltaje_Min, c.wifi_ssid, c.wifi_password
        FROM equipos e
        LEFT JOIN alarmas a ON e.id = a.equipo_id
        LEFT JOIN config c ON e.id = c.equipo_id
        WHERE e.id = ?
    ");
    $stmt->execute([$eid]);
    $equipo_detalle = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Equipos y Clientes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa-solid fa-screwdriver-wrench"></i> Gestión de Equipos y Clientes</h2>
            <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver al Dashboard</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 1. BUSCADOR POR IDENTIFICACIÓN -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white"><i class="fa-solid fa-magnifying-glass me-2"></i>Buscar Usuario</div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <select name="tipo_doc" class="form-select">
                            <option value="V-" <?php echo $tipo_doc == 'V-' ? 'selected' : ''; ?>>V-</option>
                            <option value="E-" <?php echo $tipo_doc == 'E-' ? 'selected' : ''; ?>>E-</option>
                            <option value="J-" <?php echo $tipo_doc == 'J-' ? 'selected' : ''; ?>>J-</option>
                            <option value="G-" <?php echo $tipo_doc == 'G-' ? 'selected' : ''; ?>>G-</option>
                            <option value="DNI" <?php echo $tipo_doc == 'DNI' ? 'selected' : ''; ?>>DNI</option>
                        </select>
                    </div>
                    <div class="col-auto flex-grow-1">
                        <input type="text" name="num_doc" class="form-control" placeholder="Número de Identificación (Ej: 12345678)" value="<?php echo htmlspecialchars($num_doc); ?>" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary px-4">Buscar</button>
                        <?php if($num_doc): ?>
                            <a href="gestion_equipos.php" class="btn btn-outline-secondary">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($usuario_encontrado): ?>
            <!-- 2. RESULTADOS: USUARIO Y EQUIPOS -->
            <div class="row">
                <!-- DATOS DEL USUARIO -->
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-dark text-white">Datos del Usuario</div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($usuario_encontrado['nombre'] . ' ' . $usuario_encontrado['apellido']); ?></h5>
                            <p class="card-text mb-1"><strong>ID:</strong> <?php echo htmlspecialchars($usuario_encontrado['identificacion']); ?></p>
                            <p class="card-text mb-3"><strong>Email:</strong> <?php echo htmlspecialchars($usuario_encontrado['email']); ?></p>
                            
                            <form method="POST" class="border-top pt-3">
                                <input type="hidden" name="accion" value="actualizar_usuario_status">
                                <input type="hidden" name="usuario_id" value="<?php echo $usuario_encontrado['id']; ?>">
                                <label class="form-label fw-bold text-danger">Estado Global (Login)</label>
                                <div class="input-group">
                                    <select name="estado_usuario" class="form-select <?php echo $usuario_encontrado['estado'] == 'activo' ? 'text-success' : 'text-danger'; ?>">
                                        <option value="activo" <?php echo $usuario_encontrado['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                        <option value="suspendido" <?php echo $usuario_encontrado['estado'] != 'activo' ? 'selected' : ''; ?>>Suspendido</option>
                                    </select>
                                    <button class="btn btn-outline-primary" type="submit">Guardar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- LISTA DE EQUIPOS -->
                <div class="col-md-8 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-secondary text-white">Equipos Registrados</div>
                        <div class="card-body">
                            <?php if (count($equipos_usuario) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Alias</th>
                                                <th>MAC</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($equipos_usuario as $eq): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($eq['nombre_equipo']); ?></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($eq['mac_address']); ?></small></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="accion" value="actualizar_equipo_status">
                                                        <input type="hidden" name="equipo_id" value="<?php echo $eq['id']; ?>">
                                                        <select name="flag_equipo" class="form-select form-select-sm py-0 <?php echo $eq['flag'] == 1 ? 'text-success' : 'text-danger'; ?>" onchange="this.form.submit()" style="width: 110px;">
                                                            <option value="1" <?php echo $eq['flag'] == 1 ? 'selected' : ''; ?>>Activo</option>
                                                            <option value="0" <?php echo $eq['flag'] == 0 ? 'selected' : ''; ?>>Suspendido</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td>
                                                    <a href="gestion_equipos.php?tipo_doc=<?php echo $tipo_doc; ?>&num_doc=<?php echo $num_doc; ?>&editar_equipo=<?php echo $eq['id']; ?>" class="btn btn-sm btn-info text-white"><i class="fa-solid fa-pen-to-square"></i> Detalles</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Este usuario no tiene equipos registrados.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. FORMULARIO DE DETALLES (Solo si se seleccionó editar) -->
            <?php if ($equipo_detalle): ?>
            <div class="card shadow-sm border-info mb-5">
                <div class="card-header bg-info text-white">
                    <i class="fa-solid fa-sliders me-2"></i>Editando: <?php echo htmlspecialchars($equipo_detalle['nombre_equipo']); ?> (<?php echo htmlspecialchars($equipo_detalle['mac_address']); ?>)
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="accion" value="guardar_detalle">
                        <input type="hidden" name="equipo_id" value="<?php echo $equipo_detalle['id']; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Alias del Equipo</label>
                                <input type="text" name="nombre_equipo" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['nombre_equipo']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">WiFi SSID</label>
                                <input type="text" name="wifi_ssid" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['wifi_ssid'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">WiFi Password</label>
                                <input type="text" name="wifi_password" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['wifi_password'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12"><hr><h6>Alarmas</h6></div>
                            
                            <div class="col-md-2">
                                <label class="form-label small">Temp. Crítica</label>
                                <input type="number" step="0.1" name="temperatura" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['Temperatura'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Temp. Advertencia</label>
                                <input type="number" step="0.1" name="temp_advertencia" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['Temp_advertencia'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Presión Mín</label>
                                <input type="number" step="0.1" name="presion" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['Presion'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Voltaje Max</label>
                                <input type="number" step="0.1" name="voltaje_max" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['Voltaje_Max'] ?? ''); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Voltaje Min</label>
                                <input type="number" step="0.1" name="voltaje_min" class="form-control" value="<?php echo htmlspecialchars($equipo_detalle['Voltaje_Min'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12 text-end mt-3">
                                <a href="gestion_equipos.php?tipo_doc=<?php echo $tipo_doc; ?>&num_doc=<?php echo $num_doc; ?>" class="btn btn-secondary me-2">Cancelar</a>
                                <button type="submit" class="btn btn-success">Guardar Cambios</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>