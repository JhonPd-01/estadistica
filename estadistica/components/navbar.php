<?php
/**
 * Top navigation bar component
 */
?>

<nav class="top-navbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-sm toggle-sidebar-btn me-2" type="button">
            <i class="fas fa-bars"></i>
        </button>
        <h4 class="mb-0"><?php echo isset($pageTitle) ? $pageTitle : APP_NAME; ?></h4>
    </div>
    
    <div class="d-flex align-items-center">
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle me-1"></i>
                <?php echo $_SESSION['name']; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <li><div class="dropdown-item text-muted"><small><?php echo $_SESSION['user_role'] === 'admin' ? 'Administrador' : 'Usuario'; ?></small></div></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>
</nav>
