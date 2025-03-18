<?php
/**
 * Global configuration settings for the application
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application settings
define('APP_NAME', 'Sistema de Pronóstico - Quimiosalud SAS');
define('APP_VERSION', '1.0.0');
define('APP_URL', '/');
define('TIMEZONE', 'America/Bogota');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Authentication settings
define('AUTH_TIMEOUT', 3600); // Session timeout in seconds (1 hour)

// Function to check if user is logged in
function isLoggedIn() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        // Check if session has expired
        if (time() - $_SESSION['last_activity'] < AUTH_TIMEOUT) {
            // Update last activity
            $_SESSION['last_activity'] = time();
            return true;
        } else {
            // Session expired, destroy session
            session_unset();
            session_destroy();
        }
    }
    return false;
}

// Function to check if user is admin
function isAdmin() {
    return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
}

// Function to redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Function to generate a random token
function generateToken() {
    return bin2hex(random_bytes(32));
}

// Get current month name in Spanish
function getCurrentMonthName() {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[date('n')];
}

// Get month name in Spanish
function getMonthName($monthNumber) {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[$monthNumber];
}

// Get number of working days in a month (Mon-Sat)
function getWorkingDays($year, $month) {
    $firstDay = new DateTime("$year-$month-01");
    $lastDay = new DateTime($firstDay->format('Y-m-t'));
    
    $workingDays = 0;
    $currentDay = clone $firstDay;
    
    while ($currentDay <= $lastDay) {
        $dayOfWeek = $currentDay->format('N'); // 1 (Monday) to 7 (Sunday)
        if ($dayOfWeek < 7) { // Monday to Saturday
            $workingDays++;
        }
        $currentDay->modify('+1 day');
    }
    
    return $workingDays;
}

// Calculate compliance status based on percentage
function getComplianceStatus($percentage) {
    if ($percentage < 80) {
        return 'danger'; // Red
    } elseif ($percentage >= 80 && $percentage < 95) {
        return 'warning'; // Yellow
    } else {
        return 'success'; // Green
    }
}

// Format number with commas for thousands
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}

// Calculate percentage
function calculatePercentage($value, $total) {
    if ($total == 0) return 0;
    return round(($value / $total) * 100, 2);
}
?>
