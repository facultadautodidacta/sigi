<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * users/index.php - Listado de usuarios
 * Solo Administrador (rol 1).
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(1);

$page_title  = 'Usuarios';
$page_module = 'users';

// --- Filtros y busqueda ---
$buscar     = trim(input_get('q'));
$filtro_rol = input_get('rol');
$filtro_est = input_get('estado');
$pagina     = max(1, (int) input_get('page', 1));
$por_pagina = 10;

$where  = array('1=1');
$params = array();

if ($buscar !== '') {
    $where[] = '(u.nombre LIKE ? OR u.apellido LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = '%' . $buscar . '%';
    $params = array_merge($params, array($like, $like, $like, $like));
}
if ($filtro_rol !== '' && is_numeric($filtro_rol)) {
    $where[]  = 'u.role_id = ?';
    $params[] = (int) $filtro_rol;
}
if ($filtro_est !== '' && in_array($filtro_est, array('Activo', 'Inactivo'))) {
    $where[]  = 'u.estado = ?';
    $params[] = $filtro_est;
}

$where_sql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM users u WHERE $where_sql", $params);
$offset = ($pagina - 1) * $por_pagina;

$usuarios = db_all(
    "SELECT u.*, r.name AS role_name
       FROM users u
       JOIN roles r ON r.id = u.role_id
      WHERE $where_sql
      ORDER BY u.id
      LIMIT $por_pagina OFFSET $offset",
    $params
);

$roles = db_all("SELECT id, name FROM roles ORDER BY id");
$total_paginas = max(1, ceil($total / $por_pagina));

// --- Mensaje de la URL (tras crear/editar/borrar) ---
$mensaje = input_get('msg');
if ($mensaje !== '') {
    $textos = array(
        'created' => 'Usuario registrado correctamente.',
        'updated' => 'Usuario actualizado correctamente.',
        'deleted' => 'Usuario eliminado correctamente.',
    );
    if (isset($textos[$mensaje])) {
        flash_set('success', $textos[$mensaje]);
        header('Location: ' . url('users/index.php'));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo; Usuarios
        </div>
        <h1>Gestion de Usuarios</h1>
        <div class="ph-subtitle">
            <?php echo (int) $total; ?> usuario(s) registrado(s) en el sistema
        </div>
    </div>
    <div>
        <a href="<?php echo url('users/create.php'); ?>" class="btn btn-primary btn-sm" style="background-color:#1f4e79;border-color:#1f4e79;">
            <i class="fas fa-plus me-1"></i>Nuevo usuario
        </a>
    </div>
</div>

<div class="filter-panel">
    <form method="get" action="<?php echo url('users/index.php'); ?>" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="q">Buscar</label>
            <input type="text" class="form-control" id="q" name="q" value="<?php echo e($buscar); ?>"
                   placeholder="Nombre, apellido, usuario o correo">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="rol">Rol</label>
            <select class="form-select" id="rol" name="rol">
                <option value="">Todos</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo (int) $r['id']; ?>" <?php echo $filtro_rol == $r['id'] ? 'selected' : ''; ?>>
                        <?php echo e($r['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <option value="Activo"   <?php echo $filtro_est === 'Activo'   ? 'selected' : ''; ?>>Activo</option>
                <option value="Inactivo" <?php echo $filtro_est === 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-secondary btn-sm w-100">
                <i class="fas fa-filter me-1"></i>Filtrar
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width:55px;">ID</th>
                        <th>Nombre completo</th>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Alta</th>
                        <th style="width:150px;" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($usuarios) === 0): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-users-slash"></i>
                                    No se encontraron usuarios con los criterios indicados.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="text-muted"><?php echo (int) $u['id']; ?></td>
                                <td><?php echo e($u['nombre'] . ' ' . $u['apellido']); ?></td>
                                <td class="mono"><?php echo e($u['username']); ?></td>
                                <td class="small"><?php echo e($u['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo (int) $u['role_id'] === 1 ? 'bg-danger' : ((int) $u['role_id'] === 2 ? 'bg-primary' : 'bg-secondary'); ?>">
                                        <?php echo e($u['role_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo status_badge($u['estado']); ?></td>
                                <td class="text-muted small"><?php echo format_date($u['created_at']); ?></td>
                                <td class="text-end table-actions">
                                    <a href="<?php echo url('users/view.php?id=' . (int) $u['id']); ?>" title="Ver detalle">
                                        <i class="fas fa-eye text-info"></i>
                                    </a>
                                    <a href="<?php echo url('users/edit.php?id=' . (int) $u['id']); ?>" title="Editar">
                                        <i class="fas fa-pen-to-square text-warning"></i>
                                    </a>
                                    <a href="<?php echo url('users/delete.php?id=' . (int) $u['id']); ?>" title="Eliminar"
                                       data-confirm="Confirma eliminar al usuario <?php echo e($u['username']); ?>?">
                                        <i class="fas fa-trash text-danger"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                Pagina <?php echo (int) $pagina; ?> de <?php echo (int) $total_paginas; ?>
            </span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('users/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina - 1)))); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo url('users/index.php?' . http_build_query(array_merge($_GET, array('page' => $i)))); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('users/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina + 1)))); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
