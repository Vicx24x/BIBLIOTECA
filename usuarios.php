<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'Administrador') {
    header("Location: dashboard.php");
    exit();
}

try {
    $sql = "SELECT u.id_usuario, u.nombre, u.correo, u.boleta, u.estado, r.nombre_rol 
            FROM usuarios u 
            INNER JOIN roles r ON u.id_rol = r.id_rol
            ORDER BY u.id_usuario DESC";
    $stmt = $pdo->query($sql);
    $lista_usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar usuarios: " . $e->getMessage());
}

$total_activos   = count(array_filter($lista_usuarios, fn($u) => $u['estado'] === 'Activo'));
$total_inactivos = count(array_filter($lista_usuarios, fn($u) => $u['estado'] !== 'Activo'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios — Biblioteca UPIICSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'DM Sans','Segoe UI',sans-serif; background: #f5f3ef; margin: 0; color: #1a1a2e; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 36px 32px 60px; }

        .topnav { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; gap: 16px; flex-wrap: wrap; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--guinda,#850021); text-decoration: none; font-weight: 600; font-size: 0.875rem; padding: 8px 16px; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.2s; }
        .back-link:hover { background: var(--guinda,#850021); color: #fff; }
        .page-title { font-family: 'Playfair Display', Georgia, serif; font-size: 1.8rem; font-weight: 700; color: var(--guinda,#850021); margin: 0 0 2px; }
        .page-sub { color: #6b7280; font-size: 0.875rem; margin: 0; }
        .btn-new { display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, var(--guinda,#850021), #5a0016); color: #fff; padding: 11px 22px; border-radius: 10px; border: none; font-family: inherit; font-weight: 700; font-size: 0.875rem; cursor: pointer; box-shadow: 0 4px 14px rgba(133,0,33,0.28); transition: all 0.2s; }
        .btn-new:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(133,0,33,0.38); }

        /* Stats */
        .stats-row { display: flex; gap: 14px; margin-bottom: 28px; flex-wrap: wrap; }
        .s-chip { display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px; font-size: 0.84rem; font-weight: 700; }
        .s-chip.total  { background: #ede9fe; color: #4c1d95; }
        .s-chip.active { background: #d1fae5; color: #065f46; }
        .s-chip.inactive { background: #fee2e2; color: #991b1b; }
        .s-chip .count { font-size: 1.1rem; font-weight: 800; }

        /* Table */
        .table-card { background: #fff; border-radius: 18px; box-shadow: 0 2px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.04); overflow: hidden; }
        .table-header { padding: 20px 28px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
        .table-header h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.1rem; color: #111827; margin: 0; display: flex; align-items: center; gap: 8px; }
        .table-header h2 i { color: var(--guinda,#850021); }

        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 12px 20px; text-align: left; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #9ca3af; background: #fafafa; border-bottom: 1px solid #f3f4f6; }
        tbody tr { border-bottom: 1px solid #f9fafb; transition: background 0.15s; }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #fdf8f0; }
        tbody td { padding: 13px 20px; font-size: 0.875rem; vertical-align: middle; }

        .user-cell { display: flex; align-items: center; gap: 10px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--guinda,#850021), #5a0016); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        .user-name { font-weight: 700; color: #111827; font-size: 0.9rem; }
        .user-boleta { color: #9ca3af; font-size: 0.78rem; }

        .role-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .role-admin { background: #ede9fe; color: #4c1d95; }
        .role-biblio { background: #dbeafe; color: #1e40af; }
        .role-user { background: #f3f4f6; color: #374151; }

        .badge-on  { background: #d1fae5; color: #065f46; }
        .badge-off { background: #fee2e2; color: #991b1b; }
        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }

        .action-btn { width: 32px; height: 32px; border: none; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 0.9rem; transition: all 0.15s; background: #f3f4f6; color: #6b7280; }
        .action-btn:hover { background: #e5e7eb; color: #111827; }
        .action-btn.danger { background: #fee2e2; color: #991b1b; }
        .action-btn.danger:hover { background: #fecaca; }
        .action-btn.success { background: #d1fae5; color: #065f46; }
        .action-btn.success:hover { background: #a7f3d0; }

        /* Modal overlay */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-overlay.open { display: flex; }
        .modal-box { background: #fff; border-radius: 20px; padding: 32px; width: 100%; max-width: 440px; box-shadow: 0 24px 64px rgba(0,0,0,0.28); animation: popIn 0.25s ease; }
        @keyframes popIn { from { opacity:0; transform: scale(0.93) translateY(10px); } to { opacity:1; transform: scale(1) translateY(0); } }
        .modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .modal-head h2 { font-family: 'Playfair Display', Georgia, serif; font-size: 1.3rem; color: var(--guinda,#850021); margin: 0; }
        .close-modal { width: 32px; height: 32px; border: none; background: #f3f4f6; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #6b7280; font-size: 1rem; transition: all 0.15s; }
        .close-modal:hover { background: #e5e7eb; color: #111827; }

        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #374151; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 11px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 0.9rem; font-family: inherit; color: #111827; transition: border-color 0.2s; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: var(--guinda,#850021); box-shadow: 0 0 0 3px rgba(133,0,33,0.10); }
        .btn-save { width: 100%; padding: 13px; background: linear-gradient(135deg,var(--guinda,#850021),#5a0016); color: #fff; border: none; border-radius: 10px; font-family: inherit; font-size: 0.95rem; font-weight: 700; cursor: pointer; margin-top: 4px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .btn-save:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(133,0,33,0.35); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="page-wrap">
        <div class="topnav">
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div>
                <h1 class="page-title"><i class="fas fa-users" style="font-size:1.4rem;"></i> Gestión de Usuarios</h1>
                <p class="page-sub">Administra los accesos y roles del sistema</p>
            </div>
            <button class="btn-new" onclick="openModal()"><i class="fas fa-plus"></i> Nuevo Usuario</button>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="s-chip total"><i class="fas fa-users"></i> Total <span class="count"><?php echo count($lista_usuarios); ?></span></div>
            <div class="s-chip active"><i class="fas fa-check-circle"></i> Activos <span class="count"><?php echo $total_activos; ?></span></div>
            <div class="s-chip inactive"><i class="fas fa-ban"></i> Inactivos <span class="count"><?php echo $total_inactivos; ?></span></div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h2><i class="fas fa-id-card"></i> Directorio de Usuarios</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($lista_usuarios as $user):
                        $initials = strtoupper(implode('', array_map(fn($p) => $p[0], array_slice(explode(' ', $user['nombre']), 0, 2))));
                        $rolClass = match($user['nombre_rol']) { 'Administrador' => 'role-admin', 'Bibliotecario' => 'role-biblio', default => 'role-user' };
                    ?>
                    <tr>
                        <td style="color:#d1d5db; font-weight:600; font-size:0.8rem;">#<?php echo $user['id_usuario']; ?></td>
                        <td>
                            <div class="user-cell">
                                <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
                                <div>
                                    <div class="user-name"><?php echo htmlspecialchars($user['nombre']); ?></div>
                                    <?php if(!empty($user['boleta'])): ?>
                                    <div class="user-boleta"><i class="fas fa-id-badge" style="font-size:0.65rem;"></i> <?php echo htmlspecialchars($user['boleta']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="color:#6b7280; font-size:0.84rem;"><?php echo htmlspecialchars($user['correo']); ?></td>
                        <td><span class="role-pill <?php echo $rolClass; ?>"><?php echo htmlspecialchars($user['nombre_rol']); ?></span></td>
                        <td>
                            <span class="status-badge <?php echo $user['estado']==='Activo' ? 'badge-on' : 'badge-off'; ?>">
                                <i class="fas fa-<?php echo $user['estado']==='Activo' ? 'check-circle' : 'ban'; ?>"></i>
                                <?php echo $user['estado']; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <button class="action-btn" title="Editar"><i class="fas fa-pen"></i></button>
                                <form action="acciones_usuario.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="id_usuario" value="<?php echo $user['id_usuario']; ?>">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <?php if($user['estado']==='Activo'): ?>
                                        <button type="submit" class="action-btn danger" title="Desactivar"><i class="fas fa-ban"></i></button>
                                    <?php else: ?>
                                        <button type="submit" class="action-btn success" title="Reactivar"><i class="fas fa-check-circle"></i></button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($lista_usuarios)): ?>
                    <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:40px;">No hay usuarios registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-box">
            <div class="modal-head">
                <h2><i class="fas fa-user-plus" style="font-size:1.1rem;"></i> Registrar Usuario</h2>
                <button class="close-modal" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <form action="guardar_usuario.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Nombre Completo</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej. Juan Pérez">
                </div>
                <div class="form-group">
                    <label class="form-label">Correo Electrónico</label>
                    <input type="email" name="correo" class="form-control" required placeholder="usuario@ipn.mx">
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña Temporal</label>
                    <input type="password" name="password" class="form-control" required placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label class="form-label">Rol en el Sistema</label>
                    <select name="id_rol" class="form-control" required>
                        <option value="3">Usuario (Alumno/Docente)</option>
                        <option value="2">Bibliotecario</option>
                        <option value="1">Administrador</option>
                    </select>
                </div>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar Usuario</button>
            </form>
        </div>
    </div>

    <script>
        function openModal()  { document.getElementById('userModal').classList.add('open'); }
        function closeModal() { document.getElementById('userModal').classList.remove('open'); }
        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
