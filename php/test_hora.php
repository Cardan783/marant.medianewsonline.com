<?php
include 'conexion.php';
$res = $conn->query("SELECT NOW() as hora_mysql")->fetch();
echo "Hora en MySQL: " . $res['hora_mysql'];
?>