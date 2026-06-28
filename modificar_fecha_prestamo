<?php
// =============================================================================
// modificar_fecha_prestamo.php  —  BACKEND: EDITAR FECHA DE DEVOLUCIÓN (REQ-3)
// =============================================================================
// Propósito: Permite a Administrador o Bibliotecario modificar la
//            fecha_devolucion_esperada de un préstamo Activo.
//
// Reglas de negocio:
//   RN-01  Solo Administrador o Bibliotecario pueden ejecutar esta acción.
//   RN-02  El préstamo debe estar en estado 'Activo'.
//   RN-03  La nueva fecha debe ser >= hoy (no se puede poner fecha pasada).
//   RN-04  El campo renovaciones_realizadas NO se incrementa (es edición manual,
//          no renovación automática). Si deseas que cuente como renovación,
//          cambia el comentario en la sección 5c.
// Seguridad: CSRF, whitelist de roles, prepared statements, transacción ACID.
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php?msg=sin_sesion");
    exit();
}

// ── 2. Autorización: solo roles privilegiados [RN-01] ────────────────────────
$roles_permitidos = ['Administrador', 'Bibliotecario'];
if (!in_array($_SESSION['rol'] ?? '', $roles_permitidos, true)) {
    header("Location: dashboard.php?msg=sin_permiso");
    exit();
}

// ── 3. Solo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gestionar_prestamos.php');
    exit();
}

// ── 4. Validación CSRF ───────────────────────────────────────────────────────
$token_recibido = $_POST['csrf_token'] ?? '';
if (!csrf_valido($token_recibido)) {
    http_response_code(403);
    die("Solicitud rechazada: token CSRF inválido. <a href='gestionar_prestamos.php'>Volver</a>");
}

// ── 5. Sanitización y validación de inputs ───────────────────────────────────
$id_prestamo  = filter_input(INPUT_POST, 'id_prestamo',  FILTER_VALIDATE_INT);
$nueva_fecha  = trim($_POST['nueva_fecha_devolucion'] ?? '');

if (!$id_prestamo || $id_prestamo < 1) {
    header("Location: gestionar_prestamos.php?msg=params_invalidos");
    exit();
}

// Validar formato de fecha (YYYY-MM-DD)
$fecha_obj = DateTime::createFromFormat('Y-m-d', $nueva_fecha);
if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $nueva_fecha) {
    header("Location: gestionar_prestamos.php?msg=fecha_invalida");
    exit();
}

// [RN-03] La nueva fecha no puede ser anterior a hoy
if ($fecha_obj < new DateTime('today')) {
    header("Location: gestionar_prestamos.php?msg=fecha_pasada");
    exit();
}

// ── 6. Transacción principal ──────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Leer préstamo con bloqueo pesimista
    $stmt_get = $pdo->prepare(
        "SELECT id_prestamo, estado, fecha_devolucion_esperada, renovaciones_realizadas
         FROM prestamos
         WHERE id_prestamo = :id_prestamo
         FOR UPDATE"
    );
    $stmt_get->execute(['id_prestamo' => $id_prestamo]);
    $prestamo = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$prestamo) {
        $pdo->rollBack();
        header("Location: gestionar_prestamos.php?msg=prestamo_no_encontrado");
        exit();
    }

    // [RN-02] Solo préstamos activos
    if ($prestamo['estado'] !== 'Activo') {
        $pdo->rollBack();
        header("Location: gestionar_prestamos.php?msg=prestamo_no_activo");
        exit();
    }

    // Actualizar la fecha de devolución esperada
    // [RN-04] Edición manual: no incrementa renovaciones_realizadas.
    //         Si prefieres que sí cuente, cambia la línea de abajo a:
    //         renovaciones_realizadas = renovaciones_realizadas + 1
    $stmt_upd = $pdo->prepare(
        "UPDATE prestamos
         SET fecha_devolucion_esperada = :nueva_fecha
         WHERE id_prestamo = :id_prestamo"
    );
    $stmt_upd->execute([
        'nueva_fecha' => $nueva_fecha,
        'id_prestamo' => $id_prestamo,
    ]);

    $pdo->commit();

    $fecha_formateada = $fecha_obj->format('d/m/Y');
    header("Location: gestionar_prestamos.php?msg=fecha_actualizada&nueva_fecha=" . urlencode($fecha_formateada));
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[BiblioMPS][modificar_fecha_prestamo] " . $e->getMessage());
    header("Location: gestionar_prestamos.php?msg=error_servidor");
    exit();
}
?>
