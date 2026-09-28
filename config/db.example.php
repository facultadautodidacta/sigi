<?php
/**
 * SIGI - Configuracion de ejemplo para la conexion mysqli.
 * Copiar como db.php y completar con credenciales locales.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'sigi');
define('DB_PASS', 'CHANGE_ME');
define('DB_NAME', 'sigi');
define('DB_CHARSET', 'utf8mb4');

function db()
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            die('Error de conexion a la base de datos: ' . $conn->connect_error);
        }

        $conn->set_charset(DB_CHARSET);
    }

    return $conn;
}

function db_query($sql, $params = array())
{
    $stmt = db()->prepare($sql);

    if ($stmt === false) {
        die('Error en prepare: ' . db()->error);
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    return $stmt;
}

function db_all($sql, $params = array())
{
    $stmt = db_query($sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function db_row($sql, $params = array())
{
    $stmt = db_query($sql, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? $row : null;
}

function db_value($sql, $params = array())
{
    $stmt = db_query($sql, $params);
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();

    return $row ? $row[0] : null;
}

function db_exec($sql, $params = array())
{
    $stmt = db_query($sql, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected;
}

function db_insert_id()
{
    return db()->insert_id;
}
