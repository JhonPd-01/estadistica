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
            <i class="fas fa-users me-2"></i>
            Gestión de Población
        </h1>
        
        <?php if (!$activeYear): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No hay año de gestión activo. Por favor, configure un año de gestión en la sección de configuración.
        </div>
        <?php else: ?>
        
        <div id="populationApp">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Registrar/Actualizar Población</h5>
                    <span class="badge bg-primary">{{ currentMonth }}</span>
                </div>
                <div class="card-body">
                    <form @submit.prevent="savePopulation">
                        <div class="row mb-3">
                          <div class="col-md-6">
                                <label for="eps_id" class="form-label">EPS</label>
                                <select id="eps_id" v-model="form.eps_id" class="form-select" required>
                                    <option value="">Seleccione una EPS</option>
                                    <option v-for="eps in epsList" :key="eps.id" :value="eps.id">{{ eps.name }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="month" class="form-label">Mes</label>
                                <select id="month" v-model="form.month" class="form-select" required>
                                    <option v-for="(name, index) in months" :key="index" :value="index+1">{{ name }}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="total_population" class="form-label">Población Total</label>
                                <input type="number" id="total_population" v-model="form.total_population" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="active_population" class="form-label">Población EPS Activa</label>
                                <input type="number" id="active_population" v-model="form.active_population" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="adults" class="form-label">Adultos</label>
                                <input type="number" id="adults" v-model="form.adults" class="form-control" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="fertile_women" class="form-label">Mujeres en Edad Fértil</label>
                                <input type="number" id="fertile_women" v-model="form.fertile_women" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="pregnant_women" class="form-label">Gestantes</label>
                                <input type="number" id="pregnant_women" v-model="form.pregnant_women" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="pediatric_diagnosed" class="form-label">Pediátricos con Diagnóstico</label>
                                <input type="number" id="pediatric_diagnosed" v-model="form.pediatric_diagnosed" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-3">
                                <label for="minors_follow_up" class="form-label">Menores en Seguimiento</label>
                                <input type="number" id="minors_follow_up" v-model="form.minors_follow_up" class="form-control" min="0" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary" @click="resetForm">
                                <i class="fas fa-undo me-1"></i> Limpiar
                            </button>
                            <button type="submit" class="btn btn-primary" :disabled="loading">
                                <i class="fas fa-save me-1"></i> Guardar Población
                                <span v-if="loading" class="spinner-border spinner-border-sm ms-1" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Población Registrada</h5>
                    <div>
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
                        <table class="table table-striped table-hover" id="populationTable">
                            <thead>
                                <tr>
                                    <th>EPS</th>
                                    <th>Mes</th>
                                    <th>Población Total</th>
                                    <th>Población Activa</th>
                                    <th>Adultos</th>
                                    <th>Mujeres Fértiles</th>
                                    <th>Gestantes</th>
                                    <th>Pediátricos</th>
                                    <th>Menores en Seguimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="loading">
                                    <td colspan="10" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else-if="populations.length === 0">
                                    <td colspan="10" class="text-center">No hay datos de población registrados</td>
                                </tr>
                                <tr v-for="pop in populations" :key="`${pop.eps_id}-${pop.month}`">
                                    <td>{{ getEpsName(pop.eps_id) }}</td>
                                    <td>{{ months[pop.month-1] }}</td>
                                    <td>{{ pop.total_population }}</td>
                                    <td>{{ pop.active_population }}</td>
                                    <td>{{ pop.adults }}</td>
                                    <td>{{ pop.fertile_women }}</td>
                                    <td>{{ pop.pregnant_women }}</td>
                                    <td>{{ pop.pediatric_diagnosed }}</td>
                                    <td>{{ pop.minors_follow_up }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary me-1" @click="editPopulation(pop)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success" @click="calculateProjections(pop)">
                                            <i class="fas fa-calculator"></i>
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
        </div>
        
        <?php endif; ?>
    </div>
    
    <div class="col-md-3">
        <?php include 'includes/sidebar.php'; ?>
    </div>
</div>

<script src="assets/js/population.js"></script>

<?php include 'includes/footer.php'; ?>
