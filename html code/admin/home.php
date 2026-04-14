<?php
session_start();
include_once '../gest/db_config.php';

// Check if connection exists
if (!isset($con) && isset($conn)) {
    $con = $conn;
}
if (!isset($con) && isset($connection)) {
    $con = $connection;
}

// Check admin login
if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin' ||
    !isset($_SESSION['admin']) ||
    $_SESSION['admin'] === ''
) {
    header('Location: ../gest/login.php');
    exit;
}

// Get admin details from session
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$admin_email = $_SESSION['admin'] ?? 'admin@boxcricket.com';
$admin_id = $_SESSION['user_id'] ?? 0;

// Helper function to get count
function get_count($con, $query)
{
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['count'];
    }
    return 0;
}

// Get Dashboard Statistics using COUNT
$total_users = get_count($con, "SELECT COUNT(*) as count FROM users WHERE role = 'user'");
$total_admins = get_count($con, "SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$today_matches = get_count($con, "SELECT COUNT(*) as count FROM matches WHERE match_date = CURDATE() AND status = 'upcoming'");
$active_tournaments = get_count($con, "SELECT COUNT(*) as count FROM matches WHERE status IN ('upcoming', 'ongoing')");
$total_bookings = get_count($con, "SELECT COUNT(*) as count FROM bookings");
$total_ground_bookings = get_count($con, "SELECT COUNT(*) as count FROM ground_bookings");
$new_messages = get_count($con, "SELECT COUNT(*) as count FROM contact_messages");

// Recent Ground Bookings
$recent_bookings_query = "SELECT gb.*, u.fullname, gr.name as ground_name 
                          FROM ground_bookings gb 
                          INNER JOIN users u ON gb.user_id = u.id 
                          INNER JOIN grounds gr ON gb.ground_id = gr.id 
                          ORDER BY gb.created_at DESC LIMIT 5";
$recent_bookings = mysqli_query($con, $recent_bookings_query);

// Upcoming Matches (from matches table)
$upcoming_matches_query = "SELECT * FROM matches 
                           WHERE match_date >= CURDATE() AND status = 'upcoming' 
                           ORDER BY match_date ASC LIMIT 5";
$upcoming_matches = mysqli_query($con, $upcoming_matches_query);

// Active Matches List (from matches table)
$active_tournaments_query = "SELECT * FROM matches 
                             WHERE status IN ('upcoming', 'ongoing')
                             ORDER BY match_date ASC, match_time ASC LIMIT 3";
$active_tournaments_list = mysqli_query($con, $active_tournaments_query);

// Recent Messages (from contact_messages table)
$recent_messages_query = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5";
$recent_messages = mysqli_query($con, $recent_messages_query);

// Calculate percentage changes
$user_growth = 12;
$booking_growth = 8;

// Get first letter of admin name for avatar
$admin_initial = strtoupper(substr($admin_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Google Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            overflow-x: hidden;
        }

        /* Admin Layout */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* ========== MAIN CONTENT AREA ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s;
        }

        .top-navbar {
            background: white;
            padding: 12px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-page-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        /* Admin Profile Dropdown */
        .profile-trigger-wrap {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            border-radius: 40px;
            background: #f8f9fa;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            width: auto;
        }

        .profile-trigger:hover {
            background: #e9ecef;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }

        .profile-info {
            line-height: 1.3;
            text-align: left;
        }

        .profile-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
            display: block;
        }

        .profile-role {
            font-size: 11px;
            color: #6c757d;
            display: block;
        }

        .profile-chevron {
            font-size: 18px;
            color: #6c757d;
            transition: transform 0.2s;
        }

        .profile-trigger[aria-expanded='true'] .profile-chevron {
            transform: rotate(180deg);
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 10px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            display: none;
            z-index: 999;
            padding: 8px;
        }

        .profile-dropdown.show {
            display: block;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
            text-decoration: none;
            color: #495057;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        .dropdown-item .material-icons {
            font-size: 18px;
        }

        .dropdown-item.text-danger:hover {
            background: #ffe8e8;
            color: #dc3545;
        }

        .dropdown-divider {
            height: 1px;
            background: #e9ecef;
            margin: 8px 0;
        }

        /* ========== CONTENT CONTAINER ========== */
        .content-container {
            padding: 30px;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid rgba(0, 0, 0, 0.05);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(13, 110, 253, 0.12);
            border-color: #0d6efd;
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }

        .stat-info p {
            color: #6c757d;
            margin: 0;
            font-weight: 500;
            font-size: 14px;
        }

        .stat-info small {
            font-size: 11px;
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .stat-icon.blue {
            background: #e7f1ff;
            color: #0d6efd;
        }

        .stat-icon.green {
            background: #e3f9e5;
            color: #198754;
        }

        .stat-icon.orange {
            background: #fff3cd;
            color: #ffc107;
        }

        .stat-icon.purple {
            background: #e3f0ff;
            color: #6610f2;
        }

        .stat-icon.red {
            background: #f8d7da;
            color: #dc3545;
        }

        /* Section Title */
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title h4 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
            font-weight: 600;
            font-size: 13px;
            padding: 12px;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            font-size: 14px;
        }

        .badge-status {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .card-body {
            padding: 20px;
        }

        .progress {
            height: 8px;
            border-radius: 10px;
        }

        /* Message Preview */
        .message-preview {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="admin-wrapper">
        <!-- navbar  -->
        <?php include 'nevbar.php'; ?>

        <!-- ========== MAIN CONTENT AREA ========== -->
        <div class="main-content">
            <header class="top-navbar">
                <h1 class="navbar-page-title">Dashboard</h1>
                <div class="navbar-right">
                    <a href="profile.php" class="profile-trigger text-decoration-none">
                        <div class="profile-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                        <div class="profile-info d-none d-sm-block">
                            <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                            <div class="profile-role">Administrator</div>
                        </div>
                    </a>
                </div>
            </header>

            <!-- ========== CONTENT CONTAINER ========== -->
            <div class="content-container">
                <!-- Dashboard Section -->
                <div id="dashboard-section">
                    <!-- 5 Dashboard Cards with Real Data -->
                    <div class="dashboard-cards">
                        <div class="stat-card" onclick="window.location.href='users_details.php'">
                            <div class="stat-info">
                                <h3><?php echo $total_users; ?></h3>
                                <p>Total Users</p>
                                <small class="text-success"><i class="bi bi-arrow-up"></i> +<?php echo $user_growth; ?>% from last month</small>
                            </div>
                            <div class="stat-icon blue">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                        <div class="stat-card" onclick="window.location.href='turnament_details.php'">
                            <div class="stat-info">
                                <h3><?php echo $today_matches; ?></h3>
                                <p>Box Matches Today</p>
                                <small class="text-primary"><i class="bi bi-calendar"></i> <?php echo $today_matches; ?> matches scheduled</small>
                            </div>
                            <div class="stat-icon green">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                        </div>
                        <div class="stat-card" onclick="window.location.href='turnament_details.php'">
                            <div class="stat-info">
                                <h3><?php echo $active_tournaments; ?></h3>
                                <p>Active Tournaments</p>
                                <small class="text-warning"><i class="bi bi-trophy"></i> Currently running</small>
                            </div>
                            <div class="stat-icon orange">
                                <i class="bi bi-trophy"></i>
                            </div>
                        </div>
                        <div class="stat-card" onclick="window.location.href='slot_bookind_details.php'">
                            <div class="stat-info">
                                <h3><?php echo $total_bookings + $total_ground_bookings; ?></h3>
                                <p>Total Bookings</p>
                                <small class="text-info"><i class="bi bi-ticket"></i> +<?php echo $booking_growth; ?>% this month</small>
                            </div>
                            <div class="stat-icon purple">
                                <i class="bi bi-ticket"></i>
                            </div>
                        </div>
                        <div class="stat-card" onclick="window.location.href='contect_message.php'">
                            <div class="stat-info">
                                <h3><?php echo $new_messages; ?></h3>
                                <p>New Messages</p>
                                <small class="text-danger"><i class="bi bi-envelope"></i> <?php echo $new_messages; ?> total</small>
                            </div>
                            <div class="stat-icon red">
                                <i class="bi bi-envelope"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity Section -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-container">
                                <div class="section-title">
                                    <h4><i class="bi bi-clock-history"></i> Recent Ground Bookings</h4>
                                    <a href="slot_bookind_details.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>

                                            <th>User</th>
                                            <th>Ground</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Status</th>
                                        </thead>
                                        <tbody>
                                            <?php if ($recent_bookings && mysqli_num_rows($recent_bookings) > 0): ?>
                                                <?php while ($booking = mysqli_fetch_assoc($recent_bookings)): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($booking['fullname']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($booking['ground_name']); ?></td>
                                                        <td><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></td>
                                                        <td><?php echo date('h:i A', strtotime($booking['start_time'])); ?></td>
                                                        <td>
                                                            <span class="badge-status <?php echo $booking['booking_status'] == 'confirmed' ? 'badge-success' : ($booking['booking_status'] == 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                                                <?php echo ucfirst($booking['booking_status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No recent bookings</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="table-container">
                                <div class="section-title">
                                    <h4><i class="bi bi-calendar-week"></i> Upcoming Matches</h4>
                                    <a href="turnament_details.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>

                                            <th>Match</th>
                                            <th>Teams</th>
                                            <th>Time</th>
                                            <th>Venue</th>
                                        </thead>
                                        <tbody>
                                            <?php if ($upcoming_matches && mysqli_num_rows($upcoming_matches) > 0): ?>
                                                <?php while ($match = mysqli_fetch_assoc($upcoming_matches)): ?>
                                                    <tr>
                                                        <td><?php echo ucfirst($match['match_type']); ?></td>
                                                        <td><?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></td>
                                                        <td><?php echo date('d M, h:i A', strtotime($match['match_date'] . ' ' . $match['match_time'])); ?></td>
                                                        <td><?php echo htmlspecialchars($match['venue']); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted">No upcoming matches</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tournament Status & Recent Messages -->
                    <div class="row">
                        <div class="col-md-7">
                            <div class="table-container">
                                <div class="section-title">
                                    <h4><i class="bi bi-trophy-fill"></i> Active Matches</h4>
                                    <a href="turnament_details.php" class="text-decoration-none">Manage <i class="bi bi-arrow-right"></i></a>
                                </div>
                                <div class="row">
                                    <?php if ($active_tournaments_list && mysqli_num_rows($active_tournaments_list) > 0): ?>
                                        <?php while ($match = mysqli_fetch_assoc($active_tournaments_list)):
                                            $total_slots = $match['total_slots'];
                                            $filled_slots = $total_slots - $match['available_slots'];
                                            $progress = ($filled_slots / $total_slots) * 100;
                                            $status_class = $match['status'] == 'ongoing' ? 'success' : 'warning';
                                        ?>
                                            <div class="col-md-4">
                                                <div class="card mb-3">
                                                    <div class="card-body">
                                                        <h6 class="card-title"><i class="bi bi-trophy"></i> <?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></h6>
                                                        <p class="card-text small text-muted">
                                                            <?php echo date('d M Y', strtotime($match['match_date'])); ?> | <?php echo date('h:i A', strtotime($match['match_time'])); ?>
                                                        </p>
                                                        <div class="progress mt-2">
                                                            <div class="progress-bar bg-<?php echo $status_class; ?>" style="width: <?php echo $progress; ?>%">
                                                                <?php echo round($progress); ?>%
                                                            </div>
                                                        </div>
                                                        <div class="mt-2">
                                                            <small class="text-muted">Slots left: <?php echo $match['available_slots']; ?> / <?php echo $match['total_slots']; ?></small>
                                                        </div>
                                                        <div class="mt-2">
                                                            <span class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($match['status']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <p class="text-center text-muted">No active matches</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="table-container">
                                <div class="section-title">
                                    <h4><i class="bi bi-envelope"></i> Recent Messages</h4>
                                    <a href="contect_message.php" class="text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>From</th>
                                                <th>Message</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recent_messages && mysqli_num_rows($recent_messages) > 0): ?>
                                                <?php while ($message = mysqli_fetch_assoc($recent_messages)): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($message['name']); ?></strong><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($message['email']); ?></small>
                                                        </td>
                                                        <td class="message-preview"><?php echo htmlspecialchars(substr($message['message'], 0, 50)); ?>...</td>
                                                        <td><?php echo date('d M', strtotime($message['created_at'])); ?></td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">No messages</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Profile dropdown functionality
        const profileTrigger = document.getElementById('profileTrigger');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileTrigger && profileDropdown) {
            profileTrigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const expanded = profileTrigger.getAttribute('aria-expanded') === 'true';
                profileTrigger.setAttribute('aria-expanded', !expanded);
                profileDropdown.classList.toggle('show', !expanded);
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (event) => {
                if (!profileTrigger.contains(event.target) && !profileDropdown.contains(event.target)) {
                    profileTrigger.setAttribute('aria-expanded', 'false');
                    profileDropdown.classList.remove('show');
                }
            });

            // Prevent dropdown from closing when clicking inside
            profileDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>