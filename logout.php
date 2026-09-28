<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * logout.php - Cierre de sesion
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/audit.php';

// Registra la salida antes de destruir la sesion.
if (is_logged_in()) {
    audit_log('LOGOUT', 'login', 'Cierre de sesion del usuario: ' . $_SESSION['username']);
}

$_SESSION = array();

// Elimina la cookie de sesion.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Vuelve a la pantalla de login.
header('Location: ' . url('login.php'));
exit;
