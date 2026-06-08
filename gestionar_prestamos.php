<?php
// gestionar_prestamos.php
session_start();
require_once 'config/db.php';

// NOTA: Aquí podrías meter una validación de rol si ya tienes $_SESSION['rol'] === 'Bibliotecario'
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

try {
    // Consulta para traer los préstamos activos junto con los datos del alumno y del libro
    $sql = "SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_devolucion_esperada, 
                   u.usuario AS nombre_alumno, l.titulo, l.autor, e.id_ejemplar
            FROM prestamos p
            INNER JOIN usuarios u ON p.id_usuario = u.id
            INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
            INNER JOIN libros l ON e.id_libro = l.id_libro
            WHERE p.estado = 'Activo'
            ORDER BY p.fecha_devolucion_esperada ASC";
            
    $stmt = $pdo->query($sql);
    $prestamos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar la gestión de préstamos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Préstamos - Panel Bibliotecario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 40px; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        
        .header-title { color: #2c3e50; margin: 0 0 5px 0; }
        .header-subtitle { color: #7f8c8d; margin: 0 0 30px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #34495e; color: white; font-weight: bold; text-transform: uppercase; font-size: 0.85rem; }
        tr:hover { background-color: #fcfcfc; }
        
        .btn-devolucion { background-color: #2ecc71; color: white; padding: 8px 15px; border-radius: 4px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; }
        .btn-devolucion:hover { background-color: #27ae60; }
        
        .alerta-vencido { color: #c0392b; font-weight: bold; }
        .btn-back { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #3498db; font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        
        <h1 class="header-title"><i class="fas fa-tasks"></i> Control de Préstamos Activos</h1>
        <p class="header-subtitle">Módulo administrativo para registrar devoluciones físicas de ejemplares.</p>

        <?php if (count($prestamos_activos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Alumno / Usuario</th>
                        <th>Libro Solicitado</th>
                        <th>Fecha Salida</th>
                        <th>Fecha Límite</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestamos_activos as $p): ?>
                        <?php 
                            $es_vencido = (date('Y-m-d') > $p['fecha_devolucion_esperada']);
                        ?>
                        <tr>
                            <td><strong>#<?php echo $p['id_prestamo']; ?></strong></td>
                            <td><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($p['nombre_alumno']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['titulo']); ?></strong><br>
                                <small style="color:#7f8c8d;">Ejemplar ID: <?php echo $p['id_ejemplar']; ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($p['fecha_prestamo'])); ?></td>
                            <td class="<?php echo $es_vencido ? 'alerta-vencido' : ''; ?>">
                                <?php echo date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])); ?>
                                <?php echo $es_vencido ? ' ⚠️ (Vencido)' : ''; ?>
                            </td>
                            <td>
                                <form action="procesar_devolucion.php" method="POST" onsubmit="return confirm('¿Confirmas que el alumno entregó el libro físico en buen estado?');">
                                    <input type="hidden" name="id_prestamo" value="<?php echo $p['id_prestamo']; ?>">
                                    <input type="hidden" name="id_ejemplar" value="<?php echo $p['id_ejemplar']; ?>">
                                    <button type="submit" class="btn-devolucion"><i class="fas fa-clipboard-check"></i> Recibir Libro</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px;">
                <i class="fas fa-check-double" style="font-size: 3rem; color: #2ecc71; margin-bottom: 15px;"></i>
                <h3 style="color: #7f8c8d; margin: 0;">¡Al corriente!</h3>
                <p style="color: #95a5a6; margin-top: 10px;">No hay ningún préstamo activo pendiente de devolución en el sistema.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
