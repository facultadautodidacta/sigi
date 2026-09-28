<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * users/view.php - Detalle de un usuario
 * Solo Administrador (rol 1).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(1);

$id = (int) input_get('id');

if ($id <= 0) {
    flash_set('error', 'El identificador del usuario no es valido.');
    redirect('users/index.php');
}

$usuario = db_row(
    "SELECT u.*, r.name AS role_name, r.description AS role_description
       FROM users u
       JOIN roles r ON r.id = u.role_id
      WHERE u.id = ?",
    array($id)
);

if ($usuario === null) {
    flash_set('error', 'El usuario solicitado no existe.');
    redirect('users/index.php');
}

// Actividad reciente del usuario en la bitacora.
$actividad = db_all(
    "SELECT accion, modulo, ip, descripcion, created_at
       FROM audit_log
      WHERE usuario = ?
      ORDER BY id DESC
      LIMIT 15",
    array($usuario['username'])
);

// Equipos asignados al tecnico (rol Tecnico o Administrador).
$equipos_asignados = array();
if ((int) $usuario['role_id'] === 1 || (int) $usuario['role_id'] === 2) {
    $equipos_asignados = db_all(
        "SELECT e.id, e.inventory_number, e.model, e.status
           FROM maintenance m
           JOIN equipment e ON e.id = m.equipment_id
          WHERE m.technician_id = ?
          ORDER BY m.maintenance_date DESC
          LIMIT 10",
        array($id)
    );
}

$page_title  = 'Usuario: ' . $usuario['username'];
$page_module = 'users';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('users/index.php'); ?>">Usuarios</a> &rsaquo; Detalle
        </div>
        <h1><?php echo e($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h1>
        <div class="ph-subtitle">Usuario #<?php echo (int) $usuario['id']; ?> &middot; <?php echo e($usuario['role_name']); ?></div>
    </div>
    <div>
        <a href="<?php echo url('users/edit.php?id=' . (int) $usuario['id']); ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-pen-to-square me-1"></i>Editar
        </a>
        <a href="<?php echo url('users/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Datos del usuario</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <div class="detail-label">Nombre</div>
                        <div class="detail-value"><?php echo e($usuario['nombre']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Apellido</div>
                        <div class="detail-value"><?php echo e($usuario['apellido']); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="detail-label">Correo electronico</div>
                        <div class="detail-value"><?php echo e($usuario['email']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Usuario</div>
                        <div class="detail-value mono"><?php echo e($usuario['username']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Estado</div>
                        <div class="detail-value"><?php echo status_badge($usuario['estado']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Fecha de alta</div>
                        <div class="detail-value"><?php echo format_date($usuario['created_at']); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="detail-label">Ultima actualizacion</div>
                        <div class="detail-value"><?php echo format_datetime($usuario['updated_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Rol asignado</div>
            <div class="card-body">
                <div class="detail-label">Rol</div>
                <div class="detail-value">
                    <span class="badge <?php echo (int) $usuario['role_id'] === 1 ? 'bg-danger' : ((int) $usuario['role_id'] === 2 ? 'bg-primary' : 'bg-secondary'); ?>">
                        <?php echo e($usuario['role_name']); ?>
                    </span>
                </div>
                <div class="detail-label">Permisos</div>
                <div class="detail-value text-muted small"><?php echo e($usuario['role_description']); ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if (count($equipos_asignados) > 0): ?>
            <div class="card mb-3">
                <div class="card-header">Mantenimientos registrados por este usuario</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th>Modelo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($equipos_asignados as $eq): ?>
                                <tr>
                                    <td class="mono">
                                        <a href="<?php echo url('equipment/view.php?id=' . (int) $eq['id']); ?>">
                                            <?php echo e($eq['inventory_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo e($eq['model']); ?></td>
                                    <td><?php echo status_badge($eq['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Actividad reciente en el sistema</div>
            <div class="table-responsive">
                <?php if (count($actividad) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        Sin actividad registrada para este usuario.
                    </div>
                <?php else: ?>
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Accion</th>
                                <th>Modulo</th>
                                <th>IP</th>
                                <th>Descripcion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actividad as $a): ?>
                                <tr>
                                    <td class="text-muted small nowrap"><?php echo format_datetime($a['created_at']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo e($a['accion']); ?></span></td>
                                    <td class="small"><?php echo e($a['modulo']); ?></td>
                                    <td class="mono small"><?php echo e($a['ip']); ?></td>
                                    <td class="small text-muted"><?php echo e($a['descripcion']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
