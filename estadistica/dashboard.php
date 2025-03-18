<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Require user to be logged in
requireLogin();

// Get active year
$activeYear = getActiveYear($pdo);
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-md-12">
        <h1 class="mb-4">
            <i class="fas fa-tachometer-alt me-2"></i>
            Tablero de Control
        </h1>
    </div>
</div>

<?php if (!$activeYear): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    No hay año de gestión activo. Por favor, configure un año de gestión en la sección de configuración.
</div>
<?php else: ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Año de Gestión Activo: <?php echo htmlspecialchars($activeYear['year_label']); ?></h5>
                <p class="card-text">
                    Período: <?php echo date('d/m/Y', strtotime($activeYear['start_date'])); ?> - 
                    <?php echo date('d/m/Y', strtotime($activeYear['end_date'])); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- EPS Counter -->
    <div class="col-md-4 mb-4">
        <div class="card border-primary h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-primary fw-bold">EPS Activas</h6>
                        <h2 class="mb-0 fw-bold" id="epsCount">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </h2>
                    </div>
                    <div>
                        <i class="fas fa-hospital-user fa-3x text-primary opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="eps_management.php" class="text-decoration-none">Ver detalle <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <!-- Población Total Counter -->
    <div class="col-md-4 mb-4">
        <div class="card border-success h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-success fw-bold">Población Total</h6>
                        <h2 class="mb-0 fw-bold" id="totalPopulation">
                            <div class="spinner-border spinner-border-sm text-success" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </h2>
                    </div>
                    <div>
                        <i class="fas fa-users fa-3x text-success opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="population.php" class="text-decoration-none">Ver detalle <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <!-- Atenciones Counter -->
    <div class="col-md-4 mb-4">
        <div class="card border-info h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-info fw-bold">Atenciones Programadas</h6>
                        <h2 class="mb-0 fw-bold" id="scheduledAppointments">
                            <div class="spinner-border spinner-border-sm text-info" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </h2>
                    </div>
                    <div>
                        <i class="fas fa-calendar-check fa-3x text-info opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="appointments.php" class="text-decoration-none">Ver detalle <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Cumplimiento de Atenciones por Mes</h5>
            </div>
            <div class="card-body">
                <canvas id="appointmentsChart" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Distribución por Especialidad</h5>
            </div>
            <div class="card-body">
                <canvas id="specialtyChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cumplimiento por EPS</h5>
                <span class="badge rounded-pill bg-primary" id="currentMonthLabel"></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>EPS</th>
                                <th>Atenciones Programadas</th>
                                <th>Atenciones Realizadas</th>
                                <th>Cumplimiento</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="epsComplianceTable">
                            <tr>
                                <td colspan="5" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Include app.js only after closing the PHP if-else block -->
<script src="assets/js/dashboard.js"></script>

<?php include 'includes/footer.php'; ?>
