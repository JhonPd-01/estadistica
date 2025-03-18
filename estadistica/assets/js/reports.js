/**
 * Reports JavaScript for Quimiosalud SAS
 */

// Vue application for reports
const reportsApp = Vue.createApp({
    data() {
        return {
            // Filters
            filters: {
                year_id: 0,
                eps_id: 0,
                month: new Date().getMonth() + 1, // Current month
                semester: 1 // First semester by default
            },
            // Report data
            monthlyReport: [],
            semesterReport: [],
            annualReport: [],
            epsReport: [],
            epsStats: {
                total_population: 0,
                total_projected: 0,
                total_completed: 0,
                overall_compliance: 0
            },
            // Lists
            epsList: [],
            specialties: [],
            // Month names
            months: [
                'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
                'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre', 'Enero'
            ],
            // Loading state
            loading: false,
            // Charts
            monthlyChart: null,
            semesterChart: null,
            annualChart: null,
            epsChart: null,
            // Compliance thresholds
            thresholds: {
                red: 70,
                yellow: 90
            }
        };
    },
    mounted() {
        // Get URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const yearId = urlParams.get('year_id') || 0;
        const epsId = urlParams.get('eps_id') || 0;
        const month = urlParams.get('month') || new Date().getMonth() + 1;
        
        // Set filters
        this.filters.year_id = yearId;
        this.filters.eps_id = epsId;
        this.filters.month = parseInt(month);
        
        // Load data
        this.loadEPS();
        this.loadSpecialties();
        this.loadComplianceThresholds();
        
        // Initialize report tabs event listeners
        this.initTabsEventListeners();
        
        // Load monthly report (default view)
        this.loadMonthlyReport();
    },
    methods: {
        /**
         * Initialize tabs event listeners
         */
        initTabsEventListeners() {
            const tabElements = document.querySelectorAll('button[data-bs-toggle="tab"]');
            
            tabElements.forEach(tabElement => {
                tabElement.addEventListener('shown.bs.tab', event => {
                    const targetId = event.target.getAttribute('data-bs-target');
                    
                    // Load report based on active tab
                    switch (targetId) {
                        case '#monthly':
                            this.loadMonthlyReport();
                            break;
                        case '#semester':
                            this.loadSemesterReport();
                            break;
                        case '#annual':
                            this.loadAnnualReport();
                            break;
                        case '#eps':
                            this.loadEpsReport();
                            break;
                    }
                });
            });
        },
        
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
                        console.error('Error loading EPS:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading EPS:', error);
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
                        console.error('Error loading specialties:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading specialties:', error);
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
         * Load monthly report
         */
        loadMonthlyReport() {
            this.loading = true;
            
            fetch(`api/reports.php?action=getMonthlyReport&year_id=${this.filters.year_id}&eps_id=${this.filters.eps_id}&month=${this.filters.month}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.monthlyReport = data.report;
                        this.renderMonthlyChart(data.chartData);
                    } else {
                        console.error('Error loading monthly report:', data.message);
                        this.monthlyReport = [];
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading monthly report:', error);
                    this.monthlyReport = [];
                });
        },
        
        /**
         * Load semester report
         */
        loadSemesterReport() {
            this.loading = true;
            
            fetch(`api/reports.php?action=getSemesterReport&year_id=${this.filters.year_id}&eps_id=${this.filters.eps_id}&semester=${this.filters.semester}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.semesterReport = data.report;
                        this.renderSemesterChart(data.chartData);
                    } else {
                        console.error('Error loading semester report:', data.message);
                        this.semesterReport = [];
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading semester report:', error);
                    this.semesterReport = [];
                });
        },
        
        /**
         * Load annual report
         */
        loadAnnualReport() {
            this.loading = true;
            
            fetch(`api/reports.php?action=getAnnualReport&year_id=${this.filters.year_id}&eps_id=${this.filters.eps_id}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.annualReport = data.report;
                        this.renderAnnualChart(data.chartData);
                    } else {
                        console.error('Error loading annual report:', data.message);
                        this.annualReport = [];
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading annual report:', error);
                    this.annualReport = [];
                });
        },
        
        /**
         * Load EPS report
         */
        loadEpsReport() {
            if (this.filters.eps_id <= 0) {
                this.epsReport = [];
                return;
            }
            
            this.loading = true;
            
            fetch(`api/reports.php?action=getEpsReport&year_id=${this.filters.year_id}&eps_id=${this.filters.eps_id}`)
                .then(response => response.json())
                .then(data => {
                    this.loading = false;
                    if (data.success) {
                        this.epsReport = data.report;
                        this.epsStats = data.stats;
                        this.renderEpsChart(data.chartData);
                    } else {
                        console.error('Error loading EPS report:', data.message);
                        this.epsReport = [];
                    }
                })
                .catch(error => {
                    this.loading = false;
                    console.error('Error loading EPS report:', error);
                    this.epsReport = [];
                });
        },
        
        /**
         * Render monthly chart
         * @param {Object} chartData - Chart data
         */
        renderMonthlyChart(chartData) {
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (this.monthlyChart) {
                this.monthlyChart.destroy();
            }
            
            // Create new chart
            this.monthlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Programadas',
                            data: chartData.projected,
                            backgroundColor: 'rgba(78, 115, 223, 0.4)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Realizadas',
                            data: chartData.completed,
                            backgroundColor: 'rgba(28, 200, 138, 0.4)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Atenciones'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Especialidades'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: `Atenciones para ${this.months[this.filters.month - 1]}`
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Render semester chart
         * @param {Object} chartData - Chart data
         */
        renderSemesterChart(chartData) {
            const ctx = document.getElementById('semesterChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (this.semesterChart) {
                this.semesterChart.destroy();
            }
            
            // Create new chart
            this.semesterChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Programadas',
                            data: chartData.projected,
                            backgroundColor: 'rgba(78, 115, 223, 0.4)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Realizadas',
                            data: chartData.completed,
                            backgroundColor: 'rgba(28, 200, 138, 0.4)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Atenciones'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Especialidades'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: `Atenciones para ${this.filters.semester === 1 ? 'Primer Semestre (Feb-Jul)' : 'Segundo Semestre (Ago-Ene)'}`
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Render annual chart
         * @param {Object} chartData - Chart data
         */
        renderAnnualChart(chartData) {
            const ctx = document.getElementById('annualChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (this.annualChart) {
                this.annualChart.destroy();
            }
            
            // Create new chart
            this.annualChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Programadas',
                            data: chartData.projected,
                            backgroundColor: 'rgba(78, 115, 223, 0.4)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Realizadas',
                            data: chartData.completed,
                            backgroundColor: 'rgba(28, 200, 138, 0.4)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Pendientes',
                            data: chartData.pending,
                            backgroundColor: 'rgba(246, 194, 62, 0.4)',
                            borderColor: 'rgba(246, 194, 62, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Atenciones'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Especialidades'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Atenciones Anuales'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Render EPS chart
         * @param {Object} chartData - Chart data
         */
        renderEpsChart(chartData) {
            const ctx = document.getElementById('epsChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (this.epsChart) {
                this.epsChart.destroy();
            }
            
            // Create new chart
            this.epsChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        data: chartData.values,
                        backgroundColor: chartData.labels.map((_, index) => getChartColor(index)),
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: true,
                            text: `Distribución de Atenciones - ${this.getEpsName(this.filters.eps_id)}`
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
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
         * Export monthly report to Excel
         */
        exportMonthlyToExcel() {
            exportTableToExcel('monthlyTable', `Reporte_Mensual_${this.months[this.filters.month - 1]}_Quimiosalud`);
        },
        
        /**
         * Export monthly report to PDF
         */
        exportMonthlyToPDF() {
            exportTableToPDF('monthlyTable', `Reporte_Mensual_${this.months[this.filters.month - 1]}_Quimiosalud`, 
                `Reporte Mensual - ${this.months[this.filters.month - 1]} - Quimiosalud SAS`);
        },
        
        /**
         * Export semester report to Excel
         */
        exportSemesterToExcel() {
            const semester = this.filters.semester === 1 ? 'Primer_Semestre' : 'Segundo_Semestre';
            exportTableToExcel('semesterTable', `Reporte_${semester}_Quimiosalud`);
        },
        
        /**
         * Export semester report to PDF
         */
        exportSemesterToPDF() {
            const semester = this.filters.semester === 1 ? 'Primer Semestre' : 'Segundo Semestre';
            exportTableToPDF('semesterTable', `Reporte_${semester}_Quimiosalud`, 
                `Reporte ${semester} - Quimiosalud SAS`);
        },
        
        /**
         * Export annual report to Excel
         */
        exportAnnualToExcel() {
            exportTableToExcel('annualTable', 'Reporte_Anual_Quimiosalud');
        },
        
        /**
         * Export annual report to PDF
         */
        exportAnnualToPDF() {
            exportTableToPDF('annualTable', 'Reporte_Anual_Quimiosalud', 'Reporte Anual - Quimiosalud SAS');
        },
        
        /**
         * Export EPS report to Excel
         */
        exportEpsReportToExcel() {
            const epsName = this.getEpsName(this.filters.eps_id).replace(/\s+/g, '_');
            exportTableToExcel('epsReportTable', `Reporte_EPS_${epsName}_Quimiosalud`);
        },
        
        /**
         * Export EPS report to PDF
         */
        exportEpsReportToPDF() {
            const epsName = this.getEpsName(this.filters.eps_id);
            exportTableToPDF('epsReportTable', `Reporte_EPS_${epsName}_Quimiosalud`, 
                `Reporte EPS ${epsName} - Quimiosalud SAS`);
        }
    }
});

// Mount the app when the DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('reportsApp')) {
        reportsApp.mount('#reportsApp');
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
