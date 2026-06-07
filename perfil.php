<?php
// perfil.php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
$stmt->execute([$id_usuario]);
$usuario_actual = $stmt->fetch();

// Obtener sus préstamos activos
$sql_prestamos = "SELECT l.titulo, p.fecha_prestamo, p.fecha_devolucion_esperada 
                  FROM prestamos p 
                  INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
                  INNER JOIN libros l ON e.id_libro = l.id_libro
                  WHERE p.id_usuario = ? AND p.estado = 'Activo'";
$stmt_p = $pdo->prepare($sql_prestamos);
$stmt_p->execute([$id_usuario]);
$mis_prestamos = $stmt_p->fetchAll();

// Obtener sus reservas
$sql_reservas = "SELECT l.titulo, r.fecha_reserva, r.estado 
                 FROM reservas r 
                 INNER JOIN libros l ON r.id_libro = l.id_libro
                 WHERE r.id_usuario = ? AND r.estado = 'Pendiente'";
$stmt_r = $pdo->prepare($sql_reservas);
$stmt_r->execute([$id_usuario]);
$mis_reservas = $stmt_r->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .profile-header { display: flex; align-items: center; gap: 20px; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .profile-icon { font-size: 4rem; color: #3498db; }
        h1 { margin: 0; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #7f8c8d; }
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: bold; background: #3498db; color: white; }
    </style>
</head>
<body>
    <a href="dashboard.php" style="text-decoration: none; color: #7f8c8d; font-weight: bold;"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    
    <div class="card" style="margin-top: 20px;">
        <div class="profile-header">
            <div class="profile-icon"><i class="fas fa-user-circle"></i></div>
            <div>
                <h1><?php echo htmlspecialchars($usuario_actual['nombre']); ?></h1>
                <p style="color: #7f8c8d; margin: 5px 0;">
                    <span class="badge"><?php echo htmlspecialchars($_SESSION['rol']); ?></span>
                    &nbsp; | &nbsp; <i class="fas fa-id-card"></i> Boleta: <strong><?php echo htmlspecialchars($usuario_actual['boleta']); ?></strong>
                </p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div>
                <h3 style="color: #e67e22;"><i class="fas fa-book-reader"></i> Mis Préstamos Activos</h3>
                <?php if(count($mis_prestamos) > 0): ?>
                    <table>
                        <tr><th>Libro</th><th>Entrega Esperada</th></tr>
                        <?php foreach($mis_prestamos as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['titulo']); ?></td>
                                <td style="color: #e74c3c; font-weight: bold;"><?php echo date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p style="color: #7f8c8d;">No tienes libros pendientes por entregar.</p>
                <?php endif; ?>
            </div>

            <div>
                <h3 style="color: #3498db;"><i class="fas fa-clock"></i> Mis Reservas (Lista de espera)</h3>
                <?php if(count($mis_reservas) > 0): ?>
                    <table>
                        <tr><th>Libro</th><th>Fecha de Solicitud</th></tr>
                        <?php foreach($mis_reservas as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['titulo']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($r['fecha_reserva'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php else: ?>
                    <p style="color: #7f8c8d;">No tienes libros en lista de espera.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>