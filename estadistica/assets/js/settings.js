/**
 * Settings JavaScript for Quimiosalud SAS
 */

// Vue application for settings
const settingsApp = Vue.createApp({
    data() {
        return {
            // Form data for year
            yearForm: {
                id: 0,
                year_label: '',
                start_date: '',
                end_date: '',
                active: false
            },
            // Form data for settings
            settingsForm: {
                work_days: [],
                distribution_percentage: [19, 19, 19, 19, 19, 5],
                compliance_threshold_red: 70,
                compliance_threshold_yellow: 90
            },
            // List of years
            years: [],
            // Weekday names
            weekdays: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            // Month names
            months: [
                'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Enero'
            ],
            // Loading state
            loading: false,
            // Toast message
            message: '',
            // Year to delete
            yearToDelete: null,
            // Delete modal
            deleteModal: null,
            // Editing state
            isEditingYear: false
        };
    },
    computed: {
        // Total percentage for distribution
        totalPercentage() {
            return this.settingsForm.distribution_percentage.reduce((sum, val) => sum + parseInt(val), 0);
        }
    },
    mounted() {
        // Initialize Bootstrap toast
        this.toast = new bootstrap.Toast(this.$refs.toast);
        
        // Initialize delete modal
        this.deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        // Set default dates for yearForm
        this.setDefaultDates();
        
        // Load data
        this.loadYears();
        this.loadSettings();
    },
    methods: {
        /**
         * Set default dates for year form
         */
        setDefaultDates() {
            const today = new Date();
            const year = today.getFullYear();
            
            // Set default start date (February 1st of current year)
            this.yearForm.start_date = `${year}-02-01`;
            
            // Set default end date (January 31st of next year)
            this.yearForm.end_date = `${year + 1}-01-31`;
            
            // Set default year label
            this.yearForm.year_label = `${year}-${year + 1}`;
        },
        
        /**
         * Load years data
         */
        loadYears() {
            this.loading = true;
            
            fetch('api/settings.php?action=getYears')
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.years = data.years;
                    } else {
                        this.showMessage('Error al cargar los años: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading years:', error);
                    this.showMessage('Error al cargar los años');
                });
        },
        
        /**
         * Load settings data
         */
        loadSettings() {
            fetch('api/settings.php?action=getSettings')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Work days
                        this.settingsForm.work_days = data.settings.work_days.split(',');
                        
                        // Distribution percentage
                        this.settingsForm.distribution_percentage = data.settings.distribution_percentage.split(',').map(p => parseInt(p));
                        
                        // Compliance thresholds
                        this.settingsForm.compliance_threshold_red = parseInt(data.settings.compliance_threshold_red);
                        this.settingsForm.compliance_threshold_yellow = parseInt(data.settings.compliance_threshold_yellow);
                    } else {
                        this.showMessage('Error al cargar la configuración: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading settings:', error);
                    this.showMessage('Error al cargar la configuración');
                });
        },
        
        /**
         * Save year
         */
        saveYear() {
            this.loading = true;
            
            const action = this.isEditingYear ? 'updateYear' : 'createYear';
            
            fetch(`api/settings.php?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.yearForm)
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.showMessage(data.message);
                        this.loadYears();
                        this.resetYearForm();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error saving year:', error);
                    this.showMessage('Error al guardar el año');
                });
        },
        
        /**
         * Save settings
         */
        saveSettings() {
            if (this.totalPercentage !== 100) {
                this.showMessage('La suma de los porcentajes debe ser 100%');
                return;
            }
            
            this.loading = true;
            
            fetch('api/settings.php?action=saveSettings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    work_days: this.settingsForm.work_days.join(','),
                    distribution_percentage: this.settingsForm.distribution_percentage.join(','),
                    compliance_threshold_red: this.settingsForm.compliance_threshold_red,
                    compliance_threshold_yellow: this.settingsForm.compliance_threshold_yellow
                })
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.showMessage(data.message);
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error saving settings:', error);
                    this.showMessage('Error al guardar la configuración');
                });
        },
        
        /**
         * Edit year
         * @param {Object} year - Year data to edit
         */
        editYear(year) {
            this.isEditingYear = true;
            this.yearForm = { ...year };
            window.scrollTo(0, 0);
        },
        
        /**
         * Confirm year deletion
         * @param {Object} year - Year to delete
         */
        confirmDeleteYear(year) {
            this.yearToDelete = year;
            this.deleteModal.show();
        },
        
        /**
         * Delete year
         */
        deleteYear() {
            if (!this.yearToDelete) return;
            
            this.loading = true;
            
            fetch('api/settings.php?action=deleteYear', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: this.yearToDelete.id })
            })
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    this.deleteModal.hide();
                    
                    if (data.success) {
                        this.showMessage(data.message);
                        this.loadYears();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    this.deleteModal.hide();
                    console.error('Error deleting year:', error);
                    this.showMessage('Error al eliminar el año');
                });
        },
        
        /**
         * Reset the year form
         */
        resetYearForm() {
            this.isEditingYear = false;
            this.yearForm = {
                id: 0,
                year_label: '',
                start_date: '',
                end_date: '',
                active: false
            };
            this.setDefaultDates();
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
         * Get color for progress bar
         * @param {number} index - Index of the month
         * @returns {string} Bootstrap color class
         */
        getColorForIndex(index) {
            const colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
            return colors[index % colors.length];
        }
    }
});

// Mount the app when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('settingsApp')) {
        settingsApp.mount('#settingsApp');
    }
});
