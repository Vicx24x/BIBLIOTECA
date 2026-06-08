<?php
// index.php - Pantalla de Acceso y Registro de Alumnos
session_start();

if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

require_once 'config/db.php';

$error_login = '';
$mensaje_registro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    
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

    if ($_POST['accion'] === 'registro_alumno') {
        $nombre = trim($_POST['nombre']);
        $boleta = trim($_POST['boleta']);
        $correo = filter_var($_POST['correo'], FILTER_SANITIZE_EMAIL);
        $password_plana = $_POST['password'];

        if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+$/", $nombre)) {
            $mensaje_registro = "error|Nombre inválido: No se permiten números ni símbolos.";
        } elseif (!preg_match("/^\d{10}$/", $boleta)) {
            $mensaje_registro = "error|La boleta debe tener exactamente 10 dígitos numéricos.";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $mensaje_registro = "error|El correo proporcionado no es válido.";
        } elseif (strlen($password_plana) < 6) {
            $mensaje_registro = "error|La contraseña debe tener al menos 6 caracteres.";
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
                $mensaje_registro = "success|¡Registro exitoso! Ya puedes iniciar sesión.";
            } catch (PDOException $e) {
                $mensaje_registro = "error|La boleta o correo ya está registrado en el sistema.";
            }
        }
    }
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id_rol ASC")->fetchAll();

$reg_parts = $mensaje_registro ? explode('|', $mensaje_registro, 2) : ['',''];
$reg_tipo = $reg_parts[0];
$reg_msg  = $reg_parts[1] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'DM Sans', 'Segoe UI', sans-serif;
            background-color: var(--guinda-dark, #5a0016);
            margin: 0;
            min-height: 100vh;
        }

        /* ─── Login Layout ─── */
        .login-bg {
            min-height: calc(100vh - 110px);
            background: linear-gradient(160deg, var(--guinda-dark,#5a0016) 0%, #2d000b 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px 60px;
            position: relative;
            overflow: hidden;
        }

        .login-bg::before {
            content: '';
            position: absolute;
            width: 700px; height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,0.08) 0%, transparent 70%);
            top: -200px; right: -200px;
            pointer-events: none;
        }
        .login-bg::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,168,76,0.06) 0%, transparent 70%);
            bottom: -150px; left: -100px;
            pointer-events: none;
        }

        .login-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45);
            width: 100%;
            max-width: 960px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            position: relative;
            z-index: 2;
        }

        /* ─── Left Panel ─── */
        .panel-login {
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
            position: relative;
        }

        .panel-divider {
            position: absolute;
            right: 0; top: 10%; bottom: 10%;
            width: 1px;
            background: linear-gradient(to bottom, transparent, #e5e7eb 30%, #e5e7eb 70%, transparent);
        }

        /* ─── Right Panel ─── */
        .panel-register {
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--cream, #fdf8f0);
        }

        /* ─── Panel titles ─── */
        .panel-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 18px;
        }
        .panel-icon.guinda { background: linear-gradient(135deg, var(--guinda,#850021), var(--guinda-dark,#5a0016)); color: #fff; }
        .panel-icon.gold   { background: linear-gradient(135deg, var(--gold,#c9a84c), #a07830); color: #fff; }

        .panel-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--guinda, #850021);
            margin: 0 0 4px 0;
            line-height: 1.2;
        }
        .panel-sub {
            color: var(--text-muted, #6b7280);
            font-size: 0.875rem;
            margin: 0 0 28px 0;
        }

        /* ─── Alerts ─── */
        .alert-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn { from { opacity:0; transform: translateY(-8px); } to { opacity:1; transform: translateY(0); } }
        .alert-box.error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-box.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-box i { margin-top: 1px; flex-shrink: 0; }

        /* ─── Form Elements ─── */
        .form-group { margin-bottom: 16px; }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            background: #fff;
            color: #111827;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--guinda, #850021);
            box-shadow: 0 0 0 3px rgba(133,0,33,0.10);
        }
        .panel-register .form-control:focus {
            border-color: var(--gold, #c9a84c);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }
        .form-control::placeholder { color: #9ca3af; }

        .input-icon-wrap { position: relative; }
        .input-icon-wrap .form-control { padding-left: 40px; }
        .input-icon-wrap .icon {
            position: absolute;
            left: 13px; top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 0.9rem;
        }
        .input-icon-wrap .form-control:focus ~ .icon,
        .input-icon-wrap .form-control:focus + .icon { color: var(--guinda, #850021); }

        /* ─── Buttons ─── */
        .btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.25s ease;
            margin-top: 4px;
        }
        .btn-guinda {
            background: linear-gradient(135deg, var(--guinda,#850021) 0%, var(--guinda-dark,#5a0016) 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(133,0,33,0.30);
        }
        .btn-guinda:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(133,0,33,0.40);
        }
        .btn-guinda:active { transform: translateY(0); }
        
        .btn-gold {
            background: linear-gradient(135deg, var(--gold,#c9a84c) 0%, #a07830 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(201,168,76,0.30);
        }
        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(201,168,76,0.40);
        }

        /* ─── Responsive ─── */
        @media (max-width: 720px) {
            .login-card { grid-template-columns: 1fr; max-width: 480px; }
            .panel-divider { display: none; }
            .panel-login { border-bottom: 1px solid #e5e7eb; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="login-bg">
        <div class="login-card">

            <!-- LOGIN -->
            <div class="panel-login">
                <div class="panel-divider"></div>
                <div class="panel-icon guinda">
                    <i class="fas fa-university"></i>
                </div>
                <h2 class="panel-title">Iniciar Sesión</h2>
                <p class="panel-sub">Ingresa tus credenciales para acceder al sistema</p>

                <?php if($error_login): ?>
                <div class="alert-box error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_login); ?></span>
                </div>
                <?php endif; ?>

                <form action="index.php" method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="login">
                    
                    <div class="form-group">
                        <label class="form-label">Número de Boleta</label>
                        <div class="input-icon-wrap">
                            <input type="text" name="boleta" class="form-control" required placeholder="Ej. 2021600123" maxlength="10">
                            <i class="fas fa-id-card icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Rol de Acceso</label>
                        <div class="input-icon-wrap">
                            <select name="id_rol" class="form-control" required style="padding-left:40px; appearance:none; cursor:pointer;">
                                <?php foreach($roles as $rol): ?>
                                    <option value="<?php echo $rol['id_rol']; ?>"><?php echo htmlspecialchars($rol['nombre_rol']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-user-tag icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <div class="input-icon-wrap">
                            <input type="password" name="password" class="form-control" required placeholder="••••••••">
                            <i class="fas fa-lock icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-guinda">
                        <i class="fas fa-sign-in-alt"></i> Ingresar al Sistema
                    </button>
                </form>
            </div>

            <!-- REGISTRO -->
            <div class="panel-register">
                <div class="panel-icon gold">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2 class="panel-title" style="color: #92400e;">¿Nuevo alumno?</h2>
                <p class="panel-sub">Crea tu cuenta para solicitar préstamos de libros</p>

                <?php if($reg_tipo): ?>
                <div class="alert-box <?php echo $reg_tipo; ?>">
                    <i class="fas fa-<?php echo $reg_tipo === 'success' ? 'check-circle' : 'times-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($reg_msg); ?></span>
                </div>
                <?php endif; ?>

                <form action="index.php" method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="registro_alumno">
                    
                    <div class="form-group">
                        <label class="form-label">Nombre Completo</label>
                        <div class="input-icon-wrap">
                            <input type="text" name="nombre" class="form-control" required
                                   pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ ]+"
                                   title="Solo letras y espacios."
                                   placeholder="Ej. Carlos Gómez Pérez">
                            <i class="fas fa-user icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Número de Boleta</label>
                        <div class="input-icon-wrap">
                            <input type="text" name="boleta" class="form-control" required
                                   pattern="\d{10}" title="10 dígitos numéricos."
                                   placeholder="Ej. 2021600123" maxlength="10">
                            <i class="fas fa-hashtag icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Correo Institucional</label>
                        <div class="input-icon-wrap">
                            <input type="email" name="correo" class="form-control" required placeholder="alumno@alumno.ipn.mx">
                            <i class="fas fa-envelope icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Contraseña</label>
                        <div class="input-icon-wrap">
                            <input type="password" name="password" class="form-control" required placeholder="Mínimo 6 caracteres">
                            <i class="fas fa-key icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-gold">
                        <i class="fas fa-user-plus"></i> Registrarme como Alumno
                    </button>
                </form>
            </div>

        </div>
    </div>
</body>
</html>
