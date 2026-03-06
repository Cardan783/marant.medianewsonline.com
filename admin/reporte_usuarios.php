<?php
session_start();
require_once '../php/conexion.php';

// Verificar sesión de ADMIN
if (!isset($_SESSION['admin_id']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Obtener usuarios
$usuarios = [];
try {
    $stmt = $conn->query("SELECT id, nombre, email, identificacion, created_at FROM usuarios ORDER BY created_at DESC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar usuarios";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Usuarios - SAMPATV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa-solid fa-users-viewfinder"></i> Reporte de Usuarios</h2>
            <div>
                <button onclick="generarPDF()" class="btn btn-danger"><i class="fa-solid fa-file-pdf me-2"></i>Descargar PDF</button>
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <table class="table table-striped table-hover" id="tablaUsuarios">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Identificación</th>
                            <th>Fecha Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($usuarios as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['identificacion']); ?></td>
                            <td><?php echo $u['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Librerías para PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script>
        function generarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.text("Reporte de Usuarios Registrados - SAMPATV", 14, 20);
            doc.setFontSize(10);
            doc.text("Generado el: " + new Date().toLocaleString(), 14, 28);

            doc.autoTable({
                html: '#tablaUsuarios',
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [13, 110, 253] } // Azul Bootstrap
            });

            doc.save('reporte_usuarios_sampatv.pdf');
        }
    </script>
</body>
</html>