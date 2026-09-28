<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * employees/index.php - Directorio de empleados
 *
 * Vista publica: cualquier usuario autenticado puede consultar el
 * directorio. Los datos personales no se muestran aqui, solo en la ficha.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$page_title  = 'Empleados';
$page_module = 'employees';

$buscar     = trim(input_get('q'));
$filtro_dep = input_get('departamento');
$pagina     = max(1, (int) input_get('page', 1));
$por_pagina = 12;

$where  = array('1=1');
$params = array();

if ($buscar !== '') {
    $where[] = '(nombre LIKE ? OR apellido LIKE ? OR numero_empleado LIKE ? OR puesto LIKE ?)';
    $like    = '%' . $buscar . '%';
    $params  = array_merge($params, array($like, $like, $like, $like));
}
if ($filtro_dep !== '') {
    $where[]  = 'departamento = ?';
    $params[] = $filtro_dep;
}

$where_sql = implode(' AND ', $where);

$total         = (int) db_value("SELECT COUNT(*) FROM employees WHERE $where_sql", $params);
$offset        = ($pagina - 1) * $por_pagina;
$total_paginas = max(1, ceil($total / $por_pagina));

$empleados = db_all(
    "SELECT e.*,
            (SELECT COUNT(*) FROM assignments a
              WHERE a.employee_id = e.id AND a.fecha_devolucion IS NULL) AS equipos_activos
       FROM employees e
      WHERE $where_sql
      ORDER BY e.apellido, e.nombre
      LIMIT $por_pagina OFFSET $offset",
    $params
);

$departamentos = db_all("SELECT DISTINCT departamento FROM employees ORDER BY departamento");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo; Empleados
        </div>
        <h1>Directorio de Empleados</h1>
        <div class="ph-subtitle"><?php echo (int) $total; ?> persona(s) registrada(s)</div>
    </div>
    <div>
        <a href="<?php echo url('assignments/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-user-check me-1"></i>Asignaciones
        </a>
    </div>
</div>

<div class="filter-panel">
    <form method="get" action="<?php echo url('employees/index.php'); ?>" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="q">Buscar</label>
            <input type="text" class="form-control" id="q" name="q" value="<?php echo e($buscar); ?>"
                   placeholder="Nombre, numero de empleado o puesto">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="departamento">Departamento</label>
            <select class="form-select" id="departamento" name="departamento">
                <option value="">Todos</option>
                <?php foreach ($departamentos as $d): ?>
                    <option value="<?php echo e($d['departamento']); ?>" <?php echo $filtro_dep === $d['departamento'] ? 'selected' : ''; ?>>
                        <?php echo e($d['departamento']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
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
                        <th style="width:110px;">No. empleado</th>
                        <th>Nombre</th>
                        <th>Puesto</th>
                        <th>Departamento</th>
                        <th class="text-center">Equipos</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($empleados) === 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-id-card"></i>
                                    No se encontraron empleados con los criterios indicados.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($empleados as $emp): ?>
                            <tr>
                                <td class="mono small"><?php echo e($emp['numero_empleado']); ?></td>
                                <td>
                                    <a href="<?php echo url('employees/view.php?id=' . (int) $emp['id']); ?>" class="text-decoration-none">
                                        <?php echo e($emp['apellido'] . ', ' . $emp['nombre']); ?>
                                    </a>
                                </td>
                                <td class="small"><?php echo e($emp['puesto']); ?></td>
                                <td class="small text-muted"><?php echo e($emp['departamento']); ?></td>
                                <td class="text-center">
                                    <?php if ((int) $emp['equipos_activos'] > 0): ?>
                                        <span class="badge bg-primary"><?php echo (int) $emp['equipos_activos']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo status_badge($emp['estado']); ?></td>
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
                        <a class="page-link" href="<?php echo url('employees/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina - 1)))); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo url('employees/index.php?' . http_build_query(array_merge($_GET, array('page' => $i)))); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('employees/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina + 1)))); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
