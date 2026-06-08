<?php
// index.php - Pantalla de Acceso y Registro de Alumnos
session_start();

// Si el usuario ya tiene sesión activa, lo mandamos al Dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config/db.php';

$error_login = '';
$mensaje_registro = '';

// PROCESAR FORMULARIOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
    // ─────────────── LOGICA 1: INICIO DE SESIÓN ───────────────
    if ($_POST['accion'] === 'login') {
        $boleta = htmlspecialchars($_POST['boleta']);
        $id_rol_seleccionado = (int)$_POST['id_rol'];
        $password = $_POST['password'];

        try {
            $sql = "SELECT u.*, r.nombre_rol 
                    FROM usuarios u 
                    INNER JOIN roles r ON u.id_rol = r.id_rol 
                    WHERE u.boleta = :boleta LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['boleta' => $boleta]);
            $user = $stmt->fetch();

            if ($user) {
                if ($user['id_rol'] !== $id_rol_seleccionado) {
                    $error_login = 'Rol seleccionado incorrecto. Intentar nuevamente.';
                } else if (password_verify($password, $user['password'])) {
                    if ($user['estado'] === 'Inactivo') {
                        $error_login = 'Tu cuenta está desactivada. Contacta al administrador.';
                    } else {
                        $_SESSION['id_usuario'] = $user['id_usuario'];
                        $_SESSION['nombre'] = $user['nombre'];
                        $_SESSION['rol'] = $user['nombre_rol'];
                        $_SESSION['boleta'] = $user['boleta'];
                        header("Location: dashboard.php");
                        exit();
                    }
                } else {
                    $error_login = 'Contraseña incorrecta.';
                }
            } else {
                $error_login = 'No se encontró ningún usuario con esa boleta.';
            }
        } catch (PDOException $e) {
            $error_login = "Error en el servidor: " . $e->getMessage();
        }
    }

    // ─────────────── LOGICA 2: REGISTRO DE ALUMNOS ───────────────
    if ($_POST['accion'] === 'registro_alumno') {
        $nombre = trim($_POST['nombre']);
        $boleta = trim($_POST['boleta']);
        $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
        $password_plana = $_POST['password'];

        if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/", $nombre)) {
            $mensaje_registro = "<div class='alert error'>Nombre inválido: No se permiten números ni símbolos.</div>";
        } elseif (!preg_match("/^\d{10}$/", $boleta)) {
            $mensaje_registro = "<div class='alert error'>La boleta debe tener exactamente 10 dígitos numéricos.</div>";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje_registro = "<div class='alert error'>El correo proporcionado no es válido.</div>";
        } elseif (strlen($password_plana) < 6) {
            $mensaje_registro = "<div class='alert error'>La contraseña debe tener al menos 6 caracteres.</div>";
        } else {
            $password_encriptada = password_hash($password_plana, PASSWORD_DEFAULT);
            try {
                $sql = "INSERT INTO usuarios (nombre, boleta, correo, password, id_rol, estado) 
                        VALUES (:nombre, :boleta, :correo, :password, 3, 'Activo')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre, 
                    'boleta' => $boleta, 
                    'correo' => $correo, 
                    'password' => $password_encriptada
                ]);
                $mensaje_registro = "<div class='alert success'>¡Registro exitoso! Ya puedes iniciar sesión.</div>";
            } catch (PDOException $e) {
                $mensaje_registro = "<div class='alert error'>Error: La boleta ya está registrada en el sistema.</div>";
            }
        }
    }
}

// Obtener roles para el selector del login
$roles = $pdo->query("SELECT * FROM roles ORDER BY id_rol ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso y Registro - Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-dark: #850021; --accent: #bf2a2a; --success: #2ecc71; --bg-body: #f4f7f6; --white: #ffffff; }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); margin: 0; }
        
        .page-wrapper { min-height: 100vh; display: flex; flex-direction: column; }
        
        .login-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: var(--primary-dark);
            padding: 40px 20px;
        }

        .container-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 900px;
            overflow: hidden;
        }
        
        .login-side, .register-side { padding: 40px; display: flex; flex-direction: column; justify-content: center; }
        .login-side { border-right: 1px solid #eee; }
        .register-side { background-color: #fcfcfc; }

        h2 { color: var(--primary-dark); margin: 0 0 5px 0; font-size: 1.6rem; }
        .subtitle { color: #7f8c8d; margin: 0 0 25px 0; font-size: 0.95rem; }
        
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: bold; color: #333; font-size: 0.85rem; }
        .form-group input, .form-group select { width: 100%; padding: 11px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus { border-color: var(--accent); outline: none; }
        
        .btn-submit { color: white; border: none; padding: 12px; width: 100%; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-blue { background-color: var(--accent); }
        .btn-blue:hover { background-color: #2980b9; }
        .btn-green { background-color: var(--success); }
        .btn-green:hover { background-color: #27ae60; }

        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 0.88rem; font-weight: bold; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        @media (max-width: 768px) {
            .container-split { grid-template-columns: 1fr; }
            .login-side { border-right: none; border-bottom: 1px solid #eee; }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">
        <?php include 'header.php'; ?>
        
        <div class="login-content">
            <div class="container-split">
                
                <div class="login-side">
                    <div style="text-align: center; margin-bottom: 15px;">
                        <i class="fas fa-university" style="font-size: 2.5rem; color: var(--accent);"></i>
                    </div>
                    <h2>Biblioteca UPIICSA</h2>
                    <p class="subtitle">Ingresa tus datos para continuar</p>

                    <?php if($error_login): ?>
                        <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_login; ?></div>
                    <?php endif; ?>

                    <form action="index.php" method="POST">
                        <input type="hidden" name="accion" value="login">
                        
                        <div class="form-group">
                            <label>No. de Boleta</label>
                            <input type="text" name="boleta" required placeholder="Ej. 2021600123">
                        </div>
                        
                        <div class="form-group">
                            <label>Rol de Acceso</label>
                            <select name="id_rol" required>
                                <?php foreach($roles as $rol): ?>
                                    <option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars($rol['nombre_rol']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Contraseña</label>
                            <input type="password" name="password" required placeholder="********">
                        </div>
                        
                        <button type="submit" class="btn-submit btn-blue">Ingresar al Sistema</button>
                    </form>
                </div>

                <div class="register-side">
                    <h2>¿No te has registrado?</h2>
                    <p class="subtitle">Ingresa tus datos como alumno para darte de alta</p>

                    <?php echo $mensaje_registro; ?>

                    <form action="index.php" method="POST">
                        <input type="hidden" name="accion" value="registro_alumno">
                        
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" name="nombre" required 
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+" 
                                   title="El nombre solo debe contener letras y espacios."
                                   placeholder="Ej. Carlos Gómez">
                        </div>

                        <div class="form-group">
                            <label>Número de Boleta</label>
                            <input type="text" name="boleta" required 
                                   pattern="\d{10}" 
                                   title="La boleta debe tener exactamente 10 números."
                                   placeholder="Ej. 2021600123" maxlength="10">
                        </div>
                        
                        <div class="form-group">
                            <label>Correo Institucional</label>
                            <input type="email" name="correo" required placeholder="alumno@alumno.ipn.mx">
                        </div>
                        
                        <div class="form-group">
                            <label>Establecer Contraseña</label>
                            <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
                        </div>
                        
                        <button type="submit" class="btn-submit btn-green"><i class="fas fa-user-plus"></i> Registrarme como Alumno</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
