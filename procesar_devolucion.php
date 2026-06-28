<?php
// =============================================================================
// procesar_devolucion_v2.php  —  DEVOLUCIÓN + ENTREGA EXTEMPORÁNEA
// =============================================================================
// Sustituye a procesar_devolucion.php.
// Cambios clave:
//   • Detecta automáticamente entregas fuera de tiempo (extemporáneas).
//   • Calcula días de atraso con DATEDIFF para precisión exacta.
//   • Registra la morosidad en la tabla `morosidad` si aplica.
//   • Actualiza fecha_devolucion_real con timestamp de la devolución.
//   • Protección CSRF + verificación de ownership del préstamo.
//   • Transacción ACID con bloqueo FOR UPDATE.
// =============================================================================

session_start();
require_once 'config/db.php';
require_once 'csrf_helper.php';
require_once 'notificaciones.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(403);
    die("Acceso denegado. <a href='index.php'>Iniciar sesión</a>");
}

// ── 2. Solo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: gestionar_prestamos.php');
    exit();
}

// ── 3. Validación CSRF ───────────────────────────────────────────────────────
// Vector de ataque: CSRF permite que un atacante fuerce una devolución falsa
// desde otro dominio usando la sesión activa de la víctima. El token CSRF
// vincula la acción a una sesión específica, haciendo el ataque imposible.
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    http_response_code(403);
    die("Solicitud no autorizada (token CSRF inválido).");
}

// ── 4. Sanitización ──────────────────────────────────────────────────────────
$id_prestamo = filter_input(INPUT_POST, 'id_prestamo', FILTER_VALIDATE_INT);
$id_ejemplar = filter_input(INPUT_POST, 'id_ejemplar', FILTER_VALIDATE_INT);

if (!$id_prestamo || !$id_ejemplar || $id_prestamo < 1 || $id_ejemplar < 1) {
    die("Parámetros inválidos. <a href='gestionar_prestamos.php'>Volver</a>");
}

$id_rol      = $_SESSION['rol']       ?? 'Usuario';
$id_sesion   = (int)$_SESSION['id_usuario'];
$es_privil   = in_array($id_rol, ['Administrador', 'Bibliotecario']);

// ── 5. Transacción principal ──────────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // 5a. Leer préstamo con bloqueo pesimista
    // FOR UPDATE garantiza que ninguna otra transacción concurrente lea ni
    // modifique este registro hasta que hagamos commit o rollback.
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
        die("Préstamo #$id_prestamo no encontrado.");
    }
    if ($prestamo['estado'] !== 'Activo') {
        $pdo->rollBack();
        header("Location: gestionar_prestamos.php?msg=ya_devuelto");
        exit();
    }
    if ((int)$prestamo['id_ejemplar'] !== $id_ejemplar) {
        $pdo->rollBack();
        die("Discrepancia de ejemplar: el ID no corresponde al préstamo.");
    }

    // Solo el owner o un privilegiado puede registrar la devolución
    if (!$es_privil && (int)$prestamo['id_usuario'] !== $id_sesion) {
        $pdo->rollBack();
        http_response_code(403);
        die("No tienes permiso para registrar esta devolución.");
    }

    // 5c. Calcular extemporaneidad
    $hoy                    = new DateTime('today');
    $fecha_limite           = new DateTime($prestamo['fecha_devolucion_esperada']);
    $dias_atraso            = 0;
    $es_extemporanea        = false;

    if ($hoy > $fecha_limite) {
        $es_extemporanea = true;
        $diff            = $fecha_limite->diff($hoy);
        $dias_atraso     = (int)$diff->days; // diferencia exacta en días
    }

    $fecha_devolucion_real = $hoy->format('Y-m-d');

    // 5d. Actualizar préstamo a 'Devuelto' con fecha real
    $nuevo_estado = $es_extemporanea ? 'Extemporáneo' : 'Devuelto';

    $stmt_p = $pdo->prepare(
        "UPDATE prestamos
         SET estado                = :estado,
             fecha_devolucion_real = :fecha_real,
             dias_atraso           = :dias_atraso
         WHERE id_prestamo = :id_prestamo"
    );
    $stmt_p->execute([
        'estado'       => $nuevo_estado,
        'fecha_real'   => $fecha_devolucion_real,
        'dias_atraso'  => $dias_atraso,
        'id_prestamo'  => $id_prestamo,
    ]);

    // 5e. Liberar el ejemplar físico
    $stmt_e = $pdo->prepare(
        "UPDATE ejemplares SET estado = 'Disponible' WHERE id_ejemplar = :id_ejemplar"
    );
    $stmt_e->execute(['id_ejemplar' => $id_ejemplar]);

    // 5f. Registrar morosidad si aplica
    // La tabla `morosidad` sirve como registro histórico disciplinario.
    if ($es_extemporanea) {
        $stmt_mor = $pdo->prepare(
            "INSERT INTO morosidad
                (id_prestamo, id_usuario, dias_atraso, fecha_registro)
             VALUES
                (:id_prestamo, :id_usuario, :dias_atraso, CURRENT_DATE)
             ON DUPLICATE KEY UPDATE
                dias_atraso     = VALUES(dias_atraso),
                fecha_registro  = VALUES(fecha_registro)"
        );
        $stmt_mor->execute([
            'id_prestamo' => $id_prestamo,
            'id_usuario'  => (int)$prestamo['id_usuario'],
            'dias_atraso' => $dias_atraso,
        ]);
    }

    $pdo->commit();

    // 5g. Enviar correo de notificación (tras el commit, fail-safe)
    $stmt_user = $pdo->prepare(
        "SELECT nombre, correo FROM usuarios WHERE id_usuario = :id LIMIT 1"
    );
    $stmt_user->execute(['id' => (int)$prestamo['id_usuario']]);
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $stmt_libro = $pdo->prepare(
        "SELECT l.titulo FROM libros l
         INNER JOIN ejemplares e ON e.id_libro = l.id_libro
         WHERE e.id_ejemplar = :id LIMIT 1"
    );
    $stmt_libro->execute(['id' => $id_ejemplar]);
    $libro_data = $stmt_libro->fetch(PDO::FETCH_ASSOC);

    if ($user_data && $libro_data && $es_extemporanea) {
        notificar_devolucion_extemporanea(
            correo_usuario: $user_data['correo'],
            nombre_usuario: $user_data['nombre'],
            titulo_libro:   $libro_data['titulo'],
            dias_atraso:    $dias_atraso,
            fecha_limite:   $prestamo['fecha_devolucion_esperada']
        );
    }

    // 5h. Redirección con contexto
    if ($es_extemporanea) {
        header("Location: gestionar_prestamos.php?msg=devolucion_extemporanea&dias=$dias_atraso");
    } else {
        header("Location: gestionar_prestamos.php?msg=devolucion_exitosa");
    }
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("[BiblioMPS][procesar_devolucion_v2] " . $e->getMessage());
    header("Location: gestionar_prestamos.php?msg=error_devolucion");
    exit();
}
?>
