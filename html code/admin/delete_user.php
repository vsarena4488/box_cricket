<?php
session_start();
include_once '../gest/db_config.php';
require_admin_login();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    header('Location: users_details.php?error=invalid_user_id');
    exit();
}

if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user_id) {
    header('Location: users_details.php?error=cannot_delete_self');
    exit();
}

$stmt = mysqli_prepare($con, 'DELETE FROM users WHERE id = ?');
if (!$stmt) {
    header('Location: users_details.php?error=delete_failed');
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $user_id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($ok) {
    header('Location: users_details.php?success=user_deleted');
    exit();
}

header('Location: users_details.php?error=delete_failed');
exit();
?>
