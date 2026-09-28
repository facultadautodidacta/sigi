<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * config/lab_store.php - Utilidades de almacenamiento y salida
 *
 * =====================================================================
 * LABORATORIO DE SEGURIDAD
 * =====================================================================
 *   LAB-04  Almacenamiento de entrada sin validar (el sink)
 *   LAB-04b Ausencia de escapado en la salida (el sink)
 *
 * El resto de las vulnerabilidades del laboratorio vive en su propio
 * archivo, siguiendo la convencion de lab_auth.php:
 *
 *   LAB-01  config/lab_auth.php     SQLi en autenticacion
 *   LAB-02  config/lab_search.php   SQLi en busqueda de equipos
 *   LAB-03  employees/view.php      IDOR en datos confidenciales
 *   LAB-05  lab/traversal.php       Path Traversal
 *   LAB-07  lab/command.php         Command Injection
 *   LAB-06  lab/upload.php          Subida de archivos sin control
 * =====================================================================
 */

require_once __DIR__ . '/app.php';

/**
 * ---------------------------------------------------------------------
 * LAB-04b - Salida sin escapar (VULNERABILIDAD DELIBERADA)
 * ---------------------------------------------------------------------
 *
 * Imprime un valor almacenado en la base sin aplicar htmlspecialchars().
 *
 * Cuando el valor proviene de un campo que el usuario escribe y que se
 * guarda tal cual, esto es un XSS almacenado: el payload se ejecuta en el
 * navegador de CUALQUIER usuario que abra la pagina mas adelante.
 *
 * Casos reproducibles (Escenario A, sin WAF):
 *
 *   En el campo Descripcion de un equipo, o en las Observaciones de una
 *   asignacion, se guarda:
 *
 *     <script>alert('XSS almacenado')</script>
 *
 *   La siguiente persona que abra esa ficha ve el dialogo.
 *
 * En el camino correcto se usaria e() de config/app.php, que ya existe y
 * se aplica en todas las vistas del sistema salvo donde se llama a esta
 * funcion a proposito.
 *
 * @param  string $text Valor a imprimir
 * @return void
 */
function lab_render_untrusted($text)
{
    // ESTA ES LA VULNERABILIDAD DELIBERADA (LAB-04b).
    // No se escapa: el HTML del dato se interpreta tal cual.
    echo $text;
}

/**
 * ---------------------------------------------------------------------
 * Implementacion correcta de la salida (NO usa la app en este camino)
 * ---------------------------------------------------------------------
 *
 * El equivalente seguro ya existe como e() en config/app.php. Se conserva
 * aqui la referencia explicita para dejar escrito el punto de la
 * correccion: no falta una funcion de escapado en el proyecto, falta
 * usarla en el punto de salida.
 */
function secure_render_untrusted($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
