<?php
session_start();
include_once 'db_config.php';

// Check if email is set in session
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_password.php");
    exit();
}

$error = '';
$email = $_SESSION['reset_email'];
$user_id = $_SESSION['reset_user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = trim($_POST['otp']);
    
    if (empty($otp)) {
        $error = "Please enter the OTP";
    } else {
        // Verify OTP
        $query = "SELECT id, reset_otp, reset_otp_expiry FROM users 
                  WHERE id = $user_id AND email = '$email'";
        $result = mysqli_query($con, $query);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && $user['reset_otp'] == $otp) {
            // Check if OTP is expired
            $expiry = strtotime($user['reset_otp_expiry']);
            $now = time();
            
            if ($now > $expiry) {
                $error = "OTP has expired. Please request a new one.";
                // Clear the expired OTP
                mysqli_query($con, "UPDATE users SET reset_otp = NULL, reset_otp_expiry = NULL WHERE id = $user_id");
            } else {
                // Update log status
                mysqli_query($con, "UPDATE password_reset_logs SET status = 'verified' 
                                   WHERE user_id = $user_id AND otp = '$otp' AND status = 'sent'");
                
                // Clear OTP and redirect to reset password
                mysqli_query($con, "UPDATE users SET reset_otp = NULL, reset_otp_expiry = NULL WHERE id = $user_id");
                $_SESSION['reset_verified'] = true;
                header("Location: reset_password.php");
                exit();
            }
        } else {
            $error = "Invalid OTP. Please try again.";
            // Log failed attempt
            mysqli_query($con, "INSERT INTO password_reset_logs (user_id, email, otp, status) 
                               VALUES ($user_id, '$email', '$otp', 'failed')");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Box Cricket</title>
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
        .verify-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .timer {
            font-size: 14px;
            color: #dc3545;
            text-align: center;
            margin-top: 15px;
        }
        .otp-input {
            font-size: 24px;
            letter-spacing: 10px;
            text-align: center;
            font-weight: bold;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .resend-link {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock fs-1 text-primary"></i>
            <h3 class="mt-2">Verify OTP</h3>
            <p class="text-muted">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars($email); ?></strong></p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <label class="form-label">OTP Code</label>
                <input type="text" class="form-control otp-input" name="otp" maxlength="6" 
                       placeholder="------" required pattern="[0-9]{6}" autocomplete="off">
                <div class="timer" id="timer">OTP expires in: <span id="countdown">02:00</span></div>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-circle me-2"></i>Verify OTP
            </button>
        </form>
        
        <div class="resend-link">
            <a href="forgot_password.php" class="text-decoration-none">
                <i class="bi bi-arrow-repeat"></i> Request New OTP
            </a>
        </div>
    </div>
    
    <script>
        // Countdown timer for OTP expiry (2 minutes)
        let timeLeft = 120; // 2 minutes in seconds
        
        function updateTimer() {
            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            document.getElementById('countdown').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                document.getElementById('timer').innerHTML = '<span class="text-danger">OTP has expired. Please request a new one.</span>';
                document.querySelector('button[type="submit"]').disabled = true;
            }
            timeLeft--;
        }
        
        const timerInterval = setInterval(updateTimer, 1000);
        
        // Auto-format OTP input
        document.querySelector('input[name="otp"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
