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
    case 'getSpecialties':
        getSpecialties($pdo);
        break;
    case 'getAppointmentSummaries':
        getAppointmentSummaries($pdo);
        break;
    case 'getAppointments':
        getAppointments($pdo);
        break;
    case 'saveAppointment':
        saveAppointment($pdo);
        break;
    case 'deleteAppointment':
        deleteAppointment($pdo);
        break;
    case 'recalculateProjections':
        recalculateProjections($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Get all specialties
 * @param PDO $pdo Database connection
 */
function getSpecialties($pdo) {
    try {
        $specialties = getAllSpecialties($pdo);
        echo json_encode(['success' => true, 'specialties' => $specialties]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get appointment summaries
 * @param PDO $pdo Database connection
 */
function getAppointmentSummaries($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
        
        // Build query for projected appointments
        $sqlProjected = "
            SELECT pa.eps_id, pa.specialty_id, SUM(pa.projected_qty) as projected_qty
            FROM projected_appointments pa
            WHERE 1=1
        ";
        $paramsProjected = [];
        
        // Add filters
        if ($yearId > 0) {
            $sqlProjected .= " AND pa.year_id = ?";
            $paramsProjected[] = $yearId;
        } else {
            // If no year_id provided, get active year
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $sqlProjected .= " AND pa.year_id = ?";
                $paramsProjected[] = $activeYear['id'];
                $yearId = $activeYear['id']; // Set yearId for completed appointments query
            }
        }
        
        if ($epsId > 0) {
            $sqlProjected .= " AND pa.eps_id = ?";
            $paramsProjected[] = $epsId;
        }
        
        if ($month > 0) {
            $sqlProjected .= " AND pa.month = ?";
            $paramsProjected[] = $month;
        }
        
        $sqlProjected .= " GROUP BY pa.eps_id, pa.specialty_id";
        
        // Execute projected appointments query
        $stmtProjected = $pdo->prepare($sqlProjected);
        $stmtProjected->execute($paramsProjected);
        $projectedData = $stmtProjected->fetchAll();
        
        // Build query for completed appointments
        $sqlCompleted = "
            SELECT ca.eps_id, ca.specialty_id, SUM(ca.quantity) as completed_qty
            FROM completed_appointments ca
            WHERE 1=1
        ";
        $paramsCompleted = [];
        
        // Add filters
        if ($yearId > 0) {
            $sqlCompleted .= " AND ca.year_id = ?";
            $paramsCompleted[] = $yearId;
        }
        
        if ($epsId > 0) {
            $sqlCompleted .= " AND ca.eps_id = ?";
            $paramsCompleted[] = $epsId;
        }
        
        if ($month > 0) {
            $sqlCompleted .= " AND ca.month = ?";
            $paramsCompleted[] = $month;
        }
        
        $sqlCompleted .= " GROUP BY ca.eps_id, ca.specialty_id";
        
        // Execute completed appointments query
        $stmtCompleted = $pdo->prepare($sqlCompleted);
        $stmtCompleted->execute($paramsCompleted);
        $completedData = $stmtCompleted->fetchAll();
        
        // Get compliance thresholds
        $thresholds = getComplianceThresholds($pdo);
        
        // Combine data and calculate compliance
        $summaries = [];
        
        foreach ($projectedData as $projected) {
            $epsId = $projected['eps_id'];
            $specialtyId = $projected['specialty_id'];
            $projectedQty = (int)$projected['projected_qty'];
            
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
            
            // Add to summaries
            $summaries[] = [
                'eps_id' => $epsId,
                'specialty_id' => $specialtyId,
                'projected_qty' => $projectedQty,
                'completed_qty' => $completedQty,
                'pending_qty' => $pendingQty,
                'compliance' => $compliance
            ];
        }
        
        echo json_encode(['success' => true, 'summaries' => $summaries, 'thresholds' => [
            'red' => $thresholds[0],
            'yellow' => $thresholds[1]
        ]]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get appointments
 * @param PDO $pdo Database connection
 */
function getAppointments($pdo) {
    try {
        // Get parameters
        $yearId = isset($_GET['year_id']) ? (int)$_GET['year_id'] : 0;
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
        
        // Build query
        $sql = "SELECT * FROM completed_appointments WHERE 1=1";
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
        
        $sql .= " ORDER BY appointment_date DESC";
        
        // Execute query
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'appointments' => $appointments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save appointment
 * @param PDO $pdo Database connection
 */
function saveAppointment($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['eps_id']) || empty($data['year_id']) || empty($data['month']) || 
            empty($data['specialty_id']) || empty($data['appointment_date']) || $data['quantity'] < 1) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
            return;
        }
        
        // Sanitize inputs
        $epsId = (int)$data['eps_id'];
        $yearId = (int)$data['year_id'];
        $month = (int)$data['month'];
        $specialtyId = (int)$data['specialty_id'];
        $appointmentDate = $data['appointment_date'];
        $quantity = (int)$data['quantity'];
        
        // Insert appointment
        $stmt = $pdo->prepare("
            INSERT INTO completed_appointments 
            (eps_id, year_id, month, specialty_id, appointment_date, quantity) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $epsId,
            $yearId,
            $month,
            $specialtyId,
            $appointmentDate,
            $quantity
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Atención registrada correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete appointment
 * @param PDO $pdo Database connection
 */
function deleteAppointment($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Falta el ID de la atención']);
            return;
        }
        
        // Delete appointment
        $stmt = $pdo->prepare("DELETE FROM completed_appointments WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Atención eliminada correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Recalculate projections and redistribute pending appointments
 * @param PDO $pdo Database connection
 */
function recalculateProjections($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate data
        $yearId = isset($data['year_id']) ? (int)$data['year_id'] : 0;
        $epsId = isset($data['eps_id']) ? (int)$data['eps_id'] : 0;
        $month = isset($data['month']) ? (int)$data['month'] : 0;
        
        // If no yearId provided, get active year
        if ($yearId <= 0) {
            $activeYear = getActiveYear($pdo);
            if ($activeYear) {
                $yearId = $activeYear['id'];
            } else {
                echo json_encode(['success' => false, 'message' => 'No hay año activo']);
                return;
            }
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get all EPS or specific EPS
        if ($epsId > 0) {
            $epsList = [$epsId];
        } else {
            $stmt = $pdo->query("SELECT id FROM eps WHERE active = 1");
            $epsList = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Process each EPS
        foreach ($epsList as $currentEpsId) {
            // If month is specified, only process that month
            if ($month > 0) {
                redistributePendingAppointments($pdo, $currentEpsId, $yearId, $month);
            } else {
                // Process all months
                for ($i = 1; $i <= 12; $i++) {
                    redistributePendingAppointments($pdo, $currentEpsId, $yearId, $i);
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Proyecciones recalculadas correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
