<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * users/user_form.php - Logica compartida de alta y edicion de usuarios
 *
 * Uso:
 *   $user_row   = array()  (nuevo)  o  fila de users (editar)
 *   $form_action= 'users/create.php' o 'users/edit.php'
 *   $titulo, $submit_text  se definen antes de incluir
 */

if (!defined('SIGI_APP')) {
    die('Acceso directo no permitido.');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/audit.php';

$roles  = db_all("SELECT id, name, description FROM roles ORDER BY id");
$es_edicion = !empty($user_row);
$errores   = array();
$old       = array(
    'nombre'   => $es_edicion ? $user_row['nombre']   : '',
    'apellido' => $es_edicion ? $user_row['apellido'] : '',
    'email'    => $es_edicion ? $user_row['email']    : '',
    'username' => $es_edicion ? $user_row['username'] : '',
    'role_id'  => $es_edicion ? $user_row['role_id']  : '',
    'estado'   => $es_edicion ? $user_row['estado']   : 'Activo',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger y limpiar los datos enviados.
    $old['nombre']   = trim(input_post('nombre'));
    $old['apellido'] = trim(input_post('apellido'));
    $old['email']    = trim(input_post('email'));
    $old['username'] = trim(input_post('username'));
    $old['role_id']  = input_post('role_id');
    $old['estado']   = input_post('estado');
    $password  = input_post('password');
    $password2 = input_post('password2');

    // -----------------------------------------------------------------
    // Validaciones
    // -----------------------------------------------------------------
    if ($old['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if ($old['apellido'] === '') {
        $errores[] = 'El apellido es obligatorio.';
    }
    if ($old['email'] === '') {
        $errores[] = 'El correo electronico es obligatorio.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El formato del correo electronico no es valido.';
    } else {
        // Email unico
        $existe = db_value(
            "SELECT id FROM users WHERE email = ? AND id <> ?",
            array($old['email'], $es_edicion ? (int) $user_row['id'] : 0)
        );
        if ($existe) {
            $errores[] = 'Ya existe un usuario registrado con ese correo electronico.';
        }
    }

    if ($old['username'] === '') {
        $errores[] = 'El nombre de usuario es obligatorio.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $old['username'])) {
        $errores[] = 'El usuario solo puede contener letras, numeros, punto, guion y guion bajo (3 a 50 caracteres).';
    } else {
        // Username unico
        $existe = db_value(
            "SELECT id FROM users WHERE username = ? AND id <> ?",
            array($old['username'], $es_edicion ? (int) $user_row['id'] : 0)
        );
        if ($existe) {
            $errores[] = 'Ese nombre de usuario ya esta en uso.';
        }
    }

    if ($old['role_id'] === '' || !in_array((int) $old['role_id'], array(1, 2, 3), true)) {
        $errores[] = 'Debe seleccionar un rol.';
    }
    if (!in_array($old['estado'], array('Activo', 'Inactivo'), true)) {
        $errores[] = 'El estado no es valido.';
    }

    // Contrasena
    if (!$es_edicion) {
        // En el alta la contrasena es obligatoria.
        if ($password === '') {
            $errores[] = 'La contrasena es obligatoria.';
        }
    } else {
        // En edicion solo se cambia si se escribe una nueva.
        // Si el campo queda vacio se conserva la contrasena actual.
    }

    if ($password !== '' && strlen($password) < 8) {
        $errores[] = 'La contrasena debe tener al menos 8 caracteres.';
    }
    if ($password !== '' && $password !== $password2) {
        $errores[] = 'La contrasena y su confirmacion no coinciden.';
    }

    // -----------------------------------------------------------------
    // Persistencia
    // -----------------------------------------------------------------
    if (count($errores) === 0) {
        if ($es_edicion) {
            // Actualizacion. El password solo cambia si se ingreso uno nuevo.
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db_exec(
                    "UPDATE users
                        SET nombre = ?, apellido = ?, email = ?, username = ?,
                            role_id = ?, estado = ?, password = ?, password_legacy = MD5(?)
                      WHERE id = ?",
                    array(
                        $old['nombre'], $old['apellido'], $old['email'], $old['username'],
                        (int) $old['role_id'], $old['estado'], $hash, $password,
                        (int) $user_row['id']
                    )
                );
            } else {
                db_exec(
                    "UPDATE users
                        SET nombre = ?, apellido = ?, email = ?, username = ?,
                            role_id = ?, estado = ?
                      WHERE id = ?",
                    array(
                        $old['nombre'], $old['apellido'], $old['email'], $old['username'],
                        (int) $old['role_id'], $old['estado'], (int) $user_row['id']
                    )
                );
            }

            audit_log('UPDATE', 'users',
                'Actualizo el usuario ' . $old['username'] . ' (id ' . (int) $user_row['id'] . ')');

            redirect('users/index.php?msg=updated');
        } else {
            // Alta
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db_exec(
                "INSERT INTO users (nombre, apellido, email, username, password, password_legacy, role_id, estado)
                 VALUES (?, ?, ?, ?, ?, MD5(?), ?, ?)",
                array(
                    $old['nombre'], $old['apellido'], $old['email'], $old['username'],
                    $hash, $password,
                    (int) $old['role_id'], $old['estado']
                )
            );

            $nuevo_id = db_insert_id();
            audit_log('CREATE', 'users',
                'Registro el usuario ' . $old['username'] . ' (id ' . $nuevo_id . ')');

            redirect('users/index.php?msg=created');
        }
    }
}
?>
