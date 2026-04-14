<?php
// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$db   = "box_cricket";

// Create Connection
$con = mysqli_connect($host, $user, $pass, $db);

// Check Connection
if (!$con) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($con, "utf8");

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check admin login
function is_admin_logged_in() {
    return !empty($_SESSION['role']) && $_SESSION['role'] === 'admin' && !empty($_SESSION['admin']);
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        header("Location: ../gest/login.php");
        exit();
    }
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>