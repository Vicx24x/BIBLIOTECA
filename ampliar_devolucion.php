<?php
// =============================================================================
// ampliar_devolucion.php  —  MÓDULO DE RENOVACIÓN DE PRÉSTAMO
// =============================================================================
// Propósito  : Extiende la fecha límite de un préstamo activo.
// Reglas de negocio:
//   RN-01  Solo el propietario del préstamo O un Bibliotecario/Admin puede renovar.
//   RN-02  Máximo 1 renovación por préstamo (campo renovaciones_realizadas).
//   RN-03  No se puede renovar si el préstamo ya está vencido.
//   RN-04  La nueva fecha = fecha_devolucion_esperada + 7 días.
// Seguridad : Token CSRF, verificación de ownership, prepared statements.
// =============================================================================

session_start();
require_once 'config/db.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'msg' => 'Sesión requerida.']));
}

// ── 2. Sólo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: prestamos.php');
    exit();
}

// ── 3. Validación CSRF ───────────────────────────────────────────────────────
// Vector de ataque: Cross-Site Request Forgery. Un sitio externo podría
// hacer que el navegador del usuario logueado envíe una petición de renovación
// sin su conocimiento. El token aleatorio, vinculado a la sesión, lo impide.
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    http_response_code(403);
    die("Solicitud no autorizada (CSRF). <a href='prestamos.php'>Volver</a>");
}

// ── 4. Sanitización de entrada ───────────────────────────────────────────────
$id_prestamo  = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
$id_rol_sesion = $_SESSION['rol'] ?? 'Usuario';

if (!$id_prestamo || $id_prestamo < 1) {
    die("ID de préstamo inválido. <a href='prestamos.php'>Volver</a>");
}

// ── 5. Lógica principal con transacción ──────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Bloqueo pesimista (SELECT … FOR UPDATE): impide que otra petición
    // concurrente lea el mismo registro en un estado inconsistente.
    $sql_get = "SELECT id_prestamo, id_usuario, fecha_devolucion_esperada,
                       estado, renovaciones_realizadas
                FROM prestamos
                WHERE id_prestamo = :id_prestamo
                FOR UPDATE";
    $stmt_get = $pdo->prepare($sql_get);
    $stmt_get->execute(['id_prestamo' => $id_prestamo]);
    $prestamo = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$prestamo) {
        $pdo->rollBack();
        die("Préstamo no encontrado. <a href='prestamos.php'>Volver</a>");
    }

    // RN-01 Verificación de ownership
    $es_admin_o_biblio = in_array($id_rol_sesion, ['Administrador', 'Bibliotecario']);
    if (!$es_admin_o_biblio && (int)$prestamo['id_usuario'] !== (int)$_SESSION['id_usuario']) {
        $pdo->rollBack();
        http_response_code(403);
        die("No tienes permiso para renovar este préstamo. <a href='prestamos.php'>Volver</a>");
    }

    // RN-02 Límite de renovaciones
    if ((int)$prestamo['renovaciones_realizadas'] >= 1) {
        $pdo->rollBack();
        header("Location: prestamos.php?msg=max_renovaciones");
        exit();
    }

    // RN-03 No renovar préstamos vencidos
    if ($prestamo['estado'] !== 'Activo') {
        $pdo->rollBack();
        header("Location: prestamos.php?msg=estado_invalido");
        exit();
    }
    if (new DateTime() > new DateTime($prestamo['fecha_devolucion_esperada'])) {
        $pdo->rollBack();
        header("Location: prestamos.php?msg=prestamo_vencido");
        exit();
    }

    // RN-04 Calcular nueva fecha
    $nueva_fecha = (new DateTime($prestamo['fecha_devolucion_esperada']))
                      ->modify('+7 days')
                      ->format('Y-m-d');

    $sql_update = "UPDATE prestamos
                   SET fecha_devolucion_esperada = :nueva_fecha,
                       renovaciones_realizadas    = renovaciones_realizadas + 1
                   WHERE id_prestamo = :id_prestamo";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        'nueva_fecha'  => $nueva_fecha,
        'id_prestamo'  => $id_prestamo,
    ]);

    $pdo->commit();
    header("Location: prestamos.php?msg=renovacion_exitosa&nueva_fecha=" . urlencode($nueva_fecha));
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    // En producción: loguear $e->getMessage() en archivo de log, no mostrarlo.
    error_log("[BiblioMPS][ampliar_devolucion] " . $e->getMessage());
    header("Location: prestamos.php?msg=error_renovacion");
    exit();
}
?>
