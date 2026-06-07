<?php
// acciones_usuario.php
require_once 'config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_usuario'])) {
    $id_usuario = $_POST['id_usuario'];

    // 1. Obtener estado actual
    $stmt = $pdo->prepare("SELECT estado FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();

    // 2. Invertir estado (si es Activo -> pasa a Inactivo, si es Inactivo -> pasa a Activo)
    $nuevo_estado = ($usuario['estado'] == 'Activo') ? 'Inactivo' : 'Activo';

    // 3. Actualizar
    $update = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id_usuario = ?");
    $update->execute([$nuevo_estado, $id_usuario]);

    header("Location: usuarios.php");
    exit();
}
?>