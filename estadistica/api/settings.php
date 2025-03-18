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

// Check if user is admin for actions that require it
$adminActions = ['createYear', 'updateYear', 'deleteYear', 'saveSettings'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if (in_array($action, $adminActions) && !isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

// Handle different actions
switch ($action) {
    case 'getYears':
        getYears($pdo);
        break;
    case 'createYear':
        createYear($pdo);
        break;
    case 'updateYear':
        updateYear($pdo);
        break;
    case 'deleteYear':
        deleteYear($pdo);
        break;
    case 'getSettings':
        getSettings($pdo);
        break;
    case 'saveSettings':
        saveSettings($pdo);
        break;
    case 'getComplianceThresholds':
        getComplianceThresholdsJson($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Get all management years
 * @param PDO $pdo Database connection
 */
function getYears($pdo) {
    try {
        $years = getAllYears($pdo);
        echo json_encode(['success' => true, 'years' => $years]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Create a new management year
 * @param PDO $pdo Database connection
 */
function createYear($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['year_label']) || empty($data['start_date']) || empty($data['end_date'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
            return;
        }
        
        // Sanitize inputs
        $yearLabel = sanitizeInput($data['year_label']);
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $active = isset($data['active']) && $data['active'] ? 1 : 0;
        
        // Validate dates
        if (strtotime($startDate) >= strtotime($endDate)) {
            echo json_encode(['success' => false, 'message' => 'La fecha de inicio debe ser anterior a la fecha de fin']);
            return;
        }
        
        // Check for duplicate year label
        $stmt = $pdo->prepare("SELECT id FROM management_years WHERE year_label = ?");
        $stmt->execute([$yearLabel]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un año con esta etiqueta']);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // If setting as active, deactivate all other years
        if ($active) {
            $stmt = $pdo->prepare("UPDATE management_years SET active = 0");
            $stmt->execute();
        }
        
        // Insert new year
        $stmt = $pdo->prepare("
            INSERT INTO management_years 
            (year_label, start_date, end_date, active) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$yearLabel, $startDate, $endDate, $active]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Año creado correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update an existing management year
 * @param PDO $pdo Database connection
 */
function updateYear($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['id']) || empty($data['year_label']) || empty($data['start_date']) || empty($data['end_date'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
            return;
        }
        
        // Sanitize inputs
        $id = (int)$data['id'];
        $yearLabel = sanitizeInput($data['year_label']);
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $active = isset($data['active']) && $data['active'] ? 1 : 0;
        
        // Validate dates
        if (strtotime($startDate) >= strtotime($endDate)) {
            echo json_encode(['success' => false, 'message' => 'La fecha de inicio debe ser anterior a la fecha de fin']);
            return;
        }
        
        // Check if year exists
        $stmt = $pdo->prepare("SELECT id FROM management_years WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'El año no existe']);
            return;
        }
        
        // Check for duplicate year label
        $stmt = $pdo->prepare("SELECT id FROM management_years WHERE year_label = ? AND id != ?");
        $stmt->execute([$yearLabel, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otro año con esta etiqueta']);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // If setting as active, deactivate all other years
        if ($active) {
            $stmt = $pdo->prepare("UPDATE management_years SET active = 0");
            $stmt->execute();
        }
        
        // Update year
        $stmt = $pdo->prepare("
            UPDATE management_years 
            SET year_label = ?, start_date = ?, end_date = ?, active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$yearLabel, $startDate, $endDate, $active, $id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Año actualizado correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete a management year
 * @param PDO $pdo Database connection
 */
function deleteYear($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Falta el ID del año']);
            return;
        }
        
        $id = (int)$data['id'];
        
        // Check if year exists and is not active
        $stmt = $pdo->prepare("SELECT active FROM management_years WHERE id = ?");
        $stmt->execute([$id]);
        $year = $stmt->fetch();
        
        if (!$year) {
            echo json_encode(['success' => false, 'message' => 'El año no existe']);
            return;
        }
        
        if ((bool)$year['active']) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar un año activo']);
            return;
        }
        
        // Count total years
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM management_years");
        $count = $stmt->fetch()['count'];
        
        if ($count <= 1) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar el único año existente']);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete related records
        $tables = [
            'population',
            'projected_appointments',
            'completed_appointments'
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE year_id = ?");
            $stmt->execute([$id]);
        }
        
        // Delete year
        $stmt = $pdo->prepare("DELETE FROM management_years WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Año eliminado correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get system settings
 * @param PDO $pdo Database connection
 */
function getSettings($pdo) {
    try {
        // Get all settings
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settingsArray = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Set default values if settings don't exist
        $settings = [
            'work_days' => $settingsArray['work_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'distribution_percentage' => $settingsArray['distribution_percentage'] ?? '19,19,19,19,19,5',
            'compliance_threshold_red' => $settingsArray['compliance_threshold_red'] ?? '70',
            'compliance_threshold_yellow' => $settingsArray['compliance_threshold_yellow'] ?? '90'
        ];
        
        echo json_encode(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save system settings
 * @param PDO $pdo Database connection
 */
function saveSettings($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate distribution percentage
        if (isset($data['distribution_percentage'])) {
            $percentages = explode(',', $data['distribution_percentage']);
            $total = array_sum(array_map('intval', $percentages));
            
            if ($total != 100) {
                echo json_encode(['success' => false, 'message' => 'La suma de los porcentajes de distribución debe ser 100%']);
                return;
            }
        }
        
        // Validate work days
        if (isset($data['work_days']) && empty($data['work_days'])) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar al menos un día laboral']);
            return;
        }
        
        // Validate thresholds
        if (isset($data['compliance_threshold_red']) && isset($data['compliance_threshold_yellow'])) {
            $redThreshold = (int)$data['compliance_threshold_red'];
            $yellowThreshold = (int)$data['compliance_threshold_yellow'];
            
            if ($redThreshold >= $yellowThreshold) {
                echo json_encode(['success' => false, 'message' => 'El umbral rojo debe ser menor que el umbral amarillo']);
                return;
            }
            
            if ($redThreshold < 0 || $redThreshold > 100 || $yellowThreshold < 0 || $yellowThreshold > 100) {
                echo json_encode(['success' => false, 'message' => 'Los umbrales deben estar entre 0 y 100']);
                return;
            }
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Prepare statement for updating settings
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        // Update each setting
        foreach ($data as $key => $value) {
            $stmt->execute([$key, $value, $value]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get compliance thresholds for API
 * @param PDO $pdo Database connection
 */
function getComplianceThresholdsJson($pdo) {
    try {
        $thresholds = getComplianceThresholds($pdo);
        
        echo json_encode([
            'success' => true, 
            'thresholds' => [
                'red' => $thresholds[0],
                'yellow' => $thresholds[1]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
