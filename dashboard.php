<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * dashboard.php - Panel principal
 *
 * FASE 3 ampliara este archivo con graficos y estadisticas detalladas.
 * En la FASE 2 se muestra un resumen funcional de los datos ya cargados.
 *
 * IMPORTANTE: este archivo NO contiene vulnerabilidades deliberadas.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/audit.php';
require_login();

$page_title  = 'Dashboard';
$page_module = 'dashboard';

// --- Totales de inventario ---
$total_equipos   = (int) db_value("SELECT COUNT(*) FROM equipment");
$disp_disponible = (int) db_value("SELECT COUNT(*) FROM equipment WHERE status = 'Disponible'");
$disp_asignado   = (int) db_value("SELECT COUNT(*) FROM equipment WHERE status = 'Asignado'");
$disp_mantenim   = (int) db_value("SELECT COUNT(*) FROM equipment WHERE status = 'Mantenimiento'");
$disp_baja       = (int) db_value("SELECT COUNT(*) FROM equipment WHERE status = 'Baja'");

$total_usuarios  = (int) db_value("SELECT COUNT(*) FROM users");
$total_empleados = (int) db_value("SELECT COUNT(*) FROM employees");
$total_activos   = (int) db_value("SELECT COUNT(*) FROM employees WHERE estado = 'Activo'");

// --- Listados auxiliares ---
$por_categoria = db_all(
    "SELECT c.nombre, COUNT(e.id) AS total
       FROM categories c
       LEFT JOIN equipment e ON e.category_id = c.id
      GROUP BY c.id, c.nombre
      ORDER BY total DESC"
);

$por_marca = db_all(
    "SELECT b.nombre, COUNT(e.id) AS total
       FROM brands b
       LEFT JOIN equipment e ON e.brand_id = b.id
      GROUP BY b.id, b.nombre
      ORDER BY total DESC
      LIMIT 8"
);

$ultimos = db_all(
    "SELECT e.id, e.inventory_number, e.model, e.status, e.updated_at, b.nombre AS marca
       FROM equipment e
       JOIN brands b ON b.id = e.brand_id
      ORDER BY e.updated_at DESC
      LIMIT 8"
);

$actividad = db_all(
    "SELECT accion, modulo, descripcion, created_at
       FROM audit_log
      ORDER BY id DESC
      LIMIT 8"
);

$u = current_user();
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">Inicio &rsaquo; Dashboard</div>
        <h1>Panel de Control</h1>
        <div class="ph-subtitle">
            Bienvenido, <strong><?php echo e($u['nombre'] . ' ' . $u['apellido']); ?></strong>
            &middot; <?php echo e($u['role_name']); ?>
        </div>
    </div>
    <div class="text-muted small">
        Ultimo acceso: <?php echo format_datetime(date('Y-m-d H:i:s', $u['updated_at'] ? strtotime($u['updated_at']) : time())); ?>
    </div>
</div>

<div class="row">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-desktop stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_equipos; ?></div>
                    <div class="stat-label">Total de equipos</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card stat-available">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-circle-check stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $disp_disponible; ?></div>
                    <div class="stat-label">Disponibles</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card stat-assigned">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-user-check stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $disp_asignado; ?></div>
                    <div class="stat-label">Asignados</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card stat-maint">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-screwdriver-wrench stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $disp_mantenim; ?></div>
                    <div class="stat-label">En mantenimiento</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card stat-retired">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-box-archive stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $disp_baja; ?></div>
                    <div class="stat-label">Dados de baja</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-users stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_usuarios; ?></div>
                    <div class="stat-label">Usuarios del sistema</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center">
                <div class="me-3"><i class="fas fa-id-card stat-icon"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_empleados; ?></div>
                    <div class="stat-label">Empleados (<?php echo $total_activos; ?> activos)</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">
                Equipos modificados recientemente
                <span class="card-header-tools">
                    <a href="<?php echo url('equipment/index.php'); ?>" class="text-decoration-none">Ver todos</a>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Inventario</th>
                            <th>Equipo</th>
                            <th>Marca</th>
                            <th>Estado</th>
                            <th>Actualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ultimos) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Sin equipos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($ultimos as $eq): ?>
                                <tr>
                                    <td class="mono">
                                        <a href="<?php echo url('equipment/view.php?id=' . (int) $eq['id']); ?>">
                                            <?php echo e($eq['inventory_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo e($eq['model']); ?></td>
                                    <td class="small"><?php echo e($eq['marca']); ?></td>
                                    <td><?php echo status_badge($eq['status']); ?></td>
                                    <td class="text-muted small nowrap"><?php echo format_datetime($eq['updated_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Inventario por categoria</div>
            <div class="card-body">
                <?php
                $max_cat = 0;
                foreach ($por_categoria as $c) {
                    if ($c['total'] > $max_cat) { $max_cat = (int) $c['total']; }
                }
                foreach ($por_categoria as $c):
                    $pct = $max_cat > 0 ? round(($c['total'] / $max_cat) * 100) : 0;
                ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?php echo e($c['nombre']); ?></span>
                            <strong><?php echo (int) $c['total']; ?></strong>
                        </div>
                        <div class="progress" style="height:9px;">
                            <div class="progress-bar bg-primary" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Equipos por marca</div>
            <div class="card-body">
                <ul class="timeline-list">
                    <?php foreach ($por_marca as $m): ?>
                        <li>
                            <div class="d-flex justify-content-between">
                                <span><?php echo e($m['nombre']); ?></span>
                                <span class="badge bg-secondary"><?php echo (int) $m['total']; ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Actividad reciente del sistema</div>
            <div class="card-body">
                <?php if (count($actividad) === 0): ?>
                    <p class="text-muted text-center py-3 mb-0">Sin actividad registrada.</p>
                <?php else: ?>
                    <ul class="timeline-list">
                        <?php foreach ($actividad as $a): ?>
                            <li>
                                <div class="d-flex justify-content-between">
                                    <span class="small">
                                        <span class="badge bg-secondary me-1"><?php echo e($a['accion']); ?></span>
                                        <?php echo e($a['descripcion']); ?>
                                    </span>
                                </div>
                                <div class="text-muted" style="font-size:11px;">
                                    <?php echo e($a['modulo']); ?> &middot; <?php echo format_datetime($a['created_at']); ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
