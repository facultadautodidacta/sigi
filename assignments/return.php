<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * assignments/return.php - Devolver un equipo asignado
 *
 * Reglas de negocio:
 *   - Solo se puede devolver una asignacion activa.
 *   - Al devolver, el equipo pasa a estado Disponible.
 *   - La fecha de devolucion no puede ser anterior a la de asignacion.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(array(1, 2));

$id = (int) input_get('id');

$asignacion = $id > 0
    ? db_row(
        "SELECT a.*, e.inventory_number, e.model, e.serial_number, e.status AS estado_equipo,
                em.nombre, em.apellido, em.numero_empleado
           FROM assignments a
           JOIN equipment e ON e.id = a.equipment_id
           JOIN employees em ON em.id = a.employee_id
          WHERE a.id = ?",
        array($id)
    )
    : null;

if ($asignacion === null) {
    flash_set('danger', 'La asignacion solicitada no existe.');
    redirect('assignments/index.php');
}

if ($asignacion['fecha_devolucion'] !== null) {
    flash_set('warning', 'Esa asignacion ya fue devuelta el ' . format_date($asignacion['fecha_devolucion']) . '.');
    redirect('assignments/index.php');
}

$errores = array();
$fecha   = date('Y-m-d');
$nota    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = input_post('fecha_devolucion');
    $nota  = trim(input_post('nota_devolucion'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || strtotime($fecha) === false) {
        $errores[] = 'La fecha de devolucion no tiene un formato valido.';
    } elseif (strtotime($fecha) < strtotime($asignacion['fecha_asignacion'])) {
        $errores[] = 'La devolucion no puede ser anterior a la asignacion ('
            . format_date($asignacion['fecha_asignacion']) . ').';
    } elseif (strtotime($fecha) > time()) {
        $errores[] = 'La devolucion no puede ser una fecha futura.';
    }

    if (count($errores) === 0) {
        db_exec(
            "UPDATE assignments SET fecha_devolucion = ?,
                    observaciones = CONCAT_WS(CHAR(10), NULLIF(observaciones,''), ?)
              WHERE id = ? AND fecha_devolucion IS NULL",
            array($fecha, $nota !== '' ? 'Devolucion ' . $fecha . ': ' . $nota : null, $id)
        );

        db_exec("UPDATE equipment SET status = 'Disponible' WHERE id = ?", array($asignacion['equipment_id']));

        audit_log('RETURN', 'assignments',
            'registro la devolucion del equipo ' . $asignacion['inventory_number']
            . ' por ' . $asignacion['numero_empleado']);

        flash_set('success', 'Devolucion registrada. El equipo ' . $asignacion['inventory_number']
            . ' quedo disponible.');
        redirect('assignments/index.php');
    }
}

$page_title  = 'Devolver equipo';
$page_module = 'assignments';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('assignments/index.php'); ?>">Asignaciones</a> &rsaquo; Devolver
        </div>
        <h1>Registrar Devolucion</h1>
        <div class="ph-subtitle">Cierre de la asignacion activa del equipo</div>
    </div>
</div>

<?php if (count($errores) > 0): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 small">
            <?php foreach ($errores as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Equipo a devolver</div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="detail-label">Equipo</div>
                    <div class="detail-value"><?php echo e($asignacion['model']); ?></div>
                    <div class="text-muted small mono"><?php echo e($asignacion['inventory_number']); ?></div>
                </div>
                <div class="mb-3">
                    <div class="detail-label">Numero de serie</div>
                    <div class="detail-value mono small"><?php echo e($asignacion['serial_number']); ?></div>
                </div>
                <div class="mb-3">
                    <div class="detail-label">Empleado</div>
                    <div class="detail-value">
                        <?php echo e($asignacion['apellido'] . ', ' . $asignacion['nombre']); ?>
                    </div>
                    <div class="text-muted small mono"><?php echo e($asignacion['numero_empleado']); ?></div>
                </div>
                <div class="mb-3">
                    <div class="detail-label">Fecha de asignacion</div>
                    <div class="detail-value"><?php echo format_date($asignacion['fecha_asignacion']); ?></div>
                </div>
                <?php if ($asignacion['observaciones']): ?>
                    <div>
                        <div class="detail-label">Observaciones del alta</div>
                        <div class="detail-value small"><?php echo e($asignacion['observaciones']); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <form method="post" action="<?php echo url('assignments/return.php?id=' . $id); ?>">
            <div class="card">
                <div class="card-header">Datos de la devolucion</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="fecha_devolucion">Fecha de devolucion <span class="required-mark">*</span></label>
                        <input type="date" class="form-control" id="fecha_devolucion" name="fecha_devolucion"
                               value="<?php echo e($fecha); ?>" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="nota_devolucion">Nota de devolucion</label>
                        <textarea class="form-control" id="nota_devolucion" name="nota_devolucion" rows="3"
                                  placeholder="Estado del equipo, accesorios devueltos, observaciones"><?php echo e($nota); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary" style="background-color:#1f4e79;border-color:#1f4e79;">
                    <i class="fas fa-undo me-1"></i>Registrar devolucion
                </button>
                <a href="<?php echo url('assignments/index.php'); ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
