/**
 * Quimiosalud - Sistema de Pronóstico de Atenciones y Laboratorios
 * Charts JavaScript Functions
 */

// Chart color palette
const chartColors = [
    '#0d6efd', '#6f42c1', '#d63384', '#dc3545', '#fd7e14', 
    '#ffc107', '#198754', '#20c997', '#0dcaf0', '#6c757d'
];

// Dashboard charts
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('complianceByEpsChart')) {
        initializeDashboardCharts();
    }
});

/**
 * Initialize all dashboard charts
 */
function initializeDashboardCharts() {
    // Load dashboard data
    loadDashboardData();
}

/**
 * Load dashboard data from API
 */
async function loadDashboardData() {
    try {
        const response = await fetch(`api/reports.php?action=dashboard_data&year=${currentYear}&month=${currentMonth}`);
        const data = await response.json();
        
        if (data.success) {
            updateDashboardStats(data.stats);
            createComplianceByEpsChart(data.compliance_by_eps);
            createMonthlyDistributionChart(data.monthly_distribution);
            createSpecialtiesChart(data.specialties_data);
            populateRecentActivity(data.recent_activity);
        } else {
            showError('Error loading dashboard data:', data.message);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

/**
 * Update dashboard statistics
 * @param {Object} stats - The statistics data
 */
function updateDashboardStats(stats) {
    // Update total population
    if (document.getElementById('totalPopulation')) {
        document.getElementById('totalPopulation').textContent = stats.total_population.toLocaleString();
    }
    
    // Update monthly appointments
    if (document.getElementById('monthlyAppointments')) {
        document.getElementById('monthlyAppointments').textContent = stats.monthly_appointments.toLocaleString();
    }
    
    // Update compliance rate
    if (document.getElementById('complianceRate')) {
        document.getElementById('complianceRate').textContent = `${stats.compliance_rate.toFixed(1)}%`;
        
        // Add appropriate color class
        const element = document.getElementById('complianceRate');
        element.classList.remove('text-danger', 'text-warning', 'text-success');
        
        if (stats.compliance_rate < 70) {
            element.classList.add('text-danger');
        } else if (stats.compliance_rate < 90) {
            element.classList.add('text-warning');
        } else {
            element.classList.add('text-success');
        }
    }
}

/**
 * Create compliance by EPS chart
 * @param {Array} data - The compliance data by EPS
 */
function createComplianceByEpsChart(data) {
    const ctx = document.getElementById('complianceByEpsChart');
    if (!ctx) return;
    
    // Extract labels and data
    const labels = data.map(item => item.name);
    const values = data.map(item => item.compliance);
    
    // Create color array based on compliance
    const backgroundColors = values.map(value => {
        if (value < 70) return '#dc3545'; // Danger (Red)
        if (value < 90) return '#ffc107'; // Warning (Yellow)
        return '#198754'; // Success (Green)
    });
    
    // Create the chart
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Porcentaje de Cumplimiento',
                data: values,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors,
                borderWidth: 1,
                borderRadius: 4,
                maxBarThickness: 50
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Porcentaje de Cumplimiento'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'EPS'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toFixed(1) + '%';
                        }
                    }
                }
            }
        }
    });
}

/**
 * Create monthly distribution chart
 * @param {Array} data - The monthly distribution data
 */
function createMonthlyDistributionChart(data) {
    const ctx = document.getElementById('monthlyDistributionChart');
    if (!ctx) return;
    
    // Extract labels and data
    const labels = data.map(item => item.month_name);
    const scheduled = data.map(item => item.scheduled);
    const completed = data.map(item => item.completed);
    
    // Create the chart
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Programadas',
                    data: scheduled,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Realizadas',
                    data: completed,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
            }
        }
    });
}

/**
 * Create specialties chart
 * @param {Array} data - The specialties data
 */
function createSpecialtiesChart(data) {
    const ctx = document.getElementById('specialtiesChart');
    if (!ctx) return;
    
    // Extract labels and data
    const labels = data.map(item => item.name);
    const values = data.map(item => item.appointments);
    
    // Create the chart
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: chartColors.slice(0, data.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const value = context.raw;
                            const percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

/**
 * Populate recent activity table
 * @param {Array} activities - The recent activities
 */
function populateRecentActivity(activities) {
    const tableBody = document.getElementById('recentActivityTable');
    if (!tableBody) return;
    
    if (activities.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center py-3">No hay actividad reciente para mostrar.</td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        html += `
            <tr>
                <td>${formatDate(activity.appointment_date)}</td>
                <td>${activity.eps_name}</td>
                <td>${activity.specialty_name}</td>
                <td class="text-center">${activity.quantity}</td>
            </tr>
        `;
    });
    
    tableBody.innerHTML = html;
}

/**
 * Create a chart for a report
 * @param {string} elementId - The ID of the canvas element
 * @param {string} type - The chart type (bar, line, pie, etc.)
 * @param {Object} data - The chart data
 * @param {Object} options - The chart options
 * @returns {Chart} The created Chart instance
 */
function createReportChart(elementId, type, data, options) {
    const ctx = document.getElementById(elementId);
    if (!ctx) return null;
    
    // Destroy existing chart if it exists
    if (ctx.chart) {
        ctx.chart.destroy();
    }
    
    // Create new chart
    const chart = new Chart(ctx, {
        type: type,
        data: data,
        options: options
    });
    
    // Store the chart instance on the canvas element
    ctx.chart = chart;
    
    return chart;
}

/**
 * Create a comparison chart for two datasets
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - The chart labels
 * @param {Array} dataset1 - The first dataset
 * @param {Array} dataset2 - The second dataset
 * @param {string} label1 - The label for dataset1
 * @param {string} label2 - The label for dataset2
 * @param {string} yAxisLabel - The label for the y-axis
 * @returns {Chart} The created Chart instance
 */
function createComparisonChart(elementId, labels, dataset1, dataset2, label1, label2, yAxisLabel) {
    return createReportChart(
        elementId,
        'bar',
        {
            labels: labels,
            datasets: [
                {
                    label: label1,
                    data: dataset1,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                },
                {
                    label: label2,
                    data: dataset2,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1
                }
            ]
        },
        {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: yAxisLabel
                    }
                }
            }
        }
    );
}

/**
 * Create a compliance chart with color-coded bars
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - The chart labels
 * @param {Array} values - The compliance values
 * @param {string} label - The dataset label
 * @returns {Chart} The created Chart instance
 */
function createComplianceChart(elementId, labels, values, label) {
    // Create color array based on compliance
    const backgroundColors = values.map(value => {
        if (value < 70) return '#dc3545'; // Danger (Red)
        if (value < 90) return '#ffc107'; // Warning (Yellow)
        return '#198754'; // Success (Green)
    });
    
    return createReportChart(
        elementId,
        'bar',
        {
            labels: labels,
            datasets: [{
                label: label,
                data: values,
                backgroundColor: backgroundColors,
                borderColor: backgroundColors,
                borderWidth: 1
            }]
        },
        {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Porcentaje'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.raw.toFixed(1) + '%';
                        }
                    }
                }
            }
        }
    );
}

/**
 * Create a pie or doughnut chart for distribution
 * @param {string} elementId - The ID of the canvas element
 * @param {Array} labels - The chart labels
 * @param {Array} values - The data values
 * @param {string} type - The chart type ('pie' or 'doughnut')
 * @returns {Chart} The created Chart instance
 */
function createDistributionChart(elementId, labels, values, type = 'pie') {
    // Get colors for the segments
    const backgroundColors = chartColors.slice(0, labels.length);
    
    return createReportChart(
        elementId,
        type,
        {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: backgroundColors,
                borderWidth: 1
            }]
        },
        {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const value = context.raw;
                            const percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    );
}
