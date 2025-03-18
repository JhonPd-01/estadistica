<?php

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect user if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Check if user is an admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Redirect if user is not an admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Get current active management year
 * @param PDO $pdo Database connection
 * @return array|null Year data or null if not found
 */
function getActiveYear($pdo) {
    $stmt = $pdo->query("SELECT * FROM management_years WHERE active = 1 LIMIT 1");
    return $stmt->fetch();
}

/**
 * Get all management years
 * @param PDO $pdo Database connection
 * @return array Years data
 */
function getAllYears($pdo) {
    $stmt = $pdo->query("SELECT * FROM management_years ORDER BY start_date DESC");
    return $stmt->fetchAll();
}

/**
 * Get all EPS
 * @param PDO $pdo Database connection
 * @param bool $activeOnly Only return active EPS
 * @return array EPS data
 */
function getAllEPSLegacy($pdo, $activeOnly = true) {
    $sql = "SELECT * FROM eps";
    if ($activeOnly) {
        $sql .= " WHERE active = 1";
    }
    $sql .= " ORDER BY name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get all specialties
 * @param PDO $pdo Database connection
 * @return array Specialties data
 */
function getAllSpecialties($pdo) {
    $stmt = $pdo->query("SELECT * FROM specialties ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get month name in Spanish
 * @param int $month Month number (1-12)
 * @return string Month name
 */
function getMonthName($month) {
    $months = [
        1 => 'Febrero',
        2 => 'Marzo',
        3 => 'Abril',
        4 => 'Mayo',
        5 => 'Junio',
        6 => 'Julio',
        7 => 'Agosto',
        8 => 'Septiembre',
        9 => 'Octubre',
        10 => 'Noviembre',
        11 => 'Diciembre',
        12 => 'Enero'
    ];
    return $months[$month] ?? '';
}

/**
 * Calculate working days in a month
 * @param int $year Year
 * @param int $month Month (1-12)
 * @param array $workdays Array of working days (0=Sunday, 6=Saturday)
 * @return int Number of working days
 */
function getWorkingDaysInMonth($year, $month, $workdays) {
    // Convert management month to calendar month
    $calendarMonth = ($month % 12) + 1;
    $calendarYear = $year;
    if ($month == 12) {
        $calendarYear++; // January of next year
    }
    
    $date = new DateTime("$calendarYear-$calendarMonth-01");
    $daysInMonth = (int)$date->format('t');
    $count = 0;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date->setDate($calendarYear, $calendarMonth, $day);
        $weekday = (int)$date->format('w'); // 0 (Sunday) to 6 (Saturday)
        if (in_array($weekday, $workdays)) {
            $count++;
        }
    }
    
    return $count;
}

/**
 * Get setting value
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['setting_value'];
    }
    
    return $default;
}

/**
 * Get work days array from settings
 * @param PDO $pdo Database connection
 * @return array Work days (0=Sunday, 6=Saturday)
 */
function getWorkDays($pdo) {
    $days = getSetting($pdo, 'work_days', 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
    $dayNames = explode(',', $days);
    $dayNumbers = [];
    
    $map = [
        'Sunday' => 0,
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6
    ];
    
    foreach ($dayNames as $day) {
        if (isset($map[trim($day)])) {
            $dayNumbers[] = $map[trim($day)];
        }
    }
    
    return $dayNumbers;
}

/**
 * Get distribution percentages from settings
 * @param PDO $pdo Database connection
 * @return array Percentages for each month
 */
function getDistributionPercentages($pdo) {
    $percentages = getSetting($pdo, 'distribution_percentage', '19,19,19,19,19,5');
    return array_map('intval', explode(',', $percentages));
}

/**
 * Get compliance thresholds from settings
 * @param PDO $pdo Database connection
 * @return array [red_threshold, yellow_threshold]
 */
function getComplianceThresholds($pdo) {
    $red = (int)getSetting($pdo, 'compliance_threshold_red', 70);
    $yellow = (int)getSetting($pdo, 'compliance_threshold_yellow', 90);
    return [$red, $yellow];
}

/**
 * Calculate projected appointments based on population data
 * @param PDO $pdo Database connection
 * @param int $epsId EPS ID
 * @param int $yearId Year ID
 * @param int $month Month number (1-12)
 * @return bool Success status
 */
function calculateProjectedAppointments($pdo, $epsId, $yearId, $month) {
    try {
        // Get population data for the month
        $stmt = $pdo->prepare("
            SELECT * FROM population 
            WHERE eps_id = ? AND year_id = ? AND month = ?
        ");
        $stmt->execute([$epsId, $yearId, $month]);
        $population = $stmt->fetch();
        
        if (!$population) {
            return false;
        }
        
        // Get contracted services for the EPS
        $stmt = $pdo->prepare("
            SELECT cs.*, s.name as specialty_name, s.code as specialty_code
            FROM contracted_services cs
            JOIN specialties s ON cs.specialty_id = s.id
            WHERE cs.eps_id = ?
        ");
        $stmt->execute([$epsId]);
        $services = $stmt->fetchAll();
        
        // Get distribution percentages
        $percentages = getDistributionPercentages($pdo);
        $monthPercentage = isset($percentages[$month - 1]) ? $percentages[$month - 1] / 100 : 0.19;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Calculate projected appointments for each specialty
        foreach ($services as $service) {
            $specialtyId = $service['specialty_id'];
            $yearlyQty = $service['yearly_qty'];
            $specialtyCode = $service['specialty_code'];
            
            // Calculate the number of appointments for this month based on specialty
            $appointments = 0;
            
            switch ($specialtyCode) {
                case 'MIA': // Médico infectólogo adultos
                    $appointments = $population['adults'] * $yearlyQty * $monthPercentage;
                    break;
                case 'MIP': // Médico infectólogo pediátrico
                    $appointments = ($population['pediatric_diagnosed'] + $population['minors_follow_up']) * $yearlyQty * $monthPercentage;
                    break;
                case 'PED': // Pediatría
                    $appointments = ($population['pediatric_diagnosed'] + $population['minors_follow_up']) * $yearlyQty * $monthPercentage;
                    break;
                case 'PSQ': // Psiquiatría
                    $appointments = $population['adults'] * $yearlyQty * $monthPercentage;
                    break;
                case 'GIN': // Ginecología
                    $appointments = $population['fertile_women'] * $yearlyQty * $monthPercentage;
                    break;
                case 'GIG': // Ginecología gestantes
                    $appointments = $population['pregnant_women'] * $yearlyQty * $monthPercentage;
                    break;
                case 'ODO': // Odontología
                    $appointments = $population['adults'] * $yearlyQty * $monthPercentage;
                    break;
                case 'LAB': // Laboratorios
                    $appointments = ($population['adults'] + $population['pediatric_diagnosed']) * $yearlyQty * $monthPercentage;
                    break;
                default: // Enfermería, Psicología, Nutrición, Trabajo Social, Químico, etc.
                    $appointments = $population['active_population'] * $yearlyQty * $monthPercentage;
                    break;
            }
            
            // Round to integer
            $appointments = (int)ceil($appointments);
            
            // Insert or update projected appointments
            $stmt = $pdo->prepare("
                INSERT INTO projected_appointments 
                (eps_id, year_id, month, specialty_id, projected_qty) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE projected_qty = ?
            ");
            $stmt->execute([$epsId, $yearId, $month, $specialtyId, $appointments, $appointments]);
        }
        
        // Commit transaction
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error calculating projected appointments: " . $e->getMessage());
        return false;
    }
}

/**
 * Get completed appointments count for a specific month
 * @param PDO $pdo Database connection
 * @param int $epsId EPS ID
 * @param int $yearId Year ID
 * @param int $month Month number (1-12)
 * @param int $specialtyId Specialty ID
 * @return int Number of completed appointments
 */
function getCompletedAppointments($pdo, $epsId, $yearId, $month, $specialtyId) {
    $stmt = $pdo->prepare("
        SELECT SUM(quantity) as total
        FROM completed_appointments
        WHERE eps_id = ? AND year_id = ? AND month = ? AND specialty_id = ?
    ");
    $stmt->execute([$epsId, $yearId, $month, $specialtyId]);
    $result = $stmt->fetch();
    
    return $result && $result['total'] ? (int)$result['total'] : 0;
}

/**
 * Calculate compliance percentage
 * @param int $completed Completed appointments
 * @param int $projected Projected appointments
 * @return int Percentage of compliance
 */
function calculateCompliance($completed, $projected) {
    if ($projected <= 0) {
        return 0;
    }
    
    return min(100, (int)(($completed / $projected) * 100));
}

/**
 * Get compliance status based on percentage
 * @param int $percentage Compliance percentage
 * @param array $thresholds [red_threshold, yellow_threshold]
 * @return string 'danger', 'warning', or 'success'
 */
function getComplianceStatus($percentage, $thresholds) {
    if ($percentage < $thresholds[0]) {
        return 'danger';
    } elseif ($percentage < $thresholds[1]) {
        return 'warning';
    } else {
        return 'success';
    }
}

/**
 * Redistribute pending appointments to future months
 * @param PDO $pdo Database connection
 * @param int $epsId EPS ID
 * @param int $yearId Year ID
 * @param int $month Month number (1-12)
 * @return bool Success status
 */
function redistributePendingAppointments($pdo, $epsId, $yearId, $month) {
    try {
        // Get all specialties
        $specialties = getAllSpecialties($pdo);
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Process each specialty
        foreach ($specialties as $specialty) {
            $specialtyId = $specialty['id'];
            
            // Get projected and completed appointments for current month
            $stmt = $pdo->prepare("
                SELECT projected_qty FROM projected_appointments
                WHERE eps_id = ? AND year_id = ? AND month = ? AND specialty_id = ?
            ");
            $stmt->execute([$epsId, $yearId, $month, $specialtyId]);
            $projected = $stmt->fetch();
            
            if (!$projected) {
                continue; // Skip if no projection exists
            }
            
            $projectedQty = (int)$projected['projected_qty'];
            $completedQty = getCompletedAppointments($pdo, $epsId, $yearId, $month, $specialtyId);
            
            // Calculate pending appointments
            $pendingQty = max(0, $projectedQty - $completedQty);
            
            if ($pendingQty > 0) {
                // Calculate remaining months in the year
                $remainingMonths = 0;
                for ($i = $month + 1; $i <= 12; $i++) {
                    $remainingMonths++;
                }
                
                if ($remainingMonths > 0) {
                    // Distribute pending appointments equally among remaining months
                    $distribution = floor($pendingQty / $remainingMonths);
                    $remainder = $pendingQty % $remainingMonths;
                    
                    for ($i = $month + 1; $i <= 12; $i++) {
                        // Add extra appointment from remainder if applicable
                        $extra = ($i - $month - 1) < $remainder ? 1 : 0;
                        $additionalQty = $distribution + $extra;
                        
                        if ($additionalQty > 0) {
                            // Update projected appointments for future month
                            $stmt = $pdo->prepare("
                                INSERT INTO projected_appointments 
                                (eps_id, year_id, month, specialty_id, projected_qty) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE projected_qty = projected_qty + ?
                            ");
                            $stmt->execute([$epsId, $yearId, $i, $specialtyId, $additionalQty, $additionalQty]);
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error redistributing pending appointments: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate CSV data
 * @param array $data Array of data
 * @param array $headers CSV headers
 * @return string CSV content
 */
function generateCSV($data, $headers) {
    $output = fopen('php://temp', 'r+');
    
    // Add headers
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

/**
 * Sanitize and validate input
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
