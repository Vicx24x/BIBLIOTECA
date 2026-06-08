<?php
// config/db.php

// ==============================================================================
// CONFIGURACIÓN DE LA BASE DE DATOS (DETECTA ENTORNO)
// ==============================================================================

// Si estamos en la nube de Azure, usará las variables de entorno que configuramos.
// Si no encuentra variables de entorno, usará tus datos de XAMPP (localhost).
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'biblioteca_mps';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

// ==============================================================================
// CREACIÓN DE LA CONEXIÓN (PDO)
// ==============================================================================

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    // Líneas agregadas para pasar la seguridad de Azure:
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    PDO::MYSQL_ATTR_SSL_CA       => '/etc/ssl/certs/ca-certificates.crt',
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si falla, mostramos un mensaje genérico. 
    // En desarrollo puedes dejar $e->getMessage() para ver el error real.
    die("❌ Error de conexión: " . $e->getMessage());
}
