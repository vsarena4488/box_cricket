<?php
include_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$user_name = trim($_SESSION['user_name'] ?? 'User');
$user_email = trim($_SESSION['user'] ?? '');

if ($user_id <= 0) {
    header("Location: ../gest/login.php");
    exit();
}
$ground_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['ground_id']) ? (int)$_GET['ground_id'] : 0);

// Allowed time slots
$allowed_slots = [
    '06:00:00' => '6:00 AM - 7:00 AM',
    '07:30:00' => '7:30 AM - 8:30 AM',
    '17:00:00' => '5:00 PM - 6:00 PM',
    '18:30:00' => '6:30 PM - 7:30 PM',
    '20:00:00' => '8:00 PM - 9:00 PM',
];
$allowed_durations = [1, 2, 3];

// Check if ground exists
if ($ground_id <= 0) {
    $first_ground_result = mysqli_query($con, "SELECT id FROM grounds WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    if ($first_ground_result && ($first_ground = mysqli_fetch_assoc($first_ground_result))) {
        header("Location: booking_form.php?id=" . (int)$first_ground['id']);
        exit();
    }
    die('No active ground found.');
}

// Fetch ground details
$ground_stmt = mysqli_prepare($con, "SELECT * FROM grounds WHERE id = ? AND status = 'active' LIMIT 1");
mysqli_stmt_bind_param($ground_stmt, "i", $ground_id);
mysqli_stmt_execute($ground_stmt);
$ground_result = mysqli_stmt_get_result($ground_stmt);
$ground = $ground_result ? mysqli_fetch_assoc($ground_result) : null;
mysqli_stmt_close($ground_stmt);

if (!$ground) {
    die('Ground not found.');
}

// Fetch ground images
$images = [];
$images_stmt = mysqli_prepare($con, "SELECT image_url FROM ground_images WHERE ground_id = ? ORDER BY is_primary DESC, id ASC LIMIT 4");
mysqli_stmt_bind_param($images_stmt, "i", $ground_id);
mysqli_stmt_execute($images_stmt);
$images_result = mysqli_stmt_get_result($images_stmt);
if ($images_result) {
    while ($image_row = mysqli_fetch_assoc($images_result)) {
        $images[] = $image_row['image_url'];
    }
}
mysqli_stmt_close($images_stmt);

if (empty($images)) {
    $images[] = $ground['image_url'];
}

// Review form state/messages
$review_success_message = (isset($_GET['review']) && $_GET['review'] === 'success')
    ? 'Your review has been submitted successfully.'
    : '';
$review_error_message = '';
$review_rating = isset($_POST['review_rating']) ? (int) $_POST['review_rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $review_errors = [];

    if ($user_id <= 0) {
        $review_errors[] = 'Please log in again to submit your review.';
    }
    if ($review_rating < 1 || $review_rating > 5) {
        $review_errors[] = 'Please select a valid rating between 1 and 5.';
    }
    if ($review_text === '' || strlen($review_text) < 5) {
        $review_errors[] = 'Please write at least 5 characters in your review.';
    }

    if (empty($review_errors)) {
        $insert_review_stmt = mysqli_prepare(
            $con,
            "INSERT INTO ground_reviews (user_id, ground_id, rating, review_text) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($insert_review_stmt, "iiis", $user_id, $ground_id, $review_rating, $review_text);

        if (mysqli_stmt_execute($insert_review_stmt)) {
            // Keep ground summary in sync.
            mysqli_query(
                $con,
                "UPDATE grounds g
                 SET
                    g.rating = (
                        SELECT ROUND(AVG(gr.rating), 1)
                        FROM ground_reviews gr
                        WHERE gr.ground_id = g.id
                    ),
                    g.total_reviews = (
                        SELECT COUNT(*)
                        FROM ground_reviews gr
                        WHERE gr.ground_id = g.id
                    )
                 WHERE g.id = " . (int) $ground_id
            );

            mysqli_stmt_close($insert_review_stmt);
            header("Location: booking_form.php?id=" . (int)$ground_id . "&review=success");
            exit();
        }

        $review_error_message = 'Unable to submit review right now. Please try again.';
        mysqli_stmt_close($insert_review_stmt);
    } else {
        $review_error_message = implode('<br>', $review_errors);
    }
}

// Fetch reviews
$reviews = [];
$reviews_stmt = mysqli_prepare($con, "SELECT gr.rating, gr.review_text, u.fullname FROM ground_reviews gr INNER JOIN users u ON u.id = gr.user_id WHERE gr.ground_id = ? ORDER BY gr.created_at DESC");
mysqli_stmt_bind_param($reviews_stmt, "i", $ground_id);
mysqli_stmt_execute($reviews_stmt);
$reviews_result = mysqli_stmt_get_result($reviews_stmt);
if ($reviews_result) {
    while ($review_row = mysqli_fetch_assoc($reviews_result)) {
        $reviews[] = $review_row;
    }
}
mysqli_stmt_close($reviews_stmt);

// Initialize form variables
$selected_date = isset($_POST['booking_date']) ? $_POST['booking_date'] : date('Y-m-d');
$selected_slot = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$selected_duration = isset($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : 1;
$selected_payment_method = 'qr';
$special_request = isset($_POST['special_request']) ? trim($_POST['special_request']) : '';

$error_message = '';
$success_message = '';
$price_per_hour = (float)$ground['price_per_hour'];
$total_amount = $price_per_hour * max(1, $selected_duration);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_ground'])) {
    // Validate inputs
    $errors = [];
    
    if ($user_id <= 0) {
        $errors[] = 'Please log in again to continue.';
    }
    if ($selected_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
        $errors[] = 'Please select a valid booking date.';
    }
    if (strtotime($selected_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Booking date cannot be in the past.';
    }
    if (!array_key_exists($selected_slot, $allowed_slots)) {
        $errors[] = 'Please select a valid time slot.';
    }
    if (!in_array($selected_duration, $allowed_durations, true)) {
        $errors[] = 'Please select a valid duration.';
    }
    
    if (empty($errors)) {
        $total_amount = $price_per_hour * $selected_duration;
        $requested_start_time = $selected_slot;
        $requested_end_time = date('H:i:s', strtotime($selected_slot . ' +' . $selected_duration . ' hours'));

        // Prevent cross-day bookings (for example 11 PM + 3 hours)
        if ($requested_end_time <= $requested_start_time) {
            $errors[] = 'Selected duration exceeds the allowed time range for this day.';
        }
    }

    if (empty($errors)) {
        // Block overlapping bookings on same ground and date.
        $duplicate_stmt = mysqli_prepare(
            $con,
            "SELECT id
             FROM ground_bookings
             WHERE ground_id = ?
               AND booking_date = ?
               AND booking_status IN ('pending', 'confirmed')
               AND start_time < ?
               AND ADDTIME(start_time, SEC_TO_TIME(duration_hours * 3600)) > ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($duplicate_stmt, "isss", $ground_id, $selected_date, $requested_end_time, $requested_start_time);
        mysqli_stmt_execute($duplicate_stmt);
        $duplicate_result = mysqli_stmt_get_result($duplicate_stmt);
        $existing_booking = $duplicate_result ? mysqli_fetch_assoc($duplicate_result) : null;
        mysqli_stmt_close($duplicate_stmt);

        if ($existing_booking) {
            $error_message = 'This time range is already booked. Please choose a different slot or shorter duration.';
        } else {
            // Insert booking
            $insert_stmt = mysqli_prepare($con, "INSERT INTO ground_bookings (user_id, ground_id, booking_date, start_time, duration_hours, price_per_hour, total_amount, payment_method, payment_status, booking_status, special_request) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)");
            mysqli_stmt_bind_param($insert_stmt, "iissiddss", $user_id, $ground_id, $selected_date, $selected_slot, $selected_duration, $price_per_hour, $total_amount, $selected_payment_method, $special_request);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $booking_number = mysqli_insert_id($con);
                header("Location: payment_slot.php?booking_id=" . (int)$booking_number . "&type=ground");
                exit();
            } else {
                $error_message = 'Unable to save your booking right now. Please try again.';
            }
            mysqli_stmt_close($insert_stmt);
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Parse amenities
$amenities = !empty($ground['amenities']) ? array_filter(array_map('trim', explode(',', $ground['amenities']))) : [];
?>

<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Ground - <?php echo htmlspecialchars($ground['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .carousel-section { width: 100%; margin-bottom: 30px; }
        .carousel-img { height: 500px; object-fit: cover; width: 100%; }
        .booking-form-card { border: none; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); padding: 30px; background: white; }
        .facility-badge { background: #e9ecef; padding: 8px 15px; border-radius: 30px; margin: 5px; display: inline-block; }
        .price-display { font-size: 32px; color: #28a745; font-weight: bold; }
        .detail-section { background: white; border-radius: 15px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .ground-title { margin-top: 20px; margin-bottom: 10px; }
        .alert { border-radius: 10px; }
        .payment-info { background: #e7f1ff; border-left: 4px solid #0d6efd; padding: 12px; border-radius: 8px; margin-top: 15px; }
    </style>
</head>
<body>

<div class="container my-5 pt-5">
    <!-- Image Carousel -->
    <div class="carousel-section">
        <div id="groundCarousel" class="carousel slide rounded-4 overflow-hidden" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($images as $index => $image_url): ?>
                    <button type="button" data-bs-target="#groundCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($images as $index => $image_url): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($image_url); ?>" class="d-block w-100 carousel-img" alt="Ground Image">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#groundCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#groundCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
        </div>
    </div>

    <!-- Ground Title -->
    <div class="ground-title">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="mb-1"><?php echo htmlspecialchars($ground['name']); ?></h2>
                <p class="text-muted mb-0"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ground['location']); ?></p>
            </div>
            <div class="text-end">
                <span class="price-display">₹<?php echo number_format($price_per_hour, 0); ?><span class="fs-6 text-muted">/hr</span></span><br>
                <span class="badge bg-success p-2 mt-2"><?php echo ucfirst($ground['status']); ?></span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="booking-form-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                    <h4 class="mb-0">Book Your Slot</h4>
                    <a href="slot_booking.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Grounds</a>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="booking_form.php?id=<?php echo $ground_id; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Your Name</label>
                            <input type="text" class="form-control"
                            id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_name); ?>" data-validation="required alphabetic min" data-min="3" >
                            <span id="fullname_error" class="invalid-feedback"></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" data-validation="required email">
                            <span id="email_error" class="invalid-feedback"></span>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Select Date</label>
                            <input type="date" class="form-control" name="booking_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($selected_date); ?>" data-validation="required date">
                            <span id="booking_date" class="invalid-feedback"></span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Select Time Slot</label>
                            <select class="form-select" name="start_time" id="start_time" data-validation="required" >
                                <option value="">Choose time</option>
                                <?php foreach ($allowed_slots as $slot_value => $slot_label): ?>
                                    <option value="<?php echo $slot_value; ?>" <?php echo $selected_slot === $slot_value ? 'selected' : ''; ?>><?php echo htmlspecialchars($slot_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span id="start_time" class="invalid-feedback"></span>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Duration</label>
                            <select class="form-select"  name="duration_hours" id="duration_hours" data-validation="required">
                                <?php foreach ($allowed_durations as $duration_option): ?>
                                    <option value="<?php echo $duration_option; ?>" <?php echo $selected_duration === $duration_option ? 'selected' : ''; ?>>
                                        <?php echo $duration_option; ?> Hour<?php echo $duration_option > 1 ? 's' : ''; ?> - ₹<?php echo number_format($price_per_hour * $duration_option, 0); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="duration_hours" class="invalid-feedback"></span>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Special Request</label>
                            <input type="text" class="form-control" name="special_request" id="special_request" maxlength="255" placeholder="Optional note" value="<?php echo htmlspecialchars($special_request); ?>" data-validation="alphabetic min" data-min="10">
                            <span id="special_request" class="invalid-feedback"></span>
                        </div>
                    </div>

                    <div class="alert alert-info d-flex justify-content-between align-items-center mt-3">
                        <span class="fw-bold">Total Price:</span>
                        <span class="fs-4 fw-bold">₹<?php echo number_format($price_per_hour * $selected_duration, 0); ?></span>
                    </div>

                    <button type="submit" name="book_ground" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-check-circle"></i> Confirm Booking
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Ground Details Section -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="detail-section">
                <h4><i class="bi bi-info-circle me-2"></i>Description</h4>
                <p><?php echo htmlspecialchars($ground['description']); ?></p>
            </div>
            <div class="detail-section">
                <h4><i class="bi bi-building me-2"></i>Facilities</h4>
                <div>
                    <?php if (!empty($amenities)): ?>
                        <?php foreach ($amenities as $amenity): ?>
                            <span class="facility-badge"><i class="bi bi-check-circle text-success me-1"></i> <?php echo htmlspecialchars($amenity); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Facilities will be updated soon.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="detail-section">
                <h4><i class="bi bi-grid-3x3-gap-fill me-2"></i>Ground Details</h4>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between"><span>Ground Type:</span><span class="badge bg-primary"><?php echo ucfirst($ground['ground_type']); ?></span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Capacity:</span><span><?php echo (int)$ground['capacity']; ?> players</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Rating:</span><span><?php for ($i = 1; $i <= 5; $i++) echo '<i class="bi ' . ($i <= round($ground['rating']) ? 'bi-star-fill text-warning' : 'bi-star text-warning') . '"></i>'; ?> <?php echo number_format($ground['rating'], 1); ?>/5</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>Total Reviews:</span><span><?php echo (int)$ground['total_reviews']; ?> reviews</span></li>
                </ul>
            </div>
            <div class="detail-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3" id="customer-reviews">
                    <h4 class="mb-0"><i class="bi bi-star-fill text-warning me-2"></i>Customer Reviews</h4>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                        <i class="bi bi-pencil-square me-1"></i> Write Review
                    </button>
                </div>

                <?php if ($review_success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($review_success_message); ?></div>
                <?php endif; ?>
                <?php if ($review_error_message): ?>
                    <div class="alert alert-danger"><?php echo $review_error_message; ?></div>
                <?php endif; ?>

                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="mb-3 pb-2 border-bottom">
                            <div class="mb-2"><?php for ($i = 1; $i <= 5; $i++) echo '<i class="bi ' . ($i <= $review['rating'] ? 'bi-star-fill text-warning' : 'bi-star text-warning') . '"></i>'; ?> <strong><?php echo $review['rating']; ?>.0</strong> by <?php echo htmlspecialchars($review['fullname']); ?></div>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($review['review_text']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No reviews yet. Be the first to review!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="booking_form.php?id=<?php echo $ground_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="reviewModalLabel">Write Your Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Rating</label>
                        <select class="form-select" name="review_rating" required>
                            <option value="">Select Rating</option>
                            <option value="5" <?php echo $review_rating === 5 ? 'selected' : ''; ?>>5 - Excellent</option>
                            <option value="4" <?php echo $review_rating === 4 ? 'selected' : ''; ?>>4 - Very Good</option>
                            <option value="3" <?php echo $review_rating === 3 ? 'selected' : ''; ?>>3 - Good</option>
                            <option value="2" <?php echo $review_rating === 2 ? 'selected' : ''; ?>>2 - Fair</option>
                            <option value="1" <?php echo $review_rating === 1 ? 'selected' : ''; ?>>1 - Poor</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Your Review</label>
                        <textarea class="form-control" name="review_text" rows="4" maxlength="500" placeholder="Share your experience..." required><?php echo htmlspecialchars($review_text); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="submit_review" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($review_error_message): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
        reviewModal.show();
    });
</script>
<?php endif; ?>

<!-- JQUERY FOR VALIDATION -->
    <script src="../javascript/jquery-4.0.0.js"></script>
    <script src="../javascript/validation.js"></script>
</body>
</html>

<?php include 'footer.php'; ?>
