<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$fiscalYear = getCurrentFiscalYear();
$fiscalMonths = getFiscalMonths();
?>
<!-- Sidebar -->
<div class="col-md-3 col-lg-2">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-calendar-range me-2"></i> Año Fiscal <?php echo $fiscalYear; ?>-<?php echo $fiscalYear + 1; ?>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($fiscalMonths as $month): ?>
                <?php 
                $isCurrentMonth = (date('n') == $month);
                $yearForMonth = ($month == 1) ? $fiscalYear + 1 : $fiscalYear;
                $monthName = getMonthName($month);
                $active = (isset($_GET['month']) && $_GET['month'] == $month) ? 'active' : '';
                ?>
                <a href="?month=<?php echo $month; ?>&year=<?php echo $yearForMonth; ?>" 
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $active; ?> <?php echo $isCurrentMonth ? 'fw-bold' : ''; ?>">
                    <?php echo $monthName; ?> <?php echo $yearForMonth; ?>
                    <?php if ($isCurrentMonth): ?>
                        <span class="badge bg-primary rounded-pill">Actual</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-building me-2"></i> EPS Activas
        </div>
        <div class="list-group list-group-flush">
            <?php 
            $activeEps = getActiveEps();
            foreach ($activeEps as $eps): 
                $activeClass = (isset($_GET['eps_id']) && $_GET['eps_id'] == $eps['id']) ? 'active' : '';
            ?>
                <a href="?eps_id=<?php echo $eps['id']; ?>&month=<?php echo isset($_GET['month']) ? $_GET['month'] : date('n'); ?>" 
                   class="list-group-item list-group-item-action <?php echo $activeClass; ?>">
                    <?php echo htmlspecialchars($eps['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
