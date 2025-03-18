/**
 * Functions for exporting data to Excel and PDF
 */

/**
 * Export HTML table to Excel
 * @param {string} tableId - ID of the HTML table to export
 * @param {string} filename - Name of the exported file
 */
function exportTableToExcel(tableId, filename = 'table.xlsx') {
    // Get the table element
    const table = document.getElementById(tableId);
    if (!table) {
        console.error(`Table with id ${tableId} not found`);
        return;
    }
    
    // Create a workbook and worksheet
    const wb = XLSX.utils.book_new();
    
    // Clone the table to manipulate it without affecting the original
    const tableClone = table.cloneNode(true);
    
    // Remove any action buttons or columns that shouldn't be exported
    const actionCells = tableClone.querySelectorAll('.no-export');
    actionCells.forEach(cell => {
        cell.parentNode.removeChild(cell);
    });
    
    // Convert table to worksheet
    const ws = XLSX.utils.table_to_sheet(tableClone);
    
    // Add the worksheet to the workbook
    XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
    
    // Write the workbook and trigger a download
    XLSX.writeFile(wb, filename);
}

/**
 * Export HTML table to PDF
 * @param {string} tableId - ID of the HTML table to export
 * @param {string} filename - Name of the exported file
 */
function exportTableToPDF(tableId, filename = 'table.pdf') {
    // Get the table element
    const table = document.getElementById(tableId);
    if (!table) {
        console.error(`Table with id ${tableId} not found`);
        return;
    }
    
    // Clone the table to manipulate it without affecting the original
    const tableClone = table.cloneNode(true);
    
    // Remove any action buttons or columns that shouldn't be exported
    const actionCells = tableClone.querySelectorAll('.no-export');
    actionCells.forEach(cell => {
        cell.parentNode.removeChild(cell);
    });
    
    // Create a new jsPDF instance
    const doc = new jspdf.jsPDF('l', 'pt', 'a4');
    
    // Get current date for the footer
    const currentDate = new Date().toLocaleDateString('es-CO');
    
    // Add a title to the PDF
    doc.setFontSize(18);
    doc.text('Quimiosalud SAS - Sistema de Pronóstico', 40, 40);
    
    // Add subtitle with current date
    doc.setFontSize(12);
    doc.text(`Reporte generado el ${currentDate}`, 40, 60);
    
    // Convert the table to a PDF table
    doc.autoTable({
        html: tableClone,
        startY: 70,
        styles: {
            fontSize: 10,
            cellPadding: 3,
            overflow: 'linebreak',
            halign: 'center'
        },
        headStyles: {
            fillColor: [13, 110, 253],
            textColor: 255,
            fontStyle: 'bold',
            halign: 'center'
        },
        columnStyles: {
            0: { halign: 'left' } // Align first column to the left
        },
        margin: { top: 70 },
        didDrawPage: function (data) {
            // Add page number at the bottom
            doc.setFontSize(10);
            doc.text(`Página ${doc.internal.getNumberOfPages()}`, data.settings.margin.left, doc.internal.pageSize.height - 10);
            
            // Add footer with company name
            doc.text('Quimiosalud SAS', doc.internal.pageSize.width / 2, doc.internal.pageSize.height - 10, { align: 'center' });
        }
    });
    
    // Save the PDF
    doc.save(filename);
}

/**
 * Export chart to PNG image
 * @param {Chart} chart - Chart.js instance to export
 * @param {string} filename - Name of the exported file
 */
function exportChartToPNG(chart, filename = 'chart.png') {
    // Get chart canvas
    const canvas = chart.canvas;
    
    // Create an image
    const image = canvas.toDataURL('image/png');
    
    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = image;
    link.download = filename;
    link.click();
}

/**
 * Export all visible charts on the page
 */
function exportAllCharts() {
    // Get all chart canvases
    const chartCanvases = document.querySelectorAll('.chart-container canvas');
    
    chartCanvases.forEach((canvas, index) => {
        // Find the Chart.js instance associated with this canvas
        const chartInstance = Chart.getChart(canvas);
        if (chartInstance) {
            // Generate filename based on chart title or index
            let title = 'chart';
            if (chartInstance.options && chartInstance.options.plugins && chartInstance.options.plugins.title) {
                title = chartInstance.options.plugins.title.text || `chart_${index + 1}`;
                // Sanitize the title for use as filename
                title = title.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            }
            
            // Export the chart
            exportChartToPNG(chartInstance, `${title}_${new Date().toISOString().split('T')[0]}.png`);
        }
    });
}
