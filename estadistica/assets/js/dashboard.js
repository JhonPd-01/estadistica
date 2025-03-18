/**
 * Dashboard JavaScript for Quimiosalud SAS
 * Contains functionality for dashboard charts and statistics
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts and statistics on dashboard
    loadDashboardData();
});

/**
 * Load all dashboard data
 */
function loadDashboardData() {
    // Get current year ID from the URL or use the default
    const params = new URLSearchParams(window.location.search);
    const yearId = params.get('year_id') || 0;
    const epsId = params.get('eps_id') || 0;
    
    // Load statistics
    loadDashboardStatistics(yearId, epsId);
    
    // Load monthly chart
    loadMonthlyComplianceChart(yearId, epsId);
    
    // Load specialty distribution chart
    loadSpecialtyDistributionChart(yearId, epsId);
    
    // Load EPS compliance table
    loadEpsComplianceTable(yearId);
}

/**
 * Load dashboard statistics
 * @param {number} yearId - Year ID
 * @param {number} epsId - EPS ID
 */
function loadDashboardStatistics(yearId, epsId) {
    // Fetch dashboard statistics from API
    fetch(`api/dashboard.php?action=getStatistics&year_id=${yearId}&eps_id=${epsId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update statistics on the dashboard
                document.getElementById('epsCount').textContent = formatNumber(data.epsCount);
                document.getElementById('totalPopulation').textContent = formatNumber(data.totalPopulation);
                document.getElementById('scheduledAppointments').textContent = formatNumber(data.scheduledAppointments);
            } else {
                console.error('Error loading dashboard statistics:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading dashboard statistics:', error);
        });
}

/**
 * Load monthly compliance chart
 * @param {number} yearId - Year ID
 * @param {number} epsId - EPS ID
 */
function loadMonthlyComplianceChart(yearId, epsId) {
    // Fetch monthly compliance data
    fetch(`api/dashboard.php?action=getMonthlyCompliance&year_id=${yearId}&eps_id=${epsId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create chart
                const ctx = document.getElementById('appointmentsChart').getContext('2d');
                
                // Check if chart already exists
                if (window.monthlyChart) {
                    window.monthlyChart.destroy();
                }
                
                window.monthlyChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Programado',
                                data: data.projected,
                                backgroundColor: 'rgba(78, 115, 223, 0.4)',
                                borderColor: 'rgba(78, 115, 223, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Ejecutado',
                                data: data.completed,
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
                                    text: 'Mes'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            title: {
                                display: true,
                                text: 'Atenciones por Mes'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += formatNumber(context.parsed.y);
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                console.error('Error loading monthly compliance data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading monthly compliance chart:', error);
        });
}

/**
 * Load specialty distribution chart
 * @param {number} yearId - Year ID
 * @param {number} epsId - EPS ID
 */
function loadSpecialtyDistributionChart(yearId, epsId) {
    // Fetch specialty distribution data
    fetch(`api/dashboard.php?action=getSpecialtyDistribution&year_id=${yearId}&eps_id=${epsId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create chart
                const ctx = document.getElementById('specialtyChart').getContext('2d');
                
                // Check if chart already exists
                if (window.specialtyChart) {
                    window.specialtyChart.destroy();
                }
                
                // Generate colors
                const backgroundColors = data.labels.map((_, index) => getChartColor(index));
                
                window.specialtyChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: backgroundColors,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12
                                }
                            },
                            title: {
                                display: true,
                                text: 'Distribución por Especialidad'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${formatNumber(value)} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            } else {
                console.error('Error loading specialty distribution data:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading specialty chart:', error);
        });
}

/**
 * Load EPS compliance table
 * @param {number} yearId - Year ID
 */
function loadEpsComplianceTable(yearId) {
    // Get current month
    const date = new Date();
    const month = date.getMonth() + 1; // JavaScript months are 0-11, we need 1-12
    
    // Calculate the management month (1=February, 12=January)
    // If current month is January (1), then management month is 12
    // For all other months, management month is current month - 1
    const managementMonth = (month === 1) ? 12 : month - 1;
    
    // Set current month label
    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    document.getElementById('currentMonthLabel').textContent = monthNames[month - 1];
    
    // Fetch EPS compliance data
    fetch(`api/dashboard.php?action=getEpsCompliance&year_id=${yearId}&month=${managementMonth}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tableBody = document.getElementById('epsComplianceTable');
                
                // Clear table
                tableBody.innerHTML = '';
                
                if (data.compliance.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center">No hay datos disponibles</td></tr>';
                    return;
                }
                
                // Add rows
                data.compliance.forEach(eps => {
                    const row = document.createElement('tr');
                    
                    // Get compliance status
                    const complianceClass = getComplianceStatusClass(eps.compliance, data.thresholds.red, data.thresholds.yellow);
                    const complianceText = getComplianceStatusText(eps.compliance, data.thresholds.red, data.thresholds.yellow);
                    
                    row.innerHTML = `
                        <td>${eps.eps_name}</td>
                        <td>${formatNumber(eps.projected_qty)}</td>
                        <td>${formatNumber(eps.completed_qty)}</td>
                        <td>${eps.compliance}%</td>
                        <td><span class="badge rounded-pill ${complianceClass}">${complianceText}</span></td>
                    `;
                    
                    tableBody.appendChild(row);
                });
            } else {
                console.error('Error loading EPS compliance data:', data.message);
                document.getElementById('epsComplianceTable').innerHTML = 
                    '<tr><td colspan="5" class="text-center">Error al cargar los datos</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading EPS compliance table:', error);
            document.getElementById('epsComplianceTable').innerHTML = 
                '<tr><td colspan="5" class="text-center">Error al cargar los datos</td></tr>';
        });
}

// Event listener for sidebar filter form
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get selected values
            const yearId = document.getElementById('yearSelect').value;
            const epsId = document.getElementById('epsSelect').value;
            
            // Update URL with parameters
            const url = new URL(window.location.href);
            url.searchParams.set('year_id', yearId);
            url.searchParams.set('eps_id', epsId);
            window.history.pushState({}, '', url);
            
            // Reload dashboard data
            loadDashboardData();
        });
    }
});
