<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * lab/red.php - Diagnostico de conectividad
 *
 * =====================================================================
 * VULNERABILIDAD DELIBERADA: LAB-07 (Inyeccion de comandos)
 *
 *   El campo ?host= se concatena en una cadena de shell y se ejecuta
 *   con exec(). No se valida que sea una direccion IPv4 valida ni se
 *   escapan los metacaracteres de shell con escapeshellarg().
 *
 *   Que faltaria en el camino correcto:
 *
 *       $host = input_get('host', '127.0.0.1');
 *       if (!filter_var($host, FILTER_VALIDATE_IP)) {
 *           $errores[] = 'Direccion IP no valida.';
 *       }
 *       $comando = 'ping -c 3 -W 2 ' . escapeshellarg($host);
 *
 *   Con ; o $() se encadena cualquier orden del sistema. SafeLine
 *   detecta los metacaracteres y bloquea la peticion.
 *
 *   Ver documents/LAB-07-Command-Injection.md
 * =====================================================================
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$page_title  = 'Diagnostico de red';
$page_module = 'lab';

// El formulario usa POST, pero la pagina acepta GET o POST para que el
// payload se pueda enviar como enlace o como cuerpo en las capturas.
$entrada = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? input_post('host', input_get('host'))
    : input_get('host', '127.0.0.1');

$host      = trim($entrada !== '' ? $entrada : '127.0.0.1');
$salida    = '';
$ejecutado = false;
$errores   = array();

// ---------------------------------------------------------------------
// VULNERABILIDAD (LAB-07): se concatena $host sin escapeshellarg().
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($host === '') {
        $host = '127.0.0.1';
    }

    $comando = 'ping -c 3 -W 2 ' . $host;

    $salida    = array();
    $codigo    = 0;
    $ejecutado = true;

    exec($comando . ' 2>&1', $salida, $codigo);
    $salida = implode("\n", $salida);

    audit_log('MAINTENANCE', 'lab',
        ' ejecuto un diagnostico de red hacia ' . $host);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo;
            <a href="<?php echo url('lab/index.php'); ?>">Laboratorio</a> &rsaquo; Diagnostico de red
        </div>
        <h1>Diagnostico de Conectividad</h1>
        <div class="ph-subtitle">Verifica si un equipo de la red responde</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Ejecutar prueba</div>
            <div class="card-body">
                <form method="post" action="<?php echo url('lab/red.php'); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="host">Direccion IP o nombre de host</label>
                        <input type="text" class="form-control mono" id="host" name="host"
                               value="<?php echo e($host); ?>" placeholder="127.0.0.1">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="fas fa-network-wired me-1"></i>Ejecutar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Resultado</div>
            <div class="card-body">
                <?php if (!$ejecutado): ?>
                    <div class="empty-state">
                        <i class="fas fa-terminal"></i>
                        Sin ejecuciones en esta vista.
                    </div>
                <?php else: ?>
                    <pre class="salida-terminal mb-0"><?php echo e($salida); ?></pre>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
