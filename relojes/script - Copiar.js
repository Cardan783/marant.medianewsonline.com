// Get current sensor readings when the page loads  
window.addEventListener('load', getReadings);

// --- La URL de tu script PHP ---
// Si tu index.html y obtener_datos_actuales.php están en la misma carpeta del servidor, esto funciona.
const PHP_API_URL = "http://marant.medianewsonline.com/php/obtener_datos_actuales.php"; 
const REFRESH_INTERVAL_MS = 5000; // Recargar datos cada 5 segundos

// Colores del semáforo
const COLOR_ROJO = "rgba(200, 50, 50, .75)";    // Malo/Peligroso
const COLOR_AMARILLO = "rgba(255, 165, 0, .5)"; // Advertencia/Marginal (No usado en este ajuste, pero mantenido por si acaso)
const COLOR_VERDE = "rgba(0, 150, 0, .5)";      // Normal/Óptimo


// Create Temperature Gauge (Radial Gauge) - Rango: 0 a 120 C
var gaugeTemp = new RadialGauge({
  renderTo: 'gauge-temperature',
  width: 300, 
  height: 300, 
  units: "Temperatura C",
  minValue: 0, 
  maxValue: 120, 
  colorValueBoxRect: "#049faa",
  colorValueBoxRectEnd: "#049faa",
  colorValueBoxBackground: "#f1fbfc",
  valueDec: 2,
  valueInt: 2,
  majorTicks: [ "0", "10", "20", "30", "40", "50", "60", "70", "80", "90", "100", "110", "120" ], // <-- MODIFICADO: De 10 en 10
  minorTicks: 5,
  strokeTicks: true,
  // --- ZONAS DE SEMÁFORO PARA TEMPERATURA (0-120) - MANTENIDAS DE LA VEZ ANTERIOR ---
  highlights: [ 
    { "from": 0, "to": 20, "color": COLOR_ROJO },       // Peligro (Bajo): 0 a 20 C
    { "from": 20, "to": 80, "color": COLOR_VERDE },     // Óptimo: 20 a 80 C
    { "from": 80, "to": 100, "color": COLOR_AMARILLO }, // Advertencia: 80 a 100 C
    { "from": 100, "to": 120, "color": COLOR_ROJO }     // Peligro (Alto): 100 a 120 C
  ],
  // ----------------------------------------------------
  colorPlate: "#fff",
  borderShadowWidth: 0,
  borders: false,
  // --- CONFIGURACIÓN DE AGUJA ---
  needleType: "arrow", 
  colorNeedle: "#CC2936", 
  colorNeedleEnd: "#CC2936",
  needleWidth: 3, 
  needleCircleSize: 5, 
  colorNeedleCircleOuter: "#CC2936",
  needleCircleOuter: true,
  needleCircleInner: false,
  // ----------------------------------------
  animationDuration: 1500,
  animationRule: "linear"
}).draw();
  
// Create Pressure Gauge (Radial Gauge) - Rango: 0 a 6
var gaugePres = new RadialGauge({
  renderTo: 'gauge-humidity', 
  width: 300,
  height: 300,
  units: "Presión", 
  minValue: 0, 
  maxValue: 6, 
  colorValueBoxRect: "#049faa",
  colorValueBoxRectEnd: "#049faa",
  colorValueBoxBackground: "#f1fbfc",
  valueInt: 2,
  majorTicks: [ "0", "1", "2", "3", "4", "5", "6" ], 
  minorTicks: 5,
  strokeTicks: true,
  // --- ZONAS DE SEMÁFORO PARA PRESIÓN (0-6) - MODIFICADO ---
  highlights: [ 
    { "from": 0, "to": 2, "color": COLOR_ROJO },      // Rojo: 0 a 2
    { "from": 2, "to": 5, "color": COLOR_VERDE },    // Verde: 2 a 5
    { "from": 5, "to": 6, "color": COLOR_ROJO }       // Rojo: 5 a 6
  ],
  // ---------------------------------------------
  colorPlate: "#fff",
  borderShadowWidth: 0,
  borders: false,
  // --- CONFIGURACIÓN DE AGUJA ---
  needleType: "arrow", 
  colorNeedle: "#007F80",
  colorNeedleEnd: "#007F80",
  needleWidth: 3, 
  needleCircleSize: 5, 
  colorNeedleCircleOuter: "#007F80",
  needleCircleOuter: true,
  needleCircleInner: false,
  // ----------------------------------------
  animationDuration: 1500,
  animationRule: "linear"
}).draw();

// Create Voltage Gauge (Radial Gauge) - Rango: 0 a 30 V
var gaugeVolt = new RadialGauge({
    renderTo: 'gauge-voltage',
    width: 300,
    height: 300,
    units: "Voltaje (V)",
    minValue: 0,
    maxValue: 30, 
    colorValueBoxRect: "#049faa",
    colorValueBoxRectEnd: "#049faa",
    colorValueBoxBackground: "#f1fbfc",
    valueInt: 2,
    majorTicks: [ "0", "5", "10", "15", "20", "25", "30" ], 
    minorTicks: 5,
    strokeTicks: true,
    // --- ZONAS DE SEMÁFORO PARA VOLTAJE (0-30 V) - MODIFICADO ---
    highlights: [ 
        { "from": 0, "to": 12, "color": COLOR_ROJO },      // Rojo: 0 a 12
        { "from": 12, "to": 14, "color": COLOR_VERDE },    // Verde: 12 a 14
        { "from": 14, "to": 24, "color": COLOR_ROJO },     // Rojo: 14 a 24
        { "from": 24, "to": 28, "color": COLOR_VERDE },    // Verde: 24 a 28
        { "from": 28, "to": 30, "color": COLOR_ROJO }      // Rojo: 28 a 30
    ],
    // ------------------------------------------------
    colorPlate: "#fff",
    borderShadowWidth: 0,
    borders: false,
    // --- CONFIGURACIÓN DE AGUJA ---
    needleType: "arrow",
    colorNeedle: "#4682B4",
    colorNeedleEnd: "#4682B4",
    needleWidth: 3,
    needleCircleSize: 5,
    colorNeedleCircleOuter: "#4682B4",
    needleCircleOuter: true,
    needleCircleInner: false,
    // ----------------------------------------------------------
    animationDuration: 1500,
    animationRule: "linear"
}).draw();


// Función para obtener lecturas del PHP (SIN CAMBIOS)
function getReadings(){
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      try {
        var myObj = JSON.parse(this.responseText);
        // ... (resto de la lógica) ...
        
        // Verifica si hay error en la respuesta del PHP
        if (myObj.error) {
          console.error("Error del servidor PHP:", myObj.error);
          document.getElementById('last-update').innerHTML = "ERROR: " + myObj.error;
          return;
        }

        var temp = myObj.temperatura;
        var presion = myObj.presion; 
        var voltaje = myObj.voltaje;
        var fechaHora = myObj.fecha_hora;
        
        // Asignar valores a los indicadores
        if (temp !== null) {
            gaugeTemp.value = temp;
        }
        if (presion !== null) {
            gaugePres.value = presion; 
        }
        if (voltaje !== null) {
            gaugeVolt.value = voltaje; 
        }
        
        // Actualizar la etiqueta de última actualización
        if (fechaHora !== null) {
            document.getElementById('last-update').innerHTML = fechaHora;
        }

      } catch (e) {
        console.error("Error al parsear JSON o al actualizar los indicadores:", e);
      }
    } else if (this.readyState == 4) {
      console.error("Error al obtener datos. Estado HTTP:", this.status);
      document.getElementById('last-update').innerHTML = "Error de conexión (" + this.status + ")";
    }
  }; 
  
  // Realiza la solicitud GET al archivo PHP
  xhr.open("GET", PHP_API_URL, true);
  xhr.send();

  // Recargar los datos para simular 'live data'
  setTimeout(getReadings, REFRESH_INTERVAL_MS); 
}