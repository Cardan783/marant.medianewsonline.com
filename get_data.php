<?php
if (!isset($_GET["start"]) || 
    !isset($_GET["startTime"]) || // Nuevo
    !isset($_GET["end"])||
    !isset($_GET["endTime"])||   // Nuevo
    !isset($_GET["temp"]) ||
    !isset($_GET["pre"]) ||
    !isset($_GET["volt"])) {
    echo json_encode([]);
    exit;
}
$start = $_GET["start"];
$startTime = $_GET["startTime"]; // Nuevo
$end = $_GET["end"];
$endTime = $_GET["endTime"];     // Nuevo
$temp = $_GET["temp"];
$pre = $_GET["pre"];
$volt = $_GET["volt"];

// NOTA: La función getSensorData en functions.php debe aceptar 6 parámetros.
include_once "functions.php";
echo json_encode(getSensorData($start, $startTime, $end, $endTime, $temp, $pre, $volt));
?>