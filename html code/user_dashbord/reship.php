<?php
include_once 'db_config.php';

if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    header("Location: ../gest/login.php");
    exit();
}

$booking_id = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$booking_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$booking_type = in_array($booking_type, ['match', 'ground'], true) ? $booking_type : '';

$receipt = null;

function fetch_match_booking($con, $user_id, $booking_id = 0)
{
    $sql = "SELECT
                b.id AS booking_id,
                'match' AS booking_type,
                b.booking_date,
                b.booking_time,
                b.slots_booked,
                b.seat_number,
                b.total_amount,
                b.payment_method,
                b.payment_status,
                b.booking_status,
                b.created_at,
                u.fullname AS user_name,
                u.email AS user_email,
                u.phone AS user_phone,
                m.team1_name,
                m.team2_name,
                m.venue AS location,
                m.match_date,
                m.match_time,
                m.price AS unit_price
            FROM bookings b
            INNER JOIN matches m ON b.match_id = m.id
            INNER JOIN users u ON b.user_id = u.id
            WHERE b.user_id = ? ";

    if ($booking_id > 0) {
        $sql .= "AND b.id = ? ";
    }

    $sql .= "ORDER BY b.created_at DESC LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        return null;
    }

    if ($booking_id > 0) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $booking_id);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

function fetch_ground_booking($con, $user_id, $booking_id = 0)
{
    $sql = "SELECT
                gb.id AS booking_id,
                'ground' AS booking_type,
                gb.booking_date,
                gb.start_time AS booking_time,
                gb.duration_hours,
                gb.total_amount,
                gb.price_per_hour AS unit_price,
                gb.payment_method,
                gb.payment_status,
                gb.booking_status,
                gb.created_at,
                gb.special_request,
                u.fullname AS user_name,
                u.email AS user_email,
                u.phone AS user_phone,
                g.name AS ground_name,
                g.location
            FROM ground_bookings gb
            INNER JOIN grounds g ON gb.ground_id = g.id
            INNER JOIN users u ON gb.user_id = u.id
            WHERE gb.user_id = ? ";

    if ($booking_id > 0) {
        $sql .= "AND gb.id = ? ";
    }

    $sql .= "ORDER BY gb.created_at DESC LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        return null;
    }

    if ($booking_id > 0) {
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $booking_id);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row;
}

if ($booking_type === 'match') {
    $receipt = fetch_match_booking($con, $user_id, $booking_id);
} elseif ($booking_type === 'ground') {
    $receipt = fetch_ground_booking($con, $user_id, $booking_id);
} else {
    if ($booking_id > 0) {
        $receipt = fetch_match_booking($con, $user_id, $booking_id);
        if (!$receipt) {
            $receipt = fetch_ground_booking($con, $user_id, $booking_id);
        }
    } else {
        $latest_match = fetch_match_booking($con, $user_id, 0);
        $latest_ground = fetch_ground_booking($con, $user_id, 0);

        if ($latest_match && $latest_ground) {
            $receipt = (strtotime($latest_match['created_at']) >= strtotime($latest_ground['created_at'])) ? $latest_match : $latest_ground;
        } else {
            $receipt = $latest_match ?: $latest_ground;
        }
    }
}

$transaction_id = '-';
if ($receipt) {
    $payments_table_exists = false;
    $table_check = mysqli_query($con, "SHOW TABLES LIKE 'payments'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $payments_table_exists = true;
    }

    if ($payments_table_exists) {
        $booking_id_int = (int) $receipt['booking_id'];
        $booking_type_str = mysqli_real_escape_string($con, $receipt['booking_type']);
        $payment_query = "SELECT transaction_id
                          FROM payments
                          WHERE booking_id = $booking_id_int
                            AND booking_type = '$booking_type_str'
                          ORDER BY created_at DESC
                          LIMIT 1";
        $payment_result = mysqli_query($con, $payment_query);
        if ($payment_result && mysqli_num_rows($payment_result) > 0) {
            $payment_row = mysqli_fetch_assoc($payment_result);
            if (!empty($payment_row['transaction_id'])) {
                $transaction_id = $payment_row['transaction_id'];
            }
        }
    }
}

function status_badge_class($status)
{
    switch ($status) {
        case 'confirmed':
            return 'success';
        case 'pending':
            return 'warning';
        case 'completed':
            return 'info';
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>

<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Receipt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .receipt-card {
            max-width: 900px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<div class="container mt-5 pt-5 mb-5">
    <?php if (!$receipt): ?>
        <div class="alert alert-warning text-center">
            <h5 class="mb-2">No booking found</h5>
            <p class="mb-3">We could not find a receipt for this booking.</p>
            <a href="booking_details.php" class="btn btn-primary">Go to My Bookings</a>
        </div>
    <?php else: ?>
        <?php
            $is_match = $receipt['booking_type'] === 'match';
            $display_name = $is_match
                ? ($receipt['team1_name'] . ' vs ' . $receipt['team2_name'])
                : $receipt['ground_name'];
            $location = $receipt['location'] ?? '-';
            $start_time = $is_match ? $receipt['match_time'] : $receipt['booking_time'];
            $end_time = $is_match
                ? date('H:i:s', strtotime(($start_time ?: '00:00:00') . ' +2 hours'))
                : date('H:i:s', strtotime(($start_time ?: '00:00:00') . ' +' . ((int) ($receipt['duration_hours'] ?? 1)) . ' hours'));

            $quantity = $is_match ? max(1, (int) ($receipt['slots_booked'] ?? 1)) : max(1, (int) ($receipt['duration_hours'] ?? 1));
            $unit_price = isset($receipt['unit_price']) ? (float) $receipt['unit_price'] : 0.0;
            $base_amount = $unit_price > 0 ? ($unit_price * $quantity) : (float) $receipt['total_amount'];
            $total_amount = (float) $receipt['total_amount'];
            $gst_amount = max(0, $total_amount - $base_amount);

            $status = $receipt['booking_status'] ?? 'pending';
            $status_class = status_badge_class($status);
            $booking_code = 'BK-' . strtoupper(substr($receipt['booking_type'], 0, 1)) . str_pad((string) $receipt['booking_id'], 5, '0', STR_PAD_LEFT);
        ?>

        <div class="card receipt-card">
            <div class="card-header bg-primary text-white text-center">
                <h3>Booking Receipt</h3>
                <p class="mb-0">#<?php echo htmlspecialchars($booking_code); ?></p>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><?php echo $is_match ? 'Match' : 'Ground'; ?>:</strong> <?php echo htmlspecialchars($display_name); ?></p>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($location); ?></p>
                        <p><strong>Date:</strong> <?php echo date('d F Y', strtotime($receipt['booking_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($start_time)); ?> - <?php echo date('h:i A', strtotime($end_time)); ?></p>
                        <?php if ($is_match): ?>
                            <p><strong>Seat:</strong> <?php echo htmlspecialchars($receipt['seat_number'] ?? '-'); ?></p>
                        <?php else: ?>
                            <p><strong>Duration:</strong> <?php echo (int) $receipt['duration_hours']; ?> hour(s)</p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($receipt['user_name'] ?? '-'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($receipt['user_email'] ?? '-'); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($receipt['user_phone'] ?? '-'); ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span></p>
                        <p><strong>Payment:</strong> <?php echo ucfirst(htmlspecialchars($receipt['payment_method'] ?? 'qr')); ?> (<?php echo ucfirst(htmlspecialchars($receipt['payment_status'] ?? 'pending')); ?>)</p>
                        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($transaction_id); ?></p>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-6">
                        <p><?php echo $is_match ? 'Ticket Charges:' : 'Ground Charges:'; ?></p>
                        <p>GST:</p>
                        <p><strong>Total:</strong></p>
                    </div>
                    <div class="col-6 text-end">
                        <p>&#8377;<?php echo number_format($base_amount, 2); ?></p>
                        <p>&#8377;<?php echo number_format($gst_amount, 2); ?></p>
                        <p><strong>&#8377;<?php echo number_format($total_amount, 2); ?></strong></p>
                    </div>
                </div>

                <hr>

                <div class="text-center">
                    <button class="btn btn-primary" onclick="window.print()">Print</button>
                    <a href="booking_details.php" class="btn btn-secondary">Back</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

<?php include 'footer.php'; ?>
