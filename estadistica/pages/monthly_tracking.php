<?php
/**
 * Monthly Tracking Page
 * Allows tracking of appointments by month and EPS
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Seguimiento Mensual";

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

// Get EPS ID from query parameter
$selectedEpsId = isset($_GET['eps_id']) ? intval($_GET['eps_id']) : 0;
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
                    <i class="fas fa-chart-line me-2"></i> Seguimiento Mensual
                </h2>
                <div>
                    <button class="btn btn-outline-success me-2" @click="exportToPDF">
                        <i class="fas fa-file-pdf me-1"></i> Exportar PDF
                    </button>
                    <button class="btn btn-outline-primary" @click="exportToExcel">
                        <i class="fas fa-file-excel me-1"></i> Exportar Excel
                    </button>
                </div>
            </div>
            
            <!-- Filters section -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="epsSelect" class="form-label">EPS:</label>
                            <select id="epsSelect" class="form-select" v-model="selectedEps" @change="loadMonthlyTrackingData">
                                <option value="">Seleccione una EPS</option>
                                <option v-for="eps in epsList" :value="eps.id">{{ eps.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="yearSelect" class="form-label">Año:</label>
                            <select id="yearSelect" class="form-select" v-model="selectedYear" @change="loadMonthlyTrackingData">
                                <option v-for="year in yearsList" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="specialtySelect" class="form-label">Especialidad:</label>
                            <select id="specialtySelect" class="form-select" v-model="selectedSpecialty" @change="loadMonthlyTrackingData">
                                <option value="">Todas las especialidades</option>
                                <option v-for="specialty in specialtiesList" :value="specialty.id">{{ specialty.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100" @click="loadMonthlyTrackingData">
                                <i class="fas fa-search me-1"></i> Consultar
                            </button>
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
                <!-- Monthly Overview -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Resumen de Seguimiento{{ selectedEpsName ? ' - ' + selectedEpsName : '' }}</h5>
                    </div>
                    <div class="card-body">
                        <!-- Summary cards -->
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-muted mb-3">Atenciones Proyectadas</h6>
                                        <h3 class="card-text text-primary">{{ formatNumber(trackingData.summary.total_projected) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-muted mb-3">Atenciones Realizadas</h6>
                                        <h3 class="card-text text-success">{{ formatNumber(trackingData.summary.total_actual) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-muted mb-3">Porcentaje de Cumplimiento</h6>
                                        <h3 class="card-text" :class="getComplianceTextClass(trackingData.summary.compliance_percentage)">
                                            {{ trackingData.summary.compliance_percentage }}%
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-muted mb-3">Estado</h6>
                                        <h3 class="card-text">
                                            <span class="badge" :class="getComplianceBadgeClass(trackingData.summary.compliance_percentage)">
                                                {{ getStatusText(trackingData.summary.compliance_percentage) }}
                                            </span>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Monthly trend chart -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="chart-container">
                                    <h6 class="mb-3">Tendencia Mensual {{ selectedYear }}</h6>
                                    <div class="chart-wrapper" style="position: relative; height: 300px;">
                                        <canvas id="monthly-trend-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Monthly Data Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Detalle Mensual</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="monthly-data-table">
                                <thead>
                                    <tr>
                                        <th>Mes</th>
                                        <th class="text-center">Atenciones Proyectadas</th>
                                        <th class="text-center">Atenciones Realizadas</th>
                                        <th class="text-center">Cumplimiento</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="month in trackingData.monthly_data">
                                        <td>{{ month.month_name }}</td>
                                        <td class="text-center">{{ formatNumber(month.projected) }}</td>
                                        <td class="text-center">{{ formatNumber(month.actual) }}</td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div 
                                                        class="progress-bar" 
                                                        :class="`bg-${month.status}`"
                                                        :style="`width: ${Math.min(month.percentage, 100)}%`" 
                                                        role="progressbar">
                                                    </div>
                                                </div>
                                                <span class="text-nowrap small">{{ month.percentage }}%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" :class="`bg-${month.status}`">
                                                {{ getStatusText(month.percentage) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a :href="`appointments.php?eps_id=${selectedEps}&year=${selectedYear}&month=${month.month}`" class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Specialty Breakdown -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Desglose por Especialidad</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="specialty-data-table">
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
                                            <tr v-for="specialty in trackingData.specialty_data">
                                                <td>{{ specialty.name }}</td>
                                                <td class="text-center">{{ formatNumber(specialty.projected) }}</td>
                                                <td class="text-center">{{ formatNumber(specialty.actual) }}</td>
                                                <td class="text-center">
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                            <div 
                                                                class="progress-bar" 
                                                                :class="`bg-${specialty.status}`"
                                                                :style="`width: ${Math.min(specialty.compliance, 100)}%`" 
                                                                role="progressbar">
                                                            </div>
                                                        </div>
                                                        <span class="text-nowrap small">{{ specialty.compliance }}%</span>
                                                    </div>
                                                </td>
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
                            <div class="col-md-5">
                                <div class="chart-container">
                                    <h6 class="mb-3">Distribución por Especialidad</h6>
                                    <div class="chart-wrapper" style="position: relative; height: 300px;">
                                        <canvas id="specialty-distribution-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Population Chart -->
                <div v-if="trackingData.population_data && trackingData.population_data.length > 0" class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Evolución de Población</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <div class="chart-wrapper" style="position: relative; height: 300px;">
                                <canvas id="population-trend-chart"></canvas>
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
                    selectedEps: '<?php echo $selectedEpsId; ?>',
                    selectedYear: <?php echo $activeYear; ?>,
                    selectedSpecialty: '',
                    selectedEpsName: '',
                    epsList: <?php echo json_encode($epsList); ?>,
                    specialtiesList: <?php echo json_encode($specialtiesList); ?>,
                    yearsList: [],
                    trackingData: {
                        summary: {
                            total_projected: 0,
                            total_actual: 0,
                            compliance_percentage: 0
                        },
                        monthly_data: [],
                        specialty_data: [],
                        population_data: []
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
                    loadMonthlyTrackingData() {
                        if (!this.selectedEps) {
                            this.errorMessage = 'Por favor seleccione una EPS.';
                            this.isLoading = false;
                            return;
                        }
                        
                        this.isLoading = true;
                        this.clearMessages();
                        
                        // Set EPS name
                        const selectedEpsObj = this.epsList.find(eps => eps.id == this.selectedEps);
                        if (selectedEpsObj) {
                            this.selectedEpsName = selectedEpsObj.name;
                        }
                        
                        // Prepare URL - if specialty is selected, use specialty report, otherwise use EPS report
                        let url;
                        if (this.selectedSpecialty) {
                            url = `../api/reports.php?action=getSpecialtyReport&year=${this.selectedYear}&specialty_id=${this.selectedSpecialty}`;
                            if (this.selectedEps) {
                                url += `&eps_id=${this.selectedEps}`;
                            }
                        } else {
                            url = `../api/reports.php?action=getEpsReport&year=${this.selectedYear}&eps_id=${this.selectedEps}`;
                        }
                        
                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.trackingData = data.report;
                                    this.$nextTick(() => {
                                        this.updateCharts();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar los datos de seguimiento.';
                                }
                                this.isLoading = false;
                            })
                            .catch(error => {
                                console.error('Error loading monthly tracking data:', error);
                                this.errorMessage = 'Error al cargar los datos de seguimiento.';
                                this.isLoading = false;
                            });
                    },
                    updateCharts() {
                        // Update monthly trend chart
                        this.updateMonthlyTrendChart();
                        
                        // Update specialty distribution chart
                        this.updateSpecialtyDistributionChart();
                        
                        // Update population trend chart if data is available
                        if (this.trackingData.population_data && this.trackingData.population_data.length > 0) {
                            this.updatePopulationTrendChart();
                        }
                    },
                    updateMonthlyTrendChart() {
                        const ctx = document.getElementById('monthly-trend-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.monthlyTrendChart) {
                            this.charts.monthlyTrendChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.trackingData.monthly_data.map(month => month.month_name);
                        const projected = this.trackingData.monthly_data.map(month => month.projected);
                        const actual = this.trackingData.monthly_data.map(month => month.actual);
                        
                        this.charts.monthlyTrendChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Proyectadas',
                                        data: projected,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Realizadas',
                                        data: actual,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        borderWidth: 2,
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
                                },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                let value = context.parsed.y || 0;
                                                
                                                value = value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                                return `${label}: ${value}`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    },
                    updateSpecialtyDistributionChart() {
                        const ctx = document.getElementById('specialty-distribution-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.specialtyDistributionChart) {
                            this.charts.specialtyDistributionChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const labels = this.trackingData.specialty_data.map(specialty => specialty.name);
                        const data = this.trackingData.specialty_data.map(specialty => specialty.actual);
                        
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
                        
                        for (let i = 0; i < data.length; i++) {
                            colors.push(baseColors[i % baseColors.length]);
                        }
                        
                        this.charts.specialtyDistributionChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: labels,
                                datasets: [{
                                    data: data,
                                    backgroundColor: colors,
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.parsed || 0;
                                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                const percentage = Math.round((value / total) * 100);
                                                
                                                const formattedValue = value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                                return `${label}: ${formattedValue} (${percentage}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    },
                    updatePopulationTrendChart() {
                        const ctx = document.getElementById('population-trend-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.charts.populationTrendChart) {
                            this.charts.populationTrendChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.trackingData.population_data.map(pop => pop.month_name);
                        const totalPopulation = this.trackingData.population_data.map(pop => pop.total_population);
                        const activePopulation = this.trackingData.population_data.map(pop => pop.active_population);
                        const adults = this.trackingData.population_data.map(pop => pop.adults);
                        const pediatric = this.trackingData.population_data.map(pop => pop.pediatric_diagnosed);
                        
                        this.charts.populationTrendChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Población Total',
                                        data: totalPopulation,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Población Activa',
                                        data: activePopulation,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Adultos',
                                        data: adults,
                                        borderColor: 'rgb(255, 99, 132)',
                                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                        borderWidth: 2,
                                        tension: 0.1,
                                        fill: true
                                    },
                                    {
                                        label: 'Pediátricos',
                                        data: pediatric,
                                        borderColor: 'rgb(255, 159, 64)',
                                        backgroundColor: 'rgba(255, 159, 64, 0.1)',
                                        borderWidth: 2,
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
                                },
                                plugins: {
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                let value = context.parsed.y || 0;
                                                
                                                value = value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                                                return `${label}: ${value}`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    },
                    getComplianceTextClass(percentage) {
                        if (percentage < 80) return 'text-danger';
                        if (percentage >= 80 && percentage < 95) return 'text-warning';
                        return 'text-success';
                    },
                    getComplianceBadgeClass(percentage) {
                        if (percentage < 80) return 'bg-danger';
                        if (percentage >= 80 && percentage < 95) return 'bg-warning';
                        return 'bg-success';
                    },
                    getStatusText(percentage) {
                        if (percentage < 80) return 'Crítico';
                        if (percentage >= 80 && percentage < 95) return 'En proceso';
                        return 'Cumplido';
                    },
                    exportToExcel() {
                        if (this.trackingData.monthly_data.length === 0) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Export monthly data
                        const filename = `Seguimiento_Mensual_${this.selectedEpsName}_${this.selectedYear}.xlsx`;
                        exportTableToExcel('monthly-data-table', filename);
                        
                        this.successMessage = 'Datos exportados correctamente.';
                        setTimeout(() => {
                            this.successMessage = '';
                        }, 3000);
                    },
                    exportToPDF() {
                        if (this.trackingData.monthly_data.length === 0) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        // Create PDF document
                        const doc = new jspdf.jsPDF('l', 'mm', 'a4');
                        
                        // Add title
                        doc.setFontSize(18);
                        doc.text('Quimiosalud SAS - Seguimiento Mensual', 14, 20);
                        
                        // Add EPS and year
                        doc.setFontSize(12);
                        doc.text(`EPS: ${this.selectedEpsName || 'Todas'} - Año: ${this.selectedYear}`, 14, 30);
                        
                        // Add summary
                        doc.setFontSize(14);
                        doc.text('Resumen', 14, 40);
                        
                        doc.setFontSize(10);
                        doc.text(`Atenciones Proyectadas: ${this.formatNumber(this.trackingData.summary.total_projected)}`, 14, 50);
                        doc.text(`Atenciones Realizadas: ${this.formatNumber(this.trackingData.summary.total_actual)}`, 14, 55);
                        doc.text(`Porcentaje de Cumplimiento: ${this.trackingData.summary.compliance_percentage}%`, 14, 60);
                        
                        // Add monthly data table
                        doc.setFontSize(14);
                        doc.text('Detalle Mensual', 14, 70);
                        
                        const monthlyTableData = this.trackingData.monthly_data.map(month => [
                            month.month_name,
                            this.formatNumber(month.projected),
                            this.formatNumber(month.actual),
                            `${month.percentage}%`,
                            this.getStatusText(month.percentage)
                        ]);
                        
                        doc.autoTable({
                            startY: 75,
                            head: [['Mes', 'Proyectadas', 'Realizadas', 'Cumplimiento', 'Estado']],
                            body: monthlyTableData
                        });
                        
                        // Add specialty data table
                        doc.setFontSize(14);
                        doc.text('Desglose por Especialidad', 14, doc.lastAutoTable.finalY + 15);
                        
                        const specialtyTableData = this.trackingData.specialty_data.map(specialty => [
                            specialty.name,
                            this.formatNumber(specialty.projected),
                            this.formatNumber(specialty.actual),
                            `${specialty.compliance}%`,
                            this.getStatusText(specialty.compliance)
                        ]);
                        
                        doc.autoTable({
                            startY: doc.lastAutoTable.finalY + 20,
                            head: [['Especialidad', 'Proyectadas', 'Realizadas', 'Cumplimiento', 'Estado']],
                            body: specialtyTableData
                        });
                        
                        // Add footer
                        doc.setFontSize(10);
                        doc.text('Quimiosalud SAS - Sistema de Pronóstico de Atenciones y Laboratorios', doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 10, {
                            align: 'center'
                        });
                        
                        // Save PDF
                        const filename = `Seguimiento_Mensual_${this.selectedEpsName}_${this.selectedYear}.pdf`;
                        doc.save(filename);
                        
                        this.successMessage = 'PDF exportado correctamente.';
                        setTimeout(() => {
                            this.successMessage = '';
                        }, 3000);
                    },
                    formatNumber(number) {
                        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                    },
                    clearMessages() {
                        this.errorMessage = '';
                        this.successMessage = '';
                    }
                },
                mounted() {
                    this.loadYearsList();
                    
                    // Load tracking data if EPS is selected
                    if (this.selectedEps) {
                        this.loadMonthlyTrackingData();
                    } else {
                        this.isLoading = false;
                    }
                }
            });
        });
    </script>
</body>
</html>
