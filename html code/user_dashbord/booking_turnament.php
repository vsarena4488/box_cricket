<?php
include_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

// Get match ID from URL
$match_id = isset($_GET['match_id']) ? (int)$_GET['match_id'] : 0;

if ($match_id == 0) {
    header("Location: match.php");
    exit();
}

// Fetch match details from database
$match_query = "SELECT * FROM matches WHERE id = $match_id AND status = 'upcoming'";
$match_result = mysqli_query($con, $match_query);

if (!$match_result || mysqli_num_rows($match_result) == 0) {
    header("Location: match.php");
    exit();
}

$match = mysqli_fetch_assoc($match_result);

// Get user details
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user'] ?? '';

// Resolve the actual users.id required by the bookings foreign key.
$resolved_user_id = 0;

if ($user_id > 0) {
    $user_check_result = mysqli_query($con, "SELECT id FROM users WHERE id = $user_id LIMIT 1");
    if ($user_check_result && mysqli_num_rows($user_check_result) > 0) {
        $resolved_user = mysqli_fetch_assoc($user_check_result);
        $resolved_user_id = (int) $resolved_user['id'];
    }
}

if ($resolved_user_id === 0 && $user_email !== '') {
    $safe_user_email = mysqli_real_escape_string($con, $user_email);
    $user_by_email_result = mysqli_query($con, "SELECT id FROM users WHERE email = '$safe_user_email' LIMIT 1");

    if ($user_by_email_result && mysqli_num_rows($user_by_email_result) > 0) {
        $resolved_user = mysqli_fetch_assoc($user_by_email_result);
        $resolved_user_id = (int) $resolved_user['id'];
    } else {
        $safe_user_name = mysqli_real_escape_string($con, $user_name);
        $generated_password = mysqli_real_escape_string($con, bin2hex(random_bytes(16)));
        $create_user_query = "INSERT INTO users (fullname, email, password, role, status) VALUES ('$safe_user_name', '$safe_user_email', '$generated_password', 'user', 'active')";

        if (mysqli_query($con, $create_user_query)) {
            $resolved_user_id = (int) mysqli_insert_id($con);
        }
    }
}

if ($resolved_user_id > 0) {
    $user_id = $resolved_user_id;
    $_SESSION['user_id'] = $resolved_user_id;
}

// Get already booked seats for this match
$booked_seats_query = "SELECT seat_number FROM bookings 
                       WHERE match_id = $match_id 
                         AND booking_status IN ('pending', 'confirmed', 'completed')";
$booked_seats_result = mysqli_query($con, $booked_seats_query);
$booked_seats = [];
if ($booked_seats_result) {
    while ($row = mysqli_fetch_assoc($booked_seats_result)) {
        if (!empty($row['seat_number'])) {
            $booked_seats[] = $row['seat_number'];
        }
    }
}

// Calculate price details
$seat_price = $match['price'];
$gst = round($seat_price * 0.18);
$total_amount = $seat_price + $gst;
$upi_id = 'vishalsarena44@oksbi';
$upi_name = 'Vishal Arena';
$upi_link = 'upi://pay?pa=' . rawurlencode($upi_id) . '&pn=' . rawurlencode($upi_name) . '&am=' . rawurlencode((string) $total_amount) . '&cu=INR';
$upi_qr_url = 'https://quickchart.io/qr?text=' . rawurlencode($upi_link) . '&size=220';
$razorpay_key_id = 'rzp_test_SdMwGxkNmJiHyH';
$razorpay_key_secret = '8y0ArVHgIidoSbM8YnnGguHI';
$razorpay_amount_paise = (int) round(((float) $total_amount) * 100);

// Handle booking submission
$booking_message = '';
$booking_error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['book_seat'])) {
    $selected_seat = isset($_POST['seat_number']) ? trim($_POST['seat_number']) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'qr';
    $transaction_id = isset($_POST['transaction_id']) ? trim($_POST['transaction_id']) : '';
    $terms_accepted = isset($_POST['terms']) ? 1 : 0;
    $allowed_payment_methods = ['qr', 'cash', 'card'];

    if (!in_array($payment_method, $allowed_payment_methods, true)) {
        $payment_method = 'qr';
    }
    
    // Validation
    if ($user_id <= 0) {
        $booking_error = "User account could not be verified. Please log in again.";
    } elseif (empty($selected_seat)) {
        $booking_error = "Please select a seat";
    } elseif (!preg_match('/^[A-F][1-8]$/', $selected_seat)) {
        $booking_error = "Invalid seat selected";
    } elseif ($payment_method === 'card' && $transaction_id === '') {
        $booking_error = "Razorpay payment is incomplete. Please complete payment to continue.";
    } elseif (!$terms_accepted) {
        $booking_error = "Please accept terms and conditions";
    } else {
        $selected_seat_db = mysqli_real_escape_string($con, $selected_seat);
        $transaction_id_db = mysqli_real_escape_string($con, $transaction_id);
    }

    if (empty($booking_error)) {
        // Atomic booking flow to prevent race conditions from parallel users.
        mysqli_begin_transaction($con);

        $transaction_ok = true;
        $payment_method_db = mysqli_real_escape_string($con, $payment_method);
        $payment_status_db = $payment_method === 'card' ? 'completed' : 'pending';
        $booking_status_db = 'confirmed';
        $booking_date = date('Y-m-d');
        $booking_time = date('H:i:s');

        // Lock match row first so slot count and seat assignment stay consistent.
        $lock_match_query = "SELECT available_slots 
                             FROM matches 
                             WHERE id = $match_id AND status = 'upcoming' 
                             FOR UPDATE";
        $lock_match_result = mysqli_query($con, $lock_match_query);

        if (!$lock_match_result || mysqli_num_rows($lock_match_result) === 0) {
            $transaction_ok = false;
            $booking_error = "Match is not available for booking.";
        }

        if ($transaction_ok) {
            $locked_match = mysqli_fetch_assoc($lock_match_result);
            $available_slots_now = (int)($locked_match['available_slots'] ?? 0);

            if ($available_slots_now <= 0) {
                $transaction_ok = false;
                $booking_error = "No slots available for this match!";
            }
        }

        if ($transaction_ok) {
            $seat_lock_query = "SELECT id FROM bookings 
                                WHERE match_id = $match_id 
                                  AND seat_number = '$selected_seat_db' 
                                  AND booking_status IN ('pending', 'confirmed', 'completed')
                                LIMIT 1
                                FOR UPDATE";
            $seat_lock_result = mysqli_query($con, $seat_lock_query);

            if (!$seat_lock_result) {
                $transaction_ok = false;
                $booking_error = "Could not validate seat availability. Please try again.";
            } elseif (mysqli_num_rows($seat_lock_result) > 0) {
                $transaction_ok = false;
                $booking_error = "This seat is already booked!";
            }
        }

        if ($transaction_ok) {
            $insert_query = "INSERT INTO bookings (user_id, match_id, seat_number, booking_date, booking_time, slots_booked, total_amount, payment_method, payment_status, booking_status) 
                             VALUES ($user_id, $match_id, '$selected_seat_db', '$booking_date', '$booking_time', 1, $total_amount, '$payment_method_db', '$payment_status_db', '$booking_status_db')";
            $insert_result = mysqli_query($con, $insert_query);

            if (!$insert_result) {
                $transaction_ok = false;
                $booking_error = "Booking failed: " . mysqli_error($con);
            }
        }

        if ($transaction_ok) {
            $update_slots_query = "UPDATE matches 
                                   SET available_slots = available_slots - 1 
                                   WHERE id = $match_id AND available_slots > 0";
            $update_slots_result = mysqli_query($con, $update_slots_query);

            if (!$update_slots_result || mysqli_affected_rows($con) !== 1) {
                $transaction_ok = false;
                $booking_error = "No slots available for this match!";
            }
        }

        if ($transaction_ok) {
            $booking_insert_id = (int) mysqli_insert_id($con);
            $payments_table_exists = false;
            $payments_table_result = mysqli_query($con, "SHOW TABLES LIKE 'payments'");
            if ($payments_table_result && mysqli_num_rows($payments_table_result) > 0) {
                $payments_table_exists = true;
            }

            if ($payments_table_exists) {
                $final_transaction_id = $transaction_id_db ?? '';
                if ($final_transaction_id === '') {
                    $final_transaction_id = 'TXN' . time() . rand(1000, 9999);
                }

                $insert_payment_query = "INSERT INTO payments (booking_id, booking_type, user_id, amount, payment_method, payment_status, transaction_id) 
                                         VALUES ($booking_insert_id, 'match', $user_id, $total_amount, '$payment_method_db', '$payment_status_db', '$final_transaction_id')";
                $insert_payment_result = mysqli_query($con, $insert_payment_query);
                if (!$insert_payment_result) {
                    $transaction_ok = false;
                    $booking_error = "Payment logging failed. Please try again.";
                }
            }
        }

        if ($transaction_ok) {
            mysqli_commit($con);
            $booking_message = $payment_method === 'card'
                ? "Payment successful! Your seat $selected_seat has been confirmed."
                : "Booking successful! Your seat $selected_seat has been confirmed.";
            $booked_seats[] = $selected_seat;

            echo "<script>
                setTimeout(function() {
                    window.location.href = 'home.php';
                }, 2000);
            </script>";
        } else {
            mysqli_rollback($con);
        }
    }
}

// Get seat layout based on match type
$seat_rows = ['A', 'B', 'C', 'D', 'E', 'F'];
$seats_per_row = 8;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Book Match Seat</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .booking-form-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .form-section h5 {
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .price-card {
            background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
        }
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 10px;
        }
        .payment-method-card:hover {
            border-color: #0d6efd;
            background-color: #f8f9ff;
        }
        .payment-method-card.selected {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .payment-method-card i {
            font-size: 24px;
            margin-right: 10px;
        }
        .upi-box {
            display: none;
            margin-top: 15px;
            border: 1px solid #dbe7ff;
            border-radius: 14px;
            background: #f8fbff;
            padding: 18px;
            text-align: center;
        }
        .upi-box.active {
            display: block;
        }
        .upi-qr-image {
            width: 220px;
            max-width: 100%;
            border-radius: 12px;
            border: 1px solid #dee2e6;
            background: #fff;
            padding: 10px;
        }
        .upi-id-badge {
            display: inline-block;
            margin-top: 12px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #e7f1ff;
            color: #0d6efd;
            font-weight: 600;
        }
        .match-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .seat-map {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        .seat {
            width: 70px;
            height: 70px;
            border: 2px solid #0d6efd;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            background: white;
        }
        .seat-code {
            font-size: 15px;
            line-height: 1;
        }
        .seat-status {
            margin-top: 4px;
            font-size: 10px;
            font-weight: 500;
            line-height: 1;
        }
        .seat.available {
            background: white;
            color: #0d6efd;
        }
        .seat.available:hover {
            background: #0d6efd;
            color: white;
            transform: scale(1.05);
        }
        .seat.selected {
            background: #0d6efd;
            color: white;
            transform: scale(1.05);
        }
        .seat.booked {
            background: #e9ecef;
            border-color: #adb5bd;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .seat.booked .seat-status {
            color: #6c757d;
        }
        .seat-info {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            justify-content: center;
        }
        .seat-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .seat-dot {
            width: 20px;
            height: 20px;
            border-radius: 5px;
        }
        .seat-dot.available { background: white; border: 2px solid #0d6efd; }
        .seat-dot.selected { background: #0d6efd; }
        .seat-dot.booked { background: #e9ecef; border: 2px solid #adb5bd; }
        
        .sticky-top {
            top: 20px;
        }
        
        .alert {
            border-radius: 12px;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<?php include 'nevbar.php'; ?>  

<div class="container my-5 pt-4">
    <div class="row mt-4">
        <!-- Main Booking Form -->
        <div class="col-lg-8">
            <div class="card booking-form-card">
                <div class="card-header bg-primary text-white py-3">
                    <h4 class="mb-0"><i class="bi bi-ticket-perforated me-2"></i>Book Your Match Seat</h4>
                </div>
                <div class="card-body p-4 mt-4">
                    
                    <!-- Display Messages -->
                    <?php if($booking_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo $booking_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($booking_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo $booking_error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="bookingForm">
                        <!-- Match Information -->
                        <div class="form-section mt-5">
                            <h5><i class="bi bi-info-circle me-2"></i>Match Information</h5>
                            <div class="match-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Match:</strong> 
                                            <?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Date:</strong> 
                                            <?php echo date('l, d F Y', strtotime($match['match_date'])); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Time:</strong> 
                                            <?php echo date('h:i A', strtotime($match['match_time'])); ?> (2 Hours)
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <strong>Ground:</strong> 
                                            <?php echo htmlspecialchars($match['venue']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Match Type:</strong> 
                                            <?php echo ucfirst($match['match_type']); ?>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Available Slots:</strong> 
                                            <span class="badge bg-success"><?php echo $match['available_slots']; ?> slots left</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seat Selection Section -->
                        <div class="form-section">
                            <h5><i class="bi bi-grid-3x3-gap-fill me-2"></i>Select Your Seat</h5>
                            
                            <!-- Seat Legend -->
                            <div class="seat-info mb-3">
                                <div class="seat-indicator">
                                    <div class="seat-dot available"></div>
                                    <span>Available</span>
                                </div>
                                <div class="seat-indicator">
                                    <div class="seat-dot selected"></div>
                                    <span>Selected</span>
                                </div>
                                <div class="seat-indicator">
                                    <div class="seat-dot booked"></div>
                                    <span>Booked</span>
                                </div>
                            </div>

                            <!-- Dynamic Seat Map -->
                            <div class="seat-map">
                                <?php 
                                $seat_number = 1;
                                for($row = 0; $row < 6; $row++): 
                                    $row_letter = chr(65 + $row); // A, B, C, D, E, F
                                ?>
                                    <?php for($seat = 1; $seat <= 8; $seat++): 
                                        $seat_name = $row_letter . $seat;
                                        $is_booked = in_array($seat_name, $booked_seats);
                                        $seat_status = $is_booked ? 'Booked' : 'Available';
                                    ?>
                                        <div class="seat <?php echo $is_booked ? 'booked' : 'available'; ?>" 
                                             data-seat="<?php echo $seat_name; ?>"
                                             data-status="<?php echo $seat_status; ?>"
                                             title="<?php echo $seat_name . ' - ' . $seat_status; ?>"
                                             onclick="<?php echo !$is_booked ? "selectSeat(this, '$seat_name')" : ''; ?>">
                                            <span class="seat-code"><?php echo $seat_name; ?></span>
                                            <span class="seat-status"><?php echo $seat_status; ?></span>
                                        </div>
                                    <?php endfor; ?>
                                <?php endfor; ?>
                            </div>
                            
                            <input type="hidden" name="seat_number" id="selectedSeatInput" value="">
                            <p class="text-secondary small mt-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Select your preferred seat. Green seats are available.
                            </p>
                        </div>

                        <!-- Payment Options Section -->
                        <div class="form-section">
                            <h5><i class="bi bi-credit-card me-2"></i>Select Payment Method</h5>
                            
                            <!-- QR Code Payment -->
                            <div class="payment-method-card" id="qrMethod" onclick="selectPayment('qr')">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-qr-code-scan text-primary"></i>
                                    <div>
                                        <h6 class="mb-0">Online Payment</h6>
                                        <small class="text-secondary">Scan with any UPI app (GPay, PhonePe, Paytm)</small>
                                    </div>
                                </div>
                            </div>

                            <div class="upi-box active" id="upiPaymentBox">
                                <img src="<?php echo htmlspecialchars($upi_qr_url); ?>" alt="UPI QR Code" class="upi-qr-image mb-3">
                                <div class="fw-semibold mb-1">Scan to pay online</div>
                                <div class="text-secondary small">Amount: Rs. <?php echo number_format($total_amount, 2); ?></div>
                                <div class="upi-id-badge">UPI ID: <?php echo htmlspecialchars($upi_id); ?></div>
                            </div>

                            <!-- Cash Payment -->
                            <div class="payment-method-card" id="cashMethod" onclick="selectPayment('cash')">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-cash-stack text-success"></i>
                                    <div>
                                        <h6 class="mb-0">Cash Payment</h6>
                                        <small class="text-secondary">Pay at the ground before match</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Razorpay Payment -->
                            <div class="payment-method-card" id="cardMethod" onclick="selectPayment('card')">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-credit-card-2-front text-primary"></i>
                                    <div>
                                        <h6 class="mb-0">Razorpay (Card/UPI/Wallet)</h6>
                                        <small class="text-secondary">Pay securely using Razorpay Checkout</small>
                                    </div>
                                </div>
                            </div>

                            <div class="upi-box" id="razorpayPaymentBox">
                                <div class="fw-semibold mb-1">Secure online payment with Razorpay</div>
                                <div class="text-secondary small">Use Card, UPI, Netbanking, or Wallet.</div>
                                <button type="button" class="btn btn-primary mt-3" onclick="startRazorpayPayment()">
                                    <i class="bi bi-lightning-charge-fill me-1"></i>Pay &#8377;<?php echo number_format($total_amount, 0); ?> with Razorpay
                                </button>
                                <div class="small mt-2 text-success d-none" id="razorpayPaidText">
                                    <i class="bi bi-check-circle-fill me-1"></i>Payment captured. You can now confirm booking.
                                </div>
                            </div>

                            <input type="hidden" name="payment_method" id="paymentMethodInput" value="qr">
                            <input type="hidden" name="transaction_id" id="razorpayTransactionId" value="">
                        </div>
                        
                        <input type="hidden" name="book_seat" value="1">
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column - Booking Summary -->
        <div class="col-lg-4">
            <div class="card booking-form-card sticky-top" style="top: 20px;">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Booking Summary</h5>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Match Details -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">Match Details:</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Match:</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Date:</span>
                            <span class="fw-semibold"><?php echo date('d M Y', strtotime($match['match_date'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Time:</span>
                            <span class="fw-semibold"><?php echo date('h:i A', strtotime($match['match_time'])); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Ground:</span>
                            <span class="fw-semibold"><?php echo htmlspecialchars($match['venue']); ?></span>
                        </div>
                    </div>

                    <!-- Seat Details -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">Seat Details:</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Selected Seat:</span>
                            <span class="fw-semibold" id="selectedSeatDisplay">Not selected</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Seat Price:</span>
                            <span class="fw-semibold">₹<?php echo number_format($seat_price, 0); ?></span>
                        </div>
                    </div>

                    <!-- Price Breakup -->
                    <div class="price-card mb-4">
                        <h6 class="fw-bold mb-3">Price Details:</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Ticket Price:</span>
                            <span>₹<?php echo number_format($seat_price, 0); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>GST (18%):</span>
                            <span>₹<?php echo number_format($gst, 0); ?></span>
                        </div>
                        <hr class="bg-white">
                        <div class="d-flex justify-content-between fw-bold">
                            <span>Total Amount:</span>
                            <span>₹<?php echo number_format($total_amount, 0); ?></span>
                        </div>
                        <div class="text-center small mt-2">
                            <i class="bi bi-check-circle"></i> Payment Method: <span id="summaryPayment">QR Code</span>
                        </div>
                    </div>

                    <!-- Terms Checkbox -->
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="terms" name="terms" value="1" form="bookingForm">
                        <label class="form-check-label small" for="terms">
                            I agree to the <a href="#">Terms & Conditions</a> and cancellation policy
                        </label>
                    </div>

                    <!-- Book Button -->
                    <button class="btn btn-primary w-100 py-3 fw-semibold" onclick="submitBooking()">
                        <i class="bi bi-check-circle me-2"></i>Confirm & Pay ₹<?php echo number_format($total_amount, 0); ?>
                    </button>

                    <p class="text-center text-secondary small mt-3">
                        <i class="bi bi-shield-check me-1"></i>
                        Free cancellation within 24 hours
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<?php include 'footer.php'; ?>

<script>
    let selectedSeatNumber = null;
    let selectedPayment = 'qr';
    let razorpayPaymentDone = false;
    const seatPrice = <?php echo $seat_price; ?>;
    const gst = <?php echo $gst; ?>;
    const total = <?php echo $total_amount; ?>;

    // Select seat
    function selectSeat(element, seatNumber) {
        if(element.classList.contains('booked')) return;
        
        // Remove selection from all seats
        document.querySelectorAll('.seat').forEach(seat => {
            seat.classList.remove('selected');
        });
        
        // Add selection to clicked seat
        element.classList.add('selected');
        selectedSeatNumber = seatNumber;
        document.getElementById('selectedSeatDisplay').innerText = seatNumber;
        document.getElementById('selectedSeatInput').value = seatNumber;
    }

    // Select payment method
    function selectPayment(method) {
        selectedPayment = method;
        
        // Reset all styles
        document.getElementById('qrMethod').classList.remove('selected');
        document.getElementById('cashMethod').classList.remove('selected');
        document.getElementById('cardMethod').classList.remove('selected');
        document.getElementById('upiPaymentBox').classList.remove('active');
        document.getElementById('razorpayPaymentBox').classList.remove('active');
        
        // Show selected
        if(method === 'qr') {
            document.getElementById('qrMethod').classList.add('selected');
            document.getElementById('summaryPayment').innerText = 'QR Code';
            document.getElementById('upiPaymentBox').classList.add('active');
            razorpayPaymentDone = false;
            document.getElementById('razorpayTransactionId').value = '';
            document.getElementById('razorpayPaidText').classList.add('d-none');
        } else if(method === 'cash') {
            document.getElementById('cashMethod').classList.add('selected');
            document.getElementById('summaryPayment').innerText = 'Cash';
            razorpayPaymentDone = false;
            document.getElementById('razorpayTransactionId').value = '';
            document.getElementById('razorpayPaidText').classList.add('d-none');
        } else {
            document.getElementById('cardMethod').classList.add('selected');
            document.getElementById('summaryPayment').innerText = 'Razorpay';
            document.getElementById('razorpayPaymentBox').classList.add('active');
        }
        
        document.getElementById('paymentMethodInput').value = method;
    }

    function startRazorpayPayment() {
        if (selectedPayment !== 'card') {
            return;
        }

        const options = {
            key: <?php echo json_encode($razorpay_key_id); ?>,
            amount: <?php echo (int) $razorpay_amount_paise; ?>,
            currency: 'INR',
            name: 'Box Cricket',
            description: <?php echo json_encode('Match Seat Booking #' . $match_id); ?>,
            handler: function(response) {
                const paymentId = response.razorpay_payment_id || '';
                document.getElementById('razorpayTransactionId').value = paymentId;
                razorpayPaymentDone = paymentId !== '';
                if (razorpayPaymentDone) {
                    document.getElementById('razorpayPaidText').classList.remove('d-none');
                }
            },
            prefill: {
                name: <?php echo json_encode($user_name); ?>,
                email: <?php echo json_encode($user_email); ?>
            },
            notes: {
                match_id: <?php echo json_encode((string) $match_id); ?>,
                seat_number: selectedSeatNumber || ''
            },
            theme: {
                color: '#0d6efd'
            }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    }

    // Submit booking
    function submitBooking() {
        // Check required fields
        if(!selectedSeatNumber) {
            Swal.fire({
                icon: 'warning',
                title: 'No Seat Selected',
                text: 'Please select a seat to continue',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }
        
        if(!document.getElementById('terms').checked) {
            Swal.fire({
                icon: 'warning',
                title: 'Terms & Conditions',
                text: 'Please agree to the terms & conditions',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        if(selectedPayment === 'card' && !razorpayPaymentDone) {
            Swal.fire({
                icon: 'warning',
                title: 'Complete Razorpay Payment',
                text: 'Please complete Razorpay payment first.',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }
        
        // Confirm booking
        Swal.fire({
            title: 'Confirm Booking',
            text: `Seat: ${selectedSeatNumber}\nAmount: ₹${total}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            cancelButtonColor: '#dc3545',
            confirmButtonText: 'Confirm Booking',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit the form
                document.getElementById('bookingForm').submit();
            }
        });
    }
    
    // Initialize payment method
    selectPayment('qr');
</script>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
