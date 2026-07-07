<?php
include_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user'] ?? '';

if ($user_id <= 0) {
    header("Location: ../gest/login.php");
    exit();
}

// Get booking parameters
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$booking_type = isset($_GET['type']) ? mysqli_real_escape_string($con, $_GET['type']) : '';

// If no booking ID, redirect to my bookings
if ($booking_id <= 0 || empty($booking_type) || !in_array($booking_type, ['match', 'ground'], true)) {
    header("Location: booking_details.php");
    exit();
}

// Fetch booking details based on type
$booking = null;
$error_message = '';
$success_message = '';
$selected_payment = 'qr';
$allowed_payment_methods = ['qr', 'cash', 'card'];

// Razorpay Test Credentials
$razorpay_key_id = 'rzp_test_SdMwGxkNmJiHyH';
$razorpay_key_secret = '8y0ArVHgIidoSbM8YnnGguHI';

if ($booking_type == 'match') {
    $query = "SELECT b.*, m.team1_name, m.team2_name, m.match_date, m.match_time, m.venue, m.price 
              FROM bookings b 
              INNER JOIN matches m ON b.match_id = m.id 
              WHERE b.id = $booking_id AND b.user_id = $user_id";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
        $booking['item_name'] = $booking['team1_name'] . ' vs ' . $booking['team2_name'];
        $booking['booking_time'] = date('h:i A', strtotime($booking['match_time']));
        $booking['duration'] = '2 Hours';
        $booking['details'] = 'Seat: ' . ($booking['seat_number'] ?? '-') . ' | Slots: ' . ((int) $booking['slots_booked']);
        $booking['location'] = $booking['venue'];
    }
} elseif ($booking_type == 'ground') {
    $query = "SELECT gb.*, gr.name as ground_name, gr.location, gr.price_per_hour 
              FROM ground_bookings gb 
              INNER JOIN grounds gr ON gb.ground_id = gr.id 
              WHERE gb.id = $booking_id AND gb.user_id = $user_id";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
        $booking['item_name'] = $booking['ground_name'];
        $booking['booking_time'] = date('h:i A', strtotime($booking['start_time']));
        $booking['end_time'] = date('h:i A', strtotime($booking['start_time'] . ' + ' . $booking['duration_hours'] . ' hours'));
        $booking['duration'] = $booking['duration_hours'] . ' Hour(s)';
        $booking['details'] = 'Duration: ' . ((int) $booking['duration_hours']) . ' hrs';
        $booking['location'] = $booking['location'];
    }
}

// If booking not found, redirect
if (!$booking) {
    header("Location: booking_details.php");
    exit();
}

// Handle payment method selection from POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['select_payment'])) {
    $posted_payment = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    if (in_array($posted_payment, $allowed_payment_methods, true)) {
        $selected_payment = $posted_payment;
    }
}

// UPI Details
$upi_id = 'vishalsarena44@oksbi';
$upi_name = 'Vishals Arena';
$upi_amount = number_format((float) $booking['total_amount'], 2, '.', '');
$upi_note = ucfirst($booking_type) . ' Booking #' . $booking_id;
$razorpay_amount_paise = (int) round(((float) $booking['total_amount']) * 100);

// Generate UPI Payment URL
$upi_url = "upi://pay?pa=" . urlencode($upi_id) .
    "&pn=" . urlencode($upi_name) .
    "&am=" . urlencode($upi_amount) .
    "&cu=INR" .
    "&tn=" . urlencode($upi_note);

// Generate QR Code
$qr_code_url = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=" . urlencode($upi_url) . "&choe=UTF-8";

// Check whether optional payments table exists
$payments_table_exists = false;
$payments_table_result = mysqli_query($con, "SHOW TABLES LIKE 'payments'");
if ($payments_table_result && mysqli_num_rows($payments_table_result) > 0) {
    $payments_table_exists = true;
}

// Check if already paid
if ($payments_table_exists) {
    $payment_check = "SELECT id FROM payments WHERE booking_id = $booking_id AND booking_type = '$booking_type' AND payment_status = 'completed' LIMIT 1";
    $payment_result = mysqli_query($con, $payment_check);
    if ($payment_result && mysqli_num_rows($payment_result) > 0) {
        if ($booking_type == 'match') {
            mysqli_query($con, "UPDATE bookings SET payment_status = 'completed', booking_status = 'confirmed' WHERE id = $booking_id");
        } else {
            mysqli_query($con, "UPDATE ground_bookings SET payment_status = 'completed', booking_status = 'confirmed' WHERE id = $booking_id");
        }
        header("Location: booking_details.php?booking_id=$booking_id&type=$booking_type&msg=already_paid");
        exit();
    }
}

// Process payment
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['confirm_payment'])) {
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    $transaction_id = isset($_POST['transaction_id']) ? mysqli_real_escape_string($con, $_POST['transaction_id']) : '';
    $cash_confirm = isset($_POST['cash_confirm']) ? $_POST['cash_confirm'] : '';

    $errors = [];

    if (!in_array($payment_method, $allowed_payment_methods, true)) {
        $errors[] = "Invalid payment method selected.";
    }

    if ($payment_method == 'qr' && empty($transaction_id)) {
        $errors[] = "Please enter the transaction ID for QR payment.";
    }

    if ($payment_method == 'cash' && empty($cash_confirm)) {
        $errors[] = "Please confirm that you will pay cash at the venue.";
    }

    if ($payment_method == 'card' && empty($transaction_id)) {
        $errors[] = "Razorpay payment is incomplete. Please pay using Razorpay Checkout.";
    }

    if (empty($errors)) {
        if (empty($transaction_id)) {
            $transaction_id = 'TXN' . time() . rand(1000, 9999);
        }

        
        $payment_record_saved = true;
        if ($payments_table_exists) {
            $insert_query = "INSERT INTO payments (booking_id, booking_type, user_id, amount, payment_method, payment_status, transaction_id) 
                             VALUES ($booking_id, '$booking_type', $user_id, {$booking['total_amount']}, '$payment_method', 'completed', '$transaction_id')";
            $payment_record_saved = mysqli_query($con, $insert_query) ? true : false;
        }

        if ($payment_record_saved) {
            if ($booking_type == 'match') {
                mysqli_query($con, "UPDATE bookings SET payment_status = 'completed', booking_status = 'confirmed' WHERE id = $booking_id");
            } else {
                mysqli_query($con, "UPDATE ground_bookings SET payment_status = 'completed', booking_status = 'confirmed' WHERE id = $booking_id");
            }

            $success_message = "Payment successful! Your booking has been confirmed.";
            echo "<meta http-equiv='refresh' content='2;url=booking_details.php?booking_id=$booking_id&type=$booking_type'>";
        } else {
            $error_message = "Payment failed. Please try again.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .payment-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .booking-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
        }

        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .payment-option.selected {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .qr-code {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }

        .qr-code img {
            max-width: 220px;
            height: auto;
            margin: 10px auto;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px;
        }

        .upi-details {
            background: #f0f4ff;
            padding: 10px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
        }

        .amount-display {
            font-size: 28px;
            font-weight: bold;
            color: #28a745;
        }

        .btn-copy {
            cursor: pointer;
        }
    </style>
</head>

<body class="bg-light">

    <?php include 'nevbar.php'; ?>

    <div class="container my-5 pt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">

                <div class="card payment-card shadow">
                    <div class="payment-header text-white text-center py-4">
                        <h3 class="mb-0">
                            <i class="bi bi-credit-card me-2"></i>Complete Payment
                        </h3>
                        <p class="mb-0 text-white-50">Secure payment for your <?php echo $booking_type; ?> booking</p>
                    </div>

                    <div class="card-body p-4">

                        <!-- Success Message -->
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo $success_message; ?>
                                <br><small>Redirecting to your bookings...</small>
                            </div>
                        <?php endif; ?>

                        <!-- Error Message -->
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Booking Details -->
                        <div class="booking-details mb-4">
                            <h5 class="fw-bold mb-3">
                                <i class="bi bi-receipt text-primary me-2"></i>Booking Details
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong><i class="bi bi-<?php echo $booking_type == 'match' ? 'trophy' : 'building'; ?> me-1"></i> <?php echo ucfirst($booking_type); ?>:</strong><br>
                                        <?php echo htmlspecialchars($booking['item_name']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <strong><i class="bi bi-geo-alt me-1"></i> Location:</strong><br>
                                        <?php echo htmlspecialchars($booking['location']); ?>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2">
                                        <strong><i class="bi bi-calendar me-1"></i> Date & Time:</strong><br>
                                        <?php echo date('d M Y', strtotime($booking_type === 'match' ? $booking['match_date'] : $booking['booking_date'])); ?> |
                                        <?php echo $booking['booking_time']; ?>
                                        <?php if (isset($booking['end_time'])): ?>
                                            - <?php echo $booking['end_time']; ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-2">
                                        <strong><i class="bi bi-info-circle me-1"></i> Details:</strong><br>
                                        <?php echo $booking['details']; ?>
                                    </p>
                                </div>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Total Amount:</span>
                                <span class="fw-bold text-primary amount-display">&#8377;<?php echo number_format($booking['total_amount'], 0); ?></span>
                            </div>
                        </div>

                        <!-- Payment Method Selection Form -->
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Payment Method</label>
                                <select name="payment_method" class="form-select" onchange="this.form.submit()">
                                    <option value="qr" <?php echo $selected_payment == 'qr' ? 'selected' : ''; ?>>QR Code Payment (UPI)</option>
                                    <option value="cash" <?php echo $selected_payment == 'cash' ? 'selected' : ''; ?>>Cash Payment</option>
                                    <option value="card" <?php echo $selected_payment == 'card' ? 'selected' : ''; ?>>Razorpay (Card/UPI/Wallet)</option>
                                </select>
                            </div>
                            <input type="hidden" name="select_payment" value="1">
                        </form>

                        <!-- Payment Details Form -->
                        <form method="POST" action="" id="payment_form">
                            <input type="hidden" name="payment_method" value="<?php echo $selected_payment; ?>">

                            <?php if ($selected_payment == 'qr'): ?>
                                <!-- QR Payment Details -->
                                <div class="qr-code mt-3">
                                    <div class="text-center">
                                        <img src="<?php echo htmlspecialchars($qr_code_url); ?>"
                                            alt="UPI QR Code"
                                            onerror="this.src='https://quickchart.io/qr?size=250&text=<?php echo urlencode($upi_url); ?>'">

                                        <div class="upi-details mt-3">
                                            <i class="bi bi-upc-scan"></i>
                                            <strong><?php echo htmlspecialchars($upi_id); ?></strong>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-copy ms-2" onclick="copyUPI()">
                                                <i class="bi bi-clipboard me-1"></i> Copy UPI ID
                                            </button>
                                        </div>

                                        <div class="mt-3">
                                            <p class="small text-secondary mb-1">
                                                <i class="bi bi-info-circle"></i>
                                                Pay <strong>&#8377;<?php echo number_format($booking['total_amount'], 0); ?></strong> to the above UPI ID
                                            </p>
                                            <input type="text" class="form-control" name="transaction_id"
                                                placeholder="Enter Transaction ID (Required)"
                                                style="text-transform: uppercase;" required>
                                            <small class="text-muted">Example: TXN123456789</small>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($selected_payment == 'cash'): ?>
                                <!-- Cash Payment Details -->
                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Please pay <strong>&#8377;<?php echo number_format($booking['total_amount'], 0); ?></strong> in cash at the venue before your
                                    <?php echo $booking_type == 'match' ? 'match' : 'ground booking'; ?>.
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="cash_confirm" id="cash_confirm" value="1" data-validation="required">
                                    <span id="cash_confirm_error" class="invalid-feedback"></span>
                                    <label class="form-check-label" for="cash_confirm">
                                        I confirm that I will pay cash at the venue
                                    </label>
                                </div>
                            <?php elseif ($selected_payment == 'card'): ?>
                                <!-- Razorpay Payment Details -->
                                <div class="mt-3">
                                    <div class="alert alert-primary mb-0">
                                        <i class="bi bi-lightning-charge-fill me-2"></i>
                                        Pay securely with Razorpay Checkout. You can use Card, UPI, Netbanking, or Wallet.
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($selected_payment == 'card'): ?>
                                <input type="hidden" name="transaction_id" id="razorpay_transaction_id">
                                <input type="hidden" name="confirm_payment" value="1">
                                <button type="button" id="rzp-pay-btn" class="btn btn-primary w-100 py-3 mt-4">
                                    <i class="bi bi-check-circle me-2"></i>Pay with Razorpay &#8377;<?php echo number_format($booking['total_amount'], 0); ?>
                                </button>
                            <?php else: ?>
                                <button type="submit" name="confirm_payment" class="btn btn-primary w-100 py-3 mt-4">
                                    <i class="bi bi-check-circle me-2"></i>Confirm & Pay &#8377;<?php echo number_format($booking['total_amount'], 0); ?>
                                </button>
                            <?php endif; ?>
                        </form>

                        <p class="text-center text-secondary small mt-3">
                            <i class="bi bi-shield-check me-1"></i>
                            Secure payment processed by Box Cricket
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Only one small JavaScript function for copying UPI ID
        function copyUPI() {
            const upiId = '<?php echo $upi_id; ?>';
            navigator.clipboard.writeText(upiId).then(() => {
                alert('UPI ID copied to clipboard: ' + upiId);
            }).catch(() => {
                alert('Failed to copy UPI ID. Please copy manually: ' + upiId);
            });
        }
    </script>
    <?php if ($selected_payment == 'card'): ?>
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            const rzpPayButton = document.getElementById('rzp-pay-btn');

            if (rzpPayButton) {
                rzpPayButton.addEventListener('click', function(e) {
                    e.preventDefault();

                    const options = {
                        key: <?php echo json_encode($razorpay_key_id); ?>,
                        amount: <?php echo (int) $razorpay_amount_paise; ?>,
                        currency: 'INR',
                        name: 'Box Cricket',
                        description: <?php echo json_encode(ucfirst($booking_type) . ' Booking #' . $booking_id); ?>,
                        handler: function(response) {
                            document.getElementById('razorpay_transaction_id').value = response.razorpay_payment_id || '';
                            document.getElementById('payment_form').submit();
                        },
                        prefill: {
                            name: <?php echo json_encode($user_name); ?>,
                            email: <?php echo json_encode($user_email); ?>
                        },
                        notes: {
                            booking_id: <?php echo json_encode((string) $booking_id); ?>,
                            booking_type: <?php echo json_encode($booking_type); ?>,
                            user_id: <?php echo json_encode((string) $user_id); ?>
                        },
                        theme: {
                            color: '#0d6efd'
                        },
                        modal: {
                            ondismiss: function() {
                                alert('Payment was cancelled. Please try again to confirm your booking.');
                            }
                        }
                    };

                    const rzp = new Razorpay(options);
                    rzp.open();
                });
            }
        </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- JQUERY FOR VALIDATION -->
    <script src="../javascript/jquery-4.0.0.js"></script>
    <script src="../javascript/validation.js"></script>

</body>

</html>

<?php include 'footer.php'; ?>
