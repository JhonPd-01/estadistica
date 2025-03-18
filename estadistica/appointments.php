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
            <i class="fas fa-calendar-check me-2"></i>
            Gestión de Atenciones
        </h1>
        
        <?php if (!$activeYear): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No hay año de gestión activo. Por favor, configure un año de gestión en la sección de configuración.
        </div>
        <?php else: ?>
        
        <div id="appointmentsApp">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Registrar Atenciones</h5>
                    <span class="badge bg-primary">{{ currentMonth }}</span>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveAppointment">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="eps_id" class="form-label">EPS</label>
                                <select id="eps_id" v-model="form.eps_id" class="form-select" required>
                                    <option value="">Seleccione una EPS</option>
                                    <option v-for="eps in epsList" :key="eps.id" :value="eps.id">{{ eps.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="month" class="form-label">Mes</label>
                                <select id="month" v-model="form.month" class="form-select" required>
                                    <option v-for="(name, index) in months" :key="index" :value="index+1">{{ name }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="specialty_id" class="form-label">Especialidad</label>
                                <select id="specialty_id" v-model="form.specialty_id" class="form-select" required>
                                    <option value="">Seleccione una especialidad</option>
                                    <option v-for="specialty in specialties" :key="specialty.id" :value="specialty.id">{{ specialty.name }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="appointment_date" class="form-label">Fecha de Atención</label>
                                <input type="date" id="appointment_date" v-model="form.appointment_date" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="quantity" class="form-label">Cantidad de Atenciones</label>
                                <input type="number" id="quantity" v-model="form.quantity" class="form-control" min="1" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" @click="resetForm">
                                <i class="fas fa-undo me-1"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="loading">
                                <i class="fas fa-save me-1"></i> Registrar Atenciones
                                <span v-if="loading" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Resumen de Atenciones</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-success me-2" @click="recalculateProjections" :disabled="loading">
                            <i class="fas fa-sync-alt me-1"></i> Recalcular
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success me-2" @click="exportToExcel">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="exportToPDF">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="appointmentsSummaryTable">
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
                                <tr v-else-if="summaries.length === 0">
                                    <td colspan="7" class="text-center">No hay datos de atenciones registrados</td>
                                </tr>
                                <tr v-for="summary in summaries" :key="`${summary.eps_id}-${summary.specialty_id}`">
                                    <td>{{ getEpsName(summary.eps_id) }}</td>
                                    <td>{{ getSpecialtyName(summary.specialty_id) }}</td>
                                    <td>{{ summary.projected_qty }}</td>
                                    <td>{{ summary.completed_qty }}</td>
                                    <td>{{ summary.pending_qty }}</td>
                                    <td>{{ summary.compliance }}%</td>
                                    <td>
                                        <span class="badge rounded-pill" :class="getComplianceClass(summary.compliance)">
                                            {{ getComplianceStatus(summary.compliance) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Atenciones Registradas</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-success me-2" @click="exportAppointmentsToExcel">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="exportAppointmentsToPDF">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="appointmentsTable">
                            <thead>
                                <tr>
                                    <th>EPS</th>
                                    <th>Especialidad</th>
                                    <th>Fecha</th>
                                    <th>Cantidad</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loadingAppointments">
                                    <td colspan="5" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else-if="appointments.length === 0">
                                    <td colspan="5" class="text-center">No hay atenciones registradas</td>
                                </tr>
                                <tr v-for="appointment in appointments" :key="appointment.id">
                                    <td>{{ getEpsName(appointment.eps_id) }}</td>
                                    <td>{{ getSpecialtyName(appointment.specialty_id) }}</td>
                                    <td>{{ formatDate(appointment.appointment_date) }}</td>
                                    <td>{{ appointment.quantity }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger" @click="deleteAppointment(appointment.id)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Success alert -->
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 5">
                <div ref="toast" class="toast align-items-center text-white bg-success" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-check-circle me-2"></i> {{ message }}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            
            <!-- Delete confirmation modal -->
            <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalLabel">Confirmar eliminación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ¿Está seguro de que desea eliminar esta atención? Esta acción no se puede deshacer.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" @click="confirmDelete" :disabled="loading">
                                <i class="fas fa-trash me-1"></i> Eliminar
                                <span v-if="loading" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                            </button>
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

<script src="assets/js/appointments.js"></script>

<?php include 'includes/footer.php'; ?>
