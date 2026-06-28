<?php
session_start();
require_once 'config/db.php';

// 1. Validar sesión
if(!isset($_SESSION['rol']) || !isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_usuario_actual = $_SESSION['id_usuario'];

try {
    // 2. Consulta filtrada estrictamente por el usuario activo
    $sql = "SELECT l.titulo, l.autor, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado, 
                   DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) as dias_retraso
            FROM prestamos p
            INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
            INNER JOIN libros l ON e.id_libro = l.id_libro
            WHERE p.id_usuario = :id_usuario AND p.estado = 'Activo'
            ORDER BY p.fecha_devolucion_esperada ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_usuario' => $id_usuario_actual]);
    $mis_libros = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar tus libros: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Libros — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'DM Sans','Segoe UI',sans-serif; background: #f5f3ef; margin: 0; color: #1a1a2e; }
        .page-wrap { max-width: 900px; margin: 0 auto; padding: 36px 32px 60px; }
        .page-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: #850021; margin: 0 0 5px; }
        .page-sub { color: #6b7280; font-size: 0.9rem; margin: 0 0 30px 0; }
        
        .book-card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #850021; }
        .book-info h3 { margin: 0 0 5px; color: #111827; font-size: 1.1rem; }
        .book-info p { margin: 0; color: #6b7280; font-size: 0.85rem; }
        .book-dates { text-align: right; font-size: 0.85rem; }
        .badge-ok { background: #d1fae5; color: #065f46; padding: 5px 10px; border-radius: 8px; font-weight: bold; display: inline-block; margin-top: 5px;}
        .badge-late { background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 8px; font-weight: bold; display: inline-block; margin-top: 5px;}
        .empty-state { text-align: center; padding: 40px; color: #6b7280; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">
        <h1 class="page-title"><i class="fas fa-book-reader"></i> Mis Préstamos</h1>
        <p class="page-sub">Consulta los libros que tienes actualmente en tu poder.</p>

        <?php if(count($mis_libros) > 0): ?>
            <?php foreach($mis_libros as $libro): ?>
                <div class="book-card">
                    <div class="book-info">
                        <h3><?php echo htmlspecialchars($libro['titulo']); ?></h3>
                        <p><i class="fas fa-user-pen"></i> <?php echo htmlspecialchars($libro['autor']); ?></p>
                    </div>
                    <div class="book-dates">
                        <p><strong>Devolución:</strong> <?php echo date('d/m/Y', strtotime($libro['fecha_devolucion_esperada'])); ?></p>
                        <?php if($libro['dias_retraso'] > 0): ?>
                            <span class="badge-late"><i class="fas fa-exclamation-circle"></i> Retraso de <?php echo $libro['dias_retraso']; ?> día(s)</span>
                        <?php else: ?>
                            <span class="badge-ok"><i class="fas fa-check"></i> A tiempo</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open" style="font-size: 3rem; color: #e5e7eb; margin-bottom: 15px;"></i>
                <h2>No tienes préstamos activos</h2>
                <p>Anímate a explorar nuestro catálogo y solicita un libro.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
