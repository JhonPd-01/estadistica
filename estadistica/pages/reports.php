<?php
/**
 * Reports Page
 * Allows generation and viewing of various system reports
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Informes";

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Get current active period
$periodQuery = "SELECT * FROM annual_periods WHERE active = 1 ORDER BY year DESC LIMIT 1";
$periodStmt = $db->prepare($periodQuery);
$periodStmt->execute();

if ($periodStmt->rowCount() == 0) {
    // If no active period, get the most recent period
    $periodQuery = "SELECT * FROM annual_periods ORDER BY year DESC LIMIT 1";
    $periodStmt = $db->prepare($periodQuery);
    $periodStmt->execute();
}

$activePeriod = null;
$activeYear = date('Y');

if ($periodStmt->rowCount() > 0) {
    $activePeriod = $periodStmt->fetch(PDO::FETCH_ASSOC);
    $activeYear = $activePeriod['year'];
}

// Get EPS list
$epsQuery = "SELECT id, name FROM eps WHERE status = 1 ORDER BY name";
$epsStmt = $db->prepare($epsQuery);
$epsStmt->execute();
$epsList = $epsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get specialties list
$specialtiesQuery = "SELECT id, name FROM specialties ORDER BY name";
$specialtiesStmt = $db->prepare($specialtiesQuery);
$specialtiesStmt->execute();
$specialtiesList = $specialtiesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get current month
$currentMonth = date('n');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include_once '../components/sidebar.php'; ?>
    
    <!-- Main content -->
    <div class="main-content" id="app">
        <!-- Top navbar -->
        <?php include_once '../components/navbar.php'; ?>
        
        <!-- Content -->
        <div class="container-fluid mt-3">
            <!-- Alert for messages -->
            <div v-if="errorMessage" class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ errorMessage }}
                <button type="button" class="btn-close" @click="clearMessages"></button>
            </div>
            
            <div v-if="successMessage" class="alert alert-success alert-dismissible fade show" role="alert">
                {{ successMessage }}
                <button type="button" class="btn-close" @click="clearMessages"></button>
            </div>
            
            <!-- Page heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="fas fa-file-alt me-2"></i> Informes
                </h2>
            </div>
            
            <!-- Report Types Tabs -->
            <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="annual-tab" data-bs-toggle="tab" data-bs-target="#annual" type="button" role="tab" aria-controls="annual" aria-selected="true">
                        <i class="fas fa-calendar-alt me-2"></i> Informe Anual
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="semester-tab" data-bs-toggle="tab" data-bs-target="#semester" type="button" role="tab" aria-controls="semester" aria-selected="false">
                        <i class="fas fa-calendar me-2"></i> Informe Semestral
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab" aria-controls="monthly" aria-selected="false">
                        <i class="fas fa-calendar-day me-2"></i> Informe Mensual
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="eps-tab" data-bs-toggle="tab" data-bs-target="#eps" type="button" role="tab" aria-controls="eps" aria-selected="false">
                        <i class="fas fa-hospital me-2"></i> Informe por EPS
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="specialty-tab" data-bs-toggle="tab" data-bs-target="#specialty" type="button" role="tab" aria-controls="specialty" aria-selected="false">
                        <i class="fas fa-user-md me-2"></i> Informe por Especialidad
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="compliance-tab" data-bs-toggle="tab" data-bs-target="#compliance" type="button" role="tab" aria-controls="compliance" aria-selected="false">
                        <i class="fas fa-chart-pie me-2"></i> Informe de Cumplimiento
                    </button>
                </li>
            </ul>
            
            <!-- Tab content -->
            <div class="tab-content" id="reportTabsContent">
                <!-- Annual Report Tab -->
                <div class="tab-pane fade show active" id="annual" role="tabpanel" aria-labelledby="annual-tab">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informe Anual</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="annualYearSelect" class="form-label">Año:</label>
                                    <select id="annualYearSelect" class="form-select" v-model="filters.annual.year" @change="loadAnnualReport">
                                        <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button class="btn btn-primary" @click="loadAnnualReport">
                                        <i class="fas fa-search me-1"></i> Generar Informe
                                    </button>
                                    <button class="btn btn-success ms-2" @click="exportAnnualReport" :disabled="!annualReport.year">
                                        <i class="fas fa-file-excel me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Loading indicator -->
                            <div v-if="isLoading.annual" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Generando informe, por favor espere...</p>
                            </div>
                            
                            <!-- Report content -->
                            <div v-else-if="annualReport.year">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h4 class="text-center mb-3">Informe Anual {{ annualReport.year }}</h4>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Proyectadas</h5>
                                                        <h2 class="text-primary">{{ formatNumber(annualReport.summary.total_projected) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Realizadas</h5>
                                                        <h2 class="text-success">{{ formatNumber(annualReport.summary.total_actual) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Cumplimiento</h5>
                                                        <h2 :class="getComplianceTextClass(annualReport.summary.compliance_percentage)">
                                                            {{ annualReport.summary.compliance_percentage }}%
                                                        </h2>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por EPS</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="annual-eps-table">
                                                <thead>
                                                    <tr>
                                                        <th>EPS</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="eps in annualReport.eps_data">
                                                        <td>{{ eps.name }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.actual) }}</td>
                                                        <td class="text-center">{{ eps.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${eps.status}`">
                                                                {{ getStatusText(eps.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por Especialidad</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="annual-specialty-table">
                                                <thead>
                                                    <tr>
                                                        <th>Especialidad</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="specialty in annualReport.specialty_data">
                                                        <td>{{ specialty.name }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.actual) }}</td>
                                                        <td class="text-center">{{ specialty.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${specialty.status}`">
                                                                {{ getStatusText(specialty.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5>Tendencia Mensual</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="annual-monthly-table">
                                                <thead>
                                                    <tr>
                                                        <th>Mes</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="month in annualReport.monthly_data">
                                                        <td>{{ month.month_name }}</td>
                                                        <td class="text-center">{{ formatNumber(month.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(month.actual) }}</td>
                                                        <td class="text-center">{{ month.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${month.status}`">
                                                                {{ getStatusText(month.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="chart-container">
                                            <h5>Gráfico de Tendencia Mensual</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="annual-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-5">
                                <p class="text-muted">Seleccione un año y haga clic en "Generar Informe" para ver el informe anual.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Semester Report Tab -->
                <div class="tab-pane fade" id="semester" role="tabpanel" aria-labelledby="semester-tab">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informe Semestral</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="semesterYearSelect" class="form-label">Año:</label>
                                    <select id="semesterYearSelect" class="form-select" v-model="filters.semester.year">
                                        <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="semesterSelect" class="form-label">Semestre:</label>
                                    <select id="semesterSelect" class="form-select" v-model="filters.semester.semester">
                                        <option value="1">Primer Semestre (Feb-Jul)</option>
                                        <option value="2">Segundo Semestre (Ago-Ene)</option>
                                    </select>
                                </div>
                                <div class="col-md-5 d-flex align-items-end">
                                    <button class="btn btn-primary" @click="loadSemesterReport">
                                        <i class="fas fa-search me-1"></i> Generar Informe
                                    </button>
                                    <button class="btn btn-success ms-2" @click="exportSemesterReport" :disabled="!semesterReport.year">
                                        <i class="fas fa-file-excel me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Loading indicator -->
                            <div v-if="isLoading.semester" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Generando informe, por favor espere...</p>
                            </div>
                            
                            <!-- Report content -->
                            <div v-else-if="semesterReport.year">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h4 class="text-center mb-3">
                                            Informe {{ semesterReport.semester === 1 ? 'Primer' : 'Segundo' }} Semestre {{ semesterReport.year }}
                                        </h4>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Proyectadas</h5>
                                                        <h2 class="text-primary">{{ formatNumber(semesterReport.summary.total_projected) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Realizadas</h5>
                                                        <h2 class="text-success">{{ formatNumber(semesterReport.summary.total_actual) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Cumplimiento</h5>
                                                        <h2 :class="getComplianceTextClass(semesterReport.summary.compliance_percentage)">
                                                            {{ semesterReport.summary.compliance_percentage }}%
                                                        </h2>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por EPS</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="semester-eps-table">
                                                <thead>
                                                    <tr>
                                                        <th>EPS</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="eps in semesterReport.eps_data">
                                                        <td>{{ eps.name }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.actual) }}</td>
                                                        <td class="text-center">{{ eps.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${eps.status}`">
                                                                {{ getStatusText(eps.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por Especialidad</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="semester-specialty-table">
                                                <thead>
                                                    <tr>
                                                        <th>Especialidad</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="specialty in semesterReport.specialty_data">
                                                        <td>{{ specialty.name }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.actual) }}</td>
                                                        <td class="text-center">{{ specialty.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${specialty.status}`">
                                                                {{ getStatusText(specialty.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5>Tendencia Mensual</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="semester-monthly-table">
                                                <thead>
                                                    <tr>
                                                        <th>Mes</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="month in semesterReport.monthly_data">
                                                        <td>{{ month.month_name }}</td>
                                                        <td class="text-center">{{ formatNumber(month.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(month.actual) }}</td>
                                                        <td class="text-center">{{ month.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${month.status}`">
                                                                {{ getStatusText(month.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="chart-container">
                                            <h5>Gráfico de Tendencia Mensual</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="semester-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-5">
                                <p class="text-muted">Seleccione un año y semestre, luego haga clic en "Generar Informe" para ver el informe semestral.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Report Tab -->
                <div class="tab-pane fade" id="monthly" role="tabpanel" aria-labelledby="monthly-tab">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informe Mensual</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label for="monthlyYearSelect" class="form-label">Año:</label>
                                    <select id="monthlyYearSelect" class="form-select" v-model="filters.monthly.year">
                                        <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="monthlyMonthSelect" class="form-label">Mes:</label>
                                    <select id="monthlyMonthSelect" class="form-select" v-model="filters.monthly.month">
                                        <option value="1">Enero</option>
                                        <option value="2">Febrero</option>
                                        <option value="3">Marzo</option>
                                        <option value="4">Abril</option>
                                        <option value="5">Mayo</option>
                                        <option value="6">Junio</option>
                                        <option value="7">Julio</option>
                                        <option value="8">Agosto</option>
                                        <option value="9">Septiembre</option>
                                        <option value="10">Octubre</option>
                                        <option value="11">Noviembre</option>
                                        <option value="12">Diciembre</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button class="btn btn-primary" @click="loadMonthlyReport">
                                        <i class="fas fa-search me-1"></i> Generar Informe
                                    </button>
                                    <button class="btn btn-success ms-2" @click="exportMonthlyReport" :disabled="!monthlyReport.year">
                                        <i class="fas fa-file-excel me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Loading indicator -->
                            <div v-if="isLoading.monthly" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Generando informe, por favor espere...</p>
                            </div>
                            
                            <!-- Report content -->
                            <div v-else-if="monthlyReport.year">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h4 class="text-center mb-3">Informe Mensual: {{ monthlyReport.month_name }} {{ monthlyReport.year }}</h4>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Proyectadas</h5>
                                                        <h2 class="text-primary">{{ formatNumber(monthlyReport.summary.total_projected) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Realizadas</h5>
                                                        <h2 class="text-success">{{ formatNumber(monthlyReport.summary.total_actual) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Cumplimiento</h5>
                                                        <h2 :class="getComplianceTextClass(monthlyReport.summary.compliance_percentage)">
                                                            {{ monthlyReport.summary.compliance_percentage }}%
                                                        </h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Días Hábiles</h5>
                                                        <h2 class="text-info">{{ monthlyReport.summary.working_days }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por EPS</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="monthly-eps-table">
                                                <thead>
                                                    <tr>
                                                        <th>EPS</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="eps in monthlyReport.eps_data">
                                                        <td>{{ eps.name }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.actual) }}</td>
                                                        <td class="text-center">{{ eps.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${eps.status}`">
                                                                {{ getStatusText(eps.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por Especialidad</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="monthly-specialty-table">
                                                <thead>
                                                    <tr>
                                                        <th>Especialidad</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="specialty in monthlyReport.specialty_data">
                                                        <td>{{ specialty.name }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.actual) }}</td>
                                                        <td class="text-center">{{ specialty.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${specialty.status}`">
                                                                {{ getStatusText(specialty.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5>Registro Diario</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="monthly-daily-table">
                                                <thead>
                                                    <tr>
                                                        <th>Día</th>
                                                        <th class="text-center">Fecha</th>
                                                        <th class="text-center">Día de la Semana</th>
                                                        <th class="text-center">Atenciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="day in monthlyReport.daily_data">
                                                        <td>{{ day.day }}</td>
                                                        <td class="text-center">{{ formatDate(day.date) }}</td>
                                                        <td class="text-center">{{ day.day_name }}</td>
                                                        <td class="text-center">{{ formatNumber(day.appointments_count) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Distribución por Especialidad</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="monthly-specialty-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Atenciones Diarias</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="monthly-daily-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-5">
                                <p class="text-muted">Seleccione un año y mes, luego haga clic en "Generar Informe" para ver el informe mensual.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- EPS Report Tab -->
                <div class="tab-pane fade" id="eps" role="tabpanel" aria-labelledby="eps-tab">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informe por EPS</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="epsReportSelect" class="form-label">EPS:</label>
                                    <select id="epsReportSelect" class="form-select" v-model="filters.eps.eps_id">
                                        <option v-for="eps in epsList" :value="eps.id">{{ eps.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="epsYearSelect" class="form-label">Año:</label>
                                    <select id="epsYearSelect" class="form-select" v-model="filters.eps.year">
                                        <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                                <div class="col-md-5 d-flex align-items-end">
                                    <button class="btn btn-primary" @click="loadEpsReport">
                                        <i class="fas fa-search me-1"></i> Generar Informe
                                    </button>
                                    <button class="btn btn-success ms-2" @click="exportEpsReport" :disabled="!epsReport.eps_id">
                                        <i class="fas fa-file-excel me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Loading indicator -->
                            <div v-if="isLoading.eps" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Generando informe, por favor espere...</p>
                            </div>
                            
                            <!-- Report content -->
                            <div v-else-if="epsReport.eps_id">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h4 class="text-center mb-3">Informe {{ epsReport.eps_name }} - {{ epsReport.year }}</h4>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Proyectadas</h5>
                                                        <h2 class="text-primary">{{ formatNumber(epsReport.summary.appointments.total_projected) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Realizadas</h5>
                                                        <h2 class="text-success">{{ formatNumber(epsReport.summary.appointments.total_actual) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Cumplimiento</h5>
                                                        <h2 :class="getComplianceTextClass(epsReport.summary.compliance_percentage)">
                                                            {{ epsReport.summary.compliance_percentage }}%
                                                        </h2>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-5">
                                        <h5>Datos de Población</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="eps-population-table">
                                                <thead>
                                                    <tr>
                                                        <th>Mes</th>
                                                        <th class="text-center">Activa</th>
                                                        <th class="text-center">Adultos</th>
                                                        <th class="text-center">Pediátricos</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="pop in epsReport.population_data">
                                                        <td>{{ pop.month_name }}</td>
                                                        <td class="text-center">{{ formatNumber(pop.active_population) }}</td>
                                                        <td class="text-center">{{ formatNumber(pop.adults) }}</td>
                                                        <td class="text-center">{{ formatNumber(pop.pediatric_diagnosed) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <h5>Cumplimiento por Especialidad</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="eps-specialty-table">
                                                <thead>
                                                    <tr>
                                                        <th>Especialidad</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="specialty in epsReport.specialty_data">
                                                        <td>{{ specialty.name }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(specialty.actual) }}</td>
                                                        <td class="text-center">{{ specialty.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${specialty.status}`">
                                                                {{ getStatusText(specialty.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5>Tendencia Mensual</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="eps-monthly-table">
                                                <thead>
                                                    <tr>
                                                        <th>Mes</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="month in epsReport.monthly_data">
                                                        <td>{{ month.month_name }}</td>
                                                        <td class="text-center">{{ formatNumber(month.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(month.actual) }}</td>
                                                        <td class="text-center">{{ month.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${month.status}`">
                                                                {{ getStatusText(month.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Tendencia Mensual</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="eps-monthly-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Tendencia de Población</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="eps-population-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-5">
                                <p class="text-muted">Seleccione una EPS y año, luego haga clic en "Generar Informe" para ver el informe por EPS.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Specialty Report Tab -->
                <div class="tab-pane fade" id="specialty" role="tabpanel" aria-labelledby="specialty-tab">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informe por Especialidad</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label for="specialtyReportSelect" class="form-label">Especialidad:</label>
                                    <select id="specialtyReportSelect" class="form-select" v-model="filters.specialty.specialty_id">
                                        <option v-for="specialty in specialtiesList" :value="specialty.id">{{ specialty.name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="specialtyYearSelect" class="form-label">Año:</label>
                                    <select id="specialtyYearSelect" class="form-select" v-model="filters.specialty.year">
                                        <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                                <div class="col-md-5 d-flex align-items-end">
                                    <button class="btn btn-primary" @click="loadSpecialtyReport">
                                        <i class="fas fa-search me-1"></i> Generar Informe
                                    </button>
                                    <button class="btn btn-success ms-2" @click="exportSpecialtyReport" :disabled="!specialtyReport.specialty_id">
                                        <i class="fas fa-file-excel me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Loading indicator -->
                            <div v-if="isLoading.specialty" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Generando informe, por favor espere...</p>
                            </div>
                            
                            <!-- Report content -->
                            <div v-else-if="specialtyReport.specialty_id">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h4 class="text-center mb-3">Informe {{ specialtyReport.specialty_name }} - {{ specialtyReport.year }}</h4>
                                        <p class="text-center text-muted">{{ specialtyReport.specialty_description }}</p>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Proyectadas</h5>
                                                        <h2 class="text-primary">{{ formatNumber(specialtyReport.summary.total_projected) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Realizadas</h5>
                                                        <h2 class="text-success">{{ formatNumber(specialtyReport.summary.total_actual) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Cumplimiento</h5>
                                                        <h2 :class="getComplianceTextClass(specialtyReport.summary.compliance_percentage)">
                                                            {{ specialtyReport.summary.compliance_percentage }}%
                                                        </h2>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Cumplimiento por EPS</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="specialty-eps-table">
                                                <thead>
                                                    <tr>
                                                        <th>EPS</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="eps in specialtyReport.eps_data">
                                                        <td>{{ eps.name }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(eps.actual) }}</td>
                                                        <td class="text-center">{{ eps.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${eps.status}`">
                                                                {{ getStatusText(eps.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="chart-container">
                                            <h5>Distribución por EPS</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="specialty-eps-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5>Tendencia Mensual</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="specialty-monthly-table">
                                                <thead>
                                                    <tr>
                                                        <th>Mes</th>
                                                        <th class="text-center">Proyectadas</th>
                                                        <th class="text-center">Realizadas</th>
                                                        <th class="text-center">Cumplimiento</th>
                                                        <th class="text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="month in specialtyReport.monthly_data">
                                                        <td>{{ month.month_name }}</td>
                                                        <td class="text-center">{{ formatNumber(month.projected) }}</td>
                                                        <td class="text-center">{{ formatNumber(month.actual) }}</td>
                                                        <td class="text-center">{{ month.compliance }}%</td>
                                                        <td class="text-center">
                                                            <span class="badge" :class="`bg-${month.status}`">
                                                                {{ getStatusText(month.compliance) }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="chart-container">
                                            <h5>Tendencia Mensual</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="specialty-monthly-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-5">
                                <p class="text-muted">Seleccione una especialidad y año, luego haga clic en "Generar Informe" para ver el informe por especialidad.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Compliance Report Tab -->
                <div class="tab-pane fade" id="compliance" role="tabpanel" aria-labelledby="compliance-tab">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Informe de Cumplimiento</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <label for="complianceYearSelect" class="form-label">Año:</label>
                                    <select id="complianceYearSelect" class="form-select" v-model="filters.compliance.year">
                                        <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                    </select>
                                </div>
                                <div class="col-md-9 d-flex align-items-end">
                                    <button class="btn btn-primary" @click="loadComplianceReport">
                                        <i class="fas fa-search me-1"></i> Generar Informe
                                    </button>
                                    <button class="btn btn-success ms-2" @click="exportComplianceReport" :disabled="!complianceReport.year">
                                        <i class="fas fa-file-excel me-1"></i> Exportar
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Loading indicator -->
                            <div v-if="isLoading.compliance" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2">Generando informe, por favor espere...</p>
                            </div>
                            
                            <!-- Report content -->
                            <div v-else-if="complianceReport.year">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <h4 class="text-center mb-3">Matriz de Cumplimiento {{ complianceReport.year }}</h4>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Proyectadas</h5>
                                                        <h2 class="text-primary">{{ formatNumber(complianceReport.summary.total_projected) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Atenciones Realizadas</h5>
                                                        <h2 class="text-success">{{ formatNumber(complianceReport.summary.total_actual) }}</h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Cumplimiento</h5>
                                                        <h2 :class="getComplianceTextClass(complianceReport.summary.compliance_percentage)">
                                                            {{ complianceReport.summary.compliance_percentage }}%
                                                        </h2>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="card mb-3">
                                                    <div class="card-body text-center">
                                                        <h5 class="card-title">Estadísticas</h5>
                                                        <div class="mt-2">
                                                            <span class="badge bg-success">{{ complianceReport.success_count }}</span> Cumplidos
                                                        </div>
                                                        <div class="mt-1">
                                                            <span class="badge bg-warning">{{ complianceReport.warning_count }}</span> En proceso
                                                        </div>
                                                        <div class="mt-1">
                                                            <span class="badge bg-danger">{{ complianceReport.danger_count }}</span> Críticos
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <h5>Matriz de Cumplimiento</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover table-bordered" id="compliance-matrix-table">
                                                <thead>
                                                    <tr>
                                                        <th rowspan="2" class="align-middle">EPS</th>
                                                        <th colspan="100%" class="text-center">Especialidades</th>
                                                    </tr>
                                                    <tr>
                                                        <th v-for="specialty in complianceReport.specialties" class="text-center">{{ specialty.name }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="eps in complianceReport.compliance_matrix">
                                                        <td>{{ eps.eps_name }}</td>
                                                        <td v-for="specialty in eps.specialties" class="text-center" :class="`table-${specialty.status}`">
                                                            {{ specialty.compliance }}%
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-12">
                                        <div class="chart-container">
                                            <h5>Distribución de Cumplimiento</h5>
                                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                                <canvas id="compliance-chart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="text-center py-5">
                                <p class="text-muted">Seleccione un año y haga clic en "Generar Informe" para ver el informe de cumplimiento.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <?php include_once '../components/footer.php'; ?>
    </div>

    <script>
        // Initialize Vue application
        document.addEventListener('DOMContentLoaded', function() {
            const app = new Vue({
                el: '#app',
                data: {
                    isLoading: {
                        annual: false,
                        semester: false,
                        monthly: false,
                        eps: false,
                        specialty: false,
                        compliance: false
                    },
                    errorMessage: '',
                    successMessage: '',
                    epsList: <?php echo json_encode($epsList); ?>,
                    specialtiesList: <?php echo json_encode($specialtiesList); ?>,
                    yearsList: [],
                    filters: {
                        annual: {
                            year: <?php echo $activeYear; ?>
                        },
                        semester: {
                            year: <?php echo $activeYear; ?>,
                            semester: 1
                        },
                        monthly: {
                            year: <?php echo $activeYear; ?>,
                            month: <?php echo $currentMonth; ?>
                        },
                        eps: {
                            eps_id: '',
                            year: <?php echo $activeYear; ?>
                        },
                        specialty: {
                            specialty_id: '',
                            year: <?php echo $activeYear; ?>
                        },
                        compliance: {
                            year: <?php echo $activeYear; ?>
                        }
                    },
                    annualReport: {},
                    semesterReport: {},
                    monthlyReport: {},
                    epsReport: {},
                    specialtyReport: {},
                    complianceReport: {},
                    charts: {}
                },
                methods: {
                    loadYearsList() {
                        fetch('../api/dashboard.php?action=listYears')
                            .then(response => response.json())
                            .then(data => {
                                this.yearsList = data;
                                
                                // Set initial EPS and specialty if available
                                if (this.epsList.length > 0) {
                                    this.filters.eps.eps_id = this.epsList[0].id;
                                }
                                if (this.specialtiesList.length > 0) {
                                    this.filters.specialty.specialty_id = this.specialtiesList[0].id;
                                }
                            })
                            .catch(error => {
                                console.error('Error loading years list:', error);
                                this.errorMessage = 'Error al cargar la lista de años.';
                            });
                    },
                    
                    // Annual Report
                    loadAnnualReport() {
                        this.isLoading.annual = true;
                        this.clearMessages();
                        
                        fetch(`../api/reports.php?action=getAnnualReport&year=${this.filters.annual.year}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.annualReport = data.report;
                                    this.$nextTick(() => {
                                        this.updateAnnualChart();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al generar el informe anual.';
                                }
                                this.isLoading.annual = false;
                            })
                            .catch(error => {
                                console.error('Error loading annual report:', error);
                                this.errorMessage = 'Error al generar el informe anual.';
                                this.isLoading.annual = false;
                            });
                    },
                    updateAnnualChart() {
                        const ctx = document.getElementById('annual-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.annualChart) {
                            this.charts.annualChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.annualReport.monthly_data.map(month => month.month_name);
                        const projected = this.annualReport.monthly_data.map(month => month.projected);
                        const actual = this.annualReport.monthly_data.map(month => month.actual);
                        
                        this.charts.annualChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Proyectadas',
                                        data: projected,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Realizadas',
                                        data: actual,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    },
                    exportAnnualReport() {
                        if (!this.annualReport.year) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create a workbook with multiple sheets
                        const wb = XLSX.utils.book_new();
                        
                        // Add EPS data sheet
                        const epsTable = document.getElementById('annual-eps-table');
                        if (epsTable) {
                            const wsEps = XLSX.utils.table_to_sheet(epsTable);
                            XLSX.utils.book_append_sheet(wb, wsEps, 'EPS');
                        }
                        
                        // Add Specialty data sheet
                        const specialtyTable = document.getElementById('annual-specialty-table');
                        if (specialtyTable) {
                            const wsSpecialty = XLSX.utils.table_to_sheet(specialtyTable);
                            XLSX.utils.book_append_sheet(wb, wsSpecialty, 'Especialidades');
                        }
                        
                        // Add Monthly data sheet
                        const monthlyTable = document.getElementById('annual-monthly-table');
                        if (monthlyTable) {
                            const wsMonthly = XLSX.utils.table_to_sheet(monthlyTable);
                            XLSX.utils.book_append_sheet(wb, wsMonthly, 'Mensual');
                        }
                        
                        // Download the Excel file
                        XLSX.writeFile(wb, `Informe_Anual_${this.annualReport.year}.xlsx`);
                        
                        this.successMessage = 'Informe exportado exitosamente.';
                    },
                    
                    // Semester Report
                    loadSemesterReport() {
                        this.isLoading.semester = true;
                        this.clearMessages();
                        
                        fetch(`../api/reports.php?action=getSemesterReport&year=${this.filters.semester.year}&semester=${this.filters.semester.semester}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.semesterReport = data.report;
                                    this.$nextTick(() => {
                                        this.updateSemesterChart();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al generar el informe semestral.';
                                }
                                this.isLoading.semester = false;
                            })
                            .catch(error => {
                                console.error('Error loading semester report:', error);
                                this.errorMessage = 'Error al generar el informe semestral.';
                                this.isLoading.semester = false;
                            });
                    },
                    updateSemesterChart() {
                        const ctx = document.getElementById('semester-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.semesterChart) {
                            this.charts.semesterChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.semesterReport.monthly_data.map(month => month.month_name);
                        const projected = this.semesterReport.monthly_data.map(month => month.projected);
                        const actual = this.semesterReport.monthly_data.map(month => month.actual);
                        
                        this.charts.semesterChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Proyectadas',
                                        data: projected,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Realizadas',
                                        data: actual,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    },
                    exportSemesterReport() {
                        if (!this.semesterReport.year) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create a workbook with multiple sheets
                        const wb = XLSX.utils.book_new();
                        
                        // Add EPS data sheet
                        const epsTable = document.getElementById('semester-eps-table');
                        if (epsTable) {
                            const wsEps = XLSX.utils.table_to_sheet(epsTable);
                            XLSX.utils.book_append_sheet(wb, wsEps, 'EPS');
                        }
                        
                        // Add Specialty data sheet
                        const specialtyTable = document.getElementById('semester-specialty-table');
                        if (specialtyTable) {
                            const wsSpecialty = XLSX.utils.table_to_sheet(specialtyTable);
                            XLSX.utils.book_append_sheet(wb, wsSpecialty, 'Especialidades');
                        }
                        
                        // Add Monthly data sheet
                        const monthlyTable = document.getElementById('semester-monthly-table');
                        if (monthlyTable) {
                            const wsMonthly = XLSX.utils.table_to_sheet(monthlyTable);
                            XLSX.utils.book_append_sheet(wb, wsMonthly, 'Mensual');
                        }
                        
                        // Download the Excel file
                        XLSX.writeFile(wb, `Informe_Semestral_${this.semesterReport.semester_name}_${this.semesterReport.year}.xlsx`);
                        
                        this.successMessage = 'Informe exportado exitosamente.';
                    },
                    
                    // Monthly Report
                    loadMonthlyReport() {
                        this.isLoading.monthly = true;
                        this.clearMessages();
                        
                        fetch(`../api/reports.php?action=getMonthlyReport&year=${this.filters.monthly.year}&month=${this.filters.monthly.month}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.monthlyReport = data.report;
                                    this.$nextTick(() => {
                                        this.updateMonthlyCharts();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al generar el informe mensual.';
                                }
                                this.isLoading.monthly = false;
                            })
                            .catch(error => {
                                console.error('Error loading monthly report:', error);
                                this.errorMessage = 'Error al generar el informe mensual.';
                                this.isLoading.monthly = false;
                            });
                    },
                    updateMonthlyCharts() {
                        // Specialty distribution chart
                        const ctxSpecialty = document.getElementById('monthly-specialty-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.monthlySpecialtyChart) {
                            this.charts.monthlySpecialtyChart.destroy();
                        }
                        
                        // Prepare data for specialty chart
                        const specialtyLabels = this.monthlyReport.specialty_data.map(specialty => specialty.name);
                        const specialtyData = this.monthlyReport.specialty_data.map(specialty => specialty.actual);
                        
                        // Generate colors
                        const colors = [];
                        const baseColors = [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(201, 203, 207, 0.7)'
                        ];
                        
                        for (let i = 0; i < specialtyData.length; i++) {
                            colors.push(baseColors[i % baseColors.length]);
                        }
                        
                        this.charts.monthlySpecialtyChart = new Chart(ctxSpecialty, {
                            type: 'doughnut',
                            data: {
                                labels: specialtyLabels,
                                datasets: [{
                                    data: specialtyData,
                                    backgroundColor: colors,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                        
                        // Daily appointments chart
                        const ctxDaily = document.getElementById('monthly-daily-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.monthlyDailyChart) {
                            this.charts.monthlyDailyChart.destroy();
                        }
                        
                        // Prepare data for daily chart
                        const dailyLabels = this.monthlyReport.daily_data.map(day => day.day);
                        const dailyData = this.monthlyReport.daily_data.map(day => day.appointments_count);
                        
                        this.charts.monthlyDailyChart = new Chart(ctxDaily, {
                            type: 'bar',
                            data: {
                                labels: dailyLabels,
                                datasets: [{
                                    label: 'Atenciones Diarias',
                                    data: dailyData,
                                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                    borderColor: 'rgb(54, 162, 235)',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    },
                    exportMonthlyReport() {
                        if (!this.monthlyReport.year) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create a workbook with multiple sheets
                        const wb = XLSX.utils.book_new();
                        
                        // Add EPS data sheet
                        const epsTable = document.getElementById('monthly-eps-table');
                        if (epsTable) {
                            const wsEps = XLSX.utils.table_to_sheet(epsTable);
                            XLSX.utils.book_append_sheet(wb, wsEps, 'EPS');
                        }
                        
                        // Add Specialty data sheet
                        const specialtyTable = document.getElementById('monthly-specialty-table');
                        if (specialtyTable) {
                            const wsSpecialty = XLSX.utils.table_to_sheet(specialtyTable);
                            XLSX.utils.book_append_sheet(wb, wsSpecialty, 'Especialidades');
                        }
                        
                        // Add Daily data sheet
                        const dailyTable = document.getElementById('monthly-daily-table');
                        if (dailyTable) {
                            const wsDaily = XLSX.utils.table_to_sheet(dailyTable);
                            XLSX.utils.book_append_sheet(wb, wsDaily, 'Diario');
                        }
                        
                        // Download the Excel file
                        XLSX.writeFile(wb, `Informe_Mensual_${this.monthlyReport.month_name}_${this.monthlyReport.year}.xlsx`);
                        
                        this.successMessage = 'Informe exportado exitosamente.';
                    },
                    
                    // EPS Report
                    loadEpsReport() {
                        if (!this.filters.eps.eps_id) {
                            this.errorMessage = 'Por favor seleccione una EPS.';
                            return;
                        }
                        
                        this.isLoading.eps = true;
                        this.clearMessages();
                        
                        fetch(`../api/reports.php?action=getEpsReport&eps_id=${this.filters.eps.eps_id}&year=${this.filters.eps.year}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.epsReport = data.report;
                                    this.$nextTick(() => {
                                        this.updateEpsCharts();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al generar el informe por EPS.';
                                }
                                this.isLoading.eps = false;
                            })
                            .catch(error => {
                                console.error('Error loading EPS report:', error);
                                this.errorMessage = 'Error al generar el informe por EPS.';
                                this.isLoading.eps = false;
                            });
                    },
                    updateEpsCharts() {
                        // Monthly trend chart
                        const ctxMonthly = document.getElementById('eps-monthly-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.epsMonthlyChart) {
                            this.charts.epsMonthlyChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.epsReport.monthly_data.map(month => month.month_name);
                        const projected = this.epsReport.monthly_data.map(month => month.projected);
                        const actual = this.epsReport.monthly_data.map(month => month.actual);
                        
                        this.charts.epsMonthlyChart = new Chart(ctxMonthly, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Proyectadas',
                                        data: projected,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Realizadas',
                                        data: actual,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                        
                        // Population trend chart
                        const ctxPopulation = document.getElementById('eps-population-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.epsPopulationChart) {
                            this.charts.epsPopulationChart.destroy();
                        }
                        
                        // Check if population data is available
                        if (this.epsReport.population_data && this.epsReport.population_data.length > 0) {
                            // Prepare data for chart
                            const popMonths = this.epsReport.population_data.map(pop => pop.month_name);
                            const activePopulation = this.epsReport.population_data.map(pop => pop.active_population);
                            const adults = this.epsReport.population_data.map(pop => pop.adults);
                            const pediatric = this.epsReport.population_data.map(pop => pop.pediatric_diagnosed);
                            
                            this.charts.epsPopulationChart = new Chart(ctxPopulation, {
                                type: 'line',
                                data: {
                                    labels: popMonths,
                                    datasets: [
                                        {
                                            label: 'Población Activa',
                                            data: activePopulation,
                                            borderColor: 'rgb(54, 162, 235)',
                                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                            tension: 0.1,
                                            fill: true
                                        },
                                        {
                                            label: 'Adultos',
                                            data: adults,
                                            borderColor: 'rgb(75, 192, 192)',
                                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                            tension: 0.1,
                                            fill: true
                                        },
                                        {
                                            label: 'Pediátricos',
                                            data: pediatric,
                                            borderColor: 'rgb(255, 99, 132)',
                                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                            tension: 0.1,
                                            fill: true
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        }
                    },
                    exportEpsReport() {
                        if (!this.epsReport.eps_id) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create a workbook with multiple sheets
                        const wb = XLSX.utils.book_new();
                        
                        // Add Population data sheet
                        const populationTable = document.getElementById('eps-population-table');
                        if (populationTable) {
                            const wsPopulation = XLSX.utils.table_to_sheet(populationTable);
                            XLSX.utils.book_append_sheet(wb, wsPopulation, 'Población');
                        }
                        
                        // Add Specialty data sheet
                        const specialtyTable = document.getElementById('eps-specialty-table');
                        if (specialtyTable) {
                            const wsSpecialty = XLSX.utils.table_to_sheet(specialtyTable);
                            XLSX.utils.book_append_sheet(wb, wsSpecialty, 'Especialidades');
                        }
                        
                        // Add Monthly data sheet
                        const monthlyTable = document.getElementById('eps-monthly-table');
                        if (monthlyTable) {
                            const wsMonthly = XLSX.utils.table_to_sheet(monthlyTable);
                            XLSX.utils.book_append_sheet(wb, wsMonthly, 'Mensual');
                        }
                        
                        // Download the Excel file
                        XLSX.writeFile(wb, `Informe_EPS_${this.epsReport.eps_name}_${this.epsReport.year}.xlsx`);
                        
                        this.successMessage = 'Informe exportado exitosamente.';
                    },
                    
                    // Specialty Report
                    loadSpecialtyReport() {
                        if (!this.filters.specialty.specialty_id) {
                            this.errorMessage = 'Por favor seleccione una especialidad.';
                            return;
                        }
                        
                        this.isLoading.specialty = true;
                        this.clearMessages();
                        
                        fetch(`../api/reports.php?action=getSpecialtyReport&specialty_id=${this.filters.specialty.specialty_id}&year=${this.filters.specialty.year}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.specialtyReport = data.report;
                                    this.$nextTick(() => {
                                        this.updateSpecialtyCharts();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al generar el informe por especialidad.';
                                }
                                this.isLoading.specialty = false;
                            })
                            .catch(error => {
                                console.error('Error loading specialty report:', error);
                                this.errorMessage = 'Error al generar el informe por especialidad.';
                                this.isLoading.specialty = false;
                            });
                    },
                    updateSpecialtyCharts() {
                        // Monthly trend chart
                        const ctxMonthly = document.getElementById('specialty-monthly-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.specialtyMonthlyChart) {
                            this.charts.specialtyMonthlyChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.specialtyReport.monthly_data.map(month => month.month_name);
                        const projected = this.specialtyReport.monthly_data.map(month => month.projected);
                        const actual = this.specialtyReport.monthly_data.map(month => month.actual);
                        
                        this.charts.specialtyMonthlyChart = new Chart(ctxMonthly, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Proyectadas',
                                        data: projected,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Realizadas',
                                        data: actual,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        tension: 0.1,
                                        fill: true
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                        
                        // EPS distribution chart
                        const ctxEps = document.getElementById('specialty-eps-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.specialtyEpsChart) {
                            this.charts.specialtyEpsChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const epsLabels = this.specialtyReport.eps_data.map(eps => eps.name);
                        const epsData = this.specialtyReport.eps_data.map(eps => eps.actual);
                        
                        // Generate colors
                        const colors = [];
                        const baseColors = [
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(255, 159, 64, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 205, 86, 0.7)',
                            'rgba(201, 203, 207, 0.7)'
                        ];
                        
                        for (let i = 0; i < epsData.length; i++) {
                            colors.push(baseColors[i % baseColors.length]);
                        }
                        
                        this.charts.specialtyEpsChart = new Chart(ctxEps, {
                            type: 'pie',
                            data: {
                                labels: epsLabels,
                                datasets: [{
                                    data: epsData,
                                    backgroundColor: colors,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                    },
                    exportSpecialtyReport() {
                        if (!this.specialtyReport.specialty_id) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create a workbook with multiple sheets
                        const wb = XLSX.utils.book_new();
                        
                        // Add EPS data sheet
                        const epsTable = document.getElementById('specialty-eps-table');
                        if (epsTable) {
                            const wsEps = XLSX.utils.table_to_sheet(epsTable);
                            XLSX.utils.book_append_sheet(wb, wsEps, 'EPS');
                        }
                        
                        // Add Monthly data sheet
                        const monthlyTable = document.getElementById('specialty-monthly-table');
                        if (monthlyTable) {
                            const wsMonthly = XLSX.utils.table_to_sheet(monthlyTable);
                            XLSX.utils.book_append_sheet(wb, wsMonthly, 'Mensual');
                        }
                        
                        // Download the Excel file
                        XLSX.writeFile(wb, `Informe_Especialidad_${this.specialtyReport.specialty_name}_${this.specialtyReport.year}.xlsx`);
                        
                        this.successMessage = 'Informe exportado exitosamente.';
                    },
                    
                    // Compliance Report
                    loadComplianceReport() {
                        this.isLoading.compliance = true;
                        this.clearMessages();
                        
                        fetch(`../api/reports.php?action=getComplianceReport&year=${this.filters.compliance.year}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.complianceReport = data.report;
                                    this.$nextTick(() => {
                                        this.updateComplianceChart();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al generar el informe de cumplimiento.';
                                }
                                this.isLoading.compliance = false;
                            })
                            .catch(error => {
                                console.error('Error loading compliance report:', error);
                                this.errorMessage = 'Error al generar el informe de cumplimiento.';
                                this.isLoading.compliance = false;
                            });
                    },
                    updateComplianceChart() {
                        const ctx = document.getElementById('compliance-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.complianceChart) {
                            this.charts.complianceChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const data = [
                            this.complianceReport.success_count,
                            this.complianceReport.warning_count,
                            this.complianceReport.danger_count
                        ];
                        
                        this.charts.complianceChart = new Chart(ctx, {
                            type: 'pie',
                            data: {
                                labels: ['Cumplidos (≥95%)', 'En proceso (80-94%)', 'Críticos (<80%)'],
                                datasets: [{
                                    data: data,
                                    backgroundColor: [
                                        'rgba(40, 167, 69, 0.7)',
                                        'rgba(255, 193, 7, 0.7)',
                                        'rgba(220, 53, 69, 0.7)'
                                    ],
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                    },
                    exportComplianceReport() {
                        if (!this.complianceReport.year) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create a workbook
                        const wb = XLSX.utils.book_new();
                        
                        // Add Compliance Matrix sheet
                        const matrixTable = document.getElementById('compliance-matrix-table');
                        if (matrixTable) {
                            const wsMatrix = XLSX.utils.table_to_sheet(matrixTable);
                            XLSX.utils.book_append_sheet(wb, wsMatrix, 'Matriz de Cumplimiento');
                        }
                        
                        // Download the Excel file
                        XLSX.writeFile(wb, `Matriz_Cumplimiento_${this.complianceReport.year}.xlsx`);
                        
                        this.successMessage = 'Informe exportado exitosamente.';
                    },
                    
                    // Utility methods
                    getComplianceTextClass(percentage) {
                        if (percentage < 80) return 'text-danger';
                        if (percentage >= 80 && percentage < 95) return 'text-warning';
                        return 'text-success';
                    },
                    getStatusText(percentage) {
                        if (percentage < 80) return 'Crítico';
                        if (percentage >= 80 && percentage < 95) return 'En proceso';
                        return 'Cumplido';
                    },
                    formatNumber(number) {
                        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    },
                    formatDate(dateString) {
                        const date = new Date(dateString);
                        return date.toLocaleDateString('es-ES', { 
                            day: '2-digit', 
                            month: '2-digit', 
                            year: 'numeric' 
                        });
                    },
                    clearMessages() {
                        this.errorMessage = '';
                        this.successMessage = '';
                    }
                },
                mounted() {
                    this.loadYearsList();
                    
                    // Add event listeners for tab changes to resize charts
                    const reportTabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
                    reportTabs.forEach(tab => {
                        tab.addEventListener('shown.bs.tab', event => {
                            // Resize charts when a tab is shown
                            if (event.target.id === 'annual-tab' && this.charts.annualChart) {
                                this.charts.annualChart.resize();
                            } else if (event.target.id === 'semester-tab' && this.charts.semesterChart) {
                                this.charts.semesterChart.resize();
                            } else if (event.target.id === 'monthly-tab') {
                                if (this.charts.monthlySpecialtyChart) this.charts.monthlySpecialtyChart.resize();
                                if (this.charts.monthlyDailyChart) this.charts.monthlyDailyChart.resize();
                            } else if (event.target.id === 'eps-tab') {
                                if (this.charts.epsMonthlyChart) this.charts.epsMonthlyChart.resize();
                                if (this.charts.epsPopulationChart) this.charts.epsPopulationChart.resize();
                            } else if (event.target.id === 'specialty-tab') {
                                if (this.charts.specialtyMonthlyChart) this.charts.specialtyMonthlyChart.resize();
                                if (this.charts.specialtyEpsChart) this.charts.specialtyEpsChart.resize();
                            } else if (event.target.id === 'compliance-tab' && this.charts.complianceChart) {
                                this.charts.complianceChart.resize();
                            }
                        });
                    });
                }
            });
        });
    </script>
</body>
</html>
