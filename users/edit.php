<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * users/edit.php - Edicion de usuario
 * Solo Administrador (rol 1). Si un usuario edita su propio perfil desde
 * el menu, tambien se permite (el sistema no distingue ese caso, porque
 * cualquier Administrador puede editar usuarios).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(1);

$id = (int) input_get('id');

if ($id <= 0) {
    flash_set('error', 'El identificador del usuario no es valido.');
    redirect('users/index.php');
}

$user_row = db_row("SELECT * FROM users WHERE id = ?", array($id));

if ($user_row === null) {
    flash_set('error', 'El usuario solicitado no existe.');
    redirect('users/index.php');
}

$titulo      = 'Editar Usuario';
$submit_text = 'Guardar cambios';
$es_edicion  = true;
$form_action = 'users/edit.php';

require __DIR__ . '/user_form.php';

$page_title  = 'Editar Usuario';
$page_module = 'users';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('users/index.php'); ?>">Usuarios</a> &rsaquo; Editar
        </div>
        <h1><?php echo e($titulo); ?></h1>
        <div class="ph-subtitle">
            Modificando a <strong><?php echo e($user_row['nombre'] . ' ' . $user_row['apellido']); ?></strong>
            (<?php echo e($user_row['username']); ?>)
        </div>
    </div>
    <div>
        <a href="<?php echo url('users/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<?php if (count($errores) > 0): ?>
    <div class="alert alert-danger">
        <strong><i class="fas fa-exclamation-triangle me-1"></i>No se pudo guardar el usuario:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errores as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo url('users/edit.php'); ?>?id=<?php echo (int) $id; ?>" data-once>
    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">

    <div class="card">
        <div class="card-header">Datos personales</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="nombre">Nombre <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="nombre" name="nombre"
                           value="<?php echo e($old['nombre']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="apellido">Apellido <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="apellido" name="apellido"
                           value="<?php echo e($old['apellido']); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">Correo electronico <span class="required-mark">*</span></label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?php echo e($old['email']); ?>" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Credenciales de acceso</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="username">Usuario <span class="required-mark">*</span></label>
                    <input type="text" class="form-control mono" id="username" name="username"
                           value="<?php echo e($old['username']); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password">Nueva contrasena</label>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                    <div class="form-text">Dejelo vacio para conservar la contrasena actual.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password2">Confirmar nueva contrasena</label>
                    <input type="password" class="form-control" id="password2" name="password2" autocomplete="new-password">
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">Permisos y estado</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="role_id">Rol <span class="required-mark">*</span></label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo (int) $r['id']; ?>"
                                <?php echo (int) $old['role_id'] === (int) $r['id'] ? 'selected' : ''; ?>>
                                <?php echo e($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="estado">Estado <span class="required-mark">*</span></label>
                    <select class="form-select" id="estado" name="estado" required>
                        <option value="Activo"   <?php echo $old['estado'] === 'Activo'   ? 'selected' : ''; ?>>Activo</option>
                        <option value="Inactivo" <?php echo $old['estado'] === 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-muted small">
                Ultima actualizacion: <?php echo format_datetime($user_row['updated_at']); ?>
            </div>
            <div>
                <a href="<?php echo url('users/index.php'); ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary ms-2" style="background-color:#1f4e79;border-color:#1f4e79;">
                    <i class="fas fa-save me-1"></i><?php echo e($submit_text); ?>
                </button>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
