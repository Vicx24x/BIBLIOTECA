<?php
// =============================================================================
// csrf_helper.php  —  GENERACIÓN Y VALIDACIÓN DE TOKENS CSRF
// =============================================================================
// Incluir con: require_once 'csrf_helper.php';
// Genera un token una vez por sesión; lo regenera si expira (24h).
// =============================================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Genera (o recupera) el token CSRF de la sesión actual.
 * Lo rota cada 24 horas para limitar la ventana de ataque.
 *
 * @return string Token CSRF hexadecimal de 64 caracteres.
 */
function csrf_token(): string {
    $ahora = time();

    // Rotar si no existe o lleva más de 24 h
    if (
        empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_ts']) ||
        ($ahora - (int)$_SESSION['csrf_token_ts']) > 86400
    ) {
        $_SESSION['csrf_token']    = bin2hex(random_bytes(32)); // 256 bits de entropía
        $_SESSION['csrf_token_ts'] = $ahora;
    }

    return $_SESSION['csrf_token'];
}

/**
 * Valida el token enviado en POST contra el de la sesión.
 * Usa comparación de tiempo constante (hash_equals) para prevenir
 * ataques de timing que inferirían el token carácter a carácter.
 *
 * @param  string $token_enviado  Token recibido del formulario.
 * @return bool
 */
function csrf_valido(string $token_enviado): bool {
    return !empty($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token_enviado);
}

/**
 * Emite el campo oculto HTML con el token CSRF.
 * Usar dentro de cualquier <form> que realice cambios de estado.
 *
 * @return string HTML del input hidden.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
           . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
           . '">';
}
?>
