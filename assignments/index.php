<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * assignments/index.php - Historial de asignaciones y devoluciones
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-04 (XSS almacenado)
 *
 *   El campo assignments.observaciones se guarda tal cual llega del
 *   formulario (assignments/create.php) y en ESTA pagina se imprime
 *   con echo $a['observaciones'] en lugar de e($a['observaciones']).
 *
 *   La diferencia con el resto de campos de la aplicacion, que si pasan
 *   por e(), es la unica causa del XSS. Cuando un Administrador o Tecnico
 *   escribe <script>alert('XSS-ALMACENADO-LAB04')</script> al asignar un
 *   equipo, el script queda guardado y se ejecuta en el navegador de
 *   CUALQUIER usuario que abra el listado de asignaciones.
 *
 *   Que faltaria en el camino correcto:
 *
 *       <?php echo e($a['observaciones']); ?>
 *
 *   Este es el vector clasico de segunda orden: el atacante no necesita
 *   que la victima abra su propio formulario, basta con que consulte la
 *   lista de asignaciones. SafeLine lo detiene en el POST, y entonces el
 *   payload ni siquiera llega a almacenarse.
 *
 *   Ver documents/LAB-04-XSS.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$page_title  = 'Asignaciones';
$page_module = 'assignments';

$buscar  = trim(input_get('q'));
$estado  = input_get('estado');
$pagina  = max(1, (int) input_get('page', 1));
$por_pagina = 15;

$where  = array('1=1');
$params = array();

if ($buscar !== '') {
    $where[] = '(e.inventory_number LIKE ? OR e.model LIKE ? OR em.nombre LIKE ? OR em.apellido LIKE ?)';
    $like    = '%' . $buscar . '%';
    $params  = array_merge($params, array($like, $like, $like, $like));
}
if ($estado === 'activas') {
    $where[] = 'a.fecha_devolucion IS NULL';
} elseif ($estado === 'devueltas') {
    $where[] = 'a.fecha_devolucion IS NOT NULL';
}

$where_sql = implode(' AND ', $where);

$total         = (int) db_value("SELECT COUNT(*) FROM assignments a
                                   JOIN equipment e ON e.id = a.equipment_id
                                   JOIN employees em ON em.id = a.employee_id
                                  WHERE $where_sql", $params);
$offset        = ($pagina - 1) * $por_pagina;
$total_paginas = max(1, ceil($total / $por_pagina));

$asignaciones = db_all(
    "SELECT a.*, e.inventory_number, e.model, e.status AS estado_equipo,
            em.nombre, em.apellido, em.numero_empleado
       FROM assignments a
       JOIN equipment e ON e.id = a.equipment_id
       JOIN employees em ON em.id = a.employee_id
      WHERE $where_sql
      ORDER BY a.fecha_asignacion DESC, a.id DESC
      LIMIT $por_pagina OFFSET $offset",
    $params
);

$resumen = db_row(
    "SELECT
        SUM(a.fecha_devolucion IS NULL) AS activas,
        SUM(a.fecha_devolucion IS NOT NULL) AS devueltas
     FROM assignments a
     JOIN equipment e ON e.id = a.equipment_id
     JOIN employees em ON em.id = a.employee_id
     WHERE $where_sql",
    $params
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo; Asignaciones
        </div>
        <h1>Asignaciones y Devoluciones</h1>
        <div class="ph-subtitle">
            <?php echo (int) $total; ?> asignacion(es) en el historial
        </div>
    </div>
    <div>
        <a href="<?php echo url('assignments/create.php'); ?>" class="btn btn-primary btn-sm"
           style="background-color:#1f4e79;border-color:#1f4e79;">
            <i class="fas fa-plus me-1"></i>Asignar equipo
        </a>
    </div>
</div>

<div class="filter-panel">
    <form method="get" action="<?php echo url('assignments/index.php'); ?>" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="q">Buscar</label>
            <input type="text" class="form-control" id="q" name="q" value="<?php echo e($buscar); ?>"
                   placeholder="Inventario, modelo o empleado">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="estado">Tipo</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todas</option>
                <option value="activas"   <?php echo $estado === 'activas'   ? 'selected' : ''; ?>>Solo activas</option>
                <option value="devueltas" <?php echo $estado === 'devueltas' ? 'selected' : ''; ?>>Solo devueltas</option>
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
                        <th>Equipo</th>
                        <th>Empleado</th>
                        <th>Asignacion</th>
                        <th>Devolucion</th>
                        <th>Observaciones</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($asignaciones) === 0): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="fas fa-user-check"></i>
                                    No hay asignaciones con los criterios indicados.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($asignaciones as $a): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo url('equipment/view.php?id=' . (int) $a['equipment_id']); ?>">
                                        <?php echo e($a['model']); ?>
                                    </a>
                                    <div class="text-muted small mono"><?php echo e($a['inventory_number']); ?></div>
                                </td>
                                <td>
                                    <a href="<?php echo url('employees/view.php?id=' . (int) $a['employee_id']); ?>">
                                        <?php echo e($a['apellido'] . ', ' . $a['nombre']); ?>
                                    </a>
                                    <div class="text-muted small mono"><?php echo e($a['numero_empleado']); ?></div>
                                </td>
                                <td class="small"><?php echo format_date($a['fecha_asignacion']); ?></td>
                                <td class="small">
                                    <?php if ($a['fecha_devolucion'] === null): ?>
                                        <span class="text-muted">En custodia</span>
                                    <?php else: ?>
                                        <?php echo format_date($a['fecha_devolucion']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    // ------------------------------------------------------------
                                    // SINK VULNERABLE - LAB-04
                                    // Sin e() a proposito. Comparar con el resto de celdas.
                                    // ------------------------------------------------------------
                                    ?>
                                    <?php echo $a['observaciones']; ?>
                                </td>
                                <td>
                                    <?php if ($a['fecha_devolucion'] === null): ?>
                                        <a href="<?php echo url('assignments/return.php?id=' . (int) $a['id']); ?>"
                                           class="btn btn-outline-secondary btn-sm">Devolver</a>
                                    <?php else: ?>
                                        <?php echo status_badge($a['estado_equipo']); ?>
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
                <?php echo (int) $resumen['activas']; ?> activa(s) &middot;
                <?php echo (int) $resumen['devueltas']; ?> devuelta(s) &middot;
                pagina <?php echo (int) $pagina; ?> de <?php echo (int) $total_paginas; ?>
            </span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('assignments/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina - 1)))); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo url('assignments/index.php?' . http_build_query(array_merge($_GET, array('page' => $i)))); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('assignments/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina + 1)))); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
