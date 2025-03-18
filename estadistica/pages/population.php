<?php
/**
 * Population management page
 * Allows registration and management of population demographics
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Gestión de Población";

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
            
            <!-- Page heading -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="fas fa-users me-2"></i> Gestión de Población
                </h2>
                <div>
                    <button class="btn btn-outline-primary" @click="loadPopulationData">
                        <i class="fas fa-sync-alt me-1"></i> Actualizar
                    </button>
                    <button class="btn btn-outline-success ms-2" @click="exportToExcel">
                        <i class="fas fa-file-excel me-1"></i> Exportar
                    </button>
                </div>
            </div>
            
            <!-- Filters section -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="epsSelect" class="form-label">EPS:</label>
                            <select id="epsSelect" class="form-select" v-model="selectedEps" @change="handleSelectionChange">
                                <option value="">Seleccione una EPS</option>
                                <option v-for="eps in epsList" :value="eps.id">{{ eps.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="yearSelect" class="form-label">Año:</label>
                            <select id="yearSelect" class="form-select" v-model="selectedYear" @change="handleSelectionChange">
                                <option v-for="year in yearsList" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="monthSelect" class="form-label">Mes:</label>
                            <select id="monthSelect" class="form-select" v-model="selectedMonth" @change="handleSelectionChange">
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
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <div v-if="activePeriod" class="small text-muted">
                                Periodo activo: {{ activePeriod.description || `${activePeriod.year}` }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Population Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Datos de Población - {{ monthName }} {{ selectedYear }}</h5>
                </div>
                <div class="card-body">
                    <!-- Loading indicator -->
                    <div v-if="isLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando datos, por favor espere...</p>
                    </div>
                    
                    <div v-else>
                        <form @submit.prevent="savePopulationData">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="totalPopulation" class="form-label">Población Total:</label>
                                    <input type="number" id="totalPopulation" class="form-control" v-model.number="populationData.total_population" min="0" required>
                                    <small class="text-muted">Población total de la agencia</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="activePopulation" class="form-label">Población EPS Activa:</label>
                                    <input type="number" id="activePopulation" class="form-control" v-model.number="populationData.active_population" min="0" required>
                                    <small class="text-muted">Población total afiliada activa</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fertileWomen" class="form-label">Mujeres en Edad Fértil:</label>
                                    <input type="number" id="fertileWomen" class="form-control" v-model.number="populationData.fertile_women" min="0" required>
                                    <small class="text-muted">Mujeres en edad fértil</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="pregnantWomen" class="form-label">Gestantes:</label>
                                    <input type="number" id="pregnantWomen" class="form-control" v-model.number="populationData.pregnant_women" min="0" required>
                                    <small class="text-muted">Mujeres gestantes</small>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="adults" class="form-label">Adultos:</label>
                                    <input type="number" id="adults" class="form-control" v-model.number="populationData.adults" min="0" required>
                                    <small class="text-muted">Población adulta</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="pediatricDiagnosed" class="form-label">Pediátricos con Diagnóstico:</label>
                                    <input type="number" id="pediatricDiagnosed" class="form-control" v-model.number="populationData.pediatric_diagnosed" min="0" required>
                                    <small class="text-muted">Pacientes pediátricos con diagnóstico</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="monitoredMinors" class="form-label">Menores en Seguimiento:</label>
                                    <input type="number" id="monitoredMinors" class="form-control" v-model.number="populationData.monitored_minors" min="0" required>
                                    <small class="text-muted">Menores en seguimiento</small>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-outline-secondary me-2" @click="resetForm">
                                    <i class="fas fa-undo me-1"></i> Restablecer
                                </button>
                                <button type="submit" class="btn btn-primary" :disabled="!selectedEps">
                                    <i class="fas fa-save me-1"></i> Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Population History -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Historial de Población</h5>
                </div>
                <div class="card-body">
                    <!-- Loading indicator -->
                    <div v-if="isLoadingHistory" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando historial, por favor espere...</p>
                    </div>
                    
                    <div v-else-if="populationHistory.length === 0" class="text-center py-4">
                        <p class="text-muted">No hay datos históricos para mostrar</p>
                    </div>
                    
                    <div v-else class="table-responsive">
                        <table class="table table-striped table-hover" id="population-history-table">
                            <thead>
                                <tr>
                                    <th>Mes</th>
                                    <th class="text-center">Población Total</th>
                                    <th class="text-center">Población Activa</th>
                                    <th class="text-center">Mujeres Fértil</th>
                                    <th class="text-center">Gestantes</th>
                                    <th class="text-center">Adultos</th>
                                    <th class="text-center">Pediátricos</th>
                                    <th class="text-center">Menores en Seg.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in populationHistory">
                                    <td>{{ getMonthName(item.month) }}</td>
                                    <td class="text-center">{{ formatNumber(item.total_population) }}</td>
                                    <td class="text-center">{{ formatNumber(item.active_population) }}</td>
                                    <td class="text-center">{{ formatNumber(item.fertile_women) }}</td>
                                    <td class="text-center">{{ formatNumber(item.pregnant_women) }}</td>
                                    <td class="text-center">{{ formatNumber(item.adults) }}</td>
                                    <td class="text-center">{{ formatNumber(item.pediatric_diagnosed) }}</td>
                                    <td class="text-center">{{ formatNumber(item.monitored_minors) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Population Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Tendencia de Población</h5>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper" style="position: relative; height: 300px;">
                        <canvas id="population-chart"></canvas>
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
                    isLoading: false,
                    isLoadingHistory: false,
                    errorMessage: '',
                    successMessage: '',
                    selectedEps: '',
                    selectedYear: <?php echo $activeYear; ?>,
                    selectedMonth: <?php echo $currentMonth; ?>,
                    activePeriod: <?php echo $activePeriod ? json_encode($activePeriod) : 'null'; ?>,
                    epsList: <?php echo json_encode($epsList); ?>,
                    yearsList: [],
                    populationData: {
                        total_population: 0,
                        active_population: 0,
                        fertile_women: 0,
                        pregnant_women: 0,
                        adults: 0,
                        pediatric_diagnosed: 0,
                        monitored_minors: 0
                    },
                    populationHistory: [],
                    populationChart: null
                },
                computed: {
                    monthName() {
                        const months = [
                            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                        ];
                        return months[this.selectedMonth - 1];
                    }
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
                    loadPopulationData() {
                        if (!this.selectedEps || !this.selectedYear || !this.selectedMonth) {
                            this.errorMessage = 'Por favor seleccione una EPS, año y mes.';
                            return;
                        }
                        
                        this.isLoading = true;
                        this.clearMessages();
                        
                        fetch(`../api/population.php?action=get&eps_id=${this.selectedEps}&year=${this.selectedYear}&month=${this.selectedMonth}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    if (data.population) {
                                        this.populationData = data.population;
                                    } else {
                                        // Reset form if no data found
                                        this.resetForm();
                                    }
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar los datos de población.';
                                }
                                this.isLoading = false;
                            })
                            .catch(error => {
                                console.error('Error loading population data:', error);
                                this.errorMessage = 'Error al cargar los datos de población.';
                                this.isLoading = false;
                            });
                        
                        // Load population history
                        this.loadPopulationHistory();
                    },
                    loadPopulationHistory() {
                        if (!this.selectedEps || !this.selectedYear) {
                            return;
                        }
                        
                        this.isLoadingHistory = true;
                        
                        fetch(`../api/reports.php?action=getEpsReport&eps_id=${this.selectedEps}&year=${this.selectedYear}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.populationHistory = data.report.population_data;
                                    this.$nextTick(() => {
                                        this.updatePopulationChart();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar el historial de población.';
                                }
                                this.isLoadingHistory = false;
                            })
                            .catch(error => {
                                console.error('Error loading population history:', error);
                                this.errorMessage = 'Error al cargar el historial de población.';
                                this.isLoadingHistory = false;
                            });
                    },
                    savePopulationData() {
                        if (!this.selectedEps || !this.selectedYear || !this.selectedMonth) {
                            this.errorMessage = 'Por favor seleccione una EPS, año y mes.';
                            return;
                        }
                        
                        this.isLoading = true;
                        this.clearMessages();
                        
                        // Prepare data for sending
                        const populationData = {
                            eps_id: this.selectedEps,
                            year: this.selectedYear,
                            month: this.selectedMonth,
                            ...this.populationData
                        };
                        
                        fetch('../api/population.php?action=save', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(populationData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'Datos de población guardados correctamente.';
                                // Reload population history
                                this.loadPopulationHistory();
                            } else {
                                this.errorMessage = data.message || 'Error al guardar los datos de población.';
                            }
                            this.isLoading = false;
                        })
                        .catch(error => {
                            console.error('Error saving population data:', error);
                            this.errorMessage = 'Error al guardar los datos de población.';
                            this.isLoading = false;
                        });
                    },
                    resetForm() {
                        this.populationData = {
                            total_population: 0,
                            active_population: 0,
                            fertile_women: 0,
                            pregnant_women: 0,
                            adults: 0,
                            pediatric_diagnosed: 0,
                            monitored_minors: 0
                        };
                    },
                    handleSelectionChange() {
                        this.loadPopulationData();
                    },
                    updatePopulationChart() {
                        const ctx = document.getElementById('population-chart').getContext('2d');
                        
                        // Destroy existing chart if it exists
                        if (this.populationChart) {
                            this.populationChart.destroy();
                        }
                        
                        // Prepare data for chart
                        const months = this.populationHistory.map(item => this.getMonthName(item.month));
                        const activePopulation = this.populationHistory.map(item => item.active_population);
                        const adults = this.populationHistory.map(item => item.adults);
                        const pediatric = this.populationHistory.map(item => item.pediatric_diagnosed);
                        const fertile = this.populationHistory.map(item => item.fertile_women);
                        
                        this.populationChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: months,
                                datasets: [
                                    {
                                        label: 'Población Activa',
                                        data: activePopulation,
                                        borderColor: 'rgb(54, 162, 235)',
                                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                        tension: 0.2,
                                        fill: true
                                    },
                                    {
                                        label: 'Adultos',
                                        data: adults,
                                        borderColor: 'rgb(75, 192, 192)',
                                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                        tension: 0.2,
                                        fill: true
                                    },
                                    {
                                        label: 'Pediátricos',
                                        data: pediatric,
                                        borderColor: 'rgb(255, 99, 132)',
                                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                        tension: 0.2,
                                        fill: true
                                    },
                                    {
                                        label: 'Mujeres Fértil',
                                        data: fertile,
                                        borderColor: 'rgb(153, 102, 255)',
                                        backgroundColor: 'rgba(153, 102, 255, 0.1)',
                                        tension: 0.2,
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
                    exportToExcel() {
                        if (this.populationHistory.length === 0) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        const epsName = this.epsList.find(eps => eps.id == this.selectedEps)?.name || 'Todas';
                        const filename = `Población_${epsName}_${this.selectedYear}.xlsx`;
                        
                        exportTableToExcel('population-history-table', filename);
                        
                        this.successMessage = 'Datos exportados correctamente.';
                        setTimeout(() => {
                            this.successMessage = '';
                        }, 3000);
                    },
                    getMonthName(monthNumber) {
                        const months = [
                            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                        ];
                        return months[monthNumber - 1];
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
                }
            });
        });
    </script>
</body>
</html>
