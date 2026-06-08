<?php
// procesar_devolucion.php
session_start();
require_once 'config/db.php';

// Validar inicio de sesión
if (!isset($_SESSION['id_usuario'])) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_prestamo']) && isset($_POST['id_ejemplar'])) {
    
    $id_prestamo = (int)$_POST['id_prestamo'];
    $id_ejemplar = (int)$_POST['id_ejemplar'];
    
    try {
        // 1. Iniciar transacción de seguridad
        $pdo->beginTransaction();
        
        // 2. Actualizar el estado del préstamo a 'Devuelto'
        $sql_prestamo = "UPDATE prestamos SET estado = 'Devuelto' WHERE id_prestamo = :id_prestamo";
        $stmt_p = $pdo->prepare($sql_prestamo);
        $stmt_p->execute(['id_prestamo' => $id_prestamo]);
        
        // 3. Volver a cambiar el estado del ejemplar físico a 'Disponible'
        $sql_ejemplar = "UPDATE ejemplares SET estado = 'Disponible' WHERE id_ejemplar = :id_ejemplar";
        $stmt_e = $pdo->prepare($sql_ejemplar);
        $stmt_e->execute(['id_ejemplar' => $id_ejemplar]);
        
        // 4. Guardar los cambios definitivamente en la Base de Datos
        $pdo->commit();
        
        // Redirigir de vuelta al panel con una alerta limpia
        echo "<script>
                alert('Devolución procesada con éxito. El ejemplar vuelve a estar disponible en el catálogo.');
                window.location.href='gestionar_prestamos.php';
              </script>";
        exit();

    } catch (PDOException $e) {
        // Si algo falla, deshacemos los cambios para proteger el inventario
        $pdo->rollBack();
        die("Error crítico al procesar la devolución: " . $e->getMessage());
    }
} else {
    header("Location: gestionar_prestamos.php");
    exit();
}
?>
