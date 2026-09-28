<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * lab/index.php - Consola del laboratorio de seguridad
 *
 * Pagina explicita que agrupa las practicas de forma navegable, para
 * poder grabar cada una sin saltar entre modulos de negocio.
 *
 * Este indice NO es una vulnerabilidad: es el indice del catalogo de
 * pruebas. Los fallos estan en las paginas enlazadas.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';
require_login();

$page_title  = 'Laboratorio';
$page_module = 'lab';

$pruebas = array(
    array(
        'lab'      => 'LAB-01',
        'archivo'  => 'config/lab_auth.php',
        'ruta'     => 'login.php',
        'titulo'   => 'Inyeccion SQL en el acceso',
        'tipo'     => 'OWASP A03:2021 - Injection',
        'resumen'  => 'El formulario de acceso concatena el usuario en la consulta. Burp detecta el tiempo de respuesta y la respuesta de error.',
        'rol'      => 'Sin sesion',
    ),
    array(
        'lab'      => 'LAB-02',
        'archivo'  => 'equipment/search.php',
        'ruta'     => 'equipment/search.php?q=',
        'titulo'   => 'Inyeccion SQL en la busqueda de equipos',
        'tipo'     => 'OWASP A03:2021 - Injection',
        'resumen'  => 'La caja de busqueda del inventario concatena el termino. Prueba de tiempo con SLEEP y extraccion con UNION.',
        'rol'      => 'Autenticado',
    ),
    array(
        'lab'      => 'LAB-03',
        'archivo'  => 'employees/view.php',
        'ruta'     => 'employees/view.php?id=',
        'titulo'   => 'Acceso directo a objetos (IDOR)',
        'tipo'     => 'OWASP A01:2021 - Broken Access Control',
        'resumen'  => 'Recorre los id del 1 al 22 con un rol de solo lectura y lee los datos personales de toda la empresa.',
        'rol'      => 'Autenticado',
    ),
    array(
        'lab'      => 'LAB-04',
        'archivo'  => 'assignments/index.php',
        'ruta'     => 'assignments/create.php',
        'titulo'   => 'XSS almacenado en observaciones',
        'tipo'     => 'OWASP A03:2021 - Injection',
        'resumen'  => 'Guarda un script en la observacion de una asignacion y se ejecuta en el navegador de quien abra el listado.',
        'rol'      => 'Administrador o Tecnico',
    ),
    array(
        'lab'      => 'LAB-05',
        'archivo'  => 'lab/contratos/',
        'ruta'     => 'lab/documentos.php?archivo=',
        'titulo'   => 'Recorrido de rutas (LFI)',
        'tipo'     => 'OWASP A01:2021 - Broken Access Control',
        'resumen'  => 'El visor de contratos concatena el nombre de archivo. Con ../../../ se sale del directorio de contratos.',
        'rol'      => 'Autenticado',
    ),
    array(
        'lab'      => 'LAB-06',
        'archivo'  => 'uploads/',
        'ruta'     => 'lab/subidas.php',
        'titulo'   => 'Subida de archivos sin restriccion',
        'tipo'     => 'OWASP A04:2021 - Insecure Design',
        'resumen'  => 'El formulario acepta cualquier extension y deja el archivo en un directorio accesible por URL.',
        'rol'      => 'Autenticado',
    ),
    array(
        'lab'      => 'LAB-07',
        'archivo'  => 'lab/red.php',
        'ruta'     => 'lab/red.php?host=',
        'titulo'   => 'Inyeccion de comandos en el diagnostico',
        'tipo'     => 'OWASP A03:2021 - Injection',
        'resumen'  => 'La herramienta de diagnostico de red pasa la direccion a un comando de shell sin validar.',
        'rol'      => 'Autenticado',
    ),
    array(
        'lab'      => 'LAB-08',
        'archivo'  => 'login.php',
        'ruta'     => 'login.php',
        'titulo'   => 'Credenciales débiles y sin bloqueo',
        'tipo'     => 'OWASP A07:2021 - Identification and Authentication Failures',
        'resumen'  => 'Contrasenas predecibles, sin caducidad y sin bloqueo de cuenta tras varios intentos.',
        'rol'      => 'Sin sesion',
    ),
    array(
        'lab'      => 'LAB-09',
        'archivo'  => 'audit/',
        'ruta'     => 'audit/index.php',
        'titulo'   => 'Fuerza bruta sin deteccion ni registro',
        'tipo'     => 'OWASP A07:2021 - Identification and Authentication Failures',
        'resumen'  => 'Enumera usuarios y contrasenas sin limite de intentos. El visor de auditoria muestra lo que el sistema registro.',
        'rol'      => 'Autenticado',
    ),
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="breadcrumb-app">
            <a href="<?php echo url('dashboard.php'); ?>">Inicio</a> &rsaquo; Laboratorio
        </div>
        <h1>Laboratorio de Seguridad</h1>
        <div class="ph-subtitle">Practicas deliberadas para medir el antes y el despues de SafeLine</div>
    </div>
</div>

<div class="alert alert-warning">
    <strong>Aviso.</strong> Esta aplicacion es un objetivo de prueba y contiene fallos
    deliberados. No la expongas a una red real ni la publiques en Internet.
</div>

<div class="row g-3">
    <?php foreach ($pruebas as $p): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-danger"><?php echo e($p['lab']); ?></span>
                        <span class="text-muted small"><?php echo e($p['rol']); ?></span>
                    </div>
                    <h2 style="font-size:1.05rem;" class="mb-1"><?php echo e($p['titulo']); ?></h2>
                    <div class="text-muted small mb-2"><?php echo e($p['tipo']); ?></div>
                    <p class="small mb-2"><?php echo e($p['resumen']); ?></p>
                    <div class="mono text-muted small mb-3"><?php echo e($p['archivo']); ?></div>
                    <a href="<?php echo url($p['ruta']); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-play me-1"></i>Abrir practica
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
