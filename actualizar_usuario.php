<?php
session_start();
require_once 'config/db.php';

// Validar que solo los administradores puedan editar
if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) !== 'administrador') {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir los datos del modal
    $id_usuario = (int)$_POST['id_usuario'];
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    $boleta = htmlspecialchars(trim($_POST['boleta'])); 
    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
    $id_rol = (int)$_POST['id_rol'];

    try {
        // Actualizar los datos en la base de datos
        $sql = "UPDATE usuarios 
                SET nombre = :nombre, boleta = :boleta, correo = :correo, id_rol = :id_rol 
                WHERE id_usuario = :id_usuario";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':boleta' => $boleta,
            ':correo' => $correo,
            ':id_rol' => $id_rol,
            ':id_usuario' => $id_usuario
        ]);

        header("Location: usuarios.php?update=exito");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>¡Datos Duplicados!</h2><p>El correo o la boleta ingresada ya pertenece a otro usuario.</p><a href='usuarios.php'>Volver al directorio</a></div>");
        }
        die("Error al actualizar: " . $e->getMessage());
    }
} else {
    header("Location: usuarios.php");
    exit();
}
?>
