<?php
session_start();
include_once '../gest/db_config.php';
require_admin_login();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = trim($_GET['status'] ?? '');

if ($user_id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
    header('Location: users_details.php?error=invalid_status_request');
    exit();
}

if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id && $status !== 'active') {
    header('Location: users_details.php?error=cannot_suspend_self');
    exit();
}

$stmt = mysqli_prepare($con, 'UPDATE users SET status = ? WHERE id = ?');
if (!$stmt) {
    header('Location: users_details.php?error=status_update_failed');
    exit();
}

mysqli_stmt_bind_param($stmt, 'si', $status, $user_id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok) {
    header('Location: users_details.php?success=status_updated');
    exit();
}

header('Location: users_details.php?error=status_update_failed');
exit();
?>
