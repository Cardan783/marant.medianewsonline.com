<?php
// Determinar ruta base si no está definida (para archivos en subcarpetas)
if (!isset($base_path)) {
    $base_path = '';
}
$current_page = basename($_SERVER['PHP_SELF']);

// Obtener foto de sesión o usar default
$user_photo = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : 'default.png';

// Lógica de visualización segura para el sidebar
$ruta_img_sidebar = $base_path . 'img/usuarios/' . $user_photo;
// Verificamos si es un archivo local y si existe (esto requiere ruta absoluta del sistema de archivos, 
// pero para simplificar en vista web usaremos el onerror en la etiqueta img)
$url_avatar_default = "https://ui-avatars.com/api/?name=" . urlencode($_SESSION['user_name'] ?? 'U') . "&background=random&color=fff";
?>
<style>
    /* --- Estilos Globales de Tema Personalizado --- */
    :root {
        --bs-primary: #0d6efd; /* Valor por defecto */
        --bs-primary-rgb: 13, 110, 253;
        --placeholder-color: #6c757d;
    }
    
    /* Sobrescribir componentes de Bootstrap para usar la variable dinámica */
    .btn-primary {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
    }
    .btn-outline-primary {
        color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
    }
    .btn-outline-primary:hover {
        background-color: var(--bs-primary) !important;
        color: #fff !important;
    }
    .text-primary {
        color: var(--bs-primary) !important;
    }
    .bg-primary {
        background-color: var(--bs-primary) !important;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25) !important;
    }
    ::placeholder {
        color: var(--placeholder-color) !important;
        opacity: 0.7;
    }

    /* --- Estilos del Sidebar Global --- */
    .sidebar-global {
        width: 280px;
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1040;
        overflow-y: auto;
        transition: transform 0.3s ease-in-out;
        box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    }
    
    /* Ajuste para contenido principal cuando el sidebar está visible en desktop */
    @media (min-width: 992px) {
        body {
            padding-left: 280px; /* Empuja el contenido a la derecha */
        }
        .sidebar-global {
            transform: none !important;
            visibility: visible !important;
        }
        /* Ocultar botón de toggle en desktop */
        .mobile-nav-toggle {
            display: none !important;
        }
    }

    /* En móviles, el sidebar se comporta como offcanvas */
    @media (max-width: 991.98px) {
        .sidebar-global {
            transform: translateX(-100%);
        }
        .sidebar-global.show {
            transform: translateX(0);
        }
        body {
            padding-left: 0;
        }
    }

    /* Estilo activo para los enlaces */
    .sidebar-global .nav-link.active {
        background-color: var(--bs-primary) !important; /* Azul Bootstrap / Personalizado */
        color: white !important;
    }
    
    /* Botón flotante para móviles (Pestaña para maximizar) */
    .mobile-nav-toggle {
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1050;
        padding: 0;
        border-radius: 50%;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        border: 2px solid white;
    }
</style>

<!-- Sidebar Offcanvas -->
<div class="offcanvas-lg offcanvas-start bg-dark text-white sidebar-global" tabindex="-1" id="globalSidebar" aria-labelledby="globalSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title" id="globalSidebarLabel"><i class="fa-solid fa-chart-line me-2"></i>SAMPATV</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#globalSidebar" aria-label="Close"></button>
    </div>
    <div class="d-flex flex-column p-3 h-100">
        <a href="<?php echo $base_path; ?>panel_control.php" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-white text-decoration-none d-none d-lg-flex">
            <i class="fa-solid fa-chart-line me-2 fs-4"></i>
            <span class="fs-4">SAMPATV</span>
        </a>
        <hr class="d-none d-lg-block border-secondary">
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item mb-1">
                <a href="<?php echo $base_path; ?>panel_control.php" class="nav-link text-white <?php echo $current_page == 'panel_control.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house me-2" style="width: 20px;"></i> Inicio
                </a>
            </li>
            <li class="mb-1">
                <a href="<?php echo $base_path; ?>panel_general.php" class="nav-link text-white <?php echo $current_page == 'panel_general.php' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-fill me-2" style="width: 20px;"></i> Panel General
                </a>
            </li>
            <li class="mb-1">
                <a href="<?php echo $base_path; ?>Graficas.php" class="nav-link text-white <?php echo $current_page == 'Graficas.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-area me-2" style="width: 20px;"></i> Gráficas
                </a>
            </li>
            <li class="mb-1">
                <a href="<?php echo $base_path; ?>tabla.php" class="nav-link text-white <?php echo $current_page == 'tabla.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-table me-2" style="width: 20px;"></i> Tablas
                </a>
            </li>
            <li class="mb-1">
                <a href="<?php echo $base_path; ?>analisis_operacional.php" class="nav-link text-white <?php echo $current_page == 'analisis_operacional.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-sd-card me-2" style="width: 20px;"></i> Análisis operacional diario
                </a>
            </li>
            <li class="mb-1">
                <a href="<?php echo $base_path; ?>php/configuracion.php" class="nav-link text-white <?php echo $current_page == 'configuracion.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gear me-2" style="width: 20px;"></i> Configuración
                </a>
            </li>
            <li class="mb-1">
                <a href="#" class="nav-link text-white darkModeToggleBtn">
                    <i class="fa-solid fa-moon me-2" style="width: 20px;"></i> Modo Oscuro
                </a>
            </li>
        </ul>
        <hr class="border-secondary">
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo htmlspecialchars($ruta_img_sidebar); ?>" onerror="this.src='<?php echo $url_avatar_default; ?>'" alt="" width="32" height="32" class="rounded-circle me-2" style="object-fit: cover; border: 2px solid rgba(255,255,255,0.2); background-color: #fff;">
                <div>
                    <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></strong>
                    <?php if(isset($_SESSION['user_lastname'])): ?>
                        <small class="d-block text-muted" style="font-size: 0.75rem; line-height: 1;"><?php echo htmlspecialchars($_SESSION['user_lastname']); ?></small>
                    <?php endif; ?>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="<?php echo $base_path; ?>php/perfil.php"><i class="fa-solid fa-user-gear me-2"></i>Mi Perfil</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo $base_path; ?>php/logout.php" onclick="confirmLogout(event)"><i class="fa-solid fa-right-from-bracket me-2"></i>Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtns = document.querySelectorAll('.darkModeToggleBtn');
    const body = document.body;

    // --- Lógica Global de Colores Personalizados ---
    const applyCustomColors = () => {
        // 1. Color del Tema (Primario)
        const savedColor = localStorage.getItem('themeColor');
        if (savedColor) {
            try {
                const color = JSON.parse(savedColor);
                if (color.hex && color.rgb) {
                    document.documentElement.style.setProperty('--bs-primary', color.hex);
                    document.documentElement.style.setProperty('--bs-primary-rgb', color.rgb);
                }
            } catch (e) { console.error("Error parsing themeColor", e); }
        }

        // 2. Color del Placeholder
        const savedPlaceholder = localStorage.getItem('placeholderColor');
        if (savedPlaceholder) {
            try {
                const color = JSON.parse(savedPlaceholder);
                if (color.hex) {
                    document.documentElement.style.setProperty('--placeholder-color', color.hex);
                }
            } catch (e) { console.error("Error parsing placeholderColor", e); }
        }
    };
    applyCustomColors();

    const applyTheme = (isDark) => {
        if (isDark) {
            body.classList.add('dark-mode');
            toggleBtns.forEach(btn => {
                btn.innerHTML = '<i class="fa-solid fa-sun me-2" style="width: 20px;"></i>' + (btn.tagName === 'A' ? 'Modo Claro' : '');
                if(btn.tagName === 'BUTTON') {
                    btn.style.backgroundColor = '#333';
                    btn.style.color = '#fff';
                    btn.style.borderColor = '#555';
                }
            });
        } else {
            body.classList.remove('dark-mode');
            toggleBtns.forEach(btn => {
                btn.innerHTML = '<i class="fa-solid fa-moon me-2" style="width: 20px;"></i>' + (btn.tagName === 'A' ? 'Modo Oscuro' : '');
                if(btn.tagName === 'BUTTON') {
                    btn.style.backgroundColor = 'white';
                    btn.style.color = '';
                    btn.style.borderColor = '#dee2e6';
                }
            });
        }
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        // Disparar evento para que gráficas y tablas se actualicen
        window.dispatchEvent(new Event('themeChanged'));
    };

    // Cargar tema guardado
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') applyTheme(true);

    // Event Listeners para todos los botones de toggle (Desktop y Móvil)
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            applyTheme(!body.classList.contains('dark-mode'));
        });
    });
});
</script>