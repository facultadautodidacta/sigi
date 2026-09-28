<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * config/lab_search.php
 *
 * =====================================================================
 * LABORATORIO DE SEGURIDAD - VULNERABILIDAD DELIBERADA
 * =====================================================================
 *   ID    : LAB-02
 *   Tipo  : SQL Injection - Busqueda de equipos
 *   Riesgo: ALTO (CVSS 8.6)
 *   CWE   : CWE-89
 *   OWASP : A03:2021 - Injection
 *   Donde : equipment/search.php
 *   Ref   : LAB-02-SQLi-Busqueda.md
 * =====================================================================
 *
 * CONTEXTO
 *   El buscador de equipos se escribio para permitir consultas rapidas
 *   por texto libre. En lugar de consultas preparadas, el termino se
 *   empuja dentro del LIKE y se cierra con un asterisco, una tecnica
 *   comun en el codigo de la epoca en que se hizo el modulo.
 *
 *   A diferencia de LAB-01, aqui la inyeccion no necesita('--' ni '#':
 *   la consulta es un SELECT y el atacante puede cerrar el LIKE y anadir
 *   su propia clausula, o un UNION para traer datos de otras tablas.
 *
 * PROPOSITO
 *   Demostrar que SafeLine detecta SQLi tambien en parametros GET de
 *   lectura, no solo en el formulario de autenticacion.
 *
 * AISLAMIENTO (regla fundamental del proyecto)
 *   - Esta es la UNICA concatenacion de entrada de usuario en un SELECT
 *     de lectura fuera de LAB-01.
 *   - secure_search_equipment() existe y es correcta, pero no la usa
 *     equipment/search.php.
 * =====================================================================
 */

require_once __DIR__ . '/app.php';

/**
 * ---------------------------------------------------------------------
 * LAB-02 - Busqueda de equipos vulnerable a SQL Injection
 * ---------------------------------------------------------------------
 *
 * El termino buscado se concatena en la sentencia. Casos reproducibles
 * (Escenario A, sin WAF):
 *
 *   Termino: Dell                          -> busqueda normal
 *   Termino: ' OR '1'='1                   -> devuelve todos los equipos
 *   Termino: x%' UNION SELECT 1,2,3,4,5,6,7,8,9,10,11,12,13 --   -> UNION
 *   Termino: ' AND SLEEP(5) --             -> retardo de 5 segundos
 *
 * El ultimo es el que mejor se ve en un video: la respuesta tarda 5
 * segundos, lo que demuestra que la consulta se esta ejecutando.
 *
 * @param  string $q Termino buscado por el usuario
 * @return array Filas de equipment que coinciden
 */
function lab_search_equipment($q)
{
    // ESTA ES LA VULNERABILIDAD DELIBERADA (LAB-02).
    // El termino del usuario se concatena en el LIKE.
    $sql = "SELECT e.id, e.inventory_number, e.model, e.status, e.location, e.price
               FROM equipment e
              WHERE e.model LIKE '%$q%'
                 OR e.inventory_number LIKE '%$q%'
                 OR e.location LIKE '%$q%'
              ORDER BY e.id";

    return db_all($sql);
}

/**
 * ---------------------------------------------------------------------
 * Implementacion correcta de la busqueda (NO la usa equipment/search.php)
 * ---------------------------------------------------------------------
 *
 * Consulta preparada. Los comodines LIKE se anaden en PHP, no en SQL, y
 * el comodin del usuario se escapa para que no rompa el patron.
 *
 * @param  string $q Termino buscado
 * @return array Filas de equipment que coinciden
 */
function secure_search_equipment($q)
{
    $termino = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\%', '\_'), $q) . '%';

    return db_all(
        "SELECT e.id, e.inventory_number, e.model, e.status, e.location, e.price
           FROM equipment e
          WHERE e.model LIKE ?
             OR e.inventory_number LIKE ?
             OR e.location LIKE ?
          ORDER BY e.id",
        array($termino, $termino, $termino)
    );
}
