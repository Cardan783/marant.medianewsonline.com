<?php
session_start();
require_once '../php/conexion.php';

// Verificar permisos: Solo Superadmin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_rol'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

// --- Verificación de Seguridad (Sudo Mode) ---
if (!isset($_SESSION['sudo_mode']) || $_SESSION['sudo_mode'] !== true) {
    $error_pass = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sudo_password'])) {
        $pass = $_POST['sudo_password'];
        $id = $_SESSION['admin_id'];
        
        $stmt = $conn->prepare("SELECT password FROM administrador WHERE id = ?");
        $stmt->execute([$id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin_data && password_verify($pass, $admin_data['password'])) {
            $_SESSION['sudo_mode'] = true;
            header("Location: gestion_admins.php");
            exit();
        } else {
            $error_pass = "Contraseña incorrecta.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Seguridad - SAMPATV</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
            .lock-card { max-width: 400px; width: 100%; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
            .lock-icon { font-size: 3rem; color: #dc3545; margin-bottom: 1rem; }
        </style>
    </head>
    <body>
        <div class="card lock-card">
            <div class="card-body text-center p-5">
                <div class="lock-icon"><i class="fa-solid fa-user-lock"></i></div>
                <h4 class="mb-3">Acceso Protegido</h4>
                <p class="text-muted mb-4">Confirma tu contraseña de Superadmin para continuar.</p>
                
                <?php if($error_pass): ?>
                    <div class="alert alert-danger py-2 small"><?php echo $error_pass; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <input type="password" name="sudo_password" class="form-control form-control-lg text-center" placeholder="Contraseña" required autofocus>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger btn-lg">Desbloquear</button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$mensaje = '';
$tipo_alerta = '';

// --- Lógica CRUD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        try {
            if ($_POST['accion'] === 'crear') {
                // Crear Admin
                $nombre = trim($_POST['nombre']);
                $usuario = trim($_POST['usuario']);
                $email = trim($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $rol = $_POST['rol'];

                $stmt = $conn->prepare("INSERT INTO administrador (nombre, usuario, email, password, rol, estado) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$nombre, $usuario, $email, $password, $rol]);
                
                $mensaje = "Administrador creado correctamente.";
                $tipo_alerta = "success";

            } elseif ($_POST['accion'] === 'editar') {
                // Editar Admin
                $id = $_POST['id'];
                $nombre = trim($_POST['nombre']);
                $usuario = trim($_POST['usuario']);
                $email = trim($_POST['email']);
                $rol = $_POST['rol'];
                $estado = $_POST['estado'];

                // Si el campo password no está vacío, lo actualizamos
                if (!empty($_POST['password'])) {
                    $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE administrador SET nombre=?, usuario=?, email=?, rol=?, estado=?, password=? WHERE id=?");
                    $stmt->execute([$nombre, $usuario, $email, $rol, $estado, $hash, $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE administrador SET nombre=?, usuario=?, email=?, rol=?, estado=? WHERE id=?");
                    $stmt->execute([$nombre, $usuario, $email, $rol, $estado, $id]);
                }
                
                $mensaje = "Datos actualizados correctamente.";
                $tipo_alerta = "success";

            } elseif ($_POST['accion'] === 'eliminar') {
                // Eliminar Admin
                $id = $_POST['id'];
                // Evitar auto-eliminación
                if ($id != $_SESSION['admin_id']) {
                    $stmt = $conn->prepare("DELETE FROM administrador WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = "Administrador eliminado.";
                    $tipo_alerta = "warning";
                } else {
                    $mensaje = "No puedes eliminar tu propia cuenta.";
                    $tipo_alerta = "danger";
                }
            }
        } catch (PDOException $e) {
            $mensaje = "Error en base de datos: " . $e->getMessage();
            $tipo_alerta = "danger";
        }
    }
}

// Obtener lista de administradores
$admins = $conn->query("SELECT * FROM administrador ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Administradores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .strength-meter { height: 4px; transition: all 0.3s; margin-top: 4px; border-radius: 2px; }
        .strength-weak { background-color: #dc3545; width: 30%; }
        .strength-medium { background-color: #ffc107; width: 60%; }
        .strength-strong { background-color: #198754; width: 100%; }
        .pass-actions .btn { padding: 0.375rem 0.6rem; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa-solid fa-users-gear"></i> Gestión de Administradores</h2>
            <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver al Dashboard</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Formulario de Creación -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">Agregar Nuevo Administrador</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-3">
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre Completo" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
                    </div>
                    <div class="col-md-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="password" name="password" id="new_pass" class="form-control" placeholder="Contraseña" required onkeyup="checkStrength(this, 'meter_new')">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleView('new_pass')" title="Ver"><i class="fa-solid fa-eye"></i></button>
                            <button class="btn btn-outline-secondary" type="button" onclick="generatePass('new_pass', 'meter_new')" title="Sugerir"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyPass('new_pass')" title="Copiar"><i class="fa-solid fa-copy"></i></button>
                        </div>
                        <div class="progress strength-meter"><div id="meter_new" class="progress-bar" role="progressbar"></div></div>
                    </div>
                    <div class="col-md-3">
                        <select name="rol" class="form-select">
                            <option value="admin">Admin Normal</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success w-100"><i class="fa-solid fa-plus"></i> Crear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Administradores -->
        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="accion" value="editar">
                                <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                                
                                <td><input type="text" name="nombre" class="form-control form-control-sm" value="<?php echo htmlspecialchars($admin['nombre']); ?>"></td>
                                <td><input type="text" name="usuario" class="form-control form-control-sm" value="<?php echo htmlspecialchars($admin['usuario']); ?>"></td>
                                <td>
                                    <select name="rol" class="form-select form-select-sm">
                                        <option value="admin" <?php echo $admin['rol'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="superadmin" <?php echo $admin['rol'] == 'superadmin' ? 'selected' : ''; ?>>Superadmin</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="estado" class="form-select form-select-sm <?php echo $admin['estado'] == 1 ? 'text-success' : 'text-danger'; ?>">
                                        <option value="1" <?php echo $admin['estado'] == 1 ? 'selected' : ''; ?>>Activo</option>
                                        <option value="0" <?php echo $admin['estado'] == 0 ? 'selected' : ''; ?>>Inactivo</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
                                    <div class="input-group input-group-sm d-inline-flex w-auto" style="vertical-align: middle;">
                                        <input type="password" name="password" id="pass_<?php echo $admin['id']; ?>" class="form-control" placeholder="Nueva Pass (Opcional)" style="max-width: 140px;">
                                        <button class="btn btn-outline-secondary" type="button" onclick="toggleView('pass_<?php echo $admin['id']; ?>')"><i class="fa-solid fa-eye"></i></button>
                                        <button class="btn btn-outline-secondary" type="button" onclick="generatePass('pass_<?php echo $admin['id']; ?>', null)"><i class="fa-solid fa-key"></i></button>
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyPass('pass_<?php echo $admin['id']; ?>')"><i class="fa-solid fa-copy"></i></button>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm" title="Guardar Cambios"><i class="fa-solid fa-save"></i></button>
                                    
                                    <?php if($admin['id'] != $_SESSION['admin_id']): ?>
                                        <button type="submit" form="form-delete-<?php echo $admin['id']; ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar este administrador?');"><i class="fa-solid fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </form>
                            <!-- Formulario separado para eliminar para evitar conflictos -->
                            <form id="form-delete-<?php echo $admin['id']; ?>" method="POST" style="display:none;">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?php echo $admin['id']; ?>">
                            </form>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Generador de Contraseñas Fuertes
        function generatePass(inputId, meterId) {
            const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            const length = 16;
            let password = "";
            for (let i = 0; i < length; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            const input = document.getElementById(inputId);
            input.value = password;
            input.type = "text"; // Mostrarla automáticamente al generar
            
            if (meterId) checkStrength(input, meterId);
        }

        // Alternar visibilidad
        function toggleView(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === "password" ? "text" : "password";
        }

        // Copiar al portapapeles
        function copyPass(inputId) {
            const input = document.getElementById(inputId);
            if (!input.value) return;
            
            navigator.clipboard.writeText(input.value).then(() => {
                const Toast = Swal.mixin({
                    toast: true, position: 'top-end', showConfirmButton: false, timer: 2000,
                    didOpen: (toast) => { toast.onmouseenter = Swal.stopTimer; toast.onmouseleave = Swal.resumeTimer; }
                });
                Toast.fire({ icon: 'success', title: 'Contraseña copiada' });
            });
        }

        // Medidor de Fortaleza
        function checkStrength(input, meterId) {
            const meter = document.getElementById(meterId);
            if (!meter) return;
            
            const val = input.value;
            let score = 0;
            if (val.length > 6) score++;
            if (val.length > 10) score++;
            if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            meter.className = 'progress-bar'; // Reset
            if (val.length === 0) { meter.style.width = '0%'; }
            else if (score < 2) { meter.classList.add('bg-danger'); meter.style.width = '30%'; }
            else if (score < 4) { meter.classList.add('bg-warning'); meter.style.width = '60%'; }
            else { meter.classList.add('bg-success'); meter.style.width = '100%'; }
        }
    </script>
</body>
</html>