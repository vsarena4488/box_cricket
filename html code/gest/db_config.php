<?php
// Database Configuration
$host = "localhost";
$user = "root";          // default in XAMPP
$pass = "";              // default is empty
$db   = "box_cricket";   // your database name

// Create Connection
$con = mysqli_connect($host, $user, $pass, $db);
$conn = $con; // Backward compatibility: some files use $conn, others use $con.

// Check Connection
if (!$con) {
    die("Database Connection Failed: " . mysqli_connect_error());
} else {
    // echo "Database Connection Successful!";
}

// Optional: Set charset (recommended)
mysqli_set_charset($con, "utf8");

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_to($path)
{
    if (!headers_sent()) {
        header("Location: $path");
        exit();
    }

    echo "<script>window.location.href=" . json_encode($path) . ";</script>";
    exit();
}

function is_admin_logged_in()
{
    return !empty($_SESSION['role']) && $_SESSION['role'] === 'admin' && !empty($_SESSION['admin']);
}

function is_user_logged_in()
{
    return !empty($_SESSION['role']) && $_SESSION['role'] === 'user' && !empty($_SESSION['user']);
}

function require_admin_login()
{
    if (!is_admin_logged_in()) {
        redirect_to('../gest/login.php');
    }
}

function require_user_login()
{
    if (!is_user_logged_in()) {
        redirect_to('../gest/login.php');
    }
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Helper function to get client IP
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
