/**
 * Configuration for Chart.js charts
 */

/**
 * Initialize compliance chart comparing projected vs actual appointments
 * @param {string} chartId - ID of canvas element for the chart
 * @returns {Chart} - The created Chart.js instance
 */
function initializeComplianceChart(chartId) {
    const ctx = document.getElementById(chartId).getContext('2d');
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [], // Will be populated with specialty names
            datasets: [
                {
                    label: 'Atenciones Proyectadas',
                    data: [], // Will be populated with projected appointment counts
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgb(54, 162, 235)',
                    borderWidth: 1
                },
                {
                    label: 'Atenciones Realizadas',
                    data: [], // Will be populated with actual appointment counts
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgb(75, 192, 192)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Cumplimiento de Atenciones por Especialidad',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.parsed.y || 0;
                            
                            value = value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            return `${label}: ${value}`;
                        },
                        footer: function(tooltipItems) {
                            // Get the projected and actual values for this item
                            const projected = tooltipItems[0].parsed.y;
                            const actual = tooltipItems.length > 1 ? tooltipItems[1].parsed.y : 0;
                            
                            // Calculate percentage
                            const percentage = projected ? Math.round((actual / projected) * 100) : 0;
                            
                            return `Cumplimiento: ${percentage}%`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Initialize specialty distribution chart
 * @param {string} chartId - ID of canvas element for the chart
 * @param {Array} data - Array of data objects with name and value properties
 * @returns {Chart} - The created Chart.js instance
 */
function initializeDistributionChart(chartId, data) {
    const ctx = document.getElementById(chartId).getContext('2d');
    
    // Extract labels and values from data
    const labels = data.map(item => item.name);
    const values = data.map(item => item.value);
    
    // Generate colors for each segment
    const colors = generateColors(data.length);
    
    return new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Distribución de Atenciones por Especialidad',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.parsed || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            
                            return `${label}: ${value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Initialize monthly trend chart
 * @param {string} chartId - ID of canvas element for the chart
 * @param {Array} months - Array of month names
 * @param {Array} projected - Array of projected values for each month
 * @param {Array} actual - Array of actual values for each month
 * @returns {Chart} - The created Chart.js instance
 */
function initializeMonthlyTrendChart(chartId, months, projected, actual) {
    const ctx = document.getElementById(chartId).getContext('2d');
    
    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Proyectado',
                    data: projected,
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                },
                {
                    label: 'Real',
                    data: actual,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                        }
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Tendencia Mensual de Atenciones',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.parsed.y || 0;
                            
                            value = value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                            return `${label}: ${value}`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Generate an array of distinct colors for charts
 * @param {number} count - Number of colors to generate
 * @returns {Array} - Array of color strings
 */
function generateColors(count) {
    const baseColors = [
        'rgb(54, 162, 235)',    // Blue
        'rgb(75, 192, 192)',    // Green
        'rgb(255, 99, 132)',    // Red
        'rgb(255, 159, 64)',    // Orange
        'rgb(153, 102, 255)',   // Purple
        'rgb(255, 205, 86)',    // Yellow
        'rgb(201, 203, 207)',   // Grey
        'rgb(54, 97, 214)',     // Dark blue
        'rgb(44, 160, 44)',     // Dark green
        'rgb(214, 39, 40)',     // Dark red
        'rgb(255, 127, 14)',    // Dark orange
        'rgb(148, 103, 189)',   // Dark purple
        'rgb(188, 189, 34)',    // Olive
        'rgb(23, 190, 207)'     // Teal
    ];
    
    // If we need more colors than available in baseColors, generate them
    const colors = [...baseColors];
    
    if (count > baseColors.length) {
        for (let i = baseColors.length; i < count; i++) {
            // Generate random color
            const r = Math.floor(Math.random() * 255);
            const g = Math.floor(Math.random() * 255);
            const b = Math.floor(Math.random() * 255);
            colors.push(`rgb(${r}, ${g}, ${b})`);
        }
    }
    
    return colors.slice(0, count);
}
