    </div> <!-- End of main content container -->

    <!-- Footer -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between">
                <span class="text-muted">&copy; <?php echo date('Y'); ?> Quimiosalud SAS</span>
                <span class="text-muted">Sistema de Pronóstico de Atenciones v<?php echo APP_VERSION; ?></span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Main JS file -->
    <script src="assets/js/main.js"></script>
    
    <?php if (isset($includeCharts) && $includeCharts): ?>
    <!-- Chart.js scripts -->
    <script src="assets/js/charts.js"></script>
    <?php endif; ?>
    
    <?php if (basename($_SERVER['PHP_SELF']) == 'population.php'): ?>
    <!-- Population JS -->
    <script src="assets/js/population.js"></script>
    <?php endif; ?>
    
    <?php if (basename($_SERVER['PHP_SELF']) == 'appointments.php'): ?>
    <!-- Appointments JS -->
    <script src="assets/js/appointments.js"></script>
    <?php endif; ?>
    
    <?php if (basename($_SERVER['PHP_SELF']) == 'reports.php'): ?>
    <!-- Reports JS -->
    <script src="assets/js/reports.js"></script>
    <?php endif; ?>
</body>
</html>
