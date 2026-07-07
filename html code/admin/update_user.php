<?php
session_start();
include_once '../gest/db_config.php';
require_admin_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: users_details.php');
    exit();
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$fullname = trim($_POST['fullname'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$role = trim($_POST['role'] ?? 'user');
$status = trim($_POST['status'] ?? 'active');

$valid_roles = ['admin', 'user'];
$valid_status = ['active', 'inactive', 'pending'];

if ($user_id <= 0 || $fullname === '' || $email === '') {
    header('Location: users_details.php?error=invalid_update_data');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: users_details.php?error=invalid_email');
    exit();
}

if (!in_array($role, $valid_roles, true)) {
    $role = 'user';
}

if (!in_array($status, $valid_status, true)) {
    $status = 'active';
}

if ($phone === '') {
    $phone = null;
}

$check_stmt = mysqli_prepare($con, 'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
if ($check_stmt) {
    mysqli_stmt_bind_param($check_stmt, 'si', $email, $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $email_exists = $check_result && mysqli_num_rows($check_result) > 0;
    mysqli_stmt_close($check_stmt);

    if ($email_exists) {
        header('Location: users_details.php?error=email_exists');
        exit();
    }
}

$stmt = mysqli_prepare(
    $con,
    'UPDATE users SET fullname = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?'
);

if (!$stmt) {
    header('Location: users_details.php?error=update_failed');
    exit();
}

mysqli_stmt_bind_param($stmt, 'sssssi', $fullname, $email, $phone, $role, $status, $user_id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok) {
    header('Location: users_details.php?success=user_updated');
    exit();
}

header('Location: users_details.php?error=update_failed');
exit();
?>
