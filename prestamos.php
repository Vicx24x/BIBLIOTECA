<?php
// =============================================================================
// prestamos.php  —  OPERACIONES Y PRÉSTAMOS  (REQ-1, REQ-2, REQ-3)
// =============================================================================
// Cambios vs versión anterior:
//   [REQ-1]  Mensajes de feedback para devolución exitosa/extemporánea.
//   [REQ-2]  Badge visual de días de atraso en tabla.
//   [REQ-3]  Botón "Editar Fecha" + modal para modificar fecha_devolucion_esperada.
//   [SEC]    Protección CSRF en formularios de devolución y edición de fecha.
//   [FIX]    Devoluciones procesadas en procesar_devolucion.php (POST/Redirect/GET).
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';

// ── 1. Autenticación ──────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit();
}

// ── 2. Autorización ──────────────────────────────────────────────────────────
if ($_SESSION['rol'] === 'Usuario') {
    die("Acceso denegado. <a href='catalogo.php'>Volver al catálogo</a>");
}

// ── 3. Carga de préstamos activos ─────────────────────────────────────────────
$prestamos_activos = [];
$historial         = [];
$error_carga       = false;

try {
    // Préstamos activos con cálculo de días de atraso
    $sql_activos = "SELECT
                        p.id_prestamo,
                        p.fecha_prestamo,
                        p.fecha_devolucion_esperada,
                        p.renovaciones_realizadas,
                        u.nombre       AS usuario,
                        l.titulo,
                        l.autor,
                        e.id_ejemplar,
                        e.codigo_activo,
                        DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) AS dias_atraso
                    FROM prestamos p
                    INNER JOIN usuarios   u ON p.id_usuario  = u.id_usuario
                    INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
                    INNER JOIN libros     l ON e.id_libro    = l.id_libro
                    WHERE p.estado = 'Activo'
                    ORDER BY p.fecha_devolucion_esperada ASC";

    $prestamos_activos = $pdo->query($sql_activos)->fetchAll(PDO::FETCH_ASSOC);

    // Historial de devoluciones (últimas 20)
    $sql_historial = "SELECT
                          p.id_prestamo,
                          u.nombre AS usuario,
                          l.titulo,
                          p.fecha_prestamo,
                          p.fecha_devolucion_real
                      FROM prestamos p
                      INNER JOIN usuarios   u ON p.id_usuario  = u.id_usuario
                      INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
                      INNER JOIN libros     l ON e.id_libro    = l.id_libro
                      WHERE p.estado = 'Devuelto'
                      ORDER BY p.fecha_devolucion_real DESC
                      LIMIT 20";

    $historial = $pdo->query($sql_historial)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[BiblioMPS][prestamos] ' . $e->getMessage());
    $error_carga = true;
}

// ── 4. Token CSRF ─────────────────────────────────────────────────────────────
$csrf = $_SESSION['csrf_token'];

// ── 5. Mensajes de feedback desde query string (Post/Redirect/Get) ────────────
$msg       = $_GET['msg']        ?? '';
$dias_url  = (int)($_GET['dias'] ?? 0);
$fecha_url = htmlspecialchars($_GET['nueva_fecha'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operaciones de Biblioteca — BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --accent:       #3498db;
            --guinda:       #850021;
            --guinda-dark:  #5a0016;
            --dorado:       #c9a84c;
            --dorado-dark:  #a8893c;
            --bg-body:      #f4f7f6;
            --white:        #ffffff;
            --shadow:       0 4px 15px rgba(0,0,0,.05);
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--bg-body);
            color: #333;
            margin: 0;
            padding: 40px;
        }

        /* ── Header de página ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 { color: var(--primary-dark); margin: 0; font-size: 1.8rem; }
        .page-header p  { color: #7f8c8d; margin: 4px 0 0; }
        .btn-volver {
            text-decoration: none;
            color: #7f8c8d;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #e5e7eb;
            transition: all .2s;
        }
        .btn-volver:hover { color: var(--accent); border-color: var(--accent); }

        /* ── Alertas de feedback [REQ-1] ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-weight: 500;
            font-size: .9rem;
        }
        .alert i { margin-top: 2px; flex-shrink: 0; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.warning { background: #fff7ed; color: #9a3412; border: 1px solid #fdba74; }
        .alert.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert.info    { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        /* ── Cards ── */
        .card {
            background: var(--white);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 40px;
            overflow: hidden;
        }
        .card h2 {
            margin-top: 0;
            color: var(--primary-dark);
            font-size: 1.4rem;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        /* ── Tabla ── */
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: .9rem; }
        th {
            background-color: #f8f9fa;
            color: #7f8c8d;
            text-transform: uppercase;
            font-size: .8rem;
            letter-spacing: .4px;
        }
        tbody tr:hover { background: #fdf8f0; }
        tbody tr:last-child td { border-bottom: none; }

        /* ── Badges ── */
        .badge         { padding: 4px 10px; border-radius: 20px; font-size: .78rem; font-weight: bold; }
        .badge-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .badge-success { background: #d4edda; color: #155724; }

        /* [REQ-2] Badge de días de atraso */
        .fecha-ok      { color: #065f46; font-weight: 600; }
        .fecha-vencida { color: #991b1b; font-weight: 700; }
        .badge-vencido {
            display: inline-block;
            background: #fee2e2;
            color: #991b1b;
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 8px;
            margin-left: 4px;
        }

        /* ── Botones de acción ── */
        .btn-devolver {
            background-color: #e67e22;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: .85rem;
            transition: background .2s;
            white-space: nowrap;
        }
        .btn-devolver:hover { background-color: #d35400; }

        /* [REQ-3] Botón editar fecha */
        .btn-editar-fecha {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
            padding: 7px 12px;
            border-radius: 6px;
            font-family: inherit;
            font-size: .82rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 6px;
            transition: all .2s;
            white-space: nowrap;
        }
        .btn-editar-fecha:hover { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }

        .acciones-cell { display: flex; flex-direction: column; gap: 0; }

        /* ── Estado vacío ── */
        .empty-msg { color: #7f8c8d; text-align: center; padding: 28px 20px; }

        /* ── Modal [REQ-3] ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
        }
        .modal-box h3 {
            color: var(--primary-dark);
            margin: 0 0 6px;
            font-size: 1.2rem;
        }
        .modal-box p { color: #6b7280; font-size: .875rem; margin: 0 0 20px; }
        .modal-info {
            background: #fff7ed;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: .82rem;
            color: #92400e;
            margin-bottom: 20px;
        }
        .modal-field { margin-bottom: 20px; }
        .modal-field label {
            display: block;
            font-weight: 600;
            font-size: .85rem;
            color: #374151;
            margin-bottom: 7px;
        }
        .modal-field input[type="date"] {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: .9rem;
            box-sizing: border-box;
            transition: border-color .2s;
        }
        .modal-field input[type="date"]:focus { outline: none; border-color: var(--accent); }
        .modal-buttons { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-cancel {
            padding: 9px 18px;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-family: inherit;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-confirm {
            padding: 9px 20px;
            background: var(--primary-dark);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: .875rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }
        .btn-confirm:hover { background: #1a252f; }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- ── Encabezado de página ── -->
    <div class="page-header">
        <div>
            <h1>🔄 Operaciones de Biblioteca</h1>
            <p>Gestiona las devoluciones, ajusta fechas y consulta el historial.</p>
        </div>
        <a href="dashboard.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>

    <!-- ── Mensajes de feedback [REQ-1] ── -->
    <?php if ($msg === 'devolucion_exitosa'): ?>
    <div class="alert success">
        <i class="fas fa-check-circle"></i>
        <div><strong>Devolución registrada.</strong> El ejemplar fue recibido a tiempo y ya está disponible en inventario.</div>
    </div>

    <?php elseif ($msg === 'devolucion_extemporanea'): ?>
    <div class="alert warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Entrega extemporánea registrada.</strong>
            El libro fue devuelto con <strong><?= $dias_url ?> día(s) de atraso</strong>.
            Se generó un registro de morosidad para el usuario.
        </div>
    </div>

    <?php elseif ($msg === 'fecha_actualizada'): ?>
    <div class="alert info">
        <i class="fas fa-calendar-check"></i>
        <div><strong>Fecha actualizada.</strong> La nueva fecha de entrega esperada es: <strong><?= $fecha_url ?></strong>.</div>
    </div>

    <?php elseif ($msg === 'ya_devuelto'): ?>
    <div class="alert error">
        <i class="fas fa-times-circle"></i>
        <div>Este préstamo ya fue registrado como devuelto anteriormente.</div>
    </div>

    <?php elseif ($msg === 'fecha_pasada'): ?>
    <div class="alert error">
        <i class="fas fa-times-circle"></i>
        <div>La fecha indicada es anterior a hoy. Selecciona una fecha igual o posterior a la fecha actual.</div>
    </div>

    <?php elseif ($msg === 'error_servidor'): ?>
    <div class="alert error">
        <i class="fas fa-server"></i>
        <div>Error interno del servidor. Por favor intenta nuevamente o contacta al administrador.</div>
    </div>
    <?php endif; ?>

    <?php if ($error_carga): ?>
    <div class="alert error">
        <i class="fas fa-database"></i>
        <div>No se pudieron cargar los préstamos. Por favor recarga la página.</div>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         TABLA DE PRÉSTAMOS ACTIVOS
    ══════════════════════════════════════════════════════ -->
    <div class="card">
        <h2><i class="fas fa-hand-holding-book"></i> Préstamos Activos</h2>

        <?php if (!empty($prestamos_activos)): ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Título del Libro</th>
                    <th>Código Etiqueta</th>
                    <th>Fecha Préstamo</th>
                    <th>Límite de Entrega</th>  <!-- [REQ-2] badge aquí -->
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prestamos_activos as $p):
                    $es_vencido = ($p['dias_atraso'] > 0);
                ?>
                <tr>
                    <td style="font-weight:bold;"><?= htmlspecialchars($p['usuario']) ?></td>

                    <td>
                        <strong><?= htmlspecialchars($p['titulo']) ?></strong><br>
                        <small style="color:#9ca3af;"><?= htmlspecialchars($p['autor']) ?></small>
                    </td>

                    <td>
                        <span class="badge badge-warning">
                            <?= htmlspecialchars($p['codigo_activo']) ?>
                        </span>
                    </td>

                    <td><?= date('d/m/Y', strtotime($p['fecha_prestamo'])) ?></td>

                    <!-- [REQ-2] Fecha límite con badge de atraso -->
                    <td class="<?= $es_vencido ? 'fecha-vencida' : 'fecha-ok' ?>">
                        <?= date('d/m/Y', strtotime($p['fecha_devolucion_esperada'])) ?>
                        <?php if ($es_vencido): ?>
                            <span class="badge-vencido">+<?= (int)$p['dias_atraso'] ?> día(s)</span>
                        <?php endif; ?>
                    </td>

                    <!-- [REQ-1] Botón devolver  |  [REQ-3] Botón editar fecha -->
                    <td>
                        <div class="acciones-cell">

                            <!-- Devolver -->
                            <form action="procesar_devolucion.php" method="POST"
                                  onsubmit="return confirm('¿Confirmas que el libro ha sido devuelto en buen estado?');">
                                <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="id_prestamo" value="<?= (int)$p['id_prestamo'] ?>">
                                <input type="hidden" name="id_ejemplar" value="<?= (int)$p['id_ejemplar'] ?>">
                                <button type="submit" class="btn-devolver">
                                    <i class="fas fa-undo"></i> Devolver
                                </button>
                            </form>

                            <!-- [REQ-3] Editar fecha -->
                            <button type="button" class="btn-editar-fecha"
                                    onclick="abrirModalFecha(
                                        <?= (int)$p['id_prestamo'] ?>,
                                        '<?= htmlspecialchars($p['fecha_devolucion_esperada']) ?>',
                                        '<?= htmlspecialchars(addslashes($p['titulo'])) ?>'
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
        <p class="empty-msg">No hay libros prestados en este momento.</p>
        <?php endif; ?>
    </div>


    <!-- ══════════════════════════════════════════════════════
         HISTORIAL DE DEVOLUCIONES
    ══════════════════════════════════════════════════════ -->
    <div class="card">
        <h2><i class="fas fa-history"></i> Historial de Devoluciones (Últimas 20)</h2>

        <?php if (!empty($historial)): ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Título del Libro</th>
                    <th>Fecha Préstamo</th>
                    <th>Fecha Devolución Real</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $item): ?>
                <tr>
                    <td style="font-weight:bold;"><?= htmlspecialchars($item['usuario']) ?></td>
                    <td><?= htmlspecialchars($item['titulo']) ?></td>
                    <td><?= date('d/m/Y', strtotime($item['fecha_prestamo'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($item['fecha_devolucion_real'])) ?></td>
                    <td>
                        <span class="badge badge-success">
                            <i class="fas fa-check"></i> Devuelto
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php else: ?>
        <p class="empty-msg">Aún no hay registros de devoluciones.</p>
        <?php endif; ?>
    </div>


    <!-- ══════════════════════════════════════════════════════
         [REQ-3] MODAL: Modificar fecha de devolución esperada
    ══════════════════════════════════════════════════════ -->
    <div class="modal-overlay" id="modalFecha">
        <div class="modal-box">
            <h3><i class="fas fa-calendar-edit"></i> Modificar Fecha de Entrega</h3>
            <p id="modal-subtitle">Ajusta el plazo para el préstamo seleccionado.</p>

            <div class="modal-info">
                <i class="fas fa-info-circle"></i>
                Fecha actual: <strong id="modal-fecha-actual">—</strong>
            </div>

            <form id="formModificarFecha" action="modificar_fecha_prestamo.php" method="POST">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id_prestamo" id="modal-id-prestamo" value="">

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
        // ── [REQ-3] Lógica del modal ───────────────────────────────────────────
        function abrirModalFecha(idPrestamo, fechaActual, tituloLibro) {
            document.getElementById('modal-id-prestamo').value  = idPrestamo;
            document.getElementById('modal-subtitle').textContent =
                'Préstamo #' + idPrestamo + ' — ' + tituloLibro;

            // Mostrar fecha actual en formato dd/mm/aaaa
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
        document.getElementById('modalFecha').addEventListener('click', function (e) {
            if (e.target === this) cerrarModalFecha();
        });
    </script>
</body>
</html>
