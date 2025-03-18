<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Require user to be an admin
requireAdmin();
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-md-9">
        <h1 class="mb-4">
            <i class="fas fa-cog me-2"></i>
            Configuración
        </h1>
        
        <div id="settingsApp">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Gestión de Años</h5>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="saveYear">
                                <div class="mb-3">
                                    <label for="yearLabel" class="form-label">Etiqueta del Año</label>
                                    <input type="text" class="form-control" id="yearLabel" v-model="yearForm.year_label" placeholder="Ej: 2023-2024" required>
                                    <small class="form-text text-muted">Formato recomendado: AAAA-AAAA</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="startDate" class="form-label">Fecha de Inicio</label>
                                    <input type="date" class="form-control" id="startDate" v-model="yearForm.start_date" required>
                                    <small class="form-text text-muted">Generalmente el 1 de febrero</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="endDate" class="form-label">Fecha de Fin</label>
                                    <input type="date" class="form-control" id="endDate" v-model="yearForm.end_date" required>
                                    <small class="form-text text-muted">Generalmente el 31 de enero del siguiente año</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="yearActive" v-model="yearForm.active">
                                        <label class="form-check-label" for="yearActive">
                                            Establecer como año activo
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Solo puede haber un año activo a la vez</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" @click="resetYearForm">
                                        <i class="fas fa-undo me-1"></i> {{ isEditingYear ? 'Cancelar' : 'Limpiar' }}
                                    </button>
                                    <button type="submit" class="btn btn-primary" :disabled="loading">
                                        <i class="fas" :class="isEditingYear ? 'fa-save' : 'fa-plus-circle'"></i> 
                                        {{ isEditingYear ? 'Actualizar' : 'Agregar' }}
                                        <span v-if="loading" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Parametrización del Sistema</h5>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="saveSettings">
                                <div class="mb-3">
                                    <label for="workDays" class="form-label">Días Laborales</label>
                                    <div>
                                        <div class="form-check form-check-inline" v-for="(day, index) in weekdays" :key="index">
                                            <input class="form-check-input" type="checkbox" :id="`day${index}`" :value="day" v-model="settingsForm.work_days">
                                            <label class="form-check-label" :for="`day${index}`">{{ day }}</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Distribución de Población (% por mes)</label>
                                    <div class="row">
                                        <div class="col-4" v-for="(month, index) in months.slice(0, 6)" :key="index">
                                            <div class="input-group mb-2">
                                                <span class="input-group-text">{{ month }}</span>
                                                <input type="number" class="form-control" v-model="settingsForm.distribution_percentage[index]" min="0" max="100" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="progress mb-2">
                                        <div v-for="(percentage, index) in settingsForm.distribution_percentage" :key="index"
                                            class="progress-bar" 
                                            :class="`bg-${getColorForIndex(index)}`"
                                            role="progressbar" 
                                            :style="`width: ${percentage}%`" 
                                            :aria-valuenow="percentage"
                                            aria-valuemin="0" 
                                            aria-valuemax="100">
                                            {{ percentage }}%
                                        </div>
                                    </div>
                                    <small :class="totalPercentage === 100 ? 'text-success' : 'text-danger'">
                                        Total: {{ totalPercentage }}% (debe ser 100%)
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Umbrales de Cumplimiento</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="input-group mb-2">
                                                <span class="input-group-text bg-danger text-white">Rojo</span>
                                                <input type="number" class="form-control" v-model="settingsForm.compliance_threshold_red" min="0" max="100" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="form-text text-muted">Por debajo de este % se considera incumplimiento</small>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group mb-2">
                                                <span class="input-group-text bg-warning text-dark">Amarillo</span>
                                                <input type="number" class="form-control" v-model="settingsForm.compliance_threshold_yellow" min="0" max="100" required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="form-text text-muted">Por debajo de este % se considera alerta</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success" :disabled="loading || totalPercentage !== 100">
                                        <i class="fas fa-save me-1"></i> Guardar Configuración
                                        <span v-if="loading" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Años de Gestión</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Etiqueta</th>
                                    <th>Fecha de Inicio</th>
                                    <th>Fecha de Fin</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
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
                                <tr v-else-if="years.length === 0">
                                    <td colspan="6" class="text-center">No hay años de gestión registrados</td>
                                </tr>
                                <tr v-for="year in years" :key="year.id">
                                    <td>{{ year.id }}</td>
                                    <td>{{ year.year_label }}</td>
                                    <td>{{ formatDate(year.start_date) }}</td>
                                    <td>{{ formatDate(year.end_date) }}</td>
                                    <td>
                                        <span class="badge rounded-pill" :class="year.active ? 'bg-success' : 'bg-secondary'">
                                            {{ year.active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary me-1" @click="editYear(year)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" @click="confirmDeleteYear(year)" :disabled="year.active || years.length <= 1">
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
                            <p>¿Está seguro de que desea eliminar el año <strong>{{ yearToDelete ? yearToDelete.year_label : '' }}</strong>?</p>
                            <p class="text-danger"><strong>Advertencia:</strong> Esta acción eliminará todos los datos asociados a este año y no se puede deshacer.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" @click="deleteYear" :disabled="loading">
                                <i class="fas fa-trash me-1"></i> Eliminar
                                <span v-if="loading" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<script src="assets/js/settings.js"></script>

<?php include 'includes/footer.php'; ?>
