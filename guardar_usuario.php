<?php
// guardar_usuario.php
session_start();

// 1. Conectar a la base de datos
require_once 'config/db.php';

// 2. Verificar que los datos vengan del formulario (método POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 3. Recibir y limpiar los datos
    // htmlspecialchars evita inyecciones de código HTML/JS (Cross-Site Scripting)
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    $boleta = htmlspecialchars(trim($_POST['boleta'])); // <-- NUEVO: Capturar boleta
    $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
    $password_plana = $_POST['password'];
    $id_rol = (int)$_POST['id_rol'];

    // 4. Cumplimiento de Seguridad (RNF21): Cifrar la contraseña
    // PASSWORD_DEFAULT usa el algoritmo bcrypt, el estándar actual de PHP
    $password_encriptada = password_hash($password_plana, PASSWORD_DEFAULT);

    // 5. Preparar la consulta SQL (PDO con sentencias preparadas evita Inyección SQL)
    try {
        // <-- NUEVO: Agregar 'boleta' a la consulta de inserción
        $sql = "INSERT INTO usuarios (nombre, boleta, correo, password, id_rol, estado) 
                VALUES (:nombre, :boleta, :correo, :password, :id_rol, 'Activo')";
        
        $stmt = $pdo->prepare($sql);
        
        // Vincular los parámetros
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':boleta', $boleta); // <-- NUEVO: Vincular la boleta a la base de datos
        $stmt->bindParam(':correo', $correo);
        $stmt->bindParam(':password', $password_encriptada);
        $stmt->bindParam(':id_rol', $id_rol);
        
        // Ejecutar la consulta
        $stmt->execute();

        // 6. Si todo sale bien, redirigir de vuelta a la tabla con un mensaje de éxito
        header("Location: usuarios.php?registro=exito");
        exit();

   } catch (PDOException $e) {
        // Si el correo O LA BOLETA ya existen, MySQL lanzará un error de clave duplicada (código 23000)
        if ($e->getCode() == 23000) {
            
            // Creamos una pantalla de error estilizada con CSS
            $error_html = "
            <!DOCTYPE html>
            <html lang='es'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Error de Registro</title>
                <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'>
                <style>
                    body { font-family: 'DM Sans', 'Segoe UI', sans-serif; background: #f5f3ef; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                    .error-card { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center; max-width: 450px; border-top: 6px solid #850021; }
                    .error-icon { font-size: 3.5rem; color: #dc2626; margin-bottom: 20px; }
                    h2 { color: #111827; margin-top: 0; font-family: 'Playfair Display', Georgia, serif; font-size: 1.5rem; }
                    p { color: #4b5563; font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px; }
                    strong { color: #111827; }
                    .btn-back { display: inline-flex; align-items: center; gap: 8px; background: #850021; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; transition: background 0.2s; box-shadow: 0 4px 12px rgba(133,0,33,0.2); }
                    .btn-back:hover { background: #5a0016; transform: translateY(-2px); }
                </style>
            </head>
            <body>
                <div class='error-card'>
                    <i class='fas fa-times-circle error-icon'></i>
                    <h2>¡Datos Duplicados!</h2>
                    <p>El correo electrónico <strong>$correo</strong> o la boleta <strong>$boleta</strong> ya se encuentran registrados en la base de datos.</p>
                    <a href='usuarios.php' class='btn-back'><i class='fas fa-arrow-left'></i> Volver al directorio</a>
                </div>
            </body>
            </html>
            ";
            
            die($error_html);
            
        } else {
            die("<div style='font-family:sans-serif; padding:40px; text-align:center;'><h2>Error crítico en la base de datos</h2><p>" . $e->getMessage() . "</p></div>");
        }
    }
?>
