<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * config/audit.php - Registro de auditoria
 *
 * Acciones: LOGIN LOGOUT CREATE UPDATE DELETE ASSIGN RETURN MAINTENANCE
 */

require_once __DIR__ . '/app.php';

/**
 * Registra una accion en la tabla audit_log.
 *
 * @param string $accion      LOGIN, CREATE, UPDATE, DELETE, ASSIGN, ...
 * @param string $modulo      login, users, equipment, assignments, ...
 * @param string $descripcion Texto libre del detalle
 * @param string $usuario     username; si se omite, el de la sesion o SISTEMA
 */
function audit_log($accion, $modulo, $descripcion = '', $usuario = null)
{
    if ($usuario === null) {
        $u = current_user();
        $usuario = $u ? $u['username'] : 'SISTEMA';
    }

    try {
        db_exec(
            "INSERT INTO audit_log (usuario, accion, modulo, ip, descripcion)
             VALUES (?, ?, ?, ?, ?)",
            array(
                $usuario,
                $accion,
                $modulo,
                client_ip(),
                mb_substr($descripcion, 0, 500)
            )
        );
    } catch (Exception $ex) {
        // La auditoria nunca debe romper la operacion principal.
        error_log('audit_log fallo: ' . $ex->getMessage());
    }
}

/**
 * Registra un intento de autenticacion en login_attempts.
 * Laboratorio LAB-09: soporte para pruebas de fuerza bruta.
 *
 * @param string $username Usuario tal como se envio en el formulario
 * @param bool   $success  Resultado del intento
 */
function log_login_attempt($username, $success)
{
    try {
        db_exec(
            "INSERT INTO login_attempts (username, ip, success, user_agent)
             VALUES (?, ?, ?, ?)",
            array(
                mb_substr($username, 0, 100),
                client_ip(),
                $success ? 1 : 0,
                client_agent()
            )
        );
    } catch (Exception $ex) {
        error_log('log_login_attempt fallo: ' . $ex->getMessage());
    }
}
