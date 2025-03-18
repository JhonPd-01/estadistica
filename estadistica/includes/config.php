<?php
// Database configuration
define('DB_HOST', 'localhost');  // Database hostname
define('DB_USER', 'root');       // Database username
define('DB_PASS', '');           // Database password
define('DB_NAME', 'quimiosalud_db'); // Database name

// Application settings
define('APP_NAME', 'Quimiosalud - Sistema de Pronóstico');
define('APP_URL', 'http://localhost/quimiosalud');
define('APP_VERSION', '1.0.0');

// Session settings
define('SESSION_NAME', 'quimiosalud_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// Define fiscal year (Feb to Jan)
function getCurrentFiscalYear() {
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    
    if ($currentMonth === 1) { // January is part of previous fiscal year
        return $currentYear - 1;
    } else {
        return $currentYear;
    }
}

// Define fiscal year months (Feb[2] to Jan[1])
function getFiscalMonths() {
    return [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 1];
}

// Define percentage distribution for months
function getMonthlyDistribution() {
    return [
        2 => 19, // February (19%)
        3 => 19, // March (19%)
        4 => 19, // April (19%)
        5 => 19, // May (19%)
        6 => 19, // June (19%)
        7 => 5,  // July (5%) - mes de gabela
        8 => 0,  // These months are for recalculation of pending appointments
        9 => 0,
        10 => 0,
        11 => 0,
        12 => 0,
        1 => 0   // January
    ];
}

// Set error reporting based on environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('America/Bogota');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
