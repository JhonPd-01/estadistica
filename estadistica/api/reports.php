<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No ha iniciado sesión']);
    exit;
}

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'getMonthlyReport':
        getMonthlyReport($pdo);
        break;
    case 'getSemesterReport':
        getSemesterReport($pdo);
        break;
    case 'getAnnualReport':
        getAnnualReport($pdo);
        break;
    case 'getEpsReport':
        getEpsReport($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Get monthly report
 * @param PDO $pdo Database connection
 */
function getMonthlyReport($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        $month = isset($_GET['month']) ? (int)$_GET['month'] : getCurrentMonth();
        
        // If no year_id provided, get active year
        if ($yearId <= 0) {
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $yearId = $activeYear['id'];
            } else {
                echo json_encode(['success' => false, 'message' => 'No hay año activo']);
                return;
            }
        }
        
        // Build query for projected appointments
        $sqlProjected = "
            SELECT pa.eps_id, e.name as eps_name, pa.specialty_id, s.name as specialty_name, 
                   pa.projected_qty
            FROM projected_appointments pa
            JOIN eps e ON pa.eps_id = e.id
            JOIN specialties s ON pa.specialty_id = s.id
            WHERE pa.year_id = ? AND pa.month = ?
        ";
        $paramsProjected = [$yearId, $month];
        
        // Add EPS filter if provided
        if ($epsId > 0) {
            $sqlProjected .= " AND pa.eps_id = ?";
            $paramsProjected[] = $epsId;
        }
        
        $sqlProjected .= " ORDER BY e.name, s.name";
        
        // Execute projected appointments query
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute($paramsProjected);
        $projectedData = $stmtProjected->fetchAll();
        
        // Build query for completed appointments
        $sqlCompleted = "
            SELECT ca.eps_id, ca.specialty_id, SUM(ca.quantity) as completed_qty
            FROM completed_appointments ca
            WHERE ca.year_id = ? AND ca.month = ?
        ";
        $paramsCompleted = [$yearId, $month];
        
        // Add EPS filter if provided
        if ($epsId > 0) {
            $sqlCompleted .= " AND ca.eps_id = ?";
            $paramsCompleted[] = $epsId;
        }
        
        $sqlCompleted .= " GROUP BY ca.eps_id, ca.specialty_id";
        
        // Execute completed appointments query
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute($paramsCompleted);
        $completedData = $stmtCompleted->fetchAll();
        
        // Get compliance thresholds
        $thresholds = getComplianceThresholds($pdo);
        
        // Combine data and calculate compliance
        $report = [];
        $chartData = [
            'labels' => [],
            'projected' => [],
            'completed' => []
        ];
        
        // Create a map of specialty names for chart labels
        $specialtyMap = [];
        
        foreach ($projectedData as $projected) {
            $epsId = $projected['eps_id'];
            $epsName = $projected['eps_name'];
            $specialtyId = $projected['specialty_id'];
            $specialtyName = $projected['specialty_name'];
            $projectedQty = (int)$projected['projected_qty'];
            
            // Add to specialty map for chart
            if (!isset($specialtyMap[$specialtyId])) {
                $specialtyMap[$specialtyId] = $specialtyName;
            }
            
            // Find completed quantity
            $completedQty = 0;
            foreach ($completedData as $completed) {
                if ($completed['eps_id'] == $epsId && $completed['specialty_id'] == $specialtyId) {
                    $completedQty = (int)$completed['completed_qty'];
                    break;
                }
            }
            
            // Calculate pending and compliance
            $pendingQty = max(0, $projectedQty - $completedQty);
            $compliance = calculateCompliance($completedQty, $projectedQty);
            
            // Add to report
            $report[] = [
                'eps_id' => $epsId,
                'eps_name' => $epsName,
                'specialty_id' => $specialtyId,
                'specialty_name' => $specialtyName,
                'projected_qty' => $projectedQty,
                'completed_qty' => $completedQty,
                'pending_qty' => $pendingQty,
                'compliance' => $compliance
            ];
        }
        
        // Prepare chart data
        if (count($report) > 0) {
            // Group by specialty for chart
            $specialties = [];
            $projectedBySpecialty = [];
            $completedBySpecialty = [];
            
            foreach ($report as $item) {
                $specialtyId = $item['specialty_id'];
                $specialtyName = $item['specialty_name'];
                
                if (!in_array($specialtyName, $specialties)) {
                    $specialties[] = $specialtyName;
                    $projectedBySpecialty[$specialtyId] = 0;
                    $completedBySpecialty[$specialtyId] = 0;
                }
                
                $projectedBySpecialty[$specialtyId] += $item['projected_qty'];
                $completedBySpecialty[$specialtyId] += $item['completed_qty'];
            }
            
            // Sort specialties alphabetically
            sort($specialties);
            
            $chartData['labels'] = $specialties;
            
            // Fill data arrays in the same order as labels
            foreach ($specialties as $specialtyName) {
                $specialtyId = array_search($specialtyName, $specialtyMap);
                if ($specialtyId !== false) {
                    $chartData['projected'][] = $projectedBySpecialty[$specialtyId];
                    $chartData['completed'][] = $completedBySpecialty[$specialtyId];
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'report' => $report, 
            'chartData' => $chartData,
            'thresholds' => [
                'red' => $thresholds[0],
                'yellow' => $thresholds[1]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get semester report
 * @param PDO $pdo Database connection
 */
function getSemesterReport($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : 1;
        
        // If no year_id provided, get active year
        if ($yearId <= 0) {
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $yearId = $activeYear['id'];
            } else {
                echo json_encode(['success' => false, 'message' => 'No hay año activo']);
                return;
            }
        }
        
        // Define semester months
        $monthStart = $semester == 1 ? 1 : 7;  // 1 = Feb, 7 = Aug
        $monthEnd = $semester == 1 ? 6 : 12;   // 6 = Jul, 12 = Jan
        
        // Build query for projected appointments
        $sqlProjected = "
            SELECT pa.eps_id, e.name as eps_name, pa.specialty_id, s.name as specialty_name, 
                   SUM(pa.projected_qty) as projected_qty
            FROM projected_appointments pa
            JOIN eps e ON pa.eps_id = e.id
            JOIN specialties s ON pa.specialty_id = s.id
            WHERE pa.year_id = ? AND pa.month BETWEEN ? AND ?
        ";
        $paramsProjected = [$yearId, $monthStart, $monthEnd];
        
        // Add EPS filter if provided
        if ($epsId > 0) {
            $sqlProjected .= " AND pa.eps_id = ?";
            $paramsProjected[] = $epsId;
        }
        
        $sqlProjected .= " GROUP BY pa.eps_id, pa.specialty_id ORDER BY e.name, s.name";
        
        // Execute projected appointments query
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute($paramsProjected);
        $projectedData = $stmtProjected->fetchAll();
        
        // Build query for completed appointments
        $sqlCompleted = "
            SELECT ca.eps_id, ca.specialty_id, SUM(ca.quantity) as completed_qty
            FROM completed_appointments ca
            WHERE ca.year_id = ? AND ca.month BETWEEN ? AND ?
        ";
        $paramsCompleted = [$yearId, $monthStart, $monthEnd];
        
        // Add EPS filter if provided
        if ($epsId > 0) {
            $sqlCompleted .= " AND ca.eps_id = ?";
            $paramsCompleted[] = $epsId;
        }
        
        $sqlCompleted .= " GROUP BY ca.eps_id, ca.specialty_id";
        
        // Execute completed appointments query
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute($paramsCompleted);
        $completedData = $stmtCompleted->fetchAll();
        
        // Get compliance thresholds
        $thresholds = getComplianceThresholds($pdo);
        
        // Combine data and calculate compliance
        $report = [];
        $chartData = [
            'labels' => [],
            'projected' => [],
            'completed' => []
        ];
        
        // Create a map of specialty names for chart labels
        $specialtyMap = [];
        
        foreach ($projectedData as $projected) {
            $epsId = $projected['eps_id'];
            $epsName = $projected['eps_name'];
            $specialtyId = $projected['specialty_id'];
            $specialtyName = $projected['specialty_name'];
            $projectedQty = (int)$projected['projected_qty'];
            
            // Add to specialty map for chart
            if (!isset($specialtyMap[$specialtyId])) {
                $specialtyMap[$specialtyId] = $specialtyName;
            }
            
            // Find completed quantity
            $completedQty = 0;
            foreach ($completedData as $completed) {
                if ($completed['eps_id'] == $epsId && $completed['specialty_id'] == $specialtyId) {
                    $completedQty = (int)$completed['completed_qty'];
                    break;
                }
            }
            
            // Calculate pending and compliance
            $pendingQty = max(0, $projectedQty - $completedQty);
            $compliance = calculateCompliance($completedQty, $projectedQty);
            
            // Add to report
            $report[] = [
                'eps_id' => $epsId,
                'eps_name' => $epsName,
                'specialty_id' => $specialtyId,
                'specialty_name' => $specialtyName,
                'projected_qty' => $projectedQty,
                'completed_qty' => $completedQty,
                'pending_qty' => $pendingQty,
                'compliance' => $compliance
            ];
        }
        
        // Prepare chart data
        if (count($report) > 0) {
            // Group by specialty for chart
            $specialties = [];
            $projectedBySpecialty = [];
            $completedBySpecialty = [];
            
            foreach ($report as $item) {
                $specialtyId = $item['specialty_id'];
                $specialtyName = $item['specialty_name'];
                
                if (!in_array($specialtyName, $specialties)) {
                    $specialties[] = $specialtyName;
                    $projectedBySpecialty[$specialtyId] = 0;
                    $completedBySpecialty[$specialtyId] = 0;
                }
                
                $projectedBySpecialty[$specialtyId] += $item['projected_qty'];
                $completedBySpecialty[$specialtyId] += $item['completed_qty'];
            }
            
            // Sort specialties alphabetically
            sort($specialties);
            
            $chartData['labels'] = $specialties;
            
            // Fill data arrays in the same order as labels
            foreach ($specialties as $specialtyName) {
                $specialtyId = array_search($specialtyName, $specialtyMap);
                if ($specialtyId !== false) {
                    $chartData['projected'][] = $projectedBySpecialty[$specialtyId];
                    $chartData['completed'][] = $completedBySpecialty[$specialtyId];
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'report' => $report, 
            'chartData' => $chartData,
            'thresholds' => [
                'red' => $thresholds[0],
                'yellow' => $thresholds[1]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get annual report
 * @param PDO $pdo Database connection
 */
function getAnnualReport($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        
        // If no year_id provided, get active year
        if ($yearId <= 0) {
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $yearId = $activeYear['id'];
            } else {
                echo json_encode(['success' => false, 'message' => 'No hay año activo']);
                return;
            }
        }
        
        // Build query for projected appointments
        $sqlProjected = "
            SELECT pa.eps_id, e.name as eps_name, pa.specialty_id, s.name as specialty_name, 
                   SUM(pa.projected_qty) as projected_qty
            FROM projected_appointments pa
            JOIN eps e ON pa.eps_id = e.id
            JOIN specialties s ON pa.specialty_id = s.id
            WHERE pa.year_id = ?
        ";
        $paramsProjected = [$yearId];
        
        // Add EPS filter if provided
        if ($epsId > 0) {
            $sqlProjected .= " AND pa.eps_id = ?";
            $paramsProjected[] = $epsId;
        }
        
        $sqlProjected .= " GROUP BY pa.eps_id, pa.specialty_id ORDER BY e.name, s.name";
        
        // Execute projected appointments query
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute($paramsProjected);
        $projectedData = $stmtProjected->fetchAll();
        
        // Build query for completed appointments
        $sqlCompleted = "
            SELECT ca.eps_id, ca.specialty_id, SUM(ca.quantity) as completed_qty
            FROM completed_appointments ca
            WHERE ca.year_id = ?
        ";
        $paramsCompleted = [$yearId];
        
        // Add EPS filter if provided
        if ($epsId > 0) {
            $sqlCompleted .= " AND ca.eps_id = ?";
            $paramsCompleted[] = $epsId;
        }
        
        $sqlCompleted .= " GROUP BY ca.eps_id, ca.specialty_id";
        
        // Execute completed appointments query
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute($paramsCompleted);
        $completedData = $stmtCompleted->fetchAll();
        
        // Get compliance thresholds
        $thresholds = getComplianceThresholds($pdo);
        
        // Combine data and calculate compliance
        $report = [];
        $chartData = [
            'labels' => [],
            'projected' => [],
            'completed' => [],
            'pending' => []
        ];
        
        // Create a map of specialty names for chart labels
        $specialtyMap = [];
        
        foreach ($projectedData as $projected) {
            $epsId = $projected['eps_id'];
            $epsName = $projected['eps_name'];
            $specialtyId = $projected['specialty_id'];
            $specialtyName = $projected['specialty_name'];
            $projectedQty = (int)$projected['projected_qty'];
            
            // Add to specialty map for chart
            if (!isset($specialtyMap[$specialtyId])) {
                $specialtyMap[$specialtyId] = $specialtyName;
            }
            
            // Find completed quantity
            $completedQty = 0;
            foreach ($completedData as $completed) {
                if ($completed['eps_id'] == $epsId && $completed['specialty_id'] == $specialtyId) {
                    $completedQty = (int)$completed['completed_qty'];
                    break;
                }
            }
            
            // Calculate pending and compliance
            $pendingQty = max(0, $projectedQty - $completedQty);
            $compliance = calculateCompliance($completedQty, $projectedQty);
            
            // Add to report
            $report[] = [
                'eps_id' => $epsId,
                'eps_name' => $epsName,
                'specialty_id' => $specialtyId,
                'specialty_name' => $specialtyName,
                'projected_qty' => $projectedQty,
                'completed_qty' => $completedQty,
                'pending_qty' => $pendingQty,
                'compliance' => $compliance
            ];
        }
        
        // Prepare chart data
        if (count($report) > 0) {
            // Group by specialty for chart
            $specialties = [];
            $projectedBySpecialty = [];
            $completedBySpecialty = [];
            $pendingBySpecialty = [];
            
            foreach ($report as $item) {
                $specialtyId = $item['specialty_id'];
                $specialtyName = $item['specialty_name'];
                
                if (!in_array($specialtyName, $specialties)) {
                    $specialties[] = $specialtyName;
                    $projectedBySpecialty[$specialtyId] = 0;
                    $completedBySpecialty[$specialtyId] = 0;
                    $pendingBySpecialty[$specialtyId] = 0;
                }
                
                $projectedBySpecialty[$specialtyId] += $item['projected_qty'];
                $completedBySpecialty[$specialtyId] += $item['completed_qty'];
                $pendingBySpecialty[$specialtyId] += $item['pending_qty'];
            }
            
            // Sort specialties alphabetically
            sort($specialties);
            
            $chartData['labels'] = $specialties;
            
            // Fill data arrays in the same order as labels
            foreach ($specialties as $specialtyName) {
                $specialtyId = array_search($specialtyName, $specialtyMap);
                if ($specialtyId !== false) {
                    $chartData['projected'][] = $projectedBySpecialty[$specialtyId];
                    $chartData['completed'][] = $completedBySpecialty[$specialtyId];
                    $chartData['pending'][] = $pendingBySpecialty[$specialtyId];
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'report' => $report, 
            'chartData' => $chartData,
            'thresholds' => [
                'red' => $thresholds[0],
                'yellow' => $thresholds[1]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get EPS report
 * @param PDO $pdo Database connection
 */
function getEpsReport($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        
        if ($epsId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Falta el ID de la EPS']);
            return;
        }
        
        // If no year_id provided, get active year
        if ($yearId <= 0) {
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $yearId = $activeYear['id'];
            } else {
                echo json_encode(['success' => false, 'message' => 'No hay año activo']);
                return;
            }
        }
        
        // Get EPS name
        $stmt = $pdo->prepare("SELECT name FROM eps WHERE id = ?");
        $stmt->execute([$epsId]);
        $eps = $stmt->fetch();
        
        if (!$eps) {
            echo json_encode(['success' => false, 'message' => 'La EPS no existe']);
            return;
        }
        
        $epsName = $eps['name'];
        
        // Build query for projected appointments
        $sqlProjected = "
            SELECT pa.specialty_id, s.name as specialty_name, 
                   SUM(pa.projected_qty) as projected_qty
            FROM projected_appointments pa
            JOIN specialties s ON pa.specialty_id = s.id
            WHERE pa.year_id = ? AND pa.eps_id = ?
            GROUP BY pa.specialty_id
            ORDER BY s.name
        ";
        
        // Execute projected appointments query
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute([$yearId, $epsId]);
        $projectedData = $stmtProjected->fetchAll();
        
        // Build query for completed appointments
        $sqlCompleted = "
            SELECT ca.specialty_id, SUM(ca.quantity) as completed_qty
            FROM completed_appointments ca
            WHERE ca.year_id = ? AND ca.eps_id = ?
            GROUP BY ca.specialty_id
        ";
        
        // Execute completed appointments query
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute([$yearId, $epsId]);
        $completedData = $stmtCompleted->fetchAll();
        
        // Get population stats
        $sqlPopulation = "
            SELECT SUM(total_population) as total_population,
                   SUM(active_population) as active_population
            FROM population
            WHERE year_id = ? AND eps_id = ?
        ";
        
        // Execute population query
        $stmtPopulation = $pdo->prepare($sqlPopulation);
        $stmtPopulation->execute([$yearId, $epsId]);
        $population = $stmtPopulation->fetch();
        
        // Get compliance thresholds
        $thresholds = getComplianceThresholds($pdo);
        
        // Combine data and calculate compliance
        $report = [];
        $chartData = [
            'labels' => [],
            'values' => []
        ];
        
        $totalProjected = 0;
        $totalCompleted = 0;
        
        foreach ($projectedData as $projected) {
            $specialtyId = $projected['specialty_id'];
            $specialtyName = $projected['specialty_name'];
            $projectedQty = (int)$projected['projected_qty'];
            
            // Add to chart data
            $chartData['labels'][] = $specialtyName;
            $chartData['values'][] = $projectedQty;
            
            $totalProjected += $projectedQty;
            
            // Find completed quantity
            $completedQty = 0;
            foreach ($completedData as $completed) {
                if ($completed['specialty_id'] == $specialtyId) {
                    $completedQty = (int)$completed['completed_qty'];
                    break;
                }
            }
            
            $totalCompleted += $completedQty;
            
            // Calculate pending and compliance
            $pendingQty = max(0, $projectedQty - $completedQty);
            $compliance = calculateCompliance($completedQty, $projectedQty);
            
            // Add to report
            $report[] = [
                'specialty_id' => $specialtyId,
                'specialty_name' => $specialtyName,
                'projected_qty' => $projectedQty,
                'completed_qty' => $completedQty,
                'pending_qty' => $pendingQty,
                'compliance' => $compliance
            ];
        }
        
        // Calculate overall compliance
        $overallCompliance = calculateCompliance($totalCompleted, $totalProjected);
        
        // Prepare stats
        $stats = [
            'total_population' => (int)($population['total_population'] ?? 0),
            'active_population' => (int)($population['active_population'] ?? 0),
            'total_projected' => $totalProjected,
            'total_completed' => $totalCompleted,
            'overall_compliance' => $overallCompliance
        ];
        
        echo json_encode([
            'success' => true, 
            'report' => $report,
            'eps_name' => $epsName,
            'stats' => $stats,
            'chartData' => $chartData,
            'thresholds' => [
                'red' => $thresholds[0],
                'yellow' => $thresholds[1]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get current month (1-12)
 * @return int Current month number
 */
function getCurrentMonth() {
    $month = (int)date('n'); // 1-12
    
    // Convert to management month (1 = February, 12 = January)
    return $month == 1 ? 12 : $month - 1;
}
