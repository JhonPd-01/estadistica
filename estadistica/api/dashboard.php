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
    case 'getStatistics':
        getStatistics($pdo);
        break;
    case 'getMonthlyCompliance':
        getMonthlyCompliance($pdo);
        break;
    case 'getSpecialtyDistribution':
        getSpecialtyDistribution($pdo);
        break;
    case 'getEpsCompliance':
        getEpsCompliance($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Get dashboard statistics
 * @param PDO $pdo Database connection
 */
function getStatistics($pdo) {
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
        
        // Count active EPS
        $sqlEps = "SELECT COUNT(*) as count FROM eps WHERE active = 1";
        $epsCount = $pdo->query($sqlEps)->fetch()['count'];
        
        // Get total population
        $sqlPopulation = "
            SELECT SUM(total_population) as total
            FROM population
            WHERE year_id = ?
        ";
        $paramsPopulation = [$yearId];
        
        if ($epsId > 0) {
            $sqlPopulation .= " AND eps_id = ?";
            $paramsPopulation[] = $epsId;
        }
        
        $stmtPopulation = $pdo->prepare($sqlPopulation);
        $stmtPopulation->execute($paramsPopulation);
        $totalPopulation = $stmtPopulation->fetch()['total'] ?? 0;
        
        // Get scheduled appointments
        $sqlAppointments = "
            SELECT SUM(projected_qty) as total
            FROM projected_appointments
            WHERE year_id = ?
        ";
        $paramsAppointments = [$yearId];
        
        if ($epsId > 0) {
            $sqlAppointments .= " AND eps_id = ?";
            $paramsAppointments[] = $epsId;
        }
        
        $stmtAppointments = $pdo->prepare($sqlAppointments);
        $stmtAppointments->execute($paramsAppointments);
        $scheduledAppointments = $stmtAppointments->fetch()['total'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'epsCount' => (int)$epsCount,
            'totalPopulation' => (int)$totalPopulation,
            'scheduledAppointments' => (int)$scheduledAppointments
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get monthly compliance data for chart
 * @param PDO $pdo Database connection
 */
function getMonthlyCompliance($pdo) {
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
        
        // Define month names
        $monthNames = [
            1 => 'Feb', 2 => 'Mar', 3 => 'Abr', 4 => 'May', 5 => 'Jun',
            6 => 'Jul', 7 => 'Ago', 8 => 'Sep', 9 => 'Oct', 10 => 'Nov',
            11 => 'Dic', 12 => 'Ene'
        ];
        
        // Get projected appointments by month
        $sqlProjected = "
            SELECT month, SUM(projected_qty) as total
            FROM projected_appointments
            WHERE year_id = ?
        ";
        $paramsProjected = [$yearId];
        
        if ($epsId > 0) {
            $sqlProjected .= " AND eps_id = ?";
            $paramsProjected[] = $epsId;
        }
        
        $sqlProjected .= " GROUP BY month ORDER BY month";
        
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute($paramsProjected);
        $projectedData = $stmtProjected->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get completed appointments by month
        $sqlCompleted = "
            SELECT month, SUM(quantity) as total
            FROM completed_appointments
            WHERE year_id = ?
        ";
        $paramsCompleted = [$yearId];
        
        if ($epsId > 0) {
            $sqlCompleted .= " AND eps_id = ?";
            $paramsCompleted[] = $epsId;
        }
        
        $sqlCompleted .= " GROUP BY month ORDER BY month";
        
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute($paramsCompleted);
        $completedData = $stmtCompleted->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Prepare chart data
        $labels = [];
        $projected = [];
        $completed = [];
        
        // Fill in data for all months
        for ($month = 1; $month <= 12; $month++) {
            $labels[] = $monthNames[$month];
            $projected[] = isset($projectedData[$month]) ? (int)$projectedData[$month] : 0;
            $completed[] = isset($completedData[$month]) ? (int)$completedData[$month] : 0;
        }
        
        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'projected' => $projected,
            'completed' => $completed
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get specialty distribution data for chart
 * @param PDO $pdo Database connection
 */
function getSpecialtyDistribution($pdo) {
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
        
        // Get specialty distribution
        $sql = "
            SELECT s.name, SUM(pa.projected_qty) as total
            FROM projected_appointments pa
            JOIN specialties s ON pa.specialty_id = s.id
            WHERE pa.year_id = ?
        ";
        $params = [$yearId];
        
        if ($epsId > 0) {
            $sql .= " AND pa.eps_id = ?";
            $params[] = $epsId;
        }
        
        $sql .= " GROUP BY pa.specialty_id ORDER BY total DESC LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        // Prepare chart data
        $labels = [];
        $values = [];
        
        foreach ($data as $row) {
            $labels[] = $row['name'];
            $values[] = (int)$row['total'];
        }
        
        echo json_encode([
            'success' => true,
            'labels' => $labels,
            'values' => $values
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get EPS compliance data for table
 * @param PDO $pdo Database connection
 */
function getEpsCompliance($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
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
        
        // Get projected appointments by EPS
        $sqlProjected = "
            SELECT pa.eps_id, e.name as eps_name, SUM(pa.projected_qty) as projected_qty
            FROM projected_appointments pa
            JOIN eps e ON pa.eps_id = e.id
            WHERE pa.year_id = ? AND pa.month = ?
            GROUP BY pa.eps_id
            ORDER BY e.name
        ";
        
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute([$yearId, $month]);
        $projectedData = $stmtProjected->fetchAll();
        
        // Get completed appointments by EPS
        $sqlCompleted = "
            SELECT ca.eps_id, SUM(ca.quantity) as completed_qty
            FROM completed_appointments ca
            WHERE ca.year_id = ? AND ca.month = ?
            GROUP BY ca.eps_id
        ";
        
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute([$yearId, $month]);
        $completedData = $stmtCompleted->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get compliance thresholds
        $thresholds = getComplianceThresholds($pdo);
        
        // Combine data and calculate compliance
        $compliance = [];
        
        foreach ($projectedData as $projected) {
            $epsId = $projected['eps_id'];
            $epsName = $projected['eps_name'];
            $projectedQty = (int)$projected['projected_qty'];
            
            // Get completed quantity
            $completedQty = isset($completedData[$epsId]) ? (int)$completedData[$epsId] : 0;
            
            // Calculate compliance
            $compliancePercentage = calculateCompliance($completedQty, $projectedQty);
            
            // Add to results
            $compliance[] = [
                'eps_id' => $epsId,
                'eps_name' => $epsName,
                'projected_qty' => $projectedQty,
                'completed_qty' => $completedQty,
                'compliance' => $compliancePercentage
            ];
        }
        
        echo json_encode([
            'success' => true,
            'compliance' => $compliance,
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
