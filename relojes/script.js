// EL ARCHIVO DEBE GUARDARSE CON CODIFICACIÓN UTF-8 PARA SOPORTAR TILDES Y Ñ
// Get current sensor readings when the page loads  
window.addEventListener('load', getReadings);

// --- La URL de tu script PHP ---
const PHP_API_URL = "http://marant.medianewsonline.com/php/obtener_datos_actuales.php"; 
const REFRESH_INTERVAL_MS = 5000; // Recargar datos cada 5 segundos

// Colores del semáforo
const COLOR_ROJO = "rgba(200, 50, 50, .75)";    // Malo/Peligroso
const COLOR_AMARILLO = "rgba(255, 165, 0, .5)"; // Advertencia/Marginal
const COLOR_VERDE = "rgba(0, 150, 0, .5)";      // Normal/Óptimo

// --- PARÁMETROS DE ESTILO DASHBOARD ---
const COLOR_DARK_PLATE = "#1e1e1e";       // Fondo oscuro de la placa del medidor
const COLOR_DARK_FRAME = "#333333";       // Color del marco/bisel
const COLOR_DIGITAL_BG = "rgba(0, 0, 0, 0.7)"; // Fondo oscuro y semi-transparente para la lectura digital
const COLOR_DIGITAL_TEXT = "#00ff41";     // Color verde terminal por defecto

// --- COLORES NEÓN PARA AGUJAS Y RESPLANDOR ---
const NEON_TEMP_COLOR = "#FF6347";      // Naranja-Rojo brillante (Temp)
const NEON_TEMP_SHADOW_COLOR = "rgba(255, 99, 71, 0.8)"; 

const NEON_PRES_COLOR = "#00FFFF";      // Cian brillante (Presión)
const NEON_PRES_SHADOW_COLOR = "rgba(0, 255, 255, 0.8)"; 

const NEON_VOLT_COLOR = "#9933FF";      // Púrpura brillante (Voltaje)
const NEON_VOLT_SHADOW_COLOR = "rgba(153, 51, 255, 0.8)"; 

// --- COLORES NEÓN PARA LAS ESCALAS (NÚMEROS Y MARCAS) ---
const NEON_TEMP_SCALE_COLOR = "#FFB8B0"; 
const NEON_PRES_SCALE_COLOR = "#BFFFFF"; 
const NEON_VOLT_SCALE_COLOR = "#D5A3FF"; 

// --- COLORES DEGRADADOS PARA EL FONDO DE LA PLACA ---
const GRADIENT_TEMP_END = "#403030"; 
const GRADIENT_PRES_END = "#304040"; 
const GRADIENT_VOLT_END = "#403040"; 


// 1. Create Temperature Gauge (Radial Gauge) - Rango: 0 a 120 C
var gaugeTemp = new RadialGauge({
  renderTo: 'gauge-temperature',
  units: "Temperatura C", 
  minValue: 0, 
  maxValue: 120, 
  valueDec: 2,
  valueInt: 2,
  majorTicks: [ "0", "10", "20", "30", "40", "50", "60", "70", "80", "90", "100", "110", "120" ], 
  minorTicks: 5,
  strokeTicks: true,
  // ZONAS DE SEMÁFORO
  highlights: [ 
    { "from": 0, "to": 60, "color": COLOR_ROJO },       
    { "from": 60, "to": 90, "color": COLOR_VERDE },     
    { "from": 90, "to": 100, "color": COLOR_AMARILLO }, 
    { "from": 100, "to": 120, "color": COLOR_ROJO }     
  ],
  
  // ESTILOS VISUALES
  colorPlate: COLOR_DARK_PLATE,
  colorPlateEnd: GRADIENT_TEMP_END, 
  colorFrame: COLOR_DARK_FRAME,
  borderOuterWidth: 3,
  borderInnerWidth: 0,
  borderMiddleWidth: 0,
  borderShadowWidth: 0,
  borders: true, 
  
  // COLORES DE ESCALA NEÓN
  colorMajorTicks: NEON_TEMP_SCALE_COLOR,
  colorMinorTicks: NEON_TEMP_SCALE_COLOR,
  colorNumbers: NEON_TEMP_SCALE_COLOR,
  colorUnits: NEON_TEMP_SCALE_COLOR,
  colorStrokeTicks: NEON_TEMP_SCALE_COLOR, 

  // Lectura Digital 
  valueBox: true,
  fontValue: "DSDigital", 
  fontValueSize: 36, 
  colorValueBoxRect: COLOR_DIGITAL_BG,
  colorValueBoxRectEnd: COLOR_DIGITAL_BG,
  colorValueBoxBackground: COLOR_DIGITAL_BG,
  colorValueText: COLOR_DIGITAL_TEXT, 

  // Aguja Neón
  needleType: "arrow", 
  colorNeedle: NEON_TEMP_COLOR, 
  colorNeedleEnd: NEON_TEMP_COLOR,
  needleWidth: 5, 
  needleCircleSize: 7, 
  colorNeedleCircleOuter: NEON_TEMP_COLOR,
  needleCircleOuter: true,
  needleCircleInner: false,
  needleShadow: true, 
  colorNeedleShadowUp: NEON_TEMP_SHADOW_COLOR, 

  animationDuration: 1500,
  animationRule: "linear"
}).draw();
  
// 2. Create Pressure Gauge (Radial Gauge) - Rango: 0 a 160 PSI
var gaugePres = new RadialGauge({
  renderTo: 'gauge-humidity', 
  units: "Presión PSI", 
  minValue: 0, 
  maxValue: 160, 
  valueInt: 2,
  majorTicks: [ "0", "20", "40", "60", "80", "100", "120", "140", "160" ], 
  minorTicks: 5,
  strokeTicks: true,
  // ZONAS DE SEMÁFORO
  highlights: [ 
    { "from": 0, "to": 15, "color": COLOR_ROJO },      
    { "from": 15, "to": 65, "color": COLOR_VERDE },    
    { "from": 65, "to": 160, "color": COLOR_AMARILLO }       
  ],
  
  // ESTILOS VISUALES
  colorPlate: COLOR_DARK_PLATE,
  colorPlateEnd: GRADIENT_PRES_END, 
  colorFrame: COLOR_DARK_FRAME,
  borderOuterWidth: 3,
  borderInnerWidth: 0,
  borderMiddleWidth: 0,
  borders: true, 

  // COLORES DE ESCALA NEÓN
  colorMajorTicks: NEON_PRES_SCALE_COLOR,
  colorMinorTicks: NEON_PRES_SCALE_COLOR,
  colorNumbers: NEON_PRES_SCALE_COLOR,
  colorUnits: NEON_PRES_SCALE_COLOR, 
  colorStrokeTicks: NEON_PRES_SCALE_COLOR, 
  
  // Lectura Digital 
  valueBox: true,
  fontValue: "DSDigital", 
  fontValueSize: 36, 
  colorValueBoxRect: COLOR_DIGITAL_BG,
  colorValueBoxRectEnd: COLOR_DIGITAL_BG,
  colorValueBoxBackground: COLOR_DIGITAL_BG,
  colorValueText: COLOR_DIGITAL_TEXT,

  // Aguja Neón
  needleType: "arrow", 
  colorNeedle: NEON_PRES_COLOR, 
  colorNeedleEnd: NEON_PRES_COLOR,
  needleWidth: 5,
  needleCircleSize: 7, 
  colorNeedleCircleOuter: NEON_PRES_COLOR,
  needleCircleOuter: true,
  needleCircleInner: false,
  needleShadow: true, 
  colorNeedleShadowUp: NEON_PRES_SHADOW_COLOR, 

  animationDuration: 1500,
  animationRule: "linear"
}).draw();

// 3. Create Voltage Gauge (Radial Gauge) - Rango: 0 a 30 V
var gaugeVolt = new RadialGauge({
    renderTo: 'gauge-voltage',
    units: "Voltaje (V)",
    minValue: 0,
    maxValue: 30, 
    valueInt: 2,
    majorTicks: [ "0", "5", "10", "15", "20", "25", "30" ], 
    minorTicks: 5,
    strokeTicks: true,
    // ZONAS DE SEMÁFORO
    highlights: [ 
        { "from": 0, "to": 12, "color": COLOR_ROJO },      
        { "from": 12, "to": 14, "color": COLOR_VERDE },    
        { "from": 14, "to": 24, "color": COLOR_ROJO },     
        { "from": 24, "to": 28, "color": COLOR_VERDE },    
        { "from": 28, "to": 30, "color": COLOR_ROJO }      
    ],
    
    // ESTILOS VISUALES
    colorPlate: COLOR_DARK_PLATE,
    colorPlateEnd: GRADIENT_VOLT_END, 
    colorFrame: COLOR_DARK_FRAME,
    borderOuterWidth: 3,
    borderInnerWidth: 0,
    borderMiddleWidth: 0,
    borders: true, 

    // COLORES DE ESCALA NEÓN
    colorMajorTicks: NEON_VOLT_SCALE_COLOR,
    colorMinorTicks: NEON_VOLT_SCALE_COLOR,
    colorNumbers: NEON_VOLT_SCALE_COLOR,
    colorUnits: NEON_VOLT_SCALE_COLOR,
    colorStrokeTicks: NEON_VOLT_SCALE_COLOR,
    
    // Lectura Digital 
    valueBox: true,
    fontValue: "DSDigital", 
    fontValueSize: 36, 
    colorValueBoxRect: COLOR_DIGITAL_BG,
    colorValueBoxRectEnd: COLOR_DIGITAL_BG,
    colorValueBoxBackground: COLOR_DIGITAL_BG,
    colorValueText: COLOR_DIGITAL_TEXT,
    
    // Aguja Neón
    needleType: "arrow",
    colorNeedle: NEON_VOLT_COLOR, 
    colorNeedleEnd: NEON_VOLT_COLOR,
    needleWidth: 5,
    needleCircleSize: 7,
    colorNeedleCircleOuter: NEON_VOLT_COLOR,
    needleCircleOuter: true,
    needleCircleInner: false,
    needleShadow: true, 
    colorNeedleShadowUp: NEON_VOLT_SHADOW_COLOR, 

    animationDuration: 1500,
    animationRule: "linear"
}).draw();


// --- FUNCIÓN DE VERIFICACIÓN DE ALERTA ---
function isAlert(gauge, value) {
    if (value === null) return false;

    // 1. TEMPERATURA (0 a 120 C)
    if (gauge === gaugeTemp) {
        return value < 20 || value > 80;
    }
    
    // 2. PRESIÓN (0 a 160 PSI)
    else if (gauge === gaugePres) {
        return value < 80 || value >= 140; 
    }
    
    // 3. VOLTAJE (0 a 30 V)
    else if (gauge === gaugeVolt) {
        return (value < 12 || (value > 14 && value < 24) || value > 28);
    }

    return false;
}


// --- FUNCIÓN PRINCIPAL PARA OBTENER DATOS ---
function getReadings(){
  var xhr = new XMLHttpRequest();
  xhr.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      try {
        var myObj = JSON.parse(this.responseText);
        
        if (myObj.error) {
          console.error("Error del servidor PHP:", myObj.error);
          document.getElementById('last-update').innerHTML = "ERROR: " + myObj.error;
          return;
        }

        var temp = myObj.temperatura;
        var presion = myObj.presion; 
        var voltaje = myObj.voltaje;
        var fechaHora = myObj.fecha_hora;
        
        // --- FUNCIÓN INTERNA PARA ACTUALIZAR MEDIDOR Y ALERTA ---
        function updateGauge(gauge, value, canvasId) {
            if (value !== null) {
                // 1. Establecer el valor en el medidor
                gauge.value = value;
                
                // 2. Obtener el elemento CANVAS del HTML
                var canvasEl = document.getElementById(canvasId);
                
                if (canvasEl) {
                    if (isAlert(gauge, value)) {
                        // --- MODO ALERTA (PELIGRO) ---
                        
                        // Añadir la clase CSS para el parpadeo del borde
                        canvasEl.classList.add('alert-active');
                        
                        // Cambiar el color del texto digital interno a ROJO
                        // Usamos update() de la librería en lugar de style directo
                        gauge.update({ colorValueText: "#FF0000" });

                    } else {
                        // --- MODO NORMAL ---
                        
                        // Quitar la clase CSS
                        canvasEl.classList.remove('alert-active');
                        
                        // Restaurar el color del texto digital original
                        gauge.update({ colorValueText: COLOR_DIGITAL_TEXT });
                    }
                }
            }
        }
        
        // Llamada a la función de actualización
        // Nota: Asegúrate de que los IDs coincidan con los de tu HTML
        updateGauge(gaugeTemp, temp, 'gauge-temperature');
        updateGauge(gaugePres, presion, 'gauge-humidity'); 
        updateGauge(gaugeVolt, voltaje, 'gauge-voltage');

        // Actualizar la etiqueta de última actualización
        if (fechaHora !== null) {
            document.getElementById('last-update').innerHTML = fechaHora;
        }

      } catch (e) {
        console.error("Error al parsear JSON:", e);
      }
    } else if (this.readyState == 4) {
      console.error("Error de conexión:", this.status);
      document.getElementById('last-update').innerHTML = "Sin conexión (" + this.status + ")";
    }
  }; 
  
  xhr.open("GET", PHP_API_URL, true);
  xhr.send();

  setTimeout(getReadings, REFRESH_INTERVAL_MS); 
}