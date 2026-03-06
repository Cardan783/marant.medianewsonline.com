<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once 'conexion.php';
$user_id = $_SESSION['user_id'];
$mensaje = '';
$tipo_alerta = '';

// --- Procesar Formulario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    
    try {
        // 1. Manejo de la Foto
        $foto_sql = ""; // Fragmento SQL para la foto
        $params = [$nombre, $apellido, $telefono, $direccion];

        // Obtener foto actual de la BD antes de procesar la nueva (para borrarla luego)
        $stmt_old = $conn->prepare("SELECT foto FROM usuarios WHERE id = ?");
        $stmt_old->execute([$user_id]);
        $foto_anterior_db = $stmt_old->fetchColumn();

        if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {
            // Validar errores de subida
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                switch ($_FILES['foto']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        throw new Exception("La imagen es demasiado grande.");
                    case UPLOAD_ERR_PARTIAL:
                        throw new Exception("La subida se interrumpió.");
                    case UPLOAD_ERR_NO_FILE:
                        break; 
                    default:
                        throw new Exception("Error al subir la imagen. Código: " . $_FILES['foto']['error']);
                }
            }

            $file_tmp = $_FILES['foto']['tmp_name'];
            $file_name = $_FILES['foto']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_ext, $allowed)) {
                // Crear nombre único
                $new_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
                $upload_dir = __DIR__ . '/../img/usuarios/';
                
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                $destination = $upload_dir . $new_name;

                // --- CREAR MINIATURA (Redimensionar) ---
                list($width, $height) = getimagesize($file_tmp);
                $new_width = 300; // Tamaño cuadrado deseado
                $new_height = 300;
                
                // Crear lienzo vacío
                $thumb = imagecreatetruecolor($new_width, $new_height);
                
                // Cargar imagen original según tipo
                $source = null;
                switch($file_ext) {
                    case 'jpeg': case 'jpg': $source = @imagecreatefromjpeg($file_tmp); break;
                    case 'png': $source = @imagecreatefrompng($file_tmp); 
                                imagealphablending($thumb, false);
                                imagesavealpha($thumb, true);
                                break;
                    case 'gif': $source = @imagecreatefromgif($file_tmp); break;
                }

                if (!$source) {
                    throw new Exception("El archivo no es una imagen válida o está corrupto.");
                }

                // Redimensionar y recortar (Center Crop simple)
                $aspect_ratio = $width / $height;
                if ($aspect_ratio >= 1) {
                    // Más ancha que alta
                    $src_h = $height;
                    $src_w = $height;
                    $src_x = ($width - $height) / 2;
                    $src_y = 0;
                } else {
                    // Más alta que ancha
                    $src_w = $width;
                    $src_h = $width;
                    $src_x = 0;
                    $src_y = ($height - $width) / 2;
                }

                imagecopyresampled($thumb, $source, 0, 0, $src_x, $src_y, $new_width, $new_height, $src_w, $src_h);

                // Guardar imagen procesada
                $saved = false;
                switch($file_ext) {
                    case 'jpeg': case 'jpg': $saved = imagejpeg($thumb, $destination, 90); break;
                    case 'png': $saved = imagepng($thumb, $destination, 9); break;
                    case 'gif': $saved = imagegif($thumb, $destination); break;
                }

                if (!$saved) {
                    throw new Exception("No se pudo guardar la imagen en el servidor. Verifique permisos.");
                }
                
                imagedestroy($thumb);
                imagedestroy($source);

                // Agregar a la consulta SQL
                $foto_sql = ", foto = ?";
                $params[] = $new_name;
                
                // Borrar foto anterior del servidor si existe y no es la default
                if ($foto_anterior_db && $foto_anterior_db !== 'default.png') {
                    $ruta_foto_anterior = $upload_dir . $foto_anterior_db;
                    if (file_exists($ruta_foto_anterior)) {
                        @unlink($ruta_foto_anterior);
                    }
                }

                // Actualizar sesión inmediatamente
                $_SESSION['user_photo'] = $new_name;

            } else {
                throw new Exception("Formato de imagen no válido. Solo JPG, PNG o GIF.");
            }
        }

        // 2. Actualizar Base de Datos
        $params[] = $user_id; // ID para el WHERE
        $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ?, direccion = ? $foto_sql WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Actualizar datos de sesión
        $_SESSION['user_name'] = $nombre;
        $_SESSION['user_lastname'] = $apellido;

        $mensaje = "¡Perfil actualizado correctamente!";
        $tipo_alerta = "success";

    } catch (Exception $e) {
        $mensaje = "Error al actualizar: " . $e->getMessage();
        $tipo_alerta = "danger";
    }
}

// --- Obtener Datos Actuales ---
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - SAMPATV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .profile-img-container {
            width: 150px; height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 5px solid #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            position: relative;
            background-color: #e9ecef; /* Fondo gris por si la imagen falla */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-img-container img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .btn-upload {
            position: absolute; bottom: 0; width: 100%;
            background: rgba(0,0,0,0.6); color: white;
            text-align: center; padding: 5px; cursor: pointer;
            font-size: 0.8rem; transition: 0.3s;
            z-index: 20; /* Asegurar que esté por encima de la imagen */
        }
        .btn-upload:hover { background: rgba(13, 110, 253, 0.8); }
    </style>
</head>
<body>
    <?php $base_path = '../'; include 'sidebar.php'; ?>

    <div class="container pt-5 pt-lg-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?php echo $tipo_alerta; ?> alert-dismissible fade show" role="alert">
                        <?php echo $mensaje; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fa-solid fa-user-pen me-2"></i>Editar Mi Perfil</h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="perfil.php" method="POST" enctype="multipart/form-data">
                            
                            <!-- Foto de Perfil -->
                            <div class="text-center mb-4">
                                <div class="profile-img-container">
                                    <?php 
                                        $foto_actual = !empty($usuario['foto']) ? $usuario['foto'] : 'default.png';
                                        $ruta_fisica = __DIR__ . '/../img/usuarios/' . $foto_actual;
                                        $url_foto = '../img/usuarios/' . $foto_actual;
                                        
                                        // Si el archivo no existe físicamente, usar avatar generado
                                        if (!file_exists($ruta_fisica)) {
                                            $url_foto = "https://ui-avatars.com/api/?name=" . urlencode($usuario['nombre']) . "&background=random&color=fff&size=150";
                                        }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($url_foto); ?>" id="preview" alt="Foto de perfil" onerror="this.src='https://ui-avatars.com/api/?name=User&background=random&size=150'">
                                    <label for="foto_input" class="btn-upload">
                                        <i class="fa-solid fa-camera"></i> Cambiar
                                    </label>
                                </div>
                                <input type="file" name="foto" id="foto_input" class="d-none" accept="image/*" onchange="previewImage(this)">
                                <small class="text-muted">Click en "Cambiar" para subir una nueva foto.</small>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Nombre</label>
                                    <input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Apellido</label>
                                    <input type="text" class="form-control" name="apellido" value="<?php echo htmlspecialchars($usuario['apellido'] ?? ''); ?>" placeholder="Completa tu apellido" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Teléfono</label>
                                    <input type="text" class="form-control" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" placeholder="Ej: +58 412 1234567" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email (No editable)</label>
                                    <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Dirección</label>
                                    <textarea class="form-control" name="direccion" rows="2" placeholder="Dirección completa de habitación u oficina" required><?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-save me-2"></i>Guardar Cambios</button>
                                <a href="../panel_control.php" class="btn btn-outline-secondary">Volver al Panel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>