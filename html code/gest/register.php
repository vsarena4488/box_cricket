<?php
include 'db_config.php';

if (is_admin_logged_in()) {
    redirect_to('../admin/home.php');
} elseif (is_user_logged_in()) {
    redirect_to('../user_dashbord/home.php');
}

$error_message = '';
$success_message = '';

if (isset($_POST['register_btn'])) {
    $fullname = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($fullname === '' || $email === '' || $phone === '' || $password === '' || $confirm_password === '') {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
        $error_message = 'Please enter a valid phone number using digits only.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM `users` WHERE email = ? LIMIT 1");

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "s", $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            $email_exists = mysqli_stmt_num_rows($check_stmt) > 0;
            mysqli_stmt_close($check_stmt);

            if ($email_exists) {
                $error_message = 'An account with this email already exists.';
            } else {
                $insert_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO `users` (`fullname`, `email`, `password`, `phone`, `role`, `status`) VALUES (?, ?, ?, ?, 'user', 'active')"
                );

                if ($insert_stmt) {
                    mysqli_stmt_bind_param($insert_stmt, "ssss", $fullname, $email, $password, $phone);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        $success_message = 'Registration successful. You can now log in.';
                        $_POST = [];
                    } else {
                        $error_message = 'Unable to create your account right now. Please try again.';
                    }

                    mysqli_stmt_close($insert_stmt);
                } else {
                    $error_message = 'Unable to prepare registration right now. Please try again.';
                }
            }
        } else {
            $error_message = 'Unable to validate your registration right now. Please try again.';
        }
    }
}
?>
<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Register</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .register-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 0;
        }
        .register-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        /* Fix for body height when navbar and footer are included */
        main {
            flex: 1;
        }
    </style>
</head>
<body>

    <main>
        <div class="register-container mt-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-5">
                        
                        <!-- Register Card -->
                        <div class="card register-card">
                            <div class="card-body p-4">
                                
                                <!-- Header with Icon -->
                                <div class="text-center mb-4">  <!-- Changed mt-5 to mb-4 -->
                                    <i class="bi bi-trophy-fill text-primary" style="font-size: 40px;"></i>
                                    <h3 class="mt-2">Create Account</h3>
                                    <p class="text-muted small">Join Box Cricket today</p>
                                </div>

                                <?php if ($error_message !== ''): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?php echo h($error_message); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($success_message !== ''): ?>
                                    <div class="alert alert-success" role="alert">
                                        <?php echo h($success_message); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Register Form -->
                                <form id="form" action="" method="POST">
                                    <!-- Full Name -->
                                    <div class="mb-3">
                                        <label for="name" 
                                        class="form-label fw-bold">Full Name</label>
                                        <input type="text" 
                                        class="form-control" 
                                        id="name" 
                                        name="name" 
                                        placeholder="Enter your full name" 
                                        data-validation="required alphabetic min" data-min="3"
                                        value="<?php echo h($_POST['name'] ?? ''); ?>">
                                        <span id="name_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Email -->
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-bold">Email address</label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" data-validation="required email" value="<?php echo h($_POST['email'] ?? ''); ?>">
                                        <span id="email_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Phone Number (Optional) -->
                                    <div class="mb-3">
                                        <label for="phone" class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter your phone number" data-validation="required number min max" data-min="10" data-max="15" value="<?php echo h($_POST['phone'] ?? ''); ?>">
                                        <span id="phone_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Password -->
                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-bold">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" placeholder="Create password" data-validation="required min" data-min="4">
                                        <span id="password_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Confirm Password -->
                                    <div class="mb-3">
                                        <label for="confirm-password" class="form-label fw-bold">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm-password" name="confirm_password" placeholder="Re-enter password" data-validation="required min confirmPassword" data-min="4" data-compare="password">
                                        <span id="confirm_password_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Terms and Conditions -->
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="checkbox" name="checkbox" data-validation="required">
                                        <label class="form-check-label small" for="checkbox">
                                            I agree to the <a href="#" class="text-decoration-none">Terms & Conditions</a> and <a href="#" class="text-decoration-none">Privacy Policy</a>
                                        </label>
                                        <span id="checkbox_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Register Button -->
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="register_btn" class="btn btn-primary py-2">Create Account</button>
                                    </div>

                                    <!-- Login Link -->
                                    <div class="mt-3 text-center">
                                        <small class="text-muted">Already have an account? </small>
                                        <a href="login.php" class="small text-decoration-none fw-bold">Login here</a>  <!-- Fixed spelling -->
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JQUERY FOR VALIDATION -->
    <script src="../javascript/jquery-4.0.0.js"></script>    
    <script src="../javascript/validation.js"></script>

</body>
</html>

<?php include 'footer.php'; ?>
