<?php
// Redirect to login page or dashboard
session_start();

if (isset($_SESSION['user_id'])) {
    // If user is logged in, redirect to dashboard
    header("Location: dashboard.php");
    exit;
} else {
    // If user is not logged in, redirect to login page
    header("Location: login.php");
    exit;
}
