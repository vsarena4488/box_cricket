<?php
session_start();
include_once '../gest/db_config.php';

if (function_exists('require_admin_login')) {
    require_admin_login();
} else {
    if (!isset($_SESSION['admin']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../gest/login.php");
        exit();
    }
}

if (!isset($con) && isset($conn)) {
    $con = $conn;
}
if (!isset($con) && isset($connection)) {
    $con = $connection;
}
if (!isset($con) || !$con) {
    die("Database connection failed. Please check your configuration.");
}

$admin_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($admin_id <= 0) {
    header("Location: ../gest/login.php");
    exit();
}

$message = '';
$message_type = '';
$profile_upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile_pictures';
$profile_upload_web_dir = 'uploads/profile_pictures';

if (!is_dir($profile_upload_dir)) {
    mkdir($profile_upload_dir, 0755, true);
}

function get_admin_profile_picture_path($upload_dir, $web_dir, $user_id)
{
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    foreach ($allowed_extensions as $extension) {
        $file_name = 'admin_' . $user_id . '.' . $extension;
        $full_path = $upload_dir . DIRECTORY_SEPARATOR . $file_name;
        if (file_exists($full_path)) {
            return $web_dir . '/' . $file_name;
        }
    }
    return '';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $uploaded_file = $_FILES['profile_picture'] ?? null;
    $new_profile_picture_uploaded = false;

    if ($fullname === '' || $email === '') {
        $message = 'Name and email are required.';
        $message_type = 'danger';
    } else {
        $check_sql = "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1";
        $check_stmt = mysqli_prepare($con, $check_sql);

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "si", $email, $admin_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $email_exists = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);

            if ($email_exists) {
                $message = 'This email is already used by another account.';
                $message_type = 'danger';
            } else {
                if ($uploaded_file && isset($uploaded_file['name']) && $uploaded_file['name'] !== '') {
                    if ((int) $uploaded_file['error'] !== UPLOAD_ERR_OK) {
                        $message = 'Failed to upload profile picture.';
                        $message_type = 'danger';
                    } elseif ((int) $uploaded_file['size'] > 2 * 1024 * 1024) {
                        $message = 'Profile picture must be less than 2MB.';
                        $message_type = 'danger';
                    } else {
                        $image_info = @getimagesize($uploaded_file['tmp_name']);
                        $extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                        if (!$image_info || !in_array($extension, $allowed_extensions, true)) {
                            $message = 'Please upload a valid image file (jpg, jpeg, png, gif, webp).';
                            $message_type = 'danger';
                        } else {
                            $new_file_name = 'admin_' . $admin_id . '.' . $extension;
                            $new_file_path = $profile_upload_dir . DIRECTORY_SEPARATOR . $new_file_name;

                            if (move_uploaded_file($uploaded_file['tmp_name'], $new_file_path)) {
                                $new_profile_picture_uploaded = true;
                                foreach (glob($profile_upload_dir . DIRECTORY_SEPARATOR . 'admin_' . $admin_id . '.*') as $old_file) {
                                    if ($old_file !== $new_file_path && is_file($old_file)) {
                                        @unlink($old_file);
                                    }
                                }
                            } else {
                                $message = 'Unable to save profile picture.';
                                $message_type = 'danger';
                            }
                        }
                    }
                }

                if ($message_type !== 'danger') {
                    $update_sql = "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ? AND role = 'admin'";
                    $update_stmt = mysqli_prepare($con, $update_sql);

                    if ($update_stmt) {
                        mysqli_stmt_bind_param($update_stmt, "sssi", $fullname, $email, $phone, $admin_id);
                        if (mysqli_stmt_execute($update_stmt)) {
                            $_SESSION['admin'] = $email;
                            $_SESSION['admin_name'] = $fullname;
                            $message = $new_profile_picture_uploaded ? 'Profile and picture updated successfully.' : 'Profile updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to update profile. Please try again.';
                            $message_type = 'danger';
                        }
                        mysqli_stmt_close($update_stmt);
                    } else {
                        $message = 'Database error while updating profile.';
                        $message_type = 'danger';
                    }
                }
            }
        } else {
            $message = 'Database error while checking email.';
            $message_type = 'danger';
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $message = 'Please fill all password fields.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirm password do not match.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 4) {
        $message = 'New password must be at least 4 characters.';
        $message_type = 'danger';
    } else {
        $pass_sql = "SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1";
        $pass_stmt = mysqli_prepare($con, $pass_sql);

        if ($pass_stmt) {
            mysqli_stmt_bind_param($pass_stmt, "i", $admin_id);
            mysqli_stmt_execute($pass_stmt);
            $pass_result = mysqli_stmt_get_result($pass_stmt);
            $pass_row = mysqli_fetch_assoc($pass_result);
            mysqli_stmt_close($pass_stmt);

            if (!$pass_row || $pass_row['password'] !== $current_password) {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            } else {
                $update_pass_sql = "UPDATE users SET password = ? WHERE id = ? AND role = 'admin'";
                $update_pass_stmt = mysqli_prepare($con, $update_pass_sql);

                if ($update_pass_stmt) {
                    mysqli_stmt_bind_param($update_pass_stmt, "si", $new_password, $admin_id);
                    if (mysqli_stmt_execute($update_pass_stmt)) {
                        $message = 'Password changed successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update password.';
                        $message_type = 'danger';
                    }
                    mysqli_stmt_close($update_pass_stmt);
                } else {
                    $message = 'Database error while changing password.';
                    $message_type = 'danger';
                }
            }
        } else {
            $message = 'Database error while reading password.';
            $message_type = 'danger';
        }
    }
}

$admin_sql = "SELECT id, fullname, email, phone, role, status, created_at FROM users WHERE id = ? AND role = 'admin' LIMIT 1";
$admin_stmt = mysqli_prepare($con, $admin_sql);
$admin_user = null;
if ($admin_stmt) {
    mysqli_stmt_bind_param($admin_stmt, "i", $admin_id);
    mysqli_stmt_execute($admin_stmt);
    $admin_result = mysqli_stmt_get_result($admin_stmt);
    $admin_user = mysqli_fetch_assoc($admin_result);
    mysqli_stmt_close($admin_stmt);
}

if (!$admin_user) {
    header("Location: ../gest/login.php");
    exit();
}

$profile_picture_path = get_admin_profile_picture_path($profile_upload_dir, $profile_upload_web_dir, $admin_id);

$total_users = 0;
$total_users_result = mysqli_query($con, "SELECT COUNT(*) AS total FROM users");
if ($total_users_result) {
    $total_users = (int) (mysqli_fetch_assoc($total_users_result)['total'] ?? 0);
}

$total_grounds = 0;
$total_grounds_result = mysqli_query($con, "SELECT COUNT(*) AS total FROM grounds");
if ($total_grounds_result) {
    $total_grounds = (int) (mysqli_fetch_assoc($total_grounds_result)['total'] ?? 0);
}

$total_feedback = 0;
$total_feedback_result = mysqli_query($con, "SELECT COUNT(*) AS total FROM feedback");
if ($total_feedback_result) {
    $total_feedback = (int) (mysqli_fetch_assoc($total_feedback_result)['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; }
        .admin-wrapper { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; }
        .top-navbar {
            background: #fff; padding: 12px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;
        }
        .page-title h2 { font-size: 22px; font-weight: 600; color: #333; margin: 0; }
        .page-title p { font-size: 13px; color: #6c757d; margin: 0; }
        .content-container { padding: 30px; }
        .profile-card { border: none; border-radius: 18px; box-shadow: 0 8px 25px rgba(0,0,0,0.06); }
        .profile-head {
            background: linear-gradient(120deg, #1a2b3c, #0d6efd);
            color: #fff; border-radius: 18px 18px 0 0; padding: 24px;
        }
        .profile-avatar {
            width: 90px; height: 90px; border-radius: 50%; object-fit: cover;
            border: 3px solid rgba(255,255,255,0.65); background: rgba(255,255,255,0.2);
        }
        .avatar-placeholder {
            width: 90px; height: 90px; border-radius: 50%; display: inline-flex;
            align-items: center; justify-content: center; font-size: 34px;
            border: 3px solid rgba(255,255,255,0.65); background: rgba(255,255,255,0.2);
        }
        .stat-box { border-radius: 14px; padding: 16px; background: #f8f9fa; height: 100%; }
        .stat-box h5 { margin: 0; font-weight: 700; }
        .label-muted { color: #6c757d; font-size: 13px; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .content-container { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <?php include 'nevbar.php'; ?>

    <div class="main-content">
        <div class="top-navbar">
            <div class="page-title">
                <h2>Admin Profile</h2>
                <p>Manage profile details, photo, and password</p>
            </div>
        </div>

        <div class="content-container">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card profile-card mb-4">
                <div class="profile-head">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($profile_picture_path !== ''): ?>
                                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="Profile Picture" class="profile-avatar">
                            <?php else: ?>
                                <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>
                            <div>
                                <h3 class="mb-1"><?php echo htmlspecialchars($admin_user['fullname']); ?></h3>
                                <div class="small">Administrator account settings</div>
                            </div>
                        </div>
                        <div>
                            <a href="home.php" class="btn btn-light btn-sm"><i class="bi bi-house"></i> Dashboard</a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-box">
                                <div class="label-muted">Total Users</div>
                                <h5><?php echo $total_users; ?></h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box">
                                <div class="label-muted">Total Grounds</div>
                                <h5><?php echo $total_grounds; ?></h5>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box">
                                <div class="label-muted">Total Feedback</div>
                                <h5><?php echo $total_feedback; ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="card profile-card">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><i class="bi bi-person-lines-fill me-2"></i>Profile Details</h5>
                            <form method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" id="fullname" name="fullname" class="form-control" value="<?php echo htmlspecialchars($admin_user['fullname']); ?>" data-validation="required alphabetic">
                                    <span id="fullname_error" class="text-danger"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin_user['email']); ?>" data-validation="required email">
                                    <span id="email_error" class="text-danger"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin_user['phone'] ?? ''); ?>" data-validation="required alphabetic">
                                    <span id="phone_error" class="text-danger"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Profile Picture</label>
                                    <input type="file" id="profile_picture" name="profile_picture" class="form-control" accept=".jpg,.jpeg,.png,.gif,.webp,image/*" data-validation="required">
                                    <div class="form-text">Upload jpg, jpeg, png, gif, or webp image (max 2MB).</div>
                                    <span id="profile_picture_error" class="text-danger"></span>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Role</label>
                                        <input type="text" id="role" name="role" class="form-control" value="<?php echo htmlspecialchars($admin_user['role']); ?>" data-validation="required">
                                        <span id="role_error" class="text-danger"></span>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Joined Date</label>
                                        <input type="text" id="joined_date" name="joined_date" class="form-control" value="<?php echo htmlspecialchars(date('d M Y', strtotime($admin_user['created_at']))); ?>" data-validation="required">
                                        <span id="joined_date_error" class="text-danger"></span>
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Save Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card profile-card">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control" data-validation="required alphabetic minlength:4">
                                    <span id="current_password_error" class="text-danger"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" data-validation="required alphabetic minlength:4">
                                    <span id="new_password_error" class="text-danger"></span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" data-validation="required minlength[4] match[new_password]">
                                    <span id="confirm_password_error" class="text-danger"></span>

                                </div>
                                <button type="submit" name="change_password" class="btn btn-success">
                                    <i class="bi bi-key"></i> Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

   <!-- JQUERY FOR VALIDATION -->
    <script src="../javascript/jquery-4.0.0.js"></script>
    <script src="../javascript/validation.js"></script>
</body>
</html>
