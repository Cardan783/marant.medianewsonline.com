<?php
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
            $equipo_id = $equipo_id_recibido;
        } else {
            // Si recibimos MAC (desde ESP8266), buscamos el ID
            $query_equipo = "SELECT id FROM equipos WHERE mac_address = :mac_address LIMIT 1";
            $stmt_equipo = $conn->prepare($query_equipo);
            $stmt_equipo->bindParam(':mac_address', $mac_recibida, PDO::PARAM_STR);
            $stmt_equipo->execute();
            
            if ($stmt_equipo->rowCount() > 0) {
                $fila = $stmt_equipo->fetch(); 
                $equipo_id = $fila['id']; 
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