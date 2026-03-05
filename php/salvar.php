<?php
require_once 'conexion.php';

// Configurar zona horaria
date_default_timezone_set('America/Caracas');
$fecha_venezuela = date('Y-m-d H:i:s');

// Función auxiliar para logs (opcional, para depuración)
function log_debug($msg) {
    // file_put_contents("debug_log.txt", date("Y-m-d H:i:s") . " - " . $msg . "\n", FILE_APPEND);
}

// Recibir datos: Primero intentamos por $_POST (Formulario), sino leemos el cuerpo crudo (ESP32 text/plain)
$input_data = isset($_POST['datos']) ? $_POST['datos'] : file_get_contents("php://input");

if (!empty($input_data)) {
    $datos = explode(",", $input_data);
    
    log_debug("Datos recibidos: " . $input_data); 

    if (count($datos) >= 4) {
        $mac_address = trim($datos[0]); // Recibimos la MAC
        $temp_valor = trim($datos[1]);
        $pres_valor = trim($datos[2]);
        $volt_valor = trim($datos[3]);

        try {
            // 1. Buscar el ID numérico del equipo usando la MAC
            $stmt_eq = $conn->prepare("SELECT id FROM equipos WHERE mac_address = ? LIMIT 1");
            $stmt_eq->execute([$mac_address]);
            $equipo = $stmt_eq->fetch(PDO::FETCH_ASSOC);

            if ($equipo) {
                $id_equipo = $equipo['id']; // Usamos este ID para las relaciones

                // --- PASO 1: Auto-Registro en Alarmas (si no existe configuración previa) ---
                $stmt_check = $conn->prepare("SELECT id FROM alarmas WHERE equipo_id = ? LIMIT 1");
                $stmt_check->execute([$id_equipo]);
            
                if (!$stmt_check->fetch()) {
                    log_debug("Configuración de alarmas faltante para ID $id_equipo. Creando por defecto...");
                    
                    // INSERT con manejo de error específico
                    $sql_reg = "INSERT INTO alarmas (equipo_id, Temp_advertencia, Temperatura, Presion, Voltaje_Max, Voltaje_min, cambio_pendiente) 
                                VALUES (?, 88.0, 95.0, 0.5, 15.0, 11.5, 1)";
                    
                    if (!$conn->prepare($sql_reg)->execute([$id_equipo])) {
                        log_debug("FALLO INSERT ALARMAS: " . implode(" ", $conn->errorInfo()));
                    } else {
                        log_debug("Registro en alarmas exitoso.");
                    }
                }

                // --- PASO 2: Guardar Sensores ---
                $sql_insert = "INSERT INTO sensores (equipo_id, temperatura, presion, voltaje, created_at) 
                               VALUES (?, ?, ?, ?, ?)";
                
                if (!$conn->prepare($sql_insert)->execute([$id_equipo, $temp_valor, $pres_valor, $volt_valor, $fecha_venezuela])) {
                     log_debug("FALLO INSERT SENSORES: " . implode(" ", $conn->errorInfo()));
                     echo "ERR_DB_INSERT";
                } else {
                    // --- PASO 3: Verificar Cambios ---
                    $stmt_flag = $conn->prepare("SELECT cambio_pendiente FROM alarmas WHERE equipo_id = ? LIMIT 1");
                    $stmt_flag->execute([$id_equipo]);
                    $alerta = $stmt_flag->fetch(PDO::FETCH_ASSOC);

                    if ($alerta && (int)$alerta['cambio_pendiente'] === 1) {
                        echo "OK,1";
                        $conn->prepare("UPDATE alarmas SET cambio_pendiente = 0 WHERE equipo_id = ?")->execute([$id_equipo]);
                    } else {
                        echo "OK,0";
                    }
                }
            } else {
                log_debug("MAC no registrada: " . $mac_address);
                echo "ERR_MAC_NOT_FOUND";
            }

        } catch (PDOException $e) {
            log_debug("Error PDO: " . $e->getMessage());
            echo "ERR_EXCEPTION";
        }
    } else {
        echo "ERR_FORMATO";
    }
} else {
    echo "ERR_NO_DATA";
}
?>
