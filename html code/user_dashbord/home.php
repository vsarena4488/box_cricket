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

// Get user statistics from database (MATCH BOOKINGS)
$total_match_bookings = 0;
$upcoming_match_bookings = 0;
$completed_match_bookings = 0;
$cancelled_match_bookings = 0;

// Total match bookings
$total_match_query = "SELECT COUNT(*) as total FROM bookings WHERE user_id = $user_id";
$total_match_result = mysqli_query($con, $total_match_query);
if ($total_match_result) {
    $total_match_bookings = mysqli_fetch_assoc($total_match_result)['total'];
}

// Upcoming match bookings
$upcoming_match_query = "SELECT COUNT(*) as total FROM bookings b 
                         INNER JOIN matches m ON b.match_id = m.id 
                         WHERE b.user_id = $user_id 
                         AND m.match_date >= CURDATE() 
                         AND b.booking_status = 'confirmed'";
$upcoming_match_result = mysqli_query($con, $upcoming_match_query);
if ($upcoming_match_result) {
    $upcoming_match_bookings = mysqli_fetch_assoc($upcoming_match_result)['total'];
}

// Completed match bookings
$completed_match_query = "SELECT COUNT(*) as total FROM bookings b 
                          INNER JOIN matches m ON b.match_id = m.id 
                          WHERE b.user_id = $user_id 
                          AND (m.match_date < CURDATE() OR b.booking_status = 'completed')";
$completed_match_result = mysqli_query($con, $completed_match_query);
if ($completed_match_result) {
    $completed_match_bookings = mysqli_fetch_assoc($completed_match_result)['total'];
}

// Cancelled match bookings
$cancelled_match_query = "SELECT COUNT(*) as total FROM bookings 
                          WHERE user_id = $user_id AND booking_status = 'cancelled'";
$cancelled_match_result = mysqli_query($con, $cancelled_match_query);
if ($cancelled_match_result) {
    $cancelled_match_bookings = mysqli_fetch_assoc($cancelled_match_result)['total'];
}

// Get GROUND BOOKINGS statistics
$total_ground_bookings = 0;
$upcoming_ground_bookings = 0;
$completed_ground_bookings = 0;
$cancelled_ground_bookings = 0;

// Total ground bookings
$total_ground_query = "SELECT COUNT(*) as total FROM ground_bookings WHERE user_id = $user_id";
$total_ground_result = mysqli_query($con, $total_ground_query);
if ($total_ground_result) {
    $total_ground_bookings = mysqli_fetch_assoc($total_ground_result)['total'];
}

// Upcoming ground bookings
$upcoming_ground_query = "SELECT COUNT(*) as total FROM ground_bookings 
                          WHERE user_id = $user_id 
                          AND booking_date >= CURDATE() 
                          AND booking_status = 'confirmed'";
$upcoming_ground_result = mysqli_query($con, $upcoming_ground_query);
if ($upcoming_ground_result) {
    $upcoming_ground_bookings = mysqli_fetch_assoc($upcoming_ground_result)['total'];
}

// Completed ground bookings
$completed_ground_query = "SELECT COUNT(*) as total FROM ground_bookings 
                           WHERE user_id = $user_id 
                           AND (booking_date < CURDATE() OR booking_status = 'completed')";
$completed_ground_result = mysqli_query($con, $completed_ground_query);
if ($completed_ground_result) {
    $completed_ground_bookings = mysqli_fetch_assoc($completed_ground_result)['total'];
}

// Cancelled ground bookings
$cancelled_ground_query = "SELECT COUNT(*) as total FROM ground_bookings 
                           WHERE user_id = $user_id AND booking_status = 'cancelled'";
$cancelled_ground_result = mysqli_query($con, $cancelled_ground_query);
if ($cancelled_ground_result) {
    $cancelled_ground_bookings = mysqli_fetch_assoc($cancelled_ground_result)['total'];
}

// Combined statistics
$total_bookings = $total_match_bookings + $total_ground_bookings;
$upcoming_bookings = $upcoming_match_bookings + $upcoming_ground_bookings;
$completed_bookings = $completed_match_bookings + $completed_ground_bookings;
$cancelled_bookings = $cancelled_match_bookings + $cancelled_ground_bookings;

// Get upcoming matches from database
$upcoming_matches_query = "SELECT * FROM matches 
                           WHERE match_date >= CURDATE() 
                           AND status = 'upcoming' 
                           ORDER BY match_date ASC 
                           LIMIT 3";
$upcoming_matches_result = mysqli_query($con, $upcoming_matches_query);

// Get RECENT BOOKINGS (Combined - Both Match and Ground)
$recent_bookings_query = "(
    SELECT 
        b.id as booking_id,
        'match' as booking_type,
        b.user_id,
        b.match_id as item_id,
        b.booking_date,
        b.booking_time as booking_time,
        b.slots_booked,
        b.seat_number,
        b.total_amount,
        b.payment_method,
        b.payment_status,
        b.booking_status,
        b.created_at,
        CONCAT(m.team1_name, ' vs ', m.team2_name) as item_name,
        CONCAT(m.match_date, ' ', m.match_time) as item_datetime,
        m.venue as location,
        m.match_type as type,
        m.price as price,
        NULL as duration
    FROM bookings b 
    INNER JOIN matches m ON b.match_id = m.id 
    WHERE b.user_id = $user_id
)
UNION ALL
(
    SELECT 
        gb.id as booking_id,
        'ground' as booking_type,
        gb.user_id,
        gb.ground_id as item_id,
        gb.booking_date,
        gb.start_time as booking_time,
        gb.duration_hours as slots_booked,
        NULL as seat_number,
        gb.total_amount,
        gb.payment_method,
        gb.payment_status,
        gb.booking_status,
        gb.created_at,
        gr.name as item_name,
        CONCAT(gb.booking_date, ' ', gb.start_time) as item_datetime,
        gr.location,
        gr.ground_type as type,
        gr.price_per_hour as price,
        gb.duration_hours as duration
    FROM ground_bookings gb 
    INNER JOIN grounds gr ON gb.ground_id = gr.id 
    WHERE gb.user_id = $user_id
)
ORDER BY created_at DESC 
LIMIT 5";
$recent_bookings_result = mysqli_query($con, $recent_bookings_query);
?>

<?php include 'nevbar.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - User Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        .welcome-panel {
            background: linear-gradient(145deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon {
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

        .action-btn {
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            margin: 0 3px;
        }

        .action-btn-view {
            background: #f0f4ff;
            color: #667eea;
        }

        .action-btn-book {
            background: #e3f9e5;
            color: #1e7b4c;
        }

        .action-btn-view:hover {
            background: #667eea;
            color: white;
        }

        .action-btn-book:hover {
            background: #1e7b4c;
            color: white;
        }

        .stat-trend {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 30px;
            background: #e6ffed;
            color: #0ca53c;
        }

        .table> :not(caption)>*>* {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .feature-card {
            border: none;
            border-radius: 16px;
            transition: transform 0.2s;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1) !important;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .match-card {
            border-left: 4px solid;
            transition: all 0.2s;
            cursor: pointer;
        }

        .match-card:hover {
            transform: translateX(5px);
        }

        .match-premium {
            border-left-color: #ffc107;
        }

        .match-standard {
            border-left-color: #0d6efd;
        }

        .match-practice {
            border-left-color: #198754;
        }
        
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

    <div class="container py-4 pt-5">
        <!-- Dashboard Container -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <!-- Welcome Panel -->
                <div class="welcome-panel text-white p-4 rounded-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h2>
                            <p class="mb-md-0 text-white-50">Here is your booking summary and activity. Ready for your next cricket match?</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="slot_booking.php" class="btn btn-light rounded-pill px-4 py-2 fw-semibold">
                                <i class="bi bi-plus-circle me-2"></i>Book New Slot
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                        <i class="bi bi-calendar-check"></i>
                                    </div>
                                    <span class="stat-trend"><i class="bi bi-arrow-up"></i> +<?php echo $total_bookings; ?></span>
                                </div>
                                <h3 class="fw-bold mb-0"><?php echo $total_bookings; ?></h3>
                                <p class="text-secondary-emphasis text-uppercase small fw-semibold mb-0">Total Bookings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <span class="stat-trend"><i class="bi bi-calendar"></i> Upcoming</span>
                                </div>
                                <h3 class="fw-bold mb-0"><?php echo $upcoming_bookings; ?></h3>
                                <p class="text-secondary-emphasis text-uppercase small fw-semibold mb-0">Upcoming</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <span class="stat-trend"><i class="bi bi-star"></i> Completed</span>
                                </div>
                                <h3 class="fw-bold mb-0"><?php echo $completed_bookings; ?></h3>
                                <p class="text-secondary-emphasis text-uppercase small fw-semibold mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                    <span class="stat-trend"><i class="bi bi-exclamation"></i> Cancelled</span>
                                </div>
                                <h3 class="fw-bold mb-0"><?php echo $cancelled_bookings; ?></h3>
                                <p class="text-secondary-emphasis text-uppercase small fw-semibold mb-0">Cancelled</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- UPCOMING MATCHES SECTION -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0"><i class="bi bi-calendar-week text-primary me-2"></i>Upcoming Matches</h4>
                                <a href="match.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="row g-3 mb-4">
                    <?php if ($upcoming_matches_result && mysqli_num_rows($upcoming_matches_result) > 0): ?>
                        <?php while ($match = mysqli_fetch_assoc($upcoming_matches_result)): ?>
                            <div class="col-md-4">
                                <div class="card match-card match-<?php echo $match['match_type']; ?> shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($match['team1_name']); ?></h5>
                                            <span class="badge bg-<?php echo $match['match_type'] == 'premium' ? 'warning' : ($match['match_type'] == 'standard' ? 'primary' : 'success'); ?> text-dark">
                                                <?php echo ucfirst($match['match_type']); ?>
                                            </span>
                                        </div>
                                        <p class="text-secondary mb-2">vs <?php echo htmlspecialchars($match['team2_name']); ?></p>
                                        <div class="small text-secondary mb-2">
                                            <i class="bi bi-calendar3 me-1"></i> <?php echo date('d M Y', strtotime($match['match_date'])); ?> •
                                            <i class="bi bi-clock ms-2 me-1"></i><?php echo date('h:i A', strtotime($match['match_time'])); ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-primary">₹<?php echo $match['price']; ?></span>
                                            <a href="booking_turnament.php?match_id=<?php echo $match['id']; ?>" class="btn btn-sm btn-outline-primary">Book Now</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle"></i> No upcoming matches available. Check back later!
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RECENT BOOKINGS SECTION (Combined Match & Ground Bookings) -->
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Recent Bookings</h4>
                                <a href="booking_details.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Item</th>
                                <th>Date & Time</th>
                                <th>Details</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_bookings_result && mysqli_num_rows($recent_bookings_result) > 0): ?>
                                <?php while ($booking = mysqli_fetch_assoc($recent_bookings_result)): 
                                    $type_class = $booking['booking_type'] == 'match' ? 'type-match' : 'type-ground';
                                    $type_icon = $booking['booking_type'] == 'match' ? 'bi-trophy' : 'bi-building';
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="booking-type-badge <?php echo $type_class; ?>">
                                                <i class="bi <?php echo $type_icon; ?>"></i> <?php echo ucfirst($booking['booking_type']); ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold">
                                            <?php echo htmlspecialchars($booking['item_name']); ?>
                                            <div class="small text-secondary"><?php echo htmlspecialchars($booking['location']); ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($booking['booking_date'])); ?><br>
                                            <small class="text-secondary"><?php echo date('h:i A', strtotime($booking['booking_time'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if($booking['booking_type'] == 'match'): ?>
                                                <span class="badge bg-info">Seat: <?php echo $booking['seat_number']; ?></span>
                                                <div class="small"><?php echo $booking['slots_booked']; ?> slot(s)</div>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?php echo $booking['duration']; ?> hr(s)</span>
                                                <div class="small"><?php echo $booking['slots_booked']; ?> players</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-success">
                                            ₹<?php echo number_format($booking['total_amount'], 0); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                                                <i class="bi bi-<?php echo $booking['booking_status'] == 'confirmed' ? 'check-circle' : ($booking['booking_status'] == 'pending' ? 'clock' : ($booking['booking_status'] == 'completed' ? 'trophy' : 'x-circle')); ?>"></i>
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($booking['booking_type'] == 'match'): ?>
                                            <a href="booking_turnament.php?match_id=<?php echo (int) $booking['item_id']; ?>" class="action-btn action-btn-view">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <a href="Ground_details.php?id=<?php echo (int) $booking['item_id']; ?>" class="action-btn action-btn-view">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-secondary py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No bookings yet. Book your first match or ground now!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- FEATURES & ADVANTAGES SECTION -->
                <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                    <h4 class="fw-bold mb-0"><i class="bi bi-stars text-primary me-2"></i>Why Choose Box Cricket?</h4>
                    <span class="text-secondary">Premium experience guaranteed</span>
                </div>

                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="card feature-card shadow-sm">
                            <div class="card-body text-center">
                                <div class="feature-icon bg-primary bg-opacity-10 text-primary mx-auto">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <h5 class="fw-bold mb-2">24/7 Availability</h5>
                                <p class="small text-secondary mb-0">Book slots anytime, day or night. We're always open for you.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card feature-card shadow-sm">
                            <div class="card-body text-center">
                                <div class="feature-icon bg-success bg-opacity-10 text-success mx-auto">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Professional Grounds</h5>
                                <p class="small text-secondary mb-0">International standard pitches with floodlights and seating.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card feature-card shadow-sm">
                            <div class="card-body text-center">
                                <div class="feature-icon bg-warning bg-opacity-10 text-warning mx-auto">
                                    <i class="bi bi-trophy"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Weekly Tournaments</h5>
                                <p class="small text-secondary mb-0">Compete with other teams and win exciting prizes.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card feature-card shadow-sm">
                            <div class="card-body text-center">
                                <div class="feature-icon bg-info bg-opacity-10 text-info mx-auto">
                                    <i class="bi bi-wifi"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Free Equipment</h5>
                                <p class="small text-secondary mb-0">Bats, balls, pads provided. Just bring your team!</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advantage Banner -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="bg-primary bg-opacity-10 p-4 rounded-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h5 class="fw-bold mb-2"><i class="bi bi-gem text-primary me-2"></i>Box Cricket Advantage</h5>
                                    <p class="text-secondary mb-md-0">Join 5000+ active players • 50+ tournaments every month • 5 star rated grounds • 24/7 customer support</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php include 'footer.php'; ?>
