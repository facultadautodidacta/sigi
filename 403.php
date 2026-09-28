<?php
/**
 * SIGI - Sistema de Gestion de Inventario Informatico
 * 403.php - Acceso denegado
 */

$page_title = 'Acceso denegado';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-lock me-2"></i>Acceso denegado</h1>
        <div class="ph-subtitle">Error 403 - Permisos insuficientes</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <i class="fas fa-shield-halved"></i>
            <h5>No tiene permisos para acceder a este modulo</h5>
            <p class="mb-1">
                Su rol actual es <strong><?php
                    $u = current_user();
                    echo e($u ? $u['role_name'] : 'Desconocido');
                ?></strong>.
            </p>
            <p class="text-muted small">
                El acceso fue registrado en la bitacora de auditoria.
                Si considera que se trata de un error, contacte al administrador del sistema.
            </p>
            <a href="<?php echo url('dashboard.php'); ?>" class="btn btn-primary btn-sm mt-2" style="background-color:#1f4e79;border-color:#1f4e79;">
                <i class="fas fa-home me-1"></i>Volver al Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
