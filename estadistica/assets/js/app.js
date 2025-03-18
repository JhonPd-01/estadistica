/**
 * Main JavaScript file for application functionality
 */

// Initialize Vue application when document is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the main Vue application if we have a Vue container
    if (document.getElementById('app')) {
        initializeVueApp();
    }
    
    // Initialize sidebar toggle behavior
    initializeSidebar();
    
    // Initialize tooltips and popovers
    initializeTooltips();
    
    // Initialize chart.js charts if any exist
    initializeCharts();
});

/**
 * Initialize the main Vue application
 */
function initializeVueApp() {
    const app = new Vue({
        el: '#app',
        data: {
            isLoading: false,
            errorMessage: '',
            successMessage: '',
            selectedEps: '',
            selectedYear: new Date().getFullYear(),
            selectedMonth: new Date().getMonth() + 1, // Current month (1-12)
            searchTerm: '',
            // Data for population management
            populationData: {
                total_population: 0,
                active_population: 0,
                fertile_women: 0,
                pregnant_women: 0,
                adults: 0,
                pediatric_diagnosed: 0,
                monitored_minors: 0
            },
            // Contracted services data (for each EPS)
            contractedServices: [],
            // Monthly projections data
            monthlyProjections: [],
            // EPS list
            epsList: [],
            // Specialties list
            specialtiesList: [],
            // Years list for selection
            yearsList: [],
            // Currently edited item
            currentEditItem: null,
            // Chart instances
            charts: {},
            // Filter states
            filters: {
                specialty: '',
                complianceStatus: ''
            }
        },
        computed: {
            // Calculate total projected appointments
            totalProjectedAppointments() {
                return this.monthlyProjections.reduce((total, item) => total + parseInt(item.projected_appointments || 0), 0);
            },
            // Calculate total actual appointments
            totalActualAppointments() {
                return this.monthlyProjections.reduce((total, item) => total + parseInt(item.actual_appointments || 0), 0);
            },
            // Calculate compliance percentage
            compliancePercentage() {
                if (this.totalProjectedAppointments === 0) return 0;
                return Math.round((this.totalActualAppointments / this.totalProjectedAppointments) * 100);
            },
            // Get compliance status class (success, warning, danger)
            complianceStatusClass() {
                const percentage = this.compliancePercentage;
                if (percentage < 80) return 'danger';
                if (percentage >= 80 && percentage < 95) return 'warning';
                return 'success';
            },
            // Format month name from number
            monthName() {
                const months = [
                    'Enero', 'Febrero', 'Marzo', 'Abril',
                    'Mayo', 'Junio', 'Julio', 'Agosto',
                    'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
                ];
                return months[this.selectedMonth - 1];
            },
            // Filter monthly projections based on search term
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
                    if (this.filters.specialty && projection.specialty_id !== parseInt(this.filters.specialty)) {
                        return false;
                    }
                    
                    // Filter by compliance status
                    if (this.filters.complianceStatus) {
                        const compliance = projection.actual_appointments / projection.projected_appointments * 100;
                        
                        if (this.filters.complianceStatus === 'success' && compliance < 95) {
                            return false;
                        }
                        if (this.filters.complianceStatus === 'warning' && (compliance < 80 || compliance >= 95)) {
                            return false;
                        }
                        if (this.filters.complianceStatus === 'danger' && compliance >= 80) {
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
        },
        methods: {
            // Load initial data
            loadInitialData() {
                this.loadEpsList();
                this.loadSpecialtiesList();
                this.loadYearsList();
                
                // If EPS and year are selected, load projections
                if (this.selectedEps && this.selectedYear) {
                    this.loadMonthlyProjections();
                }
            },
            
            // Load EPS list from API
            loadEpsList() {
                this.isLoading = true;
                fetch('../../api/eps.php?action=list')
                    .then(response => response.json())
                    .then(data => {
                        this.epsList = data;
                        // Select first EPS by default if none selected
                        if (!this.selectedEps && data.length > 0) {
                            this.selectedEps = data[0].id;
                        }
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error fetching EPS list:', error);
                        this.errorMessage = 'Error al cargar la lista de EPS.';
                        this.isLoading = false;
                    });
            },
            
            // Load specialties list from API
            loadSpecialtiesList() {
                this.isLoading = true;
                fetch('../../api/appointments.php?action=listSpecialties')
                    .then(response => response.json())
                    .then(data => {
                        this.specialtiesList = data;
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error fetching specialties list:', error);
                        this.errorMessage = 'Error al cargar la lista de especialidades.';
                        this.isLoading = false;
                    });
            },
            
            // Load years list from API
            loadYearsList() {
                this.isLoading = true;
                fetch('../../api/dashboard.php?action=listYears')
                    .then(response => response.json())
                    .then(data => {
                        this.yearsList = data;
                        // Select current year by default if none selected
                        if (this.yearsList.length > 0 && !this.yearsList.includes(this.selectedYear)) {
                            this.selectedYear = this.yearsList[0];
                        }
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error fetching years list:', error);
                        this.errorMessage = 'Error al cargar la lista de años.';
                        this.isLoading = false;
                    });
            },
            
            // Load population data for selected EPS and month
            loadPopulationData() {
                if (!this.selectedEps || !this.selectedYear || !this.selectedMonth) {
                    return;
                }
                
                this.isLoading = true;
                fetch(`../../api/population.php?action=get&eps_id=${this.selectedEps}&year=${this.selectedYear}&month=${this.selectedMonth}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.population) {
                            this.populationData = data.population;
                        } else {
                            // Initialize empty population data
                            this.populationData = {
                                total_population: 0,
                                active_population: 0,
                                fertile_women: 0,
                                pregnant_women: 0,
                                adults: 0,
                                pediatric_diagnosed: 0,
                                monitored_minors: 0
                            };
                        }
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error fetching population data:', error);
                        this.errorMessage = 'Error al cargar los datos de población.';
                        this.isLoading = false;
                    });
            },
            
            // Save population data
            savePopulationData() {
                if (!this.selectedEps || !this.selectedYear || !this.selectedMonth) {
                    this.errorMessage = 'Por favor seleccione EPS, año y mes.';
                    return;
                }
                
                this.isLoading = true;
                
                // Prepare data for sending
                const populationData = {
                    eps_id: this.selectedEps,
                    year: this.selectedYear,
                    month: this.selectedMonth,
                    ...this.populationData
                };
                
                fetch('../../api/population.php?action=save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(populationData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.successMessage = 'Datos de población guardados correctamente.';
                        // Clear error message
                        this.errorMessage = '';
                        // Recalculate projections based on new population
                        this.recalculateProjections();
                    } else {
                        this.errorMessage = data.message || 'Error al guardar los datos de población.';
                    }
                    this.isLoading = false;
                })
                .catch(error => {
                    console.error('Error saving population data:', error);
                    this.errorMessage = 'Error al guardar los datos de población.';
                    this.isLoading = false;
                });
            },
            
            // Load contracted services for selected EPS
            loadContractedServices() {
                if (!this.selectedEps || !this.selectedYear) {
                    return;
                }
                
                this.isLoading = true;
                fetch(`../../api/eps.php?action=getContractedServices&eps_id=${this.selectedEps}&year=${this.selectedYear}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.contractedServices = data.services;
                        } else {
                            this.contractedServices = [];
                            this.errorMessage = data.message || 'Error al cargar los servicios contratados.';
                        }
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error fetching contracted services:', error);
                        this.errorMessage = 'Error al cargar los servicios contratados.';
                        this.isLoading = false;
                    });
            },
            
            // Save contracted service
            saveContractedService(service) {
                if (!this.selectedEps || !this.selectedYear) {
                    this.errorMessage = 'Por favor seleccione EPS y año.';
                    return;
                }
                
                this.isLoading = true;
                
                // Prepare data for sending
                const serviceData = {
                    eps_id: this.selectedEps,
                    year: this.selectedYear,
                    specialty_id: service.specialty_id,
                    appointments_per_patient: service.appointments_per_patient
                };
                
                fetch('../../api/eps.php?action=saveContractedService', {
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
                        // Clear error message
                        this.errorMessage = '';
                        // Reload contracted services
                        this.loadContractedServices();
                        // Recalculate projections based on new contracted services
                        this.recalculateProjections();
                    } else {
                        this.errorMessage = data.message || 'Error al guardar el servicio contratado.';
                    }
                    this.isLoading = false;
                })
                .catch(error => {
                    console.error('Error saving contracted service:', error);
                    this.errorMessage = 'Error al guardar el servicio contratado.';
                    this.isLoading = false;
                });
            },
            
            // Load monthly projections for selected EPS and year
            loadMonthlyProjections() {
                if (!this.selectedEps || !this.selectedYear) {
                    return;
                }
                
                this.isLoading = true;
                fetch(`../../api/appointments.php?action=getMonthlyProjections&eps_id=${this.selectedEps}&year=${this.selectedYear}&month=${this.selectedMonth}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.monthlyProjections = data.projections;
                            // Update charts if they exist
                            this.updateCharts();
                        } else {
                            this.monthlyProjections = [];
                            this.errorMessage = data.message || 'Error al cargar las proyecciones mensuales.';
                        }
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error fetching monthly projections:', error);
                        this.errorMessage = 'Error al cargar las proyecciones mensuales.';
                        this.isLoading = false;
                    });
            },
            
            // Save actual appointments for a projection
            saveActualAppointments(projection) {
                if (!this.selectedEps || !this.selectedYear || !this.selectedMonth) {
                    this.errorMessage = 'Por favor seleccione EPS, año y mes.';
                    return;
                }
                
                this.isLoading = true;
                
                // Prepare data for sending
                const projectionData = {
                    id: projection.id,
                    eps_id: this.selectedEps,
                    year: this.selectedYear,
                    month: this.selectedMonth,
                    specialty_id: projection.specialty_id,
                    actual_appointments: projection.actual_appointments
                };
                
                fetch('../../api/appointments.php?action=saveActualAppointments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(projectionData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.successMessage = 'Atenciones actuales guardadas correctamente.';
                        // Clear error message
                        this.errorMessage = '';
                        // Reload monthly projections
                        this.loadMonthlyProjections();
                    } else {
                        this.errorMessage = data.message || 'Error al guardar las atenciones actuales.';
                    }
                    this.isLoading = false;
                })
                .catch(error => {
                    console.error('Error saving actual appointments:', error);
                    this.errorMessage = 'Error al guardar las atenciones actuales.';
                    this.isLoading = false;
                });
            },
            
            // Recalculate projections based on new population or contracted services
            recalculateProjections() {
                if (!this.selectedEps || !this.selectedYear) {
                    return;
                }
                
                this.isLoading = true;
                fetch(`../../api/appointments.php?action=recalculateProjections&eps_id=${this.selectedEps}&year=${this.selectedYear}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.successMessage = 'Proyecciones recalculadas correctamente.';
                        // Clear error message
                        this.errorMessage = '';
                        // Reload monthly projections
                        this.loadMonthlyProjections();
                    } else {
                        this.errorMessage = data.message || 'Error al recalcular las proyecciones.';
                    }
                    this.isLoading = false;
                })
                .catch(error => {
                    console.error('Error recalculating projections:', error);
                    this.errorMessage = 'Error al recalcular las proyecciones.';
                    this.isLoading = false;
                });
            },
            
            // Update charts with new data
            updateCharts() {
                // Check if we have the compliance chart
                if (this.charts.complianceChart) {
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
                    
                    // Update chart data
                    this.charts.complianceChart.data.labels = labels;
                    this.charts.complianceChart.data.datasets[0].data = projectedData;
                    this.charts.complianceChart.data.datasets[1].data = actualData;
                    this.charts.complianceChart.update();
                }
            },
            
            // Format number with thousands separator
            formatNumber(number) {
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            },
            
            // Get compliance status class for a projection
            getComplianceClass(projection) {
                if (!projection.projected_appointments) return 'bg-secondary';
                
                const percentage = (projection.actual_appointments / projection.projected_appointments) * 100;
                
                if (percentage < 80) return 'bg-danger';
                if (percentage >= 80 && percentage < 95) return 'bg-warning';
                return 'bg-success';
            },
            
            // Get compliance percentage for a projection
            getCompliancePercentage(projection) {
                if (!projection.projected_appointments) return 0;
                return Math.round((projection.actual_appointments / projection.projected_appointments) * 100);
            },
            
            // Handle selection changes
            handleSelectionChange() {
                // Reset messages
                this.errorMessage = '';
                this.successMessage = '';
                
                // Load data based on selection
                this.loadPopulationData();
                this.loadContractedServices();
                this.loadMonthlyProjections();
            },
            
            // Export data to Excel
            exportToExcel() {
                if (typeof exportTableToExcel === 'function') {
                    exportTableToExcel('projections-table', `Proyecciones_${this.selectedYear}_${this.monthName}_${new Date().toISOString().split('T')[0]}.xlsx`);
                } else {
                    this.errorMessage = 'La función de exportación a Excel no está disponible.';
                }
            },
            
            // Export data to PDF
            exportToPDF() {
                if (typeof exportTableToPDF === 'function') {
                    exportTableToPDF('projections-table', `Proyecciones_${this.selectedYear}_${this.monthName}_${new Date().toISOString().split('T')[0]}.pdf`);
                } else {
                    this.errorMessage = 'La función de exportación a PDF no está disponible.';
                }
            },
            
            // Clear messages
            clearMessages() {
                this.errorMessage = '';
                this.successMessage = '';
            }
        },
        mounted() {
            // Load initial data when component is mounted
            this.loadInitialData();
            
            // Initialize charts
            this.$nextTick(() => {
                if (document.getElementById('compliance-chart')) {
                    this.charts.complianceChart = initializeComplianceChart('compliance-chart');
                }
            });
        }
    });
}

/**
 * Initialize sidebar toggle behavior
 */
function initializeSidebar() {
    const toggleBtn = document.querySelector('.toggle-sidebar-btn');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
}

/**
 * Initialize tooltips and popovers
 */
function initializeTooltips() {
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialize Chart.js charts
 */
function initializeCharts() {
    // We'll initialize specific charts in the Vue app
}

/**
 * Toggle fullscreen for an element
 */
function toggleFullscreen(element) {
    if (!document.fullscreenElement) {
        element.requestFullscreen().catch(err => {
            console.error(`Error attempting to enable fullscreen: ${err.message}`);
        });
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}
