// 1. --- SELECCIONAR ELEMENTOS DEL DOM ---
console.log(">>> Carga de script.js v1.3 iniciada"); // Log para verificar versión
const selectorEquipo = document.getElementById("selectMacAddress");
const ctx = document.getElementById("myChart").getContext("2d");
const contenedorAlerta = document.getElementById("contenedor-alerta");
const fechaDiaInput = document.getElementById("fecha_dia");    

// NUEVAS SELECCIONES PARA LOS DOS UMBRALES
const umbralAdvertenciaInput = document.getElementById(
  "umbral_advertencia_temp",
);
const umbralCriticoInput = document.getElementById("umbral_critico_temp");

const tempCriticaInput = document.getElementById("temp_critica");
const themeToggleButton = document.getElementById("toggle-theme");

// Botones de exportación (deshabilitados al inicio)
const btnExportarPDF = document.getElementById("btnExportarPDF");
const btnReporteCritico = document.getElementById("btnReporteCritico");
const btnReporteSoloCritico = document.getElementById("btnReporteSoloCritico");

// NUEVAS SELECCIONES PARA EL CRONÓMETRO Y SELECTOR
const selectIntervalo = document.getElementById("selectIntervalo");
const updateTimerDisplay = document.getElementById("update-timer-display");

// NUEVO: Selector de archivos para análisis histórico
const selectorArchivoAnalisis = document.getElementById("selector-archivo-analisis");
// -----------------------------------------------------

const tempMaxRangoElem = document.getElementById("temp-max-rango");
const presionMinRangoElem = document.getElementById("presion-min-rango");
const voltajeRangoElem = document.getElementById("voltaje-rango");

const tempCardElem = document.getElementById("temp-stat-card");
const valorTempActualElem = document.getElementById("valor-temp-actual");
const valorPresionActualElem = document.getElementById("valor-presion-actual");
const valorVoltajeActualElem = document.getElementById("valor-voltaje-actual");

const tempTrendElem = document.getElementById("temp-trend");
const presionTrendElem = document.getElementById("presion-trend");
const voltajeTrendElem = document.getElementById("voltaje-trend");

// Estado de los datos cargados para reportes
let datosSensoresCargados = {
  labels: [],
  temperatura: [],
  presion: [],
  voltaje: [],
  max_temp_rango: null,
  hora_max_temp_rango: null,
  min_presion_rango: null,
  hora_min_presion_rango: null,
  max_voltaje_rango: null,
  min_voltaje_rango: null,
};

// --- URLs y Objetos de Audio ---
const URL_ALARMA_ADVERTENCIA =
  "http://marant.medianewsonline.com/Sonidos/alarm-door-chime.wav";
const URL_ALARMA_CRITICA =
  "http://marant.medianewsonline.com/Sonidos/bip-temp-critica.wav";
const URL_BIP_ACTUALIZACION =
  "http://marant.medianewsonline.com/Sonidos/beep-actualiza-gráfica.wav";

const sonidoAdvertencia = new Audio(URL_ALARMA_ADVERTENCIA);
const sonidoCritico = new Audio(URL_ALARMA_CRITICA);
const sonidoBipActualizacion = new Audio(URL_BIP_ACTUALIZACION);

// --- Variables de Estado de Alarma y Actualización ---
let intervaloAlarma = null;
let alertaSonoraActiva = false;
let intervaloActualizacion;
let alertaActualTipo = null;

// --- Variables para el Cronómetro y el Intervalo Dinámico ---
let intervaloCountdown = null;
// La constante anterior se reemplaza por una función que toma el valor del select
let INTERVALO_ACTUALIZACION_MS = 60000;
// -------------------------------------

// --- Variables de Estado para Tendencia ---
let tempAnterior = null;
let presionAnterior = null;
let voltajeAnterior = null;

// --- Función Helper para manejar toFixed en datos potencialmente nulos ---
const safeToFixed = (value, decimals = 2) => {
  if (typeof value === "number" && !isNaN(value) && value !== null) {
    return value.toFixed(decimals);
  }
  return "--";
};

// Function to create a gradient for Chart.js based on temperature values
function getTemperatureGradient(chart, alpha = 1) {
  const ctx = chart.chart.ctx;
  const chartArea = chart.chart.chartArea;

  if (!chartArea) {
    return `rgba(0, 0, 0, ${alpha})`;
  }

  const gradient = ctx.createLinearGradient(
    0,
    chartArea.bottom,
    0,
    chartArea.top,
  );

  const yScale = chart.chart.scales.y;

  // Los valores de referencia para el color deben ser fijos o dinámicos basados en la escala.
  // Usamos 21 y el umbral de advertencia inicial (85) como punto de inflexión.
  const umbralAdvertenciaInicial =
    parseFloat(umbralAdvertenciaInput.value) || 85;

  const valueLow = 2;
  const valueMid = 21;
  const valueHigh = umbralAdvertenciaInicial; // Usamos el valor inicial del input de advertencia

  const pixelLow = yScale.getPixelForValue(Math.max(valueLow, yScale.min));
  const pixelMid = yScale.getPixelForValue(Math.max(valueMid, yScale.min));
  const pixelHigh = yScale.getPixelForValue(Math.min(valueHigh, yScale.max));

  const stopLow =
    (chartArea.bottom - pixelLow) / (chartArea.bottom - chartArea.top);
  const stopMid =
    (chartArea.bottom - pixelMid) / (chartArea.bottom - chartArea.top);
  const stopHigh =
    (chartArea.bottom - pixelHigh) / (chartArea.bottom - chartArea.top);

  gradient.addColorStop(Math.max(0, stopLow), `rgba(0, 255, 0, ${alpha})`); // Green
  gradient.addColorStop(Math.max(0, stopMid), `rgba(255, 255, 0, ${alpha})`); // Yellow
  gradient.addColorStop(Math.min(1, stopHigh), `rgba(255, 0, 0, ${alpha})`); // Red

  return gradient;
}

// 2. --- CONFIGURACIÓN INICIAL DE LA GRÁFICA ---
const myChart = new Chart(ctx, {
  type: "line",
  data: {
    labels: [],
    datasets: [
      {
        label: "Temperatura (°C)",
        data: [],
        borderColor: (context) => getTemperatureGradient(context, 1),
        backgroundColor: (context) => getTemperatureGradient(context, 0.2),
        borderWidth: 2,
        yAxisID: "y",
        pointRadius: 3,
        pointHoverRadius: 5,
      },
      {
        label: "Presión (Bar)",
        data: [],
        borderColor: "rgba(54, 162, 235, 1)",
        backgroundColor: "rgba(54, 162, 235, 0.2)",
        borderWidth: 2,
        yAxisID: "y1",
      },
      {
        label: "Voltaje (V)",
        data: [],
        borderColor: "rgba(255, 206, 86, 1)",
        backgroundColor: "rgba(255, 206, 86, 0.2)",
        borderWidth: 2,
        yAxisID: "y",
      },
    ],
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: "index",
      intersect: false,
    },
    plugins: {
      annotation: {
        annotations: {
          umbralAdvertenciaLine: {
            type: "line",
            mode: "horizontal",
            scaleID: "y",
            // Valor inicial de la línea de Advertencia (CAMBIADO)
            value: 85,
            borderColor: "rgba(255, 165, 0, 0.7)", // Naranja para Advertencia
            borderWidth: 2,
            borderDash: [6, 6],
            label: {
              content: "Umbral Advertencia",
              enabled: true,
              position: "end",
              backgroundColor: "rgba(255, 165, 0, 0.1)",
              font: { size: 10 },
            },
          },
          umbralCriticoLine: {
            type: "line",
            mode: "horizontal",
            scaleID: "y",
            // Valor inicial de la línea Crítica (CAMBIADO)
            value: 90,
            borderColor: "rgba(255, 0, 0, 0.7)", // Rojo para Crítico
            borderWidth: 2,
            label: {
              content: "Umbral Crítico",
              enabled: true,
              position: "start",
              backgroundColor: "rgba(255, 0, 0, 0.1)",
              font: { size: 10 },
            },
          },
          maxTempPoint: {
            type: "point",
            scaleID: "y",
            xValue: 0,
            yValue: 0,
            radius: 8,
            backgroundColor: "rgba(255, 0, 0, 1)",
            borderColor: "rgba(255, 255, 255, 1)",
            borderWidth: 2,
            display: false,
            label: {
              content: "PICO",
              enabled: true,
              position: "start",
              backgroundColor: "rgba(255, 0, 0, 0.8)",
            },
          },
        },
      },
    },
    scales: {
      x: {
        display: true,
        title: {
          display: false,
          text: "Fecha y Hora",
        },
        ticks: {
          display: false,
        },
      },
      y: {
        type: "linear",
        display: true,
        position: "right",
        title: {
          display: true,
          text: "Temperatura (°C) / Voltaje (V)",
          color: "rgba(255, 159, 64, 1)",
        },
        min: 10,
        max: 125,
        ticks: {
          color: "rgba(255, 159, 64, 1)",
        },
      },
      y1: {
        type: "linear",
        display: true,
        position: "right",
        title: {
          display: true,
          text: "Presión (hPa)",
          color: "rgba(54, 162, 235, 1)",
        },
        grid: {
          drawOnChartArea: false,
        },
        min: 0,
        max: 160,
        ticks: {
          color: "rgba(54, 162, 235, 1)",
        },
      },
    },
  },
});

// --- FUNCIONES DE VISUALIZACIÓN ADICIONALES ---

/**
 * Habilita o deshabilita los botones de exportación.
 */
function toggleExportButtons(enable) {
  btnExportarPDF.disabled = !enable;
  btnReporteCritico.disabled = !enable;
  btnReporteSoloCritico.disabled = !enable;
}

/**
 * Aplica las clases de Semáforo a la tarjeta de Temperatura Actual.
 */
function setTemperatureCardStatus(temp, umbralAdvertencia, umbralCritico) {
  tempCardElem.classList.remove(
    "status-normal",
    "status-warning",
    "status-danger",
  );
  valorTempActualElem.classList.remove("rojo", "amarillo", "titilando-texto");

  if (temp === null) {
    return;
  }

  // Lógica actualizada:
  // 1. Peligro si T >= Umbral Crítico
  // 2. Advertencia si T >= Umbral de Advertencia Y T < Umbral Crítico
  // 3. Normal si T < Umbral de Advertencia

  if (temp >= umbralCritico) {
    tempCardElem.classList.add("status-danger");
    valorTempActualElem.classList.add("rojo", "titilando-texto");
  } else if (temp >= umbralAdvertencia) {
    tempCardElem.classList.add("status-warning");
    valorTempActualElem.classList.add("amarillo");
  } else {
    tempCardElem.classList.add("status-normal");
  }
}

/**
 * Muestra el icono de tendencia (flecha arriba/abajo/guion)
 */
function showTrend(currentValue, previousValue, element) {
  element.classList.remove("trend-up", "trend-down", "trend-neutral");

  if (
    previousValue === null ||
    currentValue === null ||
    currentValue === undefined
  ) {
    element.innerHTML = "—";
    element.classList.add("trend-neutral");
  } else if (currentValue > previousValue) {
    element.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
    element.classList.add("trend-up");
  } else if (currentValue < previousValue) {
    element.innerHTML = '<i class="fa-solid fa-arrow-down"></i>';
    element.classList.add("trend-down");
  } else {
    element.innerHTML = '<i class="fa-solid fa-minus"></i>';
    element.classList.add("trend-neutral");
  }
}

/**
 * Exporta el contenido de la gráfica (canvas) a un archivo PDF simple.
 */
function exportChartToPDF() {
  const canvas = document.getElementById("myChart");
  if (myChart.data.datasets[0].data.length === 0) {
    console.warn("No hay datos en la gráfica para exportar a PDF.");
    return;
  }

  const chartImage = canvas.toDataURL("image/png", 1.0);

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");

  const width = 210;
  const imgWidth = 180;
  const imgHeight = (canvas.height * imgWidth) / canvas.width;

  doc.setFontSize(18);
  doc.text("Reporte de Monitor de Sensores", width / 2, 20, {
    align: "center",
  });

  doc.setFontSize(10);
  const now = new Date().toLocaleString("es-ES", {
    dateStyle: "long",
    timeStyle: "short",
  });
  doc.text(`Generado el: ${now}`, width / 2, 27, { align: "center" });

  // --- DATOS DEL USUARIO Y EQUIPO ---
  const selectedOption = selectorEquipo.options[selectorEquipo.selectedIndex];
  const alias = selectedOption ? selectedOption.textContent : "Desconocido";
  const mac = selectedOption && selectedOption.dataset.mac ? selectedOption.dataset.mac : "N/A";
  const usuario = (typeof NOMBRE_USUARIO_SESION !== 'undefined') ? NOMBRE_USUARIO_SESION : "Usuario";

  doc.setFontSize(11);
  doc.text(`Cliente: ${usuario}`, 15, 35);
  doc.text(`Equipo: ${alias}`, 15, 40);
  doc.text(`MAC: ${mac}`, 15, 45);

  const startY = 50; // Bajamos la gráfica para dar espacio a los datos
  doc.addImage(
    chartImage,
    "PNG",
    (width - imgWidth) / 2,
    startY,
    imgWidth,
    imgHeight,
  );

  let currentY = startY + imgHeight + 10;

  const tempActual = valorTempActualElem.textContent;
  const presionActual = valorPresionActualElem.textContent;
  const voltajeActual = valorVoltajeActualElem.textContent;
  // Usar los valores actuales de los inputs
  const umbralAdvertencia = umbralAdvertenciaInput.value || 85;
  const umbralCritico = umbralCriticoInput.value || 90;

  doc.setFontSize(12);
  doc.text("Resumen de Valores Actuales y Umbrales:", 15, currentY);
  currentY += 8;

  doc.setFontSize(10);
  doc.text(`Temperatura: ${tempActual}`, 15, currentY);
  doc.text(`Umbral Advertencia: ${umbralAdvertencia}°C`, 70, currentY);
  currentY += 6;
  doc.text(`Presión: ${presionActual}`, 15, currentY);
  doc.text(`Umbral Crítico: ${umbralCritico}°C`, 70, currentY);
  currentY += 6;
  doc.text(`Voltaje: ${voltajeActual}`, 15, currentY);

  const filename = `Reporte_Sensores_${new Date()
    .toISOString()
    .slice(0, 10)}.pdf`;
  doc.save(filename);
  console.log(`PDF generado y guardado como ${filename}`);
}

/**
 * Exporta un reporte PDF detallado con una tabla de temperatura, destacando los valores críticos.
 * MODIFICADO: Ahora aplica resaltado en la columna de Temperatura basado en 85°C (Naranja) y 90°C (Rojo/Grande).
 */
function exportarReporteCritico() {
  if (datosSensoresCargados.temperatura.length === 0) {
    console.warn("No hay datos cargados para generar el reporte crítico.");
    return;
  }

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");
  const marginX = 14;

  // OBTENER UMBRALES DINÁMICOS DEL USUARIO (Se usan para la columna 'Estado' y el resumen final)
  const umbralAdvertenciaUsuario = parseFloat(
    umbralAdvertenciaInput.value || 85,
  );
  const umbralCriticoUsuario = parseFloat(umbralCriticoInput.value || 90);

  const width = 210;
  let registrosCriticos = 0;
  let registrosAdvertencia = 0;

  // 1. Título y Cabecera
  doc.setFontSize(18);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(200, 0, 0); // Rojo
  doc.text("REPORTE DETALLADO DE TEMPERATURAS", width / 2, 20, {
    align: "center",
  });
  doc.setTextColor(0, 0, 0); // Negro

  doc.setFontSize(10);
  doc.setFont("helvetica", "normal");
  const now = new Date().toLocaleString("es-ES", {
    dateStyle: "long",
    timeStyle: "short",
  });
  doc.text(`Generado el: ${now}`, marginX, 30);

  // --- DATOS DEL USUARIO Y EQUIPO ---
  const selectedOption = selectorEquipo.options[selectorEquipo.selectedIndex];
  const alias = selectedOption ? selectedOption.textContent : "Desconocido";
  const mac = selectedOption && selectedOption.dataset.mac ? selectedOption.dataset.mac : "N/A";
  const usuario = (typeof NOMBRE_USUARIO_SESION !== 'undefined') ? NOMBRE_USUARIO_SESION : "Usuario";

  doc.text(`Cliente: ${usuario}`, marginX, 36);
  doc.text(`Equipo: ${alias}`, marginX, 42);
  doc.text(`MAC: ${mac}`, marginX + 80, 42);

  doc.text(
    `Umbral Advertencia: ${umbralAdvertenciaUsuario}°C`,
    marginX + 80,
    30,
  );
  doc.text(`Umbral Crítico: ${umbralCriticoUsuario}°C`, marginX + 135, 30);

  // 2. Preparar los datos para la tabla
  const head = [
    [
      "Hora de Registro",
      "Temperatura (°C)",
      "Presión (hPa)",
      "Voltaje (V)",
      "Estado",
    ],
  ];

  const body = [];

  for (let i = 0; i < datosSensoresCargados.labels.length; i++) {
    const label = datosSensoresCargados.labels[i];
    // CORRECCIÓN: Convertir los valores de string a float
    const temp = parseFloat(datosSensoresCargados.temperatura[i]);
    const presion = parseFloat(datosSensoresCargados.presion[i]);
    const voltaje = parseFloat(datosSensoresCargados.voltaje[i]);

    const tempFormateada = safeToFixed(temp, 1);
    const presionFormateada = safeToFixed(presion, 1);
    const voltajeFormateado = safeToFixed(voltaje, 2);

    let status = "NORMAL";

    // Lógica de estado basada en los dos umbrales dinámicos del usuario
    if (temp >= umbralCriticoUsuario) {
      status = "CRÍTICO";
      registrosCriticos++;
    } else if (temp >= umbralAdvertenciaUsuario) {
      status = "ADVERTENCIA";
      registrosAdvertencia++;
    }

    body.push([
      label,
      tempFormateada,
      presionFormateada,
      voltajeFormateado,
      status,
    ]);
  }

  // 3. Generar la tabla con jsPDF-AutoTable
  doc.autoTable({
    startY: 48, // Ajustado para dar espacio a los datos del usuario
    head: head,
    body: body,
    theme: "striped",
    margin: { left: marginX, right: marginX },
    styles: {
      fontSize: 9,
      cellPadding: 3,
      valign: "middle",
      halign: "center", // <-- ESTA ES LA LÍNEA AÑADIDA
    },
    // ...
    headStyles: {
      fillColor: [50, 50, 50],
      textColor: [255, 255, 255],
      fontStyle: "bold",
    },
    didParseCell: (data) => {
      if (data.cell.section !== 'body') return;

      const tempValue = parseFloat(data.row.raw[1]);
      const status = data.row.raw[4]; // 'CRÍTICO', 'ADVERTENCIA', 'NORMAL'

      // Colorear el valor de la Temperatura (columna 1)
      if (data.column.index === 1) {
          if (tempValue >= umbralCriticoUsuario) {
              data.cell.styles.textColor = [220, 53, 69]; // Rojo
              data.cell.styles.fontStyle = 'bold';
          } else if (tempValue >= umbralAdvertenciaUsuario) {
              data.cell.styles.textColor = [253, 126, 20]; // Naranja
              data.cell.styles.fontStyle = 'bold';
          }
      }

      // Colorear el texto de la columna Estado (columna 4)
      if (data.column.index === 4) {
          if (status === 'CRÍTICO') {
              data.cell.styles.textColor = [220, 53, 69]; // Rojo
              data.cell.styles.fontStyle = 'bold';
          } else if (status === 'ADVERTENCIA') {
              data.cell.styles.textColor = [253, 126, 20]; // Naranja
              data.cell.styles.fontStyle = 'bold';
          } else {
              data.cell.styles.textColor = [25, 135, 84]; // Verde para Normal
          }
      }
      // Las otras columnas (Fecha, Presión, Voltaje) se quedan con el color por defecto (negro).
    },
  });

  // 4. Pie de página de resumen
  const finalY = doc.autoTable.previous.finalY + 10;
  doc.setFontSize(12);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(0, 0, 0);
  doc.text(
    `Resumen: ${registrosCriticos} registro(s) por encima del umbral CRÍTICO de ${umbralCriticoUsuario}°C.`,
    marginX,
    finalY,
  );
  doc.text(
    `${registrosAdvertencia} registro(s) por encima del umbral de ADVERTENCIA de ${umbralAdvertenciaUsuario}°C.`,
    marginX,
    finalY + 5,
  );

  // 5. Seccion de datos de rango (Máximos/Mínimos)
  let dataY = finalY + 15;
  doc.setFontSize(14);
  doc.text("Resumen de Rango del Período", marginX, dataY);
  dataY += 7;

  doc.setFontSize(10);
  doc.text(
    `Temperatura Máxima: ${tempMaxRangoElem.textContent}`,
    marginX,
    dataY,
  );
  dataY += 5;
  doc.text(
    `Presión Mínima: ${presionMinRangoElem.textContent}`,
    marginX,
    dataY,
  );
  dataY += 5;
  doc.text(
    `Voltaje (Máx/Mín): ${voltajeRangoElem.textContent}`,
    marginX,
    dataY,
  );

  const filename = `Reporte_Critico_Tabla_${new Date()
    .toISOString()
    .slice(0, 10)}.pdf`;
  doc.save(filename);
  console.log(`Reporte crítico generado y guardado como ${filename}`);
}

/**
 * Exporta un reporte PDF SOLO con los registros que superan los umbrales (Advertencia o Crítico).
 */
function exportarReporteSoloCritico() {
  if (datosSensoresCargados.temperatura.length === 0) {
    console.warn("No hay datos cargados para generar el reporte crítico.");
    return;
  }

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");
  const marginX = 14;

  const umbralAdvertenciaUsuario = parseFloat(
    umbralAdvertenciaInput.value || 85,
  );
  const umbralCriticoUsuario = parseFloat(umbralCriticoInput.value || 90);

  const width = 210;
  let registrosCriticos = 0;
  let registrosAdvertencia = 0;

  // 1. Título y Cabecera
  doc.setFontSize(18);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(200, 0, 0);
  doc.text("REPORTE DE ALERTAS (CRÍTICO/ADVERTENCIA)", width / 2, 20, {
    align: "center",
  });
  doc.setTextColor(0, 0, 0);

  doc.setFontSize(10);
  doc.setFont("helvetica", "normal");
  const now = new Date().toLocaleString("es-ES", {
    dateStyle: "long",
    timeStyle: "short",
  });
  doc.text(`Generado el: ${now}`, marginX, 30);

  // --- DATOS DEL USUARIO Y EQUIPO ---
  const selectedOption = selectorEquipo.options[selectorEquipo.selectedIndex];
  const alias = selectedOption ? selectedOption.textContent : "Desconocido";
  const mac = selectedOption && selectedOption.dataset.mac ? selectedOption.dataset.mac : "N/A";
  const usuario = (typeof NOMBRE_USUARIO_SESION !== 'undefined') ? NOMBRE_USUARIO_SESION : "Usuario";

  doc.text(`Cliente: ${usuario}`, marginX, 36);
  doc.text(`Equipo: ${alias}`, marginX, 42);
  doc.text(`MAC: ${mac}`, marginX + 80, 42);

  doc.text(
    `Umbral Advertencia: ${umbralAdvertenciaUsuario}°C`,
    marginX + 80,
    30,
  );
  doc.text(`Umbral Crítico: ${umbralCriticoUsuario}°C`, marginX + 135, 30);

  // 2. Preparar los datos para la tabla
  const head = [
    [
      "Hora de Registro",
      "Temperatura (°C)",
      "Presión (hPa)",
      "Voltaje (V)",
      "Estado",
    ],
  ];

  const body = [];

  for (let i = 0; i < datosSensoresCargados.labels.length; i++) {
    const label = datosSensoresCargados.labels[i];
    const temp = parseFloat(datosSensoresCargados.temperatura[i]);
    const presion = parseFloat(datosSensoresCargados.presion[i]);
    const voltaje = parseFloat(datosSensoresCargados.voltaje[i]);

    const tempFormateada = safeToFixed(temp, 1);
    const presionFormateada = safeToFixed(presion, 1);
    const voltajeFormateado = safeToFixed(voltaje, 2);

    let status = "NORMAL";

    if (temp >= umbralCriticoUsuario) {
      status = "CRÍTICO";
      registrosCriticos++;
    } else if (temp >= umbralAdvertenciaUsuario) {
      status = "ADVERTENCIA";
      registrosAdvertencia++;
    }

    // FILTRO: Solo agregar si NO es normal
    if (status !== "NORMAL") {
      body.push([
        label,
        tempFormateada,
        presionFormateada,
        voltajeFormateado,
        status,
      ]);
    }
  }

  if (body.length === 0) {
    alert(
      "No se encontraron registros que superen los umbrales de advertencia o crítico en el período seleccionado.",
    );
    return;
  }

  // 3. Generar la tabla con jsPDF-AutoTable
  doc.autoTable({
    startY: 48, // Ajustado para dar espacio a los datos del usuario
    head: head,
    body: body,
    theme: "striped",
    margin: { left: marginX, right: marginX },
    styles: {
      fontSize: 9,
      cellPadding: 3,
      valign: "middle",
      halign: "center",
    },
    headStyles: {
      fillColor: [200, 0, 0], // Rojo para encabezado de reporte crítico
      textColor: [255, 255, 255],
      fontStyle: "bold",
    },
    didParseCell: (data) => {
      if (data.cell.section !== 'body') return;

      const tempValue = parseFloat(data.row.raw[1]);
      const status = data.row.raw[4]; // 'CRÍTICO', 'ADVERTENCIA'

      // Colorear el valor de la Temperatura (columna 1)
      if (data.column.index === 1) {
          if (tempValue >= umbralCriticoUsuario) {
              data.cell.styles.textColor = [220, 53, 69]; // Rojo
              data.cell.styles.fontStyle = 'bold';
          } else if (tempValue >= umbralAdvertenciaUsuario) {
              data.cell.styles.textColor = [253, 126, 20]; // Naranja
              data.cell.styles.fontStyle = 'bold';
          }
      }

      // Colorear el texto de la columna Estado (columna 4)
      if (data.column.index === 4) {
          if (status === 'CRÍTICO') {
              data.cell.styles.textColor = [220, 53, 69]; // Rojo
              data.cell.styles.fontStyle = 'bold';
          } else if (status === 'ADVERTENCIA') {
              data.cell.styles.textColor = [253, 126, 20]; // Naranja
              data.cell.styles.fontStyle = 'bold';
          }
      }
      // Las otras columnas se quedan con el color por defecto (negro).
    },
  });

  // 4. Pie de página de resumen
  const finalY = doc.autoTable.previous.finalY + 10;
  doc.setFontSize(12);
  doc.setFont("helvetica", "bold");
  doc.setTextColor(0, 0, 0);
  doc.text(
    `Total encontrados: ${registrosCriticos} Críticos y ${registrosAdvertencia} Advertencias.`,
    marginX,
    finalY,
  );

  const filename = `Reporte_Solo_Critico_${new Date()
    .toISOString()
    .slice(0, 10)}.pdf`;
  doc.save(filename);
}

/**
 * Inicia o actualiza el intervalo de parpadeo y reproducción de sonido de alarma.
 */
function iniciarAlarmaSonora(
  tipo,
  currentTemp,
  umbralAdvertencia,
  umbralCritico,
) {
  const sonido = tipo === "critica" ? sonidoCritico : sonidoAdvertencia;
  const claseAlerta =
    tipo === "critica" ? "alerta-peligro" : "alerta-advertencia";

  const tempActualText = safeToFixed(currentTemp);

  const umbralMostrado = tipo === "critica" ? umbralCritico : umbralAdvertencia;

  const mensaje =
    tipo === "critica"
      ? `¡ATENCIÓN! Temperatura Crítica detectada: ${tempActualText}°C. (T \u2265 ${umbralCritico}°C)`
      : `¡ADVERTENCIA! Temperatura alta detectada: ${tempActualText}°C. (T \u2265 ${umbralAdvertencia}°C)`;

  const repetirSegundos = tipo === "critica" ? 20 : 30;
  const repeticiones = (repetirSegundos * 1000) / 1000;

  if (alertaSonoraActiva && alertaActualTipo !== tipo) {
    // Si el tipo de alerta cambia (de advertencia a crítica o viceversa), se reinicia
    clearInterval(intervaloAlarma);
    alertaSonoraActiva = false;
    contenedorAlerta.classList.remove("alerta-peligro", "alerta-advertencia");
    contenedorAlerta.style.visibility = "visible";
  }

  if (!alertaSonoraActiva) {
    alertaSonoraActiva = true;
    alertaActualTipo = tipo;

    contenedorAlerta.textContent = mensaje;
    contenedorAlerta.classList.add(claseAlerta);
    contenedorAlerta.classList.remove("oculto");

    sonido.currentTime = 0;
    sonido.volume = tipo === "critica" ? 1.0 : 0.7;
    sonido.play().catch((e) => {
      console.error("No se pudo reproducir el sonido de alarma:", e);
    });

    let contadorParpadeo = 1;

    intervaloAlarma = setInterval(() => {
      contenedorAlerta.style.visibility =
        contenedorAlerta.style.visibility === "hidden" ? "visible" : "hidden";

      if (contadorParpadeo % repeticiones === 0) {
        sonido.currentTime = 0;
        sonido.play().catch((e) => {});
      }
      contadorParpadeo++;
    }, 1000);
  }
}

/**
 * Detiene el parpadeo y el sonido de alarma.
 */
function detenerAlarmaSonora(currentTemp, umbralAdvertencia, umbralCritico) {
  if (alertaSonoraActiva) {
    alertaSonoraActiva = false;
    alertaActualTipo = null;
    clearInterval(intervaloAlarma);
    contenedorAlerta.classList.add("oculto");
    contenedorAlerta.style.visibility = "visible";

    valorTempActualElem.classList.remove("titilando-texto", "rojo", "amarillo");

    setTemperatureCardStatus(currentTemp, umbralAdvertencia, umbralCritico);
  }
}

// --- NUEVAS/MODIFICADAS FUNCIONES PARA EL CRONÓMETRO ---

/**
 * Detiene y limpia el cronómetro de cuenta regresiva.
 */
function detenerCountdown() {
  if (intervaloCountdown) {
    clearInterval(intervaloCountdown);
    intervaloCountdown = null;
  }
}

/**
 * Inicia el cronómetro de cuenta regresiva.
 * Usa INTERVALO_ACTUALIZACION_MS que se actualiza desde el selector.
 */
function iniciarCountdown() {
  detenerCountdown(); // Asegura que solo haya un intervalo activo

  // Si el intervalo es 0 (Deshabilitar), no iniciamos el contador
  if (INTERVALO_ACTUALIZACION_MS === 0) {
    updateTimerDisplay.textContent = "Actualización automática deshabilitada";
    return;
  }

  // Se establece un punto final en el tiempo para evitar desviaciones
  const endTime = Date.now() + INTERVALO_ACTUALIZACION_MS;

  const actualizarDisplay = () => {
    // Se calcula el tiempo restante real en cada tick
    const tiempoRestante = endTime - Date.now();

    if (tiempoRestante < 1) {
      updateTimerDisplay.textContent = "Actualizando...";
      if (intervaloCountdown) clearInterval(intervaloCountdown);
      return;
    }

    const totalSegundos = Math.floor(tiempoRestante / 1000);
    const minutos = Math.floor(totalSegundos / 60);
    const segundos = totalSegundos % 60;

    // Formato de dos dígitos para minutos y segundos
    const display = `${minutos.toString().padStart(2, "0")}:${segundos
      .toString()
      .padStart(2, "0")}`;
    updateTimerDisplay.textContent = `Próxima actualización en: ${display}`;
  };

  // Ejecuta inmediatamente y luego cada segundo
  actualizarDisplay();
  intervaloCountdown = setInterval(actualizarDisplay, 1000);
}
// ------------------------------------------

/**
 * Función para obtener las alarmas desde la BD y actualizar los inputs.
 * Se conecta a php/get_alarmas.php
 */
async function actualizarAlarmas() {
  console.log(">>> [actualizarAlarmas] Iniciando solicitud de alarmas...");

  // Obtener el ID del equipo seleccionado
  const urlParams = new URLSearchParams(window.location.search);
  const currentEquipoId = urlParams.get('equipo_id') || (selectorEquipo ? selectorEquipo.value : '');

  if (!currentEquipoId) {
    console.warn(">>> [actualizarAlarmas] No hay equipo seleccionado. Se omite la carga.");
    return;
  }

  const url = `php/get_alarmas.php?equipo_id=${currentEquipoId}`;
  console.log(`>>> [actualizarAlarmas] URL: ${url}`);

  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`Error HTTP: ${response.status}`);
    }

    const text = await response.text();
    console.log(`>>> [actualizarAlarmas] Respuesta cruda: ${text}`);

    // Verificar si la respuesta es un error del PHP
    if (text.trim().startsWith("Error")) {
      console.error(`>>> [actualizarAlarmas] Error devuelto por PHP: ${text}`);
      return;
    }

    // Parsear CSV: id, equipo_id, temperatura, temp_advertencia, presion, voltaje_max, voltaje_min
    const parts = text.split(',');
    
    if (parts.length >= 4) {
      // Según get_alarmas.php:
      // parts[2] -> Temperatura (Crítica)
      // parts[3] -> Temp_advertencia
      
      const tempCritica = parseFloat(parts[2]);
      const tempAdvertencia = parseFloat(parts[3]);

      console.log(`>>> [actualizarAlarmas] Datos parseados -> Crítico: ${tempCritica}, Advertencia: ${tempAdvertencia}`);

      if (!isNaN(tempCritica) && umbralCriticoInput) {
        umbralCriticoInput.value = tempCritica;
      }

      if (!isNaN(tempAdvertencia) && umbralAdvertenciaInput) {
        umbralAdvertenciaInput.value = tempAdvertencia;
      }
    } else {
      console.warn(">>> [actualizarAlarmas] Formato de respuesta inesperado (faltan campos):", parts);
    }

  } catch (error) {
    console.error(">>> [actualizarAlarmas] Excepción:", error);
  }
  console.log(">>> [actualizarAlarmas] Finalizado.");
}

// 3. --- FUNCIÓN PARA OBTENER DATOS Y ACTUALIZAR LA GRÁFICA ---
async function actualizarGrafica(
  fechaInicio = null,
  fechaFin = null,
  // Obtener umbrales dinámicos
  umbralAdvertencia = umbralAdvertenciaInput.value || 85,
  umbralCritico = umbralCriticoInput.value || 90,
  mostrarPico = false,
) {
  toggleExportButtons(false);

  // Detenemos el cronómetro al inicio de la actualización
  detenerCountdown();

  let url = "php/consulta_tablas.php";

  const params = new URLSearchParams();

  // Obtener ID de equipo de la URL (prioridad) o del selector
  const urlParams = new URLSearchParams(window.location.search);
  const currentEquipoId = urlParams.get('equipo_id') || (selectorEquipo ? selectorEquipo.value : '');
  
  if (currentEquipoId) {
      params.append("equipo_id", currentEquipoId);
  }

  // AÑADIDO: Limitar siempre a las últimas 30 mediciones por defecto
  params.append("limit", "30");

  if (fechaInicio && fechaFin) {
    // El backend local espera YYYY-MM-DD (formato estándar del input date)
    params.append("fecha_inicio", fechaInicio);
    params.append("fecha_fin", fechaFin);
  }

  url += `?${params.toString()}`;

  console.log("Solicitando datos desde:", url);
  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`Error HTTP: ${response.status}`);
    }
    const rawData = await response.json();

    // Procesar datos del formato Array [{fecha, temperatura...}] al formato de Gráfica
    // Invertimos el array porque viene DESC (más reciente primero) y la gráfica necesita cronológico
    const data = Array.isArray(rawData) ? rawData.reverse() : [];

    const labels = data.map(d => d.fecha ? d.fecha.split(' ')[1] : ''); // Solo hora
    const temps = data.map(d => d.temperatura);
    const presions = data.map(d => d.presion);
    const voltajes = data.map(d => d.voltaje);

    // Guardar todos los datos cargados para el reporte crítico
    datosSensoresCargados = {
      labels: labels,
      temperatura: temps,
      presion: presions,
      voltaje: voltajes,
      // Calcular rangos manualmente ya que el endpoint devuelve lista cruda
      max_temp_rango:
        temps.length > 0 ? Math.max(...temps.map(v => parseFloat(v))) : null,
      hora_max_temp_rango: null,
      min_presion_rango:
        presions.length > 0 ? Math.min(...presions.map(v => parseFloat(v))) : null,
      hora_min_presion_rango: null,
      max_voltaje_rango:
        voltajes.length > 0 ? Math.max(...voltajes.map(v => parseFloat(v))) : null,
      min_voltaje_rango:
        voltajes.length > 0 ? Math.min(...voltajes.map(v => parseFloat(v))) : null,
    };

    let currentTemp =
      datosSensoresCargados.temperatura.length > 0
        ? parseFloat(datosSensoresCargados.temperatura.slice(-1)[0])
        : null;

    let currentPresion =
      datosSensoresCargados.presion.length > 0
        ? parseFloat(datosSensoresCargados.presion.slice(-1)[0])
        : null;

    let currentVoltaje =
      datosSensoresCargados.voltaje.length > 0
        ? parseFloat(datosSensoresCargados.voltaje.slice(-1)[0])
        : null;

    // Obtener los valores numéricos de los umbrales (usando el valor del input si existe, sino el valor por defecto)
    let currentUmbralAdvertencia =
      parseFloat(umbralAdvertenciaInput.value) || 85;
    let currentUmbralCritico = parseFloat(umbralCriticoInput.value) || 90;

    // Si el umbral de advertencia es mayor o igual al crítico, usamos el crítico para la advertencia.
    if (currentUmbralAdvertencia >= currentUmbralCritico) {
      console.warn(
        "El Umbral de Advertencia es mayor o igual al Crítico. Usando el Crítico para Advertencia.",
      );
      currentUmbralAdvertencia = currentUmbralCritico - 1; // Ajuste para que la lógica funcione mejor
    }

    if (intervaloActualizacion) {
      sonidoBipActualizacion.currentTime = 0;
      sonidoBipActualizacion.volume = 0.5;
      sonidoBipActualizacion.play().catch((e) => {
        console.error("No se pudo reproducir el sonido de actualización:", e);
      });
    }

    if (
      datosSensoresCargados.labels &&
      datosSensoresCargados.labels.length > 0
    ) {
      toggleExportButtons(true);
    }

    // --- LÓGICA DEL MARCADOR DE PICO Y DETALLE DE DATOS ---
    const maxTemp = datosSensoresCargados.max_temp_rango;
    const maxTempTime = datosSensoresCargados.hora_max_temp_rango;
    const annotation =
      myChart.options.plugins.annotation.annotations.maxTempPoint;

    // Control dinámico de las etiquetas del Eje X
    const xScales = myChart.options.scales.x;
    if (xScales) {
      xScales.ticks.display = mostrarPico;
      xScales.title.display = mostrarPico;
      xScales.title.text = "Fecha y Hora de Registro (Detalle Completo)";
    }

    if (mostrarPico && maxTemp !== null) {
      const maxIndex = datosSensoresCargados.temperatura.indexOf(
        String(maxTemp),
      ); // Busca el valor como string
      const maxLabel = datosSensoresCargados.labels[maxIndex];

      annotation.xValue = maxLabel;
      annotation.yValue = maxTemp;
      annotation.label.content = `Pico: ${safeToFixed(maxTemp)}°C (a las ${
        maxTempTime || "N/A"
      })`;
      annotation.display = true;
    } else {
      annotation.display = false;
    }
    // ------------------------------------------

    // Actualizar las dos líneas de umbral en la gráfica
    if (myChart.options.plugins.annotation.annotations.umbralAdvertenciaLine) {
      myChart.options.plugins.annotation.annotations.umbralAdvertenciaLine.value =
        currentUmbralAdvertencia;
      myChart.options.plugins.annotation.annotations.umbralAdvertenciaLine.label.content = `Umbral Advertencia: ${currentUmbralAdvertencia}°C`;
    }
    if (myChart.options.plugins.annotation.annotations.umbralCriticoLine) {
      myChart.options.plugins.annotation.annotations.umbralCriticoLine.value =
        currentUmbralCritico;
      myChart.options.plugins.annotation.annotations.umbralCriticoLine.label.content = `Umbral Crítico: ${currentUmbralCritico}°C`;
    }

    // Actualizar los datos de la gráfica
    myChart.data.labels = datosSensoresCargados.labels;
    myChart.data.datasets[0].data = datosSensoresCargados.temperatura;
    myChart.data.datasets[1].data = datosSensoresCargados.presion;
    myChart.data.datasets[2].data = datosSensoresCargados.voltaje;
    myChart.update();

    // --- Actualizar las tarjetas de estadísticas del RANGO ---
    const maxTempRange = datosSensoresCargados.max_temp_rango;
    const minPresionRange = datosSensoresCargados.min_presion_rango;
    const maxVoltajeRange = datosSensoresCargados.max_voltaje_rango;
    const minVoltajeRange = datosSensoresCargados.min_voltaje_rango;

    const horaMaxTemp = datosSensoresCargados.hora_max_temp_rango;
    const horaMinPresion = datosSensoresCargados.hora_min_presion_rango;

    tempMaxRangoElem.textContent =
      maxTempRange !== null ? `${safeToFixed(maxTempRange)}°C` : "--";

    presionMinRangoElem.textContent =
      minPresionRange !== null ? `${safeToFixed(minPresionRange)} hPa` : "--";

    const maxV = safeToFixed(maxVoltajeRange, 2);
    const minV = safeToFixed(minVoltajeRange, 2);

    voltajeRangoElem.textContent =
      (maxVoltajeRange !== null || minVoltajeRange !== null) &&
      (maxV !== "--" || minV !== "--")
        ? `${maxV}V / ${minV}V`
        : "--";

    // Se mantiene la clase de peligro si la temp. máxima del rango supera el umbral DINÁMICO CRÍTICO
    if (maxTempRange !== null && maxTempRange > currentUmbralCritico) {
      tempMaxRangoElem.classList.add("temp-peligro");
    } else {
      tempMaxRangoElem.classList.remove("temp-peligro");
    }

    // --- Actualizar las tarjetas de valores actuales y Tendencias ---
    if (
      currentTemp !== null &&
      currentPresion !== null &&
      currentVoltaje !== null
    ) {
      valorTempActualElem.textContent = `${safeToFixed(currentTemp, 1)}°C`;
      valorPresionActualElem.textContent = `${safeToFixed(
        currentPresion,
        1,
      )} hPa`;
      valorVoltajeActualElem.textContent = `${safeToFixed(
        currentVoltaje,
        2,
      )} V`;

      // Pasar ambos umbrales dinámicos
      setTemperatureCardStatus(
        currentTemp,
        currentUmbralAdvertencia,
        currentUmbralCritico,
      );

      showTrend(currentTemp, tempAnterior, tempTrendElem);
      showTrend(currentPresion, presionAnterior, presionTrendElem);
      showTrend(currentVoltaje, voltajeAnterior, voltajeTrendElem);

      tempAnterior = currentTemp;
      presionAnterior = currentPresion;
      voltajeAnterior = currentVoltaje;
    } else {
      // Limpiar si no hay datos actuales
      valorTempActualElem.textContent = "-- °C";
      valorPresionActualElem.textContent = "-- hPa";
      valorVoltajeActualElem.textContent = "-- V";

      showTrend(null, null, tempTrendElem);
      showTrend(null, null, presionTrendElem);
      showTrend(null, null, voltajeTrendElem);

      tempCardElem.classList.remove(
        "status-normal",
        "status-warning",
        "status-danger",
      );
      valorTempActualElem.classList.remove(
        "rojo",
        "amarillo",
        "titilando-texto",
      );
      tempAnterior = null;
      presionAnterior = null;
      voltajeAnterior = null;
    }

    // 4. --- LÓGICA DE ALARMA SONORA DE 2 NIVELES DINÁMICOS ---
    // PRIORIDAD ALARMA CRÍTICA (Umbral Crítico del usuario)
    if (currentTemp !== null && currentTemp >= currentUmbralCritico) {
      iniciarAlarmaSonora(
        "critica",
        currentTemp,
        currentUmbralAdvertencia,
        currentUmbralCritico,
      );
      // SIGUIENTE: ALARMA ADVERTENCIA (Umbral de Advertencia del usuario)
    } else if (
      currentTemp !== null &&
      currentTemp >= currentUmbralAdvertencia
    ) {
      iniciarAlarmaSonora(
        "advertencia",
        currentTemp,
        currentUmbralAdvertencia,
        currentUmbralCritico,
      );
    } else {
      detenerAlarmaSonora(
        currentTemp,
        currentUmbralAdvertencia,
        currentUmbralCritico,
      );
    }

    // 5. Reiniciamos el cronómetro incondicionalmente.
    iniciarCountdown();
  } catch (error) {
    // Lógica de manejo de errores
    console.error("No se pudieron cargar los datos de la gráfica:", error);

    toggleExportButtons(false);

    // Limpiar UI
    tempMaxRangoElem.textContent = "--";
    presionMinRangoElem.textContent = "--";
    voltajeRangoElem.textContent = "--";
    valorTempActualElem.textContent = "-- °C";
    valorPresionActualElem.textContent = "-- hPa";
    valorVoltajeActualElem.textContent = "-- V";
    myChart.data.labels = [];
    myChart.data.datasets.forEach((dataset) => {
      dataset.data = [];
    });
    myChart.update();

    contenedorAlerta.textContent =
      "Error al cargar los datos. Revisa la consola para más detalles.";
    contenedorAlerta.classList.add("alerta-peligro");
    contenedorAlerta.classList.remove("oculto");

    showTrend(null, null, tempTrendElem);
    showTrend(null, null, presionTrendElem);
    showTrend(null, null, voltajeTrendElem);
    tempAnterior = null;
    presionAnterior = null;
    voltajeAnterior = null;
    tempCardElem.classList.remove(
      "status-normal",
      "status-warning",
      "status-danger",
    );
    valorTempActualElem.classList.remove("rojo", "amarillo", "titilando-texto");
    detenerAlarmaSonora(null, null, null);

    // Reiniciamos el contador para el próximo reintento.
    iniciarCountdown();
  }
}

// 5. --- LÓGICA DEL TEMA OSCURO ---

/**
 * Actualiza los colores de la grilla de la gráfica para que contraste mejor.
 */
function updateChartTheme(isDarkMode) {
  const gridColor = isDarkMode
    ? "rgba(255, 255, 255, 0.15)"
    : "rgba(0, 0, 0, 0.1)";

  if (myChart && myChart.options && myChart.options.scales) {
    if (myChart.options.scales.y) {
      myChart.options.scales.y.grid.color = gridColor;
    }
    if (myChart.options.scales.y1) {
      myChart.options.scales.y1.grid.color = gridColor;
    }
    if (myChart.options.scales.x) {
      myChart.options.scales.x.grid.color = gridColor;
    }
    myChart.update();
  }
}

/**
 * Alterna entre el modo claro y oscuro y guarda la preferencia.
 */
function toggleTheme() {
  const body = document.body;
  body.classList.toggle("dark-mode");
  const isDarkMode = body.classList.contains("dark-mode");

  themeToggleButton.innerHTML = isDarkMode
    ? '<i class="fa-solid fa-sun"></i> Tema Claro'
    : '<i class="fa-solid fa-moon"></i> Tema Oscuro';

  localStorage.setItem("theme", isDarkMode ? "dark" : "light");
  updateChartTheme(isDarkMode);
}

/**
 * Carga la preferencia de tema guardada al iniciar.
 */
function loadTheme() {
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme === "dark") {
    document.body.classList.add("dark-mode");
  }
  const isDarkMode = document.body.classList.contains("dark-mode");
  if (themeToggleButton) {
    themeToggleButton.innerHTML = isDarkMode
      ? '<i class="fa-solid fa-sun"></i> Tema Claro'
      : '<i class="fa-solid fa-moon"></i> Tema Oscuro';
  }
  updateChartTheme(isDarkMode);
}

// 7. --- LÓGICA PARA CARGAR Y ANALIZAR ARCHIVOS TXT ---

/**
 * Procesa el contenido de texto del archivo y actualiza la gráfica.
 * ASUME FORMATO: Fecha, Temperatura, Presion, Voltaje (separados por coma, ; o espacio)
 */
function procesarDatosArchivo(texto) {
  const lineas = texto.split('\n');
  const labels = [];
  const temps = [];
  const presiones = [];
  const voltajes = [];

  lineas.forEach((linea) => {
    linea = linea.trim();
    if (!linea) return;

    // Separar por coma según el formato: Fecha, Temp, Presion, Voltaje
    const partes = linea.split(',');

    // Validación básica: necesitamos al menos fecha y 3 valores
    if (partes.length >= 4) {
      // Asumimos orden: Fecha, Temp, Presion, Voltaje
      // Si tu archivo tiene otro orden, cambia los índices [1], [2], [3]
      labels.push(partes[0].trim());
      temps.push(parseFloat(partes[1]));
      presiones.push(parseFloat(partes[2]));
      voltajes.push(parseFloat(partes[3]));
    }
  });

  if (labels.length === 0) {
    alert("No se pudieron extraer datos válidos del archivo. Verifica el formato.");
    return;
  }

  // Actualizar el objeto global de datos (para que funcionen los reportes PDF)
  datosSensoresCargados = {
    labels: labels,
    temperatura: temps,
    presion: presiones,
    voltaje: voltajes,
    max_temp_rango: Math.max(...temps),
    min_presion_rango: Math.min(...presiones),
    max_voltaje_rango: Math.max(...voltajes),
    min_voltaje_rango: Math.min(...voltajes),
    hora_max_temp_rango: null, // Opcional: calcular si es necesario
    hora_min_presion_rango: null
  };

  // Actualizar la gráfica
  myChart.data.labels = labels;
  myChart.data.datasets[0].data = temps;
  myChart.data.datasets[1].data = presiones;
  myChart.data.datasets[2].data = voltajes;
  myChart.update();

  // Actualizar tarjetas de resumen con promedios o últimos valores
  valorTempActualElem.textContent = "Archivo";
  valorPresionActualElem.textContent = "Cargado";
  valorVoltajeActualElem.textContent = "Modo Análisis";
  
  // Desactivar alarmas en modo análisis
  detenerAlarmaSonora(null, null, null);
  
  toggleExportButtons(true);
  alert(`Archivo cargado exitosamente: ${labels.length} registros encontrados.`);
}

/**
 * Maneja la selección de un archivo del servidor.
 */
async function cargarArchivoSeleccionado() {
  const nombreArchivo = selectorArchivoAnalisis.value;
  
  if (!nombreArchivo) {
    // Si vuelve a la opción vacía, reactivar actualización automática si corresponde
    controlarActualizacion();
    return;
  }

  // Detener actualizaciones automáticas mientras se analiza un archivo
  if (intervaloActualizacion) {
    clearInterval(intervaloActualizacion);
    intervaloActualizacion = null;
  }
  detenerCountdown();
  updateTimerDisplay.textContent = "Modo Análisis de Archivo (Auto-update pausado)";

  const url = `http://marant.medianewsonline.com/Archivos_SDCards/uploads/${nombreArchivo}`;
  
  try {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`Error al cargar archivo: ${response.status}`);
    const texto = await response.text();
    procesarDatosArchivo(texto);
  } catch (error) {
    console.error(error);
    alert("Error al descargar el archivo: " + error.message);
  }
}

/**
 * Carga la lista de archivos .txt disponibles para una MAC específica.
 */
function cargarArchivosHistoricos(macAddress) {
  const selector = document.getElementById("selector-archivo-analisis");
  // Buscar el contenedor padre para ocultarlo/mostrarlo
  const container = selector ? selector.closest('.intervalo-group') : null;
  
  if (!selector) return;

  // Limpiar y reiniciar
  selector.innerHTML = '<option value="">-- Seleccionar Archivo --</option>';
  if (container) container.style.display = 'none'; // Ocultar por defecto

  if (!macAddress) return;

  fetch(`php/listar_archivos_por_mac.php?mac=${encodeURIComponent(macAddress)}`)
    .then(response => response.json())
    .then(files => {
      if (files.length > 0) {
          files.forEach(file => {
            const option = document.createElement('option');
            option.value = file;
            // Formatear nombre: quitar prefijo MAC=XX-XX..._ (Soporta separador _ o -)
            option.textContent = file.replace(/^MAC=.{17}[-_]/, ''); 
            selector.appendChild(option);
          });
          // Mostrar el selector solo si hay archivos
          if (container) {
              container.style.display = 'flex';
              // Agregar animación si se desea
              container.classList.add('animate__animated', 'animate__fadeIn');
          }
      }
    })
    .catch(error => console.error('Error cargando lista de archivos:', error));
}

// 6. --- MANEJO DE EVENTOS Y LLAMADA INICIAL ---

/**
 * Función que se ejecuta cuando cambia un filtro o el intervalo.
 */
const aplicarFiltros = () => {
  const umbralAdvertencia = umbralAdvertenciaInput.value || 85;
  const umbralCritico = umbralCriticoInput.value || 90;
  const fecha = fechaDiaInput.value;
  const mostrarPico = tempCriticaInput.checked;

  if (fecha) {
    actualizarGrafica(
      fecha,
      fecha,
      umbralAdvertencia,
      umbralCritico,
      mostrarPico,
    );
  } else {
    actualizarGrafica(
      null,
      null,
      umbralAdvertencia,
      umbralCritico,
      mostrarPico,
    );
  }
};

/**
 * Inicia o detiene la actualización automática de la gráfica
 * en función de si la fecha seleccionada es hoy y el intervalo.
 */
const controlarActualizacion = async () => {
  // Detener cualquier intervalo de actualización automático previo
  if (intervaloActualizacion) {
    clearInterval(intervaloActualizacion);
    intervaloActualizacion = null;
  }

  const fechaSeleccionada = fechaDiaInput.value;
  const hoy = new Date().toISOString().slice(0, 10);
  INTERVALO_ACTUALIZACION_MS = parseInt(selectIntervalo.value, 10);

  // Determinar si la actualización automática debe estar activa
  const debeActualizarAutomaticamente =
    (!fechaSeleccionada || fechaSeleccionada === hoy) &&
    INTERVALO_ACTUALIZACION_MS > 0;

  // Cargar los umbrales de alarma y luego los datos de la gráfica
  if (typeof actualizarAlarmas === "function") {
    await actualizarAlarmas();
  }
  aplicarFiltros(); // Esto siempre llama a actualizarGrafica para la selección actual

  if (debeActualizarAutomaticamente) {
    // Si la auto-actualización debe estar activa, se inicia el ciclo.
    // La función `iniciarCountdown` será llamada desde `actualizarGrafica`.
    console.log(
      `✅ Iniciando actualización automática (cada ${
        INTERVALO_ACTUALIZACION_MS / 1000
      }s).`,
    );
    intervaloActualizacion = setInterval(() => {
      // Las llamadas subsecuentes también cargan filtros.
      aplicarFiltros();
    }, INTERVALO_ACTUALIZACION_MS);
  } else {
    // Si no debe haber auto-actualización (fecha pasada o intervalo 0).
    console.log("ℹ️ Actualización automática deshabilitada para esta vista.");
    detenerCountdown(); // Detiene el contador.
    
    // Se establece un mensaje estático claro para el usuario.
    if (fechaSeleccionada) {
        updateTimerDisplay.textContent = "Mostrando datos históricos";
    } else { // El único otro caso es que el intervalo sea 0.
        updateTimerDisplay.textContent = "Actualización automática deshabilitada";
    }
  }
};

// Asignamos los listeners a los inputs
fechaDiaInput.addEventListener("change", controlarActualizacion);
// NUEVO: Listener para el select de intervalo
selectIntervalo.addEventListener("change", controlarActualizacion);
// Usamos 'change' para los inputs tipo number

// NUEVO: Listener para el selector de archivos
if (selectorArchivoAnalisis) {
  selectorArchivoAnalisis.addEventListener("change", cargarArchivoSeleccionado);
}

umbralAdvertenciaInput.addEventListener("change", aplicarFiltros);
umbralCriticoInput.addEventListener("change", aplicarFiltros);
tempCriticaInput.addEventListener("change", aplicarFiltros);

themeToggleButton.addEventListener("click", toggleTheme);
document
  .getElementById("btnExportarPDF")
  .addEventListener("click", exportChartToPDF);
document
  .getElementById("btnReporteCritico")
  .addEventListener("click", exportarReporteCritico);
document
  .getElementById("btnReporteSoloCritico")
  .addEventListener("click", exportarReporteSoloCritico);

// Llama a las funciones de carga
loadTheme();
// controlarActualizacion(); // Se llama desde Graficas.php después de cargar los equipos
// cargarListaArchivos(); // ELIMINADO: Se llama dinámicamente desde Graficas.php
