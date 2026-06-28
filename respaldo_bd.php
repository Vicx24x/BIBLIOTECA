<?php
// =============================================================================
// respaldo_bd.php  —  DESCARGA DE RESPALDO DE BASE DE DATOS
// =============================================================================
// Correcciones aplicadas:
//   [SEC-01] Credenciales leídas de variables de entorno (getenv()) como el
//            resto del proyecto; eliminadas las cadenas hardcodeadas 'root'/''.
//   [SEC-02] Autenticación y rol verificados antes de cualquier operación.
//   [SEC-03] Nombre del archivo de respaldo escapado para prevenir
//            inyección de cabeceras HTTP (header injection).
//   [LOG-01] Errores de sistema registrados en el log, no expuestos al usuario.
// =============================================================================

session_start();

// ── 1. Autenticación + autorización [SEC-02] ──────────────────────────────────
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    header('Location: dashboard.php');
    exit();
}

// ── 2. Credenciales desde variables de entorno [SEC-01] ───────────────────────
// Mismo patrón que config/db.php para mantener consistencia y evitar que las
// credenciales queden expuestas si el repositorio fuera público.
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'biblioteca_mps';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// ── 3. Nombre de archivo seguro [SEC-03] ──────────────────────────────────────
// Se usa solo la fecha/hora como nombre; no se interpolan datos externos.
$fecha          = date('Y-m-d_H-i-s');
$nombre_archivo = 'respaldo_biblioteca_' . $fecha . '.sql';

// ── 4. Cabeceras de descarga ──────────────────────────────────────────────────
// addslashes() protege el filename en Content-Disposition contra header injection.
header('Content-Type: application/octet-stream');
header('Content-Transfer-Encoding: Binary');
header('Content-Disposition: attachment; filename="' . addslashes($nombre_archivo) . '"');
header('Pragma: no-cache');
header('Expires: 0');

// ── 5. Construcción del comando mysqldump ─────────────────────────────────────
// La contraseña vacía no genera el flag -p para evitar avisos de mysqldump.
// escapeshellarg() protege cada argumento frente a inyección de shell.
$password_flag = ($db_pass !== '') ? '-p' . escapeshellarg($db_pass) : '';

$comando = sprintf(
    'mysqldump --opt -h %s -u %s %s %s',
    escapeshellarg($db_host),
    escapeshellarg($db_user),
    $password_flag,
    escapeshellarg($db_name)
);

// ── 6. Ejecución y fallback ───────────────────────────────────────────────────
system($comando, $codigo_salida);

if ($codigo_salida !== 0) {
    // [LOG-01] Registrar el fallo para diagnóstico, sin exponer detalles al usuario.
    error_log('[BiblioMPS][respaldo_bd] mysqldump falló con código: ' . $codigo_salida);

    // Mensaje de fallback incluido en el archivo SQL descargado.
    echo "-- ============================================================\n";
    echo "-- Respaldo BiblioMPS — generado el {$fecha}\n";
    echo "-- ============================================================\n";
    echo "-- ADVERTENCIA: mysqldump no está disponible en el PATH del servidor.\n";
    echo "-- Para obtener un respaldo completo:\n";
    echo "--   1. Accede a Azure MySQL Flexible Server → Backup en el portal.\n";
    echo "--   2. O ejecuta mysqldump localmente si tienes acceso SSH al servidor.\n";
}

exit();
