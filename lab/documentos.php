<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * lab/documentos.php - Visor de contratos y polizas
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-05 (Path Traversal / LFI)
 *
 *   El parametro ?archivo= se concatena directo a la ruta base y el
 *   contenido se imprime sin validar. No hay basename(), ni realpath()
 *   con comparacion, ni lista blanca de archivos permitidos.
 *
 *   Que faltaria en el camino correcto:
 *
 *       $permitidos = array('contrato-servicios.txt', 'polizas.md');
 *       $nombre = basename(input_get('archivo'));
 *       if (!in_array($nombre, $permitidos, true)) {
 *           http_response_code(400);
 *           exit('Archivo no permitido.');
 *       }
 *       $ruta = realpath(DOC_ROOT . '/lab/contratos/' . $nombre);
 *       if ($ruta === false || strpos($ruta, realpath(DOC_ROOT . '/lab/contratos')) !== 0) {
 *           http_response_code(403);
 *           exit('Ruta fuera del directorio permitido.');
 *       }
 *
 *   Con ../../../../etc/passwd se lee cualquier archivo que el usuario
 *   del servidor pueda abrir. SafeLine detiene la secuencia de ../ en el
 *   GET, y la lectura nunca llega a ocurrir.
 *
 *   Ver documents/LAB-05-Path-Traversal.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$page_title  = 'Documentos';
$page_module = 'lab';

$ruta_base = __DIR__ . '/contratos/';

// Archivos que la pagina ofrece en el menu lateral.
$documentos = db_all(
    "SELECT archivo, descripcion FROM lab_documentos ORDER BY id"
);

// Valor por defecto cuando no se envia el parametro.
$archivo = input_get('archivo', 'contrato-servicios.txt');
if ($archivo === '') {
    $archivo = 'contrato-servicios.txt';
}

$ruta_completa = $ruta_base . $archivo;

$existe = is_file($ruta_completa) && is_readable($ruta_completa);
$contenido = $existe ? @file_get_contents($ruta_completa) : false;
if ($contenido === false && $existe) {
    $contenido = '';
}

if ($existe) {
    audit_log('VIEW', 'lab',
        ' consulted el documento ' . $archivo . ' (tamano ' . strlen($contenido) . ' bytes)');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('lab/index.php'); ?>">Laboratorio</a> &rsaquo; Documentos
        </div>
        <h1>Documentos y Contratos</h1>
        <div class="ph-subtitle">Consulta de contratos marco y polizas vigentes</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Documentos disponibles</div>
            <div class="list-group list-group-flush">
                <?php foreach ($documentos as $d): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       href="<?php echo url('lab/documentos.php?archivo=' . urlencode($d['archivo'])); ?>">
                        <span>
                            <i class="fas fa-file-alt me-2 text-muted"></i><?php echo e($d['archivo']); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Consultar documento</div>
            <div class="card-body">
                <form method="get" action="<?php echo url('lab/documentos.php'); ?>" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label" for="archivo">Nombre del archivo</label>
                        <input type="text" class="form-control mono" id="archivo" name="archivo"
                               value="<?php echo e($archivo); ?>" placeholder="contrato-servicios.txt">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-secondary btn-sm w-100">
                            <i class="fas fa-folder-open me-1"></i>Abrir
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <span class="mono"><?php echo e($archivo); ?></span>
            </div>
            <div class="card-body">
                <?php if (!$existe): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-circle-question"></i>
                        El archivo no existe o no se puede leer.
                    </div>
                <?php else: ?>
                    <?php
                    // ------------------------------------------------------------
                    // CONTENIDO IMPRESO SIN ESCAPAR
                    // Cualquier etiqueta HTML o PHP del archivo se interpreta.
                    // ------------------------------------------------------------
                    ?>
                    <div class="documento-vista"><?php echo $contenido; ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
