<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Require user to be logged in and be an admin
requireAdmin();
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-md-9">
        <h1 class="mb-4">
            <i class="fas fa-hospital-user me-2"></i>
            Gestión de EPS
        </h1>
        
        <div id="epsApp">
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">{{ isEditing ? 'Editar EPS' : 'Agregar Nueva EPS' }}</h5>
                        </div>
                        <div class="card-body">
                            <form @submit.prevent="saveEps">
                                <div class="mb-3">
                                    <label for="epsName" class="form-label">Nombre de la EPS</label>
                                    <input type="text" class="form-control" id="epsName" v-model="form.name" required>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="epsActive" v-model="form.active">
                                        <label class="form-check-label" for="epsActive">
                                            Activa
                                        </label>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" @click="resetForm">
                                        <i class="fas fa-undo me-1"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary" :disabled="loading">
                                        <i class="fas" :class="isEditing ? 'fa-save' : 'fa-plus-circle'"></i> 
                                        {{ isEditing ? 'Actualizar' : 'Agregar' }}
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
                            <h5 class="mb-0">Servicios Contratados</h5>
                        </div>
                        <div class="card-body">
                            <div v-if="!selectedEps">
                                <p class="text-muted">Seleccione una EPS para editar sus servicios contratados.</p>
                            </div>
                            <form v-else @submit.prevent="saveServices">
                                <div class="mb-3">
                                    <h6>{{ selectedEps.name }}</h6>
                                    <small class="text-muted">Cantidad de atenciones anuales por especialidad</small>
                                </div>
                                
                                <div class="mb-3" v-for="service in services" :key="service.specialty_id">
                                    <label :for="`service_${service.specialty_id}`" class="form-label">{{ service.specialty_name }}</label>
                                    <input type="number" class="form-control" :id="`service_${service.specialty_id}`" v-model="service.yearly_qty" min="0" required>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success" :disabled="loadingServices">
                                        <i class="fas fa-save me-1"></i> Guardar Servicios
                                        <span v-if="loadingServices" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">EPS Registradas</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="exportToExcel">
                        <i class="fas fa-file-excel me-1"></i> Exportar
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="epsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Fecha de Registro</th>
                                    <th>Última Actualización</th>
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
                                <tr v-else-if="epsList.length === 0">
                                    <td colspan="6" class="text-center">No hay EPS registradas</td>
                                </tr>
                                <tr v-for="eps in epsList" :key="eps.id" :class="{'table-active': selectedEps && selectedEps.id === eps.id}">
                                    <td>{{ eps.id }}</td>
                                    <td>{{ eps.name }}</td>
                                    <td>
                                        <span class="badge rounded-pill" :class="eps.active ? 'bg-success' : 'bg-secondary'">
                                            {{ eps.active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td>{{ formatDate(eps.created_at) }}</td>
                                    <td>{{ formatDate(eps.updated_at) }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary me-1" @click="editEps(eps)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info me-1" @click="editServices(eps)">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" @click="confirmDeleteEps(eps)" :disabled="epsList.length <= 1">
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
                            <p>¿Está seguro de que desea eliminar la EPS <strong>{{ epsToDelete ? epsToDelete.name : '' }}</strong>?</p>
                            <p class="text-danger"><strong>Advertencia:</strong> Esta acción eliminará todos los datos asociados a esta EPS y no se puede deshacer.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" @click="deleteEps" :disabled="loading">
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

<script src="assets/js/eps_management.js"></script>

<?php include 'includes/footer.php'; ?>
