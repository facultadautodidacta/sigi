<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * equipment/index.php - Listado de equipos con filtros y paginacion
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$page_title  = 'Equipos';
$page_module = 'equipment';

// --- Filtros y busqueda ---
$buscar    = trim(input_get('q'));
$filtro_est = input_get('estado');
$filtro_cat = input_get('categoria');
$filtro_mar = input_get('marca');
$pagina    = max(1, (int) input_get('page', 1));
$por_pagina = 12;

$where  = array('1=1');
$params = array();

if ($buscar !== '') {
    $where[] = '(e.model LIKE ? OR e.inventory_number LIKE ? OR e.serial_number LIKE ? OR e.location LIKE ?)';
    $like    = '%' . $buscar . '%';
    $params  = array_merge($params, array($like, $like, $like, $like));
}
if ($filtro_est !== '' && in_array($filtro_est, array('Disponible', 'Asignado', 'Mantenimiento', 'Baja'))) {
    $where[]  = 'e.status = ?';
    $params[] = $filtro_est;
}
if ($filtro_cat !== '' && is_numeric($filtro_cat)) {
    $where[]  = 'e.category_id = ?';
    $params[] = (int) $filtro_cat;
}
if ($filtro_mar !== '' && is_numeric($filtro_mar)) {
    $where[]  = 'e.brand_id = ?';
    $params[] = (int) $filtro_mar;
}

$where_sql = implode(' AND ', $where);

$total         = (int) db_value("SELECT COUNT(*) FROM equipment e WHERE $where_sql", $params);
$offset        = ($pagina - 1) * $por_pagina;
$total_paginas = max(1, ceil($total / $por_pagina));

$equipos = db_all(
    "SELECT e.*, b.nombre AS marca, c.nombre AS categoria
       FROM equipment e
       JOIN brands b     ON b.id = e.brand_id
       JOIN categories c ON c.id = e.category_id
      WHERE $where_sql
      ORDER BY e.id
      LIMIT $por_pagina OFFSET $offset",
    $params
);

$categorias = db_all("SELECT id, nombre FROM categories WHERE estado = 'Activo' ORDER BY nombre");
$marcas     = db_all("SELECT id, nombre FROM brands WHERE estado = 'Activo' ORDER BY nombre");

// --- Resumen por estado ---
$resumen = array();
foreach (db_all("SELECT status, COUNT(*) AS n FROM equipment GROUP BY status") as $r) {
    $resumen[$r['status']] = (int) $r['n'];
}

// --- Mensaje de la URL (tras crear/editar) ---
$mensaje = input_get('msg');
if ($mensaje !== '') {
    $textos = array(
        'created' => 'Equipo registrado correctamente.',
        'updated' => 'Equipo actualizado correctamente.',
    );
    if (isset($textos[$mensaje])) {
        flash_set('success', $textos[$mensaje]);
        header('Location: ' . url('equipment/index.php'));
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo; Equipos
        </div>
        <h1>Inventario de Equipos</h1>
        <div class="ph-subtitle">
            <?php echo (int) $total; ?> equipo(s) con los criterios indicados
        </div>
    </div>
    <div>
        <a href="<?php echo url('equipment/search.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-magnifying-glass me-1"></i>Buscador
        </a>
        <?php if (has_role(array(1, 2))): ?>
            <a href="<?php echo url('equipment/create.php'); ?>" class="btn btn-primary btn-sm" style="background-color:#1f4e79;border-color:#1f4e79;">
                <i class="fas fa-plus me-1"></i>Registrar equipo
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="sb-title">Disponibles</div>
            <div class="sb-subtitle stat-available"><?php echo isset($resumen['Disponible']) ? $resumen['Disponible'] : 0; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="sb-title">Asignados</div>
            <div class="sb-subtitle stat-assigned"><?php echo isset($resumen['Asignado']) ? $resumen['Asignado'] : 0; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="sb-title">En mantenimiento</div>
            <div class="sb-subtitle stat-maint"><?php echo isset($resumen['Mantenimiento']) ? $resumen['Mantenimiento'] : 0; ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="sb-title">De baja</div>
            <div class="sb-subtitle text-muted-2"><?php echo isset($resumen['Baja']) ? $resumen['Baja'] : 0; ?></div>
        </div>
    </div>
</div>

<div class="filter-panel">
    <form method="get" action="<?php echo url('equipment/index.php'); ?>" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="q">Buscar</label>
            <input type="text" class="form-control" id="q" name="q" value="<?php echo e($buscar); ?>"
                   placeholder="Modelo, numero de inventario, serie o ubicacion">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (array('Disponible', 'Asignado', 'Mantenimiento', 'Baja') as $s): ?>
                    <option value="<?php echo e($s); ?>" <?php echo $filtro_est === $s ? 'selected' : ''; ?>>
                        <?php echo e($s); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="categoria">Categoria</label>
            <select class="form-select" id="categoria" name="categoria">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>" <?php echo $filtro_cat == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo e($c['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="marca">Marca</label>
            <select class="form-select" id="marca" name="marca">
                <option value="">Todas</option>
                <?php foreach ($marcas as $m): ?>
                    <option value="<?php echo (int) $m['id']; ?>" <?php echo $filtro_mar == $m['id'] ? 'selected' : ''; ?>>
                        <?php echo e($m['nombre']); ?>
                    </option>
                <?php endforeach; ?>
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
                        <th style="width:110px;">Inventario</th>
                        <th>Equipo</th>
                        <th>Marca / Categoria</th>
                        <th>Ubicacion</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($equipos) === 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-desktop"></i>
                                    No se encontraron equipos con los criterios indicados.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($equipos as $eq): ?>
                            <tr>
                                <td class="mono small"><?php echo e($eq['inventory_number']); ?></td>
                                <td>
                                    <a href="<?php echo url('equipment/view.php?id=' . (int) $eq['id']); ?>" class="text-decoration-none">
                                        <?php echo e($eq['model']); ?>
                                    </a>
                                    <div class="text-muted small"><?php echo e($eq['processor']); ?></div>
                                </td>
                                <td class="small">
                                    <?php echo e($eq['marca']); ?>
                                    <div class="text-muted"><?php echo e($eq['categoria']); ?></div>
                                </td>
                                <td class="small text-muted"><?php echo e($eq['location']); ?></td>
                                <td><?php echo status_badge($eq['status']); ?></td>
                                <td class="text-end table-actions">
                                    <a href="<?php echo url('equipment/view.php?id=' . (int) $eq['id']); ?>" title="Ver ficha">
                                        <i class="fas fa-eye text-info"></i>
                                    </a>
                                    <?php if (has_role(array(1, 2))): ?>
                                        <a href="<?php echo url('equipment/edit.php?id=' . (int) $eq['id']); ?>" title="Editar">
                                            <i class="fas fa-pen-to-square text-warning"></i>
                                        </a>
                                    <?php endif; ?>
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
                        <a class="page-link" href="<?php echo url('equipment/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina - 1)))); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo url('equipment/index.php?' . http_build_query(array_merge($_GET, array('page' => $i)))); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('equipment/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina + 1)))); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
