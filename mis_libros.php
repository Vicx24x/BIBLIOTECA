<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

try {
    $sql = "SELECT p.id_prestamo, p.fecha_prestamo, p.fecha_devolucion_esperada, p.estado, 
                   l.titulo, l.autor, l.isbn 
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

$hoy = date('Y-m-d');
$activos  = array_filter($historial, fn($i) => $i['estado'] === 'Activo' && $i['fecha_devolucion_esperada'] >= $hoy);
$vencidos = array_filter($historial, fn($i) => $i['estado'] === 'Activo' && $i['fecha_devolucion_esperada'] < $hoy);
$devueltos = array_filter($historial, fn($i) => $i['estado'] === 'Devuelto');
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

        .page-wrap { max-width: 1100px; margin: 0 auto; padding: 36px 32px 60px; }

        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda,#850021); text-decoration: none; font-weight: 600; font-size: 0.875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.2s; }
        .back-link:hover { background: var(--guinda,#850021); color: #fff; }

        .page-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda,#850021); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: 0.875rem; margin: 0; }

        /* Summary chips */
        .summary-row { display: flex; gap: 14px; margin-bottom: 28px; flex-wrap: wrap; }
        .s-chip { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px; font-size: 0.84rem; font-weight: 700; }
        .s-chip.active  { background: #fef3c7; color: #78350f; }
        .s-chip.overdue { background: #fee2e2; color: #991b1b; }
        .s-chip.done    { background: #d1fae5; color: #065f46; }
        .s-chip .count  { font-size: 1.1rem; font-weight: 800; }

        /* Table */
        .table-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); overflow: hidden; }
        .table-header { padding: 20px 28px; border-bottom: 1px solid #f3f4f6; }
        .table-header h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.1rem; color: #111827; margin: 0; display: flex; align-items: center; gap: 8px; }
        .table-header h2 i { color: var(--guinda,#850021); }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 12px 20px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; background: #fafafa; border-bottom: 1px solid #f3f4f6; }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fdf8f0; }
        tbody td { padding: 14px 20px; font-size: 0.875rem; vertical-align: middle; }

        .book-cell { display: flex; align-items: center; gap: 12px; }
        .book-thumb { width: 42px; height: 58px; border-radius: 6px; object-fit: cover; flex-shrink: 0; }
        .book-fallback { width: 42px; height: 58px; background: linear-gradient(135deg,#2d000b,#850021); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.4); font-size: 1.1rem; flex-shrink: 0; }
        .book-title { font-weight: 700; color: #111827; font-size: 0.9rem; margin-bottom: 2px; }
        .book-author { color: #9ca3af; font-size: 0.8rem; }

        .date-cell { font-size: 0.85rem; color: #374151; font-weight: 500; }
        .date-limit { font-weight: 700; }
        .date-limit.red { color: #dc2626; }
        .date-limit.orange { color: #d97706; }
        .date-limit.green { color: #059669; }

        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 13px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; }
        .badge-active  { background: #fef3c7; color: #78350f; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }
        .badge-done    { background: #d1fae5; color: #065f46; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .icon-wrap { width: 72px; height: 72px; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.8rem; color: #d1d5db; }
        .empty-state h3 { color: #374151; margin: 0 0 8px; font-size: 1.1rem; font-family: 'Playfair Display', Georgia, serif; }
        .empty-state p { color: #9ca3af; font-size: 0.875rem; margin: 0 0 20px; }
        .btn-catalog { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, var(--guinda,#850021), #5a0016); color: #fff; padding: 11px 22px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.875rem; box-shadow: 0 4px 14px rgba(133,0,33,0.28); transition: all 0.2s; }
        .btn-catalog:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(133,0,33,0.38); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">
        <div class="topnav">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div>
                <h1 class="page-title"><i class="fas fa-book-reader" style="font-size:1.4rem;"></i> Mis Libros</h1>
                <p class="page-sub">Historial de préstamos de <?php echo htmlspecialchars(explode(' ', $nombre_usuario)[0]); ?></p>
            </div>
            <div style="width:100px;"></div>
        </div>

        <!-- Resumen -->
        <div class="summary-row">
            <div class="s-chip active"><i class="fas fa-clock"></i> En lectura <span class="count"><?php echo count($activos); ?></span></div>
            <div class="s-chip overdue"><i class="fas fa-exclamation-triangle"></i> Vencidos <span class="count"><?php echo count($vencidos); ?></span></div>
            <div class="s-chip done"><i class="fas fa-check-circle"></i> Devueltos <span class="count"><?php echo count($devueltos); ?></span></div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h2><i class="fas fa-list-ul"></i> Historial Completo</h2>
            </div>

            <?php if(count($historial) > 0): ?>
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
                    <?php foreach($historial as $item):
                        $ruta_imagen = "https://covers.openlibrary.org/b/isbn/".urlencode($item['isbn'])."-M.jpg?default=404";
                        $dias_restantes = (strtotime($item['fecha_devolucion_esperada']) - strtotime($hoy)) / 86400;
                        if ($item['estado'] === 'Devuelto') {
                            $badge = '<span class="badge badge-done"><i class="fas fa-check-circle"></i> Devuelto</span>';
                            $date_class = 'green';
                        } elseif ($item['fecha_devolucion_esperada'] < $hoy) {
                            $badge = '<span class="badge badge-overdue"><i class="fas fa-exclamation-triangle"></i> Vencido</span>';
                            $date_class = 'red';
                        } else {
                            $badge = '<span class="badge badge-active"><i class="fas fa-clock"></i> En lectura</span>';
                            $date_class = $dias_restantes <= 2 ? 'orange' : 'green';
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="book-cell">
                                <img src="<?php echo $ruta_imagen; ?>" alt="Portada" class="book-thumb"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="book-fallback" style="display:none;"><i class="fas fa-book"></i></div>
                                <div>
                                    <div class="book-title"><?php echo htmlspecialchars($item['titulo']); ?></div>
                                    <div class="book-author"><i class="fas fa-pen-nib" style="font-size:0.7rem;"></i> <?php echo htmlspecialchars($item['autor']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="date-cell"><?php echo date('d/m/Y', strtotime($item['fecha_prestamo'])); ?></td>
                        <td><span class="date-limit <?php echo $date_class; ?>"><?php echo date('d/m/Y', strtotime($item['fecha_devolucion_esperada'])); ?></span></td>
                        <td><?php echo $badge; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="icon-wrap"><i class="fas fa-book-open"></i></div>
                <h3>Aún no tienes libros en tu historial</h3>
                <p>¡Visita el catálogo y solicita tu primer préstamo!</p>
                <a href="catalogo.php" class="btn-catalog"><i class="fas fa-search"></i> Ir al Catálogo</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
