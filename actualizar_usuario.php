<?php
// =============================================================================
// actualizar_usuario.php  —  EDICIÓN DE USUARIO EXISTENTE (MODAL ADMIN)
// =============================================================================
// Correcciones aplicadas:
//   [SEC-01] Verificación de sesión activa con $_SESSION['id_usuario'].
//   [SEC-02] Autorización estricta: comparación exacta con 'Administrador'
//            (eliminado strtolower() que permitía bypass con 'ADMINISTRADOR').
//   [SEC-03] Validación CSRF con hash_equals().
//   [VAL-01] htmlspecialchars ELIMINADO antes de UPDATE en BD.
//            El escape HTML ocurre solo en la VISTA al imprimir los datos.
//   [VAL-02] Validaciones completas: nombre, boleta (10 dígitos), correo,
//            id_usuario e id_rol como enteros positivos.
//   [LOG-01] PDOException registrada en log; mensaje genérico al usuario.
//   [CON-01] Flujo PRG consistente con el resto del proyecto.
// =============================================================================

session_start();
require_once 'config/db.php';

// ── 1. Autenticación [SEC-01] ────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit();
}

// ── 2. Autorización (comparación exacta, sin strtolower) [SEC-02] ────────────
// strtolower() fue eliminado porque permitía que un rol como 'ADMINISTRADOR'
// (en mayúsculas) se colara. La sesión siempre guarda el valor exacto
// de nombre_rol que viene de la BD.
if ($_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit();
}

// ── 3. Solo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit();
}

// ── 4. Validación CSRF [SEC-03] ──────────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    header('Location: usuarios.php?error=csrf');
    exit();
}

/**
 * Redirige a usuarios.php con un mensaje de error y termina la ejecución.
 */
function redirigirError(string $mensaje): void
{
    header('Location: usuarios.php?error=1&msg=' . urlencode($mensaje));
    exit();
}

// ── 5. Sanitización de texto [VAL-01] ────────────────────────────────────────
// strip_tags() + trim() limpian el input sin corromper los datos con &amp; etc.
// htmlspecialchars() NO se usa aquí: los datos se guardan en crudo en la BD.
$nombre     = trim(strip_tags($_POST['nombre']  ?? ''));
$boleta     = trim(strip_tags($_POST['boleta']  ?? ''));
$id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$id_rol     = filter_input(INPUT_POST, 'id_rol',     FILTER_VALIDATE_INT);

// filter_var SANITIZE elimina caracteres ilegales del correo antes de validarlo.
$correo = filter_var($_POST['correo'] ?? '', FILTER_SANITIZE_EMAIL);

// ── 6. Validaciones de negocio [VAL-02] ──────────────────────────────────────

// 6a. ID de usuario: entero positivo obligatorio
if (!$id_usuario || $id_usuario < 1) {
    redirigirError('El ID de usuario no es válido.');
}

// 6b. Nombre: solo letras (con acentos y ñ) y espacios
if ($nombre === '' || mb_strlen($nombre) > 100) {
    redirigirError('El nombre es obligatorio y debe tener máximo 100 caracteres.');
}
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ ]+$/u', $nombre)) {
    redirigirError('Nombre inválido: solo se permiten letras y espacios.');
}

// 6c. Boleta: exactamente 10 dígitos numéricos
if (!preg_match('/^\d{10}$/', $boleta)) {
    redirigirError('La boleta debe tener exactamente 10 dígitos numéricos.');
}

// 6d. Correo: formato válido
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    redirigirError('El correo electrónico no tiene un formato válido.');
}
if (mb_strlen($correo) > 150) {
    redirigirError('El correo no puede superar 150 caracteres.');
}

// 6e. Rol: entero positivo en rango esperado
if (!$id_rol || $id_rol < 1 || $id_rol > 10) {
    redirigirError('El rol seleccionado no es válido.');
}

// ── 7. Verificar que el usuario a editar exista en la BD ─────────────────────
// Evita actualizaciones silenciosas sobre IDs inventados.
try {
    $stmt_chk = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = :id LIMIT 1');
    $stmt_chk->execute([':id' => $id_usuario]);
    if (!$stmt_chk->fetch()) {
        redirigirError('El usuario que intentas editar no existe.');
    }
} catch (PDOException $e) {
    error_log('[BiblioMPS][actualizar_usuario] verificación: ' . $e->getMessage());
    redirigirError('Error interno al verificar el usuario. Inténtalo de nuevo.');
}

// ── 8. Actualización en BD ───────────────────────────────────────────────────
try {
    $sql = "UPDATE usuarios
            SET nombre    = :nombre,
                boleta    = :boleta,
                correo    = :correo,
                id_rol    = :id_rol
            WHERE id_usuario = :id_usuario";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'      => $nombre,
        ':boleta'      => $boleta,
        ':correo'      => $correo,
        ':id_rol'      => $id_rol,
        ':id_usuario'  => $id_usuario,
    ]);

    // Éxito → invalidar el token para que no pueda reusarse
    unset($_SESSION['csrf_token']);

    header('Location: usuarios.php?update=exito&msg=' . urlencode('Datos del usuario actualizados correctamente.'));
    exit();

} catch (PDOException $e) {
    // [LOG-01] El detalle técnico va al log del servidor, nunca al navegador.
    error_log('[BiblioMPS][actualizar_usuario] ' . $e->getMessage());

    if ($e->getCode() == 23000) {
        // Violación UNIQUE: boleta o correo ya pertenecen a otro usuario
        redirigirError('La boleta o el correo ya está registrado en otro usuario del sistema.');
    }

    redirigirError('Error interno al actualizar el usuario. Inténtalo de nuevo.');
}
