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

// --- Lógica para mensaje de bienvenida ---
$show_welcome_message = false;
$user_name_for_js = '';

if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $show_welcome_message = true;
    // Asumimos que 'user_name' se guarda en la sesión durante el login
    $user_name_for_js = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Usuario';
    // Desactivar la bandera para que no se muestre en cada recarga
    unset($_SESSION['just_logged_in']);
}
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gráfica de Sensores</title>

    <!-- Evitar error 404 de favicon usando un icono vacío -->
    <link rel="icon" href="data:," />
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css" />

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.4.0/dist/chartjs-plugin-annotation.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.21/jspdf.plugin.autotable.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <style>
      /* Estilos para el nuevo layout dashboard */
      .main-dashboard {
        display: flex;
        flex-direction: row;
        gap: 20px;
        padding: 10px;
        align-items: flex-start;
      }

      .chart-section {
        flex: 2;
        width: 100%;
        min-width: 0;
      }

      .stats-section {
        flex: 1;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
      }

      /* Asegura que el contenedor de la gráfica tenga una altura estable */
      .chart-container {
        position: relative;
        width: 100%;
        height: 400px;
      }

      /* Asegura que el canvas de la gráfica se ajuste al ancho disponible */
      #myChart {
        width: 100%;
        max-width: 100%;
      }

      @media (max-width: 900px) {
        .main-dashboard {
          flex-direction: column;
          gap: 5px;
        }
        .chart-container {
          height: 250px;
        }
      }

      /* Estilo para resaltar la gráfica en Modo Oscuro */
      body.dark-mode .chart-container {
        background-color: #1f2937; /* Fondo gris oscuro para resaltar */
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        border: 1px solid #374151;
      }

      .control-buttons-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
      }
    </style>
  </head>
  <body>
    <?php $base_path = ''; include 'php/sidebar.php'; ?>

    <div class="header-container pt-5 pt-lg-0 ps-3 pe-3">
      <h1>Monitor de Sensores en Tiempo Real</h1>
      <div class="control-buttons-group">
        <!-- Selector de MAC Address -->
        <div class="mac-selector-wrapper">
            <select id="selectMacAddress" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; font-weight: bold;">
                <option value="" disabled selected>-- Seleccionar Unidad --</option>
            </select>
        </div>

        <button id="btnReporteCritico" disabled>
          <i class="fa-solid fa-table"></i> Reporte General
        </button>
        <button id="btnReporteSoloCritico" disabled>
          <i class="fa-solid fa-triangle-exclamation"></i> Reporte Crítico
        </button>
        <button id="btnExportarPDF" disabled>
          <i class="fa-solid fa-file-pdf"></i> Exportar Gráfica
        </button>
        <button id="toggle-theme" class="theme-toggle-button">
          <i class="fa-solid fa-moon"></i> Tema Oscuro
        </button>
      </div>
    </div>

    <div id="contenedor-alerta" class="oculto"></div>

    <div class="filtro-container">
      <div class="filtro-group fecha-group">
        <label for="fecha_dia">Fecha:</label>
        <input type="date" id="fecha_dia" name="fecha_dia" />
      </div>

      <div class="filtro-group umbral-group">
        <label for="umbral_advertencia_temp">Umbral Advertencia:</label>
        <input
          type="number"
          id="umbral_advertencia_temp"
          name="umbral_advertencia_temp"
          min="10"
          max="125"
          step="0.5"
          value="85"
          placeholder="85"
          readonly
        />
        <label>°C</label>
      </div>

      <div class="filtro-group umbral-critico-group">
        <label for="umbral_critico_temp">Umbral Crítico:</label>
        <input
          type="number"
          id="umbral_critico_temp"
          name="umbral_critico_temp"
          min="10"
          max="125"
          step="0.5"
          value="90"
          placeholder="90"
          readonly
        />
        <label>°C</label>
      </div>

      <div class="filtro-group critica-group">
        <label for="temp_critica">Mostrar Pico Máximo</label>
        <input type="checkbox" id="temp_critica" name="temp_critica" />
      </div>
    </div>

    <div class="update-control-container">
      <div class="intervalo-group">
        <label for="selectIntervalo"
          ><i class="fa-solid fa-clock-rotate-left"></i> Intervalo de
          Actualización:</label
        >
        <select id="selectIntervalo">
          <option value="60000">1 minuto (por defecto)</option>
          <option value="5000">5 segundos</option>
          <option value="10000">10 segundos</option>
          <option value="30000">30 segundos</option>
          <option value="120000">2 minutos</option>
          <option value="300000">5 minutos</option>
          <option value="0">Deshabilitar</option>
        </select>
      </div>
      
      <div class="intervalo-group">
        <label for="selector-archivo-analisis"><i class="fa-solid fa-folder-open"></i> Análisis Histórico:</label>
        <select id="selector-archivo-analisis">
            <option value="">-- Seleccionar Archivo --</option>
        </select>
      </div>

      <div class="timer-display-group">
        <span id="update-timer-display">Próxima actualización en: --:--</span>
      </div>
    </div>
    <div class="main-dashboard">
      <div class="chart-section">
        <div class="chart-container">
          <canvas id="myChart"></canvas>
        </div>
      </div>

      <div class="stats-section">
        <!-- Fila 1: Temperatura -->
        <div class="stat-card temp-card" id="temp-stat-card">
          <h3><i class="fa-solid fa-temperature-half"></i> Temp. Actual</h3>
          <p>
            <span id="valor-temp-actual" class="valor-temp-alerta"
              >25.0 °C</span
            >
            <span id="temp-trend" class="trend-icon"></span>
          </p>
        </div>
        <div class="stat-card">
          <h3 title="Temperatura máxima registrada en el período seleccionado.">
            <i class="fa-solid fa-temperature-half"></i> Temp. Máx
          </h3>
          <p id="temp-max-rango">28.5 °C</p>
        </div>

        <!-- Fila 2: Presión -->
        <div class="stat-card presion-card">
          <h3><i class="fa-solid fa-gauge"></i> P. Actual</h3>
          <p>
            <span id="valor-presion-actual">1013.2 hPa</span>
            <span id="presion-trend" class="trend-icon"></span>
          </p>
        </div>
        <div class="stat-card">
          <h3 title="Presión mínima registrada en el período seleccionado.">
            <i class="fa-solid fa-gauge"></i> Presión Mín
          </h3>
          <p id="presion-min-rango">1010.5 hPa</p>
        </div>

        <!-- Fila 3: Voltaje -->
        <div class="stat-card voltaje-card">
          <h3><i class="fa-solid fa-bolt"></i> Voltaje Actual</h3>
          <p>
            <span id="valor-voltaje-actual">12.5 V</span>
            <span id="voltaje-trend" class="trend-icon"></span>
          </p>
        </div>
        <div class="stat-card">
          <h3
            title="Voltaje máximo y mínimo registrados en el período seleccionado."
          >
            <i class="fa-solid fa-bolt"></i> Máx/Mín
          </h3>
          <p id="voltaje-rango">13.0V/12.0V</p>
        </div>
      </div>
    </div>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        // --- SweetAlert2 para Bienvenida de Usuario ---
        const showWelcome = <?php echo json_encode($show_welcome_message); ?>;
        if (showWelcome) {
            const welcomePreference = localStorage.getItem('showWelcomeMessage');
            // Mostrar si la preferencia no existe (null) o está establecida en 'true'
            if (welcomePreference === null || welcomePreference === 'true') {
                const userName = <?php echo json_encode($user_name_for_js); ?>;
                Swal.fire({
                    title: `¡Bienvenido, ${userName}!`,
                    text: 'Has iniciado sesión correctamente en SAMPATV.',
                    icon: 'success',
                    timer: 5000, // Damos más tiempo para que el usuario pueda leer y decidir
                    timerProgressBar: true,
                    showConfirmButton: false,
                    showDenyButton: true, // Activamos el botón secundario
                    denyButtonText: 'No volver a mostrar', // Texto del botón
                    toast: true,
                    position: 'top-end',
                    // Adaptar al modo oscuro si está activo
                    background: document.body.classList.contains('dark-mode') ? '#1f2937' : '#fff',
                    color: document.body.classList.contains('dark-mode') ? '#e0e0e0' : '#000',
                    didOpen: (toast) => {
                        // Pausar el temporizador si el usuario pasa el mouse por encima
                        toast.addEventListener('mouseenter', Swal.stopTimer)
                        toast.addEventListener('mouseleave', Swal.resumeTimer)
                    }
                }).then((result) => {
                    // Si el usuario hace clic en "No volver a mostrar"
                    if (result.isDenied) {
                        localStorage.setItem('showWelcomeMessage', 'false');
                        // Feedback opcional (pequeño toast informativo)
                        Swal.fire({ icon: 'info', title: 'Bienvenida desactivada', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                    }
                });
            }
        }

        const selectMac = document.getElementById("selectMacAddress");
        
        // Obtener MAC actual de la URL o LocalStorage
        const urlParams = new URLSearchParams(window.location.search);
        const currentMac = urlParams.get('mac') || localStorage.getItem('selectedMac');
        
        // --- CARGAR EQUIPOS DEL USUARIO ---
        fetch('php/obtener_equipos.php')
            .then(response => {
                if (!response.ok) throw new Error("Error al cargar equipos");
                return response.json();
            })
            .then(data => {
                // Limpiar opciones anteriores (excepto la primera)
                selectMac.innerHTML = '<option value="" disabled selected>-- Seleccionar Unidad --</option>';
                
                data.forEach(equipo => {
                    const option = document.createElement('option');
                    // Usamos el ID como valor para futuras consultas a la BD
                    option.value = equipo.id; 
                    option.textContent = equipo.nombre_equipo;
                    selectMac.appendChild(option);
                });

                // Establecer valor seleccionado si existe
                if (currentMac) {
                    selectMac.value = currentMac;
                }

                // Una vez que los equipos están cargados y seleccionados, iniciamos la lógica de la gráfica
                if(typeof controlarActualizacion === 'function') {
                    controlarActualizacion();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Iniciar la gráfica incluso si fallan los equipos, para que la UI no se quede en blanco
                if(typeof controlarActualizacion === 'function') {
                    controlarActualizacion();
                }
            });

        // --- EVENTO AL CAMBIAR SELECCIÓN ---
        selectMac.addEventListener('change', function() {
            const selectedVal = this.value;
            if (selectedVal) {
                // Guardar en localStorage
                localStorage.setItem('selectedMac', selectedVal);
                // Recargar página con el parámetro MAC para que la gráfica se actualice
                window.location.search = `?mac=${selectedVal}`;
            }
        });

        // Función para obtener y actualizar las alarmas. Será llamada por script.js
        function actualizarAlarmas() {
          let url = "php/get_alarmas.php?t=" + new Date().getTime();
          // Si hay un equipo seleccionado (ID), lo añadimos a la petición
          if (currentMac) {
              url += `&equipo_id=${encodeURIComponent(currentMac)}`;
          }

          // Agregamos un timestamp para evitar que el navegador use la caché y siempre traiga datos frescos
          return fetch(url)
            .then((response) => response.text())
            .then((data) => {
              console.log("Datos de alarmas recibidos (CSV):", data); // Para depuración en consola
              // Verificamos si recibimos datos y actualizamos los inputs
              // Formato que se recibe: id,equipo_id,Temperatura,Temp_advertencia,Presion,Voltaje_Max,Voltaje_Min
              const valores = data.split(',');

              if (valores.length >= 4) { // Asegurarse de que hay suficientes datos
                // valores[2] es 'Temperatura' (Umbral Crítico)
                document.getElementById("umbral_critico_temp").value = parseFloat(valores[2]);
                
                // valores[3] es 'Temp_advertencia' (Umbral de Advertencia)
                document.getElementById("umbral_advertencia_temp").value = parseFloat(valores[3]);
              }
            })
            .catch((error) =>
              console.error(
                "Error al cargar la configuración de alarmas:",
                error,
              ),
            );
        }
      });

      // --- Confirmación de Salida (Logout) ---
      function confirmLogout(event) {
          event.preventDefault();
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
                  window.location.href = 'php/logout.php';
              }
          });
      }
    </script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js?v=1.2"></script>
  </body>
</html>