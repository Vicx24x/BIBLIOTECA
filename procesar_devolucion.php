<?php
// =============================================================================
// procesar_devolucion.php  —  DEVOLUCIÓN + ENTREGA EXTEMPORÁNEA (SIN CORREOS)
// =============================================================================
// Requerimientos cubiertos:
//   [REQ-1] Actualiza estado de ejemplar Prestado → Disponible al devolver.
//   [REQ-2] Detecta entrega extemporánea y calcula días de atraso exactos.
//   [REQ-3] Registra morosidad en tabla `morosidad` si aplica.
//   [SIN-CORREOS] Eliminado require_once 'notificaciones.php' y toda llamada
//                 a notificar_devolucion_extemporanea(). 100% transaccional.
// Seguridad: CSRF, ownership, prepared statements, transacción ACID.
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';
// ❌ ELIMINADO: require_once 'notificaciones.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php?msg=sin_sesion");
    exit();
}

// ── 2. Solo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gestionar_prestamos.php');
    exit();
}

// ── 3. Validación CSRF ───────────────────────────────────────────────────────
$token_recibido = $_POST['csrf_token'] ?? '';
if (!csrf_valido($token_recibido)) {
    http_response_code(403);
    die("Solicitud rechazada: token CSRF inválido. <a href='gestionar_prestamos.php'>Volver</a>");
}

// ── 4. Sanitización ──────────────────────────────────────────────────────────
$id_prestamo = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
$id_ejemplar = filter_input(INPUT_POST, 'id_ejemplar', FILTER_VALIDATE_INT);

if (!$id_prestamo || !$id_ejemplar || $id_prestamo < 1 || $id_ejemplar < 1) {
    header("Location: gestionar_prestamos.php?msg=params_invalidos");
    exit();
}

$id_rol    = $_SESSION['rol']        ?? 'Usuario';
$id_sesion = (int)$_SESSION['id_usuario'];
$es_privil = in_array($id_rol, ['Administrador', 'Bibliotecario'], true);

// ── 5. Transacción principal ──────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 5a. Leer el préstamo con bloqueo pesimista
    $stmt_get = $pdo->prepare(
        "SELECT id_prestamo, id_usuario, id_ejemplar,
                fecha_prestamo, fecha_devolucion_esperada, estado
         FROM prestamos
         WHERE id_prestamo = :id_prestamo
         FOR UPDATE"
    );
    $stmt_get->execute(['id_prestamo' => $id_prestamo]);
    $prestamo = $stmt_get->fetch(PDO::FETCH_ASSOC);

    // 5b. Validaciones de integridad
    if (!$prestamo) {
        $pdo->rollBack();
        header("Location: gestionar_prestamos.php?msg=prestamo_no_encontrado");
        exit();
    }
    if ($prestamo['estado'] !== 'Activo') {
        $pdo->rollBack();
        header("Location: gestionar_prestamos.php?msg=ya_devuelto");
        exit();
    }
    if ((int)$prestamo['id_ejemplar'] !== $id_ejemplar) {
        $pdo->rollBack();
        header("Location: gestionar_prestamos.php?msg=discrepancia_ejemplar");
        exit();
    }

    // Solo el dueño del préstamo o un privilegiado puede devolver
    if (!$es_privil && (int)$prestamo['id_usuario'] !== $id_sesion) {
        $pdo->rollBack();
        http_response_code(403);
        header("Location: gestionar_prestamos.php?msg=sin_permiso");
        exit();
    }

    // ── [REQ-2] Calcular extemporaneidad ────────────────────────────────────
    $hoy          = new DateTime('today');
    $fecha_limite = new DateTime($prestamo['fecha_devolucion_esperada']);
    $dias_atraso  = 0;
    $es_extemporanea = false;

    if ($hoy > $fecha_limite) {
        $es_extemporanea = true;
        $diff            = $fecha_limite->diff($hoy);
        $dias_atraso     = (int)$diff->days; // diferencia exacta en días calendario
    }

    $fecha_devolucion_real = $hoy->format('Y-m-d');

    // 5c. Actualizar préstamo: estado + fecha real + días de atraso
    // ── [REQ-1] Cambio de estado del préstamo ───────────────────────────────
    $nuevo_estado = $es_extemporanea ? 'Extemporáneo' : 'Devuelto';

    $stmt_p = $pdo->prepare(
        "UPDATE prestamos
         SET estado                = :estado,
             fecha_devolucion_real = :fecha_real,
             dias_atraso           = :dias_atraso
         WHERE id_prestamo = :id_prestamo"
    );
    $stmt_p->execute([
        'estado'      => $nuevo_estado,
        'fecha_real'  => $fecha_devolucion_real,
        'dias_atraso' => $dias_atraso,
        'id_prestamo' => $id_prestamo,
    ]);

    // 5d. ── [REQ-1] Liberar el ejemplar físico: Prestado → Disponible ───────
    $stmt_e = $pdo->prepare(
        "UPDATE ejemplares SET estado = 'Disponible' WHERE id_ejemplar = :id_ejemplar"
    );
    $stmt_e->execute(['id_ejemplar' => $id_ejemplar]);

    // 5e. ── [REQ-2] Registrar morosidad si la entrega fue extemporánea ──────
    if ($es_extemporanea) {
        $stmt_mor = $pdo->prepare(
            "INSERT INTO morosidad
                (id_prestamo, id_usuario, dias_atraso, fecha_registro)
             VALUES
                (:id_prestamo, :id_usuario, :dias_atraso, CURRENT_DATE)
             ON DUPLICATE KEY UPDATE
                dias_atraso    = VALUES(dias_atraso),
                fecha_registro = VALUES(fecha_registro)"
        );
        $stmt_mor->execute([
            'id_prestamo' => $id_prestamo,
            'id_usuario'  => (int)$prestamo['id_usuario'],
            'dias_atraso' => $dias_atraso,
        ]);
    }

    $pdo->commit();

    // ── [SIN-CORREOS] Sin llamada a notificar_devolucion_extemporanea() ──────
    // La notificación al usuario se maneja visualmente en dashboard.php (REQ-4)

    // 5f. Redirección con mensaje contextual
    if ($es_extemporanea) {
        header("Location: gestionar_prestamos.php?msg=devolucion_extemporanea&dias={$dias_atraso}");
    } else {
        header("Location: gestionar_prestamos.php?msg=devolucion_exitosa");
    }
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[BiblioMPS][procesar_devolucion] " . $e->getMessage());
    header("Location: gestionar_prestamos.php?msg=error_servidor");
    exit();
}
?>
