/**
 * EPS Management JavaScript for Quimiosalud SAS
 */

// Vue application for EPS management
const epsApp = Vue.createApp({
    data() {
        return {
            // Form data for EPS
            form: {
                id: 0,
                name: '',
                active: true
            },
            // List of EPS
            epsList: [],
            // List of specialties
            specialties: [],
            // Services form data
            services: [],
            // Selected EPS for editing services
            selectedEps: null,
            // Loading states
            loading: false,
            loadingServices: false,
            // Toast message
            message: '',
            // EPS to delete
            epsToDelete: null,
            // Delete modal
            deleteModal: null,
            // Editing state
            isEditing: false
        };
    },
    mounted() {
        // Initialize Bootstrap toast
        this.toast = new bootstrap.Toast(this.$refs.toast);
        
        // Initialize delete modal
        this.deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        // Load data
        this.loadEPS();
        this.loadSpecialties();
    },
    methods: {
        /**
         * Load EPS data
         */
        loadEPS() {
            this.loading = true;
            
            fetch('api/eps.php?action=getAllEPS&includeInactive=true')
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.epsList = data.eps;
                    } else {
                        this.showMessage('Error al cargar las EPS: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading EPS:', error);
                    this.showMessage('Error al cargar las EPS');
                });
        },
        
        /**
         * Load specialties data
         */
        loadSpecialties() {
            fetch('api/appointments.php?action=getSpecialties')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.specialties = data.specialties;
                    } else {
                        this.showMessage('Error al cargar las especialidades: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading specialties:', error);
                    this.showMessage('Error al cargar las especialidades');
                });
        },
        
        /**
         * Save EPS
         */
        saveEps() {
            this.loading = true;
            
            const action = this.isEditing ? 'updateEPS' : 'createEPS';
            
            fetch(`api/eps.php?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.form)
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.showMessage(data.message);
                        this.loadEPS();
                        this.resetForm();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error saving EPS:', error);
                    this.showMessage('Error al guardar la EPS');
                });
        },
        
        /**
         * Edit EPS
         * @param {Object} eps - EPS data to edit
         */
        editEps(eps) {
            this.isEditing = true;
            this.form = { ...eps };
            window.scrollTo(0, 0);
        },
        
        /**
         * Edit services for an EPS
         * @param {Object} eps - EPS to edit services for
         */
        editServices(eps) {
            this.selectedEps = eps;
            this.loadServices(eps.id);
        },
        
        /**
         * Load services for an EPS
         * @param {number} epsId - EPS ID
         */
        loadServices(epsId) {
            this.loadingServices = true;
            
            fetch(`api/eps.php?action=getContractedServices&eps_id=${epsId}`)
                .then(response => response.json())
                .then(data => {
                    this.loadingServices = false;
                    if (data.success) {
                        // Transform data to include all specialties
                        this.services = this.specialties.map(specialty => {
                            const service = data.services.find(s => s.specialty_id == specialty.id);
                            return {
                                specialty_id: specialty.id,
                                specialty_name: specialty.name,
                                yearly_qty: service ? service.yearly_qty : 0
                            };
                        });
                    } else {
                        this.showMessage('Error al cargar los servicios: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loadingServices = false;
                    console.error('Error loading services:', error);
                    this.showMessage('Error al cargar los servicios');
                });
        },
        
        /**
         * Save services for an EPS
         */
        saveServices() {
            this.loadingServices = true;
            
            fetch('api/eps.php?action=saveContractedServices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    eps_id: this.selectedEps.id,
                    services: this.services
                })
            })
                .then(response => response.json())
                .then(data => {
                    this.loadingServices = false;
                    if (data.success) {
                        this.showMessage(data.message);
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loadingServices = false;
                    console.error('Error saving services:', error);
                    this.showMessage('Error al guardar los servicios');
                });
        },
        
        /**
         * Confirm EPS deletion
         * @param {Object} eps - EPS to delete
         */
        confirmDeleteEps(eps) {
            this.epsToDelete = eps;
            this.deleteModal.show();
        },
        
        /**
         * Delete EPS
         */
        deleteEps() {
            if (!this.epsToDelete) return;
            
            this.loading = true;
            
            fetch('api/eps.php?action=deleteEPS', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: this.epsToDelete.id })
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    this.deleteModal.hide();
                    
                    if (data.success) {
                        this.showMessage(data.message);
                        this.loadEPS();
                        
                        // If the deleted EPS was selected for services, deselect it
                        if (this.selectedEps && this.selectedEps.id === this.epsToDelete.id) {
                            this.selectedEps = null;
                        }
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    this.deleteModal.hide();
                    console.error('Error deleting EPS:', error);
                    this.showMessage('Error al eliminar la EPS');
                });
        },
        
        /**
         * Reset the form
         */
        resetForm() {
            this.isEditing = false;
            this.form = {
                id: 0,
                name: '',
                active: true
            };
        },
        
        /**
         * Show a toast message
         * @param {string} message - Message to display
         */
        showMessage(message) {
            this.message = message;
            this.toast.show();
        },
        
        /**
         * Format date for display
         * @param {string} dateString - Date string from database
         * @returns {string} Formatted date (DD/MM/YYYY)
         */
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        },
        
        /**
         * Export EPS list to Excel
         */
        exportToExcel() {
            exportTableToExcel('epsTable', 'EPS_Quimiosalud');
        }
    }
});

// Mount the app when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('epsApp')) {
        epsApp.mount('#epsApp');
    }
});
