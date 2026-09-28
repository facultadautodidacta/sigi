<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * includes/header.php - Cabecera comun, navbar lateral y superior
 *
 * Variables opcionales que puede definir la pagina antes de incluirlo:
 *   $page_title  Titulo de la pagina
 *   $page_module Clave del item de menu a resaltar
 */

require_once __DIR__ . '/../config/app.php';

$page_title  = isset($page_title) ? $page_title : 'Inicio';
$page_module = isset($page_module) ? $page_module : 'dashboard';

$current_user_data = current_user();
$flash = flash_get();

/**
 * Menu lateral. Cada item declara los roles con acceso.
 */
function build_menu()
{
    return array(
        array(
            'key'   => 'dashboard',
            'icon'  => 'fa-chart-pie',
            'label' => 'Dashboard',
            'url'   => 'dashboard.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'equipment',
            'icon'  => 'fa-desktop',
            'label' => 'Equipos',
            'url'   => 'equipment/index.php',
            'roles' => array(1, 2, 3),
            'children' => array(
                array('key' => 'equipment_index',  'label' => 'Listado de equipos',  'url' => 'equipment/index.php', 'roles' => array(1, 2, 3)),
                array('key' => 'equipment_create', 'label' => 'Registrar equipo',   'url' => 'equipment/create.php', 'roles' => array(1, 2)),
                array('key' => 'equipment_search', 'label' => 'Buscar equipos',     'url' => 'equipment/search.php', 'roles' => array(1, 2, 3)),
            ),
        ),
        array(
            'key'   => 'assignments',
            'icon'  => 'fa-user-check',
            'label' => 'Asignaciones',
            'url'   => 'assignments/index.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'maintenance',
            'icon'  => 'fa-screwdriver-wrench',
            'label' => 'Mantenimiento',
            'url'   => 'maintenance/index.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'employees',
            'icon'  => 'fa-id-card',
            'label' => 'Empleados',
            'url'   => 'employees/index.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'brands',
            'icon'  => 'fa-building',
            'label' => 'Marcas',
            'url'   => 'brands/index.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'categories',
            'icon'  => 'fa-tags',
            'label' => 'Categorias',
            'url'   => 'categories/index.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'reports',
            'icon'  => 'fa-file-lines',
            'label' => 'Reportes',
            'url'   => 'reports/index.php',
            'roles' => array(1, 2, 3),
        ),
        array(
            'key'   => 'users',
            'icon'  => 'fa-users-gear',
            'label' => 'Usuarios',
            'url'   => 'users/index.php',
            'roles' => array(1),
        ),
        array(
            'key'   => 'audit',
            'icon'  => 'fa-clipboard-list',
            'label' => 'Auditoria',
            'url'   => 'audit/index.php',
            'roles' => array(1),
        ),
        array(
            'key'   => 'lab',
            'icon'  => 'fa-flask',
            'label' => 'Laboratorio',
            'url'   => 'lab/index.php',
            'roles' => array(1),
            'badge' => 'LAB',
        ),
    );
}

$menu = build_menu();

/**
 * Muestra en el menu lateral solo los modulos ya implementados.
 *
 * El menu declara la estructura completa del sistema, pero durante el
 * desarrollo incremental cada modulo se agrega en su fase. Filtrar por
 * la existencia del archivo destino evita que el usuario navegue a
 * paginas inexistentes. Cuando se implemente un modulo, su link aparece
 * solo, sin tocar este archivo.
 */
$app_root = dirname(__DIR__);
$menu     = array_values(array_filter($menu, function ($item) use ($app_root) {
    return is_file($app_root . '/' . $item['url']);
}));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - <?php echo e(APP_NAME); ?></title>

    <!--
        Bootstrap 5 y jQuery se sirven LOCALES desde assets/.
        No se usa CDN de forma intencional: durante las pruebas con SafeLine
        el trafico a terceros contaminaria los resultados del WAF.
    -->
    <link rel="stylesheet" href="<?php echo url('assets/css/bootstrap.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/fontawesome.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo url('assets/css/app.css'); ?>">
</head>
<body class="app-body">

<!-- ================= NAVBAR SUPERIOR ================= -->
<nav class="navbar navbar-expand navbar-dark topbar fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo url('dashboard.php'); ?>">
            <i class="fas fa-boxes-stacked me-2"></i>
            <span class="brand-text"><?php echo e(APP_NAME); ?></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="topbarNav">
            <ul class="navbar-nav align-items-center">
                <?php if ($current_user_data): ?>
                    <li class="nav-item me-3 d-none d-md-block">
                        <span class="navbar-text text-white-50">
                            <?php echo e(date('d/m/Y')); ?>
                        </span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                           data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-sm-inline"><?php echo e($current_user_data['nombre'] . ' ' . $current_user_data['apellido']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text small text-muted">
                                    <?php echo e($current_user_data['username']); ?> &middot;
                                    <?php echo e($current_user_data['role_name']); ?>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo url('users/edit.php?id=' . (int) $current_user_data['id']); ?>">
                                <i class="fas fa-user-gear me-2"></i>Mi perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo url('logout.php'); ?>">
                                <i class="fas fa-right-from-bracket me-2"></i>Cerrar sesion</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="app-wrapper">

    <!-- ================= MENU LATERAL ================= -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand d-none d-lg-flex">
            <i class="fas fa-boxes-stacked"></i>
            <div>
                <div class="sb-title"><?php echo e(APP_NAME); ?></div>
                <div class="sb-subtitle"><?php echo e(APP_SUBTITLE); ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($menu as $item): ?>
                <?php
                if (!has_role($item['roles'])) {
                    continue;
                }
                $has_active_child = false;
                if (!empty($item['children'])) {
                    foreach ($item['children'] as $child) {
                        if ($page_module === $child['key']) {
                            $has_active_child = true;
                            break;
                        }
                    }
                }
                $is_active = ($page_module === $item['key']) || $has_active_child;
                ?>
                <div class="sidebar-group">
                    <a class="sidebar-link <?php echo $is_active ? 'active' : ''; ?>"
                       href="<?php echo url($item['url']); ?>">
                        <i class="fas <?php echo e($item['icon']); ?>"></i>
                        <span><?php echo e($item['label']); ?></span>
                        <?php if (!empty($item['badge'])): ?>
                            <span class="sidebar-badge"><?php echo e($item['badge']); ?></span>
                        <?php endif; ?>
                    </a>

                    <?php if (!empty($item['children'])): ?>
                        <ul class="sidebar-submenu">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php if (!has_role($child['roles'])) { continue; } ?>
                                <li>
                                    <a class="sidebar-sublink <?php echo $page_module === $child['key'] ? 'active' : ''; ?>"
                                       href="<?php echo url($child['url']); ?>">
                                        <?php echo e($child['label']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer d-none d-lg-block">
            <div class="sf-version"><?php echo e(APP_COMPANY); ?> &middot; v<?php echo e(APP_VERSION); ?></div>
        </div>
    </aside>

    <!-- ================= CONTENIDO ================= -->
    <main class="main-content">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show no-print" role="alert">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                <?php echo e($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
