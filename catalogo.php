<?php
// catalogo.php
session_start();
require_once 'config/db.php';

// 1. Procesar Acciones (Reserva)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reservar') {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: catalogo.php?msg=error_auth");
        exit();
    }
    $id_libro = (int)$_POST['id_libro'];
    $id_usuario = $_SESSION['id_usuario'];
    try {
        $stmt_check = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_usuario = ? AND id_libro = ? AND estado = 'Pendiente'");
        $stmt_check->execute([$id_usuario, $id_libro]);
        if ($stmt_check->fetch()) {
            header("Location: catalogo.php?msg=reserva_duplicada");
        } else {
            $stmt_res = $pdo->prepare("INSERT INTO reservas (id_usuario, id_libro) VALUES (?, ?)");
            $stmt_res->execute([$id_usuario, $id_libro]);
            header("Location: catalogo.php?msg=reserva_exitosa");
        }
    } catch (PDOException $e) {
        header("Location: catalogo.php?msg=error_reserva");
    }
    exit();
}

// 2. Cargar datos
$categorias_stmt = $pdo->query("SELECT DISTINCT categoria FROM libros WHERE categoria != ''");
$categorias_disponibles = $categorias_stmt->fetchAll(PDO::FETCH_COLUMN);

$busqueda = $_GET['q'] ?? ''; 
$filtro_cat = $_GET['categoria'] ?? '';
$letra = $_GET['letra'] ?? '';
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$libros_por_pagina = 10;
$offset = ($pagina_actual - 1) * $libros_por_pagina;

try {
    $where_sql = " WHERE 1=1";
    $params = [];
    if (!empty($busqueda)) { $where_sql .= " AND (titulo LIKE :q OR autor LIKE :q)"; $params['q'] = "%$busqueda%"; }
    if (!empty($filtro_cat)) { $where_sql .= " AND categoria = :cat"; $params['cat'] = $filtro_cat; }
    if (!empty($letra)) { $where_sql .= " AND titulo LIKE :letra"; $params['letra'] = $letra . '%'; }

    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM libros" . $where_sql);
    $stmt_count->execute($params);
    $total_paginas = ceil($stmt_count->fetchColumn() / $libros_por_pagina);

    $sql = "SELECT *, (SELECT COUNT(*) FROM ejemplares e WHERE e.id_libro = libros.id_libro AND e.estado = 'Disponible') as copias_disponibles 
            FROM libros $where_sql ORDER BY titulo ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) $stmt->bindValue(":$key", $val);
    $stmt->bindValue(':limit', $libros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
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
    <title>Catálogo Digital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 40px; }
        .alerta { padding: 15px; text-align: center; border-radius: 8px; margin: 20px auto; max-width: 800px; font-weight: bold; }
        .search-container { margin: 20px auto; display: flex; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-radius: 30px; overflow: hidden; background: white; max-width: 800px; }
        .search-container input { flex: 1; padding: 15px; border: none; outline: none; }
        .search-container button { padding: 15px 30px; background-color: #3498db; color: white; border: none; cursor: pointer; }
        .books-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 30px; margin-top: 40px; }
        .book-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .btn-prestamo { background-color: #2ecc71; color: white; padding: 10px; border-radius: 5px; border: none; width: 100%; cursor: pointer; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="alerta" style="<?php echo (strpos($_GET['msg'], 'exitosa') !== false) ? 'background:#d4edda; color:#155724;' : 'background:#f8d7da; color:#721c24;'; ?>">
            <?php 
                $msgs = ['prestamo_exitoso'=>'Préstamo realizado.','reserva_exitosa'=>'Reserva exitosa.', 'reserva_duplicada'=>'Ya tienes este libro reservado.'];
                echo $msgs[$_GET['msg']] ?? 'Error al procesar tu solicitud.'; 
            ?>
        </div>
    <?php endif; ?>

    <div style="text-align:center;">
        <h1>📖 Catálogo Digital</h1>
    </div>

    <form class="search-container" method="GET" action="catalogo.php">
        <input type="text" name="q" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
        <button type="submit">Buscar</button>
    </form>

    <div class="books-grid">
        <?php foreach ($libros as $libro): ?>
            <div class="book-card">
                <h3><?php echo htmlspecialchars($libro['titulo']); ?></h3>
                <p><?php echo htmlspecialchars($libro['autor']); ?></p>
                <?php if($libro['copias_disponibles'] > 0): ?>
                    <form action="procesar_prestamo.php" method="POST">
                        <input type="hidden" name="id_libro" value="<?php echo $libro['id_libro']; ?>">
                        <button type="submit" class="btn-prestamo">Solicitar Préstamo</button>
                    </form>
                <?php else: ?>
                    <form action="catalogo.php" method="POST">
                        <input type="hidden" name="accion" value="reservar">
                        <input type="hidden" name="id_libro" value="<?php echo $libro['id_libro']; ?>">
                        <button type="submit">Reservar</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
