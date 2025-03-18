/**
 * Appointments management JavaScript for Quimiosalud SAS
 */

// Vue application for appointments management
const appointmentsApp = Vue.createApp({
    data() {
        return {
            // Form data for appointment registration
            form: {
                eps_id: '',
                year_id: 0,
                month: 1,
                specialty_id: '',
                appointment_date: '',
                quantity: 1
            },
            // List of appointments
            appointments: [],
            // List of appointment summaries
            summaries: [],
            // List of EPS
            epsList: [],
            // List of specialties
            specialties: [],
            // Month names
            months: [
                'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Enero'
            ],
            // Loading states
            loading: false,
            loadingAppointments: false,
            // Toast message
            message: '',
            // Delete confirmation
            deleteId: null,
            deleteModal: null,
            // Compliance thresholds
            thresholds: {
                red: 70,
                yellow: 90
            }
        };
    },
    computed: {
        // Current month name
        currentMonth() {
            return this.months[this.form.month - 1];
        }
    },
    mounted() {
        // Initialize Bootstrap toast
        this.toast = new bootstrap.Toast(this.$refs.toast);
        
        // Initialize delete modal
        this.deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const yearId = urlParams.get('year_id') || 0;
        const epsId = urlParams.get('eps_id') || 0;
        const month = urlParams.get('month') || 1;
        
        // Set form defaults
        this.form.year_id = yearId;
        this.form.eps_id = epsId !== '0' ? epsId : '';
        this.form.month = parseInt(month);
        
        // Set default appointment date to today
        const today = new Date();
        this.form.appointment_date = today.toISOString().substr(0, 10);
        
        // Load data
        this.loadEPS();
        this.loadSpecialties();
        this.loadAppointmentSummaries();
        this.loadAppointments();
        this.loadComplianceThresholds();
    },
    methods: {
        /**
         * Load EPS data
         */
        loadEPS() {
            fetch('api/eps.php?action=getAllEPS')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.epsList = data.eps;
                    } else {
                        this.showMessage('Error al cargar las EPS: ' + data.message);
                    }
                })
                .catch(error => {
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
         * Load appointment summaries
         */
        loadAppointmentSummaries() {
            this.loading = true;
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const yearId = urlParams.get('year_id') || 0;
            const epsId = urlParams.get('eps_id') || 0;
            const month = urlParams.get('month') || 0;
            
            fetch(`api/appointments.php?action=getAppointmentSummaries&year_id=${yearId}&eps_id=${epsId}&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.summaries = data.summaries;
                    } else {
                        this.showMessage('Error al cargar el resumen de atenciones: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading appointment summaries:', error);
                    this.showMessage('Error al cargar el resumen de atenciones');
                });
        },
        
        /**
         * Load appointments
         */
        loadAppointments() {
            this.loadingAppointments = true;
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const yearId = urlParams.get('year_id') || 0;
            const epsId = urlParams.get('eps_id') || 0;
            const month = urlParams.get('month') || 0;
            
            fetch(`api/appointments.php?action=getAppointments&year_id=${yearId}&eps_id=${epsId}&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    this.loadingAppointments = false;
                    if (data.success) {
                        this.appointments = data.appointments;
                    } else {
                        this.showMessage('Error al cargar las atenciones: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loadingAppointments = false;
                    console.error('Error loading appointments:', error);
                    this.showMessage('Error al cargar las atenciones');
                });
        },
        
        /**
         * Load compliance thresholds
         */
        loadComplianceThresholds() {
            fetch('api/settings.php?action=getComplianceThresholds')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.thresholds.red = parseInt(data.thresholds.red);
                        this.thresholds.yellow = parseInt(data.thresholds.yellow);
                    }
                })
                .catch(error => {
                    console.error('Error loading compliance thresholds:', error);
                });
        },
        
        /**
         * Save appointment
         */
        saveAppointment() {
            this.loading = true;
            
            // Add year_id from URL if not set
            if (!this.form.year_id) {
                const urlParams = new URLSearchParams(window.location.search);
                this.form.year_id = urlParams.get('year_id') || 0;
            }
            
            fetch('api/appointments.php?action=saveAppointment', {
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
                        this.loadAppointmentSummaries();
                        this.loadAppointments();
                        this.resetForm();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error saving appointment:', error);
                    this.showMessage('Error al guardar la atención');
                });
        },
        
        /**
         * Confirm appointment deletion
         * @param {number} id - Appointment ID to delete
         */
        deleteAppointment(id) {
            this.deleteId = id;
            this.deleteModal.show();
        },
        
        /**
         * Delete appointment
         */
        confirmDelete() {
            if (!this.deleteId) return;
            
            this.loading = true;
            
            fetch('api/appointments.php?action=deleteAppointment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: this.deleteId })
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    this.deleteModal.hide();
                    
                    if (data.success) {
                        this.showMessage(data.message);
                        this.loadAppointmentSummaries();
                        this.loadAppointments();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    this.deleteModal.hide();
                    console.error('Error deleting appointment:', error);
                    this.showMessage('Error al eliminar la atención');
                });
        },
        
        /**
         * Recalculate appointment projections
         */
        recalculateProjections() {
            this.loading = true;
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const yearId = urlParams.get('year_id') || 0;
            const epsId = urlParams.get('eps_id') || 0;
            const month = urlParams.get('month') || 0;
            
            fetch('api/appointments.php?action=recalculateProjections', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ year_id: yearId, eps_id: epsId, month: month })
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.showMessage(data.message);
                        this.loadAppointmentSummaries();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error recalculating projections:', error);
                    this.showMessage('Error al recalcular las proyecciones');
                });
        },
        
        /**
         * Reset the form
         */
        resetForm() {
            // Keep the current year_id, eps_id, and month
            const yearId = this.form.year_id;
            const epsId = this.form.eps_id;
            const month = this.form.month;
            
            // Reset form
            this.form = {
                eps_id: epsId,
                year_id: yearId,
                month: month,
                specialty_id: '',
                appointment_date: new Date().toISOString().substr(0, 10),
                quantity: 1
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
         * Get EPS name by ID
         * @param {number} id - EPS ID
         * @returns {string} EPS name
         */
        getEpsName(id) {
            const eps = this.epsList.find(e => e.id == id);
            return eps ? eps.name : 'Desconocida';
        },
        
        /**
         * Get specialty name by ID
         * @param {number} id - Specialty ID
         * @returns {string} Specialty name
         */
        getSpecialtyName(id) {
            const specialty = this.specialties.find(s => s.id == id);
            return specialty ? specialty.name : 'Desconocida';
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
         * Get compliance badge class based on percentage
         * @param {number} percentage - Compliance percentage
         * @returns {string} Bootstrap badge class
         */
        getComplianceClass(percentage) {
            if (percentage < this.thresholds.red) {
                return 'bg-danger';
            } else if (percentage < this.thresholds.yellow) {
                return 'bg-warning';
            } else {
                return 'bg-success';
            }
        },
        
        /**
         * Get compliance status text based on percentage
         * @param {number} percentage - Compliance percentage
         * @returns {string} Status text
         */
        getComplianceStatus(percentage) {
            if (percentage < this.thresholds.red) {
                return 'Incumplimiento';
            } else if (percentage < this.thresholds.yellow) {
                return 'Alerta';
            } else {
                return 'Cumplimiento';
            }
        },
        
        /**
         * Export appointment summaries to Excel
         */
        exportToExcel() {
            exportTableToExcel('appointmentsSummaryTable', 'Resumen_Atenciones_Quimiosalud');
        },
        
        /**
         * Export appointment summaries to PDF
         */
        exportToPDF() {
            exportTableToPDF('appointmentsSummaryTable', 'Resumen_Atenciones_Quimiosalud', 'Resumen de Atenciones - Quimiosalud SAS');
        },
        
        /**
         * Export appointments to Excel
         */
        exportAppointmentsToExcel() {
            exportTableToExcel('appointmentsTable', 'Atenciones_Quimiosalud');
        },
        
        /**
         * Export appointments to PDF
         */
        exportAppointmentsToPDF() {
            exportTableToPDF('appointmentsTable', 'Atenciones_Quimiosalud', 'Registro de Atenciones - Quimiosalud SAS');
        }
    }
});

// Mount the app when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('appointmentsApp')) {
        appointmentsApp.mount('#appointmentsApp');
    }
});

// Handle sidebar filter form submission
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const yearId = document.getElementById('yearSelect').value;
            const epsId = document.getElementById('epsSelect').value;
            const monthElement = document.getElementById('monthSelect');
            const month = monthElement ? monthElement.value : 0;
            
            // Update URL
            const url = new URL(window.location.href);
            url.searchParams.set('year_id', yearId);
            url.searchParams.set('eps_id', epsId);
            if (month && month != 0) {
                url.searchParams.set('month', month);
            } else {
                url.searchParams.delete('month');
            }
            
            window.location.href = url.toString();
        });
    }
});
