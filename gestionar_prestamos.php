<?php
// =============================================================================
// gestionar_prestamos.php  —  CONTROL DE PRÉSTAMOS ACTIVOS (PANEL BIBLIOTECARIO)
// =============================================================================
// Correcciones aplicadas:
//   [SEC-01] Redirección a index.php (archivo existente), no a login.php.
//   [SEC-02] Whitelist de roles: Administrador y Bibliotecario; Usuarios bloqueados.
//   [SQL-01] Campos corregidos: u.nombre (no u.usuario) / u.id_usuario (no u.id).
//   [SEC-03] CSRF token incluido en el formulario de devolución.
//   [LOG-01] PDOException registrada en log; mensaje genérico al usuario.
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'Csrf helper.php';   // genera/recupera $_SESSION['csrf_token']

// ── 1. Autenticación [SEC-01] ────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');   // ← archivo que sí existe en el proyecto
    exit();
}

// ── 2. Autorización con whitelist de roles [SEC-02] ──────────────────────────
// Se declara la lista de roles permitidos; cualquier rol fuera de ella queda
// bloqueado aunque en el futuro se agreguen nuevos roles al sistema.
$rolesPermitidos = ['Administrador', 'Bibliotecario'];

if (!in_array($_SESSION['rol'] ?? '', $rolesPermitidos, true)) {
    header('Location: dashboard.php');
    exit();
}

// ── 3. Carga de préstamos activos [SQL-01] ───────────────────────────────────
$prestamos_activos = [];

try {
    // Campos corregidos: u.nombre y u.id_usuario (en lugar de u.usuario y u.id)
    $sql = "SELECT
                p.id_prestamo,
                p.fecha_prestamo,
                p.fecha_devolucion_esperada,
                u.nombre          AS nombre_alumno,
                l.titulo,
                l.autor,
                e.id_ejemplar
            FROM prestamos p
            INNER JOIN usuarios   u ON p.id_usuario  = u.id_usuario
            INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
            INNER JOIN libros     l ON e.id_libro    = l.id_libro
            WHERE p.estado = 'Activo'
            ORDER BY p.fecha_devolucion_esperada ASC";

    $stmt = $pdo->query($sql);
    $prestamos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // [LOG-01] El detalle del error nunca llega al navegador.
    error_log('[BiblioMPS][gestionar_prestamos] ' . $e->getMessage());
    $prestamos_activos = [];
    $error_carga = true;
}

// ── 4. Token CSRF para el formulario de devolución [SEC-03] ──────────────────
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Préstamos — BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --guinda:      #850021;
            --guinda-dark: #5a0016;
            --dorado:      #c9a84c;
            --dorado-dark: #a8893c;
        }
        body { font-family: 'DM Sans','Segoe UI',sans-serif; background: #f5f3ef; margin: 0; color: #1a1a2e; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 36px 32px 60px; }

        /* ── Navegación superior ── */
        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; flex-wrap: wrap; gap: 12px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda); text-decoration: none; font-weight: 600; font-size: .875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); transition: all .2s; }
        .back-link:hover { background: var(--guinda); color: #fff; }
        .page-title { font-family: 'Playfair Display',Georgia,serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: .875rem; margin: 0; }

        /* ── Card contenedora ── */
        .table-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,.06); overflow: hidden; }
        .table-header { padding: 20px 28px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px; }
        .table-header h2 { font-family: 'Playfair Display',Georgia,serif; font-size: 1.1rem; color: #111827; margin: 0; }
        .table-header h2 i { color: var(--guinda); }

        /* ── Tabla ── */
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 11px 20px; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; background: #fafafa; border-bottom: 1px solid #f3f4f6; }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fdf8f0; }
        tbody td { padding: 13px 20px; font-size: .875rem; vertical-align: middle; }

        /* ── Badges de fecha ── */
        .fecha-ok      { color: #065f46; font-weight: 600; }
        .fecha-vencida { color: #991b1b; font-weight: 700; }
        .badge-vencido { display: inline-block; background: #fee2e2; color: #991b1b; font-size: .7rem; font-weight: 700; padding: 2px 8px; border-radius: 8px; margin-left: 6px; }

        /* ── Alumno cell ── */
        .user-cell { display: flex; align-items: center; gap: 8px; }
        .avatar-sm { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, var(--guinda), var(--guinda-dark)); display: flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem; font-weight: 700; flex-shrink: 0; }
        .user-name { font-weight: 600; color: #111827; }

        /* ── Botón devolución ── */
        .btn-devolucion { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, var(--dorado), var(--dorado-dark)); color: #fff; border: none; padding: 9px 16px; border-radius: 9px; font-family: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; transition: all .2s; box-shadow: 0 3px 10px rgba(201,168,76,.3); white-space: nowrap; }
        .btn-devolucion:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(201,168,76,.4); }

        /* ── Estado vacío ── */
        .empty-state { text-align: center; padding: 60px 40px; color: #6b7280; }
        .empty-state i { font-size: 3rem; color: #c9a84c; margin-bottom: 16px; display: block; }
        .empty-state h3 { color: #374151; margin: 0 0 8px; }

        /* ── Alerta de error de carga ── */
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; font-weight: 600; font-size: .875rem; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">

        <!-- Navegación superior -->
        <div class="topnav">
            <div>
                <a href="dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
            <div>
                <h1 class="page-title"><i class="fas fa-tasks" style="font-size:1.5rem;"></i> Control de Préstamos</h1>
                <p class="page-sub">Registra devoluciones físicas de ejemplares activos.</p>
            </div>
        </div>

        <!-- Alerta si falló la carga de datos -->
        <?php if (!empty($error_carga)): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            No se pudieron cargar los préstamos. Por favor, recarga la página o contacta al administrador.
        </div>
        <?php endif; ?>

        <!-- Tabla de préstamos -->
        <div class="table-card">
            <div class="table-header">
                <h2><i class="fas fa-book-open"></i> Préstamos activos</h2>
            </div>

            <?php if (!empty($prestamos_activos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Alumno / Usuario</th>
                        <th>Libro</th>
                        <th>Fecha salida</th>
                        <th>Fecha límite</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestamos_activos as $p):
                        $es_vencido  = (date('Y-m-d') > $p['fecha_devolucion_esperada']);
                        $inicial     = mb_strtoupper(mb_substr($p['nombre_alumno'], 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                    <tr>
                        <td><strong>#<?= (int) $p['id_prestamo'] ?></strong></td>

                        <td>
                            <div class="user-cell">
                                <div class="avatar-sm"><?= htmlspecialchars($inicial) ?></div>
                                <span class="user-name"><?= htmlspecialchars($p['nombre_alumno']) ?></span>
                            </div>
                        </td>

                        <td>
                            <strong><?= htmlspecialchars($p['titulo']) ?></strong><br>
                            <small style="color:#9ca3af;">
                                <?= htmlspecialchars($p['autor']) ?> &nbsp;·&nbsp;
                                ID ejemplar: <?= (int) $p['id_ejemplar'] ?>
                            </small>
                        </td>

                        <td><?= date('d/m/Y', strtotime($p['fecha_prestamo'])) ?></td>

                        <td class="<?= $es_vencido ? 'fecha-vencida' : 'fecha-ok' ?>">
                            <?= date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) ?>
                            <?php if ($es_vencido): ?>
                                <span class="badge-vencido">VENCIDO</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <!-- [SEC-03] CSRF en cada formulario de devolución -->
                            <form action="procesar_devolucion.php" method="POST"
                                  onsubmit="return confirm('¿Confirmas que el alumno entregó el ejemplar en buen estado?');">
                                <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="id_prestamo" value="<?= (int) $p['id_prestamo'] ?>">
                                <input type="hidden" name="id_ejemplar" value="<?= (int) $p['id_ejemplar'] ?>">
                                <button type="submit" class="btn-devolucion">
                                    <i class="fas fa-clipboard-check"></i> Recibir libro
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-double"></i>
                <h3>¡Todo al corriente!</h3>
                <p>No hay préstamos activos pendientes de devolución en este momento.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
