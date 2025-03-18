<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Initialize Auth
$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Acceso no autorizado'
    ]);
    exit;
}

// Initialize database connection
$db = Database::getInstance();

// Handle API requests based on action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_all':
        // Get all specialists
        getAllSpecialists();
        break;
        
    case 'get_by_specialty':
        // Get specialists by specialty
        getSpecialistsBySpecialty();
        break;
        
    case 'add':
        // Add new specialist
        addSpecialist();
        break;
        
    case 'update':
        // Update specialist
        updateSpecialist();
        break;
        
    case 'toggle_status':
        // Toggle specialist active status
        toggleSpecialistStatus();
        break;
        
    case 'delete':
        // Delete specialist
        deleteSpecialist();
        break;
        
    default:
        // Invalid action
        echo json_encode([
            'success' => false,
            'message' => 'Acción no válida'
        ]);
        break;
}

/**
 * Get all specialists
 */
function getAllSpecialists() {
    global $db;
    
    try {
        $specialists = $db->getAll(
            "SELECT s.*, sp.name as specialty_name 
            FROM specialists s 
            JOIN specialties sp ON s.specialty_id = sp.id 
            ORDER BY s.name"
        );
        
        echo json_encode([
            'success' => true,
            'specialists' => $specialists
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener especialistas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get specialists by specialty
 */
function getSpecialistsBySpecialty() {
    global $db;
    
    // Get request parameters
    $specialtyId = isset($_GET['specialty_id']) ? (int)$_GET['specialty_id'] : 0;
    
    if ($specialtyId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de especialidad inválido'
        ]);
        return;
    }
    
    try {
        $specialists = $db->getAll(
            "SELECT s.*, sp.name as specialty_name 
            FROM specialists s 
            JOIN specialties sp ON s.specialty_id = sp.id 
            WHERE s.specialty_id = ? AND s.is_active = 1 
            ORDER BY s.name",
            [$specialtyId]
        );
        
        echo json_encode([
            'success' => true,
            'specialists' => $specialists
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener especialistas: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add new specialist
 */
function addSpecialist() {
    global $db, $auth;
    
    // Check if user is admin
    if (!$auth->isAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para realizar esta acción'
        ]);
        return;
    }
    
    // Get JSON request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    if (!$data) {
        echo json_encode([
            'success' => false,
            'message' => 'Datos inválidos'
        ]);
        return;
    }
    
    // Validate required fields
    if (!isset($data['name']) || trim($data['name']) === '' || 
        !isset($data['specialty_id']) || (int)$data['specialty_id'] <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'El nombre y la especialidad son obligatorios'
        ]);
        return;
    }
    
    try {
        // Check if specialty exists
        $specialty = $db->single(
            "SELECT id FROM specialties WHERE id = ?", 
            [(int)$data['specialty_id']]
        );
        
        if (!$specialty) {
            echo json_encode([
                'success' => false,
                'message' => 'Especialidad no encontrada'
            ]);
            return;
        }
        
        // Set default value for is_active
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        
        // Insert new specialist
        $db->query(
            "INSERT INTO specialists (name, specialty_id, is_active) 
            VALUES (?, ?, ?)",
            [
                trim($data['name']),
                (int)$data['specialty_id'],
                $isActive
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Especialista agregado correctamente',
            'id' => $db->lastInsertId()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al agregar especialista: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update specialist
 */
function updateSpecialist() {
    global $db, $auth;
    
    // Check if user is admin
    if (!$auth->isAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para realizar esta acción'
        ]);
        return;
    }
    
    // Get JSON request body
    $requestBody = file_get_contents('php://input');
    $data = json_decode($requestBody, true);
    
    if (!$data) {
        echo json_encode([
            'success' => false,
            'message' => 'Datos inválidos'
        ]);
        return;
    }
    
    // Validate required fields
    if (!isset($data['id']) || (int)$data['id'] <= 0 ||
        !isset($data['name']) || trim($data['name']) === '' || 
        !isset($data['specialty_id']) || (int)$data['specialty_id'] <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Faltan campos requeridos'
        ]);
        return;
    }
    
    $specialistId = (int)$data['id'];
    
    try {
        // Check if specialist exists
        $specialist = $db->single(
            "SELECT id FROM specialists WHERE id = ?", 
            [$specialistId]
        );
        
        if (!$specialist) {
            echo json_encode([
                'success' => false,
                'message' => 'Especialista no encontrado'
            ]);
            return;
        }
        
        // Check if specialty exists
        $specialty = $db->single(
            "SELECT id FROM specialties WHERE id = ?", 
            [(int)$data['specialty_id']]
        );
        
        if (!$specialty) {
            echo json_encode([
                'success' => false,
                'message' => 'Especialidad no encontrada'
            ]);
            return;
        }
        
        // Set default value for is_active
        $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;
        
        // Update specialist
        $db->query(
            "UPDATE specialists SET 
            name = ?, 
            specialty_id = ?, 
            is_active = ? 
            WHERE id = ?",
            [
                trim($data['name']),
                (int)$data['specialty_id'],
                $isActive,
                $specialistId
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Especialista actualizado correctamente'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar especialista: ' . $e->getMessage()
        ]);
    }
}

/**
 * Toggle specialist active status
 */
function toggleSpecialistStatus() {
    global $db, $auth;
    
    // Check if user is admin
    if (!$auth->isAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para realizar esta acción'
        ]);
        return;
    }
    
    // Get request parameters
    $specialistId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($specialistId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de especialista inválido'
        ]);
        return;
    }
    
    try {
        // Get current status
        $specialist = $db->single(
            "SELECT is_active FROM specialists WHERE id = ?", 
            [$specialistId]
        );
        
        if (!$specialist) {
            echo json_encode([
                'success' => false,
                'message' => 'Especialista no encontrado'
            ]);
            return;
        }
        
        // Toggle status
        $newStatus = !$specialist['is_active'];
        
        $db->query(
            "UPDATE specialists SET is_active = ? WHERE id = ?",
            [$newStatus, $specialistId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado del especialista actualizado correctamente',
            'new_status' => $newStatus
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al actualizar estado del especialista: ' . $e->getMessage()
        ]);
    }
}

/**
 * Delete specialist
 */
function deleteSpecialist() {
    global $db, $auth;
    
    // Check if user is admin
    if (!$auth->isAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tiene permisos para realizar esta acción'
        ]);
        return;
    }
    
    // Get request parameters
    $specialistId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($specialistId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de especialista inválido'
        ]);
        return;
    }
    
    try {
        // Check if specialist is referenced in appointments
        $appointmentsCount = $db->single(
            "SELECT COUNT(*) as count FROM appointments WHERE specialist_id = ?", 
            [$specialistId]
        );
        
        if ($appointmentsCount && $appointmentsCount['count'] > 0) {
            // Specialist is referenced, just mark as inactive
            $db->query(
                "UPDATE specialists SET is_active = 0 WHERE id = ?",
                [$specialistId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'El especialista tiene atenciones registradas. Se ha marcado como inactivo.'
            ]);
        } else {
            // Specialist is not referenced, delete it
            $db->query(
                "DELETE FROM specialists WHERE id = ?",
                [$specialistId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Especialista eliminado correctamente'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al eliminar especialista: ' . $e->getMessage()
        ]);
    }
}
