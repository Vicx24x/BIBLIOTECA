<?php
// prestamos.php
session_start();
require_once 'config/db.php';

// Seguridad: Solo Administradores y Bibliotecarios pueden gestionar devoluciones
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] === 'Usuario') {
    die("Acceso denegado. <a href='catalogo.php'>Volver al catálogo</a>");
}

$mensaje = '';

// Procesamiento de Devolución (Backend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'devolver') {
    $id_prestamo = (int)$_POST['id_prestamo'];
    $id_ejemplar = (int)$_POST['id_ejemplar'];

    try {
        // Iniciamos transacción para asegurar que ambas tablas se actualicen juntas
        $pdo->beginTransaction();

        // 1. Actualizar el registro del préstamo
        $sql_update_prestamo = "UPDATE prestamos SET estado = 'Devuelto', fecha_devolucion_real = CURRENT_DATE WHERE id_prestamo = :id_prestamo";
        $stmt_prestamo = $pdo->prepare($sql_update_prestamo);
        $stmt_prestamo->execute(['id_prestamo' => $id_prestamo]);

        // 2. Liberar el ejemplar físico para que vuelva a aparecer en el Catálogo Digital
        $sql_update_ejemplar = "UPDATE ejemplares SET estado = 'Disponible' WHERE id_ejemplar = :id_ejemplar";
        $stmt_ejemplar = $pdo->prepare($sql_update_ejemplar);
        $stmt_ejemplar->execute(['id_ejemplar' => $id_ejemplar]);

        $pdo->commit();
        $mensaje = "<div class='alert success'><i class='fas fa-check-circle'></i> ¡Libro devuelto exitosamente al inventario!</div>";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error al procesar devolución: " . $e->getMessage() . "</div>";
    }
}

// Consultar Préstamos ACTIVOS
$sql_activos = "SELECT p.id_prestamo, u.nombre AS usuario, l.titulo, e.codigo_activo, p.fecha_prestamo, p.fecha_devolucion_esperada, p.id_ejemplar 
                FROM prestamos p 
                INNER JOIN usuarios u ON p.id_usuario = u.id_usuario 
                INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar 
                INNER JOIN libros l ON e.id_libro = l.id_libro 
                WHERE p.estado = 'Activo' 
                ORDER BY p.fecha_devolucion_esperada ASC";
$prestamos_activos = $pdo->query($sql_activos)->fetchAll();

// Consultar HISTORIAL (Devueltos)
$sql_historial = "SELECT p.id_prestamo, u.nombre AS usuario, l.titulo, p.fecha_prestamo, p.fecha_devolucion_real 
                  FROM prestamos p 
                  INNER JOIN usuarios u ON p.id_usuario = u.id_usuario 
                  INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar 
                  INNER JOIN libros l ON e.id_libro = l.id_libro 
                  WHERE p.estado = 'Devuelto' 
                  ORDER BY p.fecha_devolucion_real DESC LIMIT 20";
$historial = $pdo->query($sql_historial)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operaciones y Préstamos - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --accent: #3498db;
            --bg-body: #f4f7f6;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); color: #333; margin: 0; padding: 40px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: var(--primary-dark); margin: 0; }
        .btn-volver { text-decoration: none; color: #7f8c8d; font-weight: bold; }
        .btn-volver:hover { color: var(--accent); }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .card { background: var(--white); padding: 25px; border-radius: 12px; box-shadow: var(--shadow); margin-bottom: 40px; }
        .card h2 { margin-top: 0; color: var(--primary-dark); font-size: 1.4rem; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #7f8c8d; text-transform: uppercase; font-size: 0.85rem; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-success { background-color: #d4edda; color: #155724; }

        .btn-devolver { background-color: #e67e22; color: white; border: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 0.9rem; }
        .btn-devolver:hover { background-color: #d35400; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include 'header.php'; ?>

    <div class="header">
        <div>
            <h1>🔄 Operaciones de Biblioteca</h1>
            <p style="color: #7f8c8d;">Gestiona las devoluciones y consulta el historial.</p>
        </div>
        <a href="dashboard.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
    </div>

    <?php echo $mensaje; ?>

    <div class="card">
        <h2><i class="fas fa-hand-holding-book"></i> Préstamos Activos</h2>
        <?php if(count($prestamos_activos) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Título del Libro</th>
                        <th>Código Etiqueta</th>
                        <th>Fecha Préstamo</th>
                        <th>Límite de Entrega</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($prestamos_activos as $prestamo): ?>
                    <tr>
                        <td style="font-weight: bold;"><?php echo htmlspecialchars($prestamo['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($prestamo['titulo']); ?></td>
                        <td><span class="badge badge-warning"><?php echo htmlspecialchars($prestamo['codigo_activo']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                        <td style="color: #e74c3c; font-weight: bold;"><?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_esperada'])); ?></td>
                        <td>
                            <form method="POST" action="prestamos.php" onsubmit="return confirm('¿Confirmas que el libro ha sido devuelto en buen estado?');">
                                <input type="hidden" name="accion" value="devolver">
                                <input type="hidden" name="id_prestamo" value="<?php echo $prestamo['id_prestamo']; ?>">
                                <input type="hidden" name="id_ejemplar" value="<?php echo $prestamo['id_ejemplar']; ?>">
                                <button type="submit" class="btn-devolver"><i class="fas fa-undo"></i> Devolver</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #7f8c8d; text-align: center; padding: 20px;">No hay libros prestados en este momento.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2><i class="fas fa-history"></i> Historial de Devoluciones (Últimas 20)</h2>
        <?php if(count($historial) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Título del Libro</th>
                        <th>Fecha Préstamo</th>
                        <th>Fecha Devolución Real</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($historial as $item): ?>
                    <tr>
                        <td style="font-weight: bold;"><?php echo htmlspecialchars($item['usuario']); ?></td>
                        <td><?php echo htmlspecialchars($item['titulo']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($item['fecha_prestamo'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($item['fecha_devolucion_real'])); ?></td>
                        <td><span class="badge badge-success"><i class="fas fa-check"></i> Devuelto</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #7f8c8d; text-align: center; padding: 20px;">Aún no hay registros de devoluciones.</p>
        <?php endif; ?>
    </div>

</body>
</html>