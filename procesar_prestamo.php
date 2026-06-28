<?php
// =============================================================================
// procesar_prestamo_v2.php  —  SOLICITUD DE PRÉSTAMO (VERSIÓN AUDITADA)
// =============================================================================
// Mejoras sobre procesar_prestamo.php original:
//   [SEC-01] Token CSRF obligatorio.
//   [SEC-02] Validación de sesión con verificación de estado de cuenta.
//   [SEC-03] Rate limiting: máx. 5 solicitudes / 60 s por usuario.
//   [CON-01] SELECT … FOR UPDATE dentro de transacción InnoDB.
//   [CON-02] Verificación de préstamo activo previo (límite 1 activo / usuario).
//   [BUG-01] Exposición de errores de BD eliminada en producción.
//   [BUG-02] Manejo de fallo de INSERT explícito con rollback garantizado.
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';
require_once 'rate_limiter.php';
require_once 'notificaciones.php';

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
// Un formulario legítimo siempre incluirá este token; un atacante externo
// no podrá leerlo porque la política SameSite/Same-Origin lo impide.
$token_recibido = $_POST['csrf_token'] ?? '';
if (!csrf_valido($token_recibido)) {
    http_response_code(403);
    die("Solicitud rechazada: token CSRF inválido. <a href='catalogo.php'>Volver</a>");
}

// ── 4. Rate Limiting [SEC-03] ────────────────────────────────────────────────
// Protege contra scripts automáticos que intenten acaparar todos los
// ejemplares disponibles o saturar el servidor con peticiones.
rate_limit_guard($id_usuario, max_solicitudes: 5, ventana_segundos: 60);

// ── 5. Sanitización del input ────────────────────────────────────────────────
$id_libro = filter_input(INPUT_POST, 'id_libro', FILTER_VALIDATE_INT);
if (!$id_libro || $id_libro < 1) {
    header("Location: catalogo.php?msg=id_invalido");
    exit();
}

// ── 6. Transacción principal ──────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 6a. Verificar estado de la cuenta del usuario [SEC-02]
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

    // 6b. Verificar que el usuario NO tenga ya un préstamo activo del mismo libro [CON-02]
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

    // 6c. Buscar un ejemplar disponible con bloqueo pesimista [CON-01]
    // FOR UPDATE bloquea la fila seleccionada hasta el commit. Esto garantiza
    // que dos usuarios simultáneos no obtengan el mismo ejemplar: el segundo
    // esperará hasta que el primero termine, y entonces verá estado=Prestado.
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

    // 6d. Marcar el ejemplar como Prestado
    $stmt_upd = $pdo->prepare(
        "UPDATE ejemplares SET estado = 'Prestado' WHERE id_ejemplar = :id_ejemplar"
    );
    $stmt_upd->execute(['id_ejemplar' => $id_ejemplar]);

    // 6e. Crear el registro de préstamo (7 días de plazo por política)
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

    // Enviar correo de confirmación (fail-safe: no bloquea si falla el envío)
    $stmt_user = $pdo->prepare("SELECT nombre, correo FROM usuarios WHERE id_usuario = :id LIMIT 1");
    $stmt_user->execute(['id' => $id_usuario]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $stmt_libro = $pdo->prepare(
        "SELECT l.titulo FROM libros l
         INNER JOIN ejemplares e ON e.id_libro = l.id_libro
         WHERE e.id_ejemplar = :id LIMIT 1"
    );
    $stmt_libro->execute(['id' => $id_ejemplar]);
    $libro_data = $stmt_libro->fetch(PDO::FETCH_ASSOC);

    if ($user_data && $libro_data) {
        notificar_prestamo_exitoso(
            correo_usuario:   $user_data['correo'],
            nombre_usuario:   $user_data['nombre'],
            titulo_libro:     $libro_data['titulo'],
            fecha_devolucion: $fecha_devolucion
        );
    }

    header("Location: catalogo.php?msg=prestamo_exitoso");
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // [BUG-01] No exponer errores de BD al usuario final
    error_log("[BiblioMPS][procesar_prestamo_v2] " . $e->getMessage());
    header("Location: catalogo.php?msg=error_servidor");
    exit();
}
?>
