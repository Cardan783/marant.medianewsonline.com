<?php
include 'conexion.php'; 

$mac_recibida = isset($_GET['mac']) ? $_GET['mac'] : '';

if ($mac_recibida !== '') {
    try {
        // 1. Buscamos el ID y AHORA TAMBIÉN el nombre_equipo
        $query_equipo = "SELECT id, nombre_equipo FROM equipos WHERE mac_address = :mac_address LIMIT 1";
        $stmt_equipo = $conn->prepare($query_equipo);
        $stmt_equipo->bindParam(':mac_address', $mac_recibida, PDO::PARAM_STR);
        $stmt_equipo->execute();
        
        if ($stmt_equipo->rowCount() > 0) {
            $fila = $stmt_equipo->fetch(); 
            $equipo_id = $fila['id']; 
            
            // 2. Guardamos el alias en una variable
            $nombre_equipo = $fila['nombre_equipo']; 

            $query_datos = "SELECT * FROM sensores WHERE equipo_id = :equipo_id ORDER BY id DESC LIMIT 1";
            $stmt_datos = $conn->prepare($query_datos);
            $stmt_datos->bindParam(':equipo_id', $equipo_id, PDO::PARAM_INT);
            $stmt_datos->execute();

            if ($stmt_datos->rowCount() > 0) {
                $datos = $stmt_datos->fetch();
                
                // 3. Imprimimos los datos y añadimos el ALIAS al final
                echo $datos['id'] . "," . $datos['equipo_id'] . "," . $datos['temperatura'] . "," . $datos['presion'] . "," . $datos['voltaje'] . "," . $nombre_equipo;
            } else {
                // Si aún no hay lecturas del sensor, enviamos todo en 0, pero SÍ enviamos el nombre
                echo "0,0,0,0,0," . $nombre_equipo; 
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