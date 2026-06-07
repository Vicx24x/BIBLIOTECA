<?php
// test_db.php

// 1. Intentamos incluir el archivo de conexión que acabamos de crear
try {
    // require_once detiene la ejecución si el archivo no existe
    require_once 'config/db.php';
} catch (Exception $e) {
    // Este catch atrapa errores si ni siquiera se pudo incluir el archivo config/db.php
    $error_inclusion = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Conexión - BiblioMPS</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .test-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        h1 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        p {
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .details {
            margin-top: 20px;
            text-align: left;
            font-family: monospace;
            background: #eee;
            padding: 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            overflow-x: auto;
        }
    </style>
</head>
<body>

    <div class="test-card">
        <?php if (isset($error_inclusion)): ?>
            <div class="icon">📁✖️</div>
            <h1>Error de Archivo</h1>
            <p>No se pudo cargar el archivo de configuración. Verifica que la carpeta 'config' y el archivo 'db.php' existan dentro de ella.</p>
            <div class="status error">Archivo config/db.php no encontrado</div>
            <div class="details"><?php echo htmlspecialchars($error_inclusion); ?></div>

        <?php elseif (isset($pdo)): ?>
            <div class="icon">✅🧩</div>
            <h1>¡Conexión Exitosa!</h1>
            <p>La página web se ha conectado correctamente a la base de datos MySQL de tu XAMPP.</p>
            <div class="status success">Puenté Web-BD Activo</div>
            
            <?php 
            // Prueba opcional: Intentar leer algo de la BD (ej. contar roles)
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM roles");
                $result = $stmt->fetch();
                echo "<p style='margin-top:20px; color:#27ae60;'>Verificación adicional: Hay " . $result['total'] . " roles configurados en la tabla 'roles'.</p>";
            } catch (Exception $e) {
                echo "<p style='margin-top:20px; color:#e67e22;'>Nota: Conexión establecida, pero no se pudo leer la tabla 'roles'. ¿Ejecutaste el script SQL en Workbench?</p>";
            }
            ?>

        <?php else: ?>
            <div class="icon">⚠️🤯</div>
            <h1>Algo salió mal</h1>
            <p>No hay error de archivo ni objeto $pdo. Esto es extraño.</p>
        <?php endif; ?>
    </div>

</body>
</html>