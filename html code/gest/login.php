<?php
session_start();
include_once 'db_config.php';

// Redirect if already logged in
if (is_admin_logged_in()) {
    redirect_to('../admin/home.php');
} elseif (is_user_logged_in()) {
    redirect_to('../user_dashbord/home.php');
}

$error_message = '';
$success_message = '';

// Check for password reset success message
if (isset($_GET['reset']) && $_GET['reset'] == 'success') {
    $success_message = "Password reset successfully! Please login with your new password.";
}

// Handle login form submission
if (isset($_POST['login_btn'])) {
    $fullname = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullname === '' || $email === '' || $password === '') {
        $error_message = "Please enter name, email, and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM `users` WHERE email = ? LIMIT 1");

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($user_data) {
                if (strcasecmp(trim($user_data['fullname'] ?? ''), $fullname) !== 0) {
                    $error_message = "Name does not match this email address.";
                } elseif ($password === $user_data['password']) {
                    if (strtolower($user_data['status']) === 'active') {
                        $_SESSION['user_id'] = $user_data['id'];
                        $_SESSION['role'] = strtolower($user_data['role']);

                        if ($_SESSION['role'] === 'admin') {
                            $_SESSION['admin'] = $user_data['email'];
                            $_SESSION['admin_name'] = $user_data['fullname'];
                            unset($_SESSION['user'], $_SESSION['user_name']);
                            redirect_to('../admin/home.php');
                        }

                        $_SESSION['user'] = $user_data['email'];
                        $_SESSION['user_name'] = $user_data['fullname'];
                        unset($_SESSION['admin'], $_SESSION['admin_name']);
                        redirect_to('../user_dashbord/home.php');
                    } else {
                        $error_message = "Your account is " . $user_data['status'] . ". Please contact administrator.";
                    }
                } else {
                    $error_message = "Incorrect password!";
                }
            } else {
                $error_message = "Email not found! Please register first.";
            }
        } else {
            $error_message = "Unable to process your login right now. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            background-color: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: Arial, sans-serif;
        }

        .login-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 0;
        }

        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .cricket-icon {
            color: #0d6efd;
            font-size: 40px;
        }

        main {
            flex: 1;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .forgot-link {
            color: #0d6efd;
            text-decoration: none;
            font-size: 14px;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <?php include 'nevbar.php'; ?>

    <main>
        <div class="login-container">
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-5">

                        <!-- Login Card -->
                        <div class="card login-card">
                            <div class="card-body p-4">

                                <!-- Box Cricket Logo -->
                                <div class="text-center mb-4">
                                    <i class="bi bi-trophy-fill cricket-icon"></i>
                                    <h4 class="mt-2">Box Cricket</h4>
                                    <p class="text-muted">Sign in to your account</p>
                                </div>

                                <!-- Display Success Message (Password Reset Success) -->
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="bi bi-check-circle-fill me-2"></i>
                                        <?php echo htmlspecialchars($success_message); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- Display Error Message -->
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <?php echo htmlspecialchars($error_message); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- Login Form -->
                                <form id="form" action="" method="post">

                                    <!-- Name Field -->
                                    <div class="mb-3">
                                        <label for="name" class="form-label fw-bold">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               placeholder="Enter your full name" 
                                               data-validation="required alphabetic min" data-min="3" 
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                               required>
                                        <span id="name_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Email Field -->
                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-bold">Email address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="name@example.com" 
                                               data-validation="required email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                               required>
                                        <span id="email_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Password Field with Toggle -->
                                    <div class="mb-3">
                                        <label for="password" class="form-label fw-bold">Password</label>
                                        <div class="password-field">
                                            <input type="password" class="form-control" id="password" name="password" 
                                                   placeholder="Enter password" 
                                                   data-validation="required min" data-min="4"
                                                   required>
                                            <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
                                        </div>
                                        <span id="password_error" class="invalid-feedback"></span>
                                    </div>

                                    <!-- Remember Me & Forgot Password -->
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                            <label class="form-check-label" for="remember">Remember me</label>
                                        </div>
                                        <a href="forgot_password.php" class="forgot-link">
                                            <i class="bi bi-question-circle"></i> Forgot password?
                                        </a>
                                    </div>

                                    <!-- Sign In Button -->
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="login_btn" class="btn btn-primary py-2">
                                            <i class="bi bi-box-arrow-in-right"></i> Log In
                                        </button>
                                    </div>

                                    <!-- Register Link -->
                                    <div class="mt-4 text-center">
                                        <span class="text-muted">Don't have an account? </span>
                                        <a href="register.php" class="fw-bold text-decoration-none">Register here</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JQUERY FOR VALIDATION -->
    <script src="../javascript/jquery-4.0.0.js"></script>
    <script src="../javascript/validation.js"></script>

    <script>
    // Password visibility toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const password = document.getElementById('password');
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });
    
    // Client-side validation
    $(document).ready(function() {
        $('#form').on('submit', function(e) {
            let isValid = true;
            const name = $('#name').val().trim();
            const email = $('#email').val().trim();
            const password = $('#password').val().trim();
            
            // Clear previous errors
            $('.invalid-feedback').hide();
            $('.form-control').removeClass('is-invalid');
            
            // Validate name
            if (name === '') {
                $('#name_error').text('Name is required').show();
                $('#name').addClass('is-invalid');
                isValid = false;
            } else if (name.length < 3) {
                $('#name_error').text('Name must be at least 3 characters').show();
                $('#name').addClass('is-invalid');
                isValid = false;
            }
            
            // Validate email
            if (email === '') {
                $('#email_error').text('Email is required').show();
                $('#email').addClass('is-invalid');
                isValid = false;
            } else if (!isValidEmail(email)) {
                $('#email_error').text('Please enter a valid email address').show();
                $('#email').addClass('is-invalid');
                isValid = false;
            }
            
            // Validate password
            if (password === '') {
                $('#password_error').text('Password is required').show();
                $('#password').addClass('is-invalid');
                isValid = false;
            } else if (password.length < 4) {
                $('#password_error').text('Password must be at least 4 characters').show();
                $('#password').addClass('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    });
    </script>

</body>

</html>

<?php include 'footer.php'; ?>