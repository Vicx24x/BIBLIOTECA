<?php
// catalogo.php
session_start();
require_once 'config/db.php';

$mensaje = '';

// Procesar Reserva (Backend integrado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'reservar') {
    if (!isset($_SESSION['id_usuario'])) {
        $mensaje = "<div style='background:#f8d7da; color:#721c24; padding:10px; text-align:center; margin-bottom:20px;'>Debes iniciar sesión para reservar.</div>";
    } else {
        $id_libro = (int)$_POST['id_libro'];
        $id_usuario = $_SESSION['id_usuario'];
        
        try {
            // Verificar si ya lo tiene reservado
            $stmt_check = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_usuario = ? AND id_libro = ? AND estado = 'Pendiente'");
            $stmt_check->execute([$id_usuario, $id_libro]);
            if ($stmt_check->fetch()) {
                $mensaje = "<div style='background:#fff3cd; color:#856404; padding:10px; text-align:center; margin-bottom:20px;'>Ya estás en la lista de espera para este libro.</div>";
            } else {
                $stmt_res = $pdo->prepare("INSERT INTO reservas (id_usuario, id_libro) VALUES (?, ?)");
                $stmt_res->execute([$id_usuario, $id_libro]);
                $mensaje = "<div style='background:#d4edda; color:#155724; padding:10px; text-align:center; margin-bottom:20px;'>¡Libro reservado con éxito! Te notificaremos cuando esté disponible.</div>";
            }
        } catch (PDOException $e) {
            $mensaje = "<div style='background:#f8d7da; color:#721c24; padding:10px; text-align:center; margin-bottom:20px;'>Error al reservar.</div>";
        }
    }
}

// Consultar categorías únicas para el filtro
$categorias_stmt = $pdo->query("SELECT DISTINCT categoria FROM libros WHERE categoria != ''");
$categorias_disponibles = $categorias_stmt->fetchAll(PDO::FETCH_COLUMN);

// Consultar libros con búsqueda y filtros
try {
    $busqueda = $_GET['q'] ?? ''; 
    $filtro_cat = $_GET['categoria'] ?? '';

    $sql = "SELECT l.*, 
            (SELECT COUNT(*) FROM ejemplares e WHERE e.id_libro = l.id_libro AND e.estado = 'Disponible') as copias_disponibles 
            FROM libros l WHERE 1=1";
    $params = [];

    if (!empty($busqueda)) {
        $sql .= " AND (l.titulo LIKE :q OR l.autor LIKE :q)";
        $params['q'] = "%$busqueda%";
    }
    if (!empty($filtro_cat)) {
        $sql .= " AND l.categoria = :cat";
        $params['cat'] = $filtro_cat;
    }
    
    $sql .= " ORDER BY l.titulo ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $libros = $stmt->fetchAll();
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

    <?php echo $mensaje; ?>

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
</body>
</html>