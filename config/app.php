<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * config/app.php - Configuracion general, sesion, autenticacion y helpers
 */

// ---------------------------------------------------------------------
// Zona horaria
// ---------------------------------------------------------------------
// MariaDB corre en la zona del sistema (CST, UTC-6) y las columnas TIMESTAMP
// se interpretan en esa zona. Sin esta llamada PHP usa UTC, y los dias que
// calcula la aplicacion se adelantan un dia frente a lo que ve la base de
// datos: por la tarde el encabezado mostraria la fecha del dia siguiente.
// America/Mexico_City es UTC-6 todo el ano, igual que CST en el servidor.
date_default_timezone_set('America/Mexico_City');

// ---------------------------------------------------------------------
// Configuracion
// ---------------------------------------------------------------------
define('APP_NAME', 'SIGI');
define('APP_SUBTITLE', 'Sistema de Gestion de Inventario Informatico');
define('APP_COMPANY', 'Sistemas y Tecnología');
define('APP_VERSION', '1.0.0');

/**
 * Marca de que la aplicacion fue inicializada correctamente.
 * Los archivos incluidos comprueban esta constante para evitar ser
 * ejecutados directamente desde el navegador.
 */
define('SIGI_APP', true);

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------
// Errores
// ---------------------------------------------------------------------
// Entorno de produccion: no mostrar errores al navegador.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// ---------------------------------------------------------------------
// Sesion
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('SIGISESSID');
    session_start();
}

// ---------------------------------------------------------------------
// Rutas base
// ---------------------------------------------------------------------
define('BASE_URL', '/fa/sistemas');
define('DOC_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', DOC_ROOT . '/uploads');
define('DOCUMENT_DIR', DOC_ROOT . '/documents');

/** Construye una URL absoluta dentro de la aplicacion. */
function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Ejecuta un archivo de la base de datos. */
function db_file($rel)
{
    return DOC_ROOT . '/database/' . $rel;
}

// ---------------------------------------------------------------------
// Helpers de salida
// ---------------------------------------------------------------------

/** Escapa texto para HTML. Se usa en TODAS las vistas salvo donde el
 *  laboratorio necesita deliberadamente NO escapar (LAB-04). */
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/** Obtiene un valor de $_GET de forma segura. */
function input_get($key, $default = '')
{
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/** Obtiene un valor de $_POST de forma segura. */
function input_post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/** Formatea una fecha de la BD (Y-m-d) a formato legible. */
function format_date($date)
{
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
}

/** Formatea una fecha y hora de la BD. */
function format_datetime($datetime)
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

/** Formatea un monto. */
function format_money($amount)
{
    if ($amount === null || $amount === '') {
        return '-';
    }
    return '$' . number_format((float) $amount, 2, ',', '.');
}

/** Formatea un tamano en bytes como texto legible. */
function format_bytes($bytes)
{
    $bytes = (float) $bytes;
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i     = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

/** Devuelve la clase de badge CSS segun el estado. */
function status_badge($status)
{
    $map = array(
        'Disponible'    => 'bg-success',
        'Asignado'      => 'bg-primary',
        'Mantenimiento' => 'bg-warning text-dark',
        'Baja'          => 'bg-secondary',
        'Activo'        => 'bg-success',
        'Inactivo'      => 'bg-secondary',
        'Pendiente'     => 'bg-warning text-dark',
        'En Proceso'    => 'bg-info text-dark',
        'Completado'    => 'bg-success',
    );

    $class = isset($map[$status]) ? $map[$status] : 'bg-light text-dark';

    return '<span class="badge ' . $class . '">' . e($status) . '</span>';
}

/** IP del cliente que realizo la peticion. */
function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/** User agent del cliente. */
function client_agent()
{
    return isset($_SERVER['HTTP_USER_AGENT'])
        ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
        : '';
}

// ---------------------------------------------------------------------
// Mensajes flash
// ---------------------------------------------------------------------
function flash_set($type, $message)
{
    $_SESSION['flash'] = array('type' => $type, 'message' => $message);
}

function flash_get()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------------
// Autorizacion
// ---------------------------------------------------------------------

/** Indica si hay sesion iniciada. */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Devuelve los datos del usuario en sesion.
 */
function current_user()
{
    static $user = null;

    if (!is_logged_in()) {
        return null;
    }

    if ($user === null) {
        $user = db_row(
            "SELECT u.*, r.name AS role_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = ?",
            array($_SESSION['user_id'])
        );

        if ($user === null) {
            // El usuario fue borrado o desactivado mientras tenia sesion abierta.
            session_destroy();
            return null;
        }
    }

    return $user;
}

/** Devuelve el id del rol del usuario en sesion. */
function current_role_id()
{
    $u = current_user();
    return $u ? (int) $u['role_id'] : 0;
}

/** Indica si el usuario en sesion tiene uno de los roles indicados. */
function has_role($roles)
{
    if (!is_array($roles)) {
        $roles = array($roles);
    }
    return in_array(current_role_id(), $roles, true);
}

function is_admin()
{
    return has_role(1);
}

/**
 * Exige sesion iniciada. Redirige a login.php si no la hay.
 */
function require_login()
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url('login.php'));
        exit;
    }
}

/**
 * Exige uno de los roles indicados. Si no lo tiene, responde 403.
 */
function require_role($roles)
{
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);
        include DOC_ROOT . '/403.php';
        exit;
    }
}

/**
 * Redirige a otra pagina y termina.
 */
function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}
