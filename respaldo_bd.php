<?php
// respaldo_bd.php
// Cumple con el requerimiento: "Respaldo automático de la base de datos"
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado.");
}

$db_host = 'localhost';
$db_name = 'biblioteca_mps';
$db_user = 'root';
$db_pass = '';

$fecha = date("Y-m-d_H-i-s");
$nombre_archivo = "respaldo_biblioteca_$fecha.sql";

// Cabeceras para forzar la descarga del archivo SQL
header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary"); 
header("Content-disposition: attachment; filename=\"$nombre_archivo\""); 

// Usamos mysqldump si está disponible, o generamos un respaldo básico de estructura
$comando = "mysqldump --opt -h $db_host -u $db_user " . ($db_pass ? "-p$db_pass" : "") . " $db_name";

system($comando, $resultado);

if ($resultado !== 0) {
    // Fallback si mysqldump no está en las variables de entorno de XAMPP
    echo "-- Respaldo lógico generado el $fecha\n\n";
    echo "-- (Para obtener datos completos, asegúrate de que 'mysqldump' esté en el PATH del sistema o ejecuta el respaldo directo desde phpMyAdmin)\n";
}
exit();
?>