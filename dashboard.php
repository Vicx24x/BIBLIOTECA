<?php
session_start();
require_once 'config/db.php';

// [REQ-4] Carga defensiva: si el archivo falla, el dashboard no muere
if (file_exists(__DIR__ . '/notificaciones_pantalla.php')) {
    require_once 'notificaciones_pantalla.php';
} else {
    // Fallback vacío para que renderizar_notificaciones_pantalla() no rompa
    $notif_pantalla = ['proximos' => [], 'vencidos' => [], 'es_admin' => false];
    function renderizar_notificaciones_pantalla(array $notif): void {}
}

if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$rol_usuario    = $_SESSION['rol']    ?? 'Usuario';
$nombre_usuario = $_SESSION['nombre'] ?? 'Invitado';

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM ejemplares WHERE estado = 'Disponible'");
    $total_disponibles = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM prestamos WHERE estado = 'Activo'");
    $total_prestamos = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'Activo'");
    $total_usuarios = $stmt->fetchColumn();

    $sql_disponibilidad = "SELECT l.titulo, l.autor, COUNT(e.id_ejemplar) as copias_disponibles 
                           FROM libros l
                           LEFT JOIN ejemplares e ON l.id_libro = e.id_libro AND e.estado = 'Disponible'
                           GROUP BY l.id_libro
                           ORDER BY l.titulo ASC LIMIT 6";
    $tabla_disponibilidad = $pdo->query($sql_disponibilidad)->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar el panel: " . $e->getMessage());
}

$hora = (int)date('H');
if ($hora < 12)       $saludo = 'Buenos días';
elseif ($hora < 19)   $saludo = 'Buenas tardes';
else                  $saludo = 'Buenas noches';
$primer_nombre = explode(' ', $nombre_usuario)[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: #f5f3ef;
            margin: 0;
            color: #1a1a2e;
        }

        /* ─── App Shell ─── */
        .app-shell { display: flex; min-height: calc(100vh - 110px); }

        /* ─── Sidebar ─── */
        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: linear-gradient(180deg, var(--guinda-dark,#5a0016) 0%, #2d000b 100%);
            display: flex;
            flex-direction: column;
            padding: 28px 0 20px;
            position: relative;
            overflow: hidden;
        }
        .sidebar::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,0.07) 0%, transparent 70%);
            bottom: -80px; right: -80px;
            pointer-events: none;
        }

        .sidebar-brand {
            text-align: center;
            padding: 0 20px 28px;
            border-bottom: 1px solid rgba(201,168,76,0.18);
            margin-bottom: 16px;
        }
        .sidebar-brand .brand-icon {
            width: 50px; height: 50px;
            background: linear-gradient(135deg, var(--gold,#c9a84c), #a07830);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            color: #fff;
            margin: 0 auto 10px;
            box-shadow: 0 4px 16px rgba(201,168,76,0.4);
        }
        .sidebar-brand h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.15rem;
            color: #fff;
            margin: 0 0 2px;
            font-weight: 700;
        }
        .sidebar-brand span {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.5);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-section-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            padding: 16px 24px 6px;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 24px;
            text-decoration: none;
            color: rgba(255,255,255,0.65);
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 0;
            transition: all 0.2s ease;
            margin: 2px 12px;
            border-radius: 10px;
            position: relative;
        }
        .sidebar nav a .nav-icon {
            width: 34px; height: 34px;
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            background: rgba(255,255,255,0.08);
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .sidebar nav a:hover {
            color: #fff;
            background: rgba(255,255,255,0.07);
        }
        .sidebar nav a:hover .nav-icon { background: rgba(255,255,255,0.15); }
        .sidebar nav a.active {
            color: #fff;
            background: rgba(201,168,76,0.15);
        }
        .sidebar nav a.active .nav-icon {
            background: linear-gradient(135deg, var(--gold,#c9a84c), #a07830);
            box-shadow: 0 4px 12px rgba(201,168,76,0.35);
            color: #fff;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 12px 0;
            border-top: 1px solid rgba(255,255,255,0.07);
        }
        .logout-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 24px;
            text-decoration: none;
            color: rgba(255,100,100,0.85);
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.2s;
        }
        .logout-link:hover { background: rgba(231,76,60,0.15); color: #ff6b6b; }
        .logout-link .nav-icon { background: rgba(231,76,60,0.15); color: rgba(255,100,100,0.85); }

        /* ─── Main Content ─── */
        .main { flex: 1; padding: 36px 40px; overflow: auto; }

        /* ─── Top Bar ─── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 36px;
        }
        .topbar-left h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--guinda, #850021);
            margin: 0 0 2px;
        }
        .topbar-left p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0;
        }
        .role-chip {
            background: linear-gradient(135deg, var(--guinda,#850021), var(--guinda-dark,#5a0016));
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(133,0,33,0.25);
        }

        /* ─── Stat Cards ─── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid rgba(0,0,0,0.04);
            transition: transform 0.25s, box-shadow 0.25s;
            position: relative;
            overflow: hidden;
        }
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
        }
        .stat-card.green::after  { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.amber::after  { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.blue::after   { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-card.guinda::after { background: linear-gradient(90deg, var(--guinda,#850021), var(--guinda-dark,#5a0016)); }

        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.10); }

        .stat-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .stat-icon.green  { background: #d1fae5; color: #059669; }
        .stat-icon.amber  { background: #fef3c7; color: #d97706; }
        .stat-icon.blue   { background: #dbeafe; color: #2563eb; }
        .stat-icon.guinda { background: #ffe4ea; color: var(--guinda,#850021); }

        .stat-info h3 { margin: 0; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; }
        .stat-num { font-size: 2rem; font-weight: 800; color: #111827; line-height: 1.1; margin: 4px 0 0; }

        /* Action card */
        .action-card {
            background: linear-gradient(135deg, var(--guinda,#850021) 0%, #2d000b 100%);
            border-radius: 18px;
            padding: 24px;
            color: white;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 8px 28px rgba(133,0,33,0.30);
            transition: transform 0.25s, box-shadow 0.25s;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 12px 36px rgba(133,0,33,0.40); }
        .action-card .stat-icon { background: rgba(255,255,255,0.15); color: white; }
        .action-card h3 { margin: 0; font-size: 0.9rem; font-weight: 700; color: rgba(255,255,255,0.75); }
        .action-card .stat-num { font-size: 1.1rem; color: #fff; font-weight: 700; }

        /* ─── Table Section ─── */
        .table-section {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
        }
        .table-header {
            padding: 22px 28px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-header h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.15rem;
            color: #111827;
            margin: 0;
            display: flex; align-items: center; gap: 10px;
        }
        .table-header h2 i { color: var(--guinda, #850021); }
        .live-badge {
            font-size: 0.7rem;
            font-weight: 700;
            background: #d1fae5;
            color: #065f46;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            display: flex; align-items: center; gap: 4px;
        }
        .live-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #10b981;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            padding: 12px 28px;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9ca3af;
            background: #fafafa;
            border-bottom: 1px solid #f3f4f6;
        }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fdf8f0; }
        tbody td { padding: 14px 28px; font-size: 0.875rem; vertical-align: middle; }
        td.book-title-cell { font-weight: 600; color: #111827; }
        td.book-author-cell { color: #6b7280; }

        .badge-available {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #d1fae5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-unavailable {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        /* ─── Responsive ─── */
        @media (max-width: 900px) {
            .sidebar { width: 220px; }
            .main { padding: 24px; }
        }
        @media (max-width: 700px) {
            .app-shell { flex-direction: column; }
            .sidebar { width: 100%; flex-direction: row; flex-wrap: wrap; padding: 12px; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <?php renderizar_notificaciones_pantalla($notif_pantalla); // [REQ-4] ?>

    <div class="app-shell">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon"><i class="fas fa-book-open"></i></div>
                <h2>BiblioMPS</h2>
                <span>UPIICSA · IPN</span>
            </div>

            <div class="nav-section-label">General</div>
            <nav>
                <a href="dashboard.php" class="active">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    Inicio
                </a>
                <a href="catalogo.php">
                    <span class="nav-icon"><i class="fas fa-search"></i></span>
                    Catálogo Digital
                </a>
                <a href="mis_libros.php">
                    <span class="nav-icon"><i class="fas fa-book-open"></i></span>
                    Mis Libros
                </a>
            </nav>

            <?php if($rol_usuario === 'Administrador' || $rol_usuario === 'Bibliotecario'): ?>
            <div class="nav-section-label">Gestión</div>
            <nav>
                <a href="prestamos.php">
                    <span class="nav-icon"><i class="fas fa-exchange-alt"></i></span>
                    Operaciones
                </a>
                <a href="inventario.php">
                    <span class="nav-icon"><i class="fas fa-boxes"></i></span>
                    Inventario
                </a>
            </nav>
            <?php endif; ?>

            <?php if($rol_usuario === 'Administrador'): ?>
            <div class="nav-section-label">Administración</div>
            <nav>
                <a href="usuarios.php">
                    <span class="nav-icon"><i class="fas fa-users"></i></span>
                    Usuarios
                </a>
                <a href="reportes.php">
                    <span class="nav-icon"><i class="fas fa-chart-pie"></i></span>
                    Reportes
                </a>
            </nav>
            <?php endif; ?>

            <div class="sidebar-footer">
                <a href="cerrar_sesion.php" class="logout-link">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                    Cerrar Sesión
                </a>
            </div>
        </aside>

        <!-- MAIN -->
        <main class="main">

            <!-- Topbar -->
            <div class="topbar">
                <div class="topbar-left">
                    <h1><?php echo $saludo ?>, <?php echo htmlspecialchars($primer_nombre); ?> 👋</h1>
                    <p>Panel de control — <?php echo date('l, d \d\e F \d\e Y'); ?></p>
                </div>
                <div class="role-chip">
                    <i class="fas fa-shield-halved"></i>
                    <?php echo htmlspecialchars($rol_usuario); ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-row">

                <!-- Mis libros (action card) -->
                <a href="mis_libros.php" class="action-card" style="text-decoration:none;">
                    <span class="stat-icon"><i class="fas fa-book-reader"></i></span>
                    <div>
                        <h3>Mis Libros</h3>
                        <div class="stat-num">Ver historial →</div>
                    </div>
                </a>

                <div class="stat-card green">
                    <div class="stat-icon green"><i class="fas fa-book"></i></div>
                    <div class="stat-info">
                        <h3>Libros Disponibles</h3>
                        <div class="stat-num"><?php echo number_format($total_disponibles); ?></div>
                    </div>
                </div>

                <div class="stat-card amber">
                    <div class="stat-icon amber"><i class="fas fa-hand-holding"></i></div>
                    <div class="stat-info">
                        <h3>Préstamos Activos</h3>
                        <div class="stat-num"><?php echo number_format($total_prestamos); ?></div>
                    </div>
                </div>

                <?php if($rol_usuario === 'Administrador'): ?>
                <div class="stat-card blue">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <div class="stat-info">
                        <h3>Usuarios Registrados</h3>
                        <div class="stat-num"><?php echo number_format($total_usuarios); ?></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Table -->
            <div class="table-section">
                <div class="table-header">
                    <h2><i class="fas fa-layer-group"></i> Disponibilidad en Tiempo Real</h2>
                    <span class="live-badge">
                        <span class="live-dot"></span> En vivo
                    </span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Título del Libro</th>
                            <th>Autor</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tabla_disponibilidad as $i => $fila): ?>
                        <tr>
                            <td style="color:#d1d5db; font-weight:600; font-size:0.8rem;"><?php echo $i+1; ?></td>
                            <td class="book-title-cell"><?php echo htmlspecialchars($fila['titulo']); ?></td>
                            <td class="book-author-cell"><?php echo htmlspecialchars($fila['autor']); ?></td>
                            <td>
                                <?php if($fila['copias_disponibles'] > 0): ?>
                                    <span class="badge-available">
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo $fila['copias_disponibles']; ?> disponible(s)
                                    </span>
                                <?php else: ?>
                                    <span class="badge-unavailable">
                                        <i class="fas fa-times-circle"></i> Agotado
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</body>
</html>
