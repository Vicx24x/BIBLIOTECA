<?php
// =============================================================================
// guardar_usuario.php  —  ALTA DE NUEVO USUARIO (PANEL ADMINISTRADOR)
// =============================================================================
// Correcciones aplicadas:
//   [SEC-01] Autenticación + autorización: solo el Administrador puede crear usuarios.
//   [SEC-02] CSRF validado con hash_equals().
//   [VAL-01] htmlspecialchars ELIMINADO antes de insertar en BD (corrompe datos).
//            El escape HTML ocurre únicamente en la VISTA al imprimir.
//   [VAL-02] Validaciones de negocio completas: nombre (solo letras/espacios),
//            boleta (exactamente 10 dígitos), correo válido, rol en rango,
//            contraseña con mínimo de 8 caracteres.
//   [LOG-01] PDOException registrada en log; mensaje genérico al usuario.
//   [CON-01] Flujo consistente con index.php: PRG (Post/Redirect/Get) con
//            mensajes via parámetros de URL.
// =============================================================================

session_start();
require_once 'config/db.php';

// ── 1. Autenticación [SEC-01] ────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit();
}

// ── 2. Autorización (whitelist de roles) [SEC-01] ────────────────────────────
if ($_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit();
}

// ── 3. Solo POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit();
}

// ── 4. Validación CSRF [SEC-02] ──────────────────────────────────────────────
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

// ── 5. Recepción y sanitización de texto [VAL-01] ────────────────────────────
// trim() elimina espacios innecesarios. strip_tags() elimina etiquetas HTML.
// NO se usa htmlspecialchars() aquí: los datos se guardan en crudo en la BD
// y se escapan solo en el momento de imprimirlos en HTML (en la vista).
$nombre         = trim(strip_tags($_POST['nombre']         ?? ''));
$boleta         = trim(strip_tags($_POST['boleta']         ?? ''));
$password_plana = $_POST['password'] ?? '';
$id_rol         = filter_input(INPUT_POST, 'id_rol', FILTER_VALIDATE_INT);

// filter_var para correo: SANITIZE elimina caracteres ilegales sin validar;
// la validación real la hace FILTER_VALIDATE_EMAIL a continuación.
$correo = filter_var($_POST['correo'] ?? '', FILTER_SANITIZE_EMAIL);

// ── 6. Validaciones de negocio [VAL-02] ──────────────────────────────────────

// 6a. Nombre: solo letras (incluye acentos y ñ) y espacios
if ($nombre === '' || mb_strlen($nombre) > 100) {
    redirigirError('El nombre es obligatorio y debe tener máximo 100 caracteres.');
}
if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ ]+$/u', $nombre)) {
    redirigirError('Nombre inválido: solo se permiten letras y espacios (sin números ni símbolos).');
}

// 6b. Boleta: exactamente 10 dígitos numéricos
if (!preg_match('/^\d{10}$/', $boleta)) {
    redirigirError('La boleta debe tener exactamente 10 dígitos numéricos.');
}

// 6c. Correo: formato válido
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    redirigirError('El correo electrónico proporcionado no tiene un formato válido.');
}
if (mb_strlen($correo) > 150) {
    redirigirError('El correo no puede superar 150 caracteres.');
}

// 6d. Contraseña: mínimo 8 caracteres
if (mb_strlen($password_plana) < 8) {
    redirigirError('La contraseña debe tener al menos 8 caracteres.');
}

// 6e. Rol: entero positivo (rango esperado 1–3)
if (!$id_rol || $id_rol < 1 || $id_rol > 10) {
    redirigirError('El rol seleccionado no es válido.');
}

// ── 7. Hash de contraseña ─────────────────────────────────────────────────────
$password_encriptada = password_hash($password_plana, PASSWORD_DEFAULT);

// ── 8. Inserción en BD ───────────────────────────────────────────────────────
try {
    $sql = "INSERT INTO usuarios (nombre, boleta, correo, password, id_rol, estado)
            VALUES (:nombre, :boleta, :correo, :password, :id_rol, 'Activo')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre'   => $nombre,
        ':boleta'   => $boleta,
        ':correo'   => $correo,
        ':password' => $password_encriptada,
        ':id_rol'   => $id_rol,
    ]);

    // Éxito → invalidar el token para que no pueda reusarse
    unset($_SESSION['csrf_token']);

    header('Location: usuarios.php?registro=exito&msg=' . urlencode('Usuario registrado correctamente.'));
    exit();

} catch (PDOException $e) {
    // [LOG-01] El detalle técnico va al log del servidor, no al navegador.
    error_log('[BiblioMPS][guardar_usuario] ' . $e->getMessage());

    if ($e->getCode() == 23000) {
        // Violación UNIQUE: boleta o correo duplicados
        redirigirError('La boleta o el correo ya se encuentran registrados en el sistema.');
    }

    redirigirError('Error interno al registrar el usuario. Inténtalo de nuevo.');
}

