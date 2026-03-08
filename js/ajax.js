/**
 * ajax.js
 * Lógica para leer archivos JSON generados por los dispositivos (ESP32)
 * y actualizar las tarjetas en panel_general.php en tiempo real.
 */

document.addEventListener("DOMContentLoaded", function() {
    
    // --- CONFIGURACIÓN ---
    const INTERVALO_ACTUALIZACION = 2000; // Actualizar cada 2 segundos
    // Ruta relativa a la carpeta donde ajax.php guarda los archivos .txt
    // Si panel_general.php está en la raíz, esto suele ser "Archivos_SDCards/"
    const RUTA_ARCHIVOS = "Archivos_SDCards/"; 

    function actualizarTarjetas() {
        // 1. Seleccionar todas las tarjetas que tengan el atributo data-mac
        // Se espera que el HTML sea algo como: <div class="card" data-mac="AA-00-00-00-00-92">
        const tarjetas = document.querySelectorAll('[data-mac]');

        tarjetas.forEach(tarjeta => {
            const mac = tarjeta.getAttribute('data-mac');
            
            if (!mac) return;

            // 2. Construir el nombre del archivo
            // El archivo PHP genera nombres como: MAC=AA-BB-CC-DD-EE-FF_ajax.txt
            // Aseguramos que usamos guiones en lugar de dos puntos
            const macArchivo = mac.replace(/:/g, '-');
            const nombreArchivo = `MAC=${macArchivo}_ajax.txt`;
            
            // 3. Construir URL con timestamp para evitar caché del navegador
            const url = `${RUTA_ARCHIVOS}${nombreArchivo}?_=${new Date().getTime()}`;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Archivo no encontrado o error de red");
                    }
                    return response.json();
                })
                .then(data => {
                    // data = { timestamp, fecha, mac, temp, pres, volt }

                    // 4. Actualizar elementos dentro de la tarjeta
                    // Buscamos elementos por clase específica dentro de ESTA tarjeta
                    const elTemp = tarjeta.querySelector('.valor-temp');
                    const elPres = tarjeta.querySelector('.valor-pres');
                    const elVolt = tarjeta.querySelector('.valor-volt');
                    const elFecha = tarjeta.querySelector('.valor-fecha');
                    const elEstado = tarjeta.querySelector('.indicador-estado'); // Opcional: bolita de estado

                    // Asignar valores si los elementos existen
                    if (elTemp) elTemp.textContent = parseFloat(data.temp).toFixed(1) + ' °C';
                    if (elPres) elPres.textContent = parseFloat(data.pres).toFixed(1) + ' PSI'; // O 'Bar' según tu unidad
                    if (elVolt) elVolt.textContent = parseFloat(data.volt).toFixed(1) + ' V';
                    if (elFecha) elFecha.textContent = data.fecha;

                    // 5. Lógica de estado (Online/Offline) basada en la antigüedad del dato
                    if (elEstado) {
                        const ahora = Math.floor(Date.now() / 1000);
                        const diferencia = ahora - data.timestamp;
                        
                        // Si el dato tiene menos de 60 segundos, consideramos que está Online
                        if (diferencia < 60) {
                            elEstado.classList.add('online');
                            elEstado.classList.remove('offline');
                        } else {
                            elEstado.classList.add('offline');
                            elEstado.classList.remove('online');
                        }
                    }
                })
                .catch(error => {
                    // Silencioso en consola para no saturar si un equipo está apagado
                    // console.warn(`[Monitor] Esperando datos para ${mac}...`);
                });
        });
    }

    // Iniciar el ciclo de actualización
    setInterval(actualizarTarjetas, INTERVALO_ACTUALIZACION);
    
    // Ejecutar inmediatamente al cargar la página
    actualizarTarjetas();
});