document.addEventListener('DOMContentLoaded', () => {

    // La URL debe apuntar al script PHP que devuelve los datos actuales. 
    // La he ajustado para que coincida con el nombre de archivo que nos has proporcionado.
    const URL_DATOS = 'marant.medianewsonline.com/php/obtener_datos_actuales.php';

    // --- 1. CONFIGURACIÓN DE MEDIDORES ---

    // Configuración Base para reutilizar
    const baseConfig = {
        renderTo: null,
        width: 250,
        height: 250,
        units: "",
        minValue: 0,
        maxValue: 100,
        majorTicks: [],
        minorTicks: 2,
        strokeTicks: true,
        highlights: [],
        colorPlate: "#fff",
        borderShadowWidth: 0,
        borders: true,
        needleType: "arrow",
        needleWidth: 2,
        needleCircleSize: 7,
        needleCircleOuter: true,
        needleCircleInner: false,
        valueBox: true,
        animationDuration: 500,
        animationRule: "linear",
        fontValue: "Arial",
        valueDecimals: 1,
    };

    // --- TEMPERATURA (20 °C a 125 °C) ---
    const configTemp = {
        ...baseConfig,
        renderTo: 'gauge-temp',
        units: "°C",
        minValue: 20,
        maxValue: 125,
        majorTicks: [20, 35, 50, 65, 80, 95, 110, 125], // Ticks cada 15 grados aprox.
        highlights: [
            { from: 20, to: 90, color: "rgba(0,180,0, .3)" }, // Zona verde/segura
            { from: 90, to: 110, color: "rgba(255,165,0, .6)" }, // Zona naranja/alerta
            { from: 110, to: 125, color: "rgba(255,0,0, .6)" }  // Zona roja/peligro
        ],
        colorValueBoxBackground: "#e74c3c"
    };

    // --- PRESIÓN (0 Bar a 6 Bar) ---
    // NOTA: Se cambiaron las unidades a Bar para Canvas Gauges, asumiendo un rango típico de motor/sistema.
    const configPresion = {
        ...baseConfig,
        renderTo: 'gauge-presion',
        units: "Bar", 
        minValue: 0,
        maxValue: 6,
        majorTicks: [0, 1, 2, 3, 4, 5, 6], // Ticks cada 1 Bar
        highlights: [
            { from: 0, to: 5, color: "rgba(0,180,0, .3)" }, // Zona verde/segura
            { from: 5, to: 6, color: "rgba(255,165,0, .6)" }  // Zona naranja/alerta
        ],
        colorValueBoxBackground: "#3498db"
    };

    // --- VOLTAJE (10 V a 30 V) ---
    const configVoltaje = {
        ...baseConfig,
        renderTo: 'gauge-voltaje',
        units: "V",
        minValue: 10,
        maxValue: 30,
        majorTicks: [10, 15, 20, 25, 30], // Ticks cada 5 Voltios
        highlights: [
            { from: 10, to: 12, color: "rgba(255,165,0, .6)" }, // Alerta bajo voltaje
            { from: 12, to: 28, color: "rgba(0,180,0, .3)" }, // Zona verde/segura
            { from: 28, to: 30, color: "rgba(255,165,0, .6)" }  // Alerta alto voltaje
        ],
        colorValueBoxBackground: "#2ecc71"
    };

    // --- 2. INSTANCIACIÓN DE MEDIDORES ---
    const gaugeTemp = new RadialGauge(configTemp).draw();
    const gaugePresion = new RadialGauge(configPresion).draw();
    const gaugeVoltaje = new RadialGauge(configVoltaje).draw();

    // --- 3. FUNCIÓN DE ACTUALIZACIÓN DE DATOS ---

    async function actualizarMedidores() {
        try {
            // Se usa HTTP como solicitaste. Cuidado con el error de 'Contenido Mixto'
            const response = await fetch(`http://${URL_DATOS}`); //
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const data = await response.json();  
            
            // **CORRECCIÓN CLAVE:** Usar las claves correctas que devuelve obtener_datos_actuales.php
            const temp = parseFloat(data.temperatura) || 0;
            const presion = parseFloat(data.presion) || 0;
            const voltaje = parseFloat(data.voltaje) || 0;

            // 1. Temperatura
            gaugeTemp.value = temp;
            document.getElementById('valorTempActual').textContent = `${temp.toFixed(1)} °C`;

            // 2. Presión
            gaugePresion.value = presion;
            // Se muestra Bar, acorde al rango del medidor
            document.getElementById('valorPresionActual').textContent = `${presion.toFixed(1)} Bar`; 

            // 3. Voltaje
            gaugeVoltaje.value = voltaje;
            document.getElementById('valorVoltajeActual').textContent = `${voltaje.toFixed(1)} V`;

        } catch (error) {
            console.error('Error al obtener o procesar datos:', error);
            document.getElementById('valorTempActual').textContent = 'Error';
            document.getElementById('valorPresionActual').textContent = 'Error';
            document.getElementById('valorVoltajeActual').textContent = 'Error';
        }
    }

    // Actualizar al cargar y luego cada 5 segundos
    actualizarMedidores();
    setInterval(actualizarMedidores, 5000); 
});