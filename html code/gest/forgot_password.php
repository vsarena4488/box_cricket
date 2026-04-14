<?php
session_start();
include_once 'db_config.php';

// Clear any existing session variables
unset($_SESSION['reset_email']);
unset($_SESSION['reset_attempts']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($con, trim($_POST['email']));
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } else {
        // Check if email exists in database
        $query = "SELECT id, fullname, email, role FROM users WHERE email = '$email'";
        $result = mysqli_query($con, $query);
        
        if (mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Check daily attempt limit (max 3 attempts per day)
            $today = date('Y-m-d');
            $attempt_query = "SELECT COUNT(*) as attempts FROM password_reset_logs 
                             WHERE email = '$email' AND DATE(created_at) = '$today'";
            $attempt_result = mysqli_query($con, $attempt_query);
            $attempt_data = mysqli_fetch_assoc($attempt_result);
            
            if ($attempt_data['attempts'] >= 3) {
                $error = "You have exceeded the maximum number of reset attempts for today. Please try again tomorrow.";
            } else {
                // Generate 6-digit OTP
                $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));
                
                // Save OTP to database
                $update_query = "UPDATE users SET reset_otp = '$otp', reset_otp_expiry = '$expiry', 
                                reset_attempts = reset_attempts + 1, last_reset_attempt = NOW() 
                                WHERE id = {$user['id']}";
                mysqli_query($con, $update_query);
                
                // Log the attempt
                $ip = getClientIP();
                $user_agent = mysqli_real_escape_string($con, $_SERVER['HTTP_USER_AGENT']);
                $log_query = "INSERT INTO password_reset_logs (user_id, email, otp, ip_address, user_agent, status) 
                             VALUES ({$user['id']}, '$email', '$otp', '$ip', '$user_agent', 'sent')";
                mysqli_query($con, $log_query);
                
                // Send OTP via email using PHPMailer
                require_once 'mail_config.php';
                try {
                    $mailer = new MailConfig();
                    $result = $mailer->sendOTP($email, $user['fullname'], $otp);
                } catch (Exception $e) {
                    $result = ['success' => false, 'message' => $e->getMessage()];
                }
                
                if ($result['success']) {
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user['id'];
                    header("Location: verify_otp.php");
                    exit();
                } else {
                    $error = "Failed to send OTP. Please try again. Error: " . $result['message'];
                }
            }
        } else {
            $error = "Email address not found in our records.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Box Cricket</title>
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
        .forgot-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 48px;
            color: #0d6efd;
        }
        .logo h3 {
            font-weight: 600;
            margin-top: 10px;
            color: #333;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="forgot-card">
        <div class="logo">
            <i class="bi bi-trophy-fill"></i>
            <h3>Box Cricket</h3>
            <p class="text-muted">Forgot Password?</p>
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
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" class="form-control" name="email" placeholder="Enter your registered email" required>
                </div>
                <small class="text-muted">We'll send a 6-digit OTP to verify your identity.</small>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-send me-2"></i>Send OTP
            </button>
        </form>
        
        <div class="back-link">
            <a href="login.php" class="text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
