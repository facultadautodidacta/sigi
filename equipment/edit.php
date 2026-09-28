<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * equipment/edit.php - Edicion de equipo
 * Solo Administrador y Tecnico.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(array(1, 2));

$id = (int) input_get('id');

$equipment_row = db_row("SELECT * FROM equipment WHERE id = ?", array($id));
if ($equipment_row === null) {
    flash_set('danger', 'El equipo solicitado no existe.');
    redirect('equipment/index.php');
}

$titulo      = 'Editar Equipo';
$submit_text = 'Guardar cambios';
$form_action = 'equipment/edit.php?id=' . $id;

require_once __DIR__ . '/equipment_form.php';

$page_title  = 'Editar ' . $equipment_row['inventory_number'];
$page_module = 'equipment';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('equipment/index.php'); ?>">Equipos</a> &rsaquo;
            <a href="<?php echo url('equipment/view.php?id=' . $id); ?>">
                <?php echo e($equipment_row['inventory_number']); ?>
            </a> &rsaquo; Editar
        </div>
        <h1>Editar <?php echo e($equipment_row['model']); ?></h1>
        <div class="ph-subtitle"><span class="mono"><?php echo e($equipment_row['inventory_number']); ?></span></div>
    </div>
</div>

<?php if (count($errores) > 0): ?>
    <div class="alert alert-danger">
        <strong>No se pudo guardar el equipo:</strong>
        <ul class="mb-0 mt-1 small">
            <?php foreach ($errores as $err): ?>
                <li><?php echo e($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo url($form_action); ?>">
    <div class="card mb-3">
        <div class="card-header">Identificacion</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="inventory_number">
                        Numero de inventario <span class="required-mark">*</span>
                    </label>
                    <input type="text" class="form-control mono" id="inventory_number" name="inventory_number"
                           value="<?php echo e($old['inventory_number']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="serial_number">
                        Numero de serie <span class="required-mark">*</span>
                    </label>
                    <input type="text" class="form-control mono" id="serial_number" name="serial_number"
                           value="<?php echo e($old['serial_number']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <?php
                        $opciones = array('Disponible', 'Mantenimiento', 'Baja');
                        if ($old['status'] === 'Asignado') {
                            $opciones[] = 'Asignado';
                        }
                        foreach ($opciones as $s):
                        ?>
                            <option value="<?php echo e($s); ?>" <?php echo $old['status'] === $s ? 'selected' : ''; ?>>
                                <?php echo e($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($old['status'] === 'Asignado'): ?>
                        <div class="form-text">El equipo tiene una asignacion activa. Para devolverlo, use el modulo de Asignaciones.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Clasificacion</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="brand_id">Marca <span class="required-mark">*</span></label>
                    <select class="form-select" id="brand_id" name="brand_id" required>
                        <?php foreach ($marcas as $m): ?>
                            <option value="<?php echo (int) $m['id']; ?>" <?php echo $old['brand_id'] == $m['id'] ? 'selected' : ''; ?>>
                                <?php echo e($m['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Categoria <span class="required-mark">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo $old['category_id'] == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo e($c['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="location">Ubicacion</label>
                    <input type="text" class="form-control" id="location" name="location"
                           value="<?php echo e($old['location']); ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="model">Modelo <span class="required-mark">*</span></label>
                    <input type="text" class="form-control" id="model" name="model"
                           value="<?php echo e($old['model']); ?>" required>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Especificaciones</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="processor">Procesador</label>
                    <input type="text" class="form-control" id="processor" name="processor"
                           value="<?php echo e($old['processor']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ram">Memoria RAM</label>
                    <input type="text" class="form-control" id="ram" name="ram"
                           value="<?php echo e($old['ram']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="storage">Almacenamiento</label>
                    <input type="text" class="form-control" id="storage" name="storage"
                           value="<?php echo e($old['storage']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="operating_system">Sistema operativo</label>
                    <input type="text" class="form-control" id="operating_system" name="operating_system"
                           value="<?php echo e($old['operating_system']); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Compra y notas</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="purchase_date">Fecha de compra</label>
                    <input type="date" class="form-control" id="purchase_date" name="purchase_date"
                           value="<?php echo e($old['purchase_date']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="price">Costo</label>
                    <input type="text" class="form-control" id="price" name="price"
                           value="<?php echo e($old['price']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Descripcion</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo e($old['description']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" style="background-color:#1f4e79;border-color:#1f4e79;">
            <i class="fas fa-save me-1"></i><?php echo e($submit_text); ?>
        </button>
        <a href="<?php echo url('equipment/view.php?id=' . $id); ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
