<?php
// Iniciar sesión para manejo futuro de usuarios
session_start();

// --- Lógica de "Recordarme" (Auto-login) ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    require_once 'php/conexion.php';
    $token_cookie = $_COOKIE['remember_me'];
    // Hash del token para comparar con BD
    $token_hash = hash('sha256', $token_cookie);
    
    $stmt = $conn->prepare("SELECT id, nombre, apellido, foto, estado FROM usuarios WHERE remember_token = ? AND estado = 'activo'");
    $stmt->execute([$token_hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Restaurar sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nombre'];
        $_SESSION['user_lastname'] = $user['apellido'];
        $_SESSION['user_photo'] = !empty($user['foto']) ? $user['foto'] : 'default.png';
        $_SESSION['just_logged_in'] = true;
        header("Location: panel_control.php");
        exit();
    }
}

if (isset($_SESSION['user_id'])) {
    header("Location: panel_control.php");
    exit();
}

// --- Lógica para cargar imágenes del carrusel ---
$ruta_carrusel = 'img/carrusel/';
$imagenes_carrusel = [];

if (is_dir($ruta_carrusel)) {
    $archivos = scandir($ruta_carrusel);
    foreach ($archivos as $archivo) {
        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $imagenes_carrusel[] = $ruta_carrusel . $archivo;
        }
    }
}
// Si no hay imágenes locales, usar las de Unsplash por defecto
$usar_local = count($imagenes_carrusel) > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMPATV - Monitor de Sensores</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        /* Estilos para el Carrusel Hero */
        .hero-slide {
            background-size: cover;
            background-position: center;
            height: 100vh;
        }
        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .section-padding {
            padding: 80px 0;
        }
    </style>
</head>
<body>

    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fa-solid fa-chart-line me-2"></i> SAMPATV
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link active" href="#inicio">Inicio</a></li>
                    <li class="nav-item"><a class="nav-link" href="#nosotros">Quiénes Somos</a></li>
                    <li class="nav-item"><a class="nav-link" href="#servicios">Servicios</a></li>
                    <li class="nav-item ms-lg-3">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fa-solid fa-user me-2"></i> Iniciar Sesión
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section (Carrusel) -->
    <section id="inicio" class="p-0">
        <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="3" aria-label="Slide 4"></button>
            </div>
            <div class="carousel-inner">
                <!-- Slide 1 -->
                <div class="carousel-item active hero-slide" style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?php echo $usar_local ? ($imagenes_carrusel[0] ?? $imagenes_carrusel[0]) : 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80'; ?>');">
                    <div class="container h-100 d-flex align-items-center justify-content-center text-center">
                        <!-- Ajuste de altura: margin-bottom positivo sube el texto, margin-top lo baja -->
                        <div class="text-white" style="margin-bottom: 10px;">
                            <h1 class="display-3 fw-bold mb-4">Monitoreo Inteligente en Tiempo Real</h1>
                            <p class="lead mb-5">Sistema Auxiliar de Medición de Presión, Temperatura y Voltaje para el transporte y la industria.</p>
                            <!-- Ajuste para bajar solo los botones: aumenta el valor de margin-top -->
                            <div class="d-flex justify-content-center gap-3" style="margin-top: 270px;">
                                <a href="#nosotros" class="btn btn-lg btn-outline-light">Conocer Más</a>
                                <button class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Acceder al Sistema</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Slide 2 -->
                <div class="carousel-item hero-slide" style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?php echo $usar_local ? ($imagenes_carrusel[1] ?? $imagenes_carrusel[0]) : 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80'; ?>');">
                    <div class="container h-100 d-flex align-items-center justify-content-center text-center">
                        <div class="text-white" style="margin-bottom: 10px;">
                            <h1 class="display-3 fw-bold mb-4">Protección Total para tus Activos</h1>
                            <p class="lead mb-5">Evita fallas críticas con alertas tempranas y reportes detallados.</p>
                            <div class="d-flex justify-content-center gap-3" style="margin-top: 270px;">
                                <a href="#servicios" class="btn btn-lg btn-outline-light">Nuestros Servicios</a>
                                <button class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Iniciar Ahora</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Slide 3 -->
                <div class="carousel-item hero-slide" style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?php echo $usar_local ? ($imagenes_carrusel[2] ?? $imagenes_carrusel[0]) : 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80'; ?>');">
                    <div class="container h-100 d-flex align-items-center justify-content-center text-center">
                        <div class="text-white" style="margin-bottom: 10px;">
                            <h1 class="display-3 fw-bold mb-4">Tecnología al Servicio de tu Flota</h1>
                            <p class="lead mb-5">Accede a gráficas y estadísticas desde cualquier lugar y dispositivo.</p>
                            <div class="d-flex justify-content-center gap-3" style="margin-top: 270px;">
                                <button class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Crear Cuenta</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Slide 4 -->
                <div class="carousel-item hero-slide" style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?php echo $usar_local ? ($imagenes_carrusel[3] ?? $imagenes_carrusel[0]) : 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80'; ?>');">
                    <div class="container h-100 d-flex align-items-center justify-content-center text-center">
                        <div class="text-white" style="margin-bottom: -350px;">
                            <h1 class="display-3 fw-bold mb-4">Innovación y Seguridad</h1>
                            <p class="lead mb-5">Soluciones avanzadas para el control total de tus operaciones.</p>
                            <div class="d-flex justify-content-center gap-3" style="margin-top: -30px;">
                                <button class="btn btn-lg btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Únete Ahora</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Siguiente</span>
            </button>
        </div>
    </section>

    <!-- Sección Nosotros -->
    <section id="nosotros" class="section-padding">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="mb-4 fw-bold text-primary">Quiénes Somos</h2>
                    <p class="lead">Marant Electronics C.A. es líder en soluciones de telemetría y protección de activos.</p>
                    <p>Nos dedicamos a brindar tranquilidad a nuestros clientes mediante sistemas avanzados de monitoreo. Entendemos que sus unidades de transporte no son solo vehículos, son su inversión y el sustento de sus familias.</p>
                    <ul class="list-unstyled mt-4">
                        <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i> Tecnología de punta</li>
                        <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i> Soporte 24/7</li>
                        <li class="mb-2"><i class="fa-solid fa-check-circle text-success me-2"></i> Integración personalizada</li>
                    </ul>
                </div>
                <div class="col-md-6 text-center">
                    <i class="fa-solid fa-server fa-10x text-secondary opacity-25"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección Servicios -->
    <section id="servicios" class="section-padding bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Nuestros Servicios</h2>
                <p class="text-muted">Soluciones integrales para el monitoreo de variables críticas.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="p-5 bg-white rounded shadow-sm h-100 hover-effect">
                        <i class="fa-solid fa-temperature-high feature-icon"></i>
                        <h4>Control de Temperatura</h4>
                        <p class="text-muted">Monitoreo preciso de la temperatura del motor para prevenir sobrecalentamientos y daños costosos.</p>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="p-5 bg-white rounded shadow-sm h-100 hover-effect">
                        <i class="fa-solid fa-gauge-high feature-icon"></i>
                        <h4>Presión de Aceite</h4>
                        <p class="text-muted">Vigilancia constante de la presión para asegurar la lubricación vital del motor en todo momento.</p>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="p-5 bg-white rounded shadow-sm h-100 hover-effect">
                        <i class="fa-solid fa-bolt feature-icon"></i>
                        <h4>Sistema Eléctrico</h4>
                        <p class="text-muted">Supervisión del voltaje y estado de la batería en tiempo real para evitar fallos eléctricos.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 text-center">
        <div class="container">
            <div class="mb-3">
                <a href="#" class="text-white me-3"><i class="fa-brands fa-facebook fa-lg"></i></a>
                <a href="#" class="text-white me-3"><i class="fa-brands fa-twitter fa-lg"></i></a>
                <a href="#" class="text-white"><i class="fa-brands fa-instagram fa-lg"></i></a>
            </div>
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Marant Electronics C.A. Todos los derechos reservados.</p>
            <!-- Enlace Admin Discreto -->
            <div class="mt-2">
                <a href="admin/login.php" class="text-white text-decoration-none" style="font-size: 0.8rem; opacity: 0.3;">Admin</a>
            </div>
        </div>
    </footer>

    <!-- Modal de Login / Registro -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">Acceso a SAMPATV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Navegación de pestañas -->
                    <ul class="nav nav-pills nav-fill mb-4" id="authTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button">
                                <i class="fa-solid fa-right-to-bracket me-2"></i>Ingresar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button">
                                <i class="fa-solid fa-user-plus me-2"></i>Crear Cuenta
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="authTabContent">
                        <!-- Formulario Login -->
                        <div class="tab-pane fade show active" id="login-pane">
                            <!-- Formulario real apuntando al login.php -->
                            <form action="php/login.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Usuario / Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="text" name="email" class="form-control" placeholder="nombre@ejemplo.com" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control" placeholder="******" required>
                                    </div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Recordarme en este dispositivo</label>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Ingresar</button>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="#" class="text-decoration-none small">¿Olvidaste tu contraseña?</a>
                                </div>
                            </form>
                        </div>

                        <!-- Formulario Registro -->
                        <div class="tab-pane fade" id="register-pane">
                            <form action="php/registro.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Nombre Completo</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                        <input type="text" name="nombre" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Código de Registro (Empresarial)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                                        <input type="text" name="codigo_registro" class="form-control" placeholder="Ingrese el código provisto por el administrador" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Identificación</label>
                                    <div class="input-group">
                                        <select class="form-select" name="tipo_identificacion" style="max-width: 90px;">
                                            <option value="V-">V-</option>
                                            <option value="E-">E-</option>
                                            <option value="J-">J-</option>
                                            <option value="G-">G-</option>
                                            <option value="DNI">DNI</option>
                                        </select>
                                        <input type="text" name="identificacion" class="form-control" placeholder="12345678" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirmar Contraseña</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success btn-lg">Registrarse</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Limpiar el estado del recordatorio de perfil al cargar la página de login
            sessionStorage.removeItem('hideProfileReminder');

            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const msg = urlParams.get('msg');

            if (status === 'register_error') {
                // Abrir modal
                const loginModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginModal'));
                loginModal.show();
                
                // Cambiar a pestaña de registro
                const registerTab = bootstrap.Tab.getOrCreateInstance(document.querySelector('#register-tab'));
                registerTab.show();

                // Mostrar error con SweetAlert2
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Registro',
                    text: msg,
                    confirmButtonColor: '#d33'
                });
            } else if (status === 'register_success') {
                const loginModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginModal'));
                loginModal.show();
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Registro Exitoso!',
                    text: 'Ahora puedes iniciar sesión.',
                    confirmButtonColor: '#0d6efd'
                });
            } else if (status === 'login_error') {
                // Abrir modal en la pestaña de login
                const loginModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginModal'));
                loginModal.show();

                // Mostrar error con SweetAlert2
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Inicio de Sesión',
                    text: msg,
                    confirmButtonColor: '#d33'
                });
            }
        });
    </script>
</body>
</html>