<?php
session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php'; // <--- AGREGAR ESTA LÍNEA
$estado = ['tipo' => null, 'mensaje' => ''];
$estado_ui = [
    'prestamo_exitoso'   => ['bg' => '#d1fae5', 'color' => '#065f46', 'icono' => 'check-circle'],
    'reserva_exitosa'    => ['bg' => '#d1fae5', 'color' => '#065f46', 'icono' => 'check-circle'],
    'reserva_duplicada'  => ['bg' => '#fef3c7', 'color' => '#78350f', 'icono' => 'exclamation-triangle'],
    'error_reserva'      => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icono' => 'times-circle'],
    'sin_sesion'         => ['bg' => '#fee2e2', 'color' => '#991b1b', 'icono' => 'lock'],
];

if (!empty($_GET['msg']) && array_key_exists($_GET['msg'], $estado_ui)) {
    $mensajes_externos = ['prestamo_exitoso' => '¡Préstamo solicitado con éxito! Tienes 7 días para devolverlo.'];
    $estado['tipo']    = $_GET['msg'];
    $estado['mensaje'] = $mensajes_externos[$_GET['msg']] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reservar') {
    if (!isset($_SESSION['id_usuario'])) {
        $estado['tipo'] = 'sin_sesion';
        $estado['mensaje'] = 'Debes iniciar sesión para reservar.';
    } else {
        $id_libro   = (int)$_POST['id_libro'];
        $id_usuario = $_SESSION['id_usuario'];
        try {
            $stmt_check = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_usuario = ? AND id_libro = ? AND estado = 'Pendiente'");
            $stmt_check->execute([$id_usuario, $id_libro]);
            if ($stmt_check->fetch()) {
                $estado['tipo'] = 'reserva_duplicada';
                $estado['mensaje'] = 'Ya estás en la lista de espera para este libro.';
            } else {
                $stmt_res = $pdo->prepare("INSERT INTO reservas (id_usuario, id_libro) VALUES (?, ?)");
                $stmt_res->execute([$id_usuario, $id_libro]);
                $estado['tipo'] = 'reserva_exitosa';
                $estado['mensaje'] = '¡Libro reservado con éxito! Te notificaremos cuando esté disponible.';
            }
        } catch (PDOException $e) {
            $estado['tipo'] = 'error_reserva';
            $estado['mensaje'] = 'Error al reservar.';
        }
    }
}

$categorias_stmt        = $pdo->query("SELECT DISTINCT categoria FROM libros WHERE categoria != ''");
$categorias_disponibles = $categorias_stmt->fetchAll(PDO::FETCH_COLUMN);

$busqueda        = $_GET['q']        ?? '';
$filtro_cat      = $_GET['categoria'] ?? '';
$letra           = $_GET['letra']    ?? '';
$pagina_actual   = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$libros_por_pagina = 12;
$offset          = ($pagina_actual - 1) * $libros_por_pagina;

try {
    $where_sql = " WHERE 1=1";
    $params    = [];
    if (!empty($busqueda)) { $where_sql .= " AND (l.titulo LIKE :q1 OR l.autor LIKE :q2)"; $params['q1'] = "%$busqueda%"; $params['q2'] = "%$busqueda%"; }
    if (!empty($filtro_cat)) { $where_sql .= " AND l.categoria = :cat"; $params['cat'] = $filtro_cat; }
    if (!empty($letra)) { $where_sql .= " AND l.titulo LIKE :letra"; $params['letra'] = $letra . '%'; }

    $count_sql  = "SELECT COUNT(*) FROM libros l" . $where_sql;
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_libros  = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_libros / $libros_por_pagina);

    $sql = "SELECT l.*, (SELECT COUNT(*) FROM ejemplares e WHERE e.id_libro = l.id_libro AND e.estado = 'Disponible') as copias_disponibles 
            FROM libros l" . $where_sql . " ORDER BY l.titulo ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) { $stmt->bindValue(":$key", $val); }
    $stmt->bindValue(':limit',  $libros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,            PDO::PARAM_INT);
    $stmt->execute();
    $libros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar el catálogo: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo Digital — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background: #f5f3ef;
            margin: 0;
            color: #1a1a2e;
        }

        .catalog-wrap { max-width: 1400px; margin: 0 auto; padding: 36px 32px 60px; }

        /* ─── Top navigation bar ─── */
        .catalog-topnav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--guinda, #850021);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 8px 16px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.2s;
        }
        .back-link:hover { background: var(--guinda,#850021); color: #fff; }

        .catalog-heading h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 2rem;
            color: var(--guinda, #850021);
            margin: 0 0 4px;
            text-align: center;
        }
        .catalog-heading p { color: #6b7280; font-size: 0.875rem; text-align: center; margin: 0; }

        /* ─── Search ─── */
        .search-bar {
            display: flex;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 20px;
            border: 1.5px solid #e5e7eb;
            transition: border-color 0.2s;
        }
        .search-bar:focus-within { border-color: var(--guinda,#850021); }
        .search-input-wrap { flex: 1; display: flex; align-items: center; padding: 0 20px; gap: 12px; }
        .search-input-wrap i { color: #9ca3af; }
        .search-input-wrap input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 0.975rem;
            font-family: inherit;
            padding: 14px 0;
            background: transparent;
            color: #111827;
        }
        .search-input-wrap input::placeholder { color: #9ca3af; }
        .search-select {
            border: none;
            border-left: 1.5px solid #f3f4f6;
            outline: none;
            font-family: inherit;
            font-size: 0.875rem;
            padding: 0 18px;
            background: #fafafa;
            color: #374151;
            cursor: pointer;
            min-width: 160px;
        }
        .search-btn {
            background: linear-gradient(135deg, var(--guinda,#850021), var(--guinda-dark,#5a0016));
            color: #fff;
            border: none;
            padding: 0 28px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: opacity 0.2s;
        }
        .search-btn:hover { opacity: 0.9; }

        /* ─── Alphabet filter ─── */
        .alpha-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 32px;
            background: #fff;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .alpha-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px; height: 34px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            color: #6b7280;
            background: #f9fafb;
            transition: all 0.15s;
        }
        .alpha-btn:hover { background: #ffe4ea; color: var(--guinda,#850021); }
        .alpha-btn.active { background: var(--guinda,#850021); color: #fff; box-shadow: 0 4px 12px rgba(133,0,33,0.25); }
        .alpha-btn.all { min-width: 52px; font-size: 0.75rem; letter-spacing: 0.3px; }

        /* ─── Book Grid ─── */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .book-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            display: flex;
            flex-direction: column;
            transition: transform 0.25s, box-shadow 0.25s;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .book-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,0.13); }

        .book-cover {
            height: 240px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #2d000b, #850021);
            display: flex; align-items: center; justify-content: center;
        }
        .book-cover img {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.4s ease;
        }
        .book-card:hover .book-cover img { transform: scale(1.05); }
        .book-cover .fallback-icon { font-size: 3.5rem; color: rgba(255,255,255,0.25); display: none; }

        .book-cat-badge {
            position: absolute; top: 10px; left: 10px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .book-body { padding: 16px; flex: 1; display: flex; flex-direction: column; }
        .book-title { font-size: 0.95rem; font-weight: 700; color: #111827; margin: 0 0 6px; line-height: 1.3; }
        .book-author { color: #9ca3af; font-size: 0.8rem; margin-bottom: 14px; flex: 1; }
        .book-author i { margin-right: 4px; }

        .copies-label {
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex; align-items: center; gap: 4px;
        }
        .copies-label.available { color: #059669; }
        .copies-label.unavailable { color: #dc2626; }

        .btn-action {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 9px;
            font-family: inherit;
            font-size: 0.83rem;
            font-weight: 700;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: all 0.2s;
        }
        .btn-borrow  { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 4px 12px rgba(16,185,129,0.25); }
        .btn-reserve { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; box-shadow: 0 4px 12px rgba(59,130,246,0.25); }
        .btn-borrow:hover  { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(16,185,129,0.35); }
        .btn-reserve:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(59,130,246,0.35); }

        /* ─── Toast ─── */
        #toast {
            position: fixed; top: 24px; right: 24px; z-index: 9999;
            min-width: 300px; max-width: 420px;
            padding: 16px 20px;
            border-radius: 14px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.18);
            display: flex; align-items: flex-start; gap: 12px;
            font-size: 0.9rem; line-height: 1.4;
            opacity: 0; transform: translateX(40px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            pointer-events: none;
        }
        #toast.visible { opacity: 1; transform: translateX(0); pointer-events: all; }
        #toast.hiding  { opacity: 0; transform: translateX(40px); }
        .toast-bar { position:absolute; bottom:0; left:0; height:3px; border-radius:0 0 14px 14px; background:rgba(0,0,0,0.12); animation: tp 4s linear forwards; }
        @keyframes tp { from{width:100%} to{width:0%} }
        .toast-close { background:none; border:none; cursor:pointer; opacity:.6; font-size:1rem; padding:0; color:inherit; flex-shrink:0; }
        .toast-close:hover { opacity:1; }

        /* ─── Pagination ─── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding-bottom: 20px;
        }
        .page-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px;
            background: var(--guinda,#850021);
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.875rem;
            box-shadow: 0 4px 12px rgba(133,0,33,0.25);
            transition: all 0.2s;
        }
        .page-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(133,0,33,0.35); }
        .page-info { color: #6b7280; font-size: 0.875rem; font-weight: 600; }

        /* ─── Empty state ─── */
        .empty-state {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 16px; }
        .empty-state h3 { color: #374151; margin: 0 0 8px; font-size: 1.1rem; }
        .empty-state p { color: #9ca3af; font-size: 0.875rem; margin: 0; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if($estado['tipo']): $ui = $estado_ui[$estado['tipo']]; ?>
    <div id="toast" style="background:<?php echo $ui['bg']; ?>; color:<?php echo $ui['color']; ?>; position:relative;">
        <i class="fas fa-<?php echo $ui['icono']; ?>" style="font-size:1.2rem; flex-shrink:0; margin-top:1px;"></i>
        <span style="flex:1; font-weight:600;"><?php echo htmlspecialchars($estado['mensaje']); ?></span>
        <button class="toast-close" onclick="closeToast()">✕</button>
        <div class="toast-bar"></div>
    </div>
    <script>
    (function(){
        var t=document.getElementById('toast'), tid;
        function closeToast(){ clearTimeout(tid); t.classList.add('hiding'); t.addEventListener('transitionend',function(){t.style.display='none';},{once:true}); }
        window.closeToast=closeToast;
        requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.classList.add('visible'); tid=setTimeout(closeToast,4000); }); });
    })();
    </script>
    <?php endif; ?>

    <div class="catalog-wrap">

        <div class="catalog-topnav">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="catalog-heading">
                <h1>📖 Catálogo Digital</h1>
                <p>Explora nuestra colección · <?php echo number_format($total_libros); ?> títulos encontrados</p>
            </div>
            <div style="width:120px;"></div>
        </div>

        <!-- Search -->
        <form class="search-bar" method="GET" action="catalogo.php">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="q" placeholder="Buscar por título o autor..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <select name="categoria" class="search-select">
                <option value="">Todas las categorías</option>
                <?php foreach($categorias_disponibles as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filtro_cat === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="search-btn"><i class="fas fa-search"></i> Buscar</button>
        </form>

        <!-- Alphabet -->
        <div class="alpha-row">
            <a href="catalogo.php" class="alpha-btn all <?php echo empty($letra) ? 'active' : ''; ?>">Todos</a>
            <?php foreach(range('A','Z') as $char): ?>
                <a href="?letra=<?php echo $char; ?><?php echo !empty($filtro_cat) ? '&categoria='.urlencode($filtro_cat) : ''; ?>"
                   class="alpha-btn <?php echo $letra === $char ? 'active' : ''; ?>">
                    <?php echo $char; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Books -->
        <div class="books-grid">
            <?php if(empty($libros)): ?>
                <div class="empty-state">
                    <div><i class="fas fa-book-open"></i></div>
                    <h3>No se encontraron libros</h3>
                    <p>Intenta con otro término de búsqueda o filtro.</p>
                </div>
            <?php endif; ?>
            <?php foreach($libros as $libro): ?>
                <div class="book-card">
                    <div class="book-cover">
                        <span class="book-cat-badge"><?php echo htmlspecialchars($libro['categoria']); ?></span>
                        <?php
                        $ruta_local = !empty($libro['portada']) ? 'portadas/' . basename($libro['portada']) : '';
                        if (!empty($libro['portada']) && file_exists(__DIR__ . '/' . $ruta_local)) {
                            $ruta_imagen = $ruta_local;
                        } else {
                            $ruta_imagen = "https://covers.openlibrary.org/b/isbn/" . urlencode($libro['isbn']) . "-M.jpg?default=404";
                        }
                        ?>
                        <img src="<?php echo $ruta_imagen; ?>"
                             alt="<?php echo htmlspecialchars($libro['titulo']); ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div class="fallback-icon"><i class="fas fa-book"></i></div>
                    </div>
                    <div class="book-body">
                        <h3 class="book-title"><?php echo htmlspecialchars($libro['titulo']); ?></h3>
                        <div class="book-author"><i class="fas fa-pen-nib"></i> <?php echo htmlspecialchars($libro['autor']); ?></div>

                        <?php if($libro['copias_disponibles'] > 0): ?>
                            <div class="copies-label available">
                                <i class="fas fa-check-circle"></i>
                                <?php echo $libro['copias_disponibles']; ?> copia(s) disponible(s)
                            </div>
                            <form action="procesar_prestamo.php" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    
    <input type="hidden" name="id_libro" value="<?php echo $libro['id_libro']; ?>">
    <button type="submit" class="btn-action btn-borrow">
        <i class="fas fa-hand-holding-heart"></i> Solicitar Préstamo
    </button>
</form>
                        <?php else: ?>
                            <div class="copies-label unavailable">
                                <i class="fas fa-exclamation-circle"></i> Agotado temporalmente
                            </div>
                            <form action="catalogo.php" method="POST">
                                <input type="hidden" name="accion" value="reservar">
                                <input type="hidden" name="id_libro" value="<?php echo $libro['id_libro']; ?>">
                                <button type="submit" class="btn-action btn-reserve">
                                    <i class="fas fa-clock"></i> Reservar Libro
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_paginas > 1): ?>
        <?php $url_base = "?q=".urlencode($busqueda)."&categoria=".urlencode($filtro_cat)."&letra=".urlencode($letra)."&pagina="; ?>
        <div class="pagination">
            <?php if($pagina_actual > 1): ?>
                <a href="<?php echo $url_base.($pagina_actual-1); ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i> Anterior
                </a>
            <?php endif; ?>
            <span class="page-info">Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?></span>
            <?php if($pagina_actual < $total_paginas): ?>
                <a href="<?php echo $url_base.($pagina_actual+1); ?>" class="page-btn">
                    Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
