<?php
// mantenimiento.php
session_start();
require_once 'config/db.php';

// Seguridad: Solo Bibliotecarios o Administradores
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] === 'Usuario') {
    die("Acceso denegado.");
}

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_ejemplar'])) {
    $id_ejemplar = (int)$_POST['id_ejemplar'];
    $nuevo_estado = $_POST['nuevo_estado'];

    try {
        $sql = "UPDATE ejemplares SET estado = :estado WHERE id_ejemplar = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['estado' => $nuevo_estado, 'id' => $id_ejemplar]);
        $mensaje = "<div style='background:#d4edda; color:#155724; padding:15px; border-radius:5px; margin-bottom:20px; font-weight:bold;'>Estado del ejemplar actualizado a: $nuevo_estado</div>";
    } catch (PDOException $e) {
        $mensaje = "Error al actualizar: " . $e->getMessage();
    }
}

// Consultar todos los ejemplares físicos y su estado actual
$sql = "SELECT e.id_ejemplar, e.codigo_activo, l.titulo, e.estado 
        FROM ejemplares e 
        INNER JOIN libros l ON e.id_libro = l.id_libro 
        ORDER BY l.titulo ASC";
$ejemplares = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mantenimiento de Material - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; margin-top: 20px;}
        th, td { padding: 12px 15px; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #7f8c8d; text-transform: uppercase; font-size: 0.85rem; }
        select { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .btn-update { background: #e67e22; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight:bold;}
        .btn-update:hover { background: #d35400; }
        
        /* Colores para los estados */
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; }
        .st-Disponible { background: #d4edda; color: #155724; }
        .st-Prestado { background: #cce5ff; color: #004085; }
        .st-EnMantenimiento { background: #fff3cd; color: #856404; }
        .st-Perdido { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <a href="dashboard.php" style="text-decoration: none; color: #7f8c8d; font-weight: bold;"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
    <h1 style="color: #2c3e50;"><i class="fas fa-toolbox"></i> Estado Físico del Material</h1>
    <p style="color: #7f8c8d;">Identifica materiales dañados, extraviados o mándalos a reparación.</p>

    <?php echo $mensaje; ?>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Código (Etiqueta)</th>
                    <th>Título del Libro</th>
                    <th>Estado Actual</th>
                    <th>Cambiar Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($ejemplares as $ej): ?>
                <tr>
                    <td style="font-weight: bold;"><?php echo htmlspecialchars($ej['codigo_activo']); ?></td>
                    <td><?php echo htmlspecialchars($ej['titulo']); ?></td>
                    <td>
                        <?php 
                        // Limpiar el estado para la clase CSS (quitar espacios)
                        $clase_estado = "st-" . str_replace(' ', '', $ej['estado']); 
                        ?>
                        <span class="badge <?php echo $clase_estado; ?>"><?php echo $ej['estado']; ?></span>
                    </td>
                    <td>
                        <form method="POST" action="mantenimiento.php" style="display:flex; gap:10px;">
                            <input type="hidden" name="id_ejemplar" value="<?php echo $ej['id_ejemplar']; ?>">
                            <select name="nuevo_estado">
                                <option value="Disponible" <?php echo ($ej['estado'] == 'Disponible') ? 'selected' : ''; ?>>Disponible</option>
                                <option value="En Mantenimiento" <?php echo ($ej['estado'] == 'En Mantenimiento') ? 'selected' : ''; ?>>Dañado / En Mantenimiento</option>
                                <option value="Perdido" <?php echo ($ej['estado'] == 'Perdido') ? 'selected' : ''; ?>>Extraviado / Baja</option>
                            </select>
                            <button type="submit" class="btn-update">Actualizar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>