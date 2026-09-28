<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * users/delete.php - Eliminacion de usuario
 * Solo Administrador (rol 1).
 *
 * No permite eliminar al propio usuario administrador en sesion.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(1);

$id = (int) input_get('id');

if ($id <= 0) {
    flash_set('error', 'El identificador del usuario no es valido.');
    redirect('users/index.php');
}

$usuario = db_row("SELECT * FROM users WHERE id = ?", array($id));

if ($usuario === null) {
    flash_set('error', 'El usuario solicitado no existe.');
    redirect('users/index.php');
}

// --- Confirmacion en pantalla ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $page_title  = 'Eliminar Usuario';
    $page_module = 'users';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="page-header">
        <div>
            <div class="breadcrumb-app">
                <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
                <a href="<?php echo url('users/index.php'); ?>">Usuarios</a> &rsaquo; Eliminar
            </div>
            <h1>Eliminar Usuario</h1>
            <div class="ph-subtitle">Confirmacion de eliminacion</div>
        </div>
    </div>

    <div class="card" style="max-width:640px;">
        <div class="card-header" style="background-color:#f8d7da;color:#721c24;">
            <i class="fas fa-triangle-exclamation me-1"></i>Confirmar eliminacion
        </div>
        <div class="card-body">
            <p>Esta a punto de eliminar al siguiente usuario del sistema:</p>
            <table class="table table-sm table-bordered">
                <tr><th style="width:160px;">Nombre</th><td><?php echo e($usuario['nombre'] . ' ' . $usuario['apellido']); ?></td></tr>
                <tr><th>Usuario</th><td class="mono"><?php echo e($usuario['username']); ?></td></tr>
                <tr><th>Correo</th><td><?php echo e($usuario['email']); ?></td></tr>
                <tr><th>Rol</th><td><?php echo e($usuario['role_name'] ?? 'ID ' . $usuario['role_id']); ?></td></tr>
                <tr><th>Alta en el sistema</th><td><?php echo format_date($usuario['created_at']); ?></td></tr>
            </table>

            <div class="alert alert-warning small mb-0">
                <i class="fas fa-info-circle me-1"></i>
                Esta accion no se puede deshacer. Se conservan los registros de auditoria
                asociados a este usuario.
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="<?php echo url('users/index.php'); ?>" class="btn btn-outline-secondary">Cancelar</a>
            <form method="post" action="<?php echo url('users/delete.php'); ?>?id=<?php echo (int) $id; ?>" class="d-inline">
                <button type="submit" class="btn btn-danger ms-2">
                    <i class="fas fa-trash me-1"></i>Eliminar definitivamente
                </button>
            </form>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// --- Ejecucion de la eliminacion ---
$actual = current_user();

if ((int) $actual['id'] === $id) {
    flash_set('error', 'No puede eliminar su propia cuenta de usuario.');
    redirect('users/index.php');
}

try {
    // Equipos en mantenimiento con este tecnico quedan sin tecnico asignado.
    db_exec("UPDATE maintenance SET technician_id = NULL WHERE technician_id = ?", array($id));

    db_exec("DELETE FROM users WHERE id = ?", array($id));

    audit_log(
        'DELETE', 'users',
        'Elimino al usuario ' . $usuario['username'] . ' (id ' . $id . ')'
    );

    flash_set('success', 'Usuario eliminado correctamente.');
} catch (Exception $ex) {
    flash_set('error', 'No se pudo eliminar el usuario. Intente nuevamente.');
}

redirect('users/index.php');
