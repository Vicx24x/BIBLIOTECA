<?php
// =============================================================================
// procesar_prestamo.php  —  SOLICITUD DE PRÉSTAMO (VERSIÓN LIMPIA Y SEGURA)
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.php?msg=sin_sesion");
    exit();
}

$id_usuario = (int)$_SESSION['id_usuario'];

// ── 2. Sólo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: catalogo.php");
    exit();
}

// ── 3. Validación CSRF [SEC-01] ──────────────────────────────────────────────
$token_recibido = $_POST['csrf_token'] ?? '';
if (!csrf_valido($token_recibido)) {
    http_response_code(403);
    die("Solicitud rechazada: token CSRF inválido. <a href='catalogo.php'>Volver</a>");
}

// ── 4. Sanitización del input ────────────────────────────────────────────────
$id_libro = filter_input(INPUT_POST, 'id_libro', FILTER_VALIDATE_INT);
if (!$id_libro || $id_libro < 1) {
    header("Location: catalogo.php?msg=id_invalido");
    exit();
}

// ── 5. Transacción principal ──────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 5a. Verificar estado de la cuenta del usuario [SEC-02]
    $stmt_usr = $pdo->prepare(
        "SELECT estado FROM usuarios WHERE id_usuario = :id LIMIT 1"
    );
    $stmt_usr->execute(['id' => $id_usuario]);
    $usr = $stmt_usr->fetch(PDO::FETCH_ASSOC);

    if (!$usr || $usr['estado'] !== 'Activo') {
        $pdo->rollBack();
        header("Location: catalogo.php?msg=cuenta_inactiva");
        exit();
    }

    // 5b. Verificar que el usuario NO tenga ya un préstamo activo del mismo libro [CON-02]
    $stmt_dup = $pdo->prepare(
        "SELECT COUNT(*) FROM prestamos p
         INNER JOIN ejemplares e ON p.id_ejemplar = e.id_ejemplar
         WHERE p.id_usuario = :id_usuario
           AND e.id_libro   = :id_libro
           AND p.estado     = 'Activo'"
    );
    $stmt_dup->execute(['id_usuario' => $id_usuario, 'id_libro' => $id_libro]);
    if ((int)$stmt_dup->fetchColumn() > 0) {
        $pdo->rollBack();
        header("Location: catalogo.php?msg=prestamo_duplicado");
        exit();
    }

    // 5c. Buscar un ejemplar disponible con bloqueo pesimista [CON-01]
    $stmt_ver = $pdo->prepare(
        "SELECT id_ejemplar FROM ejemplares
         WHERE id_libro = :id_libro
           AND estado   = 'Disponible'
         LIMIT 1
         FOR UPDATE"
    );
    $stmt_ver->execute(['id_libro' => $id_libro]);
    $ejemplar = $stmt_ver->fetch(PDO::FETCH_ASSOC);

    if (!$ejemplar) {
        $pdo->rollBack();
        header("Location: catalogo.php?msg=sin_stock");
        exit();
    }

    $id_ejemplar = (int)$ejemplar['id_ejemplar'];

    // 5d. Marcar el ejemplar como Prestado
    $stmt_upd = $pdo->prepare(
        "UPDATE ejemplares SET estado = 'Prestado' WHERE id_ejemplar = :id_ejemplar"
    );
    $stmt_upd->execute(['id_ejemplar' => $id_ejemplar]);

    // 5e. Crear el registro de préstamo (7 días de plazo por política)
    $hoy              = date('Y-m-d');
    $fecha_devolucion = date('Y-m-d', strtotime($hoy . ' +7 days'));

    $stmt_ins = $pdo->prepare(
        "INSERT INTO prestamos
             (id_usuario, id_ejemplar, fecha_prestamo, fecha_devolucion_esperada,
              estado, renovaciones_realizadas)
         VALUES
             (:id_usuario, :id_ejemplar, :fecha_prestamo, :fecha_devolucion,
              'Activo', 0)"
    );
    $stmt_ins->execute([
        'id_usuario'      => $id_usuario,
        'id_ejemplar'     => $id_ejemplar,
        'fecha_prestamo'  => $hoy,
        'fecha_devolucion'=> $fecha_devolucion,
    ]);

    $pdo->commit();

    header("Location: catalogo.php?msg=prestamo_exitoso");
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // No exponer errores de BD al usuario final
    error_log("[BiblioMPS][procesar_prestamo] " . $e->getMessage());
    header("Location: catalogo.php?msg=error_servidor");
    exit();
}
?>
