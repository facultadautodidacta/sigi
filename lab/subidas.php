<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * lab/subidas.php - Carga de evidencia adjunta a ordenes de trabajo
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-06 (Subida de archivos sin restriccion)
 *
 *   El formulario acepta cualquier archivo. No se valida la extension,
 *   ni el tipo MIME real, ni el contenido, ni el tamano. El archivo se
 *   guarda con su nombre original dentro de uploads/, que esta bajo el
 *   document root y por lo tanto es accesible (y ejecutable) por URL.
 *
 *   Que faltaria en el camino correcto:
 *
 *       $permitidas = array('pdf', 'png', 'jpg', 'docx');
 *       $info = new finfo(FILEINFO_MIME_TYPE);
 *       $mime = $info->file($tmp);
 *       $ext  = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
 *       if (!in_array($ext, $permitidas, true)) {
 *           $errores[] = 'Extension no permitida.';
 *       }
 *       // ademas: comparar el MIME real, limite de tamano, y guardar
 *       // fuera del document root con un nombre generado.
 *
 *   Un .php que se sube queda en http://<host>/fa/sistemas/uploads/<archivo>
 *   y el servidor lo ejecuta. Eso convierte una subida sin control en
 *   ejecucion remota de codigo.
 *
 *   Ver documents/LAB-06-Unrestricted-Upload.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_role(array(1, 2));

$page_title  = 'Adjuntos';
$page_module = 'lab';

// Directorio de destino. VULNERABLE: esta bajo el document root.
$destino_dir = __DIR__ . '/../uploads/';
$destino_url = url('uploads/');

$errores = array();
$subida  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['adjunto'])) {
    $f = $_FILES['adjunto'];

    if ($f['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'La carga fallo (codigo ' . $f['error'] . ').';
    } else {
        // -----------------------------------------------------------------
        // SIN VALIDACION: se usan $f['name'] tal cual y no se revisa ni la
        // extension ni el tipo real. LAB-06.
        // -----------------------------------------------------------------
        $nombre_destino = $destino_dir . basename($f['name']);

        if (file_exists($nombre_destino)) {
            $errores[] = 'Ya existe un archivo con ese nombre en el servidor.';
        } elseif (!move_uploaded_file($f['tmp_name'], $nombre_destino)) {
            $errores[] = 'No se pudo guardar el archivo en el servidor.';
        } else {
            chmod($nombre_destino, 0644);
            $subida = true;

            audit_log('CREATE', 'lab',
                ' subio el archivo ' . $f['name'] . ' (' . $f['size'] . ' bytes, tipo ' . $f['type'] . ')');
        }
    }
}

// Lista lo que hay ahora mismo en el directorio de destino.
$archivos = array();
foreach (glob($destino_dir . '*') as $ruta) {
    if (is_file($ruta)) {
        $archivos[] = array(
            'nombre' => basename($ruta),
            'bytes'  => filesize($ruta),
            'fecha'  => filemtime($ruta),
        );
    }
}
usort($archivos, function ($a, $b) {
    return $b['fecha'] - $a['fecha'];
});

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('lab/index.php'); ?>">Laboratorio</a> &rsaquo; Adjuntos
        </div>
        <h1>Adjuntos de Ordenes de Trabajo</h1>
        <div class="ph-subtitle">Evidencia fotografica y documentos del equipo tecnico</div>
    </div>
</div>

<?php if ($subida): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-1"></i>
        Archivo almacenado. Queda disponible en
        <a href="<?php echo e($destino_url . rawurlencode($_FILES['adjunto']['name'])); ?>" target="_blank"><?php echo e($destino_url . $_FILES['adjunto']['name']); ?></a>
    </div>
<?php endif; ?>

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
            <div class="card-header">Subir archivo</div>
            <div class="card-body">
                <form method="post" action="<?php echo url('lab/subidas.php'); ?>" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label" for="adjunto">Archivo adjunto</label>
                        <input type="file" class="form-control" id="adjunto" name="adjunto" required>
                        <div class="form-text">Tamano maximo recomendado: 5 MB</div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="fas fa-upload me-1"></i>Subir
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Archivos almacenados</div>
            <div class="card-body p-0">
                <?php if (count($archivos) === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        No hay archivos en el directorio de destino.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Tamano</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archivos as $a): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo e($destino_url . rawurlencode($a['nombre'])); ?>"
                                               target="_blank" class="text-decoration-none"><?php echo e($a['nombre']); ?></a>
                                        </td>
                                        <td class="small text-muted"><?php echo e(format_bytes($a['bytes'])); ?></td>
                                        <td class="small text-muted"><?php echo format_datetime(date('Y-m-d H:i:s', $a['fecha'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
