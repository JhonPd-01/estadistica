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
    case 'getPopulations':
        getPopulations($pdo);
        break;
    case 'savePopulation':
        savePopulation($pdo);
        break;
    case 'calculateProjections':
        calculateProjections($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Get populations data
 * @param PDO $pdo Database connection
 */
function getPopulations($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
        
        // Build query
        $sql = "SELECT * FROM population WHERE 1=1";
        $params = [];
        
        // Add filters
        if ($yearId > 0) {
            $sql .= " AND year_id = ?";
            $params[] = $yearId;
        } else {
            // If no year_id provided, get active year
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $sql .= " AND year_id = ?";
                $params[] = $activeYear['id'];
            }
        }
        
        if ($epsId > 0) {
            $sql .= " AND eps_id = ?";
            $params[] = $epsId;
        }
        
        if ($month > 0) {
            $sql .= " AND month = ?";
            $params[] = $month;
        }
        
        $sql .= " ORDER BY eps_id, month";
        
        // Execute query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $populations = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'populations' => $populations]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save population data
 * @param PDO $pdo Database connection
 */
function savePopulation($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['eps_id']) || empty($data['year_id']) || empty($data['month'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
            return;
        }
        
        // Sanitize inputs
        $epsId = (int)$data['eps_id'];
        $yearId = (int)$data['year_id'];
        $month = (int)$data['month'];
        $totalPopulation = (int)$data['total_population'];
        $activePopulation = (int)$data['active_population'];
        $fertileWomen = (int)$data['fertile_women'];
        $pregnantWomen = (int)$data['pregnant_women'];
        $adults = (int)$data['adults'];
        $pediatricDiagnosed = (int)$data['pediatric_diagnosed'];
        $minorsFollowUp = (int)$data['minors_follow_up'];
        
        // Check if the population already exists
        $stmt = $pdo->prepare("
            SELECT id FROM population 
            WHERE eps_id = ? AND year_id = ? AND month = ?
        ");
        $stmt->execute([$epsId, $yearId, $month]);
        $existing = $stmt->fetch();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        if ($existing) {
            // Update existing population
            $stmt = $pdo->prepare("
                UPDATE population SET
                total_population = ?,
                active_population = ?,
                fertile_women = ?,
                pregnant_women = ?,
                adults = ?,
                pediatric_diagnosed = ?,
                minors_follow_up = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE eps_id = ? AND year_id = ? AND month = ?
            ");
            $stmt->execute([
                $totalPopulation,
                $activePopulation,
                $fertileWomen,
                $pregnantWomen,
                $adults,
                $pediatricDiagnosed,
                $minorsFollowUp,
                $epsId,
                $yearId,
                $month
            ]);
            
            $message = 'Población actualizada correctamente';
        } else {
            // Insert new population
            $stmt = $pdo->prepare("
                INSERT INTO population 
                (eps_id, year_id, month, total_population, active_population, 
                fertile_women, pregnant_women, adults, pediatric_diagnosed, minors_follow_up) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $epsId,
                $yearId,
                $month,
                $totalPopulation,
                $activePopulation,
                $fertileWomen,
                $pregnantWomen,
                $adults,
                $pediatricDiagnosed,
                $minorsFollowUp
            ]);
            
            $message = 'Población registrada correctamente';
        }
        
        // Calculate projected appointments for this population
        calculateProjectedAppointments($pdo, $epsId, $yearId, $month);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => $message]);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Calculate projections for a population
 * @param PDO $pdo Database connection
 */
function calculateProjections($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['eps_id']) || empty($data['year_id']) || empty($data['month'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
            return;
        }
        
        // Sanitize inputs
        $epsId = (int)$data['eps_id'];
        $yearId = (int)$data['year_id'];
        $month = (int)$data['month'];
        
        // Calculate projected appointments
        $result = calculateProjectedAppointments($pdo, $epsId, $yearId, $month);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Proyecciones calculadas correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al calcular las proyecciones']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
