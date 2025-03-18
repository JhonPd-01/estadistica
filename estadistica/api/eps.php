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
    case 'getAllEPS':
        getAllEPS($pdo);
        break;
    case 'createEPS':
        requireAdmin();
        createEPS($pdo);
        break;
    case 'updateEPS':
        requireAdmin();
        updateEPS($pdo);
        break;
    case 'deleteEPS':
        requireAdmin();
        deleteEPS($pdo);
        break;
    case 'getContractedServices':
        getContractedServices($pdo);
        break;
    case 'saveContractedServices':
        requireAdmin();
        saveContractedServices($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}

/**
 * Get all EPS
 * @param PDO $pdo Database connection
 */
function getAllEPS($pdo) {
    try {
        $includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === 'true';
        
        $sql = "SELECT * FROM eps";
        if (!$includeInactive) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY name";
        
        $stmt = $pdo->query($sql);
        $eps = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'eps' => $eps]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Create a new EPS
 * @param PDO $pdo Database connection
 */
function createEPS($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'El nombre de la EPS es requerido']);
            return;
        }
        
        // Sanitize inputs
        $name = sanitizeInput($data['name']);
        $active = isset($data['active']) && $data['active'] ? 1 : 0;
        
        // Check if EPS already exists
        $stmt = $pdo->prepare("SELECT id FROM eps WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una EPS con ese nombre']);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert EPS
        $stmt = $pdo->prepare("INSERT INTO eps (name, active) VALUES (?, ?)");
        $stmt->execute([$name, $active]);
        
        $epsId = $pdo->lastInsertId();
        
        // Insert default contracted services for the new EPS
        $defaultServices = [
            'MIA' => 2, // Médico infectólogo adultos
            'MIP' => 2, // Médico infectólogo pediátrico
            'MEX' => 10, // Médico experto
            'PSQ' => 4, // Psiquiatría
            'GIN' => 4, // Ginecología fértil
            'GIG' => 8, // Ginecología gestantes
            'ENF' => 12, // Enfermería
            'PSI' => 4, // Psicología
            'NUT' => 4, // Nutrición
            'TSO' => 4, // Trabajo Social
            'QUI' => 12, // Químico
            'ODO' => 2, // Odontología
            'LAB' => 4 // Laboratorios
        ];
        
        // Get specialties
        $stmt = $pdo->query("SELECT id, code FROM specialties");
        $specialties = $stmt->fetchAll();
        
        // Insert contracted services
        $stmt = $pdo->prepare("
            INSERT INTO contracted_services 
            (eps_id, specialty_id, yearly_qty) 
            VALUES (?, ?, ?)
        ");
        
        foreach ($specialties as $specialty) {
            $yearlyQty = isset($defaultServices[$specialty['code']]) ? $defaultServices[$specialty['code']] : 0;
            $stmt->execute([$epsId, $specialty['id'], $yearlyQty]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'EPS creada correctamente',
            'eps_id' => $epsId
        ]);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update an existing EPS
 * @param PDO $pdo Database connection
 */
function updateEPS($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['id']) || empty($data['name'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
            return;
        }
        
        // Sanitize inputs
        $id = (int)$data['id'];
        $name = sanitizeInput($data['name']);
        $active = isset($data['active']) && $data['active'] ? 1 : 0;
        
        // Check if EPS exists
        $stmt = $pdo->prepare("SELECT id FROM eps WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'La EPS no existe']);
            return;
        }
        
        // Check for name duplication
        $stmt = $pdo->prepare("SELECT id FROM eps WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otra EPS con ese nombre']);
            return;
        }
        
        // Update EPS
        $stmt = $pdo->prepare("
            UPDATE eps 
            SET name = ?, active = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$name, $active, $id]);
        
        echo json_encode(['success' => true, 'message' => 'EPS actualizada correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete an EPS
 * @param PDO $pdo Database connection
 */
function deleteEPS($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Falta el ID de la EPS']);
            return;
        }
        
        $id = (int)$data['id'];
        
        // Check if is the last active EPS
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM eps WHERE active = 1");
        $stmt->execute();
        $result = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT active FROM eps WHERE id = ?");
        $stmt->execute([$id]);
        $eps = $stmt->fetch();
        
        if ($result['count'] <= 1 && $eps && $eps['active']) {
            echo json_encode(['success' => false, 'message' => 'No se puede eliminar la única EPS activa']);
            return;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete related records
        $tables = [
            'population',
            'projected_appointments',
            'completed_appointments',
            'contracted_services'
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE eps_id = ?");
            $stmt->execute([$id]);
        }
        
        // Delete EPS
        $stmt = $pdo->prepare("DELETE FROM eps WHERE id = ?");
        $stmt->execute([$id]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'EPS eliminada correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get contracted services for an EPS
 * @param PDO $pdo Database connection
 */
function getContractedServices($pdo) {
    try {
        // Get EPS ID from request
        $epsId = isset($_GET['eps_id']) ? (int)$_GET['eps_id'] : 0;
        
        if ($epsId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Falta el ID de la EPS']);
            return;
        }
        
        // Get contracted services
        $stmt = $pdo->prepare("
            SELECT cs.*, s.name as specialty_name 
            FROM contracted_services cs
            JOIN specialties s ON cs.specialty_id = s.id
            WHERE cs.eps_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$epsId]);
        $services = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'services' => $services]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save contracted services for an EPS
 * @param PDO $pdo Database connection
 */
function saveContractedServices($pdo) {
    try {
        // Get JSON data from request
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['eps_id']) || empty($data['services']) || !is_array($data['services'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            return;
        }
        
        $epsId = (int)$data['eps_id'];
        $services = $data['services'];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Prepare statement for updating services
        $stmt = $pdo->prepare("
            INSERT INTO contracted_services 
            (eps_id, specialty_id, yearly_qty) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE yearly_qty = ?
        ");
        
        // Update services
        foreach ($services as $service) {
            $specialtyId = (int)$service['specialty_id'];
            $yearlyQty = max(0, (int)$service['yearly_qty']);
            
            $stmt->execute([$epsId, $specialtyId, $yearlyQty, $yearlyQty]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Servicios actualizados correctamente']);
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
