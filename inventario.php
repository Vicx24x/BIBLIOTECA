<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] === 'Usuario') {
    header("Location: dashboard.php");
    exit();
}

$msg_tipo = '';
$msg_text = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar') {
    $isbn     = trim($_POST['isbn']);
    $titulo   = trim($_POST['titulo']);
    $autor    = trim($_POST['autor']);
    $anio     = (int)$_POST['anio_publicacion'];
    $editorial= trim($_POST['editorial']);
    $categoria= trim($_POST['categoria']);
    $cantidad_copias = (int)$_POST['cantidad_copias'];
    $portada_path = '';

    if (isset($_FILES['portada']) && $_FILES['portada']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['portada']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['portada']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg','jpeg','png','webp'])) {
            $nuevo_nombre = $isbn . '.' . $file_ext;
            if (!is_dir(__DIR__ . '/portadas')) mkdir(__DIR__ . '/portadas', 0777, true);
            $destino = __DIR__ . '/portadas/' . $nuevo_nombre;
            if (move_uploaded_file($file_tmp, $destino)) { $portada_path = $destino; }
            else { $msg_tipo = 'error'; $msg_text = 'Error al mover la imagen.'; }
        } else { $msg_tipo = 'error'; $msg_text = 'Formato de imagen no válido. Usa JPG, PNG o WEBP.'; }
    }

    if (empty($msg_tipo)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO libros (isbn, titulo, autor, anio_publicacion, editorial, categoria, portada) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$isbn,$titulo,$autor,$anio,$editorial,$categoria,$portada_path]);
            $id_libro_nuevo = $pdo->lastInsertId();
            if ($cantidad_copias > 0) {
                $stmt_ej = $pdo->prepare("INSERT INTO ejemplares (id_libro, codigo_activo, estado) VALUES (?, ?, 'Disponible')");
                for ($i = 1; $i <= $cantidad_copias; $i++) {
                    $stmt_ej->execute([$id_libro_nuevo, $isbn.'-'.sprintf('%03d',$i)]);
                }
            }
            $pdo->commit();
            $msg_tipo = 'success'; $msg_text = "Libro registrado con $cantidad_copias ejemplar(es) listo(s) para préstamo.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $msg_tipo = 'error';
            $msg_text = ($e->getCode() == 23000) ? 'El ISBN ya existe en el sistema.' : 'Error de BD: '.$e->getMessage();
        }
    }
}

$stmt_libros = $pdo->query("SELECT * FROM libros ORDER BY id_libro DESC LIMIT 10");
$libros = $stmt_libros->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'DM Sans','Segoe UI',sans-serif; background: #f5f3ef; margin: 0; color: #1a1a2e; }
        .page-wrap { max-width: 1300px; margin: 0 auto; padding: 36px 32px 60px; }

        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; flex-wrap: wrap; gap: 12px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda,#850021); text-decoration: none; font-weight: 600; font-size: 0.875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.2s; }
        .back-link:hover { background: var(--guinda,#850021); color: #fff; }
        .page-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda,#850021); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: 0.875rem; margin: 0; }

        /* Alert */
        .alert-box { display: flex; align-items: flex-start; gap: 10px; padding: 13px 18px; border-radius: 12px; font-size: 0.875rem; font-weight: 600; margin-bottom: 24px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .alert-box.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-box.error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Grid layout */
        .inv-grid { display: grid; grid-template-columns: 360px 1fr; gap: 28px; align-items: start; }
        @media (max-width: 900px) { .inv-grid { grid-template-columns: 1fr; } }

        /* Cards */
        .card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f3f4f6; }
        .card-header h3 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.05rem; color: #111827; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: var(--guinda,#850021); }
        .card-body { padding: 24px; }

        /* Form */
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #374151; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px 13px; border: 1.5px solid #e5e7eb; border-radius: 9px; font-size: 0.9rem; font-family: inherit; color: #111827; transition: border-color 0.2s; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: var(--guinda,#850021); box-shadow: 0 0 0 3px rgba(133,0,33,0.08); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .form-hint { font-size: 0.75rem; color: #9ca3af; margin-top: 4px; }

        .copies-field .form-control { border-color: #f59e0b; }
        .copies-field .form-label { color: #b45309; }
        .copies-field .form-control:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,0.15); }

        .btn-save { width: 100%; padding: 13px; background: linear-gradient(135deg,var(--guinda,#850021),#5a0016); color: #fff; border: none; border-radius: 10px; font-family: inherit; font-size: 0.95rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; transition: all 0.2s; box-shadow: 0 4px 14px rgba(133,0,33,0.28); }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(133,0,33,0.38); }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 11px 18px; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; background: #fafafa; border-bottom: 1px solid #f3f4f6; }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fdf8f0; }
        tbody td { padding: 12px 18px; font-size: 0.875rem; vertical-align: middle; }

        .isbn-tag { font-family: monospace; font-size: 0.78rem; background: #f3f4f6; color: #374151; padding: 3px 8px; border-radius: 5px; }
        .source-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .source-local { background: #d1fae5; color: #065f46; }
        .source-web   { background: #fef3c7; color: #78350f; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">
        <div class="topnav">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div>
                <h1 class="page-title"><i class="fas fa-boxes" style="font-size:1.4rem;"></i> Gestión de Inventario</h1>
                <p class="page-sub">Agrega y controla los títulos del acervo físico</p>
            </div>
            <div style="width:120px;"></div>
        </div>

        <?php if($msg_tipo): ?>
        <div class="alert-box <?php echo $msg_tipo; ?>">
            <i class="fas fa-<?php echo $msg_tipo==='success' ? 'check-circle' : 'times-circle'; ?>"></i>
            <span><?php echo htmlspecialchars($msg_text); ?></span>
        </div>
        <?php endif; ?>

        <div class="inv-grid">

            <!-- Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Agregar Nuevo Título</h3>
                </div>
                <div class="card-body">
                    <form action="inventario.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="agregar">
                        <div class="form-group">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" class="form-control" required placeholder="Ej. 9780132350884">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Título del Libro</label>
                            <input type="text" name="titulo" class="form-control" required placeholder="Ej. Cálculo Diferencial">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Autor</label>
                            <input type="text" name="autor" class="form-control" required placeholder="Ej. Stewart, James">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Año</label>
                                <input type="number" name="anio_publicacion" class="form-control" required placeholder="2024">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Categoría</label>
                                <input type="text" name="categoria" class="form-control" required placeholder="Matemáticas">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Editorial</label>
                            <input type="text" name="editorial" class="form-control" required placeholder="Ej. Cengage Learning">
                        </div>
                        <div class="form-group copies-field">
                            <label class="form-label"><i class="fas fa-layer-group"></i> Copias Físicas a Ingresar</label>
                            <input type="number" name="cantidad_copias" class="form-control" value="1" min="1" max="50" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Portada Personalizada (Opcional)</label>
                            <input type="file" name="portada" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-hint"><i class="fas fa-info-circle"></i> Si no subes imagen, el sistema buscará la portada en internet por ISBN.</div>
                        </div>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar en Inventario</button>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Últimos Títulos Registrados</h3>
                </div>
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
                        <?php foreach($libros as $l): ?>
                        <tr>
                            <td><span class="isbn-tag"><?php echo htmlspecialchars($l['isbn']); ?></span></td>
                            <td style="font-weight:700; color:#111827;"><?php echo htmlspecialchars($l['titulo']); ?></td>
                            <td style="color:#6b7280;"><?php echo htmlspecialchars($l['autor']); ?></td>
                            <td>
                                <?php if(!empty($l['portada'])): ?>
                                    <span class="source-badge source-local"><i class="fas fa-check"></i> Local</span>
                                <?php else: ?>
                                    <span class="source-badge source-web"><i class="fas fa-globe"></i> Web</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($libros)): ?>
                        <tr><td colspan="4" style="text-align:center; color:#9ca3af; padding:30px;">No hay libros registrados aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

