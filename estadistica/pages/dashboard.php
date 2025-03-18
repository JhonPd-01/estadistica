<?php
/**
 * Dashboard page
 * Shows an overview of the system with key metrics
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Dashboard";

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
            
            <!-- Filters section -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <label for="yearSelect" class="form-label">Año:</label>
                            <select id="yearSelect" class="form-select" v-model="selectedYear" @change="loadDashboardData">
                                <option v-for="year in yearsList" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="epsSelect" class="form-label">EPS:</label>
                            <select id="epsSelect" class="form-select" v-model="selectedEps" @change="loadDashboardData">
                                <option value="">Todas las EPS</option>
                                <option v-for="eps in epsList" :value="eps.id">{{ eps.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mt-md-4">
                            <button class="btn btn-outline-primary" @click="loadDashboardData">
                                <i class="fas fa-sync-alt me-2"></i> Actualizar
                            </button>
                            <button class="btn btn-outline-success ms-2" @click="exportToPDF">
                                <i class="fas fa-file-pdf me-2"></i> Exportar PDF
                            </button>
                        </div>
                        <div class="col-md-4 mt-2 mt-md-0 text-md-end">
                            <div class="d-flex justify-content-md-end align-items-center">
                                <span class="me-2">Fecha: {{ currentDate }}</span>
                                <span class="badge bg-primary">{{ currentMonth }}</span>
                            </div>
                            <small v-if="activePeriod" class="text-muted">
                                Periodo activo: {{ activePeriod.description || `${activePeriod.year}` }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Loading indicator -->
            <div v-if="isLoading" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2">Cargando datos, por favor espere...</p>
            </div>
            
            <div v-else>
                <!-- Dashboard summary cards -->
                <div class="row">
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">Población</h4>
                                <div class="icon text-primary">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="card-value">{{ formatNumber(dashboardData.summary.population.active_population) }}</div>
                            <div class="text-muted">Total población activa</div>
                            <hr>
                            <small class="text-muted">
                                Población total: {{ formatNumber(dashboardData.summary.population.total_population) }}
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">EPS</h4>
                                <div class="icon text-success">
                                    <i class="fas fa-hospital"></i>
                                </div>
                            </div>
                            <div class="card-value">{{ dashboardData.summary.eps_count }}</div>
                            <div class="text-muted">EPS activas</div>
                            <hr>
                            <small class="text-muted">
                                Total contratado: {{ epsList.length }}
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">Atenciones</h4>
                                <div class="icon text-warning">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="card-value">{{ formatNumber(dashboardData.summary.appointments.total_actual) }}</div>
                            <div class="text-muted">
                                De {{ formatNumber(dashboardData.summary.appointments.total_projected) }} proyectadas
                            </div>
                            <hr>
                            <div class="d-flex align-items-center">
                                <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                    <div 
                                        class="progress-bar" 
                                        :class="`bg-${getComplianceClass(dashboardData.summary.compliance_percentage)}`"
                                        :style="`width: ${Math.min(dashboardData.summary.compliance_percentage, 100)}%`" 
                                        role="progressbar"
                                        :aria-valuenow="dashboardData.summary.compliance_percentage" 
                                        aria-valuemin="0" 
                                        aria-valuemax="100">
                                    </div>
                                </div>
                                <span class="text-nowrap">{{ dashboardData.summary.compliance_percentage }}%</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">Cumplimiento</h4>
                                <div class="icon text-danger">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <div class="text-center">
                                    <span class="badge bg-success">{{ getSuccessCount() }}</span>
                                    <div class="small">Cumplidas</div>
                                </div>
                                <div class="text-center">
                                    <span class="badge bg-warning">{{ getWarningCount() }}</span>
                                    <div class="small">Por completar</div>
                                </div>
                                <div class="text-center">
                                    <span class="badge bg-danger">{{ getDangerCount() }}</span>
                                    <div class="small">Críticas</div>
                                </div>
                            </div>
                            <hr>
                            <div class="small">
                                <div>
                                    <span class="status-indicator green"></span>
                                    <span>≥ 95% Cumplido</span>
                                </div>
                                <div>
                                    <span class="status-indicator yellow"></span>
                                    <span>80-94% En proceso</span>
                                </div>
                                <div>
                                    <span class="status-indicator red"></span>
                                    <span>< 80% Crítico</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts section -->
                <div class="row">
                    <div class="col-md-7 mb-4">
                        <div class="chart-container">
                            <h5>Cumplimiento por Especialidad</h5>
                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                <canvas id="specialty-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-5 mb-4">
                        <div class="chart-container">
                            <h5>Tendencia Mensual</h5>
                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                <canvas id="monthly-trend-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- EPS Compliance section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Cumplimiento por EPS</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>EPS</th>
                                                <th class="text-center">Proyectadas</th>
                                                <th class="text-center">Realizadas</th>
                                                <th class="text-center">Cumplimiento</th>
                                                <th class="text-center">Estado</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="eps in dashboardData.summary.eps_compliance">
                                                <td>{{ eps.eps_name }}</td>
                                                <td class="text-center">{{ formatNumber(eps.projected) }}</td>
                                                <td class="text-center">{{ formatNumber(eps.actual) }}</td>
                                                <td class="text-center">
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                            <div 
                                                                class="progress-bar" 
                                                                :class="`bg-${eps.status}`"
                                                                :style="`width: ${Math.min(eps.percentage, 100)}%`" 
                                                                role="progressbar">
                                                            </div>
                                                        </div>
                                                        <span class="text-nowrap small">{{ eps.percentage }}%</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge" :class="`bg-${eps.status}`">
                                                        {{ getStatusText(eps.status) }}
                                                    </span>
                                                </td>
                                                <td class="text-end">
                                                    <a :href="`monthly_tracking.php?eps_id=${eps.id}`" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-chart-line"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Specialty Compliance section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Cumplimiento por Especialidad</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
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
                                            <tr v-for="specialty in dashboardData.summary.specialty_compliance">
                                                <td>{{ specialty.specialty_name }}</td>
                                                <td class="text-center">{{ formatNumber(specialty.projected) }}</td>
                                                <td class="text-center">{{ formatNumber(specialty.actual) }}</td>
                                                <td class="text-center">
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                            <div 
                                                                class="progress-bar" 
                                                                :class="`bg-${specialty.status}`"
                                                                :style="`width: ${Math.min(specialty.percentage, 100)}%`" 
                                                                role="progressbar">
                                                            </div>
                                                        </div>
                                                        <span class="text-nowrap small">{{ specialty.percentage }}%</span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge" :class="`bg-${specialty.status}`">
                                                        {{ getStatusText(specialty.status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
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
                    isLoading: true,
                    errorMessage: '',
                    successMessage: '',
                    selectedYear: <?php echo $activeYear; ?>,
                    selectedEps: '',
                    currentDate: '<?php echo date('d/m/Y'); ?>',
                    currentMonth: '<?php echo getCurrentMonthName(); ?>',
                    activePeriod: <?php echo $activePeriod ? json_encode($activePeriod) : 'null'; ?>,
                    epsList: <?php echo json_encode($epsList); ?>,
                    yearsList: [],
                    dashboardData: {
                        summary: {
                            population: {
                                total_population: 0,
                                active_population: 0
                            },
                            eps_count: 0,
                            appointments: {
                                total_projected: 0,
                                total_actual: 0
                            },
                            compliance_percentage: 0,
                            specialty_compliance: [],
                            eps_compliance: []
                        },
                        chart_data: {
                            months: [],
                            projected: [],
                            actual: []
                        }
                    },
                    charts: {}
                },
                methods: {
                    loadYearsList() {
                        fetch('../api/dashboard.php?action=listYears')
                            .then(response => response.json())
                            .then(data => {
                                this.yearsList = data;
                            })
                            .catch(error => {
                                console.error('Error loading years list:', error);
                                this.errorMessage = 'Error al cargar la lista de años.';
                            });
                    },
                    loadDashboardData() {
                        this.isLoading = true;
                        this.clearMessages();
                        
                        let url = `../api/dashboard.php?action=getSummary&year=${this.selectedYear}`;
                        if (this.selectedEps) {
                            url = `../api/dashboard.php?action=getEpsSummary&year=${this.selectedYear}&eps_id=${this.selectedEps}`;
                        }
                        
                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.dashboardData.summary = data.summary;
                                    this.loadMonthlyTrend();
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar los datos del dashboard.';
                                    this.isLoading = false;
                                }
                            })
                            .catch(error => {
                                console.error('Error loading dashboard data:', error);
                                this.errorMessage = 'Error al cargar los datos del dashboard.';
                                this.isLoading = false;
                            });
                    },
                    loadMonthlyTrend() {
                        let url = `../api/dashboard.php?action=getMonthlyTrend&year=${this.selectedYear}`;
                        if (this.selectedEps) {
                            url += `&eps_id=${this.selectedEps}`;
                        }
                        
                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.dashboardData.chart_data = data.chart_data;
                                    this.$nextTick(() => {
                                        this.updateCharts();
                                        this.isLoading = false;
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar los datos de tendencia mensual.';
                                    this.isLoading = false;
                                }
                            })
                            .catch(error => {
                                console.error('Error loading monthly trend data:', error);
                                this.errorMessage = 'Error al cargar los datos de tendencia mensual.';
                                this.isLoading = false;
                            });
                    },
                    updateCharts() {
                        // Update specialty chart
                        if (this.charts.specialtyChart) {
                            this.charts.specialtyChart.destroy();
                        }
                        
                        const specialtyCtx = document.getElementById('specialty-chart').getContext('2d');
                        this.charts.specialtyChart = new Chart(specialtyCtx, {
                            type: 'bar',
                            data: {
                                labels: this.dashboardData.summary.specialty_compliance.map(item => item.specialty_name),
                                datasets: [{
                                    label: 'Proyectadas',
                                    data: this.dashboardData.summary.specialty_compliance.map(item => item.projected),
                                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                    borderColor: 'rgb(54, 162, 235)',
                                    borderWidth: 1
                                }, {
                                    label: 'Realizadas',
                                    data: this.dashboardData.summary.specialty_compliance.map(item => item.actual),
                                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                    borderColor: 'rgb(75, 192, 192)',
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
                        
                        // Update monthly trend chart
                        if (this.charts.trendChart) {
                            this.charts.trendChart.destroy();
                        }
                        
                        const trendCtx = document.getElementById('monthly-trend-chart').getContext('2d');
                        this.charts.trendChart = new Chart(trendCtx, {
                            type: 'line',
                            data: {
                                labels: this.dashboardData.chart_data.months,
                                datasets: [{
                                    label: 'Proyectadas',
                                    data: this.dashboardData.chart_data.projected,
                                    borderColor: 'rgb(54, 162, 235)',
                                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                }, {
                                    label: 'Realizadas',
                                    data: this.dashboardData.chart_data.actual,
                                    borderColor: 'rgb(75, 192, 192)',
                                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                    tension: 0.1,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false
                            }
                        });
                    },
                    getComplianceClass(percentage) {
                        if (percentage < 80) return 'danger';
                        if (percentage >= 80 && percentage < 95) return 'warning';
                        return 'success';
                    },
                    getStatusText(status) {
                        switch (status) {
                            case 'success': return 'Cumplido';
                            case 'warning': return 'En proceso';
                            case 'danger': return 'Crítico';
                            default: return 'Desconocido';
                        }
                    },
                    getSuccessCount() {
                        return this.dashboardData.summary.specialty_compliance.filter(item => item.status === 'success').length;
                    },
                    getWarningCount() {
                        return this.dashboardData.summary.specialty_compliance.filter(item => item.status === 'warning').length;
                    },
                    getDangerCount() {
                        return this.dashboardData.summary.specialty_compliance.filter(item => item.status === 'danger').length;
                    },
                    formatNumber(number) {
                        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    },
                    exportToPDF() {
                        // Implement PDF export logic here
                        this.successMessage = 'Exportando a PDF...';
                        
                        // Use jsPDF library
                        const doc = new jspdf.jsPDF('l', 'mm', 'a4');
                        
                        // Add title and date
                        doc.setFontSize(18);
                        doc.text('Quimiosalud SAS - Dashboard', 14, 20);
                        
                        doc.setFontSize(12);
                        doc.text(`Periodo: ${this.selectedYear} - Fecha de generación: ${this.currentDate}`, 14, 30);
                        
                        // Add summary data
                        doc.setFontSize(14);
                        doc.text('Resumen General', 14, 40);
                        
                        doc.setFontSize(10);
                        doc.text(`Población Activa: ${this.formatNumber(this.dashboardData.summary.population.active_population)}`, 14, 50);
                        doc.text(`Atenciones Proyectadas: ${this.formatNumber(this.dashboardData.summary.appointments.total_projected)}`, 14, 55);
                        doc.text(`Atenciones Realizadas: ${this.formatNumber(this.dashboardData.summary.appointments.total_actual)}`, 14, 60);
                        doc.text(`Porcentaje de Cumplimiento: ${this.dashboardData.summary.compliance_percentage}%`, 14, 65);
                        
                        // Add EPS data table
                        doc.setFontSize(14);
                        doc.text('Cumplimiento por EPS', 14, 80);
                        
                        const epsTableData = this.dashboardData.summary.eps_compliance.map(eps => [
                            eps.eps_name,
                            this.formatNumber(eps.projected),
                            this.formatNumber(eps.actual),
                            `${eps.percentage}%`,
                            this.getStatusText(eps.status)
                        ]);
                        
                        doc.autoTable({
                            startY: 85,
                            head: [['EPS', 'Proyectadas', 'Realizadas', 'Cumplimiento', 'Estado']],
                            body: epsTableData,
                        });
                        
                        // Add specialty data table
                        doc.setFontSize(14);
                        doc.text('Cumplimiento por Especialidad', 14, doc.autoTable.previous.finalY + 15);
                        
                        const specialtyTableData = this.dashboardData.summary.specialty_compliance.map(specialty => [
                            specialty.specialty_name,
                            this.formatNumber(specialty.projected),
                            this.formatNumber(specialty.actual),
                            `${specialty.percentage}%`,
                            this.getStatusText(specialty.status)
                        ]);
                        
                        doc.autoTable({
                            startY: doc.autoTable.previous.finalY + 20,
                            head: [['Especialidad', 'Proyectadas', 'Realizadas', 'Cumplimiento', 'Estado']],
                            body: specialtyTableData,
                        });
                        
                        // Add footer
                        doc.setFontSize(10);
                        doc.text('Quimiosalud SAS - Sistema de Pronóstico de Atenciones y Laboratorios', doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 10, {
                            align: 'center'
                        });
                        
                        // Save PDF
                        doc.save(`Dashboard_${this.selectedYear}_${new Date().toISOString().slice(0, 10)}.pdf`);
                        
                        this.successMessage = 'PDF exportado correctamente.';
                        setTimeout(() => {
                            this.successMessage = '';
                        }, 3000);
                    },
                    clearMessages() {
                        this.errorMessage = '';
                        this.successMessage = '';
                    }
                },
                mounted() {
                    this.loadYearsList();
                    this.loadDashboardData();
                }
            });
        });
    </script>
</body>
</html>
