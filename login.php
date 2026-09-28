<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * login.php - Autenticacion
 *
 * El formulario de acceso es el endpoint de LAB-01: SQL Injection con
 * bypass completo de autenticacion. La consulta vulnerable esta aislada en
 * config/lab_auth.php (funcion lab_login_vulnerable). Este archivo solo la
 * invoca.
 *
 * Ver SECURITY-LAB.md, seccion LAB-01.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/audit.php';
require_once __DIR__ . '/config/lab_auth.php';

// Si ya hay sesion iniciada, ir al dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = input_post('username');
    $password_input = input_post('password');

    if ($username_input === '' || $password_input === '') {
        $error = 'Debe ingresar usuario y contrasena.';
    } else {
        $user_row = lab_login_vulnerable($username_input, $password_input);

        if ($user_row === null) {
            // Mensaje unico para usuario inexistente y contrasena incorrecta.
            // Distinguirlos permitiria enumerar cuentas validas.
            $error = 'Usuario o contrasena incorrectos.';
            log_login_attempt($username_input, false);
            audit_log('LOGIN', 'login', 'Intento de acceso fallido para el usuario: ' . $username_input, $username_input);
        } else {
            // Usuario inactivo no puede iniciar sesion.
            if ($user_row['estado'] !== 'Activo') {
                $error = 'Usuario o contrasena incorrectos.';
                log_login_attempt($username_input, false);
                audit_log('LOGIN', 'login', 'Acceso denegado: usuario inactivo: ' . $username_input, $username_input);
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user_row['id'];
                $_SESSION['username'] = $user_row['username'];
                $_SESSION['role_id']  = (int) $user_row['role_id'];
                $_SESSION['login_time'] = time();

                log_login_attempt($username_input, true);
                audit_log('LOGIN', 'login', 'Acceso concedido a ' . $user_row['username'], $user_row['username']);

                // Destino posterior al login, si lo habia.
                $destino = 'dashboard.php';
                if (isset($_SESSION['redirect_after_login'])) {
                    $destino = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                }

                redirect($destino);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesion - <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/fontawesome.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/app.css'); ?>">
</head>
<body>

<div class="login-page">
    <div class="login-card">

        <div class="login-header">
            <i class="fas fa-boxes-stacked"></i>
            <h1><?php echo e(APP_NAME); ?></h1>
            <p><?php echo e(APP_SUBTITLE); ?></p>
        </div>

        <div class="login-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger py-2">
                    <i class="fas fa-exclamation-triangle me-1"></i><?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo url('login.php'); ?>" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label" for="username">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?php echo e($username_input); ?>" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Contrasena</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember" style="font-size:13px;">
                        Recordar mi usuario en este equipo
                    </label>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-1"></i>Iniciar sesion
                    </button>
                </div>
            </form>
        </div>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> <?php echo e(APP_COMPANY); ?> &middot; Soporte: Mesa de Servicios
        </div>
    </div>
</div>

</body>
</html>
