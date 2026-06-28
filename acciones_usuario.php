<?php
// =============================================================================
// acciones_usuario.php  —  TOGGLE DE ESTADO DE CUENTA (ACTIVO / INACTIVO)
// =============================================================================
// Correcciones aplicadas:
//   [SEC-01] session_start() + verificación de sesión activa.
//   [SEC-02] Whitelist de rol: solo Administrador puede ejecutar esta acción.
//   [SEC-03] Validación de token CSRF con hash_equals().
//   [SEC-04] Protección de auto-bloqueo: el admin no puede desactivar su propia cuenta.
//   [SEC-05] Parámetros PDO named en lugar de posicionales.
//   [LOG-01] PDOException registrada en log, nunca expuesta al usuario.
// =============================================================================

session_start();
require_once 'config/db.php';

// ── 1. Autenticación ─────────────────────────────────────────────────────────
if (!isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit();
}

// ── 2. Autorización (whitelist de roles) [SEC-02] ───────────────────────────
if ($_SESSION['rol'] !== 'Administrador') {
    header('Location: dashboard.php');
    exit();
}

// ── 3. Solo POST [SEC-01] ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: usuarios.php');
    exit();
}

// ── 4. Validación CSRF [SEC-03] ──────────────────────────────────────────────
// El token vincula esta petición a la sesión activa; un atacante externo
// no puede leer ni reproducir el valor del campo oculto.
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    header('Location: usuarios.php?error=csrf');
    exit();
}

// ── 5. Sanitización del ID [SEC-05] ──────────────────────────────────────────
$id_usuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

if (!$id_usuario || $id_usuario < 1) {
    header('Location: usuarios.php?error=id_invalido');
    exit();
}

// ── 6. Protección de auto-bloqueo [SEC-04] ───────────────────────────────────
// Evita que el administrador desactive su propia cuenta y quede sin acceso.
if ($id_usuario === (int) $_SESSION['id_usuario']) {
    header('Location: usuarios.php?error=self_lock');
    exit();
}

// ── 7. Lógica de toggle ──────────────────────────────────────────────────────
try {
    // 7a. Leer estado actual
    $stmt = $pdo->prepare(
        'SELECT estado FROM usuarios WHERE id_usuario = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id_usuario]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        // El ID no existe en la BD
        header('Location: usuarios.php?error=not_found');
        exit();
    }

    // 7b. Calcular nuevo estado
    $nuevo_estado = ($usuario['estado'] === 'Activo') ? 'Inactivo' : 'Activo';

    // 7c. Actualizar
    $update = $pdo->prepare(
        'UPDATE usuarios SET estado = :estado WHERE id_usuario = :id'
    );
    $update->execute([
        ':estado' => $nuevo_estado,
        ':id'     => $id_usuario,
    ]);

    header('Location: usuarios.php?update=exito');
    exit();

} catch (PDOException $e) {
    // [LOG-01] Solo el log interno recibe el detalle del error de BD.
    error_log('[BiblioMPS][acciones_usuario] ' . $e->getMessage());
    header('Location: usuarios.php?error=db');
    exit();
}
