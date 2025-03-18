<?php
/**
 * Appointments Page
 * Allows management of appointments and projections
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Gestión de Atenciones";

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

// Get current month and date
$currentMonth = date('n');
$currentDate = date('Y-m-d');
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
                    <i class="fas fa-calendar-check me-2"></i> Gestión de Atenciones
                </h2>
                <div>
                    <button class="btn btn-outline-success me-2" @click="exportToExcel">
                        <i class="fas fa-file-excel me-1"></i> Exportar
                    </button>
                    <button class="btn btn-outline-primary" @click="recalculateProjections" :disabled="!selectedEps">
                        <i class="fas fa-sync-alt me-1"></i> Recalcular Proyecciones
                    </button>
                </div>
            </div>
            
            <!-- Filters section -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-3 mb-3">
                            <label for="epsSelect" class="form-label">EPS:</label>
                            <select id="epsSelect" class="form-select" v-model="selectedEps" @change="handleSelectionChange">
                                <option value="">Seleccione una EPS</option>
                                <option v-for="eps in epsList" :value="eps.id">{{ eps.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="yearSelect" class="form-label">Año:</label>
                            <select id="yearSelect" class="form-select" v-model="selectedYear" @change="handleSelectionChange">
                                <option v-for="year in yearsList" :value="year">{{ year }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
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
                        <div class="col-md-3 mb-3">
                            <label for="specialtyFilter" class="form-label">Filtrar por Especialidad:</label>
                            <select id="specialtyFilter" class="form-select" v-model="filters.specialty">
                                <option value="">Todas las especialidades</option>
                                <option v-for="specialty in specialtiesList" :value="specialty.id">{{ specialty.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="statusFilter" class="form-label">Estado:</label>
                            <select id="statusFilter" class="form-select" v-model="filters.complianceStatus">
                                <option value="">Todos</option>
                                <option value="success">Cumplido (≥95%)</option>
                                <option value="warning">En proceso (80-94%)</option>
                                <option value="danger">Crítico (<80%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Daily Appointments Registration -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Registro de Atenciones Diarias</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="appointmentDate" class="form-label">Fecha:</label>
                            <input type="date" id="appointmentDate" class="form-control" v-model="dailyAppointment.date" :max="currentDate">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="appointmentEps" class="form-label">EPS:</label>
                            <select id="appointmentEps" class="form-select" v-model="dailyAppointment.eps_id" :disabled="selectedEps !== ''">
                                <option value="">Seleccione una EPS</option>
                                <option v-for="eps in epsList" :value="eps.id">{{ eps.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="appointmentSpecialty" class="form-label">Especialidad:</label>
                            <select id="appointmentSpecialty" class="form-select" v-model="dailyAppointment.specialty_id">
                                <option value="">Seleccione una especialidad</option>
                                <option v-for="specialty in specialtiesList" :value="specialty.id">{{ specialty.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="appointmentCount" class="form-label">Cantidad:</label>
                            <input type="number" id="appointmentCount" class="form-control" v-model.number="dailyAppointment.count" min="0">
                        </div>
                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button class="btn btn-primary w-100" @click="saveDailyAppointment" :disabled="!canSaveDailyAppointment">
                                <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Projections -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Proyecciones Mensuales - {{ monthName }} {{ selectedYear }}</h5>
                        <div>
                            <span class="badge bg-primary me-2">{{ monthWorkingDays }} días hábiles</span>
                            <span class="badge" :class="`bg-${complianceStatusClass}`">
                                Cumplimiento: {{ compliancePercentage }}%
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Loading indicator -->
                    <div v-if="isLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando proyecciones, por favor espere...</p>
                    </div>
                    
                    <div v-else>
                        <!-- Search filter -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" placeholder="Buscar especialidad..." v-model="searchTerm">
                                </div>
                            </div>
                            <div class="col-md-8 text-end">
                                <div class="d-inline-block me-3">
                                    <span class="status-indicator green"></span> Cumplido (≥95%)
                                </div>
                                <div class="d-inline-block me-3">
                                    <span class="status-indicator yellow"></span> En proceso (80-94%)
                                </div>
                                <div class="d-inline-block">
                                    <span class="status-indicator red"></span> Crítico (<80%)
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="projections-table">
                                <thead>
                                    <tr>
                                        <th>Especialidad</th>
                                        <th class="text-center">Atenciones Proyectadas</th>
                                        <th class="text-center">Atenciones Realizadas</th>
                                        <th class="text-center">Cumplimiento</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-center no-export">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="projection in filteredProjections">
                                        <td>{{ projection.specialty_name }}</td>
                                        <td class="text-center">{{ projection.projected_appointments }}</td>
                                        <td class="text-center">
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center" v-model.number="projection.actual_appointments" min="0">
                                                <button class="btn btn-outline-primary" @click="saveActualAppointments(projection)">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div 
                                                        class="progress-bar" 
                                                        :class="getComplianceClass(projection)"
                                                        :style="`width: ${Math.min(getCompliancePercentage(projection), 100)}%`" 
                                                        role="progressbar">
                                                    </div>
                                                </div>
                                                <span class="text-nowrap small">{{ getCompliancePercentage(projection) }}%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge" :class="getComplianceClass(projection)">
                                                {{ getComplianceStatus(projection) }}
                                            </span>
                                        </td>
                                        <td class="text-center no-export">
                                            <button class="btn btn-sm btn-outline-primary" @click="viewDailyAppointments(projection)">
                                                <i class="fas fa-calendar-day"></i> Diarias
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th>Total</th>
                                        <th class="text-center">{{ totalProjectedAppointments }}</th>
                                        <th class="text-center">{{ totalActualAppointments }}</th>
                                        <th class="text-center">{{ compliancePercentage }}%</th>
                                        <th class="text-center">
                                            <span class="badge" :class="`bg-${complianceStatusClass}`">
                                                {{ getStatusText(complianceStatusClass) }}
                                            </span>
                                        </th>
                                        <th class="text-center no-export"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Compliance Chart -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Cumplimiento por Especialidad</h5>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper" style="position: relative; height: 300px;">
                        <canvas id="compliance-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <?php include_once '../components/footer.php'; ?>
    </div>
    
    <!-- Daily Appointments Modal -->
    <div class="modal fade" id="dailyAppointmentsModal" tabindex="-1" aria-labelledby="dailyAppointmentsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dailyAppointmentsModalLabel">Atenciones Diarias - <span id="modalSpecialtyName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Loading indicator -->
                    <div v-if="isLoadingDailyAppointments" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando atenciones diarias, por favor espere...</p>
                    </div>
                    
                    <div v-else>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Día</th>
                                        <th class="text-center">Atenciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="day in currentMonthWorkingDays">
                                        <td>{{ formatDate(day.date) }}</td>
                                        <td>{{ day.day_name }}</td>
                                        <td class="text-center">
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center" v-model.number="day.appointments_count" min="0">
                                                <button class="btn btn-outline-primary" @click="saveDailyAppointmentFromModal(day)">
                                                    <i class="fas fa-save"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th colspan="2">Total</th>
                                        <th class="text-center">{{ getTotalDailyAppointments() }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Vue application
        document.addEventListener('DOMContentLoaded', function() {
            const app = new Vue({
                el: '#app',
                data: {
                    isLoading: true,
                    isLoadingDailyAppointments: false,
                    errorMessage: '',
                    successMessage: '',
                    selectedEps: '',
                    selectedYear: <?php echo $activeYear; ?>,
                    selectedMonth: <?php echo $currentMonth; ?>,
                    searchTerm: '',
                    currentDate: '<?php echo $currentDate; ?>',
                    epsList: <?php echo json_encode($epsList); ?>,
                    specialtiesList: <?php echo json_encode($specialtiesList); ?>,
                    yearsList: [],
                    monthlyProjections: [],
                    monthWorkingDays: 0,
                    dailyAppointment: {
                        date: '<?php echo $currentDate; ?>',
                        eps_id: '',
                        specialty_id: '',
                        count: 0
                    },
                    currentSpecialty: null,
                    currentMonthWorkingDays: [],
                    dailyAppointmentsModal: null,
                    complianceChart: null,
                    filters: {
                        specialty: '',
                        complianceStatus: ''
                    }
                },
                computed: {
                    monthName() {
                        const months = [
                            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                        ];
                        return months[this.selectedMonth - 1];
                    },
                    totalProjectedAppointments() {
                        return this.monthlyProjections.reduce((total, item) => total + parseInt(item.projected_appointments || 0), 0);
                    },
                    totalActualAppointments() {
                        return this.monthlyProjections.reduce((total, item) => total + parseInt(item.actual_appointments || 0), 0);
                    },
                    compliancePercentage() {
                        if (this.totalProjectedAppointments === 0) return 0;
                        return Math.round((this.totalActualAppointments / this.totalProjectedAppointments) * 100);
                    },
                    complianceStatusClass() {
                        const percentage = this.compliancePercentage;
                        if (percentage < 80) return 'danger';
                        if (percentage >= 80 && percentage < 95) return 'warning';
                        return 'success';
                    },
                    canSaveDailyAppointment() {
                        const appointment = this.dailyAppointment;
                        return appointment.date && 
                               (appointment.eps_id || this.selectedEps) && 
                               appointment.specialty_id && 
                               appointment.count >= 0;
                    },
                    filteredProjections() {
                        if (!this.searchTerm && !this.filters.specialty && !this.filters.complianceStatus) {
                            return this.monthlyProjections;
                        }
                        
                        return this.monthlyProjections.filter(projection => {
                            // Filter by search term
                            if (this.searchTerm && !projection.specialty_name.toLowerCase().includes(this.searchTerm.toLowerCase())) {
                                return false;
                            }
                            
                            // Filter by specialty
                            if (this.filters.specialty && projection.specialty_id != this.filters.specialty) {
                                return false;
                            }
                            
                            // Filter by compliance status
                            if (this.filters.complianceStatus) {
                                const compliance = this.getCompliancePercentage(projection);
                                const status = this.getComplianceClass(projection);
                                
                                if (this.filters.complianceStatus !== status) {
                                    return false;
                                }
                            }
                            
                            return true;
                        });
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
                    loadMonthlyProjections() {
                        if (!this.selectedEps) {
                            this.errorMessage = 'Por favor seleccione una EPS.';
                            this.isLoading = false;
                            return;
                        }
                        
                        this.isLoading = true;
                        this.clearMessages();
                        
                        fetch(`../api/appointments.php?action=getMonthlyProjections&eps_id=${this.selectedEps}&year=${this.selectedYear}&month=${this.selectedMonth}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.monthlyProjections = data.projections;
                                    
                                    // Get working days from first projection (all projections have the same value)
                                    if (this.monthlyProjections.length > 0) {
                                        this.monthWorkingDays = this.monthlyProjections[0].working_days;
                                    }
                                    
                                    // Set EPS ID in daily appointment form
                                    this.dailyAppointment.eps_id = this.selectedEps;
                                    
                                    // Update chart
                                    this.$nextTick(() => {
                                        this.updateComplianceChart();
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar las proyecciones mensuales.';
                                }
                                this.isLoading = false;
                            })
                            .catch(error => {
                                console.error('Error loading monthly projections:', error);
                                this.errorMessage = 'Error al cargar las proyecciones mensuales.';
                                this.isLoading = false;
                            });
                    },
                    saveActualAppointments(projection) {
                        fetch('../api/appointments.php?action=saveActualAppointments', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                id: projection.id,
                                actual_appointments: projection.actual_appointments
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'Atenciones actuales guardadas correctamente.';
                                // Reload projections to get updated data
                                this.loadMonthlyProjections();
                            } else {
                                this.errorMessage = data.message || 'Error al guardar las atenciones actuales.';
                            }
                        })
                        .catch(error => {
                            console.error('Error saving actual appointments:', error);
                            this.errorMessage = 'Error al guardar las atenciones actuales.';
                        });
                    },
                    recalculateProjections() {
                        if (!this.selectedEps) {
                            this.errorMessage = 'Por favor seleccione una EPS.';
                            return;
                        }
                        
                        this.isLoading = true;
                        this.clearMessages();
                        
                        fetch(`../api/appointments.php?action=recalculateProjections&eps_id=${this.selectedEps}&year=${this.selectedYear}`, {
                            method: 'POST'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'Proyecciones recalculadas correctamente.';
                                // Reload projections
                                this.loadMonthlyProjections();
                            } else {
                                this.errorMessage = data.message || 'Error al recalcular las proyecciones.';
                                this.isLoading = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error recalculating projections:', error);
                            this.errorMessage = 'Error al recalcular las proyecciones.';
                            this.isLoading = false;
                        });
                    },
                    saveDailyAppointment() {
                        if (!this.canSaveDailyAppointment) {
                            this.errorMessage = 'Por favor complete todos los campos.';
                            return;
                        }
                        
                        const appointmentData = {
                            eps_id: this.dailyAppointment.eps_id || this.selectedEps,
                            specialty_id: this.dailyAppointment.specialty_id,
                            appointment_date: this.dailyAppointment.date,
                            appointments_count: this.dailyAppointment.count
                        };
                        
                        fetch('../api/appointments.php?action=saveDailyAppointments', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(appointmentData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'Atenciones diarias guardadas correctamente.';
                                // Reset form
                                this.dailyAppointment.specialty_id = '';
                                this.dailyAppointment.count = 0;
                                // Reload projections to see updated actual appointments
                                this.loadMonthlyProjections();
                            } else {
                                this.errorMessage = data.message || 'Error al guardar las atenciones diarias.';
                            }
                        })
                        .catch(error => {
                            console.error('Error saving daily appointments:', error);
                            this.errorMessage = 'Error al guardar las atenciones diarias.';
                        });
                    },
                    viewDailyAppointments(projection) {
                        this.currentSpecialty = projection;
                        document.getElementById('modalSpecialtyName').textContent = projection.specialty_name;
                        this.isLoadingDailyAppointments = true;
                        
                        // Get the first and last day of the month
                        const year = this.selectedYear;
                        const month = this.selectedMonth;
                        const firstDay = new Date(year, month - 1, 1);
                        const lastDay = new Date(year, month, 0);
                        
                        // Prepare array of working days
                        this.currentMonthWorkingDays = [];
                        
                        const currentDate = new Date(firstDay);
                        while (currentDate <= lastDay) {
                            const dayOfWeek = currentDate.getDay(); // 0 (Sunday) to 6 (Saturday)
                            
                            // Only include Monday to Saturday (exclude Sunday)
                            if (dayOfWeek !== 0) {
                                const dateString = currentDate.toISOString().split('T')[0];
                                const dayName = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'][dayOfWeek];
                                
                                this.currentMonthWorkingDays.push({
                                    date: dateString,
                                    day: dayOfWeek,
                                    day_name: dayName,
                                    appointments_count: 0
                                });
                            }
                            
                            // Move to next day
                            currentDate.setDate(currentDate.getDate() + 1);
                        }
                        
                        // Get daily appointments for this month and specialty
                        const month_str = month.toString().padStart(2, '0');
                        fetch(`../api/appointments.php?action=getDailyAppointments&eps_id=${this.selectedEps}&specialty_id=${projection.specialty_id}&date=${year}-${month_str}-01`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Update appointments in our working days array
                                    data.appointments.forEach(appointment => {
                                        const index = this.currentMonthWorkingDays.findIndex(day => day.date === appointment.appointment_date);
                                        if (index !== -1) {
                                            this.currentMonthWorkingDays[index].appointments_count = appointment.appointments_count;
                                        }
                                    });
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar las atenciones diarias.';
                                }
                                this.isLoadingDailyAppointments = false;
                            })
                            .catch(error => {
                                console.error('Error loading daily appointments:', error);
                                this.errorMessage = 'Error al cargar las atenciones diarias.';
                                this.isLoadingDailyAppointments = false;
                            });
                        
                        this.dailyAppointmentsModal.show();
                    },
                    saveDailyAppointmentFromModal(day) {
                        const appointmentData = {
                            eps_id: this.selectedEps,
                            specialty_id: this.currentSpecialty.specialty_id,
                            appointment_date: day.date,
                            appointments_count: day.appointments_count
                        };
                        
                        fetch('../api/appointments.php?action=saveDailyAppointments', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(appointmentData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'Atenciones diarias guardadas correctamente.';
                                // Reload projections to see updated actual appointments
                                this.loadMonthlyProjections();
                            } else {
                                this.errorMessage = data.message || 'Error al guardar las atenciones diarias.';
                            }
                        })
                        .catch(error => {
                            console.error('Error saving daily appointments:', error);
                            this.errorMessage = 'Error al guardar las atenciones diarias.';
                        });
                    },
                    getTotalDailyAppointments() {
                        return this.currentMonthWorkingDays.reduce((total, day) => total + parseInt(day.appointments_count || 0), 0);
                    },
                    handleSelectionChange() {
                        this.loadMonthlyProjections();
                    },
                    getCompliancePercentage(projection) {
                        if (!projection.projected_appointments) return 0;
                        return Math.round((projection.actual_appointments / projection.projected_appointments) * 100);
                    },
                    getComplianceClass(projection) {
                        const percentage = this.getCompliancePercentage(projection);
                        if (percentage < 80) return 'bg-danger';
                        if (percentage >= 80 && percentage < 95) return 'bg-warning';
                        return 'bg-success';
                    },
                    getComplianceStatus(projection) {
                        const percentage = this.getCompliancePercentage(projection);
                        if (percentage < 80) return 'Crítico';
                        if (percentage >= 80 && percentage < 95) return 'En proceso';
                        return 'Cumplido';
                    },
                    getStatusText(status) {
                        switch (status) {
                            case 'success': return 'Cumplido';
                            case 'warning': return 'En proceso';
                            case 'danger': return 'Crítico';
                            default: return 'Desconocido';
                        }
                    },
                    updateComplianceChart() {
                        const ctx = document.getElementById('compliance-chart').getContext('2d');
                        
                        // Destroy existing chart if exists
                        if (this.complianceChart) {
                            this.complianceChart.destroy();
                        }
                        
                        // Group data by specialty
                        const specialties = {};
                        this.monthlyProjections.forEach(projection => {
                            specialties[projection.specialty_name] = specialties[projection.specialty_name] || {
                                projected: 0,
                                actual: 0
                            };
                            specialties[projection.specialty_name].projected += parseInt(projection.projected_appointments || 0);
                            specialties[projection.specialty_name].actual += parseInt(projection.actual_appointments || 0);
                        });
                        
                        // Prepare data for chart
                        const labels = Object.keys(specialties);
                        const projectedData = labels.map(label => specialties[label].projected);
                        const actualData = labels.map(label => specialties[label].actual);
                        
                        this.complianceChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Atenciones Proyectadas',
                                        data: projectedData,
                                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                        borderColor: 'rgb(54, 162, 235)',
                                        borderWidth: 1
                                    },
                                    {
                                        label: 'Atenciones Realizadas',
                                        data: actualData,
                                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                        borderColor: 'rgb(75, 192, 192)',
                                        borderWidth: 1
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
                                            footer: function(tooltipItems) {
                                                const projected = tooltipItems[0].parsed.y;
                                                const actual = tooltipItems.length > 1 ? tooltipItems[1].parsed.y : 0;
                                                const percentage = projected ? Math.round((actual / projected) * 100) : 0;
                                                return `Cumplimiento: ${percentage}%`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    },
                    exportToExcel() {
                        if (this.monthlyProjections.length === 0) {
                            this.errorMessage = 'No hay datos para exportar.';
                            return;
                        }
                        
                        const epsName = this.epsList.find(eps => eps.id == this.selectedEps)?.name || 'Todas';
                        const filename = `Atenciones_${epsName}_${this.monthName}_${this.selectedYear}.xlsx`;
                        
                        exportTableToExcel('projections-table', filename);
                        
                        this.successMessage = 'Datos exportados correctamente.';
                        setTimeout(() => {
                            this.successMessage = '';
                        }, 3000);
                    },
                    formatDate(dateString) {
                        const date = new Date(dateString);
                        return date.toLocaleDateString('es-ES', { 
                            day: '2-digit', 
                            month: '2-digit', 
                            year: 'numeric' 
                        });
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
                    this.dailyAppointmentsModal = new bootstrap.Modal(document.getElementById('dailyAppointmentsModal'));
                    
                    // Check for query parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('eps_id')) {
                        const epsId = urlParams.get('eps_id');
                        this.selectedEps = epsId;
                    }
                    
                    // Load projections once EPS is selected
                    if (this.selectedEps) {
                        this.loadMonthlyProjections();
                    } else {
                        this.isLoading = false;
                    }
                }
            });
        });
    </script>
</body>
</html>
