/**
 * Main JavaScript file for Quimiosalud SAS
 * Shared functionality and utilities
 */

// Set up Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

/**
 * Format date for display
 * @param {string} dateString - Date string from database
 * @returns {string} Formatted date (DD/MM/YYYY)
 */
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Show a Bootstrap toast notification
 * @param {string} elementId - ID of toast element
 * @param {string} message - Message to display
 */
function showToast(elementId, message) {
    const toastElement = document.getElementById(elementId);
    if (toastElement) {
        const toast = new bootstrap.Toast(toastElement);
        const messageElement = toastElement.querySelector('.toast-body');
        if (messageElement) {
            messageElement.textContent = message;
        }
        toast.show();
    }
}

/**
 * Export HTML table to Excel
 * @param {string} tableId - ID of the table to export
 * @param {string} filename - Filename for the Excel file
 */
function exportTableToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
    XLSX.writeFile(wb, filename + '.xlsx');
}

/**
 * Export HTML table to PDF
 * @param {string} tableId - ID of table to export
 * @param {string} filename - Filename for the PDF
 * @param {string} title - Title for the PDF
 */
function exportTableToPDF(tableId, filename, title) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'mm', 'a4');
    
    // Add title
    doc.setFontSize(18);
    doc.text(title, 14, 22);
    
    // Add date
    doc.setFontSize(11);
    doc.setTextColor(100);
    doc.text(`Generado: ${new Date().toLocaleDateString('es-ES')}`, 14, 30);
    
    // Auto-generate the table
    doc.autoTable({
        html: '#' + tableId,
        startY: 35,
        theme: 'grid',
        headStyles: {
            fillColor: [41, 128, 185],
            textColor: 255
        },
        footStyles: {
            fillColor: [41, 128, 185],
            textColor: 255
        },
        styles: {
            font: 'helvetica',
            fontStyle: 'normal',
            overflow: 'linebreak'
        },
        margin: { top: 35 },
    });
    
    // Save the PDF
    doc.save(filename + '.pdf');
}

/**
 * Generate a random color
 * @returns {string} Random hex color code
 */
function getRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}

/**
 * Get fixed colors for charts
 * @param {number} index - Index of the color
 * @returns {string} Hex color code
 */
function getChartColor(index) {
    const colors = [
        '#4e73df', // primary blue
        '#1cc88a', // success green
        '#36b9cc', // info cyan
        '#f6c23e', // warning yellow
        '#e74a3b', // danger red
        '#6f42c1', // purple
        '#fd7e14', // orange
        '#20c997', // teal
        '#6c757d', // gray
        '#17a2b8'  // info blue
    ];
    
    return colors[index % colors.length];
}

/**
 * Calculate total from an array of objects
 * @param {Array} items - Array of objects
 * @param {string} prop - Property name to sum
 * @returns {number} Total sum
 */
function calculateTotal(items, prop) {
    return items.reduce((total, item) => {
        return total + (parseFloat(item[prop]) || 0);
    }, 0);
}

/**
 * Filter an array by search term
 * @param {Array} array - Array to filter
 * @param {string} searchTerm - Search term
 * @param {Array} fields - Fields to search in
 * @returns {Array} Filtered array
 */
function filterArrayBySearchTerm(array, searchTerm, fields) {
    if (!searchTerm || searchTerm.trim() === '') {
        return array;
    }
    
    const term = searchTerm.toLowerCase().trim();
    
    return array.filter(item => {
        return fields.some(field => {
            const value = String(item[field] || '').toLowerCase();
            return value.includes(term);
        });
    });
}

/**
 * Get compliance status class based on percentage
 * @param {number} percentage - Compliance percentage
 * @param {number} redThreshold - Red threshold (%) 
 * @param {number} yellowThreshold - Yellow threshold (%)
 * @returns {string} Bootstrap color class
 */
function getComplianceStatusClass(percentage, redThreshold = 70, yellowThreshold = 90) {
    if (percentage < redThreshold) {
        return 'bg-danger';
    } else if (percentage < yellowThreshold) {
        return 'bg-warning';
    } else {
        return 'bg-success';
    }
}

/**
 * Get compliance status text based on percentage
 * @param {number} percentage - Compliance percentage
 * @param {number} redThreshold - Red threshold (%)
 * @param {number} yellowThreshold - Yellow threshold (%)
 * @returns {string} Status text
 */
function getComplianceStatusText(percentage, redThreshold = 70, yellowThreshold = 90) {
    if (percentage < redThreshold) {
        return 'Incumplimiento';
    } else if (percentage < yellowThreshold) {
        return 'Alerta';
    } else {
        return 'Cumplimiento';
    }
}

/**
 * Format number with thousand separators
 * @param {number} number - Number to format
 * @returns {string} Formatted number
 */
function formatNumber(number) {
    return new Intl.NumberFormat('es-ES').format(number);
}
