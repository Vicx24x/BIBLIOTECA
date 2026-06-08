<?php
// catalogo.php
session_start();
require_once 'config/db.php';

// ─────────────────────────────────────────────
// SISTEMA DE ESTADOS CENTRALIZADO
// Posibles valores de 'tipo':
//   prestamo_exitoso | reserva_exitosa | reserva_duplicada | error_reserva
// ─────────────────────────────────────────────
$estado = [
    'tipo'    => null,   // identificador de estado
    'mensaje' => '',     // texto legible para el usuario
];

$estado_ui = [
    'prestamo_exitoso'   => ['bg' => '#d4edda', 'color' => '#155724', 'icono' => '✅'],
    'reserva_exitosa'    => ['bg' => '#d4edda', 'color' => '#155724', 'icono' => '✅'],
    'reserva_duplicada'  => ['bg' => '#fff3cd', 'color' => '#856404', 'icono' => '⚠️'],
    'error_reserva'      => ['bg' => '#f8d7da', 'color' => '#721c24', 'icono' => '❌'],
    'sin_sesion'         => ['bg' => '#f8d7da', 'color' => '#721c24', 'icono' => '🔒'],
];
// ─────────────────────────────────────────────

// Leer estado proveniente de redirección externa (ej: procesar_prestamo.php → ?msg=prestamo_exitoso)
if (!empty($_GET['msg']) && array_key_exists($_GET['msg'], $estado_ui)) {
    $mensajes_externos = [
        'prestamo_exitoso' => '¡Préstamo solicitado con éxito! Tienes 7 días para devolverlo.',
    ];
    $estado['tipo']    = $_GET['msg'];
    $estado['mensaje'] = $mensajes_externos[$_GET['msg']] ?? '';
}

// Procesar Reserva (Backend integrado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reservar') {
    if (!isset($_SESSION['id_usuario'])) {
        $estado['tipo']    = 'sin_sesion';
        $estado['mensaje'] = 'Debes iniciar sesión para reservar.';
    } else {
        $id_libro   = (int)$_POST['id_libro'];
        $id_usuario = $_SESSION['id_usuario'];

        try {
            // Verificar si ya lo tiene reservado
            $stmt_check = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_usuario = ? AND id_libro = ? AND estado = 'Pendiente'");
            $stmt_check->execute([$id_usuario, $id_libro]);

            if ($stmt_check->fetch()) {
                $estado['tipo']    = 'reserva_duplicada';
                $estado['mensaje'] = 'Ya estás en la lista de espera para este libro.';
            } else {
                $stmt_res = $pdo->prepare("INSERT INTO reservas (id_usuario, id_libro) VALUES (?, ?)");
                $stmt_res->execute([$id_usuario, $id_libro]);
                $estado['tipo']    = 'reserva_exitosa';
                $estado['mensaje'] = '¡Libro reservado con éxito! Te notificaremos cuando esté disponible.';
            }
        } catch (PDOException $e) {
            $estado['tipo']    = 'error_reserva';
            $estado['mensaje'] = 'Error al reservar.';
        }
    }
}

// Consultar categorías únicas para el menú de filtros
$categorias_stmt        = $pdo->query("SELECT DISTINCT categoria FROM libros WHERE categoria != ''");
$categorias_disponibles = $categorias_stmt->fetchAll(PDO::FETCH_COLUMN);

// Capturar filtros y parámetros de paginación
$busqueda        = $_GET['q']        ?? '';
$filtro_cat      = $_GET['categoria'] ?? '';
$letra           = $_GET['letra']    ?? '';
$pagina_actual   = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$libros_por_pagina = 10;
$offset          = ($pagina_actual - 1) * $libros_por_pagina;

try {
    // Construir condiciones SQL de forma dinámica
    $where_sql = " WHERE 1=1";
    $params    = [];

    if (!empty($busqueda)) {
        $where_sql    .= " AND (l.titulo LIKE :q OR l.autor LIKE :q)";
        $params['q']   = "%$busqueda%";
    }

    if (!empty($filtro_cat)) {
        $where_sql      .= " AND l.categoria = :cat";
        $params['cat']   = $filtro_cat;
    }

    if (!empty($letra)) {
        $where_sql         .= " AND l.titulo LIKE :letra";
        $params['letra']    = $letra . '%';
    }

    // A. Obtener el total de libros que coinciden con los filtros
    $count_sql   = "SELECT COUNT(*) FROM libros l" . $where_sql;
    $stmt_count  = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_libros  = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_libros / $libros_por_pagina);

    // B. Consulta principal unificada: Filtros + Paginación
    $sql = "SELECT l.*, 
            (SELECT COUNT(*) FROM ejemplares e WHERE e.id_libro = l.id_libro AND e.estado = 'Disponible') as copias_disponibles 
            FROM libros l" . $where_sql . " 
            ORDER BY l.titulo ASC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }

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
    <title>Catálogo Digital - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 40px; }
        .search-container { margin: 20px auto; display: flex; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 30px; overflow: hidden; background: white; max-width: 800px; }
        .search-container input { flex: 1; padding: 15px 25px; border: none; outline: none; font-size: 1rem; }
        .search-container select { padding: 15px; border: none; outline: none; border-left: 1px solid #eee; background: white; cursor: pointer; }
        .search-container button { padding: 15px 30px; background-color: #3498db; color: white; border: none; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .search-container button:hover { background-color: #2980b9; }

        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 30px; margin-top: 40px; }
        .book-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; flex-direction: column; }

        .book-cover { height: 300px; background: linear-gradient(135deg, #34495e, #2c3e50); color: white; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .book-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .book-cover .fallback-icon { font-size: 4rem; display: none; }

        .book-category { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; font-size: 0.7rem; padding: 4px 10px; border-radius: 15px; z-index: 10; }
        .book-info { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .book-title { font-size: 1.1rem; font-weight: bold; color: #2c3e50; margin: 0 0 10px 0; }
        .book-author { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 15px; flex: 1; }

        .btn-prestamo { background-color: #2ecc71; color: white; padding: 10px; border-radius: 5px; font-weight: bold; border: none; width: 100%; cursor: pointer;}
        .btn-reserva { background-color: #3498db; color: white; padding: 10px; border-radius: 5px; font-weight: bold; border: none; width: 100%; cursor: pointer;}
        .badge-agotado { text-align: center; color: #e74c3c; font-size: 0.85rem; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div style="text-align:center;">
        <a href="dashboard.php" style="text-decoration: none; color: #7f8c8d; font-weight: bold; float: left;"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        <h1 style="color: #2c3e50; margin:0 ;">📖 Catálogo Digital</h1>
        <p style="color: #7f8c8d;">Explora nuestra colección y solicita o reserva tus libros</p>
    </div>

    <?php
    // ─── Renderizado del estado centralizado ───────────────────────────────────
    if ($estado['tipo'] !== null):
        $ui = $estado_ui[$estado['tipo']] ?? ['bg' => '#e2e3e5', 'color' => '#383d41', 'icono' => 'ℹ️'];
    ?>
        <div style="background:<?php echo $ui['bg']; ?>; color:<?php echo $ui['color']; ?>; padding:10px; text-align:center; margin-bottom:20px;">
            <?php echo $ui['icono'] . ' ' . htmlspecialchars($estado['mensaje']); ?>
        </div>
    <?php endif; ?>
    <!-- ─────────────────────────────────────────────────────────────────────── -->

    <form class="search-container" method="GET" action="catalogo.php">
        <input type="text" name="q" placeholder="Buscar por título o autor..." value="<?php echo htmlspecialchars($busqueda); ?>">

        <select name="categoria">
            <option value="">Todas las categorías</option>
            <?php foreach($categorias_disponibles as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filtro_cat === $cat) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit"><i class="fas fa-search"></i> Buscar</button>
    </form>

    <div style="text-align: center; margin: 30px 0; font-size: 1.1rem; flex-wrap: wrap; display: flex; justify-content: center; gap: 10px;">
        <a href="catalogo.php" style="text-decoration: none; color: <?php echo empty($letra) ? '#e74c3c' : '#3498db'; ?>; font-weight: bold; padding-bottom: 2px; <?php echo empty($letra) ? 'border-bottom: 2px solid #e74c3c;' : ''; ?>">
            Todos
        </a>

        <?php foreach (range('A', 'Z') as $char): ?>
            <a href="?letra=<?php echo $char; ?><?php echo !empty($filtro_cat) ? '&categoria='.urlencode($filtro_cat) : ''; ?>"
               style="text-decoration: none; color: <?php echo ($letra === $char) ? '#e74c3c' : '#3498db'; ?>; font-weight: <?php echo ($letra === $char) ? 'bold' : 'normal'; ?>; padding-bottom: 2px; <?php echo ($letra === $char) ? 'border-bottom: 2px solid #e74c3c;' : ''; ?>">
                <?php echo $char; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="books-grid">
        <?php foreach ($libros as $libro): ?>
            <div class="book-card">
                <div class="book-cover">
                    <span class="book-category"><?php echo htmlspecialchars($libro['categoria']); ?></span>

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

                    <div class="fallback-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="book-info">
                    <h3 class="book-title"><?php echo htmlspecialchars($libro['titulo']); ?></h3>
                    <div class="book-author"><i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($libro['autor']); ?></div>

                    <?php if($libro['copias_disponibles'] > 0): ?>
                        <div style="text-align: center; color: #27ae60; font-size: 0.85rem; font-weight: bold; margin-bottom: 10px;">
                            <?php echo $libro['copias_disponibles']; ?> copias disponibles
                        </div>
                        <form action="procesar_prestamo.php" method="POST">
                            <input type="hidden" name="id_libro" value="<?php echo $libro['id_libro']; ?>">
                            <button type="submit" class="btn-prestamo"><i class="fas fa-hand-holding"></i> Solicitar Préstamo</button>
                        </form>
                    <?php else: ?>
                        <div class="badge-agotado"><i class="fas fa-exclamation-circle"></i> Agotado temporalmente</div>
                        <form action="catalogo.php" method="POST">
                            <input type="hidden" name="accion" value="reservar">
                            <input type="hidden" name="id_libro" value="<?php echo $libro['id_libro']; ?>">
                            <button type="submit" class="btn-reserva"><i class="fas fa-clock"></i> Reservar Libro</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
    <div style="text-align: center; margin: 40px 0; padding-bottom: 20px;">
        <?php
        $url_base = "?q=" . urlencode($busqueda) . "&categoria=" . urlencode($filtro_cat) . "&letra=" . urlencode($letra) . "&pagina=";
        ?>

        <?php if($pagina_actual > 1): ?>
            <a href="<?php echo $url_base . ($pagina_actual - 1); ?>" style="background: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-right: 15px;"><i class="fas fa-chevron-left"></i> Anterior</a>
        <?php endif; ?>

        <span style="font-weight: bold; color: #7f8c8d; font-size: 1.1rem;">
            Página <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
        </span>

        <?php if($pagina_actual < $total_paginas): ?>
            <a href="<?php echo $url_base . ($pagina_actual + 1); ?>" style="background: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin-left: 15px;">Siguiente <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</body>
</html>
