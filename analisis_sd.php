<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
// Cabeceras anti-caché
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!doctype html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Análisis operacional diario - Resumen Diario</title>
    <link rel="icon" href="data:," />
    
    <!-- Bootstrap 5 & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
      body { background-color: #f4f6f9; font-family: "Segoe UI", sans-serif; }
      .chart-card {
          background: white;
          border-radius: 12px;
          padding: 20px;
          margin-bottom: 20px;
          box-shadow: 0 4px 6px rgba(0,0,0,0.05);
      }
      .chart-container {
          position: relative;
          height: 300px;
          width: 100%;
      }
      h1 { color: #0d6efd; font-weight: 700; }
      .recommendation-card {
          border-left: 5px solid;
          transition: transform 0.2s;
      }
      .recommendation-card:hover { transform: translateX(5px); }
    </style>
  </head>
  <body>
    <?php $base_path = ''; include 'php/sidebar.php'; include 'php/navbar.php'; ?>

    <div class="container pt-5 pt-lg-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fa-solid fa-sd-card me-2"></i>Análisis operacional diario</h1>
        </div>

        <!-- Selector de Equipo -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body d-flex align-items-center flex-wrap gap-3">
                <label for="selectMacAddress" class="fw-bold text-secondary mb-0">Seleccionar Unidad:</label>
                <select id="selectMacAddress" class="form-select w-auto" style="min-width: 250px;">
                    <option value="" disabled selected>-- Cargando equipos... --</option>
                </select>

                <div class="btn-group ms-2" role="group">
                    <input type="radio" class="btn-check" name="modoAnalisis" id="modoMes" value="mes" checked>
                    <label class="btn btn-outline-primary" for="modoMes">Mes Actual</label>

                    <input type="radio" class="btn-check" name="modoAnalisis" id="modoGeneral" value="general">
                    <label class="btn btn-outline-primary" for="modoGeneral">Últimos 2 Meses</label>
                </div>

                <button class="btn btn-primary" onclick="cargarDatosResumen()">
                    <i class="fa-solid fa-sync me-1"></i> Cargar Datos
                </button>
            </div>
        </div>

        <div id="loading" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2 text-muted">Leyendo archivo de la tarjeta SD...</p>
        </div>

        <!-- Sección de Recomendaciones -->
        <div id="recommendations-area" class="mb-4 d-none">
            <h4 class="mb-3 text-secondary"><i class="fa-solid fa-clipboard-check me-2"></i>Diagnóstico del Mes Actual</h4>
            <div id="recommendations-list" class="row g-3">
                <!-- Las recomendaciones se inyectan aquí -->
            </div>
        </div>

        <div id="charts-area" class="d-none">
            <!-- Gráfica 1: Temperatura -->
            <div class="chart-card">
                <h5 class="text-center mb-3 text-danger"><i class="fa-solid fa-temperature-high me-2"></i>Temperatura Máxima Diaria</h5>
                <div class="chart-container">
                    <canvas id="chartTemp"></canvas>
                </div>
            </div>

            <div class="row">
                <!-- Gráfica 2: Presión -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="text-center mb-3 text-primary"><i class="fa-solid fa-gauge me-2"></i>Presión (Máx / Mín)</h5>
                        <div class="chart-container">
                            <canvas id="chartPres"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Gráfica 3: Voltaje -->
                <div class="col-lg-6">
                    <div class="chart-card">
                        <h5 class="text-center mb-3 text-warning"><i class="fa-solid fa-bolt me-2"></i>Voltaje (Máx / Mín)</h5>
                        <div class="chart-container">
                            <canvas id="chartVolt"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="error-msg" class="alert alert-warning d-none text-center"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Variables globales para las instancias de las gráficas
        let chartTemp = null;
        let chartPres = null;
        let chartVolt = null;

        document.addEventListener("DOMContentLoaded", function () {
            cargarEquipos();
        });

        // 1. Cargar lista de equipos
        function cargarEquipos() {
            const selectMac = document.getElementById("selectMacAddress");
            fetch('php/obtener_equipos.php')
                .then(res => res.json())
                .then(data => {
                    selectMac.innerHTML = '<option value="" disabled selected>-- Seleccionar Unidad --</option>';
                    data.forEach(eq => {
                        const option = document.createElement('option');
                        option.value = eq.mac_address; // Usamos la MAC directamente como valor
                        option.textContent = eq.nombre_equipo;
                        selectMac.appendChild(option);
                    });
                    
                    // Auto-seleccionar si hay uno guardado o es el primero
                    if (data.length > 0) {
                        selectMac.value = data[0].mac_address;
                        cargarDatosResumen();
                    }
                })
                .catch(err => console.error("Error cargando equipos:", err));
        }

        // 2. Cargar y procesar datos del archivo
        function cargarDatosResumen() {
            const mac = document.getElementById("selectMacAddress").value;
            const modo = document.querySelector('input[name="modoAnalisis"]:checked').value;
            if (!mac) return;

            document.getElementById("loading").classList.remove("d-none");
            document.getElementById("charts-area").classList.add("d-none");
            document.getElementById("recommendations-area").classList.add("d-none");
            document.getElementById("error-msg").classList.add("d-none");

            fetch(`php/leer_resumen.php?mac=${encodeURIComponent(mac)}&modo=${modo}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById("loading").classList.add("d-none");
                    
                    if (data.error) {
                        const errDiv = document.getElementById("error-msg");
                        errDiv.textContent = data.error;
                        errDiv.classList.remove("d-none");
                        return;
                    }

                    // Verificar si hay datos en la propiedad 'datos'
                    if (!data.datos || data.datos.length === 0) {
                        const errDiv = document.getElementById("error-msg");
                        errDiv.textContent = "El archivo está vacío o no tiene el formato esperado.";
                        errDiv.classList.remove("d-none");
                        return;
                    }

                    document.getElementById("charts-area").classList.remove("d-none");
                    document.getElementById("recommendations-area").classList.remove("d-none");
                    
                    // Actualizar título de recomendaciones
                    const tituloRec = document.querySelector("#recommendations-area h4");
                    if(tituloRec) {
                        tituloRec.innerHTML = `<i class="fa-solid fa-clipboard-check me-2"></i>Diagnóstico (${modo === 'general' ? 'Últimos 2 Meses' : 'Mes Actual'})`;
                    }

                    renderizarGraficas(data.datos);
                    mostrarRecomendaciones(data.recomendaciones);
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById("loading").classList.add("d-none");
                    const errDiv = document.getElementById("error-msg");
                    errDiv.textContent = "Error de conexión al leer el archivo.";
                    errDiv.classList.remove("d-none");
                });
        }

        // 4. Mostrar Recomendaciones
        function mostrarRecomendaciones(lista) {
            const container = document.getElementById("recommendations-list");
            container.innerHTML = "";

            lista.forEach(rec => {
                const col = document.createElement("div");
                col.className = "col-md-6 col-lg-12"; // Full width en desktop para que se lea bien
                col.innerHTML = `
                    <div class="card recommendation-card shadow-sm h-100 border-${rec.tipo}">
                        <div class="card-body d-flex align-items-start">
                            <div class="text-${rec.tipo} me-3 fs-3"><i class="fa-solid ${rec.icono}"></i></div>
                            <div>
                                <h5 class="card-title text-${rec.tipo} fw-bold">${rec.titulo}</h5>
                                <p class="card-text text-secondary">${rec.mensaje}</p>
                            </div>
                        </div>
                    </div>`;
                container.appendChild(col);
            });
        }

        // 3. Renderizar las 3 gráficas
        function renderizarGraficas(datos) {
            // Preparar arrays
            const labels = datos.map(d => d.fecha);
            const tempMax = datos.map(d => d.temp_max);
            
            const presMax = datos.map(d => d.pres_max);
            const presMin = datos.map(d => d.pres_min);
            
            const voltMax = datos.map(d => d.volt_max);
            const voltMin = datos.map(d => d.volt_min);

            // --- Lógica de Colores Dinámicos (Anomalías en Rojo) --- 
            
            // 1. Temperatura: Rojo si > 95°C, Azul si normal
            const bgTemp = tempMax.map(v => v > 95 ? 'rgba(220, 53, 69, 0.8)' : 'rgba(13, 110, 253, 0.6)');
            const borderTemp = tempMax.map(v => v > 95 ? 'rgba(220, 53, 69, 1)' : 'rgba(13, 110, 253, 1)');

            // 2. Voltaje: Detectar sistema 12V/24V y aplicar umbrales
            const avgVolt = voltMax.reduce((a, b) => a + b, 0) / (voltMax.length || 1);
            const is24V = avgVolt > 18;

            const bgVoltMax = voltMax.map(v => {
                const limite = is24V ? 30 : 15;
                return v > limite ? 'rgba(220, 53, 69, 0.8)' : 'rgba(255, 193, 7, 0.7)';
            });

            const bgVoltMin = voltMin.map(v => {
                const limite = is24V ? 23 : 11.5;
                return v < limite ? 'rgba(220, 53, 69, 0.8)' : 'rgba(253, 126, 20, 0.7)';
            });

            // 3. Presión: Rojo si Mínima < 2 (y > 0)
            const bgPresMin = presMin.map(v => (v < 2 && v > 0) ? 'rgba(220, 53, 69, 0.8)' : 'rgba(13, 202, 240, 0.7)');

            // --- Gráfica Temperatura (Solo Max según datos, Barra Vertical) ---
            const ctxTemp = document.getElementById('chartTemp').getContext('2d');
            if (chartTemp) chartTemp.destroy();
            
            chartTemp = new Chart(ctxTemp, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Temp. Máxima (°C)',
                        data: tempMax,
                        backgroundColor: bgTemp,
                        borderColor: borderTemp,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: false } }
                }
            });

            // --- Gráfica Presión (Doble: Max y Min) ---
            const ctxPres = document.getElementById('chartPres').getContext('2d');
            if (chartPres) chartPres.destroy();

            chartPres = new Chart(ctxPres, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Máxima',
                            data: presMax,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)', // Azul
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Mínima',
                            data: presMin,
                            backgroundColor: bgPresMin,
                            borderColor: 'rgba(13, 202, 240, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true } }
                }
            });

            // --- Gráfica Voltaje (Doble: Max y Min) ---
            const ctxVolt = document.getElementById('chartVolt').getContext('2d');
            if (chartVolt) chartVolt.destroy();

            chartVolt = new Chart(ctxVolt, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Máximo',
                            data: voltMax,
                            backgroundColor: bgVoltMax,
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Mínimo',
                            data: voltMin,
                            backgroundColor: bgVoltMin,
                            borderColor: 'rgba(253, 126, 20, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: false } } // Voltaje no suele empezar en 0
                }
            });
        }

        // Función para logout (reutilizada del navbar)
        if (typeof confirmLogout !== 'function') {
            function confirmLogout(event) { /* ... lógica existente ... */ }
        }
    </script>
  </body>
</html>