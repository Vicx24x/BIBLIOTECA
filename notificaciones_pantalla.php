<?php
// =============================================================================
// notificaciones_pantalla.php  —  SISTEMA DE ALERTAS VISUALES [REQ-4]
// =============================================================================
// INSTRUCCIONES DE USO:
//   1. Incluye este archivo AL INICIO de dashboard.php, DESPUÉS de session_start()
//      y require_once 'config/db.php', pero ANTES de cualquier HTML:
//
//      session_start();
//      require_once 'config/db.php';
//      require_once 'notificaciones_pantalla.php';   ← aquí
//
//   2. Luego en el <body> de dashboard.php, justo después de <?php include 'header.php'; ?>
//      agrega esta sola línea:
//
//      <?php renderizar_notificaciones_pantalla($notif_pantalla); ?>
//
// =============================================================================
// Reglas implementadas (REQ-4):
//   A) Préstamo que vence en 2 días o menos → Banner AMARILLO de advertencia.
//   B) Préstamo(s) ya vencidos             → Banner ROJO crítico con días de atraso.
// Para usuarios con rol Usuario: consulta solo sus propios préstamos.
// Para Administrador/Bibliotecario: muestra resumen global del sistema.
// =============================================================================

if (!isset($pdo) || !isset($_SESSION['id_usuario'])) {
    // Seguridad: si no hay sesión/BD activa, no hacer nada
    $notif_pantalla = ['proximos' => [], 'vencidos' => [], 'es_admin' => false];
    return;
}

$id_usuario_notif = (int)$_SESSION['id_usuario'];
$rol_notif        = $_SESSION['rol'] ?? 'Usuario';
$es_admin_notif   = in_array($rol_notif, ['Administrador', 'Bibliotecario'], true);

$proximos_vencer = [];  // préstamos que vencen en ≤ 2 días
$vencidos        = [];  // préstamos ya vencidos

try {
    if ($es_admin_notif) {
        // ── Vista Administrador/Bibliotecario: resumen global ──────────────────
        // Préstamos activos que vencen en exactamente 0, 1 o 2 días (aún no vencidos)
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

        // Préstamos ya vencidos (fecha pasada, aún activos)
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
        // ── Vista Usuario normal: solo sus propios préstamos ──────────────────
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
    // Sin datos de notificación, el sistema sigue funcionando normalmente
    $proximos_vencer = [];
    $vencidos        = [];
}

// Empaquetar para pasar a la función de renderizado
$notif_pantalla = [
    'proximos'  => $proximos_vencer,
    'vencidos'  => $vencidos,
    'es_admin'  => $es_admin_notif,
];

// =============================================================================
// FUNCIÓN DE RENDERIZADO
// Llámala en el <body> de dashboard.php:
//   <?php renderizar_notificaciones_pantalla($notif_pantalla); ?>
// =============================================================================
function renderizar_notificaciones_pantalla(array $notif): void {
    $proximos = $notif['proximos'];
    $vencidos = $notif['vencidos'];
    $es_admin = $notif['es_admin'];

    // Sin alertas → no renderizar nada (sin HTML extra)
    if (empty($proximos) && empty($vencidos)) return;

    $prefijo_usuario = $es_admin ? 'usuario ' : 'tu libro ';
    ?>

    <!-- ════════════════════════════════════════════════════════════════════
         [REQ-4] NOTIFICACIONES EN PANTALLA — BiblioMPS
    ════════════════════════════════════════════════════════════════════ -->
    <style>
        /* ── Contenedor de notificaciones ── */
        .notif-stack {
            max-width: 1200px;
            margin: 0 auto 0;
            padding: 18px 32px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* ── Banner base ── */
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

        /* ── [REQ-4-A] Banner amarillo: vence pronto ── */
        .notif-warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #78350f;
            box-shadow: 0 2px 12px rgba(245,158,11,.15);
        }
        .notif-warning .notif-icon {
            font-size: 1.4rem;
            color: #d97706;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* ── [REQ-4-B] Banner rojo: vencido ── */
        .notif-critical {
            background: #fff1f2;
            border-color: #e11d48;
            color: #881337;
            box-shadow: 0 2px 16px rgba(225,29,72,.18);
        }
        .notif-critical .notif-icon {
            font-size: 1.4rem;
            color: #e11d48;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* ── Título del banner ── */
        .notif-title {
            font-weight: 800;
            font-size: .95rem;
            margin-bottom: 4px;
        }
        .notif-body { line-height: 1.55; }

        /* ── Lista interna ── */
        .notif-list {
            margin: 8px 0 0;
            padding-left: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .notif-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .835rem;
            font-weight: 500;
        }
        .notif-list li::before {
            content: '▸';
            font-size: .75rem;
            opacity: .7;
        }

        /* ── Badge días ── */
        .dias-badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 800;
            margin-left: auto;
        }
        .dias-badge.warning { background: #fde68a; color: #92400e; }
        .dias-badge.danger  { background: #fecdd3; color: #9f1239; }

        /* ── Botón cerrar ── */
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

        @media (max-width: 640px) {
            .notif-stack { padding: 14px 16px 0; }
        }
    </style>

    <div class="notif-stack" id="notif-stack">

        <?php if (!empty($vencidos)): ?>
        <!-- [REQ-4-B] ALERTA CRÍTICA ROJA: préstamos vencidos -->
        <div class="notif-banner notif-critical" id="notif-vencidos">
            <span class="notif-icon"><i class="fas fa-circle-exclamation"></i></span>
            <div class="notif-body">
                <div class="notif-title">
                    <?= $es_admin
                        ? '⚠️ ' . count($vencidos) . ' préstamo(s) vencido(s) en el sistema'
                        : '⚠️ Tienes préstamo(s) vencido(s) — acude a la biblioteca' ?>
                </div>
                <?php if ($es_admin): ?>
                    Estos usuarios tienen ejemplares fuera de plazo:
                <?php else: ?>
                    Los siguientes libros superaron su fecha límite de entrega:
                <?php endif; ?>
                <ul class="notif-list">
                    <?php foreach ($vencidos as $v): ?>
                    <li>
                        <?php if ($es_admin): ?>
                            <strong><?= htmlspecialchars($v['nombre_usuario']) ?></strong>
                            — <?= htmlspecialchars($v['titulo']) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($v['titulo']) ?>
                        <?php endif; ?>
                        <span class="dias-badge danger">
                            +<?= (int)$v['dias_atraso'] ?> día(s)
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <button class="notif-close" onclick="cerrarNotif('notif-vencidos')"
                    title="Cerrar alerta">✕</button>
        </div>
        <?php endif; ?>

        <?php if (!empty($proximos)): ?>
        <!-- [REQ-4-A] ALERTA AMARILLA: vence en ≤ 2 días -->
        <div class="notif-banner notif-warning" id="notif-proximos">
            <span class="notif-icon"><i class="fas fa-clock"></i></span>
            <div class="notif-body">
                <div class="notif-title">
                    <?= $es_admin
                        ? 'Recordatorio: ' . count($proximos) . ' préstamo(s) vencen muy pronto'
                        : 'Recordatorio: tu(s) préstamo(s) vencen muy pronto' ?>
                </div>
                <?php if ($es_admin): ?>
                    Recuerda notificar a estos usuarios:
                <?php else: ?>
                    Por favor devuelve el/los libro(s) a tiempo:
                <?php endif; ?>
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
            <button class="notif-close" onclick="cerrarNotif('notif-proximos')"
                    title="Cerrar alerta">✕</button>
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
?>
