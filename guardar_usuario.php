<?php
// guardar_usuario.php
session_start();

// 1. Conectar a la base de datos
require_once 'config/db.php';

// 2. Verificar que los datos vengan del formulario (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. Recibir y limpiar los datos
    // htmlspecialchars evita inyecciones de código HTML/JS (Cross-Site Scripting)
    $nombre = htmlspecialchars($_POST['nombre']);
    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
    $password_plana = $_POST['password'];
    $id_rol = (int)$_POST['id_rol'];

    // 4. Cumplimiento de Seguridad (RNF21): Cifrar la contraseña
    // PASSWORD_DEFAULT usa el algoritmo bcrypt, el estándar actual de PHP
    $password_encriptada = password_hash($password_plana, PASSWORD_DEFAULT);

    // 5. Preparar la consulta SQL (PDO con sentencias preparadas evita Inyección SQL)
    try {
        $sql = "INSERT INTO usuarios (nombre, correo, password, id_rol, estado) 
                VALUES (:nombre, :correo, :password, :id_rol, 'Activo')";
        
        $stmt = $pdo->prepare($sql);
        
        // Vincular los parámetros
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':password', $password_encriptada);
        $stmt->bindParam(':id_rol', $id_rol);
        
        // Ejecutar la consulta
        $stmt->execute();

        // 6. Si todo sale bien, redirigir de vuelta a la tabla con un mensaje de éxito
        header("Location: usuarios.php?registro=exito");
        exit();

    } catch (PDOException $e) {
        // Si el correo ya existe, MySQL lanzará un error de clave duplicada (código 23000)
        if ($e->getCode() == 23000) {
            die("❌ Error: El correo electrónico '$correo' ya está registrado en el sistema. <a href='usuarios.php'>Volver</a>");
        } else {
            die("❌ Error crítico en la base de datos: " . $e->getMessage());
        }
    }
} else {
    // Si alguien intenta entrar a este archivo escribiendo la URL directamente, lo regresamos
    header("Location: usuarios.php");
    exit();
}
?>