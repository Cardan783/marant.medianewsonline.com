<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Cabeceras para evitar que el navegador guarde la página en caché (Seguridad botón Atrás)
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Incluir conexión a la base de datos
require_once 'php/conexion.php';

$user_id = $_SESSION['user_id'];

// 1. Obtener todos los equipos del usuario
$stmt = $conn->prepare("SELECT id, nombre_equipo, mac_address FROM equipos WHERE usuario_id = ? ORDER BY nombre_equipo ASC");
$stmt->execute([$user_id]);
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Recorrer equipos para buscar sus últimos datos y configuración de alarmas
foreach ($equipos as &$equipo) {
    // Obtener última lectura de sensores
    $stmt_s = $conn->prepare("SELECT temperatura, presion, voltaje, created_at FROM sensores WHERE equipo_id = ? ORDER BY id DESC LIMIT 1");
    $stmt_s->execute([$equipo['id']]);
    $equipo['datos'] = $stmt_s->fetch(PDO::FETCH_ASSOC);

    // Obtener umbrales de alarma
    $stmt_a = $conn->prepare("SELECT Temp_advertencia, Temperatura as Temp_critica, Presion as Presion_min, Voltaje_Max, Voltaje_Min FROM alarmas WHERE equipo_id = ? LIMIT 1");
    $stmt_a->execute([$equipo['id']]);
    $equipo['alarmas'] = $stmt_a->fetch(PDO::FETCH_ASSOC);
}
unset($equipo); // Romper referencia del foreach
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel General - Mis Equipos</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        

        /* Estilos de las Tarjetas */
        .equipment-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        .equipment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        .card-header {
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .data-row:last-child { border-bottom: none; }
        .data-value { font-weight: bold; font-size: 1.1rem; }
        .last-update { font-size: 0.8rem; color: #6c757d; text-align: right; margin-top: 10px; }

        /* Estados de Alarma */
        .status-normal { border-top: 5px solid #198754; }
        .status-warning { border-top: 5px solid #ffc107; }
        .status-danger { border-top: 5px solid #dc3545; }
        
        .bg-status-normal { background-color: #198754; color: white; }
        .bg-status-warning { background-color: #ffc107; color: black; }
        .bg-status-danger { background-color: #dc3545; color: white; }

        /* --- Estilos Modo Oscuro --- */
        body.dark-mode { background-color: #121212; color: #e0e0e0; }
        body.dark-mode .text-dark { color: #e0e0e0 !important; } /* Título */
        body.dark-mode .equipment-card { background-color: #1e1e1e; color: #e0e0e0; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        body.dark-mode .equipment-card:hover { background-color: #252525; box-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        body.dark-mode .data-row { border-bottom-color: #333; }
        body.dark-mode .text-secondary { color: #a0a0a0 !important; }
        body.dark-mode .last-update { color: #888; }
        body.dark-mode .btn-outline-primary { color: #6ea8fe; border-color: #6ea8fe; }
        body.dark-mode .btn-outline-primary:hover { background-color: #6ea8fe; color: #000; }
        body.dark-mode .btn-outline-secondary { color: #adb5bd; border-color: #adb5bd; }
        body.dark-mode .btn-outline-secondary:hover { background-color: #adb5bd; color: #000; }
        body.dark-mode .card-header.bg-secondary { background-color: #495057 !important; }

        /* Animación de Parpadeo para Alertas */
        @keyframes blink-red { 50% { opacity: 0.5; } }
        .blink-active { animation: blink-red 1s infinite; }
        .text-danger-custom { color: #dc3545 !important; font-weight: bold; }
        .card-danger-glow { box-shadow: 0 0 15px rgba(220, 53, 69, 0.6) !important; border: 1px solid #dc3545 !important; }
    </style>
</head>
<body>

    <?php $base_path = ''; include 'php/sidebar.php'; include 'php/navbar.php'; ?>

    <div class="container pt-5 pt-lg-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark"><i class="bi bi-speedometer2 me-2"></i>Estado de la Flota</h2>
            <div class="d-flex align-items-center gap-2">
                <!-- Selector de Modo -->
                <div class="btn-group me-2" role="group" aria-label="Modo de datos">
                    <input type="radio" class="btn-check" name="modoDatos" id="modoDB" autocomplete="off" checked onchange="cambiarModo('db')">
                    <label class="btn btn-outline-primary" for="modoDB"><i class="bi bi-database me-1"></i>Periódico (BD)</label>

                    <input type="radio" class="btn-check" name="modoDatos" id="modoLive" autocomplete="off" onchange="cambiarModo('live')">
                    <label class="btn btn-outline-danger" for="modoLive"><i class="bi bi-lightning-charge me-1"></i>En Vivo</label>
                </div>

                <button id="btnMute" class="btn btn-outline-secondary" onclick="toggleMute()"><i class="fa-solid fa-volume-high"></i></button>
                <button id="btnActualizarManual" onclick="actualizarPanel()" class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>

        <div id="main-content-area">
        <?php if (count($equipos) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach ($equipos as $eq): 
                    // Determinar estado
                    $temp = $eq['datos']['temperatura'] ?? null;
                    $pres = $eq['datos']['presion'] ?? null;
                    $volt = $eq['datos']['voltaje'] ?? null;

                    $crit = $eq['alarmas']['Temp_critica'] ?? 90;
                    $adv = $eq['alarmas']['Temp_advertencia'] ?? 85;
                    $min_pres = $eq['alarmas']['Presion_min'] ?? 0;
                    $max_volt = $eq['alarmas']['Voltaje_Max'] ?? 0;
                    $min_volt = $eq['alarmas']['Voltaje_Min'] ?? 0;
                    
                    $statusClass = 'status-normal';
                    $headerClass = 'bg-status-normal';
                    $icon = 'bi-check-circle-fill';
                    $estadoTexto = 'Normal';
                    $hasCritical = false;
                    $hasWarning = false;

                    // Clases para valores individuales
                    $classTemp = '';
                    $classPres = '';
                    $classVolt = '';

                    if ($temp !== null) {
                        if ($temp >= $crit) {
                            $hasCritical = true;
                            $classTemp = 'text-danger-custom blink-active';
                        } elseif ($temp >= $adv) {
                            $hasWarning = true;
                            $classTemp = 'text-warning fw-bold';
                        }
                    }

                    if ($pres !== null && $min_pres > 0) {
                        if ($pres < $min_pres) {
                            $hasCritical = true;
                            $classPres = 'text-danger-custom blink-active';
                        }
                    }

                    if ($volt !== null && ($max_volt > 0 || $min_volt > 0)) {
                        if (($max_volt > 0 && $volt > $max_volt) || ($min_volt > 0 && $volt < $min_volt)) {
                            $hasCritical = true;
                            $classVolt = 'text-danger-custom blink-active';
                        }
                    }

                    if ($hasCritical) {
                        $statusClass = 'status-danger card-danger-glow';
                        $headerClass = 'bg-status-danger';
                        $icon = 'bi-exclamation-octagon-fill';
                        $estadoTexto = 'Crítico';
                    } elseif ($hasWarning) {
                        $statusClass = 'status-warning';
                        $headerClass = 'bg-status-warning';
                        $icon = 'bi-exclamation-triangle-fill';
                        $estadoTexto = 'Alerta';
                    } elseif ($temp === null) {
                        $statusClass = 'border-secondary'; // Sin datos
                        $headerClass = 'bg-secondary';
                        $icon = 'bi-question-circle-fill';
                        $estadoTexto = 'Sin Conexión';
                    }

                    // PREPARAR ATRIBUTOS DE DATOS PARA JS (MODO EMERGENCIA)
                    $dataAttrs = "data-mac='" . htmlspecialchars($eq['mac_address']) . "' " .
                                 "data-crit-temp='" . $crit . "' " .
                                 "data-adv-temp='" . $adv . "' " .
                                 "data-min-pres='" . $min_pres . "' " .
                                 "data-max-volt='" . $max_volt . "' " .
                                 "data-min-volt='" . $min_volt . "'";
                ?>
                <div class="col">
                    <div class="card equipment-card h-100 shadow-sm <?php echo $statusClass; ?>" <?php echo $dataAttrs; ?>>
                        <div class="card-header <?php echo $headerClass; ?>">
                            <span><?php echo htmlspecialchars($eq['nombre_equipo']); ?></span>
                            <i class="bi <?php echo $icon; ?>"></i>
                        </div>
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3 text-primary small"><i class="bi bi-cpu me-1"></i>MAC: <?php echo htmlspecialchars($eq['mac_address']); ?></h6>
                            
                            <div class="data-row">
                                <span class="text-secondary"><i class="fa-solid fa-temperature-half me-2"></i>Temperatura</span>
                                <span class="data-value val-temp <?php echo $classTemp; ?>"><?php echo $temp !== null ? number_format($temp, 1) . ' °C' : '--'; ?></span>
                            </div>
                            <div class="data-row">
                                <span class="text-secondary"><i class="fa-solid fa-gauge me-2"></i>Presión</span>
                                <span class="data-value val-pres <?php echo $classPres; ?>"><?php echo isset($eq['datos']['presion']) ? number_format($eq['datos']['presion'], 1) . ' hPa' : '--'; ?></span>
                            </div>
                            <div class="data-row">
                                <span class="text-secondary"><i class="fa-solid fa-bolt me-2"></i>Voltaje</span>
                                <span class="data-value val-volt <?php echo $classVolt; ?>"><?php echo isset($eq['datos']['voltaje']) ? number_format($eq['datos']['voltaje'], 2) . ' V' : '--'; ?></span>
                            </div>

                            <div class="last-update">
                                <i class="bi bi-clock-history me-1"></i> <?php echo isset($eq['datos']['created_at']) ? date('d/m/Y H:i', strtotime($eq['datos']['created_at'])) : 'Sin registros'; ?>
                            </div>
                            
                            <div class="d-grid mt-3">
                                <a href="Graficas.php?equipo_id=<?php echo $eq['id']; ?>" class="btn btn-outline-primary btn-sm">Ver Gráfica Detallada</a>
                                <a href="tabla.php?equipo_id=<?php echo $eq['id']; ?>" class="btn btn-outline-secondary btn-sm mt-2">Ver Historial de Datos</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h4><i class="bi bi-info-circle me-2"></i>No tienes equipos registrados.</h4>
                <p>Ve a la sección de <a href="php/configuracion.php">Configuración</a> para agregar tu primer equipo.</p>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        console.log(">>> Panel General JS cargado");
        let intervaloPanel = null;
        let intervaloTiempoReal = null;
        let modoActual = 'db'; // 'db' o 'live'

        // --- Lógica de Audio ---
        const audioCritico = new Audio('http://marant.medianewsonline.com/Sonidos/bip-temp-critica.wav');
        const audioAdvertencia = new Audio('http://marant.medianewsonline.com/Sonidos/alarm-door-chime.wav');
        let isMuted = localStorage.getItem('isMuted') === 'true';

        function toggleMute() {
            isMuted = !isMuted;
            localStorage.setItem('isMuted', isMuted);
            updateMuteUI();
            if(isMuted) {
                audioCritico.pause();
                audioAdvertencia.pause();
                audioCritico.currentTime = 0;
                audioAdvertencia.currentTime = 0;
            }
            // Verificar estado visual del botón inmediatamente
            verificarAlarmas();
        }

        function updateMuteUI() {
            const btn = document.getElementById('btnMute');
            if(isMuted) {
                btn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
                btn.classList.replace('btn-outline-secondary', 'btn-danger');
            } else {
                btn.innerHTML = '<i class="fa-solid fa-volume-high"></i>';
                btn.classList.replace('btn-danger', 'btn-outline-secondary');
            }
        }
        updateMuteUI();

        function verificarAlarmas() {
            console.log(">>> Verificando alarmas...");
            const hayPeligro = document.querySelector('.status-danger');
            const hayAdvertencia = document.querySelector('.status-warning');
            const btn = document.getElementById('btnMute');
            
            // 1. Actualizar UI del botón Mute
            if (isMuted) {
                if (hayPeligro || hayAdvertencia) {
                    btn.innerHTML = '<i class="fa-solid fa-bell-slash fa-shake"></i>';
                } else {
                    btn.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
                }
            } else {
                btn.innerHTML = '<i class="fa-solid fa-volume-high"></i>';
            }
            
            // 3. Lógica de Audio (Solo si no está muteado)
            if (!isMuted) {
                if (hayPeligro) {
                    audioCritico.play().catch(e => console.log("Audio play blocked", e));
                } else if (hayAdvertencia) {
                    audioAdvertencia.play().catch(e => console.log("Audio play blocked", e));
                }
            }
        }

        // --- LECTURA CONTINUA DE DATOS JSON (TIEMPO REAL) ---
        async function leerDatosTiempoReal() {
            const cards = document.querySelectorAll('.equipment-card');

            for (const card of cards) {
                const mac = card.dataset.mac ? card.dataset.mac.trim() : null;
                if (!mac) continue;

                // Leer umbrales guardados en el HTML
                const critTemp = parseFloat(card.dataset.critTemp);
                const advTemp = parseFloat(card.dataset.advTemp);
                const minPres = parseFloat(card.dataset.minPres);
                const maxVolt = parseFloat(card.dataset.maxVolt);
                const minVolt = parseFloat(card.dataset.minVolt);

                // CAMBIO: Usar el intermediario PHP para evitar errores 404 en consola
                const url = `php/leer_ajax.php?mac=${encodeURIComponent(mac)}&_=${Date.now()}`;

                try {
                    const response = await fetch(url);
                    
                    if (!response.ok) { 
                        continue;
                    }
                    const data = await response.json();
                    
                    // Si el PHP nos dice que está offline o hay error, limpiamos y seguimos
                    if (data.status === 'offline' || data.error) {
                        const badge = card.querySelector('.badge-live');
                        if(badge) badge.remove();
                        continue;
                    }
                    
                    // CORRECCIÓN: Usar las claves exactas que genera ajax.php (temp, pres, volt)
                    const temp = parseFloat(data.temp);
                    const pres = parseFloat(data.pres);
                    const volt = parseFloat(data.volt);

                    if (isNaN(temp)) continue;

                    // Actualizar valores en la tarjeta (Buscamos por orden en data-row)
                    // MEJORA: Usar selectores específicos por clase para evitar errores de orden
                    const elTemp = card.querySelector('.val-temp');
                    const elPres = card.querySelector('.val-pres');
                    const elVolt = card.querySelector('.val-volt');

                    if(elTemp && elPres && elVolt) {
                        elTemp.textContent = `${temp.toFixed(1)} °C`;
                        elPres.textContent = `${pres.toFixed(1)} hPa`;
                        elVolt.textContent = `${volt.toFixed(2)} V`;

                        // Agregar indicador visual de "En Vivo"
                        const header = card.querySelector('.card-header');
                        if (header && !header.querySelector('.badge-live')) {
                            const span = document.createElement('span');
                            span.className = 'badge bg-success ms-2 badge-live';
                            span.style.fontSize = '0.7em';
                            span.innerHTML = '<i class="fa-solid fa-bolt"></i> LIVE';
                            header.appendChild(span);
                        }
                        
                        // Resetear clases visuales de valores
                        [elTemp, elPres, elVolt].forEach(v => v.className = v.className.replace(/\b(text-danger-custom|blink-active|text-warning|fw-bold)\b/g, '').trim());

                        let isCritical = false;
                        let isWarning = false;

                        // Evaluar Temperatura
                        if (temp >= critTemp) { isCritical = true; elTemp.classList.add('text-danger-custom', 'blink-active'); }
                        else if (temp >= advTemp) { isWarning = true; elTemp.classList.add('text-warning', 'fw-bold'); }

                        // Evaluar Presión
                        if (minPres > 0 && pres < minPres) { isCritical = true; elPres.classList.add('text-danger-custom', 'blink-active'); }

                        // Evaluar Voltaje
                        if ((maxVolt > 0 && volt > maxVolt) || (minVolt > 0 && volt < minVolt)) { isCritical = true; elVolt.classList.add('text-danger-custom', 'blink-active'); }

                        // Actualizar estado general de la tarjeta
                        actualizarEstadoTarjeta(card, isCritical, isWarning);
                    }
                } catch (e) { console.error("Error leyendo JSON:", e); }
            }

            // Verificar alarmas después de actualizar con datos frescos
            verificarAlarmas();
        }

        // --- CONTROL DE MODOS ---
        function cambiarModo(modo) {
            if (modo === 'live') {
                Swal.fire({
                    title: '¿Activar Modo En Vivo?',
                    text: "Esta opción consume más datos debido a la actualización constante. ¿Desea continuar?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, activar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        ejecutarCambioModo('live');
                    } else {
                        document.getElementById('modoDB').checked = true;
                    }
                });
            } else {
                ejecutarCambioModo('db');
            }
        }

        function ejecutarCambioModo(modo) {
            modoActual = modo;
            console.log(`>>> Cambiando a modo: ${modo}`);

            // Limpiar ambos intervalos primero
            if (intervaloPanel) clearInterval(intervaloPanel);
            if (intervaloTiempoReal) clearInterval(intervaloTiempoReal);

            if (modo === 'live') {
                // MODO EN VIVO: Solo lee JSON (rápido)
                leerDatosTiempoReal(); // Ejecutar inmediatamente
                intervaloTiempoReal = setInterval(leerDatosTiempoReal, 2000);
                
                // Deshabilitar botón manual de BD para evitar confusión
                document.getElementById('btnActualizarManual').disabled = true;
            } else {
                // MODO BD: Solo lee Base de Datos (lento)
                // Limpiar badges de "LIVE" visualmente
                document.querySelectorAll('.badge-live').forEach(el => el.remove());
                
                actualizarPanel(); // Ejecutar inmediatamente
                intervaloPanel = setInterval(actualizarPanel, 5000);
                document.getElementById('btnActualizarManual').disabled = false;
            }
        }

        function actualizarEstadoTarjeta(card, isCritical, isWarning) {
            const header = card.querySelector('.card-header');
            const icon = header.querySelector('i');
            
            card.className = 'card equipment-card h-100 shadow-sm'; // Reset base
            header.className = 'card-header';
            icon.className = 'bi';

            if (isCritical) {
                card.classList.add('status-danger', 'card-danger-glow');
                header.classList.add('bg-status-danger');
                icon.classList.add('bi-exclamation-octagon-fill');
            } else if (isWarning) {
                card.classList.add('status-warning');
                header.classList.add('bg-status-warning');
                icon.classList.add('bi-exclamation-triangle-fill');
            } else {
                card.classList.add('status-normal');
                header.classList.add('bg-status-normal');
                icon.classList.add('bi-check-circle-fill');
            }
        }

        // Función para actualizar el contenido sin recargar la página (evita flash)
        function actualizarPanel() {
            // Añadir timestamp para evitar caché del navegador
            const url = window.location.href.split('#')[0] + (window.location.href.includes('?') ? '&' : '?') + '_=' + new Date().getTime();
            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContent = doc.getElementById('main-content-area');
                    if (newContent) {
                        document.getElementById('main-content-area').innerHTML = newContent.innerHTML;
                        verificarAlarmas(); // Verificar alarmas tras actualizar
                    }
                })
                .catch(err => console.error('Error al actualizar panel:', err));
        }

        // INICIALIZACIÓN
        // Arrancar en modo BD por defecto (coincide con el radio button checked)
        ejecutarCambioModo('db');
        setTimeout(verificarAlarmas, 1000);
    </script>
</body>
</html>