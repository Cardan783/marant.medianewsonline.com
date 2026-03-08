<?php
session_start(); // Iniciar sesión para verificar permisos
// Definir el token secreto que pusimos en el ESP32
$api_secret = "SampaTV_Secure";

// Verificar si viene el token correcto en la URL
$es_esp32 = (isset($_GET['api_token']) && $_GET['api_token'] === $api_secret);

// Si NO es el ESP32 y NO está logueado como usuario, bloquear.
if (!$es_esp32 && !isset($_SESSION['user_id'])) {
    die("Error: Acceso no autorizado");
}

// 1. Incluimos tu archivo de conexión PDO
include 'conexion.php'; 

// 2. Recibimos parámetros: MAC (ESP8266) o equipo_id (Web)
$mac_recibida = isset($_GET['mac']) ? $_GET['mac'] : '';
$equipo_id_recibido = isset($_GET['equipo_id']) ? $_GET['equipo_id'] : '';

if ($mac_recibida !== '' || $equipo_id_recibido !== '') {
    

    try {
        $equipo_id = null;

        if ($equipo_id_recibido !== '') {
            // Si recibimos ID directamente (desde la Web)
            
            if (!$es_esp32 && !isset($_SESSION['user_id'])) {
                echo "Error: Acceso no autorizado";
                exit;
            }
            
            if (!$es_esp32) {
                $stmt_check = $conn->prepare("SELECT id FROM equipos WHERE id = ? AND usuario_id = ?");
                $stmt_check->execute([$equipo_id_recibido, $_SESSION['user_id']]);
                if (!$stmt_check->fetch()) {
                    echo "Error: Equipo no encontrado o acceso denegado";
                    exit;
                }
            }

            $equipo_id = $equipo_id_recibido;
        } else {
             // SEGURIDAD: Verificar que el usuario esté logueado
            if (!$es_esp32 && !isset($_SESSION['user_id'])) {
                echo "Error: Acceso no autorizado";
                exit;
            }



            // Si recibimos MAC (desde ESP8266 o el esp32), buscamos el ID
            $query_equipo = "SELECT id FROM equipos WHERE mac_address = :mac_address LIMIT 1";
            $stmt_equipo = $conn->prepare($query_equipo);
            $stmt_equipo->bindParam(':mac_address', $mac_recibida, PDO::PARAM_STR);
            $stmt_equipo->execute();
            
            if ($stmt_equipo->rowCount() > 0) {
                $fila = $stmt_equipo->fetch(); 
                $equipo_id = $fila['id']; 

                     // SEGURIDAD: Adicional verificar si la mac pertenece al usuario
                     if (!$es_esp32) {
                         $stmt_check_mac = $conn->prepare("SELECT id FROM equipos WHERE mac_address = ? AND usuario_id = ?");
                         $stmt_check_mac->execute([$mac_recibida, $_SESSION['user_id']]);

                         if ($stmt_check_mac->rowCount() === 0) {
                             echo "Error: Equipo no encontrado o acceso denegado";
                             exit;
                         }
                     }

            } else {

                echo "Error: Equipo no registrado";
                exit;
            }
        }

        if ($equipo_id) {
            // Buscamos las alarmas correspondientes a ese equipo_id
            $query_alarmas = "SELECT * FROM alarmas WHERE equipo_id = :equipo_id ORDER BY id DESC LIMIT 1";
            $stmt_alarmas = $conn->prepare($query_alarmas);
            
            $stmt_alarmas->bindParam(':equipo_id', $equipo_id, PDO::PARAM_INT);
            $stmt_alarmas->execute();

            if ($stmt_alarmas->rowCount() > 0) {
                $alarmas = $stmt_alarmas->fetch();
                
                // Si el valor es NULL en la BD, se usa el valor por defecto (derecha)
                $id = $alarmas['id'] ?? 0;
                $equipo_id_res = $alarmas['equipo_id'] ?? $equipo_id;
                $temperatura = $alarmas['Temperatura'] ?? 90;
                $temp_advertencia = $alarmas['Temp_advertencia'] ?? 85;
                $presion = $alarmas['Presion'] ?? 0;
                $voltaje_max = $alarmas['Voltaje_Max'] ?? 0;
                $voltaje_min = $alarmas['Voltaje_Min'] ?? 0;

                // Imprimimos los 7 datos separados por comas
                echo "{$id},{$equipo_id_res},{$temperatura},{$temp_advertencia},{$presion},{$voltaje_max},{$voltaje_min}";
                     
            } else {
                // Respuesta de seguridad: si el equipo existe pero no tiene alarmas, devolver valores por defecto.
                echo "0,{$equipo_id},90,85,0,0,0"; 
            }
        }
        
    } catch(PDOException $e) {
        echo "Error BD: " . $e->getMessage();
    }

} else {
    echo "Error: Parámetros no recibidos";
}
?>