<?php
// csrf_helper.php
// Genera y valida tokens de seguridad para evitar ataques (Cross-Site Request Forgery)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si el usuario no tiene un token de seguridad, le creamos uno aleatorio
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Función para verificar que el token del botón coincida con el de la sesión
 */
function csrf_valido($token_recibido) {
    if (empty($_SESSION['csrf_token']) || empty($token_recibido)) {
        return false;
    }
    // Compara ambos tokens de manera segura
    return hash_equals($_SESSION['csrf_token'], $token_recibido);
}
?>
