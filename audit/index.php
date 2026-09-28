<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * audit/index.php - Visor de auditoria y de intentos de acceso
 *
 * Modulo de lectura. Admin y Tecnico.
 *
 * Este visor es la evidencia del laboratorio: permite ver, en el mismo
 * lugar y con los mismos filtros, lo que registro cada escenario. En el
 * Escenario A (sin SafeLine) la tabla de intentos muestra la fuerza bruta
 * completa; en el Escenario B (con SafeLine) la misma tabla queda vacia
 * porque las peticiones nunca llegaron a la aplicacion.
 *
 * Ver documents/LAB-09-*.md
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(array(1, 2));

$page_title  = 'Auditoria';
$page_module = 'audit';

$pestana  = input_get('tab', 'actividad') === 'accesos' ? 'accesos' : 'actividad';
$pagina   = max(1, (int) input_get('page', 1));
$por_pagina = 25;

$acciones_disponibles = db_all("SELECT DISTINCT accion FROM audit_log ORDER BY accion");
$modulos_disponibles  = db_all("SELECT DISTINCT modulo FROM audit_log ORDER BY modulo");

$where  = array('1=1');
$params = array();

if ($pestana === 'actividad') {
    $f_usuario = input_get('usuario');
    $f_accion  = input_get('accion');
    $f_modulo  = input_get('modulo');
    $f_buscar  = trim(input_get('q'));
    $f_desde   = input_get('desde');
    $f_hasta   = input_get('hasta');

    if ($f_usuario !== '') {
        $where[]  = 'usuario = ?';
        $params[] = $f_usuario;
    }
    if ($f_accion !== '') {
        $where[]  = 'accion = ?';
        $params[] = $f_accion;
    }
    if ($f_modulo !== '') {
        $where[]  = 'modulo = ?';
        $params[] = $f_modulo;
    }
    if ($f_buscar !== '') {
        $where[]  = 'descripcion LIKE ?';
        $params[] = '%' . $f_buscar . '%';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_desde)) {
        $where[]  = 'created_at >= ?';
        $params[] = $f_desde . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_hasta)) {
        $where[]  = 'created_at <= ?';
        $params[] = $f_hasta . ' 23:59:59';
    }
} else {
    $f_resultado = input_get('resultado');
    $f_usuario_la = input_get('usuario_login');
    $f_ip        = trim(input_get('ip'));

    if ($f_resultado === 'ok' || $f_resultado === 'fail') {
        $where[]  = 'success = ?';
        $params[] = $f_resultado === 'ok' ? 1 : 0;
    }
    if ($f_usuario_la !== '') {
        $where[]  = 'username = ?';
        $params[] = $f_usuario_la;
    }
    if ($f_ip !== '') {
        $where[]  = 'ip = ?';
        $params[] = $f_ip;
    }
}

$where_sql = implode(' AND ', $where);
$tabla     = $pestana === 'actividad' ? 'audit_log' : 'login_attempts';

$total         = (int) db_value("SELECT COUNT(*) FROM $tabla WHERE $where_sql", $params);
$offset        = ($pagina - 1) * $por_pagina;
$total_paginas = max(1, ceil($total / $por_pagina));

$registros = db_all(
    "SELECT * FROM $tabla WHERE $where_sql ORDER BY created_at DESC, id DESC LIMIT $por_pagina OFFSET $offset",
    $params
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo; Auditoria
        </div>
        <h1>Auditoria del Sistema</h1>
        <div class="ph-subtitle">
            <?php echo number_format($total, 0, '.', ','); ?> registro(s)
            en <?php echo $pestana === 'actividad' ? 'actividad' : 'intentos de acceso'; ?>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?php echo $pestana === 'actividad' ? 'active' : ''; ?>"
           href="<?php echo url('audit/index.php?tab=actividad'); ?>">
            <i class="fas fa-list me-1"></i>Actividad
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $pestana === 'accesos' ? 'active' : ''; ?>"
           href="<?php echo url('audit/index.php?tab=accesos'); ?>">
            <i class="fas fa-right-to-bracket me-1"></i>Intentos de acceso
        </a>
    </li>
</ul>

<div class="filter-panel">
    <form method="get" action="<?php echo url('audit/index.php'); ?>" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="<?php echo e($pestana); ?>">

        <?php if ($pestana === 'actividad'): ?>
            <div class="col-md-2">
                <label class="form-label" for="accion">Accion</label>
                <select class="form-select" id="accion" name="accion">
                    <option value="">Todas</option>
                    <?php foreach ($acciones_disponibles as $a): ?>
                        <option value="<?php echo e($a['accion']); ?>" <?php echo input_get('accion') === $a['accion'] ? 'selected' : ''; ?>>
                            <?php echo e($a['accion']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="modulo">Modulo</label>
                <select class="form-select" id="modulo" name="modulo">
                    <option value="">Todos</option>
                    <?php foreach ($modulos_disponibles as $m): ?>
                        <option value="<?php echo e($m['modulo']); ?>" <?php echo input_get('modulo') === $m['modulo'] ? 'selected' : ''; ?>>
                            <?php echo e($m['modulo']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="q">Descripcion</label>
                <input type="text" class="form-control" id="q" name="q" value="<?php echo e(input_get('q')); ?>"
                       placeholder="Texto de la descripcion">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="desde">Desde</label>
                <input type="date" class="form-control" id="desde" name="desde" value="<?php echo e(input_get('desde')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="hasta">Hasta</label>
                <input type="date" class="form-control" id="hasta" name="hasta" value="<?php echo e(input_get('hasta')); ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-secondary btn-sm w-100">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        <?php else: ?>
            <div class="col-md-3">
                <label class="form-label" for="resultado">Resultado</label>
                <select class="form-select" id="resultado" name="resultado">
                    <option value="">Todos</option>
                    <option value="ok"   <?php echo input_get('resultado') === 'ok'   ? 'selected' : ''; ?>>Exitosos</option>
                    <option value="fail" <?php echo input_get('resultado') === 'fail' ? 'selected' : ''; ?>>Fallidos</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="usuario_login">Usuario</label>
                <input type="text" class="form-control mono" id="usuario_login" name="usuario_login"
                       value="<?php echo e(input_get('usuario_login')); ?>" placeholder="admin">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ip">Direccion IP</label>
                <input type="text" class="form-control mono" id="ip" name="ip" value="<?php echo e(input_get('ip')); ?>"
                       placeholder="127.0.0.1">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary btn-sm w-100">
                    <i class="fas fa-filter me-1"></i>Filtrar
                </button>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <?php if ($pestana === 'actividad'): ?>
                <table class="table table-hover table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:150px;">Fecha</th>
                            <th style="width:110px;">Usuario</th>
                            <th style="width:100px;">Accion</th>
                            <th style="width:110px;">Modulo</th>
                            <th style="width:130px;">IP</th>
                            <th>Descripcion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registros) === 0): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="fas fa-inbox"></i>Sin registros para estos filtros.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td class="small text-muted"><?php echo format_datetime($r['created_at']); ?></td>
                                    <td class="small mono"><?php echo e($r['usuario']); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo e($r['accion']); ?></span></td>
                                    <td class="small"><?php echo e($r['modulo']); ?></td>
                                    <td class="small mono text-muted"><?php echo e($r['ip']); ?></td>
                                    <td class="small"><?php echo e($r['descripcion']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="table table-hover table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:150px;">Fecha</th>
                            <th style="width:160px;">Usuario</th>
                            <th style="width:130px;">IP</th>
                            <th style="width:110px;">Resultado</th>
                            <th>Agente</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registros) === 0): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="fas fa-shield-halved"></i>Ningun intento llego a la aplicacion.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($registros as $r): ?>
                                <tr>
                                    <td class="small text-muted"><?php echo format_datetime($r['created_at']); ?></td>
                                    <td class="small mono"><?php echo e($r['username']); ?></td>
                                    <td class="small mono text-muted"><?php echo e($r['ip']); ?></td>
                                    <td>
                                        <?php if ((int) $r['success'] === 1): ?>
                                            <span class="badge bg-success">Exitoso</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Fallido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?php echo e($r['user_agent']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Pagina <?php echo (int) $pagina; ?> de <?php echo (int) $total_paginas; ?></span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('audit/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina - 1)))); ?>">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo $i === $pagina ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo url('audit/index.php?' . http_build_query(array_merge($_GET, array('page' => $i)))); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $pagina >= $total_paginas ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo url('audit/index.php?' . http_build_query(array_merge($_GET, array('page' => $pagina + 1)))); ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
