<?php
// config/db.php

// ==============================================================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ==============================================================================

// Datos de conexión (Ajusta estos valores según tu configuración de XAMPP/Workbench)
$host = 'localhost';          // Generalmente 'localhost' o '127.0.0.1'
$db   = 'biblioteca_mps';    // El nombre exacto que le diste en Workbench
$user = 'root';              // Usuario por defecto de XAMPP es 'root'
$pass = '';                  // Contraseña por defecto de XAMPP es vacía ''
$charset = 'utf8mb4';        // Crucial para soportar tildes, ñ y emojis

// ==============================================================================
// CREACIÓN DE LA CONEXIÓN (PDO)
// ==============================================================================

// DSN (Data Source Name): La dirección completa para llegar a la BD
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Opciones de configuración de PDO para mayor seguridad y facilidad de desarrollo
$options = [
    // 1. Manejo de Errores: Lanza excepciones si algo sale mal (ideal para programar)
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    
    // 2. Modo de Retorno: Devuelve los datos como arrays asociativos ($fila['nombre'])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // 3. Seguridad: Desactiva emulación de preparaciones (previene inyecciones SQL reales)
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Intentamos crear el objeto de conexión
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Si llegamos aquí, la conexión fue exitosa.
    // La variable $pdo contiene el puente activo a la base de datos.
    
} catch (\PDOException $e) {
    // Si algo sale mal, detenemos la ejecución y mostramos el error.
    // En producción (web real), esto debería guardarse en un log, no mostrarse al usuario.
    die("❌ Error crítico de conexión: " . $e->getMessage());
}
