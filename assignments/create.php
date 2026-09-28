<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * assignments/create.php - Asignar un equipo a un empleado
 *
 * Reglas de negocio:
 *   - El equipo debe estar en estado Disponible.
 *   - El empleado debe estar Activo.
 *   - No puede haber otra asignacion activa para el mismo equipo.
 *   - Al asignar, el equipo pasa a estado Asignado.
 *
 * LAB-04: observaciones se almacena sin escapar a proposito. El sink
 * vulnerable esta en assignments/index.php.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(array(1, 2));

$errores = array();
$old     = array(
    'equipment_id'  => input_get('equipment_id'),
    'employee_id'   => input_get('employee_id'),
    'observaciones' => '',
);

$disponibles = db_all(
    "SELECT e.id, e.inventory_number, e.model, e.status,
            b.nombre AS marca, c.nombre AS categoria
       FROM equipment e
       JOIN brands b ON b.id = e.brand_id
       JOIN categories c ON c.id = e.category_id
      WHERE e.status = 'Disponible'
      ORDER BY e.inventory_number"
);

$empleados = db_all(
    "SELECT id, numero_empleado, nombre, apellido, departamento
       FROM employees
      WHERE estado = 'Activo'
      ORDER BY apellido, nombre"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array(
        'equipment_id'  => (int) input_post('equipment_id'),
        'employee_id'   => (int) input_post('employee_id'),
        'observaciones' => trim(input_post('observaciones')),
    );

    $fecha = input_post('fecha_asignacion');
    if ($fecha === '') {
        $fecha = date('Y-m-d');
    }
    $fecha_valida = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1
                 && strtotime($fecha) !== false;

    $equipo = $old['equipment_id'] > 0
        ? db_row("SELECT * FROM equipment WHERE id = ?", array($old['equipment_id']))
        : null;
    $empleado = $old['employee_id'] > 0
        ? db_row("SELECT * FROM employees WHERE id = ?", array($old['employee_id']))
        : null;

    if ($equipo === null) {
        $errores[] = 'Debe seleccionar un equipo valido.';
    } elseif ($equipo['status'] !== 'Disponible') {
        $errores[] = 'El equipo ' . $equipo['inventory_number'] . ' no esta disponible (estado actual: ' . $equipo['status'] . ').';
    } elseif (db_value("SELECT COUNT(*) FROM assignments
                          WHERE equipment_id = ? AND fecha_devolucion IS NULL",
                        array($equipo['id'])) > 0) {
        $errores[] = 'El equipo ya tiene una asignacion activa. Debe devolverlo antes de asignarlo de nuevo.';
    }

    if ($empleado === null) {
        $errores[] = 'Debe seleccionar un empleado valido.';
    } elseif ($empleado['estado'] !== 'Activo') {
        $errores[] = 'El empleado ' . $empleado['numero_empleado'] . ' esta Inactivo y no puede recibir equipos.';
    }

    if (!$fecha_valida) {
        $errores[] = 'La fecha de asignacion no tiene un formato valido.';
    }

    if (count($errores) === 0) {
        // El observaciones se guarda TAL CUAL. No hay htmlspecialchars ni e().
        // LAB-04: el valor se imprime sin escapar en assignments/index.php.
        db_exec(
            "INSERT INTO assignments (equipment_id, employee_id, fecha_asignacion, observaciones)
             VALUES (?, ?, ?, ?)",
            array(
                $equipo['id'],
                $empleado['id'],
                $fecha,
                $old['observaciones'] !== '' ? $old['observaciones'] : null
            )
        );

        db_exec("UPDATE equipment SET status = 'Asignado' WHERE id = ?", array($equipo['id']));

        audit_log('ASSIGN', 'assignments',
            'asigno el equipo ' . $equipo['inventory_number'] . ' a ' . $empleado['numero_empleado']
            . ' (' . $empleado['nombre'] . ' ' . $empleado['apellido'] . ')');

        flash_set('success', 'Equipo ' . $equipo['inventory_number'] . ' asignado a '
            . $empleado['nombre'] . ' ' . $empleado['apellido'] . '.');
        redirect('assignments/index.php');
    }
}

$page_title  = 'Asignar equipo';
$page_module = 'assignments';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('assignments/index.php'); ?>">Asignaciones</a> &rsaquo; Asignar
        </div>
        <h1>Asignar Equipo</h1>
        <div class="ph-subtitle">Entrega de un equipo disponible a un empleado activo</div>
    </div>
</div>

<?php if (count($errores) > 0): ?>
    <div class="alert alert-danger">
        <strong>No se pudo registrar la asignacion:</strong>
        <ul class="mb-0 mt-1 small">
            <?php foreach ($errores as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo url('assignments/create.php'); ?>">
    <div class="card mb-3">
        <div class="card-header">Equipo</div>
        <div class="card-body">
            <?php if (count($disponibles) === 0): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No hay equipos en estado <strong>Disponible</strong>.
                </div>
            <?php else: ?>
                <label class="form-label" for="equipment_id">
                    Equipo disponible <span class="required-mark">*</span>
                </label>
                <select class="form-select" id="equipment_id" name="equipment_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($disponibles as $eq): ?>
                        <option value="<?php echo (int) $eq['id']; ?>" <?php echo $old['equipment_id'] == $eq['id'] ? 'selected' : ''; ?>>
                            <?php echo e($eq['inventory_number'] . ' - ' . $eq['model'] . ' (' . $eq['marca'] . ', ' . $eq['categoria'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Empleado</div>
        <div class="card-body">
            <?php if (count($empleados) === 0): ?>
                <div class="alert alert-warning mb-0">No hay empleados activos.</div>
            <?php else: ?>
                <label class="form-label" for="employee_id">
                    Empleado <span class="required-mark">*</span>
                </label>
                <select class="form-select" id="employee_id" name="employee_id" required>
                    <option value="">Seleccione...</option>
                    <?php foreach ($empleados as $em): ?>
                        <option value="<?php echo (int) $em['id']; ?>" <?php echo $old['employee_id'] == $em['id'] ? 'selected' : ''; ?>>
                            <?php echo e($em['numero_empleado'] . ' - ' . $em['apellido'] . ', ' . $em['nombre'] . ' (' . $em['departamento'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Detalle de la entrega</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="fecha_asignacion">Fecha de asignacion</label>
                    <input type="date" class="form-control" id="fecha_asignacion" name="fecha_asignacion"
                           value="<?php echo e(input_post('fecha_asignacion', date('Y-m-d'))); ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="observaciones">Observaciones</label>
                    <input type="text" class="form-control" id="observaciones" name="observaciones"
                           value="<?php echo e($old['observaciones']); ?>"
                           placeholder="Condiciones de entrega, accesorios incluidos">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" style="background-color:#1f4e79;border-color:#1f4e79;">
            <i class="fas fa-user-check me-1"></i>Registrar asignacion
        </button>
        <a href="<?php echo url('assignments/index.php'); ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
