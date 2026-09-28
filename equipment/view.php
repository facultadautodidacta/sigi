<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * equipment/view.php - Ficha de un equipo
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-04 (XSS almacenado)
 *   El campo description se imprime con lab_render_untrusted(), sin
 *   escapar. Cualquier etiqueta HTML que se haya guardado en el alta o
 *   la edicion se ejecuta aqui, en el navegador de quien abra la ficha.
 *   Ver documents/LAB-04-XSS.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_once __DIR__ . '/../config/lab_store.php';
require_login();

$id  = (int) input_get('id');
$eq  = db_row(
    "SELECT e.*, b.nombre AS marca, c.nombre AS categoria
       FROM equipment e
       JOIN brands b     ON b.id = e.brand_id
       JOIN categories c ON c.id = e.category_id
      WHERE e.id = ?",
    array($id)
);

if ($eq === null) {
    flash_set('danger', 'El equipo solicitado no existe.');
    redirect('equipment/index.php');
}

$page_title  = $eq['inventory_number'];
$page_module = 'equipment';

$asignacion_activa = db_row(
    "SELECT a.*, em.nombre AS emp_nombre, em.apellido AS emp_apellido,
            em.numero_empleado, em.departamento
       FROM assignments a
       JOIN employees em ON em.id = a.employee_id
      WHERE a.equipment_id = ? AND a.fecha_devolucion IS NULL",
    array($id)
);

$historial = db_all(
    "SELECT a.*, em.nombre AS emp_nombre, em.apellido AS emp_apellido
       FROM assignments a
       JOIN employees em ON em.id = a.employee_id
      WHERE a.equipment_id = ?
      ORDER BY a.fecha_asignacion DESC",
    array($id)
);

$mantenimientos = db_all(
    "SELECT m.*, CONCAT(u.nombre, ' ', u.apellido) AS tecnico
       FROM maintenance m
       LEFT JOIN users u ON u.id = m.technician_id
      WHERE m.equipment_id = ?
      ORDER BY m.maintenance_date DESC",
    array($id)
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('equipment/index.php'); ?>">Equipos</a> &rsaquo;
            <?php echo e($eq['inventory_number']); ?>
        </div>
        <h1><?php echo e($eq['model']); ?></h1>
        <div class="ph-subtitle">
            <span class="mono"><?php echo e($eq['inventory_number']); ?></span>
            &middot; <?php echo e($eq['marca']); ?>
            &middot; <?php echo e($eq['categoria']); ?>
        </div>
    </div>
    <div>
        <?php if (has_role(array(1, 2))): ?>
            <a href="<?php echo url('equipment/edit.php?id=' . $id); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-pen-to-square me-1"></i>Editar
            </a>
        <?php endif; ?>
        <a href="<?php echo url('equipment/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Caracteristicas</span>
                <?php echo status_badge($eq['status']); ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="detail-label">Procesador</div>
                            <div class="detail-value"><?php echo e($eq['processor']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Memoria RAM</div>
                            <div class="detail-value"><?php echo e($eq['ram']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Almacenamiento</div>
                            <div class="detail-value"><?php echo e($eq['storage']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="detail-label">Sistema operativo</div>
                            <div class="detail-value"><?php echo e($eq['operating_system']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Numero de serie</div>
                            <div class="detail-value mono small"><?php echo e($eq['serial_number']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Ubicacion</div>
                            <div class="detail-value"><?php echo e($eq['location']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="detail-label">Descripcion</div>
                    <div class="detail-value">
                        <?php
                        // -------------------------------------------------------------
                        // VULNERABILIDAD DELIBERADA (LAB-04b).
                        // La descripcion se imprime sin escapar. Un payload
                        // <script> o <img onerror> guardado en el alta se ejecuta
                        // aqui. El camino correcto es e($eq['description']).
                        // -------------------------------------------------------------
                        lab_render_untrusted($eq['description']);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Historial de asignaciones</div>
            <div class="card-body p-0">
                <?php if (count($historial) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock-rotate-left"></i>
                        Este equipo nunca ha sido asignado.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Departamento</th>
                                    <th>Asignado</th>
                                    <th>Devuelto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $h): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo url('employees/view.php?id=' . (int) $h['employee_id']); ?>">
                                                <?php echo e($h['emp_nombre'] . ' ' . $h['emp_apellido']); ?>
                                            </a>
                                        </td>
                                        <td class="small text-muted"><?php echo e($h['departamento']); ?></td>
                                        <td class="small"><?php echo format_date($h['fecha_asignacion']); ?></td>
                                        <td class="small"><?php echo $h['fecha_devolucion'] ? format_date($h['fecha_devolucion']) : '-'; ?></td>
                                        <td>
                                            <?php if ($h['fecha_devolucion'] === null): ?>
                                                <span class="badge bg-primary">Activa</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">Cerrada</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Mantenimientos</div>
            <div class="card-body p-0">
                <?php if (count($mantenimientos) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-screwdriver-wrench"></i>
                        Sin mantenimientos registrados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Descripcion</th>
                                    <th>Tecnico</th>
                                    <th>Costo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mantenimientos as $m): ?>
                                    <tr>
                                        <td class="small nowrap"><?php echo format_date($m['maintenance_date']); ?></td>
                                        <td class="small"><?php echo e($m['type']); ?></td>
                                        <td class="small"><?php echo e($m['description']); ?></td>
                                        <td class="small text-muted"><?php echo e($m['tecnico']); ?></td>
                                        <td class="small"><?php echo format_money($m['cost']); ?></td>
                                        <td><?php echo status_badge($m['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Estado actual</div>
            <div class="card-body">
                <?php if ($asignacion_activa): ?>
                    <div class="detail-label">Asignado a</div>
                    <div class="detail-value mb-2">
                        <a href="<?php echo url('employees/view.php?id=' . (int) $asignacion_activa['employee_id']); ?>">
                            <?php echo e($asignacion_activa['emp_nombre'] . ' ' . $asignacion_activa['emp_apellido']); ?>
                        </a>
                    </div>
                    <div class="text-muted small">
                        <?php echo e($asignacion_activa['numero_empleado']); ?>
                        &middot; <?php echo e($asignacion_activa['departamento']); ?>
                    </div>
                    <div class="text-muted small">Desde el <?php echo format_date($asignacion_activa['fecha_asignacion']); ?></div>
                <?php else: ?>
                    <div class="detail-label">Asignacion</div>
                    <div class="detail-value">Sin asignacion activa</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Datos de compra</div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="detail-label">Fecha de compra</div>
                    <div class="detail-value"><?php echo format_date($eq['purchase_date']); ?></div>
                </div>
                <div class="mb-2">
                    <div class="detail-label">Costo</div>
                    <div class="detail-value"><?php echo format_money($eq['price']); ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Trazabilidad</div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="detail-label">Alta en el sistema</div>
                    <div class="detail-value small"><?php echo format_datetime($eq['created_at']); ?></div>
                </div>
                <div>
                    <div class="detail-label">Ultima modificacion</div>
                    <div class="detail-value small"><?php echo format_datetime($eq['updated_at']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
