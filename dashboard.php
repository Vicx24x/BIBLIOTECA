<?php
// dashboard.php
session_start();
require_once 'config/db.php';

// Validación de seguridad
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

$rol_usuario = $_SESSION['rol'] ?? 'Usuario';
$nombre_usuario = $_SESSION['nombre'] ?? 'Invitado';

try {
    // 1. Calcular Libros Físicos Disponibles
    $stmt = $pdo->query("SELECT COUNT(*) FROM ejemplares WHERE estado = 'Disponible'");
    $total_disponibles = $stmt->fetchColumn();

    // 2. Calcular Préstamos Activos
    $stmt = $pdo->query("SELECT COUNT(*) FROM prestamos WHERE estado = 'Activo'");
    $total_prestamos = $stmt->fetchColumn();

    // 3. Calcular Usuarios Registrados
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado = 'Activo'");
    $total_usuarios = $stmt->fetchColumn();

    // 4. Obtener Disponibilidad en Tiempo Real
    $sql_disponibilidad = "SELECT l.titulo, l.autor, COUNT(e.id_ejemplar) as copias_disponibles 
                           FROM libros l
                           LEFT JOIN ejemplares e ON l.id_libro = e.id_libro AND e.estado = 'Disponible'
                           GROUP BY l.id_libro
                           ORDER BY l.titulo ASC LIMIT 5";
    $tabla_disponibilidad = $pdo->query($sql_disponibilidad)->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar el panel: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BibliotecaMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #850021;
            --accent: #ffffff;
            --bg-body: #f4f7f6;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
            --sidebar-width: 250px;
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); color: #333; margin: 0; }
        
        /* Estructura */
        .wrapper { display: flex; min-height: calc(100vh - 120px); }
        
        /* Barra Lateral */
        .sidebar { width: var(--sidebar-width); background-color: var(--primary-dark); color: white; padding-top: 20px; flex-shrink: 0; }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-size: 1.5rem; }
        .sidebar a { text-decoration: none; color: #ecf0f1; padding: 15px 25px; display: block; transition: 0.3s; border-left: 4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background-color: #850021; border-left: 4px solid var(--accent); color: var(--accent); font-weight: bold; }
        .sidebar a i { margin-right: 10px; width: 20px; text-align: center; }
        .logout-btn { margin-top: auto; background-color: #e74c3c; text-align: center; font-weight: bold; }
        .logout-btn:hover { background-color: #c0392b; }

        /* Contenido Principal */
        .main-content { flex: 1; padding: 40px; }
        .header-main { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; background: var(--white); padding: 20px 30px; border-radius: 12px; box-shadow: var(--shadow); }
        .role-badge { background-color: var(--accent); color: white; padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; }

        /* Tarjetas */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .card-stat { background: var(--white); padding: 25px; border-radius: 12px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; }
        .card-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 1.8rem; color: white; }
        .card-info h3 { margin: 0; color: #7f8c8d; font-size: 0.95rem; text-transform: uppercase; }
        .card-info .number { margin: 5px 0 0 0; font-size: 2rem; font-weight: bold; color: var(--primary-dark); }

        /* Tabla */
        .table-card { background: var(--white); padding: 25px; border-radius: 12px; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #7f8c8d; text-transform: uppercase; font-size: 0.85rem; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="wrapper">
        <div class="sidebar">
            <h2><i class="fas fa-book-reader"></i> BiblioMPS</h2>
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Inicio</a>
            <a href="catalogo.php"><i class="fas fa-search"></i> Catálogo Digital</a>
            
            <?php if($rol_usuario === 'Administrador' || $rol_usuario === 'Bibliotecario'): ?>
                <a href="prestamos.php"><i class="fas fa-exchange-alt"></i> Operaciones</a>
                <a href="inventario.php"><i class="fas fa-boxes"></i> Inventario</a>
            <?php endif; ?>

            <?php if($rol_usuario === 'Administrador'): ?>
                <a href="usuarios.php"><i class="fas fa-users"></i> Usuarios</a>
                <a href="reportes.php"><i class="fas fa-chart-pie"></i> Reportes</a>
            <?php endif; ?>

            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a>
        </div>

        <div class="main-content">
            <div class="header-main">
                <div>
                    <h1>Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?> 👋</h1>
                    <p style="color: #7f8c8d; margin-top: 5px;">Panel de control principal del sistema.</p>
                </div>
                <span class="role-badge"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($rol_usuario); ?></span>
            </div>

            <div class="cards-grid">
                <div class="card-stat">
                    <div class="card-icon" style="background: linear-gradient(135deg, #2ecc71, #27ae60);"><i class="fas fa-book"></i></div>
                    <div class="card-info">
                        <h3>Libros Disponibles</h3>
                        <div class="number"><?php echo $total_disponibles; ?></div>
                    </div>
                </div>
                <div class="card-stat">
                    <div class="card-icon" style="background: linear-gradient(135deg, #f1c40f, #f39c12);"><i class="fas fa-hand-holding-book"></i></div>
                    <div class="card-info">
                        <h3>Préstamos Activos</h3>
                        <div class="number"><?php echo $total_prestamos; ?></div>
                    </div>
                </div>
                <?php if($rol_usuario === 'Administrador'): ?>
                <div class="card-stat">
                    <div class="card-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);"><i class="fas fa-users"></i></div>
                    <div class="card-info">
                        <h3>Usuarios Registrados</h3>
                        <div class="number"><?php echo $total_usuarios; ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-card">
                <h2><i class="fas fa-clock"></i> Disponibilidad en Tiempo Real</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Título del Libro</th>
                            <th>Autor</th>
                            <th>Estado en Estantería</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tabla_disponibilidad as $fila): ?>
                        <tr>
                            <td style="font-weight: bold;"><?php echo htmlspecialchars($fila['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($fila['autor']); ?></td>
                            <td>
                                <?php if($fila['copias_disponibles'] > 0): ?>
                                    <span style="color: green; font-weight: bold;"><?php echo $fila['copias_disponibles']; ?> copia(s) libre(s)</span>
                                <?php else: ?>
                                    <span style="color: red; font-weight: bold;">Agotado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>