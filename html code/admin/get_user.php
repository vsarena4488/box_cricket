<?php
session_start();
include_once '../gest/db_config.php';
require_admin_login();

header('Content-Type: application/json; charset=utf-8');

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user id']);
    exit();
}

$stmt = mysqli_prepare(
    $con,
    'SELECT id, fullname, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare query']);
    exit();
}

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

echo json_encode($user);
exit();
?>
