<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * users/create.php - Alta de usuario
 * Solo Administrador (rol 1).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(1);

$user_row      = array();
$titulo        = 'Registrar Nuevo Usuario';
$submit_text   = 'Registrar usuario';
$es_edicion    = false;
$form_action   = 'users/create.php';

require __DIR__ . '/user_form.php';

$page_title  = 'Nuevo Usuario';
$page_module = 'users_create';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('users/index.php'); ?>">Usuarios</a> &rsaquo; Nuevo
        </div>
        <h1><?php echo e($titulo); ?></h1>
        <div class="ph-subtitle">Complete los datos del nuevo usuario del sistema</div>
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

<form method="post" action="<?php echo url($form_action); ?>" data-once>
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
                    <div class="form-text">Debe ser unico en el sistema.</div>
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
                    <div class="form-text">Solo letras, numeros, punto, guion y guion bajo. Minimo 3 caracteres.</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password">Contrasena <span class="required-mark">*</span></label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Minimo 8 caracteres. Se almacena cifrada.</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password2">Confirmar contrasena <span class="required-mark">*</span></label>
                    <input type="password" class="form-control" id="password2" name="password2" required>
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
                        <option value="">Seleccione un rol...</option>
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
                    <div class="form-text">Un usuario inactivo no puede iniciar sesion.</div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="<?php echo url('users/index.php'); ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary ms-2" style="background-color:#1f4e79;border-color:#1f4e79;">
                <i class="fas fa-save me-1"></i><?php echo e($submit_text); ?>
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
