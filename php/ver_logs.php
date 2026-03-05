<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Archivos Subidos</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Confirmaciones Nube</h4>
                <a href="verificar_log.php" class="btn btn-sm btn-light text-primary fw-bold">Verificar Archivo</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 200px;">Fecha y Hora</th>
                                <th>Detalle del Evento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Ruta al archivo de log
                            $archivo_log = __DIR__ . '/../Archivos_SDCards/Confirmaciones_Nube.txt';

                            if (file_exists($archivo_log)) {
                                // Leer el archivo en un array, ignorando saltos de línea vacíos
                                $lineas = file($archivo_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                                
                                // Invertimos el array para mostrar los registros más recientes primero
                                $lineas = array_reverse($lineas);

                                foreach ($lineas as $linea) {
                                    // Ignoramos las líneas del encabezado estático
                                    if (strpos($linea, 'REGISTRO DIARIO') !== false || 
                                        strpos($linea, 'Fecha de creación') !== false || 
                                        strpos($linea, 'Ubicación:') !== false || 
                                        strpos($linea, '---') !== false) {
                                        continue;
                                    }

                                    // Buscamos el formato [Fecha] Mensaje
                                    if (preg_match('/^\[(.*?)\] (.*)$/', $linea, $coincidencias)) {
                                        $fecha = $coincidencias[1];
                                        $mensaje = $coincidencias[2];
                                        echo "<tr>";
                                        echo "<td class='fw-bold text-secondary'>$fecha</td>";
                                        echo "<td>$mensaje</td>";
                                        echo "</tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='2' class='text-center text-danger fw-bold'>El archivo de registro no existe o no se ha creado aún.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>