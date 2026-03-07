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
    <!-- jsPDF & AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

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

      /* Dark Mode Styles */
      body.dark-mode { background-color: #121212; color: #e0e0e0; }
      body.dark-mode .chart-card { background-color: #1e1e1e; color: #e0e0e0; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
      body.dark-mode .card { background-color: #1e1e1e; border-color: #333; color: #e0e0e0; }
      body.dark-mode .text-secondary { color: #a0a0a0 !important; }
      body.dark-mode h1 { color: #6ea8fe; }
      body.dark-mode .form-select { background-color: #2c2c2c; border-color: #444; color: #fff; }
      body.dark-mode .btn-outline-primary { color: #6ea8fe; border-color: #6ea8fe; }
      body.dark-mode .btn-outline-primary:hover { background-color: #6ea8fe; color: #000; }
      body.dark-mode .btn-check:checked + .btn-outline-primary { background-color: #6ea8fe; border-color: #6ea8fe; color: #000; }
    </style>
  </head>
  <body>
    <?php $base_path = ''; include 'php/sidebar.php'; include 'php/navbar.php'; ?>
    <script>
      // Variable global para el nombre del usuario (usada en reportes PDF)
      const NOMBRE_USUARIO_SESION = "<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) . ' ' . (isset($_SESSION['user_lastname']) ? htmlspecialchars($_SESSION['user_lastname']) : '') : 'Usuario'; ?>";
    </script>

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
                <button class="btn btn-danger ms-2" onclick="exportarReportePDF()" id="btnExportar" disabled>
                    <i class="fa-solid fa-file-pdf me-1"></i> Exportar reporte general
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
        let globalRecomendaciones = [];
        let globalModoTexto = '';

        document.addEventListener("DOMContentLoaded", function () {
            // --- Lógica de Modo Oscuro ---
            const toggleBtn = document.getElementById('darkModeToggle');
            const applyTheme = (isDark) => {
                if (isDark) {
                    document.body.classList.add('dark-mode');
                    toggleBtn.innerHTML = '<i class="fa-solid fa-sun me-2"></i>Modo Claro';
                    toggleBtn.classList.replace('btn-outline-secondary', 'btn-outline-light');
                    Chart.defaults.color = '#e0e0e0';
                    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
                } else {
                    document.body.classList.remove('dark-mode');
                    toggleBtn.innerHTML = '<i class="fa-solid fa-moon me-2"></i>Modo Oscuro';
                    toggleBtn.classList.replace('btn-outline-light', 'btn-outline-secondary');
                    Chart.defaults.color = '#666';
                    Chart.defaults.borderColor = 'rgba(0, 0, 0, 0.1)';
                }
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
                [chartTemp, chartPres, chartVolt].forEach(c => { if(c) c.update(); });
            };
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') applyTheme(true);
            toggleBtn.addEventListener('click', () => applyTheme(!document.body.classList.contains('dark-mode')));

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
            globalModoTexto = modo === 'general' ? 'Últimos 2 Meses' : 'Mes Actual';
            if (!mac) return;

            document.getElementById("loading").classList.remove("d-none");
            document.getElementById("charts-area").classList.add("d-none");
            document.getElementById("recommendations-area").classList.add("d-none");
            document.getElementById("error-msg").classList.add("d-none");
            document.getElementById("btnExportar").disabled = true;

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
                        tituloRec.innerHTML = `<i class="fa-solid fa-clipboard-check me-2"></i>Diagnóstico (${globalModoTexto})`;
                    }

                    globalRecomendaciones = data.recomendaciones; // Guardar para el PDF
                    document.getElementById("btnExportar").disabled = false;
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

            // --- Extraer Alarmas Diarias (con valores por defecto si el archivo es antiguo) ---
            // Temperatura
            const alarmTemp = datos.map(d => d.alarm_temp !== undefined ? d.alarm_temp : 95);
            
            // Presión
            const alarmPresMin = datos.map(d => d.alarm_pres_min !== undefined ? d.alarm_pres_min : 0.8);

            // Voltaje (Detectar sistema si no hay alarmas explícitas)
            const avgVolt = voltMax.reduce((a, b) => a + b, 0) / (voltMax.length || 1);
            const is24V = avgVolt > 18;
            const defVoltMax = is24V ? 32 : 15;
            const defVoltMin = is24V ? 23 : 11.5;
            
            const alarmVoltMax = datos.map(d => d.alarm_volt_max !== undefined ? d.alarm_volt_max : defVoltMax);
            const alarmVoltMin = datos.map(d => d.alarm_volt_min !== undefined ? d.alarm_volt_min : defVoltMin);

            // --- Lógica de Colores Dinámicos (Anomalías en Rojo) --- 
            
            // 1. Temperatura: Rojo si supera la alarma configurada ESE día
            const bgTemp = tempMax.map((v, i) => v > alarmTemp[i] ? 'rgba(220, 53, 69, 0.8)' : 'rgba(13, 110, 253, 0.6)');
            const borderTemp = tempMax.map((v, i) => v > alarmTemp[i] ? 'rgba(220, 53, 69, 1)' : 'rgba(13, 110, 253, 1)');

            // 2. Voltaje
            const bgVoltMax = voltMax.map((v, i) => v > alarmVoltMax[i] ? 'rgba(220, 53, 69, 0.8)' : 'rgba(255, 193, 7, 0.7)');
            const bgVoltMin = voltMin.map((v, i) => v < alarmVoltMin[i] ? 'rgba(220, 53, 69, 0.8)' : 'rgba(253, 126, 20, 0.7)');

            // 3. Presión: Rojo si Mínima < Alarma (y > 0)
            const bgPresMin = presMin.map((v, i) => (v < alarmPresMin[i] && v > 0) ? 'rgba(220, 53, 69, 0.8)' : 'rgba(13, 202, 240, 0.7)');

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
                    scales: { y: { beginAtZero: false } },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                footer: (tooltipItems) => {
                                    const index = tooltipItems[0].dataIndex;
                                    return `Alarma Configurada: ${alarmTemp[index]}°C`;
                                }
                            }
                        }
                    }
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
                    scales: { y: { beginAtZero: true } },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                footer: (tooltipItems) => {
                                    const index = tooltipItems[0].dataIndex;
                                    return `Alarma Mínima: ${alarmPresMin[index]}`;
                                }
                            }
                        }
                    }
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
                    scales: { y: { beginAtZero: false } },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                footer: (tooltipItems) => {
                                    const index = tooltipItems[0].dataIndex;
                                    return `Alarmas: Máx ${alarmVoltMax[index]}V / Mín ${alarmVoltMin[index]}V`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 5. Exportar Reporte PDF
        async function exportarReportePDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const pageWidth = doc.internal.pageSize.getWidth();
            const marginX = 14;
            
            // --- Encabezado ---
            doc.setFontSize(18);
            doc.setTextColor(13, 110, 253); // Azul Bootstrap
            doc.text("Reporte de Análisis Operacional", pageWidth / 2, 20, { align: "center" });
            
            // --- Datos Generales ---
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            const now = new Date().toLocaleString();
            
            const selectMac = document.getElementById("selectMacAddress");
            const selectedOption = selectMac.options[selectMac.selectedIndex];
            const equipoNombre = selectedOption.textContent;
            const mac = selectedOption.value;
            
            doc.text(`Generado el: ${now}`, marginX, 30);
            doc.text(`Usuario: ${NOMBRE_USUARIO_SESION}`, marginX, 36);
            doc.text(`Equipo: ${equipoNombre}`, marginX, 42);
            doc.text(`MAC: ${mac}`, marginX, 48);
            doc.text(`Periodo de Análisis: ${globalModoTexto}`, marginX, 54);
            
            let yPos = 65;
            
            // --- Sección de Recomendaciones ---
            doc.setFontSize(14);
            doc.setTextColor(13, 110, 253);
            doc.text("Diagnóstico y Recomendaciones", marginX, yPos);
            yPos += 10;
            
            doc.setFontSize(11);
            
            globalRecomendaciones.forEach(rec => {
                // Color del título según tipo
                if (rec.tipo === 'danger') doc.setTextColor(220, 53, 69);
                else if (rec.tipo === 'warning') doc.setTextColor(253, 126, 20);
                else if (rec.tipo === 'success') doc.setTextColor(25, 135, 84);
                else doc.setTextColor(108, 117, 125);
                
                doc.setFont(undefined, 'bold');
                doc.text(`• ${rec.titulo}`, marginX, yPos);
                yPos += 6;
                
                doc.setFont(undefined, 'normal');
                doc.setTextColor(0, 0, 0);
                const splitText = doc.splitTextToSize(rec.mensaje, pageWidth - (marginX * 2));
                doc.text(splitText, marginX, yPos);
                yPos += (splitText.length * 6) + 6;
                
                if (yPos > 270) { doc.addPage(); yPos = 20; }
            });
            
            yPos += 10;
            if (yPos > 250) { doc.addPage(); yPos = 20; }

            // --- Sección de Gráficas ---
            doc.setFontSize(14);
            doc.setTextColor(13, 110, 253);
            doc.text("Gráficas de Análisis", marginX, yPos);
            yPos += 10;
            
            const charts = [
                { id: 'chartTemp', title: 'Temperatura Máxima Diaria' },
                { id: 'chartPres', title: 'Presión (Máx / Mín)' },
                { id: 'chartVolt', title: 'Voltaje (Máx / Mín)' }
            ];

            for (const chart of charts) {
                const canvas = document.getElementById(chart.id);
                if (canvas) {
                    const imgData = canvas.toDataURL('image/png');
                    const imgProps = doc.getImageProperties(imgData);
                    const pdfWidth = pageWidth - (marginX * 2);
                    const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                    
                    // Verificar si título + gráfica caben en la página. Si no, salto de página.
                    if (yPos + 15 + pdfHeight > 280) { doc.addPage(); yPos = 20; }

                    // Agregar título descriptivo a cada gráfica (Centrado)
                    doc.setFontSize(12);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont(undefined, 'bold');
                    doc.text(chart.title, pageWidth / 2, yPos, { align: "center" });
                    yPos += 6;
                    
                    doc.addImage(imgData, 'PNG', marginX, yPos, pdfWidth, pdfHeight);
                    yPos += pdfHeight + 10;
                }
            }
            
            // Usar el Alias del equipo para el nombre del archivo (reemplazando espacios por guiones bajos)
            const nombreArchivo = equipoNombre.replace(/[^a-zA-Z0-9]/g, "_");
            doc.save(`Reporte_Operacional_${nombreArchivo}.pdf`);
        }

        // Función para logout (reutilizada del navbar)
        if (typeof confirmLogout !== 'function') {
            function confirmLogout(event) { /* ... lógica existente ... */ }
        }
    </script>
  </body>
</html>