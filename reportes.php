<?php
// reportes.php
session_start();
require_once 'config/db.php';

// Seguridad: Solo los Administradores pueden ver los reportes
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado. Solo los administradores pueden ver los reportes. <a href='dashboard.php'>Volver al inicio</a>");
}

try {
    // 1. Reporte por Tipo de Usuario (RF7)
    $sql_usuarios = "SELECT r.nombre_rol, COUNT(u.id_usuario) as total 
                     FROM roles r 
                     LEFT JOIN usuarios u ON r.id_rol = u.id_rol 
                     GROUP BY r.id_rol";
    $stats_usuarios = $pdo->query($sql_usuarios)->fetchAll();

    // 2. Reporte de Materiales Más Prestados (RF23)
    $sql_libros_populares = "SELECT l.titulo, l.autor, COUNT(p.id_prestamo) as total_prestamos 
                             FROM libros l
                             INNER JOIN ejemplares e ON l.id_libro = e.id_libro
                             INNER JOIN prestamos p ON e.id_ejemplar = p.id_ejemplar
                             GROUP BY l.id_libro 
                             ORDER BY total_prestamos DESC LIMIT 5";
    $libros_populares = $pdo->query($sql_libros_populares)->fetchAll();

    // 3. Reporte de Usuarios con Retrasos (RF24) - Fecha de entrega menor a hoy y no devuelto
    $sql_retrasos = "SELECT u.nombre, u.correo, l.titulo, p.fecha_devolucion_esperada, DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) as dias_retraso
                     FROM prestamos p
                     INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                     INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
                     INNER JOIN libros l ON e.id_libro = l.id_libro
                     WHERE p.estado = 'Activo' AND p.fecha_devolucion_esperada < CURRENT_DATE
                     ORDER BY dias_retraso DESC";
    $usuarios_retrasados = $pdo->query($sql_retrasos)->fetchAll();

} catch (PDOException $e) {
    die("Error al generar reportes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --accent: #3498db;
            --bg-body: #f4f7f6;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
            --danger: #e74c3c;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); color: #333; margin: 0; padding: 40px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: var(--primary-dark); margin: 0; }
        .btn-volver { text-decoration: none; color: #7f8c8d; font-weight: bold; }
        .btn-volver:hover { color: var(--accent); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; margin-bottom: 40px; }
        
        .card { background: var(--white); padding: 25px; border-radius: 12px; box-shadow: var(--shadow); }
        .card h2 { margin-top: 0; font-size: 1.2rem; color: var(--primary-dark); border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        th { color: #7f8c8d; text-transform: uppercase; font-size: 0.8rem; }
        
        .stat-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px dashed #eee; }
        .stat-item:last-child { border-bottom: none; }
        .stat-label { font-weight: bold; color: #555; }
        .stat-value { background: var(--accent); color: white; padding: 2px 10px; border-radius: 12px; font-weight: bold; font-size: 0.9rem; }
        
        .badge-danger { background-color: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include 'header.php'; ?>

    <div class="header">
        <div>
            <h1>📊 Centro de Reportes</h1>
            <p style="color: #7f8c8d;">Análisis en tiempo real del ecosistema de la biblioteca.</p>
        </div>
        <a href="dashboard.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
    </div>
    <a href="respaldo_bd.php" style="background: #27ae60; color: white; padding: 10px 15px; border-radius: 5px; text-decoration: none; font-weight: bold; display: inline-block; margin-top: 10px;">
    <i class="fas fa-database"></i> Generar Respaldo de Seguridad
</a>

    <div class="dashboard-grid">
        <div class="card">
            <h2><i class="fas fa-users"></i> Usuarios por Rol</h2>
            <p style="font-size: 0.85rem; color: #7f8c8d; margin-bottom: 15px;">Muestra la cantidad de cuentas registradas según su nivel de acceso.</p>
            <?php foreach($stats_usuarios as $stat): ?>
                <div class="stat-item">
                    <span class="stat-label"><?php echo htmlspecialchars($stat['nombre_rol']); ?></span>
                    <span class="stat-value"><?php echo $stat['total']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2><i class="fas fa-fire"></i> Libros Más Populares</h2>
            <p style="font-size: 0.85rem; color: #7f8c8d; margin-bottom: 15px;">Los títulos con mayor índice de rotación histórica.</p>
            <?php if(count($libros_populares) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th style="text-align: right;">Préstamos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($libros_populares as $libro): ?>
                        <tr>
                            <td style="font-weight: bold;"><?php echo htmlspecialchars($libro['titulo']); ?></td>
                            <td style="text-align: right;"><span class="stat-value" style="background: #2ecc71;"><?php echo $libro['total_prestamos']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #7f8c8d; text-align: center;">Aún no hay suficientes datos de préstamos.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Reporte de Usuarios con Retrasos</h2>
        <p style="font-size: 0.85rem; color: #7f8c8d; margin-bottom: 15px;">Usuarios que han superado su fecha límite de entrega y requieren ser notificados.</p>
        
        <?php if(count($usuarios_retrasados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Usuario / Correo</th>
                        <th>Libro Retenido</th>
                        <th>Fecha Límite</th>
                        <th>Días de Retraso</th>
                        <th>Acción Sugerida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios_retrasados as $moroso): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($moroso['nombre']); ?></strong><br>
                            <span style="font-size: 0.8rem; color: #7f8c8d;"><?php echo htmlspecialchars($moroso['correo']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($moroso['titulo']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($moroso['fecha_devolucion_esperada'])); ?></td>
                        <td><span class="badge-danger"><?php echo $moroso['dias_retraso']; ?> días</span></td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($moroso['correo']); ?>?subject=Aviso de Retraso - BiblioMPS" style="color: white; background: var(--primary-dark); padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 0.85rem;">
                                <i class="fas fa-envelope"></i> Enviar Aviso
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; text-align: center; border: 1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> ¡Excelente! Actualmente no hay ningún usuario con préstamos retrasados.
            </div>
        <?php endif; ?>
    </div>

</body>
</html>