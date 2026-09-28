<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * employees/view.php - Ficha de un empleado
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-03 (IDOR / falta de autorizacion)
 *
 *   La ficha se abre con require_login(): basta con tener cualquier
 *   sesion, incluso con el rol mas bajo (Consulta). El bloque "Datos
 *   personales" y las "Notas de RRHH" se imprimen sin comprobar que el
 *   visitante tenga permiso para verlos.
 *
 *   El identificador viaja en la URL (?id=), asi que un usuario con rol
 *   Consulta puede recorrer 1, 2, 3... 22 y leer el telefono y las notas
 *   internas de TODOS los empleados de la empresa.
 *
 *   Que faltaria en el camino correcto:
 *
 *       if (!has_role(1)) {
 *           http_response_code(403);
 *           include DOC_ROOT . '/403.php';
 *           exit;
 *       }
 *
 *   Importante para el laboratorio: un WAF como SafeLine NO detiene esto.
 *   Un IDOR no es un ataque de payload: es un uso legitimo de la URL con
 *   un id distinto. SafeLine no puede saber que ese id no le corresponde al
 *   visitante. Es el limite honesto de la proteccion y por eso el modulo
 *   de auditoria hace visibles los accesos.
 *
 *   Ver documents/LAB-03-IDOR.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$id = (int) input_get('id');

// ---------------------------------------------------------------------
// ESTA ES LA VULNERABILIDAD (LAB-03): se carga el registro completo con
// SELECT * sin filtrar por rol. El control de autorizacion que deberia
// proteger el bloque de datos personales no se aplica en ningun punto
// de esta pagina.
// ---------------------------------------------------------------------
$emp = db_row("SELECT * FROM employees WHERE id = ?", array($id));

if ($emp === null) {
    flash_set('danger', 'El empleado solicitado no existe.');
    redirect('employees/index.php');
}

$page_title  = $emp['nombre'] . ' ' . $emp['apellido'];
$page_module = 'employees';

$asignaciones = db_all(
    "SELECT a.*, e.inventory_number, e.model, e.status AS estado_equipo
       FROM assignments a
       JOIN equipment e ON e.id = a.equipment_id
      WHERE a.employee_id = ?
      ORDER BY a.fecha_asignacion DESC",
    array($id)
);

$asignacion_activa = null;
foreach ($asignaciones as $a) {
    if ($a['fecha_devolucion'] === null) {
        $asignacion_activa = $a;
        break;
    }
}

// El acceso a la ficha queda registrado. En el Escenario A (sin WAF) esta
// linea se acumula por cada id recorrido; con SafeLine tampoco cambia, y
// eso es precisamente lo que hay que señalar en el video.
audit_log('VIEW', 'employees',
    ' consulted la ficha del empleado ' . $emp['numero_empleado'] . ' (id ' . $id . ')');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('employees/index.php'); ?>">Empleados</a> &rsaquo;
            <?php echo e($emp['apellido'] . ', ' . $emp['nombre']); ?>
        </div>
        <h1><?php echo e($emp['nombre'] . ' ' . $emp['apellido']); ?></h1>
        <div class="ph-subtitle">
            <span class="mono"><?php echo e($emp['numero_empleado']); ?></span>
            &middot; <?php echo e($emp['puesto']); ?>
            &middot; <?php echo e($emp['departamento']); ?>
        </div>
    </div>
    <div>
        <a href="<?php echo url('assignments/create.php?employee_id=' . $id); ?>" class="btn btn-primary btn-sm"
           style="background-color:#1f4e79;border-color:#1f4e79;">
            <i class="fas fa-plus me-1"></i>Asignar equipo
        </a>
        <a href="<?php echo url('employees/index.php'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Volver
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Ficha laboral</span>
                <?php echo status_badge($emp['estado']); ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="detail-label">Nombre completo</div>
                            <div class="detail-value"><?php echo e($emp['nombre'] . ' ' . $emp['apellido']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Numero de empleado</div>
                            <div class="detail-value mono"><?php echo e($emp['numero_empleado']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Puesto</div>
                            <div class="detail-value"><?php echo e($emp['puesto']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="detail-label">Departamento</div>
                            <div class="detail-value"><?php echo e($emp['departamento']); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="detail-label">Alta en el sistema</div>
                            <div class="detail-value small"><?php echo format_date($emp['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Equipos asignados</div>
            <div class="card-body p-0">
                <?php if (count($asignaciones) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-laptop"></i>
                        Este empleado no tiene equipos registrados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Equipo</th>
                                    <th>Asignado</th>
                                    <th>Devuelto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asignaciones as $a): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo url('equipment/view.php?id=' . (int) $a['equipment_id']); ?>">
                                                <?php echo e($a['model']); ?>
                                            </a>
                                            <div class="text-muted small mono"><?php echo e($a['inventory_number']); ?></div>
                                        </td>
                                        <td class="small"><?php echo format_date($a['fecha_asignacion']); ?></td>
                                        <td class="small"><?php echo $a['fecha_devolucion'] ? format_date($a['fecha_devolucion']) : '-'; ?></td>
                                        <td>
                                            <?php if ($a['fecha_devolucion'] === null): ?>
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
    </div>

    <div class="col-lg-5">
        <?php
        // =================================================================
        // BLOQUE RESTRINGIDO - VULNERABLE (LAB-03)
        //
        // Este bloque deberia renderizarse solo para Administrador. No
        // hay ninguna comprobacion de rol: cualquiera con sesion lo ve.
        // Iterando ?id=1..22 un usuario de rol Consulta obtiene el
        // directorio completo de datos personales de la empresa.
        // =================================================================
        ?>
        <div class="card mb-3 border-danger">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Datos personales</span>
                <span class="badge bg-danger">Restringido</span>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="detail-label">Correo institucional</div>
                    <div class="detail-value small"><?php echo e($emp['email']); ?></div>
                </div>
                <div class="mb-3">
                    <div class="detail-label">Telefono</div>
                    <div class="detail-value"><?php echo e($emp['telefono']); ?></div>
                </div>
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Notas de RRHH</span>
                <span class="badge bg-danger">Restringido</span>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo e($emp['notas_confidenciales']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
