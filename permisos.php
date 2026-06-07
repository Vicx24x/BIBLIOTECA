<?php
// permisos.php
session_start();
require_once 'config/db.php';

// Seguridad: Solo el Administrador Central puede configurar permisos
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    die("Acceso denegado. <a href='dashboard.php'>Volver al inicio</a>");
}

$mensaje = '';

// 1. Guardar los permisos si se envió el formulario (Backend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_rol'])) {
    $id_rol_seleccionado = (int)$_POST['id_rol'];
    $permisos_marcados = $_POST['permisos'] ?? []; // Array con los IDs de los checkboxes marcados

    try {
        $pdo->beginTransaction();
        
        // Borramos los permisos anteriores de ese rol
        $sql_delete = "DELETE FROM permisos_roles WHERE id_rol = :id_rol";
        $stmt_del = $pdo->prepare($sql_delete);
        $stmt_del->execute(['id_rol' => $id_rol_seleccionado]);

        // Insertamos los nuevos permisos marcados
        if (!empty($permisos_marcados)) {
            $sql_insert = "INSERT INTO permisos_roles (id_rol, id_permiso) VALUES (:id_rol, :id_permiso)";
            $stmt_ins = $pdo->prepare($sql_insert);
            foreach ($permisos_marcados as $id_permiso) {
                $stmt_ins->execute(['id_rol' => $id_rol_seleccionado, 'id_permiso' => $id_permiso]);
            }
        }

        $pdo->commit();
        $mensaje = "<div class='alert success'><i class='fas fa-check-circle'></i> ¡Permisos actualizados correctamente para el rol!</div>";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensaje = "<div class='alert error'>Error al guardar: " . $e->getMessage() . "</div>";
    }
}

// 2. Obtener datos para pintar la interfaz
$roles = $pdo->query("SELECT * FROM roles ORDER BY id_rol ASC")->fetchAll();
$todos_los_permisos = $pdo->query("SELECT * FROM permisos ORDER BY id_permiso ASC")->fetchAll();

// Si el usuario seleccionó un rol para ver, o por defecto tomamos el primero (Administrador)
$rol_actual = isset($_GET['rol']) ? (int)$_GET['rol'] : $roles[0]['id_rol'];

// Obtener los permisos que ya tiene asignados ese rol para marcar los checkboxes
$sql_asignados = "SELECT id_permiso FROM permisos_roles WHERE id_rol = :id_rol";
$stmt_asig = $pdo->prepare($sql_asignados);
$stmt_asig->execute(['id_rol' => $rol_actual]);
$permisos_asignados = $stmt_asig->fetchAll(PDO::FETCH_COLUMN); // Devuelve un array simple [1, 3, 5...]

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - BiblioMPS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #2c3e50;
            --accent: #3498db;
            --bg-body: #f4f7f6;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        body { font-family: 'Segoe UI', sans-serif; background-color: var(--bg-body); color: #333; margin: 0; padding: 40px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: var(--primary-dark); margin: 0; font-size: 1.8rem; }
        .btn-volver { text-decoration: none; color: #7f8c8d; font-weight: bold; }
        .btn-volver:hover { color: var(--accent); }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

        .card { background: var(--white); padding: 35px; border-radius: 12px; box-shadow: var(--shadow); max-width: 900px; margin: 0 auto; }
        .card p { color: #7f8c8d; margin-bottom: 25px; }

        /* Filtro de Rol (Select) */
        .role-selector { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .role-selector label { font-weight: bold; font-size: 1.1rem; color: var(--primary-dark); margin-right: 15px; }
        .role-selector select { padding: 10px; font-size: 1rem; border-radius: 5px; border: 1px solid #ccc; min-width: 200px; }

        /* Cuadrícula de Checkboxes idéntica al Mockup */
        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 30px; }
        
        .checkbox-item { display: flex; align-items: center; background: #f9f9f9; padding: 12px 15px; border-radius: 8px; border: 1px solid #e0e0e0; transition: 0.2s; }
        .checkbox-item:hover { background: #f1f8ff; border-color: var(--accent); }
        .checkbox-item input[type="checkbox"] { width: 18px; height: 18px; margin-right: 12px; cursor: pointer; accent-color: var(--accent); }
        .checkbox-item label { cursor: pointer; font-size: 0.95rem; font-weight: 500; flex: 1; user-select: none; }

        .btn-guardar { background-color: #2ecc71; color: white; border: none; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 1.1rem; cursor: pointer; transition: 0.3s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-guardar:hover { background-color: #27ae60; }
    </style>
</head>
<body>

    <div class="header" style="max-width: 900px; margin: 0 auto 30px auto;">
        <div>
            <h1><i class="fas fa-shield-alt"></i> Gestión de Permisos</h1>
        </div>
        <a href="dashboard.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <div class="card">
        <p>Configurar derechos de acceso para diferentes tipos de usuario.</p>
        
        <?php echo $mensaje; ?>

        <div class="role-selector">
            <form action="permisos.php" method="GET" id="form-rol">
                <label><i class="fas fa-user-tag"></i> Seleccionar rol de usuario:</label>
                <select name="rol" onchange="document.getElementById('form-rol').submit();">
                    <?php foreach($roles as $r): ?>
                        <option value="<?php echo $r['id_rol']; ?>" <?php echo ($rol_actual == $r['id_rol']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['nombre_rol']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <form action="permisos.php?rol=<?php echo $rol_actual; ?>" method="POST">
            <input type="hidden" name="id_rol" value="<?php echo $rol_actual; ?>">
            
            <h3 style="margin-top: 0; margin-bottom: 20px; color: var(--primary-dark);">Permisos asignados:</h3>
            
            <div class="checkbox-grid">
                <?php foreach($todos_los_permisos as $permiso): ?>
                    <?php 
                        // Verificamos si este permiso específico está dentro del array de permisos asignados
                        $marcado = in_array($permiso['id_permiso'], $permisos_asignados) ? 'checked' : ''; 
                    ?>
                    <div class="checkbox-item">
                        <input type="checkbox" id="perm_<?php echo $permiso['id_permiso']; ?>" name="permisos[]" value="<?php echo $permiso['id_permiso']; ?>" <?php echo $marcado; ?>>
                        <label for="perm_<?php echo $permiso['id_permiso']; ?>"><?php echo htmlspecialchars($permiso['nombre_permiso']); ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar permisos</button>
        </form>
    </div>

</body>
</html>