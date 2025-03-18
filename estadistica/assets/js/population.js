/**
 * Population management JavaScript for Quimiosalud SAS
 */

// Vue application for population management
const populationApp = Vue.createApp({
    data() {
        return {
            // Form data for population registration
            form: {
                eps_id: '',
                year_id: 0,
                month: 1,
                total_population: 0,
                active_population: 0,
                fertile_women: 0,
                pregnant_women: 0,
                adults: 0,
                pediatric_diagnosed: 0,
                minors_follow_up: 0
            },
            // List of populations
            populations: [],
            // List of EPS
            epsList: [],
            // Month names
            months: [
                'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Enero'
            ],
            // Loading state
            loading: false,
            // Toast message
            message: '',
            // Editing state
            isEditing: false,
            errorMessage: '' // Added to display validation errors
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

        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const yearId = urlParams.get('year_id') || 0;
        const epsId = urlParams.get('eps_id') || 0;
        const month = urlParams.get('month') || 1;

        // Set form defaults
        this.form.year_id = yearId;
        this.form.eps_id = epsId !== '0' ? epsId : '';
        this.form.month = parseInt(month);

        // Load data
        this.loadEPS();
        this.loadPopulations();
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
         * Load population data
         */
        loadPopulations() {
            this.loading = true;

            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const yearId = urlParams.get('year_id') || 0;
            const epsId = urlParams.get('eps_id') || 0;
            const month = urlParams.get('month') || 0;

            fetch(`api/population.php?action=getPopulations&year_id=${yearId}&eps_id=${epsId}&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.populations = data.populations;
                    } else {
                        this.showMessage('Error al cargar las poblaciones: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading populations:', error);
                    this.showMessage('Error al cargar las poblaciones');
                });
        },

        /**
         * Save population data
         */
        savePopulation() {
            this.loading = true;
            this.errorMessage = ''; // Clear previous error message

            // Validate all required fields
            const requiredFields = {
                eps_id: this.form.eps_id,
                year_id: this.form.year_id,
                month: this.form.month,
                total_population: this.form.total_population,
                active_population: this.form.active_population,
                fertile_women: this.form.fertile_women,
                pregnant_women: this.form.pregnant_women,
                adults: this.form.adults,
                pediatric_diagnosed: this.form.pediatric_diagnosed,
                minors_follow_up: this.form.minors_follow_up
            };

            const emptyFields = Object.entries(requiredFields)
                .filter(([_, value]) => value === '' || value === 0 || value === null || value === undefined)
                .map(([field]) => field);

            if (emptyFields.length > 0) {
                this.errorMessage = `Por favor complete los siguientes campos: ${emptyFields.join(', ')}`;
                this.loading = false;
                return;
            }


            fetch('api/population.php?action=savePopulation', {
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
                        this.loadPopulations();
                        this.resetForm();
                    } else {
                        this.showMessage('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error saving population:', error);
                    this.showMessage('Error al guardar la población');
                });
        },

        /**
         * Edit population data
         * @param {Object} population - Population data to edit
         */
        editPopulation(population) {
            this.isEditing = true;
            this.form = { ...population };
        },

        /**
         * Calculate projections for a population
         * @param {Object} population - Population data for projections
         */
        calculateProjections(population) {
            this.loading = true;

            fetch('api/population.php?action=calculateProjections', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    eps_id: population.eps_id,
                    year_id: population.year_id,
                    month: population.month
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
                    console.error('Error calculating projections:', error);
                    this.showMessage('Error al calcular las proyecciones');
                });
        },

        /**
         * Reset the form
         */
        resetForm() {
            this.isEditing = false;
            this.form = {
                eps_id: '',
                year_id: this.form.year_id,
                month: this.form.month,
                total_population: 0,
                active_population: 0,
                fertile_women: 0,
                pregnant_women: 0,
                adults: 0,
                pediatric_diagnosed: 0,
                minors_follow_up: 0
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
         * Export population data to Excel
         */
        exportToExcel() {
            exportTableToExcel('populationTable', 'Poblacion_Quimiosalud');
        },

        /**
         * Export population data to PDF
         */
        exportToPDF() {
            exportTableToPDF('populationTable', 'Poblacion_Quimiosalud', 'Registro de Población - Quimiosalud SAS');
        }
    }
});

// Mount the app when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('populationApp')) {
        populationApp.mount('#populationApp');
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