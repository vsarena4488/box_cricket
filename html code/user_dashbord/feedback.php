<?php
include_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user'] ?? '';

if ($user_id <= 0) {
    header("Location: ../gest/login.php");
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['submit_feedback'])) {
    // Get and sanitize input
    $name = mysqli_real_escape_string($con, trim($_POST['name']));
    $email = mysqli_real_escape_string($con, trim($_POST['email']));
    $phone = !empty($_POST['phone']) ? mysqli_real_escape_string($con, trim($_POST['phone'])) : null;
    $rating = (int)$_POST['rating'];
    $feedback_type = mysqli_real_escape_string($con, $_POST['feedback_type']);
    $message = mysqli_real_escape_string($con, trim($_POST['message']));
    $recommend = mysqli_real_escape_string($con, $_POST['recommend']);
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if ($rating < 1 || $rating > 5) {
        $errors[] = "Please select a rating";
    }
    if (empty($feedback_type)) {
        $errors[] = "Please select feedback type";
    }
    if (empty($message)) {
        $errors[] = "Feedback message is required";
    }
    if (empty($recommend)) {
        $errors[] = "Please tell us if you would recommend us";
    }
    
    if (empty($errors)) {
        // Insert feedback into database
        $insert_query = "INSERT INTO feedback (user_id, name, email, phone, rating, feedback_type, message, recommend, status) 
                         VALUES ($user_id, '$name', '$email', " . ($phone ? "'$phone'" : "NULL") . ", $rating, '$feedback_type', '$message', '$recommend', 'pending')";
        
        if (mysqli_query($con, $insert_query)) {
            $feedback_id = mysqli_insert_id($con);
            $success_message = "Thank you for your valuable feedback! We appreciate your time and will review it shortly.";
            
            // Clear form data
            $_POST = array();
        } else {
            $error_message = "Sorry, there was an error submitting your feedback. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Feedback Form</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .rating-stars {
            display: flex;
            gap: 10px;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating-stars input {
            display: none;
        }
        .rating-stars label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .rating-stars label:hover,
        .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label {
            color: #ffc107;
        }
        .feedback-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .feedback-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body class="bg-light">

<!-- Main Content -->
<div class="container my-5 pt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            
            <!-- Feedback Form Card -->
            <div class="card feedback-card shadow-lg border-0">
                <div class="feedback-header text-white text-center py-4">
                    <h3 class="mb-0">
                        <i class="bi bi-chat-dots-fill me-2"></i>We Value Your Feedback
                    </h3>
                    <p class="mb-0 mt-2 text-white-50">Your feedback helps us serve you better</p>
                </div>
                
                <div class="card-body p-4 p-lg-5">
                    
                    <!-- Success Message -->
                    <?php if($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Error Message -->
                    <?php if($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Feedback Form -->
                    <form method="POST" action="" id="feedbackForm">
                        
                        <!-- Full Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold">
                                <i class="bi bi-person text-primary me-1"></i>Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="Enter your name" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($user_name); ?>"
                                   required>
                        </div>

                        <!-- Email Address -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">
                                <i class="bi bi-envelope text-primary me-1"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="name@example.com" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user_email); ?>"
                                   required>
                        </div>

                        <!-- Phone Number (Optional) -->
                        <div class="mb-3">
                            <label for="phone" class="form-label fw-semibold">
                                <i class="bi bi-telephone text-primary me-1"></i>Phone Number 
                                <span class="text-secondary">(Optional)</span>
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="Enter your phone number"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>

                        <!-- Rating / Experience -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-star-fill text-primary me-1"></i>How was your experience? <span class="text-danger">*</span>
                            </label>
                            <div class="rating-stars">
                                <input type="radio" name="rating" value="5" id="star5" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'checked' : ''; ?>>
                                <label for="star5"><i class="bi bi-star-fill"></i></label>
                                <input type="radio" name="rating" value="4" id="star4" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'checked' : ''; ?>>
                                <label for="star4"><i class="bi bi-star-fill"></i></label>
                                <input type="radio" name="rating" value="3" id="star3" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'checked' : ''; ?>>
                                <label for="star3"><i class="bi bi-star-fill"></i></label>
                                <input type="radio" name="rating" value="2" id="star2" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'checked' : ''; ?>>
                                <label for="star2"><i class="bi bi-star-fill"></i></label>
                                <input type="radio" name="rating" value="1" id="star1" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'checked' : ''; ?>>
                                <label for="star1"><i class="bi bi-star-fill"></i></label>
                            </div>
                        </div>

                        <!-- Feedback Type -->
                        <div class="mb-3">
                            <label for="feedback_type" class="form-label fw-semibold">
                                <i class="bi bi-tag text-primary me-1"></i>Feedback Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="feedback_type" name="feedback_type" required>
                                <option value="" disabled <?php echo empty($_POST['feedback_type']) ? 'selected' : ''; ?>>Select feedback type</option>
                                <option value="suggestion" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'suggestion') ? 'selected' : ''; ?>>💡 Suggestion</option>
                                <option value="complaint" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'complaint') ? 'selected' : ''; ?>>⚠️ Complaint</option>
                                <option value="praise" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'praise') ? 'selected' : ''; ?>>🌟 Praise / Appreciation</option>
                                <option value="issue" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'issue') ? 'selected' : ''; ?>>🔧 Technical Issue</option>
                                <option value="other" <?php echo (isset($_POST['feedback_type']) && $_POST['feedback_type'] == 'other') ? 'selected' : ''; ?>>📝 Other</option>
                            </select>
                        </div>

                        <!-- Feedback Message -->
                        <div class="mb-3">
                            <label for="message" class="form-label fw-semibold">
                                <i class="bi bi-pencil text-primary me-1"></i>Your Feedback <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="message" name="message" rows="5" 
                                      placeholder="Please share your experience, suggestions, or concerns..." 
                                      required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>

                        <!-- Would Recommend? -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-hand-thumbs-up text-primary me-1"></i>Would you recommend us? <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recommend" id="recommendYes" value="yes" 
                                           <?php echo (isset($_POST['recommend']) && $_POST['recommend'] == 'yes') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label" for="recommendYes">
                                        <i class="bi bi-check-circle text-success"></i> Yes, definitely!
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="recommend" id="recommendNo" value="no"
                                           <?php echo (isset($_POST['recommend']) && $_POST['recommend'] == 'no') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="recommendNo">
                                        <i class="bi bi-x-circle text-danger"></i> No, not really
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" name="submit_feedback" class="btn btn-primary btn-lg py-2">
                                <i class="bi bi-send me-2"></i>Submit Feedback
                            </button>
                        </div>

                        <!-- Note -->
                        <p class="text-center text-secondary small mt-3 mb-0">
                            <i class="bi bi-shield-check"></i> Your feedback helps us improve our service
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Client-side validation
    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        const rating = document.querySelector('input[name="rating"]:checked');
        if (!rating) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Rating Required',
                text: 'Please select a rating for your experience.',
                confirmButtonColor: '#667eea'
            });
            return false;
        }
        return true;
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>

</body>
</html>

<?php include 'footer.php'; ?>
