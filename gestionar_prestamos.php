<?php
// =============================================================================
// gestionar_prestamos.php  —  CONTROL DE PRÉSTAMOS (REQ-1, REQ-2, REQ-3)
// =============================================================================
// Cambios vs versión anterior:
//   [REQ-1]  Mensajes de feedback para devolución exitosa/extemporánea.
//   [REQ-2]  Badge visual de días de atraso en tabla.
//   [REQ-3]  Botón "Editar Fecha" + modal para modificar fecha_devolucion_esperada.
//   [FIX]    require correcto: 'csrf_helper.php' (minúsculas) no 'Csrf helper.php'.
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit();
}

// ── 2. Autorización ──────────────────────────────────────────────────────────
$rolesPermitidos = ['Administrador', 'Bibliotecario'];
$rol_sesion      = $_SESSION['rol'] ?? '';

if (!in_array($rol_sesion, $rolesPermitidos, true)) {
    header('Location: dashboard.php');
    exit();
}

// ── 3. Carga de préstamos activos ─────────────────────────────────────────────
$prestamos_activos = [];

try {
    $sql = "SELECT
                p.id_prestamo,
                p.fecha_prestamo,
                p.fecha_devolucion_esperada,
                p.renovaciones_realizadas,
                u.nombre     AS nombre_alumno,
                l.titulo,
                l.autor,
                e.id_ejemplar,
                DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) AS dias_atraso
            FROM prestamos p
            INNER JOIN usuarios   u ON p.id_usuario  = u.id_usuario
            INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
            INNER JOIN libros     l ON e.id_libro    = l.id_libro
            WHERE p.estado = 'Activo'
            ORDER BY p.fecha_devolucion_esperada ASC";

    $stmt             = $pdo->query($sql);
    $prestamos_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[BiblioMPS][gestionar_prestamos] ' . $e->getMessage());
    $prestamos_activos = [];
    $error_carga = true;
}

// ── 4. Token CSRF ─────────────────────────────────────────────────────────────
$csrf = $_SESSION['csrf_token'];

// ── 5. Mensajes de feedback desde query string ────────────────────────────────
$msg       = $_GET['msg']        ?? '';
$dias_url  = (int)($_GET['dias'] ?? 0);
$fecha_url = htmlspecialchars($_GET['nueva_fecha'] ?? '');
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

        /* ── Navegación ── */
        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda); text-decoration: none; font-weight: 600; font-size: .875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); transition: all .2s; }
        .back-link:hover { background: var(--guinda); color: #fff; }
        .page-title { font-family: 'Playfair Display',Georgia,serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: .875rem; margin: 0; }

        /* ── Alertas de feedback ── */
        .alert { display: flex; align-items: flex-start; gap: 12px; padding: 14px 18px; border-radius: 12px; margin-bottom: 22px; font-size: .875rem; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-warning { background: #fff7ed; color: #9a3412; border: 1px solid #fdba74; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* ── Card tabla ── */
        .table-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,.06); overflow: hidden; }
        .table-header { padding: 20px 28px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 10px; }
        .table-header h2 { font-family: 'Playfair Display',Georgia,serif; font-size: 1.1rem; color: #111827; margin: 0; }

        /* ── Tabla ── */
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 11px 16px; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; background: #fafafa; border-bottom: 1px solid #f3f4f6; }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background .15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fdf8f0; }
        tbody td { padding: 12px 16px; font-size: .875rem; vertical-align: middle; }

        /* ── Badges fecha ── */
        .fecha-ok      { color: #065f46; font-weight: 600; }
        .fecha-vencida { color: #991b1b; font-weight: 700; }
        .badge-vencido { display: inline-block; background: #fee2e2; color: #991b1b; font-size: .68rem; font-weight: 700; padding: 2px 8px; border-radius: 8px; margin-left: 4px; }

        /* ── Avatar ── */
        .user-cell { display: flex; align-items: center; gap: 8px; }
        .avatar-sm { width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, var(--guinda), var(--guinda-dark)); display: flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem; font-weight: 700; flex-shrink: 0; }

        /* ── Botones acción ── */
        .btn-devolucion { display: inline-flex; align-items: center; gap: 5px; background: linear-gradient(135deg, var(--dorado), var(--dorado-dark)); color: #fff; border: none; padding: 8px 13px; border-radius: 8px; font-family: inherit; font-size: .8rem; font-weight: 700; cursor: pointer; transition: all .2s; white-space: nowrap; }
        .btn-devolucion:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(201,168,76,.35); }
        .btn-editar-fecha { display: inline-flex; align-items: center; gap: 5px; background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; padding: 8px 13px; border-radius: 8px; font-family: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; transition: all .2s; white-space: nowrap; margin-top: 6px; }
        .btn-editar-fecha:hover { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }
        .acciones-cell { display: flex; flex-direction: column; gap: 0; }

        /* ── Estado vacío ── */
        .empty-state { text-align: center; padding: 60px 40px; color: #6b7280; }
        .empty-state i { font-size: 3rem; color: var(--dorado); margin-bottom: 16px; display: block; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 18px; padding: 32px; max-width: 440px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
        .modal-box h3 { font-family: 'Playfair Display',Georgia,serif; color: var(--guinda); margin: 0 0 6px; font-size: 1.2rem; }
        .modal-box p  { color: #6b7280; font-size: .875rem; margin: 0 0 24px; }
        .modal-field  { margin-bottom: 20px; }
        .modal-field label { display: block; font-weight: 600; font-size: .85rem; color: #374151; margin-bottom: 7px; }
        .modal-field input[type="date"] { width: 100%; padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-family: inherit; font-size: .9rem; box-sizing: border-box; transition: border-color .2s; }
        .modal-field input[type="date"]:focus { outline: none; border-color: var(--guinda); }
        .modal-info { background: #fdf8f0; border: 1px solid #fde68a; border-radius: 10px; padding: 12px 15px; font-size: .82rem; color: #92400e; margin-bottom: 22px; }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-cancel  { padding: 10px 20px; background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; border-radius: 9px; font-family: inherit; font-size: .875rem; font-weight: 600; cursor: pointer; transition: all .2s; }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-confirm { padding: 10px 22px; background: var(--guinda); color: #fff; border: none; border-radius: 9px; font-family: inherit; font-size: .875rem; font-weight: 700; cursor: pointer; transition: all .2s; }
        .btn-confirm:hover { background: var(--guinda-dark); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">

        <!-- Navegación -->
        <div class="topnav">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
            <div>
                <h1 class="page-title"><i class="fas fa-tasks" style="font-size:1.4rem;"></i> Control de Préstamos</h1>
                <p class="page-sub">Registra devoluciones y ajusta fechas de entrega.</p>
            </div>
        </div>

        <!-- ── Mensajes de feedback [REQ-1 y REQ-2] ── -->
        <?php if ($msg === 'devolucion_exitosa'): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div><strong>Devolución registrada.</strong> El ejemplar fue recibido a tiempo y ya está disponible en inventario.</div>
        </div>

        <?php elseif ($msg === 'devolucion_extemporanea'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Entrega Extemporánea registrada.</strong>
                El libro fue devuelto con <strong><?= $dias_url ?> día(s) de atraso</strong>.
                Se generó un registro de morosidad para el usuario.
            </div>
        </div>

        <?php elseif ($msg === 'fecha_actualizada'): ?>
        <div class="alert alert-success">
            <i class="fas fa-calendar-check"></i>
            <div><strong>Fecha actualizada.</strong> La nueva fecha de entrega esperada es: <strong><?= $fecha_url ?></strong>.</div>
        </div>

        <?php elseif ($msg === 'ya_devuelto'): ?>
        <div class="alert alert-error">
            <i class="fas fa-times-circle"></i>
            <div>Este préstamo ya fue registrado como devuelto anteriormente.</div>
        </div>

        <?php elseif ($msg === 'fecha_pasada'): ?>
        <div class="alert alert-error">
            <i class="fas fa-times-circle"></i>
            <div>La fecha indicada es anterior a hoy. Selecciona una fecha igual o posterior a la fecha actual.</div>
        </div>

        <?php elseif ($msg === 'error_servidor'): ?>
        <div class="alert alert-error">
            <i class="fas fa-server"></i>
            <div>Error interno del servidor. Por favor intenta nuevamente o contacta al administrador.</div>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_carga)): ?>
        <div class="alert alert-error">
            <i class="fas fa-database"></i>
            <div>No se pudieron cargar los préstamos. Por favor recarga la página.</div>
        </div>
        <?php endif; ?>

        <!-- Tabla de préstamos activos -->
        <div class="table-card">
            <div class="table-header">
                <h2><i class="fas fa-book-open" style="color:var(--guinda);"></i>&nbsp; Préstamos activos</h2>
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
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prestamos_activos as $p):
                        $es_vencido = ($p['dias_atraso'] > 0);
                        $inicial    = mb_strtoupper(mb_substr($p['nombre_alumno'], 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                    <tr>
                        <td><strong>#<?= (int)$p['id_prestamo'] ?></strong></td>

                        <td>
                            <div class="user-cell">
                                <div class="avatar-sm"><?= htmlspecialchars($inicial) ?></div>
                                <span><?= htmlspecialchars($p['nombre_alumno']) ?></span>
                            </div>
                        </td>

                        <td>
                            <strong><?= htmlspecialchars($p['titulo']) ?></strong><br>
                            <small style="color:#9ca3af;">
                                <?= htmlspecialchars($p['autor']) ?>
                                &nbsp;·&nbsp; Ej. #<?= (int)$p['id_ejemplar'] ?>
                            </small>
                        </td>

                        <td><?= date('d/m/Y', strtotime($p['fecha_prestamo'])) ?></td>

                        <!-- [REQ-2] Fecha límite con badge de atraso -->
                        <td class="<?= $es_vencido ? 'fecha-vencida' : 'fecha-ok' ?>">
                            <?= date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) ?>
                            <?php if ($es_vencido): ?>
                                <span class="badge-vencido">+<?= (int)$p['dias_atraso'] ?> día(s)</span>
                            <?php endif; ?>
                        </td>

                        <!-- [REQ-1] Botón devolver + [REQ-3] Botón editar fecha -->
                        <td>
                            <div class="acciones-cell">
                                <!-- Botón devolución -->
                                <form action="procesar_devolucion.php" method="POST"
                                      onsubmit="return confirm('¿Confirmas que el alumno entregó el ejemplar en buen estado?');">
                                    <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="id_prestamo" value="<?= (int)$p['id_prestamo'] ?>">
                                    <input type="hidden" name="id_ejemplar" value="<?= (int)$p['id_ejemplar'] ?>">
                                    <button type="submit" class="btn-devolucion">
                                        <i class="fas fa-clipboard-check"></i> Recibir libro
                                    </button>
                                </form>

                                <!-- [REQ-3] Botón editar fecha de entrega -->
                                <button type="button" class="btn-editar-fecha"
                                        onclick="abrirModalFecha(
                                            <?= (int)$p['id_prestamo'] ?>,
                                            '<?= htmlspecialchars($p['fecha_devolucion_esperada']) ?>',
                                            '<?= htmlspecialchars($p['titulo']) ?>'
                                        )">
                                    <i class="fas fa-calendar-edit"></i> Editar fecha
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-double"></i>
                <h3>¡Todo al corriente!</h3>
                <p>No hay préstamos activos pendientes de devolución.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════════════════
         [REQ-3] MODAL: Modificar fecha de devolución esperada
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="modalFecha">
        <div class="modal-box">
            <h3><i class="fas fa-calendar-edit"></i> Modificar Fecha de Entrega</h3>
            <p id="modal-subtitle">Ajusta el plazo para el préstamo seleccionado.</p>

            <div class="modal-info">
                <i class="fas fa-info-circle"></i>
                Fecha actual: <strong id="modal-fecha-actual">—</strong>
            </div>

            <form id="formModificarFecha" action="modificar_fecha_prestamo.php" method="POST">
                <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id_prestamo"   id="modal-id-prestamo" value="">

                <div class="modal-field">
                    <label for="nueva_fecha_devolucion">
                        <i class="fas fa-calendar-day"></i> Nueva fecha de entrega
                    </label>
                    <input type="date"
                           id="nueva_fecha_devolucion"
                           name="nueva_fecha_devolucion"
                           min="<?= date('Y-m-d') ?>"
                           required>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="cerrarModalFecha()">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-confirm">
                        <i class="fas fa-save"></i> Guardar fecha
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ── [REQ-3] Lógica del modal de edición de fecha ──────────────────────
        function abrirModalFecha(idPrestamo, fechaActual, tituloLibro) {
            document.getElementById('modal-id-prestamo').value   = idPrestamo;
            document.getElementById('modal-subtitle').textContent =
                'Préstamo #' + idPrestamo + ' — ' + tituloLibro;

            // Mostrar fecha actual formateada
            const partes = fechaActual.split('-');
            document.getElementById('modal-fecha-actual').textContent =
                partes[2] + '/' + partes[1] + '/' + partes[0];

            // Precargar el input con la fecha actual del préstamo
            document.getElementById('nueva_fecha_devolucion').value = fechaActual;

            document.getElementById('modalFecha').classList.add('active');
        }

        function cerrarModalFecha() {
            document.getElementById('modalFecha').classList.remove('active');
        }

        // Cerrar al hacer clic fuera del modal
        document.getElementById('modalFecha').addEventListener('click', function(e) {
            if (e.target === this) cerrarModalFecha();
        });
    </script>
</body>
</html>
