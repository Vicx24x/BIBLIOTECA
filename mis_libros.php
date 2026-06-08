<?php
// mis_libros.php
session_start();
require_once 'config/db.php';

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

try {
    // Consulta SQL usando JOIN para unir Préstamos, Ejemplares y Libros
    $sql = "SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado, 
                   l.titulo, l.autor, l.portada 
            FROM prestamos p
            INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
            INNER JOIN libros l ON e.id_libro = l.id_libro
            WHERE p.id_usuario = :id_usuario
            ORDER BY p.fecha_prestamo DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $id_usuario]);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar el historial: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Libros - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 40px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        
        .header-title { color: #2c3e50; margin-bottom: 5px; }
        .header-subtitle { color: #7f8c8d; margin-top: 0; margin-bottom: 30px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #2c3e50; font-weight: bold; text-transform: uppercase; font-size: 0.85rem; }
        tr:hover { background-color: #fcfcfc; }
        
        .book-cell { display: flex; align-items: center; gap: 15px; }
        .book-thumbnail { width: 50px; height: 75px; object-fit: cover; border-radius: 4px; background: #2c3e50; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .badge-activo { background-color: #fff3cd; color: #856404; } /* Amarillo para activo/pendiente */
        .badge-devuelto { background-color: #d4edda; color: #155724; } /* Verde para entregado */
        .badge-vencido { background-color: #f8d7da; color: #721c24; } /* Rojo si ya pasó la fecha */
        
        .btn-back { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #3498db; font-weight: bold; }
        .btn-back:hover { color: #2980b9; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        
        <h1 class="header-title"><i class="fas fa-book-reader"></i> Mis Libros</h1>
        <p class="header-subtitle">Consulta el historial de tus préstamos y fechas de entrega.</p>

        <?php if (count($historial) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Libro</th>
                        <th>Fecha de Préstamo</th>
                        <th>Fecha Límite</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $item): ?>
                        <tr>
                            <td>
                                <div class="book-cell">
                                    <?php 
                                    $ruta_imagen = !empty($item['portada']) ? 'portadas/' . basename($item['portada']) : '';
                                    if (empty($ruta_imagen) || !file_exists($ruta_imagen)): 
                                    ?>
                                        <div class="book-thumbnail"><i class="fas fa-book"></i></div>
                                    <?php else: ?>
                                        <img src="<?php echo $ruta_imagen; ?>" alt="Portada" class="book-thumbnail">
                                    <?php endif; ?>
                                    
                                    <div>
                                        <strong style="color: #2c3e50; display: block;"><?php echo htmlspecialchars($item['titulo']); ?></strong>
                                        <span style="color: #7f8c8d; font-size: 0.85rem;"><?php echo htmlspecialchars($item['autor']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($item['fecha_prestamo'])); ?></td>
                            <td>
                                <strong style="color: #e74c3c;">
                                    <?php echo date('d/m/Y', strtotime($item['fecha_devolucion_esperada'])); ?>
                                </strong>
                            </td>
                            <td>
                                <?php
                                    $hoy = date('Y-m-d');
                                    // Lógica visual para el estado del libro
                                    if ($item['estado'] === 'Devuelto') {
                                        echo '<span class="badge badge-devuelto"><i class="fas fa-check-circle"></i> Devuelto</span>';
                                    } elseif ($item['estado'] === 'Activo' && $item['fecha_devolucion_esperada'] < $hoy) {
                                        echo '<span class="badge badge-vencido"><i class="fas fa-exclamation-triangle"></i> Vencido</span>';
                                    } else {
                                        echo '<span class="badge badge-activo"><i class="fas fa-clock"></i> En Lectura</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; background: #f8f9fa; border-radius: 8px;">
                <i class="fas fa-book-open" style="font-size: 3rem; color: #bdc3c7; margin-bottom: 15px;"></i>
                <h3 style="color: #7f8c8d; margin: 0;">Aún no tienes libros en tu historial</h3>
                <p style="color: #95a5a6; margin-top: 10px;">¡Visita el catálogo para solicitar tu primer préstamo!</p>
                <a href="catalogo.php" style="display: inline-block; margin-top: 15px; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Ir al Catálogo</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
