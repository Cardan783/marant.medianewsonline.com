<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
require_once 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Lógica para Restablecer de Fábrica ---
    if (isset($_POST['reset_factory']) && $_POST['reset_factory'] == '1') {
        $equipo_id = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;
        $password_confirm = $_POST['reset_password'] ?? '';

        if ($equipo_id <= 0) {
            header('Location: configuracion.php?status=error&msg=' . urlencode('Equipo no seleccionado.'));
            exit();
        }

        // Verificar contraseña actual del sistema (almacenada en config) para seguridad
        $stmt = $conn->prepare("SELECT contraseña FROM config WHERE equipo_id = ?");
        $stmt->execute([$equipo_id]);
        $current_config = $stmt->fetch();

        // Si la contraseña coincide con la de la BD
        if ($current_config && $current_config['contraseña'] === $password_confirm) {
            // Restablecer valores por defecto
            $sql_reset = "UPDATE config SET 
                over_run = 1, alarma_car = 0, alarma_sis = 0,
                nombre_BLE = 'SAMPATV', pin_BLE = '230201',
                usuario = 'SAMPATV', contraseña = '12345',
                wifi_ssid = 'SAMPATV', wifi_password = 'SAMPATV23',
                nombre_mDNS = 'Archivos',
                Notificacion_perdida_wifi = 1, notificacion_solo_led = 1, notificacion_solo_led_alarm_temp = 0,
                funcion_1 = 0, funcion_2 = 0, funcion_3 = 0, funcion_4 = 0, funcion_5 = 0
                WHERE equipo_id = ?";
            
            $stmt_reset = $conn->prepare($sql_reset);
            if ($stmt_reset->execute([$equipo_id])) {
                header('Location: configuracion.php?equipo_id=' . $equipo_id . '&status=reset_success');
            } else {
                header('Location: configuracion.php?equipo_id=' . $equipo_id . '&status=error&msg=' . urlencode('Error al actualizar BD.'));
            }
        } else {
            header('Location: configuracion.php?equipo_id=' . $equipo_id . '&status=reset_fail');
        }
        exit();
    }

    // --- Lógica para Guardar Configuración Normal ---
    if (isset($_POST['is_config_form']) && $_POST['is_config_form'] == '1') {
        $equipo_id = isset($_POST['equipo_id']) ? (int)$_POST['equipo_id'] : 0;

        if ($equipo_id <= 0) {
            header('Location: configuracion.php?status=error&msg=' . urlencode('No se ha seleccionado un equipo válido.'));
            exit();
        }

        // 1. Recoger datos de Checkboxes
        // Corrección: Validar el valor '1' porque los inputs hidden siempre envían '0' si no está marcado
        $over_run = (isset($_POST['over_run']) && $_POST['over_run'] == '1') ? 1 : 0;
        $alarma_car = (isset($_POST['alarma_car']) && $_POST['alarma_car'] == '1') ? 1 : 0;
        $alarma_sis = (isset($_POST['alarma_sis']) && $_POST['alarma_sis'] == '1') ? 1 : 0;
        $notificacion_solo_led = (isset($_POST['notificacion_solo_led']) && $_POST['notificacion_solo_led'] == '1') ? 1 : 0;
        $notificacion_solo_led_alarm_temp = (isset($_POST['notificacion_solo_led_alarm_temp']) && $_POST['notificacion_solo_led_alarm_temp'] == '1') ? 1 : 0;
        $Notificacion_perdida_wifi = (isset($_POST['Notificacion_perdida_wifi']) && $_POST['Notificacion_perdida_wifi'] == '1') ? 1 : 0;
        
        $f1 = (isset($_POST['funcion_1']) && $_POST['funcion_1'] == '1') ? 1 : 0;
        $f2 = (isset($_POST['funcion_2']) && $_POST['funcion_2'] == '1') ? 1 : 0;
        $f3 = (isset($_POST['funcion_3']) && $_POST['funcion_3'] == '1') ? 1 : 0;
        $f4 = (isset($_POST['funcion_4']) && $_POST['funcion_4'] == '1') ? 1 : 0;
        $f5 = (isset($_POST['funcion_5']) && $_POST['funcion_5'] == '1') ? 1 : 0;

        // 2. Recoger datos de Texto
        $usuario = $_POST['usuario'] ?? '';
        $wifi_ssid = $_POST['wifi_ssid'] ?? '';
        $nombre_mDNS = $_POST['nombre_mDNS'] ?? '';
        $nombre_BLE = $_POST['nombre_BLE'] ?? '';

        // 3. Construcción dinámica de la consulta (solo actualizamos contraseñas si se escribieron)
        $params = [
            $usuario, $wifi_ssid, $nombre_mDNS, $nombre_BLE,
            $over_run, $alarma_car, $alarma_sis,
            $notificacion_solo_led, $notificacion_solo_led_alarm_temp, $Notificacion_perdida_wifi,
            $f1, $f2, $f3, $f4, $f5
        ];

        $sql = "UPDATE config SET 
                usuario = ?, wifi_ssid = ?, nombre_mDNS = ?, nombre_BLE = ?,
                over_run = ?, alarma_car = ?, alarma_sis = ?,
                notificacion_solo_led = ?, notificacion_solo_led_alarm_temp = ?, Notificacion_perdida_wifi = ?,
                funcion_1 = ?, funcion_2 = ?, funcion_3 = ?, funcion_4 = ?, funcion_5 = ?";

        if (!empty($_POST['contraseña'])) {
            $sql .= ", contraseña = ?";
            $params[] = $_POST['contraseña'];
        }
        if (!empty($_POST['wifi_password'])) {
            $sql .= ", wifi_password = ?";
            $params[] = $_POST['wifi_password'];
        }
        if (!empty($_POST['pin_BLE'])) {
            $sql .= ", pin_BLE = ?";
            $params[] = $_POST['pin_BLE'];
        }

        $sql .= " WHERE equipo_id = ?";
        $params[] = $equipo_id;

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            header('Location: configuracion.php?equipo_id=' . $equipo_id . '&status=success');
        } catch (PDOException $e) {
            header('Location: configuracion.php?equipo_id=' . $equipo_id . '&status=error&msg=' . urlencode($e->getMessage()));
        }
        exit();
    }
}

// Si se accede directamente sin POST
header("Location: configuracion.php");
exit();
?>
