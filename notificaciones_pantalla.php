<?php
// =============================================================================
// notificaciones_pantalla.php  —  SISTEMA DE ALERTAS VISUALES [REQ-4]
// =============================================================================
// Fix aplicado: la función renderizar_notificaciones_pantalla() ahora se
// declara SIEMPRE al inicio del archivo, independientemente del guard de
// sesión. Antes, el return; temprano la dejaba sin definir y causaba
// Fatal Error → 404 en nginx.
// =============================================================================

// =============================================================================
// FUNCIÓN DE RENDERIZADO — se declara PRIMERO para que siempre esté disponible
// =============================================================================
function renderizar_notificaciones_pantalla(array $notif): void {
    $proximos = $notif['proximos'];
    $vencidos = $notif['vencidos'];
    $es_admin = $notif['es_admin'];

    if (empty($proximos) && empty($vencidos)) return;
    ?>

    <style>
        .notif-stack {
            max-width: 1200px;
            margin: 0 auto;
            padding: 18px 32px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .notif-banner {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 20px;
            border-radius: 14px;
            border-left: 5px solid transparent;
            font-size: .875rem;
            animation: notifSlideIn .4s ease;
        }
        @keyframes notifSlideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .notif-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #78350f;
            box-shadow: 0 2px 12px rgba(245,158,11,.15);
        }
        .notif-warning .notif-icon { font-size: 1.4rem; color: #d97706; margin-top: 2px; flex-shrink: 0; }
        .notif-critical {
            background: #fff1f2;
            border-color: #e11d48;
            color: #881337;
            box-shadow: 0 2px 16px rgba(225,29,72,.18);
        }
        .notif-critical .notif-icon { font-size: 1.4rem; color: #e11d48; margin-top: 2px; flex-shrink: 0; }
        .notif-title  { font-weight: 800; font-size: .95rem; margin-bottom: 4px; }
        .notif-body   { line-height: 1.55; flex: 1; }
        .notif-list   { margin: 8px 0 0; padding-left: 0; list-style: none; display: flex; flex-direction: column; gap: 4px; }
        .notif-list li { display: flex; align-items: center; gap: 8px; font-size: .835rem; font-weight: 500; }
        .notif-list li::before { content: '▸'; font-size: .75rem; opacity: .7; }
        .dias-badge { display: inline-block; padding: 1px 8px; border-radius: 20px; font-size: .75rem; font-weight: 800; margin-left: auto; }
        .dias-badge.warning { background: #fde68a; color: #92400e; }
        .dias-badge.danger  { background: #fecdd3; color: #9f1239; }
        .notif-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            opacity: .5;
            font-size: 1rem;
            padding: 2px 6px;
            border-radius: 6px;
            transition: opacity .2s;
            flex-shrink: 0;
            align-self: flex-start;
        }
        .notif-close:hover { opacity: 1; }
        @media (max-width: 640px) { .notif-stack { padding: 14px 16px 0; } }
    </style>

    <div class="notif-stack" id="notif-stack">

        <?php if (!empty($vencidos)): ?>
        <div class="notif-banner notif-critical" id="notif-vencidos">
            <span class="notif-icon"><i class="fas fa-circle-exclamation"></i></span>
            <div class="notif-body">
                <div class="notif-title">
                    <?= $es_admin
                        ? '⚠️ ' . count($vencidos) . ' préstamo(s) vencido(s) en el sistema'
                        : '⚠️ Tienes préstamo(s) vencido(s) — acude a la biblioteca' ?>
                </div>
                <?= $es_admin ? 'Estos usuarios tienen ejemplares fuera de plazo:' : 'Los siguientes libros superaron su fecha límite de entrega:' ?>
                <ul class="notif-list">
                    <?php foreach ($vencidos as $v): ?>
                    <li>
                        <?php if ($es_admin): ?>
                            <strong><?= htmlspecialchars($v['nombre_usuario']) ?></strong>
                            — <?= htmlspecialchars($v['titulo']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($v['titulo']) ?>
                        <?php endif; ?>
                        <span class="dias-badge danger">+<?= (int)$v['dias_atraso'] ?> día(s)</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="notif-close" onclick="cerrarNotif('notif-vencidos')" title="Cerrar">✕</button>
        </div>
        <?php endif; ?>

        <?php if (!empty($proximos)): ?>
        <div class="notif-banner notif-warning" id="notif-proximos">
            <span class="notif-icon"><i class="fas fa-clock"></i></span>
            <div class="notif-body">
                <div class="notif-title">
                    <?= $es_admin
                        ? 'Recordatorio: ' . count($proximos) . ' préstamo(s) vencen muy pronto'
                        : 'Recordatorio: tu(s) préstamo(s) vencen muy pronto' ?>
                </div>
                <?= $es_admin ? 'Recuerda notificar a estos usuarios:' : 'Por favor devuelve el/los libro(s) a tiempo:' ?>
                <ul class="notif-list">
                    <?php foreach ($proximos as $pr):
                        $dr = (int)$pr['dias_restantes'];
                        $etiqueta = $dr === 0 ? '¡Hoy!' : ($dr === 1 ? 'Mañana' : 'En 2 días');
                    ?>
                    <li>
                        <?php if ($es_admin): ?>
                            <strong><?= htmlspecialchars($pr['nombre_usuario']) ?></strong>
                            — <?= htmlspecialchars($pr['titulo']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($pr['titulo']) ?>
                        <?php endif; ?>
                        <span class="dias-badge warning"><?= $etiqueta ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="notif-close" onclick="cerrarNotif('notif-proximos')" title="Cerrar">✕</button>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function cerrarNotif(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.transition = 'opacity .3s, max-height .3s';
                el.style.opacity    = '0';
                el.style.maxHeight  = '0';
                el.style.padding    = '0';
                el.style.margin     = '0';
                setTimeout(() => el.remove(), 350);
            }
        }
    </script>
    <?php
}

// =============================================================================
// LÓGICA DE DATOS — se ejecuta DESPUÉS de definir la función
// Guard de seguridad: si no hay sesión o BD, deja $notif_pantalla vacío y sale
// =============================================================================
if (!isset($pdo) || !isset($_SESSION['id_usuario'])) {
    $notif_pantalla = ['proximos' => [], 'vencidos' => [], 'es_admin' => false];
    return; // ← ahora es seguro: la función ya fue declarada antes de este return
}

$id_usuario_notif = (int)$_SESSION['id_usuario'];
$rol_notif        = $_SESSION['rol'] ?? 'Usuario';
$es_admin_notif   = in_array($rol_notif, ['Administrador', 'Bibliotecario'], true);

$proximos_vencer = [];
$vencidos        = [];

try {
    if ($es_admin_notif) {
        // Vista Admin/Bibliotecario: resumen global
        $stmt_prox = $pdo->prepare(
            "SELECT p.id_prestamo, u.nombre AS nombre_usuario,
                    l.titulo, p.fecha_devolucion_esperada,
                    DATEDIFF(p.fecha_devolucion_esperada, CURRENT_DATE) AS dias_restantes
             FROM prestamos p
             INNER JOIN usuarios   u ON p.id_usuario  = u.id_usuario
             INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
             INNER JOIN libros     l ON e.id_libro    = l.id_libro
             WHERE p.estado = 'Activo'
               AND DATEDIFF(p.fecha_devolucion_esperada, CURRENT_DATE) BETWEEN 0 AND 2
             ORDER BY p.fecha_devolucion_esperada ASC
             LIMIT 10"
        );
        $stmt_prox->execute();
        $proximos_vencer = $stmt_prox->fetchAll(PDO::FETCH_ASSOC);

        $stmt_venc = $pdo->prepare(
            "SELECT p.id_prestamo, u.nombre AS nombre_usuario,
                    l.titulo, p.fecha_devolucion_esperada,
                    DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) AS dias_atraso
             FROM prestamos p
             INNER JOIN usuarios   u ON p.id_usuario  = u.id_usuario
             INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
             INNER JOIN libros     l ON e.id_libro    = l.id_libro
             WHERE p.estado = 'Activo'
               AND p.fecha_devolucion_esperada < CURRENT_DATE
             ORDER BY dias_atraso DESC
             LIMIT 10"
        );
        $stmt_venc->execute();
        $vencidos = $stmt_venc->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Vista Usuario normal: solo sus propios préstamos
        $stmt_prox = $pdo->prepare(
            "SELECT p.id_prestamo, l.titulo,
                    p.fecha_devolucion_esperada,
                    DATEDIFF(p.fecha_devolucion_esperada, CURRENT_DATE) AS dias_restantes
             FROM prestamos p
             INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
             INNER JOIN libros     l ON e.id_libro    = l.id_libro
             WHERE p.id_usuario = :id_usuario
               AND p.estado     = 'Activo'
               AND DATEDIFF(p.fecha_devolucion_esperada, CURRENT_DATE) BETWEEN 0 AND 2
             ORDER BY p.fecha_devolucion_esperada ASC"
        );
        $stmt_prox->execute(['id_usuario' => $id_usuario_notif]);
        $proximos_vencer = $stmt_prox->fetchAll(PDO::FETCH_ASSOC);

        $stmt_venc = $pdo->prepare(
            "SELECT p.id_prestamo, l.titulo,
                    p.fecha_devolucion_esperada,
                    DATEDIFF(CURRENT_DATE, p.fecha_devolucion_esperada) AS dias_atraso
             FROM prestamos p
             INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
             INNER JOIN libros     l ON e.id_libro    = l.id_libro
             WHERE p.id_usuario = :id_usuario
               AND p.estado     = 'Activo'
               AND p.fecha_devolucion_esperada < CURRENT_DATE
             ORDER BY dias_atraso DESC"
        );
        $stmt_venc->execute(['id_usuario' => $id_usuario_notif]);
        $vencidos = $stmt_venc->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('[BiblioMPS][notificaciones_pantalla] ' . $e->getMessage());
    $proximos_vencer = [];
    $vencidos        = [];
}

$notif_pantalla = [
    'proximos' => $proximos_vencer,
    'vencidos' => $vencidos,
    'es_admin' => $es_admin_notif,
];
?>
