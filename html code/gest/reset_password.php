<?php
session_start();
include_once 'db_config.php';

// Check if user is verified
if (!isset($_SESSION['reset_verified']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$success = '';
$user_id = $_SESSION['reset_user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Update password in database
        $update_query = "UPDATE users SET password = '$password' WHERE id = $user_id";
        
        if (mysqli_query($con, $update_query)) {
            // Clear reset attempts
            mysqli_query($con, "UPDATE users SET reset_attempts = 0 WHERE id = $user_id");
            
            // Clear session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_verified']);
            
            $success = "Password changed successfully! Redirecting to login...";
            header("refresh:2;url=login.php");
        } else {
            $error = "Failed to update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Box Cricket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .password-requirements {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="text-center mb-4">
            <i class="bi bi-key fs-1 text-primary"></i>
            <h3 class="mt-2">Reset Password</h3>
            <p class="text-muted">Create a new password for your account</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" name="password" id="password" 
                           placeholder="Enter new password" required minlength="6">
                </div>
                <div class="password-requirements">
                    <i class="bi bi-info-circle"></i> Password must be at least 6 characters
                </div>
            </div>
            
            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" 
                           placeholder="Confirm new password" required>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-circle me-2"></i>Reset Password
            </button>
        </form>
        
        <div class="text-center mt-3">
            <a href="login.php" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
    
    <script>
        // Real-time password match validation
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        
        function validatePassword() {
            if (password.value !== confirm.value) {
                confirm.setCustomValidity("Passwords do not match");
            } else {
                confirm.setCustomValidity('');
            }
        }
        
        password.addEventListener('change', validatePassword);
        confirm.addEventListener('keyup', validatePassword);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
