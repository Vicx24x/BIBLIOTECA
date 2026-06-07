<?php
// inventario.php
session_start();
require_once 'config/db.php';

// 1. CORRECCIÓN DE SEGURIDAD (RF11): Solo Admin y Bibliotecarios
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] === 'Usuario') {
    header("Location: dashboard.php");
    exit();
}

$mensaje = '';

// Procesar el formulario cuando se agrega un libro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $isbn = trim($_POST['isbn']);
    $titulo = trim($_POST['titulo']);
    $autor = trim($_POST['autor']);
    $anio = (int)$_POST['anio_publicacion'];
    $editorial = trim($_POST['editorial']);
    $categoria = trim($_POST['categoria']);
    $cantidad_copias = (int)$_POST['cantidad_copias']; // NUEVO: RF9
    $portada_path = null;

    // Lógica para subir la imagen de portada
    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['portada']['tmp_name'];
        $file_name = $_FILES['portada']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $extensiones_validas = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $extensiones_validas)) {
            $nuevo_nombre = $isbn . '.' . $file_ext;
            if (!is_dir(__DIR__ . '/portadas')) {
                mkdir(__DIR__ . '/portadas', 0777, true);
            }
            $destino = __DIR__ . '/portadas/' . $nuevo_nombre;

            if (move_uploaded_file($file_tmp, $destino)) {
                $portada_path = $destino; 
            } else {
                $mensaje = "<div class='alert alert-error'>Error al mover la imagen a la carpeta portadas.</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-error'>Formato de imagen no válido. Usa JPG, PNG o WEBP.</div>";
        }
    }

    // Insertar en la base de datos (Catálogo + Inventario Físico)
    if (empty($mensaje)) {
        try {
            // Iniciamos transacción para que no se guarde el libro si fallan las copias
            $pdo->beginTransaction();

            // A) Insertar el Título en el Catálogo
            $stmt = $pdo->prepare("INSERT INTO libros (isbn, titulo, autor, anio_publicacion, editorial, categoria, portada) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$isbn, $titulo, $autor, $anio, $editorial, $categoria, $portada_path]);
            
            // B) Obtener el ID del libro recién guardado
            $id_libro_nuevo = $pdo->lastInsertId();

            // C) CUMPLIMIENTO RF9: Generar los ejemplares físicos
            if ($cantidad_copias > 0) {
                $stmt_ejemplar = $pdo->prepare("INSERT INTO ejemplares (id_libro, codigo_activo, estado) VALUES (?, ?, 'Disponible')");
                
                for ($i = 1; $i <= $cantidad_copias; $i++) {
                    // Crea un código único de inventario. Ej: 9780132350884-001
                    $codigo_activo = $isbn . '-' . sprintf('%03d', $i);
                    $stmt_ejemplar->execute([$id_libro_nuevo, $codigo_activo]);
                }
            }

            $pdo->commit();
            $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Libro registrado con $cantidad_copias ejemplares listos para préstamo.</div>";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $mensaje = "<div class='alert alert-error'>El ISBN ya existe en el sistema.</div>";
            } else {
                $mensaje = "<div class='alert alert-error'>Error de BD: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Consultar los últimos libros agregados
$stmt_libros = $pdo->query("SELECT * FROM libros ORDER BY id_libro DESC LIMIT 10");
$libros = $stmt_libros->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #2c3e50; margin: 0; }
        .btn-volver { text-decoration: none; color: #7f8c8d; font-weight: bold; }
        
        .container { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card h3 { margin-top: 0; color: #34495e; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; color: #7f8c8d; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .form-group input[type="file"] { padding: 5px; background: #f9f9f9; }
        
        .btn-submit { background-color: #3498db; color: white; padding: 12px; border: none; border-radius: 5px; width: 100%; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background-color: #2980b9; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px; border-bottom: 1px solid #ecf0f1; }
        th { background-color: #f8f9fa; color: #7f8c8d; }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; text-align: center; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include 'header.php'; ?>

    <div class="header">
        <a href="dashboard.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        <h1>📦 Gestión de Inventario</h1>
        <div></div> 
    </div>

    <?php echo $mensaje; ?>

    <div class="container">
        <div class="card">
            <h3><i class="fas fa-plus-circle"></i> Agregar Nuevo Título</h3>
            <form action="inventario.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="agregar">
                
                <div class="form-group">
                    <label>ISBN</label>
                    <input type="text" name="isbn" required placeholder="Ej. 9780132350884">
                </div>
                <div class="form-group">
                    <label>Título del Libro</label>
                    <input type="text" name="titulo" required>
                </div>
                <div class="form-group">
                    <label>Autor</label>
                    <input type="text" name="autor" required>
                </div>
                <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <label>Año</label>
                        <input type="number" name="anio_publicacion" required>
                    </div>
                    <div>
                        <label>Categoría</label>
                        <input type="text" name="categoria" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Editorial</label>
                    <input type="text" name="editorial" required>
                </div>
                
                <div class="form-group">
                    <label style="color:#e67e22;"><i class="fas fa-layer-group"></i> Copias Físicas a Ingresar</label>
                    <input type="number" name="cantidad_copias" value="1" min="1" max="50" required style="border: 2px solid #e67e22;">
                </div>

                <div class="form-group">
                    <label>Portada Personalizada (Opcional)</label>
                    <input type="file" name="portada" accept=".jpg, .jpeg, .png, .webp">
                    <small style="color: #95a5a6;">Si dejas esto vacío, el sistema buscará la portada en internet.</small>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Guardar Inventario</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fas fa-history"></i> Últimos Libros Registrados</h3>
            <table>
                <thead>
                    <tr>
                        <th>ISBN</th>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>Portada</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($libros as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($l['isbn']); ?></td>
                            <td><strong><?php echo htmlspecialchars($l['titulo']); ?></strong></td>
                            <td><?php echo htmlspecialchars($l['autor']); ?></td>
                            <td>
                                <?php if (!empty($l['portada'])): ?>
                                    <span style="color: green; font-size: 0.8rem;"><i class="fas fa-check"></i> Local</span>
                                <?php else: ?>
                                    <span style="color: #f39c12; font-size: 0.8rem;"><i class="fas fa-globe"></i> Web</span>
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