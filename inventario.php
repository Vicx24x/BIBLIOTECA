<?php
session_start();
require_once 'config/db.php';

// --------------------------------------------------------------------
// SEGURIDAD DE SESIÓN Y ACCESO
// Whitelist de roles: solo Administrador o Bibliotecario pueden entrar.
// Si no hay sesión o el rol no está autorizado, se redirige a index.php
// --------------------------------------------------------------------
$rolesPermitidos = ['Administrador', 'Bibliotecario'];
if (!isset($_SESSION['id_usuario']) || !in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
    header("Location: index.php");
    exit();
}

// Token CSRF para el formulario de alta (se valida en guardar_libro.php)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --------------------------------------------------------------------
// NOTIFICACIÓN VÍA PARÁMETROS DE URL (patrón Post/Redirect/Get)
// guardar_libro.php redirige aquí con ?update=exito o ?error=1
// --------------------------------------------------------------------
$msg_tipo = '';
$msg_text = '';
if (isset($_GET['update']) && $_GET['update'] === 'exito') {
    $msg_tipo = 'success';
    $msg_text = isset($_GET['msg'])
        ? htmlspecialchars(urldecode($_GET['msg']), ENT_QUOTES, 'UTF-8')
        : 'Operación realizada con éxito.';
} elseif (isset($_GET['error']) && $_GET['error'] === '1') {
    $msg_tipo = 'error';
    $msg_text = isset($_GET['msg'])
        ? htmlspecialchars(urldecode($_GET['msg']), ENT_QUOTES, 'UTF-8')
        : 'Ocurrió un error. Intenta de nuevo.';
}

// --------------------------------------------------------------------
// LISTADO DE ÚLTIMOS TÍTULOS (sentencia preparada PDO)
// --------------------------------------------------------------------
$stmt_libros = $pdo->prepare("SELECT * FROM libros ORDER BY id_libro DESC LIMIT 10");
$stmt_libros->execute();
$libros = $stmt_libros->fetchAll();

$anioActual = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --guinda: #850021;
            --guinda-dark: #5a0016;
            --dorado: #c9a84c;
            --dorado-dark: #a8893c;
        }
        body { font-family: 'DM Sans','Segoe UI',sans-serif; background: #f5f3ef; margin: 0; color: #1a1a2e; }
        .page-wrap { max-width: 1300px; margin: 0 auto; padding: 36px 32px 60px; }

        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; flex-wrap: wrap; gap: 12px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda); text-decoration: none; font-weight: 600; font-size: 0.875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.2s; }
        .back-link:hover { background: var(--guinda); color: #fff; }
        .page-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: 0.875rem; margin: 0; }

        /* Toast de notificación (esquina superior derecha) */
        .toast-alert {
            position: fixed; top: 24px; right: 24px; max-width: 380px; z-index: 1000;
            display: flex; align-items: flex-start; gap: 10px;
            padding: 14px 18px; border-radius: 12px; font-size: 0.875rem; font-weight: 600;
            box-shadow: 0 10px 28px rgba(0,0,0,0.16);
            animation: slideInToast 0.35s ease;
        }
        @keyframes slideInToast { from{opacity:0; transform:translateX(30px);} to{opacity:1; transform:translateX(0);} }
        .toast-alert.success { background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; border-left:4px solid var(--dorado); }
        .toast-alert.error   { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; border-left:4px solid var(--guinda); }
        .toast-close { margin-left: auto; cursor: pointer; opacity: 0.55; background:none; border:none; font-size: 0.9rem; color: inherit; }
        .toast-close:hover { opacity: 1; }

        /* Grid layout */
        .inv-grid { display: grid; grid-template-columns: 360px 1fr; gap: 28px; align-items: start; }
        @media (max-width: 900px) { .inv-grid { grid-template-columns: 1fr; } }

        /* Cards */
        .card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f3f4f6; }
        .card-header h3 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.05rem; color: #111827; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: var(--guinda); }
        .card-body { padding: 24px; }

        /* Form */
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #374151; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px 13px; border: 1.5px solid #e5e7eb; border-radius: 9px; font-size: 0.9rem; font-family: inherit; color: #111827; transition: border-color 0.2s; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: var(--guinda); box-shadow: 0 0 0 3px rgba(133,0,33,0.08); }
        .form-control:invalid:not(:placeholder-shown) { border-color: #dc2626; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .form-hint { font-size: 0.75rem; color: #9ca3af; margin-top: 4px; }

        .copies-field .form-control { border-color: var(--dorado); }
        .copies-field .form-label { color: var(--dorado-dark); }
        .copies-field .form-control:focus { border-color: var(--dorado); box-shadow: 0 0 0 3px rgba(201,168,76,0.2); }

        .btn-save { width: 100%; padding: 13px; background: linear-gradient(135deg,var(--guinda),var(--guinda-dark)); color: #fff; border: none; border-radius: 10px; font-family: inherit; font-size: 0.95rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 6px; transition: all 0.2s; box-shadow: 0 4px 14px rgba(133,0,33,0.28); }
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
        .source-web   { background: #faf3e0; color: #7a5f25; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if ($msg_tipo): ?>
    <div class="toast-alert <?php echo $msg_tipo; ?>" id="toastAlert">
        <i class="fas fa-<?php echo $msg_tipo === 'success' ? 'check-circle' : 'times-circle'; ?>"></i>
        <span><?php echo $msg_text; /* ya se escapó arriba con htmlspecialchars */ ?></span>
        <button type="button" class="toast-close" onclick="document.getElementById('toastAlert').remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <script>
        setTimeout(function () {
            var t = document.getElementById('toastAlert');
            if (t) t.remove();
        }, 6000);
    </script>
    <?php endif; ?>

    <div class="page-wrap">
        <div class="topnav">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div>
                <h1 class="page-title"><i class="fas fa-boxes" style="font-size:1.4rem;"></i> Gestión de Inventario</h1>
                <p class="page-sub">Agrega y controla los títulos del acervo físico</p>
            </div>
            <div style="width:120px;"></div>
        </div>

        <div class="inv-grid">

            <!-- Formulario -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Agregar Nuevo Título</h3>
                </div>
                <div class="card-body">
                    <form action="guardar_libro.php" method="POST" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="accion" value="agregar">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-group">
                            <label class="form-label">ISBN</label>
                            <input type="text" name="isbn" class="form-control" required
                                   pattern="^[0-9\-]{10,17}$" maxlength="17"
                                   title="10 o 13 dígitos (se permiten guiones)"
                                   placeholder="Ej. 9780132350884">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Título del Libro</label>
                            <input type="text" name="titulo" class="form-control" required
                                   maxlength="255" placeholder="Ej. Cálculo Diferencial">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Autor</label>
                            <input type="text" name="autor" class="form-control" required
                                   maxlength="150" placeholder="Ej. Stewart, James">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Año</label>
                                <input type="number" name="anio_publicacion" class="form-control" required
                                       min="1450" max="<?php echo $anioActual + 1; ?>" placeholder="2024">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Categoría</label>
                                <input type="text" name="categoria" class="form-control" required
                                       maxlength="100" placeholder="Matemáticas">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Editorial</label>
                            <input type="text" name="editorial" class="form-control" required
                                   maxlength="150" placeholder="Ej. Cengage Learning">
                        </div>
                        <div class="form-group copies-field">
                            <label class="form-label"><i class="fas fa-layer-group"></i> Copias Físicas a Ingresar</label>
                            <input type="number" name="cantidad_copias" class="form-control" value="1" min="1" max="50" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Portada Personalizada (Opcional)</label>
                            <input type="file" name="portada" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-hint"><i class="fas fa-info-circle"></i> JPG, PNG o WEBP, máx. 3MB. Si no subes imagen, el sistema buscará la portada en internet por ISBN.</div>
                        </div>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar en Inventario</button>
                    </form>
                </div>
            </div>

            <!-- Tabla -->
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
                        <?php foreach ($libros as $l): ?>
                        <tr>
                            <td><span class="isbn-tag"><?php echo htmlspecialchars($l['isbn']); ?></span></td>
                            <td style="font-weight:700; color:#111827;"><?php echo htmlspecialchars($l['titulo']); ?></td>
                            <td style="color:#6b7280;"><?php echo htmlspecialchars($l['autor']); ?></td>
                            <td>
                                <?php if (!empty($l['portada'])): ?>
                                    <span class="source-badge source-local"><i class="fas fa-check"></i> Local</span>
                                <?php else: ?>
                                    <span class="source-badge source-web"><i class="fas fa-globe"></i> Web</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($libros)): ?>
                        <tr><td colspan="4" style="text-align:center; color:#9ca3af; padding:30px;">No hay libros registrados aún.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
