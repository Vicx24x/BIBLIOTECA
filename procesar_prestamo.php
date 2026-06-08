<?php
// procesar_prestamo.php
session_start();
require_once 'config/db.php';

// Validar que el usuario haya iniciado sesión (RF3: Trazabilidad)
if (!isset($_SESSION['id_usuario'])) {
    die("Debes iniciar sesión para solicitar un préstamo. <a href='index.php'>Volver al inicio</a>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_libro'])) {
    
    $id_libro = (int)$_POST['id_libro'];
    $id_usuario = $_SESSION['id_usuario'];
    
    try {
        // 1. Iniciar la Transacción ANTES de buscar para poder bloquear el registro
        $pdo->beginTransaction();
        
        // 2. Verificar Disponibilidad en Tiempo Real (RF6) con FOR UPDATE
        // El FOR UPDATE es vital: congela temporalmente este ejemplar para que nadie más lo tome.
        $sql_verificar = "SELECT id_ejemplar FROM ejemplares WHERE id_libro = :id_libro AND estado = 'Disponible' LIMIT 1 FOR UPDATE";
        $stmt_ver = $pdo->prepare($sql_verificar);
        $stmt_ver->execute(['id_libro' => $id_libro]);
        
        $ejemplar = $stmt_ver->fetch();

        if ($ejemplar) {
            // ¡Hay un libro físico disponible!
            $id_ejemplar = $ejemplar['id_ejemplar'];
            
            // 3. Cambiar el estado del ejemplar a 'Prestado'
            $sql_update = "UPDATE ejemplares SET estado = 'Prestado' WHERE id_ejemplar = :id_ejemplar";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute(['id_ejemplar' => $id_ejemplar]);
            
            // 4. Registrar el préstamo en el historial (RF3 y RF10)
            $fecha_prestamo = date('Y-m-d');
            $fecha_devolucion_esperada = date('Y-m-d', strtotime($fecha_prestamo . ' + 7 days'));
            
            $sql_prestamo = "INSERT INTO prestamos (id_usuario, id_ejemplar, fecha_prestamo, fecha_devolucion_esperada, estado) 
                             VALUES (:id_usuario, :id_ejemplar, :fecha_prestamo, :fecha_devolucion, 'Activo')";
            $stmt_prestamo = $pdo->prepare($sql_prestamo);
            $stmt_prestamo->execute([
                'id_usuario' => $id_usuario,
                'id_ejemplar' => $id_ejemplar,
                'fecha_prestamo' => $fecha_prestamo,
                'fecha_devolucion' => $fecha_devolucion_esperada
            ]);
            
            // 5. Confirmar los cambios en la BD y liberar el bloqueo
            $pdo->commit();
            
            // Redirigir con mensaje de éxito
            header("Location: catalogo.php?msg=prestamo_exitoso");
            exit();

        } else {
            // No hay ejemplares disponibles, deshacemos la transacción y liberamos recursos
            $pdo->rollBack();
            die("Lo sentimos, no hay ejemplares disponibles de este libro en este momento. <a href='catalogo.php'>Volver al catálogo</a>");
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack(); // Si algo falla, deshacemos todo para evitar errores en el inventario
        die("Error procesando el préstamo: " . $e->getMessage());
    }
} else {
    header("Location: catalogo.php");
    exit();
}
?>
