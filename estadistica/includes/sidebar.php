<div class="sidebar bg-light p-3 border rounded">
    <h5><i class="fas fa-cog"></i> Filtros</h5>
    <hr>
    
    <?php
    // Get all years
    $years = getAllYears($pdo);
    
    // Get active year
    $activeYear = getActiveYear($pdo);
    $activeYearId = $activeYear ? $activeYear['id'] : 0;
    
    // Get all EPS
    $epsList = getAllEPSLegacy($pdo);
    ?>
    
    <form id="filterForm">
        <div class="mb-3">
            <label for="yearSelect" class="form-label">Año de gestión:</label>
            <select class="form-select" id="yearSelect" name="year_id">
                <?php foreach ($years as $year): ?>
                <option value="<?php echo $year['id']; ?>" <?php echo ($year['id'] == $activeYearId) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($year['year_label']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label for="epsSelect" class="form-label">EPS:</label>
            <select class="form-select" id="epsSelect" name="eps_id">
                <option value="0">Todas las EPS</option>
                <?php foreach ($epsList as $eps): ?>
                <option value="<?php echo $eps['id']; ?>">
                    <?php echo htmlspecialchars($eps['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if (isset($showMonthFilter) && $showMonthFilter): ?>
        <div class="mb-3">
            <label for="monthSelect" class="form-label">Mes:</label>
            <select class="form-select" id="monthSelect" name="month">
                <option value="0">Todos los meses</option>
                <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?php echo $i; ?>"><?php echo getMonthName($i); ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-filter me-1"></i> Filtrar
        </button>
    </form>
</div>
