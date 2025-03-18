<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Require user to be logged in
requireLogin();

// Get active year
$activeYear = getActiveYear($pdo);

// Set flag for month filter in sidebar
$showMonthFilter = true;
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-md-9">
        <h1 class="mb-4">
            <i class="fas fa-chart-bar me-2"></i>
            Informes y Reportes
        </h1>
        
        <?php if (!$activeYear): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No hay año de gestión activo. Por favor, configure un año de gestión en la sección de configuración.
        </div>
        <?php else: ?>
        
        <div id="reportsApp">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" id="reportsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="monthly-tab" data-bs-toggle="tab" data-bs-target="#monthly" type="button" role="tab" aria-controls="monthly" aria-selected="true">
                                <i class="fas fa-calendar-alt me-1"></i> Mensual
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="semester-tab" data-bs-toggle="tab" data-bs-target="#semester" type="button" role="tab" aria-controls="semester" aria-selected="false">
                                <i class="fas fa-calendar-week me-1"></i> Semestral
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="annual-tab" data-bs-toggle="tab" data-bs-target="#annual" type="button" role="tab" aria-controls="annual" aria-selected="false">
                                <i class="fas fa-calendar me-1"></i> Anual
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="eps-tab" data-bs-toggle="tab" data-bs-target="#eps" type="button" role="tab" aria-controls="eps" aria-selected="false">
                                <i class="fas fa-hospital me-1"></i> Por EPS
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="reportsTabContent">
                        <!-- Monthly Report Tab -->
                        <div class="tab-pane fade show active" id="monthly" role="tabpanel" aria-labelledby="monthly-tab">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5>Reporte Mensual</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success me-2" @click="exportMonthlyToExcel">
                                        <i class="fas fa-file-excel me-1"></i> Excel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="exportMonthlyToPDF">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="monthlyMonth" class="form-label">Mes</label>
                                    <select id="monthlyMonth" v-model="filters.month" class="form-select" @change="loadMonthlyReport">
                                        <option v-for="(name, index) in months" :key="index" :value="index+1">{{ name }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="monthlyEps" class="form-label">EPS</label>
                                    <select id="monthlyEps" v-model="filters.eps_id" class="form-select" @change="loadMonthlyReport">
                                        <option value="0">Todas las EPS</option>
                                        <option v-for="eps in epsList" :key="eps.id" :value="eps.id">{{ eps.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <canvas id="monthlyChart" height="300"></canvas>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped" id="monthlyTable">
                                    <thead>
                                        <tr>
                                            <th>EPS</th>
                                            <th>Especialidad</th>
                                            <th>Programadas</th>
                                            <th>Realizadas</th>
                                            <th>Pendientes</th>
                                            <th>Cumplimiento</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="loading">
                                            <td colspan="7" class="text-center">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando...</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-else-if="monthlyReport.length === 0">
                                            <td colspan="7" class="text-center">No hay datos disponibles</td>
                                        </tr>
                                        <tr v-for="item in monthlyReport" :key="`monthly-${item.eps_id}-${item.specialty_id}`">
                                            <td>{{ getEpsName(item.eps_id) }}</td>
                                            <td>{{ getSpecialtyName(item.specialty_id) }}</td>
                                            <td>{{ item.projected_qty }}</td>
                                            <td>{{ item.completed_qty }}</td>
                                            <td>{{ item.pending_qty }}</td>
                                            <td>{{ item.compliance }}%</td>
                                            <td>
                                                <span class="badge rounded-pill" :class="getComplianceClass(item.compliance)">
                                                    {{ getComplianceStatus(item.compliance) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Semester Report Tab -->
                        <div class="tab-pane fade" id="semester" role="tabpanel" aria-labelledby="semester-tab">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5>Reporte Semestral</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success me-2" @click="exportSemesterToExcel">
                                        <i class="fas fa-file-excel me-1"></i> Excel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="exportSemesterToPDF">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="semesterPeriod" class="form-label">Semestre</label>
                                    <select id="semesterPeriod" v-model="filters.semester" class="form-select" @change="loadSemesterReport">
                                        <option value="1">Primer Semestre (Feb-Jul)</option>
                                        <option value="2">Segundo Semestre (Ago-Ene)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="semesterEps" class="form-label">EPS</label>
                                    <select id="semesterEps" v-model="filters.eps_id" class="form-select" @change="loadSemesterReport">
                                        <option value="0">Todas las EPS</option>
                                        <option v-for="eps in epsList" :key="eps.id" :value="eps.id">{{ eps.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <canvas id="semesterChart" height="300"></canvas>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped" id="semesterTable">
                                    <thead>
                                        <tr>
                                            <th>EPS</th>
                                            <th>Especialidad</th>
                                            <th>Programadas</th>
                                            <th>Realizadas</th>
                                            <th>Pendientes</th>
                                            <th>Cumplimiento</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="loading">
                                            <td colspan="7" class="text-center">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando...</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-else-if="semesterReport.length === 0">
                                            <td colspan="7" class="text-center">No hay datos disponibles</td>
                                        </tr>
                                        <tr v-for="item in semesterReport" :key="`semester-${item.eps_id}-${item.specialty_id}`">
                                            <td>{{ getEpsName(item.eps_id) }}</td>
                                            <td>{{ getSpecialtyName(item.specialty_id) }}</td>
                                            <td>{{ item.projected_qty }}</td>
                                            <td>{{ item.completed_qty }}</td>
                                            <td>{{ item.pending_qty }}</td>
                                            <td>{{ item.compliance }}%</td>
                                            <td>
                                                <span class="badge rounded-pill" :class="getComplianceClass(item.compliance)">
                                                    {{ getComplianceStatus(item.compliance) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Annual Report Tab -->
                        <div class="tab-pane fade" id="annual" role="tabpanel" aria-labelledby="annual-tab">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5>Reporte Anual</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success me-2" @click="exportAnnualToExcel">
                                        <i class="fas fa-file-excel me-1"></i> Excel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="exportAnnualToPDF">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="annualEps" class="form-label">EPS</label>
                                    <select id="annualEps" v-model="filters.eps_id" class="form-select" @change="loadAnnualReport">
                                        <option value="0">Todas las EPS</option>
                                        <option v-for="eps in epsList" :key="eps.id" :value="eps.id">{{ eps.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <canvas id="annualChart" height="300"></canvas>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped" id="annualTable">
                                    <thead>
                                        <tr>
                                            <th>EPS</th>
                                            <th>Especialidad</th>
                                            <th>Programadas</th>
                                            <th>Realizadas</th>
                                            <th>Pendientes</th>
                                            <th>Cumplimiento</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="loading">
                                            <td colspan="7" class="text-center">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando...</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-else-if="annualReport.length === 0">
                                            <td colspan="7" class="text-center">No hay datos disponibles</td>
                                        </tr>
                                        <tr v-for="item in annualReport" :key="`annual-${item.eps_id}-${item.specialty_id}`">
                                            <td>{{ getEpsName(item.eps_id) }}</td>
                                            <td>{{ getSpecialtyName(item.specialty_id) }}</td>
                                            <td>{{ item.projected_qty }}</td>
                                            <td>{{ item.completed_qty }}</td>
                                            <td>{{ item.pending_qty }}</td>
                                            <td>{{ item.compliance }}%</td>
                                            <td>
                                                <span class="badge rounded-pill" :class="getComplianceClass(item.compliance)">
                                                    {{ getComplianceStatus(item.compliance) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- EPS Report Tab -->
                        <div class="tab-pane fade" id="eps" role="tabpanel" aria-labelledby="eps-tab">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5>Reporte por EPS</h5>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-success me-2" @click="exportEpsReportToExcel">
                                        <i class="fas fa-file-excel me-1"></i> Excel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="exportEpsReportToPDF">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="epsReportEps" class="form-label">EPS</label>
                                    <select id="epsReportEps" v-model="filters.eps_id" class="form-select" @change="loadEpsReport">
                                        <option value="0">Seleccione una EPS</option>
                                        <option v-for="eps in epsList" :key="eps.id" :value="eps.id">{{ eps.name }}</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div v-if="filters.eps_id > 0">
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <canvas id="epsChart" height="300"></canvas>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="card border-primary h-100">
                                            <div class="card-body text-center">
                                                <h6 class="card-title text-primary">Población Total</h6>
                                                <h3 class="card-text">{{ epsStats.total_population || 0 }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card border-success h-100">
                                            <div class="card-body text-center">
                                                <h6 class="card-title text-success">Atenciones Programadas</h6>
                                                <h3 class="card-text">{{ epsStats.total_projected || 0 }}</h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card border-info h-100">
                                            <div class="card-body text-center">
                                                <h6 class="card-title text-info">Cumplimiento Global</h6>
                                                <h3 class="card-text">{{ epsStats.overall_compliance || 0 }}%</h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped" id="epsReportTable">
                                        <thead>
                                            <tr>
                                                <th>Especialidad</th>
                                                <th>Programadas</th>
                                                <th>Realizadas</th>
                                                <th>Pendientes</th>
                                                <th>Cumplimiento</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="loading">
                                                <td colspan="6" class="text-center">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr v-else-if="epsReport.length === 0">
                                                <td colspan="6" class="text-center">No hay datos disponibles</td>
                                            </tr>
                                            <tr v-for="item in epsReport" :key="`eps-${item.specialty_id}`">
                                                <td>{{ getSpecialtyName(item.specialty_id) }}</td>
                                                <td>{{ item.projected_qty }}</td>
                                                <td>{{ item.completed_qty }}</td>
                                                <td>{{ item.pending_qty }}</td>
                                                <td>{{ item.compliance }}%</td>
                                                <td>
                                                    <span class="badge rounded-pill" :class="getComplianceClass(item.compliance)">
                                                        {{ getComplianceStatus(item.compliance) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-else class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i> Seleccione una EPS para ver su informe detallado
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<script src="assets/js/reports.js"></script>

<?php include 'includes/footer.php'; ?>
