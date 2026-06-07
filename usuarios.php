<?php
// usuarios.php
session_start();

// Validar que el archivo de conexión exista (Ajusta la ruta si usaste la Opción 2 anterior)
require_once 'config/db.php'; 

// (Opcional por ahora) Validar sesión: 
// if (!isset($_SESSION['id_usuario'])) { header("Location: index.php"); exit(); }

// Consultar los usuarios y sus roles usando PDO
try {
    $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.estado, r.nombre_rol 
            FROM usuarios u 
            INNER JOIN roles r ON u.id_rol = r.id_rol
            ORDER BY u.id_usuario DESC";
    $stmt = $pdo->query($sql);
    $lista_usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar usuarios: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos base heredados del Dashboard para mantener la estética */
        :root {
            --primary-dark: #2c3e50;
            --accent: #3498db;
            --bg-body: #f4f7f6;
            --text-main: #333;
            --white: #ffffff;
            --shadow-md: 0 4px 15px rgba(0,0,0,0.05);
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: var(--bg-body); color: var(--text-main); margin: 0; padding: 40px; }
        
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header-section h1 { color: var(--primary-dark); margin: 0; }
        
        .btn-primary { background-color: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 1rem; transition: 0.3s; }
        .btn-primary:hover { background-color: #2980b9; }

        /* Estilos de la Tabla */
        .table-container { background: var(--white); border-radius: 10px; box-shadow: var(--shadow-md); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 15px 20px; border-bottom: 1px solid #eee; }
        th { background-color: #f8f9fa; color: #7f8c8d; text-transform: uppercase; font-size: 0.85rem; }
        tr:hover { background-color: #fcfcfc; }
        
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-inactive { background-color: #f8d7da; color: #721c24; }
        
        .action-btn { background: none; border: none; color: #95a5a6; cursor: pointer; font-size: 1.1rem; margin-right: 10px; transition: 0.3s; }
        .action-btn:hover { color: var(--accent); }

        /* Estilos del Modal (Pop-up) */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 30px; border-radius: 10px; width: 400px; box-shadow: 0 5px 30px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; font-size: 1.5rem; color: var(--primary-dark); }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #7f8c8d; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9rem; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include 'header.php'; ?>

    <div class="header-section">
        <div>
            <h1>👥 Gestión de Usuarios</h1>
            <p style="color: #7f8c8d; margin-top: 5px;">Administra los accesos y roles del sistema.</p>
        </div>
        <button class="btn-primary" onclick="abrirModal()"><i class="fas fa-plus"></i> Nuevo Usuario</button>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista_usuarios as $user): ?>
                <tr>
                    <td>#<?php echo $user['id_usuario']; ?></td>
                    <td style="font-weight: bold;"><?php echo htmlspecialchars($user['nombre']); ?></td>
                    <td><?php echo htmlspecialchars($user['correo']); ?></td>
                    <td><?php echo htmlspecialchars($user['nombre_rol']); ?></td>
                    <td>
                        <span class="status-badge <?php echo ($user['estado'] == 'Activo') ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $user['estado']; ?>
                        </span>
                    </td>
                   <td>
    <button class="action-btn" title="Editar"><i class="fas fa-edit"></i></button>
    
    <form action="acciones_usuario.php" method="POST" style="display:inline;">
        <input type="hidden" name="id_usuario" value="<?php echo $user['id_usuario']; ?>">
        <input type="hidden" name="accion" value="cambiar_estado">
        
        <?php if ($user['estado'] == 'Activo'): ?>
            <button type="submit" class="action-btn" title="Desactivar" style="color: #e74c3c;">
                <i class="fas fa-ban"></i>
            </button>
        <?php else: ?>
            <button type="submit" class="action-btn" title="Reactivar" style="color: #27ae60;">
                <i class="fas fa-check-circle"></i>
            </button>
        <?php endif; ?>
    </form>
</td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(empty($lista_usuarios)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #7f8c8d;">No hay usuarios registrados aún.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal" id="modalUsuario">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Registrar Usuario</h2>
                <button class="close-btn" onclick="cerrarModal()">&times;</button>
            </div>
            <form action="guardar_usuario.php" method="POST">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" required placeholder="Ej. Juan Pérez">
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" required placeholder="usuario@ipn.mx">
                </div>
                <div class="form-group">
                    <label>Contraseña Temporal</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Rol en el Sistema</label>
                    <select name="id_rol" required>
                        <option value="3">Usuario (Alumno/Docente)</option>
                        <option value="2">Bibliotecario</option>
                        <option value="1">Administrador</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Guardar Usuario</button>
            </form>
        </div>
    </div>

    <script>
        // Funciones simples en JavaScript para abrir y cerrar el Pop-up
        function abrirModal() {
            document.getElementById('modalUsuario').style.display = 'flex';
        }
        function cerrarModal() {
            document.getElementById('modalUsuario').style.display = 'none';
        }
        // Cerrar modal si se hace clic fuera del cuadro blanco
        window.onclick = function(event) {
            let modal = document.getElementById('modalUsuario');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>