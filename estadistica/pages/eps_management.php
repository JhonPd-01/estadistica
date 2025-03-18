<?php
/**
 * EPS Management Page
 * Allows management of EPS entities and their contracted services
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if user is logged in
requireLogin();

// Set page title
$pageTitle = "Gestión de EPS";

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
$epsQuery = "SELECT id, name, status FROM eps ORDER BY name";
$epsStmt = $db->prepare($epsQuery);
$epsStmt->execute();
$epsList = $epsStmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <i class="fas fa-hospital me-2"></i> Gestión de EPS
                </h2>
                <div>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newEpsModal">
                        <i class="fas fa-plus-circle me-1"></i> Nueva EPS
                    </button>
                </div>
            </div>
            
            <!-- EPS List -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Lista de EPS</h5>
                </div>
                <div class="card-body">
                    <!-- Loading indicator -->
                    <div v-if="isLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando datos, por favor espere...</p>
                    </div>
                    
                    <div v-else class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Población Activa</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="eps in epsList">
                                    <td>{{ eps.name }}</td>
                                    <td class="text-center">
                                        <span class="badge" :class="eps.status ? 'bg-success' : 'bg-danger'">
                                            {{ eps.status ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="text-center">{{ formatNumber(getEpsPopulation(eps.id)) }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1" @click="editEps(eps)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" @click="loadContractedServices(eps.id)">
                                            <i class="fas fa-cog"></i> Servicios
                                        </button>
                                        <button v-if="isAdmin" class="btn btn-sm btn-outline-danger" @click="confirmDeleteEps(eps)" :disabled="eps.name === 'Nueva EPS'">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Contracted Services Section -->
            <div v-if="selectedEps" class="card mb-4">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Servicios Contratados - {{ getEpsName(selectedEps) }}</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary" @click="selectedEps = null">
                                <i class="fas fa-times me-1"></i> Cerrar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Loading indicator -->
                    <div v-if="isLoadingServices" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2">Cargando servicios, por favor espere...</p>
                    </div>
                    
                    <div v-else>
                        <!-- Year selector -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="servicesYearSelect" class="form-label">Año:</label>
                                <select id="servicesYearSelect" class="form-select" v-model="selectedYear" @change="loadContractedServices(selectedEps)">
                                    <option v-for="year in yearsList" :value="year">{{ year }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Especialidad</th>
                                        <th>Descripción</th>
                                        <th class="text-center">Atenciones por Paciente</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="service in contractedServices">
                                        <td>{{ service.specialty_name }}</td>
                                        <td>{{ service.specialty_description }}</td>
                                        <td class="text-center">
                                            <input type="number" class="form-control form-control-sm text-center" v-model.number="service.appointments_per_patient" min="0">
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary" @click="saveContractedService(service)">
                                                <i class="fas fa-save me-1"></i> Guardar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <p class="text-muted small">
                                <i class="fas fa-info-circle me-1"></i> Las atenciones por paciente determinan cuántas veces al año un paciente debe ser atendido 
                                por cada especialidad. Este valor se usa para calcular las proyecciones mensuales.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <?php include_once '../components/footer.php'; ?>
    </div>
    
    <!-- Edit EPS Modal -->
    <div class="modal fade" id="editEpsModal" tabindex="-1" aria-labelledby="editEpsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEpsModalLabel">Editar EPS</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editEpsForm">
                        <div class="mb-3">
                            <label for="editEpsName" class="form-label">Nombre de EPS</label>
                            <input type="text" class="form-control" id="editEpsName" v-model="currentEditItem.name" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="editEpsStatus" v-model="currentEditItem.status">
                            <label class="form-check-label" for="editEpsStatus">
                                Activa
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" @click="updateEps">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar la EPS <strong>{{ currentEditItem.name }}</strong>?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Esta acción eliminará todos los datos relacionados con esta EPS, incluyendo población, servicios contratados y atenciones. Esta acción no se puede deshacer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" @click="deleteEps">Eliminar</button>
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
                    isLoadingServices: false,
                    errorMessage: '',
                    successMessage: '',
                    epsList: <?php echo json_encode($epsList); ?>,
                    selectedEps: null,
                    selectedYear: <?php echo $activeYear; ?>,
                    contractedServices: [],
                    currentEditItem: {
                        id: null,
                        name: '',
                        status: true
                    },
                    isAdmin: <?php echo isAdmin() ? 'true' : 'false'; ?>,
                    yearsList: [],
                    epsPopulations: {},
                    editEpsModal: null,
                    deleteConfirmModal: null
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
                    loadEpsList() {
                        this.isLoading = true;
                        
                        fetch('../api/eps.php?action=list')
                            .then(response => response.json())
                            .then(data => {
                                this.epsList = data;
                                this.loadEpsPopulations();
                                this.isLoading = false;
                            })
                            .catch(error => {
                                console.error('Error loading EPS list:', error);
                                this.errorMessage = 'Error al cargar la lista de EPS.';
                                this.isLoading = false;
                            });
                    },
                    loadEpsPopulations() {
                        // Get current month
                        const currentMonth = new Date().getMonth() + 1;
                        
                        // For each EPS, get the current population
                        this.epsList.forEach(eps => {
                            fetch(`../api/population.php?action=get&eps_id=${eps.id}&year=${this.selectedYear}&month=${currentMonth}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.population) {
                                        this.$set(this.epsPopulations, eps.id, data.population.active_population);
                                    } else {
                                        this.$set(this.epsPopulations, eps.id, 0);
                                    }
                                })
                                .catch(error => {
                                    console.error(`Error loading population for EPS ${eps.id}:`, error);
                                    this.$set(this.epsPopulations, eps.id, 0);
                                });
                        });
                    },
                    getEpsPopulation(epsId) {
                        return this.epsPopulations[epsId] || 0;
                    },
                    getEpsName(epsId) {
                        const eps = this.epsList.find(e => e.id == epsId);
                        return eps ? eps.name : '';
                    },
                    loadContractedServices(epsId) {
                        this.selectedEps = epsId;
                        this.isLoadingServices = true;
                        
                        fetch(`../api/eps.php?action=getContractedServices&eps_id=${epsId}&year=${this.selectedYear}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.contractedServices = data.services;
                                } else {
                                    this.errorMessage = data.message || 'Error al cargar los servicios contratados.';
                                }
                                this.isLoadingServices = false;
                            })
                            .catch(error => {
                                console.error('Error loading contracted services:', error);
                                this.errorMessage = 'Error al cargar los servicios contratados.';
                                this.isLoadingServices = false;
                            });
                    },
                    saveContractedService(service) {
                        // Prepare data for sending
                        const serviceData = {
                            eps_id: this.selectedEps,
                            year: this.selectedYear,
                            specialty_id: service.specialty_id,
                            appointments_per_patient: service.appointments_per_patient
                        };
                        
                        fetch('../api/eps.php?action=saveContractedService', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(serviceData)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'Servicio contratado guardado correctamente.';
                                // Reload contracted services
                                this.loadContractedServices(this.selectedEps);
                            } else {
                                this.errorMessage = data.message || 'Error al guardar el servicio contratado.';
                            }
                        })
                        .catch(error => {
                            console.error('Error saving contracted service:', error);
                            this.errorMessage = 'Error al guardar el servicio contratado.';
                        });
                    },
                    editEps(eps) {
                        this.currentEditItem = JSON.parse(JSON.stringify(eps)); // Create a copy
                        this.editEpsModal.show();
                    },
                    updateEps() {
                        fetch('../api/eps.php?action=update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(this.currentEditItem)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.successMessage = 'EPS actualizada correctamente.';
                                // Reload EPS list
                                this.loadEpsList();
                                // Close modal
                                this.editEpsModal.hide();
                            } else {
                                this.errorMessage = data.message || 'Error al actualizar la EPS.';
                            }
                        })
                        .catch(error => {
                            console.error('Error updating EPS:', error);
                            this.errorMessage = 'Error al actualizar la EPS.';
                        });
                    },
                    confirmDeleteEps(eps) {
                        this.currentEditItem = JSON.parse(JSON.stringify(eps)); // Create a copy
                        this.deleteConfirmModal.show();
                    },
                    deleteEps() {
                        fetch(`../api/eps.php?action=delete&id=${this.currentEditItem.id}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.successMessage = 'EPS eliminada correctamente.';
                                    // Reload EPS list
                                    this.loadEpsList();
                                    // Close modal
                                    this.deleteConfirmModal.hide();
                                } else {
                                    this.errorMessage = data.message || 'Error al eliminar la EPS.';
                                }
                            })
                            .catch(error => {
                                console.error('Error deleting EPS:', error);
                                this.errorMessage = 'Error al eliminar la EPS.';
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
                    this.loadEpsList();
                    
                    // Initialize modals
                    this.editEpsModal = new bootstrap.Modal(document.getElementById('editEpsModal'));
                    this.deleteConfirmModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                    
                    // Check for query parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('success')) {
                        this.successMessage = 'Operación realizada con éxito.';
                    }
                    
                    if (urlParams.has('eps_id')) {
                        const epsId = parseInt(urlParams.get('eps_id'));
                        if (!isNaN(epsId)) {
                            this.loadContractedServices(epsId);
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
