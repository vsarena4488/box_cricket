<?php
include_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

// Get user details
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$user_name = $_SESSION['user_name'] ?? 'User';

if ($user_id <= 0) {
    header("Location: ../gest/login.php");
    exit();
}

// Get and validate filters
$allowed_statuses = ['all', 'confirmed', 'pending', 'cancelled', 'completed'];
$allowed_types = ['all', 'match', 'ground'];

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$booking_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'all';
}
if (!in_array($booking_type, $allowed_types, true)) {
    $booking_type = 'all';
}
$search_query_param = $search !== '' ? "&search=" . urlencode($search) : "";

// Handle cancellation request
if (isset($_POST['cancel_booking'])) {
    $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
    $booking_type_val = isset($_POST['booking_type']) ? trim($_POST['booking_type']) : '';

    if ($booking_id <= 0 || !in_array($booking_type_val, ['match', 'ground'], true)) {
        $_SESSION['error'] = "Invalid booking selected.";
    } else {
        if ($booking_type_val === 'match') {
            mysqli_begin_transaction($con);
            $cancel_success = false;

            $lock_query = "SELECT id, match_id, booking_status 
                           FROM bookings 
                           WHERE id = $booking_id AND user_id = $user_id 
                           LIMIT 1
                           FOR UPDATE";
            $lock_result = mysqli_query($con, $lock_query);

            if ($lock_result && mysqli_num_rows($lock_result) === 1) {
                $booking_row = mysqli_fetch_assoc($lock_result);
                $current_status = $booking_row['booking_status'];
                $match_id_for_restore = (int) $booking_row['match_id'];

                if ($current_status === 'cancelled') {
                    $_SESSION['error'] = "This booking is already cancelled.";
                } elseif ($current_status === 'completed') {
                    $_SESSION['error'] = "Completed booking cannot be cancelled.";
                } else {
                    $cancel_query = "UPDATE bookings 
                                     SET booking_status = 'cancelled' 
                                     WHERE id = $booking_id AND user_id = $user_id";
                    $cancel_result = mysqli_query($con, $cancel_query);

                    if ($cancel_result && mysqli_affected_rows($con) > 0) {
                        $restore_slots_query = "UPDATE matches 
                                                SET available_slots = LEAST(available_slots + 1, total_slots) 
                                                WHERE id = $match_id_for_restore";
                        $restore_result = mysqli_query($con, $restore_slots_query);

                        if ($restore_result) {
                            $cancel_success = true;
                        } else {
                            $_SESSION['error'] = "Booking cancelled, but slot restore failed.";
                        }
                    } else {
                        $_SESSION['error'] = "Failed to cancel booking.";
                    }
                }
            } else {
                $_SESSION['error'] = "Booking not found.";
            }

            if ($cancel_success) {
                mysqli_commit($con);
                $_SESSION['success'] = "Booking cancelled successfully!";
            } else {
                mysqli_rollback($con);
            }
        } else {
            $ground_status_result = mysqli_query($con, "SELECT booking_status FROM ground_bookings WHERE id = $booking_id AND user_id = $user_id LIMIT 1");
            if (!$ground_status_result || mysqli_num_rows($ground_status_result) === 0) {
                $_SESSION['error'] = "Booking not found.";
            } else {
                $ground_booking = mysqli_fetch_assoc($ground_status_result);
                $ground_status = $ground_booking['booking_status'];

                if ($ground_status === 'cancelled') {
                    $_SESSION['error'] = "This booking is already cancelled.";
                } elseif ($ground_status === 'completed') {
                    $_SESSION['error'] = "Completed booking cannot be cancelled.";
                } else {
                    $cancel_query = "UPDATE ground_bookings 
                                     SET booking_status = 'cancelled' 
                                     WHERE id = $booking_id 
                                       AND user_id = $user_id";
                    if (mysqli_query($con, $cancel_query) && mysqli_affected_rows($con) > 0) {
                        $_SESSION['success'] = "Booking cancelled successfully!";
                    } else {
                        $_SESSION['error'] = "Failed to cancel booking.";
                    }
                }
            }
        }
    }

    header("Location: booking_details.php?type=" . urlencode($booking_type) . "&status=" . urlencode($status_filter) . $search_query_param);
    exit();
}

// Build base query for MATCH BOOKINGS
$match_query = "SELECT 
                b.id AS booking_id,
                'match' AS booking_type,
                b.user_id,
                b.match_id AS item_id,
                b.booking_date,
                b.booking_time,
                b.slots_booked,
                b.seat_number,
                b.total_amount,
                b.payment_method,
                b.payment_status,
                b.booking_status,
                CASE 
                    WHEN b.booking_status = 'cancelled' THEN 'cancelled'
                    WHEN DATE_ADD(TIMESTAMP(m.match_date, m.match_time), INTERVAL 2 HOUR) < NOW() THEN 'completed'
                    WHEN b.payment_status = 'pending' AND b.booking_status = 'confirmed' THEN 'pending'
                    ELSE b.booking_status
                END AS ui_status,
                b.created_at,
                m.team1_name,
                m.team2_name,
                m.match_type,
                m.match_date,
                m.match_time,
                m.price,
                m.venue,
                CONCAT(m.team1_name, ' vs ', m.team2_name) AS item_name
                FROM bookings b
                INNER JOIN matches m ON b.match_id = m.id
                WHERE b.user_id = $user_id";

// Build base query for GROUND BOOKINGS
$ground_query = "SELECT
                gb.id AS booking_id,
                'ground' AS booking_type,
                gb.user_id,
                gb.ground_id AS item_id,
                gb.booking_date,
                gb.start_time AS booking_time,
                gb.duration_hours AS slots_booked,
                NULL AS seat_number,
                gb.total_amount,
                gb.payment_method,
                gb.payment_status,
                gb.booking_status,
                CASE 
                    WHEN gb.booking_status = 'cancelled' THEN 'cancelled'
                    WHEN DATE_ADD(TIMESTAMP(gb.booking_date, gb.start_time), INTERVAL gb.duration_hours HOUR) < NOW() THEN 'completed'
                    WHEN gb.payment_status = 'pending' AND gb.booking_status = 'confirmed' THEN 'pending'
                    ELSE gb.booking_status
                END AS ui_status,
                gb.created_at,
                NULL AS team1_name,
                NULL AS team2_name,
                gr.ground_type AS match_type,
                NULL AS match_date,
                NULL AS match_time,
                NULL AS price,
                gr.location AS venue,
                gr.name AS item_name
                FROM ground_bookings gb
                INNER JOIN grounds gr ON gb.ground_id = gr.id
                WHERE gb.user_id = $user_id";

// Combine queries based on booking type
if ($booking_type === 'match') {
    $final_query = "SELECT * FROM ($match_query) AS all_bookings";
} elseif ($booking_type === 'ground') {
    $final_query = "SELECT * FROM ($ground_query) AS all_bookings";
} else {
    $final_query = "SELECT * FROM (($match_query) UNION ALL ($ground_query)) AS all_bookings";
}

// Apply filters on top-level result
$conditions = [];
if ($status_filter !== 'all') {
    $status_db = mysqli_real_escape_string($con, $status_filter);
    $conditions[] = "ui_status = '$status_db'";
}
if ($search !== '') {
    $search_db = mysqli_real_escape_string($con, $search);
    $conditions[] = "(item_name LIKE '%$search_db%' OR COALESCE(seat_number, '') LIKE '%$search_db%' OR COALESCE(venue, '') LIKE '%$search_db%')";
}
if (!empty($conditions)) {
    $final_query .= " WHERE " . implode(" AND ", $conditions);
}

$final_query .= " ORDER BY booking_date DESC, booking_time DESC";
$bookings_result = mysqli_query($con, $final_query);
$bookings_count = $bookings_result ? mysqli_num_rows($bookings_result) : 0;

// Get statistics for both types
$match_stats_result = mysqli_query($con, "SELECT
    COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN ui_status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed,
    COALESCE(SUM(CASE WHEN ui_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
    COALESCE(SUM(CASE WHEN ui_status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
    COALESCE(SUM(CASE WHEN ui_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed
FROM (
    SELECT
        CASE
            WHEN b.booking_status = 'cancelled' THEN 'cancelled'
            WHEN DATE_ADD(TIMESTAMP(m.match_date, m.match_time), INTERVAL 2 HOUR) < NOW() THEN 'completed'
            WHEN b.payment_status = 'pending' AND b.booking_status = 'confirmed' THEN 'pending'
            ELSE b.booking_status
        END AS ui_status
    FROM bookings b
    INNER JOIN matches m ON b.match_id = m.id
    WHERE b.user_id = $user_id
) AS match_statuses");
$ground_stats_result = mysqli_query($con, "SELECT
    COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN ui_status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed,
    COALESCE(SUM(CASE WHEN ui_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
    COALESCE(SUM(CASE WHEN ui_status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
    COALESCE(SUM(CASE WHEN ui_status = 'completed' THEN 1 ELSE 0 END), 0) AS completed
FROM (
    SELECT
        CASE
            WHEN gb.booking_status = 'cancelled' THEN 'cancelled'
            WHEN DATE_ADD(TIMESTAMP(gb.booking_date, gb.start_time), INTERVAL gb.duration_hours HOUR) < NOW() THEN 'completed'
            WHEN gb.payment_status = 'pending' AND gb.booking_status = 'confirmed' THEN 'pending'
            ELSE gb.booking_status
        END AS ui_status
    FROM ground_bookings gb
    WHERE gb.user_id = $user_id
) AS ground_statuses");

$match_stats = $match_stats_result ? mysqli_fetch_assoc($match_stats_result) : ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0, 'completed' => 0];
$ground_stats = $ground_stats_result ? mysqli_fetch_assoc($ground_stats_result) : ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0, 'completed' => 0];

$total_bookings = (int) $match_stats['total'] + (int) $ground_stats['total'];
$total_confirmed = (int) $match_stats['confirmed'] + (int) $ground_stats['confirmed'];
$total_pending = (int) $match_stats['pending'] + (int) $ground_stats['pending'];
$total_cancelled = (int) $match_stats['cancelled'] + (int) $ground_stats['cancelled'];
$total_completed = (int) $match_stats['completed'] + (int) $ground_stats['completed'];
?>

<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Box Cricket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .stats-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.2s;
            cursor: pointer;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-confirmed {
            background-color: #e3f9e5;
            color: #1e7b4c;
        }
        .status-pending {
            background-color: #fff3d6;
            color: #b45b0b;
        }
        .status-completed {
            background-color: #e3f0ff;
            color: #1a5fb0;
        }
        .status-cancelled {
            background-color: #ffe8e8;
            color: #c13b3b;
        }
        .filter-btn, .type-btn {
            border-radius: 25px;
            padding: 8px 20px;
            margin: 0 5px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .filter-btn.active, .type-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .filter-btn:not(.active), .type-btn:not(.active) {
            background: white;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        .booking-card {
            transition: all 0.3s;
            border-left: 4px solid;
        }
        .booking-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .booking-confirmed { border-left-color: #28a745; }
        .booking-pending { border-left-color: #ffc107; }
        .booking-cancelled { border-left-color: #dc3545; }
        .booking-completed { border-left-color: #17a2b8; }
        .match-type-badge, .ground-type-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .match-premium { background: #ffd89b; color: #856404; }
        .match-standard { background: #a8edea; color: #0c5460; }
        .match-practice { background: #d4fc79; color: #155724; }
        .ground-floodlight { background: #cfe2ff; color: #084298; }
        .ground-covered { background: #cff4fc; color: #055160; }
        .ground-premium { background: #ffd89b; color: #856404; }
        .ground-practice { background: #d4fc79; color: #155724; }
        .ground-indoor { background: #e2e3e5; color: #41464b; }
        .ground-vip { background: #f8d7da; color: #721c24; }
        .booking-type-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        .type-match { background: #0d6efd; color: white; }
        .type-ground { background: #198754; color: white; }
    </style>
</head>
<body>

<div class="container my-5 pt-5 pb-5">
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">
                <i class="bi bi-journal-bookmark-fill text-primary me-2"></i>My Bookings
            </h2>
            <p class="text-secondary">Welcome back, <?php echo htmlspecialchars($user_name); ?>! Here are all your bookings (Matches & Grounds).</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stats-card shadow-sm" onclick="filterByStatus('all')">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Bookings</h6>
                            <h3 class="fw-bold mb-0"><?php echo $total_bookings; ?></h3>
                        </div>
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card shadow-sm" onclick="filterByStatus('confirmed')">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Confirmed</h6>
                            <h3 class="fw-bold mb-0 text-success"><?php echo $total_confirmed; ?></h3>
                        </div>
                        <div class="stats-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card shadow-sm" onclick="filterByStatus('pending')">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h3 class="fw-bold mb-0 text-warning"><?php echo $total_pending; ?></h3>
                        </div>
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card shadow-sm" onclick="filterByStatus('cancelled')">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Cancelled</h6>
                            <h3 class="fw-bold mb-0 text-danger"><?php echo $total_cancelled; ?></h3>
                        </div>
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-x-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Type Filter -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <a href="?type=all&status=<?php echo urlencode($status_filter); ?><?php echo $search_query_param; ?>" class="type-btn <?php echo $booking_type == 'all' ? 'active' : ''; ?>">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> All Bookings
                </a>
                <a href="?type=match&status=<?php echo urlencode($status_filter); ?><?php echo $search_query_param; ?>" class="type-btn <?php echo $booking_type == 'match' ? 'active' : ''; ?>">
                    <i class="bi bi-trophy me-1"></i> Match Bookings
                </a>
                <a href="?type=ground&status=<?php echo urlencode($status_filter); ?><?php echo $search_query_param; ?>" class="type-btn <?php echo $booking_type == 'ground' ? 'active' : ''; ?>">
                    <i class="bi bi-building me-1"></i> Ground Bookings
                </a>
            </div>
        </div>
    </div>

    <!-- Status Filter and Search Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?type=<?php echo urlencode($booking_type); ?>&status=all<?php echo $search_query_param; ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                            <i class="bi bi-grid-3x3-gap-fill me-1"></i> All
                        </a>
                        <a href="?type=<?php echo urlencode($booking_type); ?>&status=confirmed<?php echo $search_query_param; ?>" class="filter-btn <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
                            <i class="bi bi-check-circle me-1"></i> Confirmed
                        </a>
                        <a href="?type=<?php echo urlencode($booking_type); ?>&status=pending<?php echo $search_query_param; ?>" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
                            <i class="bi bi-clock me-1"></i> Pending
                        </a>
                        <a href="?type=<?php echo urlencode($booking_type); ?>&status=completed<?php echo $search_query_param; ?>" class="filter-btn <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">
                            <i class="bi bi-trophy me-1"></i> Completed
                        </a>
                        <a href="?type=<?php echo urlencode($booking_type); ?>&status=cancelled<?php echo $search_query_param; ?>" class="filter-btn <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
                            <i class="bi bi-x-circle me-1"></i> Cancelled
                        </a>
                    </div>
                </div>
                <div class="col-md-4 mt-3 mt-md-0">
                    <form method="GET" action="">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search bookings..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="type" value="<?php echo $booking_type; ?>">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if(!empty($search)): ?>
                                <a href="?type=<?php echo urlencode($booking_type); ?>&status=<?php echo urlencode($status_filter); ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <?php if($bookings_count > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr class="text-center">
                        <th>ID</th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Date & Time</th>
                        <th>Seat/Slots</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($booking = mysqli_fetch_assoc($bookings_result)): 
                        $booking_class = '';
                        $display_status = $booking['ui_status'] ?? $booking['booking_status'];
                        $status_text = ucfirst($display_status);
                        $status_icon = '';
                        
                        switch($display_status) {
                            case 'confirmed':
                                $booking_class = 'booking-confirmed';
                                $status_icon = 'check-circle';
                                break;
                            case 'pending':
                                $booking_class = 'booking-pending';
                                $status_icon = 'clock';
                                break;
                            case 'cancelled':
                                $booking_class = 'booking-cancelled';
                                $status_icon = 'x-circle';
                                break;
                            case 'completed':
                                $booking_class = 'booking-completed';
                                $status_icon = 'trophy';
                                break;
                            default:
                                $booking_class = 'booking-pending';
                                $status_icon = 'clock';
                                break;
                        }
                        
                        // Determine type class
                        $type_class = $booking['booking_type'] == 'match' ? 'type-match' : 'type-ground';
                        $type_icon = $booking['booking_type'] == 'match' ? 'bi-trophy' : 'bi-building';
                        
                        // Get type badge class
                        $item_type = $booking['match_type'] ?? ($booking['booking_type'] === 'ground' ? 'practice' : 'standard');
                        $type_badge_class = '';
                        if ($booking['booking_type'] == 'match') {
                            switch($item_type) {
                                case 'premium': $type_badge_class = 'match-premium'; break;
                                case 'standard': $type_badge_class = 'match-standard'; break;
                                case 'practice': $type_badge_class = 'match-practice'; break;
                            }
                        } else {
                            switch($item_type) {
                                case 'floodlight': $type_badge_class = 'ground-floodlight'; break;
                                case 'covered': $type_badge_class = 'ground-covered'; break;
                                case 'premium': $type_badge_class = 'ground-premium'; break;
                                case 'practice': $type_badge_class = 'ground-practice'; break;
                                case 'indoor': $type_badge_class = 'ground-indoor'; break;
                                case 'vip': $type_badge_class = 'ground-vip'; break;
                            }
                        }
                    ?>
                        <tr class="booking-card <?php echo $booking_class; ?>">
                            <td class="text-center fw-bold">
                                #<?php echo $booking['booking_id']; ?>
                            </td>
                            <td class="text-center">
                                <span class="booking-type-badge <?php echo $type_class; ?>">
                                    <i class="bi <?php echo $type_icon; ?>"></i> <?php echo ucfirst($booking['booking_type']); ?>
                                </span>
                             </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($booking['item_name']); ?></div>
                                <span class="match-type-badge <?php echo $type_badge_class; ?>">
                                    <?php echo ucfirst($item_type); ?>
                                </span>
                                <div class="small text-secondary"><?php echo htmlspecialchars($booking['venue']); ?></div>
                             </td>
                            <td class="text-center">
                                <?php echo date('d M Y', strtotime($booking['booking_date'])); ?><br>
                                <small class="text-secondary"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></small>
                             </td>
                            <td class="text-center">
                                <?php if($booking['seat_number']): ?>
                                    <span class="badge bg-info">Seat: <?php echo $booking['seat_number']; ?></span>
                                <?php endif; ?>
                                <div class="small"><?php echo $booking['slots_booked']; ?> slot(s)</div>
                             </td>
                            <td class="text-center fw-bold text-success">
                                Rs. <?php echo number_format((float) $booking['total_amount'], 0); ?>
                             </td>
                            <td class="text-center">
                                <span class="status-badge status-<?php echo $display_status; ?>">
                                    <i class="bi bi-<?php echo $status_icon; ?>"></i>
                                    <?php echo $status_text; ?>
                                </span>
                             </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <?php if($booking['booking_type'] == 'match'): ?>
                                        <a href="reship.php?booking_id=<?php echo (int) $booking['booking_id']; ?>&type=match" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php else: ?>
                                        <a href="reship.php?booking_id=<?php echo (int) $booking['booking_id']; ?>&type=ground" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if($booking['payment_status'] == 'pending' && $display_status != 'completed' && $booking['booking_status'] != 'cancelled'): ?>
                                        <a href="payment_slot.php?booking_id=<?php echo (int) $booking['booking_id']; ?>&type=<?php echo urlencode($booking['booking_type']); ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="bi bi-credit-card"></i> Pay
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if(in_array($display_status, ['confirmed', 'pending'], true) && !in_array($booking['booking_status'], ['cancelled', 'completed'], true)): ?>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                            <input type="hidden" name="booking_type" value="<?php echo $booking['booking_type']; ?>">
                                            <input type="hidden" name="cancel_booking" value="1">
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmCancel(this.form)">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if($display_status == 'completed'): ?>
                                        <a href="feedback.php?booking_id=<?php echo $booking['booking_id']; ?>&type=<?php echo $booking['booking_type']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bi bi-star"></i> Review
                                        </a>
                                    <?php endif; ?>
                                </div>
                             </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Results Summary -->
        <div class="text-center mt-4">
            <p class="text-muted">
                <i class="bi bi-info-circle"></i> 
                Showing <?php echo $bookings_count; ?> booking(s)
            </p>
        </div>
        
    <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-5">
            <div class="empty-state">
                <i class="bi bi-inbox fs-1 text-secondary d-block mb-3"></i>
                <h4 class="text-secondary">No Bookings Found</h4>
                <p class="text-secondary mb-3">
                    <?php if($status_filter != 'all'): ?>
                        No <?php echo $status_filter; ?> bookings available.
                    <?php else: ?>
                        You haven't made any bookings yet.
                    <?php endif; ?>
                </p>
                <div class="d-flex gap-3 justify-content-center">
                    <a href="match.php" class="btn btn-primary">
                        <i class="bi bi-trophy me-2"></i>Book a Match
                    </a>
                    <a href="slot_booking.php" class="btn btn-success">
                        <i class="bi bi-building me-2"></i>Book a Ground
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // Filter by status function
    function filterByStatus(status) {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type') || 'all';
        const search = urlParams.get('search');
        let nextUrl = `?type=${encodeURIComponent(type)}&status=${encodeURIComponent(status)}`;
        if (search) {
            nextUrl += `&search=${encodeURIComponent(search)}`;
        }
        window.location.href = nextUrl;
    }
    
    // Confirm cancellation
    function confirmCancel(form) {
        Swal.fire({
            title: 'Cancel Booking?',
            text: 'Are you sure you want to cancel this booking?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No, keep it'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
        return false;
    }
    
    // Display success message if exists
    <?php if(isset($_SESSION['success'])): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: <?php echo json_encode($_SESSION['success']); ?>,
            timer: 3000,
            showConfirmButton: false
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: <?php echo json_encode($_SESSION['error']); ?>
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- JQUERY FOR VALIDATION -->
    <script src="../javascript/jquery-4.0.0.js"></script>
    <script src="../javascript/validation.js"></script>

</body>
</html>

<?php include 'footer.php'; ?>
