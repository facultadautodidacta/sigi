<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * equipment/search.php - Buscador avanzado de equipos
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-02 (SQL Injection en busqueda)
 *   El buscador llama a lab_search_equipment() de config/lab_search.php,
 *   que concatena el termino en el SQL. La implementacion correcta
 *   secure_search_equipment() existe en ese mismo archivo pero no se usa.
 *   Ver documents/LAB-02-SQLi-Busqueda.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_once __DIR__ . '/../config/lab_search.php';
require_login();

$page_title  = 'Buscar equipos';
$page_module = 'equipment';

$termino   = trim(input_get('q'));
$resultado = array();
$error_sql = '';
$buscado   = false;

// Filtros adicionales del buscador. Se aplican aparte del termino, con
// consultas preparadas: solo el campo de texto es el punto vulnerable.
$por_estado = input_get('estado');
$por_categoria = input_get('categoria');

if (isset($_GET['q'])) {
    $buscado = true;

    try {
        // ESTA ES LA LLAMADA VULNERABLE (LAB-02).
        $resultado = lab_search_equipment($termino);
    } catch (mysqli_sql_exception $ex) {
        // Un payload que rompe la sintaxis produce un error de MySQL. Se
        // muestra tal cual: en Escenario A es la senal de que la consulta
        // se esta ejecutando con la entrada del usuario dentro.
        $error_sql = $ex->getMessage();
        $resultado = array();
    }

    if ($error_sql === '') {
        // Los filtros adicionales se aplican sobre el resultado del
        // termino, tambien en memoria para no tocar el SQL vulnerable.
        if ($por_estado !== '' && in_array($por_estado, array('Disponible', 'Asignado', 'Mantenimiento', 'Baja'))) {
            $resultado = array_values(array_filter($resultado, function ($r) use ($por_estado) {
                return $r['status'] === $por_estado;
            }));
        }
        if ($por_categoria !== '' && is_numeric($por_categoria)) {
            $cat = (int) $por_categoria;
            $resultado = array_values(array_filter($resultado, function ($r) use ($cat) {
                $q = db_row("SELECT category_id FROM equipment WHERE id = ?", array((int) $r['id']));
                return $q && (int) $q['category_id'] === $cat;
            }));
        }
    }

    audit_log('SEARCH', 'equipment', 'Busqueda de equipos con el termino: ' . $termino);
}

$categorias = db_all("SELECT id, nombre FROM categories WHERE estado = 'Activo' ORDER BY nombre");

// Si la peticion lleva marcadas las 15 columnas del SELECT, el UNION de
// LAB-02 puede completar las suyas. Solo se usa para explicar el ataque.
$columnas_union = isset($_GET['cols']) ? max(1, min(20, (int) $_GET['cols'])) : 6;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('equipment/index.php'); ?>">Equipos</a> &rsaquo; Buscar
        </div>
        <h1>Buscador de Equipos</h1>
        <div class="ph-subtitle">Busqueda por texto libre sobre modelo, inventario y ubicacion</div>
    </div>
</div>

<div class="filter-panel">
    <form method="get" action="<?php echo url('equipment/search.php'); ?>" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label" for="q">Termino de busqueda</label>
            <input type="text" class="form-control mono" id="q" name="q" value="<?php echo e($termino); ?>"
                   placeholder="Dell, INV-000001, Rack 1..." autofocus>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="estado">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos</option>
                <?php foreach (array('Disponible', 'Asignado', 'Mantenimiento', 'Baja') as $s): ?>
                    <option value="<?php echo e($s); ?>" <?php echo $por_estado === $s ? 'selected' : ''; ?>>
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
                    <option value="<?php echo (int) $c['id']; ?>" <?php echo $por_categoria == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo e($c['nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary btn-sm w-100" style="background-color:#1f4e79;border-color:#1f4e79;">
                <i class="fas fa-magnifying-glass me-1"></i>Buscar
            </button>
        </div>
    </form>
</div>

<?php if ($buscado && $error_sql !== ''): ?>
    <div class="alert alert-danger">
        <strong>Error de base de datos:</strong>
        <div class="mono small mt-1"><?php echo e($error_sql); ?></div>
    </div>
<?php endif; ?>

<?php if ($buscado && $error_sql === ''): ?>
    <div class="alert alert-<?php echo count($resultado) > 0 ? 'success' : 'secondary'; ?>">
        <i class="fas fa-info-circle me-1"></i>
        Se encontraron <strong><?php echo count($resultado); ?></strong> equipo(s) para
        <span class="mono"><?php echo e($termino === '' ? '(vacio)' : $termino); ?></span>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width:110px;">Inventario</th>
                            <th>Modelo</th>
                            <th>Ubicacion</th>
                            <th>Estado</th>
                            <th class="text-end">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($resultado) === 0): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-magnifying-glass"></i>
                                        Ningun equipo coincide con la busqueda.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resultado as $r): ?>
                                <tr>
                                    <td class="mono small">
                                        <?php if (isset($r['id'])): ?>
                                            <a href="<?php echo url('equipment/view.php?id=' . (int) $r['id']); ?>">
                                                <?php echo e($r['inventory_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo e($r['inventory_number']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e($r['model']); ?></td>
                                    <td class="small text-muted"><?php echo e($r['location']); ?></td>
                                    <td><?php echo status_badge($r['status']); ?></td>
                                    <td class="text-end small"><?php echo format_money($r['price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
