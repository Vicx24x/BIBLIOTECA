<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    die("<div style='font-family:sans-serif;padding:40px;text-align:center;'><h2 style='color:#850021'>Acceso Denegado</h2><p>Solo los administradores pueden ver los reportes.</p><a href='dashboard.php' style='color:#850021;font-weight:700;'>← Volver al inicio</a></div>");
}

try {
    $sql_usuarios = "SELECT r.nombre_rol, COUNT(u.id_usuario) as total 
                     FROM roles r LEFT JOIN usuarios u ON r.id_rol = u.id_rol 
                     GROUP BY r.id_rol";
    $stats_usuarios = $pdo->query($sql_usuarios)->fetchAll();

    $sql_libros_populares = "SELECT l.titulo, l.autor, COUNT(p.id_prestamo) as total_prestamos 
                             FROM libros l
                             INNER JOIN ejemplares e ON l.id_libro = e.id_libro
                             INNER JOIN prestamos p ON e.id_ejemplar = p.id_ejemplar
                             GROUP BY l.id_libro ORDER BY total_prestamos DESC LIMIT 5";
    $libros_populares = $pdo->query($sql_libros_populares)->fetchAll();

    $sql_retrasos = "SELECT u.nombre, u.correo, l.titulo, p.fecha_devolucion_esperada, 
                     DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) as dias_retraso
                     FROM prestamos p
                     INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                     INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
                     INNER JOIN libros l ON e.id_libro = l.id_libro
                     WHERE p.estado = 'Activo' AND p.fecha_devolucion_esperada < CURRENT_DATE
                     ORDER BY dias_retraso DESC";
    $usuarios_retrasados = $pdo->query($sql_retrasos)->fetchAll();

    $total_prestamos = $pdo->query("SELECT COUNT(*) FROM prestamos")->fetchColumn();
    $max_prestamos = count($libros_populares) > 0 ? max(array_column($libros_populares, 'total_prestamos')) : 1;
} catch (PDOException $e) {
    die("Error al generar reportes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'DM Sans','Segoe UI',sans-serif; background: #f5f3ef; margin: 0; color: #1a1a2e; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 36px 32px 60px; }

        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; flex-wrap: wrap; gap: 12px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda,#850021); text-decoration: none; font-weight: 600; font-size: 0.875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.2s; }
        .back-link:hover { background: var(--guinda,#850021); color: #fff; }
        .page-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda,#850021); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: 0.875rem; margin: 0; }
        .btn-backup { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg,#059669,#047857); color: #fff; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.875rem; box-shadow: 0 4px 12px rgba(5,150,105,0.28); transition: all 0.2s; }
        .btn-backup:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(5,150,105,0.38); }

        /* Grid */
        .reports-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        @media (max-width: 800px) { .reports-grid { grid-template-columns: 1fr; } }
        .card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); overflow: hidden; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.05rem; color: #111827; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card-header h2 i { color: var(--guinda,#850021); }
        .card-header .ch-sub { font-size: 0.75rem; color: #9ca3af; margin-top: 2px; }
        .card-body { padding: 20px 24px; }

        /* Roles chart */
        .role-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #f3f4f6; }
        .role-item:last-child { border-bottom: none; }
        .role-name { font-weight: 600; color: #374151; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .role-dot { width: 8px; height: 8px; border-radius: 50%; }
        .role-count { background: linear-gradient(135deg,var(--guinda,#850021),#5a0016); color: #fff; padding: 3px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }

        /* Books chart */
        .book-bar-item { margin-bottom: 14px; }
        .book-bar-item:last-child { margin-bottom: 0; }
        .bar-meta { display: flex; justify-content: space-between; margin-bottom: 5px; align-items: baseline; }
        .bar-title { font-size: 0.84rem; font-weight: 700; color: #111827; }
        .bar-count { font-size: 0.78rem; font-weight: 700; color: var(--guinda,#850021); }
        .bar-track { height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; }
        .bar-fill { height: 100%; border-radius: 4px; background: linear-gradient(90deg,var(--guinda,#850021),#c9a84c); transition: width 0.8s ease; }
        .bar-author { font-size: 0.75rem; color: #9ca3af; margin-bottom: 6px; }

        /* Retrasos table */
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 11px 16px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; background: #fafafa; border-bottom: 1px solid #f3f4f6; }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fff8f0; }
        tbody td { padding: 12px 16px; font-size: 0.875rem; vertical-align: middle; }

        .days-badge { display: inline-flex; align-items: center; gap: 4px; background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 0.78rem; font-weight: 700; }
        .days-badge.warn { background: #fef3c7; color: #78350f; }

        .user-mini strong { display: block; font-size: 0.9rem; font-weight: 700; color: #111827; }
        .user-mini span { font-size: 0.78rem; color: #9ca3af; }

        .mailto-btn { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg,var(--guinda,#850021),#5a0016); color: #fff; padding: 5px 12px; border-radius: 8px; text-decoration: none; font-size: 0.78rem; font-weight: 700; transition: all 0.2s; }
        .mailto-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 10px rgba(133,0,33,0.3); }

        .all-clear { display: flex; align-items: center; gap: 10px; background: #d1fae5; color: #065f46; padding: 16px 20px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">
        <div class="topnav">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div>
                <h1 class="page-title"><i class="fas fa-chart-pie" style="font-size:1.4rem;"></i> Centro de Reportes</h1>
                <p class="page-sub">Análisis en tiempo real del ecosistema de la biblioteca</p>
            </div>
            <a href="respaldo_bd.php" class="btn-backup"><i class="fas fa-database"></i> Generar Respaldo</a>
        </div>

        <div class="reports-grid">

            <!-- Usuarios por rol -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2><i class="fas fa-users"></i> Usuarios por Rol</h2>
                        <div class="ch-sub">Distribución de cuentas por nivel de acceso</div>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    $role_colors = ['#850021','#c9a84c','#3b82f6','#10b981'];
                    $ci = 0;
                    foreach($stats_usuarios as $stat):
                    ?>
                    <div class="role-item">
                        <span class="role-name">
                            <span class="role-dot" style="background:<?php echo $role_colors[$ci % count($role_colors)]; ?>;"></span>
                            <?php echo htmlspecialchars($stat['nombre_rol']); ?>
                        </span>
                        <span class="role-count"><?php echo $stat['total']; ?></span>
                    </div>
                    <?php $ci++; endforeach; ?>
                </div>
            </div>

            <!-- Libros más populares -->
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2><i class="fas fa-fire"></i> Libros Más Populares</h2>
                        <div class="ch-sub">Títulos con mayor índice de rotación histórica</div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(count($libros_populares) > 0): ?>
                        <?php foreach($libros_populares as $libro):
                            $pct = $max_prestamos > 0 ? round($libro['total_prestamos'] / $max_prestamos * 100) : 0;
                        ?>
                        <div class="book-bar-item">
                            <div class="bar-meta">
                                <span class="bar-title"><?php echo htmlspecialchars($libro['titulo']); ?></span>
                                <span class="bar-count"><?php echo $libro['total_prestamos']; ?> préstamos</span>
                            </div>
                            <div class="bar-author"><?php echo htmlspecialchars($libro['autor']); ?></div>
                            <div class="bar-track"><div class="bar-fill" style="width:<?php echo $pct; ?>%;"></div></div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#9ca3af; text-align:center; padding:20px 0;">Aún no hay datos de préstamos suficientes.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Retrasos -->
        <div class="card">
            <div class="card-header">
                <div>
                    <h2><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> Reporte de Retrasos</h2>
                    <div class="ch-sub">Usuarios que han superado su fecha límite de entrega</div>
                </div>
                <?php if(count($usuarios_retrasados) > 0): ?>
                <span style="background:#fee2e2; color:#991b1b; font-size:0.78rem; font-weight:700; padding:4px 12px; border-radius:20px;"><?php echo count($usuarios_retrasados); ?> caso(s)</span>
                <?php endif; ?>
            </div>

            <?php if(count($usuarios_retrasados) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Libro Retenido</th>
                        <th>Fecha Límite</th>
                        <th>Retraso</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios_retrasados as $m): ?>
                    <tr>
                        <td>
                            <div class="user-mini">
                                <strong><?php echo htmlspecialchars($m['nombre']); ?></strong>
                                <span><?php echo htmlspecialchars($m['correo']); ?></span>
                            </div>
                        </td>
                        <td style="font-weight:600; color:#111827;"><?php echo htmlspecialchars($m['titulo']); ?></td>
                        <td style="color:#dc2626; font-weight:700;"><?php echo date('d/m/Y', strtotime($m['fecha_devolucion_esperada'])); ?></td>
                        <td>
                            <span class="days-badge <?php echo $m['dias_retraso'] <= 3 ? 'warn' : ''; ?>">
                                <i class="fas fa-clock"></i> <?php echo $m['dias_retraso']; ?> día(s)
                            </span>
                        </td>
                        <td>
                            <a href="mailto:<?php echo htmlspecialchars($m['correo']); ?>?subject=Aviso de Retraso - BiblioMPS&body=Estimado/a <?php echo urlencode($m['nombre']); ?>, le recordamos que tiene un libro pendiente de devolución." class="mailto-btn">
                                <i class="fas fa-envelope"></i> Enviar Aviso
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="card-body">
                <div class="all-clear">
                    <i class="fas fa-check-circle" style="font-size:1.3rem;"></i>
                    ¡Excelente! Actualmente no hay ningún usuario con préstamos retrasados.
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
