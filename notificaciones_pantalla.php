<?php
// =============================================================================
// notificaciones_pantalla.php  —  SISTEMA DE ALERTAS VISUALES [REQ-4]
// =============================================================================
// Fix layout: franja lateral como border-left en la card (no div hijo),
// estructura interna con flexbox simple: icon | body | dismiss.
// =============================================================================

function renderizar_notificaciones_pantalla(array $notif): void {
    $proximos = $notif['proximos'];
    $vencidos = $notif['vencidos'];
    $es_admin = $notif['es_admin'];

    if (empty($proximos) && empty($vencidos)) return;
    ?>

    <style>
        .notif-zona {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 32px 4px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Card: flex row, franja como border-left */
        .notif-card {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            border-radius: 16px;
            border: 1px solid transparent;
            border-left-width: 5px;
            border-left-style: solid;
            box-shadow: 0 3px 18px rgba(0,0,0,.09);
            animation: notifIn .4s cubic-bezier(.22,.68,0,1.2) both;
            overflow: hidden;
        }
        .notif-card:nth-child(2) { animation-delay: .08s; }

        @keyframes notifIn {
            from { opacity: 0; transform: translateY(-12px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* Variantes de color */
        .notif-card.critical {
            background: #fff5f6;
            border-left-color: #e11d48;
            border-color: #fecdd3;
            border-left-color: #e11d48;
        }
        .notif-card.warning {
            background: #fffbeb;
            border-color: #fde68a;
            border-left-color: #f59e0b;
        }

        /* Icono */
        .notif-icon-wrap {
            flex-shrink: 0;
            padding: 18px 16px 18px 18px;
            display: flex;
            align-items: flex-start;
        }
        .notif-icon-circle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .notif-card.critical .notif-icon-circle {
            background: #fecdd3;
            color: #9f1239;
        }
        .notif-card.warning .notif-icon-circle {
            background: #fde68a;
            color: #92400e;
        }

        /* Cuerpo */
        .notif-body {
            flex: 1;
            min-width: 0;
            padding: 18px 8px 18px 0;
        }
        .notif-header-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
            flex-wrap: wrap;
        }
        .notif-titulo {
            font-size: .92rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .notif-card.critical .notif-titulo { color: #881337; }
        .notif-card.warning  .notif-titulo { color: #78350f; }

        .notif-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 20px;
            font-size: .69rem;
            font-weight: 900;
            flex-shrink: 0;
        }
        .notif-card.critical .notif-count-badge { background: #e11d48; color: #fff; }
        .notif-card.warning  .notif-count-badge { background: #f59e0b; color: #fff; }

        .notif-subtitulo {
            font-size: .78rem;
            margin-bottom: 10px;
        }
        .notif-card.critical .notif-subtitulo { color: #9f1239; opacity: .8; }
        .notif-card.warning  .notif-subtitulo { color: #92400e; opacity: .8; }

        /* Lista */
        .notif-items {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .notif-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: .81rem;
            font-weight: 500;
        }
        .notif-card.critical .notif-item { background: rgba(225,29,72,.06); color: #881337; }
        .notif-card.warning  .notif-item { background: rgba(245,158,11,.09); color: #78350f; }

        .notif-item-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .notif-card.critical .notif-item-dot { background: #e11d48; }
        .notif-card.warning  .notif-item-dot { background: #f59e0b; }

        .notif-item-nombre { font-weight: 800; }
        .notif-item-sep    { opacity: .35; margin: 0 2px; }
        .notif-item-titulo {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Chips */
        .dias-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 800;
            white-space: nowrap;
            flex-shrink: 0;
            margin-left: auto;
        }
        .dias-chip.urgent  { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
        .dias-chip.danger  { background: #fecdd3; color: #9f1239; }
        .dias-chip.caution { background: #fde68a; color: #92400e; }

        /* Dismiss */
        .notif-dismiss {
            flex-shrink: 0;
            padding: 14px 14px 14px 6px;
        }
        .notif-dismiss button {
            background: none;
            border: none;
            cursor: pointer;
            width: 26px; height: 26px;
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
            opacity: .4;
            transition: opacity .2s, background .2s;
            line-height: 1;
        }
        .notif-card.critical .notif-dismiss button { color: #9f1239; }
        .notif-card.warning  .notif-dismiss button { color: #92400e; }
        .notif-dismiss button:hover { opacity: 1; background: rgba(0,0,0,.07); }

        @media (max-width: 640px) {
            .notif-zona { padding: 12px 12px 4px; }
            .notif-item-titulo { display: none; }
        }
    </style>

    <div class="notif-zona" id="notif-zona">

        <?php if (!empty($vencidos)): ?>
        <div class="notif-card critical" id="notif-vencidos">

            <div class="notif-icon-wrap">
                <div class="notif-icon-circle">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
            </div>

            <div class="notif-body">
                <div class="notif-header-row">
                    <span class="notif-titulo">
                        <?= $es_admin ? 'Préstamos vencidos en el sistema' : 'Tienes préstamos vencidos' ?>
                    </span>
                    <span class="notif-count-badge"><?= count($vencidos) ?></span>
                </div>
                <div class="notif-subtitulo">
                    <?= $es_admin
                        ? 'Estos usuarios tienen ejemplares fuera de plazo — acción requerida'
                        : 'Acude a la biblioteca para regularizar tu situación' ?>
                </div>
                <ul class="notif-items">
                    <?php foreach ($vencidos as $v):
                        $dias = (int)$v['dias_atraso'];
                        $chipClass = $dias >= 5 ? 'urgent' : 'danger';
                    ?>
                    <li class="notif-item">
                        <span class="notif-item-dot"></span>
                        <?php if ($es_admin): ?>
                            <span class="notif-item-nombre"><?= htmlspecialchars($v['nombre_usuario']) ?></span>
                            <span class="notif-item-sep">—</span>
                        <?php endif; ?>
                        <span class="notif-item-titulo"><?= htmlspecialchars($v['titulo']) ?></span>
                        <span class="dias-chip <?= $chipClass ?>">
                            <i class="fas fa-clock" style="font-size:.63rem;"></i>
                            +<?= $dias ?> día<?= $dias !== 1 ? 's' : '' ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="notif-dismiss">
                <button onclick="descartarNotif('notif-vencidos')" title="Cerrar">✕</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($proximos)): ?>
        <div class="notif-card warning" id="notif-proximos">

            <div class="notif-icon-wrap">
                <div class="notif-icon-circle">
                    <i class="fas fa-bell"></i>
                </div>
            </div>

            <div class="notif-body">
                <div class="notif-header-row">
                    <span class="notif-titulo">
                        <?= $es_admin ? 'Préstamos próximos a vencer' : 'Recordatorio de devolución' ?>
                    </span>
                    <span class="notif-count-badge"><?= count($proximos) ?></span>
                </div>
                <div class="notif-subtitulo">
                    <?= $es_admin
                        ? 'Recuerda notificar a estos usuarios con anticipación'
                        : 'Por favor devuelve el/los libro(s) antes de que venza el plazo' ?>
                </div>
                <ul class="notif-items">
                    <?php foreach ($proximos as $pr):
                        $dr = (int)$pr['dias_restantes'];
                        if ($dr === 0)     { $etiqueta = '¡Hoy!';     $chipClass = 'urgent';  }
                        elseif ($dr === 1) { $etiqueta = 'Mañana';    $chipClass = 'danger';  }
                        else               { $etiqueta = 'En 2 días'; $chipClass = 'caution'; }
                    ?>
                    <li class="notif-item">
                        <span class="notif-item-dot"></span>
                        <?php if ($es_admin): ?>
                            <span class="notif-item-nombre"><?= htmlspecialchars($pr['nombre_usuario']) ?></span>
                            <span class="notif-item-sep">—</span>
                        <?php endif; ?>
                        <span class="notif-item-titulo"><?= htmlspecialchars($pr['titulo']) ?></span>
                        <span class="dias-chip <?= $chipClass ?>">
                            <i class="fas fa-hourglass-half" style="font-size:.63rem;"></i>
                            <?= $etiqueta ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="notif-dismiss">
                <button onclick="descartarNotif('notif-proximos')" title="Cerrar">✕</button>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
        function descartarNotif(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.style.overflow   = 'hidden';
            el.style.maxHeight  = el.offsetHeight + 'px';
            el.style.transition = 'opacity .25s ease, transform .25s ease, max-height .3s ease, margin .3s ease';
            requestAnimationFrame(() => {
                el.style.opacity   = '0';
                el.style.transform = 'translateY(-6px) scale(.98)';
                el.style.maxHeight = '0';
                el.style.margin    = '0';
            });
            setTimeout(() => {
                el.remove();
                const zona = document.getElementById('notif-zona');
                if (zona && !zona.children.length) zona.remove();
            }, 320);
        }
    </script>
    <?php
}

// =============================================================================
// LÓGICA DE DATOS
// =============================================================================
if (!isset($pdo) || !isset($_SESSION['id_usuario'])) {
    $notif_pantalla = ['proximos' => [], 'vencidos' => [], 'es_admin' => false];
    return;
}

$id_usuario_notif = (int)$_SESSION['id_usuario'];
$rol_notif        = $_SESSION['rol'] ?? 'Usuario';
$es_admin_notif   = in_array($rol_notif, ['Administrador', 'Bibliotecario'], true);

$proximos_vencer = [];
$vencidos        = [];

try {
    if ($es_admin_notif) {
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
