<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * config/lab_auth.php
 *
 * =====================================================================
 * LABORATORIO DE SEGURIDAD - VULNERABILIDAD DELIBERADA
 * =====================================================================
 *   ID    : LAB-01
 *   Tipo  : SQL Injection - Autenticacion (Login Bypass)
 *   Riesgo: CRITICO (CVSS 9.8)
 *   CWE   : CWE-89
 *   OWASP : A03:2021 - Injection
 *   Donde : login.php
 *   Ref   : SECURITY-LAB.md
 * =====================================================================
 *
 * CONTEXTO
 *   Este es el modulo de autenticacion original del sistema, escrito
 *   durante la primera migracion de la base de datos. Cuando la aplicacion
 *   paso de MD5 a password_hash() se agrego la columna `password` con
 *   bcrypt, pero este modulo nunca se reescribio: sigue comparando contra
 *   la columna `password_legacy` y-armando la sentencia por concatenacion.
 *
 *   Es exactamente el tipo de deuda tecnica que acumula un sistema interno
 *   durante años: una funcion correcta convive con el camino viejo que
 *   continua en uso porque "funcionaba".
 *
 * PROPOSITO
 *   Demostrar que un WAF como Chaitin SafeLine, colocado delante de la
 *   aplicacion, detecta y bloquea el intento de inyeccion SQL en el
 *   formulario de autenticacion.
 *
 * AISLAMIENTO (regla fundamental del proyecto)
 *   - Este es el UNICO archivo del sistema con SQL armado por concatenacion
 *     de entrada del usuario.
 *   - No se llama desde ningun otro modulo.
 *   - Ninguna consulta del camino normal usa concatenacion.
 *
 * ENTORNO
 *   - Este entorno es de laboratorio controlado y esta aislado en la red.
 *   - Las credenciales del seed son de demostracion y estan publicadas en
 *     la documentacion del proyecto.
 *   - No desplegar esta version en una red sin exposicion publica.
 * =====================================================================
 */

require_once __DIR__ . '/app.php';

/**
 * ---------------------------------------------------------------------
 * LAB-01 - Autenticacion vulnerable a SQL Injection
 * ---------------------------------------------------------------------
 *
 * El valor de los dos campos del formulario se interpola directamente en
 * la sentencia, sin consultas preparadas. Una comilla en el campo
 * Usuario cierra la cadena y permite:
 *
 *   - append de una condicion siempre verdadera, o
 *   - comentar el resto de la sentencia con -- o #
 *
 * Casos reproducibles (Escenario A, sin WAF):
 *
 *   Usuario: admin        Contrasena: cualquiera  -> login correcto
 *   Usuario: admin' --    Contrasena: cualquiera  -> login como admin
 *   Usuario: admin' #     Contrasena: cualquiera  -> login como admin
 *   Usuario: ' OR '1'='1' Contrasena: cualquiera  -> login como la
 *                                                    primera fila
 *
 * @param  string $username Valor enviado en el campo Usuario
 * @param  string $password Valor enviado en el campo Contrasena
 * @return array|null Fila del usuario si la consulta devuelve algo, null si no
 */
function lab_login_vulnerable($username, $password)
{
    // La entrada del usuario se concatena directamente en la consulta.
    // ESTA ES LA VULNERABILIDAD DELIBERADA (LAB-01).
    //
    // El WHERE va en una sola linea a proposito: es la forma en que se
    // escribio originalmente y es la que hace que el bypass funcione. Un
    // comentario SQL ('--' o '#') solo anula el resto de la linea donde
    // aparece, asi que si la comprobacion de contrasena estuviera en una
    // linea siguiente, seguiria aplicandose y el ataque no tendria exito.
    $sql = "SELECT id, nombre, apellido, email, username, role_id, estado FROM users WHERE username = '$username' AND password_legacy = MD5('$password') LIMIT 1";

    return db_row($sql);
}

/**
 * ---------------------------------------------------------------------
 * Implementacion correcta de la autenticacion (NO la usa login.php)
 * ---------------------------------------------------------------------
 *
 * Consulta preparada y password_verify() contra el hash bcrypt. Esta
 * funcion quedo escrita al migrar el sistema pero nunca se conecto: el
 * modulo heredado de arriba continua siendo el que atiende el formulario.
 *
 * Se conserva como referencia para la correccion. Es el punto central de
 * la conclusion del laboratorio: un WAF agrega una capa de defensa, pero
 * la solucion de fondo es reemplazar este codigo por secure_login().
 */
function secure_login($username, $password)
{
    $row = db_row(
        "SELECT id, nombre, apellido, email, username, password, role_id, estado
           FROM users
          WHERE username = ?",
        array($username)
    );

    if ($row === null) {
        return null;
    }

    if (!password_verify($password, $row['password'])) {
        return null;
    }

    if ($row['estado'] !== 'Activo') {
        return null;
    }

    return $row;
}
