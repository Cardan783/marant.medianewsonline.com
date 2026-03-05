<?php
// 1. Incluimos tu archivo de conexión PDO
include 'conexion.php'; 

// 2. Recibimos la MAC enviada por el ESP8266 por la URL (?mac=...)
$mac_recibida = isset($_GET['mac']) ? $_GET['mac'] : '';

if ($mac_recibida !== '') {
    
    try {
        // 3. Buscamos el ID del equipo asociado a la MAC usando tu variable $conn
        $query_equipo = "SELECT id FROM equipos WHERE mac_address = :mac_address LIMIT 1";
        $stmt_equipo = $conn->prepare($query_equipo);
        
        // Pasamos el valor de forma segura para evitar inyecciones SQL
        $stmt_equipo->bindParam(':mac_address', $mac_recibida, PDO::PARAM_STR);
        $stmt_equipo->execute();
        
        // 4. Verificamos si el equipo existe
        if ($stmt_equipo->rowCount() > 0) {
            
            // Como en tu conexion.php ya definiste FETCH_ASSOC por defecto, fetch() es suficiente
            $fila = $stmt_equipo->fetch(); 
            $equipo_id = $fila['id']; 

            // 5. Buscamos las alarmas correspondientes a ese equipo_id
            $query_alarmas = "SELECT * FROM alarmas WHERE equipo_id = :equipo_id ORDER BY id DESC LIMIT 1";
            $stmt_alarmas = $conn->prepare($query_alarmas);
            
            $stmt_alarmas->bindParam(':equipo_id', $equipo_id, PDO::PARAM_INT);
            $stmt_alarmas->execute();

            if ($stmt_alarmas->rowCount() > 0) {
                $alarmas = $stmt_alarmas->fetch();
                
                // 6. Imprimimos los 7 datos separados por comas
                // Formato exacto que espera el ESP8266: id, equipo_id, TempCritica, TempAdver, PresMin, VoltMax, VoltMin
                echo $alarmas['id'] . "," . 
                     $alarmas['equipo_id'] . "," . 
                     $alarmas['Temperatura'] . "," . 
                     $alarmas['Temp_advertencia'] . "," . 
                     $alarmas['Presion'] . "," . 
                     $alarmas['Voltaje_Max'] . "," . 
                     $alarmas['Voltaje_Min'];
                     
            } else {
                // Respuesta de seguridad: si el equipo existe pero no tiene alarmas configuradas
                echo "0,0,0,0,0,0,0"; 
            }

        } else {
            echo "Error: Equipo no registrado";
        }
        
    } catch(PDOException $e) {
        echo "Error de BD: " . $e->getMessage();
    }

} else {
    echo "Error: MAC no recibida";
}
?>