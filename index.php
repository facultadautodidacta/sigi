<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * index.php - Punto de entrada. Redirige segun el estado de la sesion.
 */

require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
