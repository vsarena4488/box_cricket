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

if (!isset($con) || !$con) {
    die("Database connection failed. Please check your configuration.");
}

// Check if admin is logged in
if (function_exists('require_admin_login')) {
    require_admin_login();
} else {
    if (!isset($_SESSION['admin']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../gest/login.php");
        exit();
    }
}

// Get admin details
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['fullname'] ?? 'Admin User';
$admin_email = $_SESSION['admin'] ?? 'admin@boxcricket.com';
$admin_id = $_SESSION['user_id'] ?? 0;

// ============================================
// HANDLE FORM SUBMISSIONS
// ============================================

// Add Match
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['add_match'])) {
    $team1_name = mysqli_real_escape_string($con, $_POST['team1_name']);
    $team2_name = mysqli_real_escape_string($con, $_POST['team2_name']);
    $match_type = mysqli_real_escape_string($con, $_POST['match_type']);
    $match_date = mysqli_real_escape_string($con, $_POST['match_date']);
    $match_time = mysqli_real_escape_string($con, $_POST['match_time']);
    $price = (float)$_POST['price'];
    $venue = mysqli_real_escape_string($con, $_POST['venue']);
    $total_slots = (int)$_POST['total_slots'];
    $available_slots = $total_slots;
    $status = 'upcoming';

    $query = "INSERT INTO matches (team1_name, team2_name, match_type, match_date, match_time, price, venue, total_slots, available_slots, status) 
              VALUES ('$team1_name', '$team2_name', '$match_type', '$match_date', '$match_time', $price, '$venue', $total_slots, $available_slots, '$status')";

    if (mysqli_query($con, $query)) {
        $success_msg = "Match added successfully!";
        header("Location: turnament_details.php?msg=added");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Update Match
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['update_match'])) {
    $id = (int)$_POST['match_id'];
    $team1_name = mysqli_real_escape_string($con, $_POST['team1_name']);
    $team2_name = mysqli_real_escape_string($con, $_POST['team2_name']);
    $match_type = mysqli_real_escape_string($con, $_POST['match_type']);
    $match_date = mysqli_real_escape_string($con, $_POST['match_date']);
    $match_time = mysqli_real_escape_string($con, $_POST['match_time']);
    $price = (float)$_POST['price'];
    $venue = mysqli_real_escape_string($con, $_POST['venue']);
    $total_slots = (int)$_POST['total_slots'];
    $status = mysqli_real_escape_string($con, $_POST['status']);

    $query = "UPDATE matches SET 
              team1_name='$team1_name', 
              team2_name='$team2_name', 
              match_type='$match_type', 
              match_date='$match_date', 
              match_time='$match_time', 
              price=$price, 
              venue='$venue', 
              total_slots=$total_slots,
              status='$status' 
              WHERE id=$id";

    if (mysqli_query($con, $query)) {
        $success_msg = "Match updated successfully!";
        header("Location: turnament_details.php?msg=updated");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Delete Match
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $query = "DELETE FROM matches WHERE id = $id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Match deleted successfully!";
        header("Location: turnament_details.php?msg=deleted");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Show message from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added') $success_msg = "Match added successfully!";
    if ($_GET['msg'] == 'updated') $success_msg = "Match updated successfully!";
    if ($_GET['msg'] == 'deleted') $success_msg = "Match deleted successfully!";
}

// ============================================
// GET DATA FOR DISPLAY
// ============================================

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'all';

// Build query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(team1_name LIKE '%$search%' OR team2_name LIKE '%$search%' OR venue LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $where_conditions[] = "status = '$status_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total matches count
$total_query = "SELECT COUNT(*) as total FROM matches $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_matches = $total_result ? mysqli_fetch_assoc($total_result)['total'] : 0;
$total_pages = $total_matches > 0 ? ceil($total_matches / $limit) : 1;

// Get matches with pagination
$query = "SELECT * FROM matches $where_clause ORDER BY match_date ASC, match_time ASC LIMIT $offset, $limit";
$matches_result = mysqli_query($con, $query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
                    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN match_type = 'premium' THEN 1 ELSE 0 END) as premium,
                    SUM(CASE WHEN match_type = 'standard' THEN 1 ELSE 0 END) as standard,
                    SUM(CASE WHEN match_type = 'practice' THEN 1 ELSE 0 END) as practice,
                    SUM(available_slots) as total_available_slots
                FROM matches";
$stats_result = mysqli_query($con, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total' => 0,
    'upcoming' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'premium' => 0,
    'standard' => 0,
    'practice' => 0,
    'total_available_slots' => 0
];

// Get single match for edit/view
$edit_match = null;
if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $edit_query = "SELECT * FROM matches WHERE id = $id";
    $edit_result = mysqli_query($con, $edit_query);
    $edit_match = $edit_result ? mysqli_fetch_assoc($edit_result) : null;
}

$view_match = null;
if (isset($_GET['view_id'])) {
    $id = (int)$_GET['view_id'];
    $view_query = "SELECT * FROM matches WHERE id = $id";
    $view_result = mysqli_query($con, $view_query);
    $view_match = $view_result ? mysqli_fetch_assoc($view_result) : null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Manage Tournaments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

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

        .page-title h2 {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .page-title p {
            font-size: 13px;
            color: #6c757d;
            margin: 0;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            border-radius: 40px;
            background: #f8f9fa;
            cursor: pointer;
            text-decoration: none;
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
        }

        .profile-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .profile-role {
            font-size: 11px;
            color: #6c757d;
        }

        .content-container {
            padding: 30px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0d6efd;
        }

        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title h4 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
            font-weight: 600;
            font-size: 14px;
            padding: 12px;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            font-size: 14px;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
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

        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-edit {
            background: #e7f1ff;
            color: #0d6efd;
        }

        .btn-delete {
            background: #f8d7da;
            color: #dc3545;
        }

        .btn-view {
            background: #e3f9e5;
            color: #198754;
        }

        .btn-icon:hover {
            transform: scale(1.05);
            filter: brightness(0.95);
        }

        .match-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
        }

        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(13, 110, 253, 0.15);
        }

        .search-filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            max-width: 300px;
            position: relative;
        }

        .search-box input {
            padding-left: 35px;
            border-radius: 25px;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 6px 16px;
            border-radius: 25px;
            border: 1px solid #dee2e6;
            background: white;
            text-decoration: none;
            color: #6c757d;
        }

        .filter-btn.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
        }

        .pagination-btn.active {
            background: #0d6efd;
            color: white;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: #0d6efd;
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .search-filter-bar {
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
            }

            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

    <div class="admin-wrapper">
        <?php include 'nevbar.php'; ?>

        <div class="main-content">
            <div class="top-navbar">
                <div class="page-title">
                    <h2>Tournament Management</h2>
                    <p>Create, manage, and track all cricket tournaments</p>
                </div>
                <div class="navbar-right">
                    <a href="profile.php" class="profile-trigger text-decoration-none">
                        <div class="profile-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                        <div class="profile-info d-none d-sm-block">
                            <div class="profile-name"><?php echo htmlspecialchars($admin_name); ?></div>
                            <div class="profile-role">Administrator</div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="content-container">

                <!-- Success/Error Messages -->
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="text-muted">Total Matches</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-warning"><?php echo $stats['upcoming']; ?></div>
                        <div class="text-muted">Upcoming</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-success"><?php echo $stats['ongoing']; ?></div>
                        <div class="text-muted">Ongoing</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-info"><?php echo $stats['completed']; ?></div>
                        <div class="text-muted">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-danger"><?php echo $stats['premium']; ?></div>
                        <div class="text-muted">Premium</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-primary"><?php echo $stats['total_available_slots']; ?></div>
                        <div class="text-muted">Available Slots</div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mb-4">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMatchModal">
                        <i class="bi bi-plus-circle me-2"></i>Create New Match
                    </button>
                </div>

                <!-- All Matches Table -->
                <div class="table-container">
                    <div class="section-title">
                        <h4><i class="bi bi-list-ul me-2"></i>All Matches</h4>
                        <div>
                            <form method="GET" action="turnament_details.php" class="d-inline">
                                <select name="status" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Matches</option>
                                    <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="ongoing" <?php echo $status_filter == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            </form>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="search-filter-bar">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <form method="GET" action="turnament_details.php">
                                <input type="text" name="search" class="form-control" placeholder="Search by team or venue..."
                                    value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>

                                <th>ID</th>
                                <th>Match</th>
                                <th>Type</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Price</th>
                                <th>Slots</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </thead>
                            <tbody>
                                <?php if ($matches_result && mysqli_num_rows($matches_result) > 0): ?>
                                    <?php while ($match = mysqli_fetch_assoc($matches_result)):
                                        $status_class = $match['status'] == 'upcoming' ? 'badge-info' : ($match['status'] == 'ongoing' ? 'badge-warning' : 'badge-success');
                                        $type_class = $match['match_type'] == 'premium' ? 'badge-danger' : ($match['match_type'] == 'standard' ? 'badge-primary' : 'badge-secondary');
                                    ?>
                                        <tr>
                                            <td><?php echo $match['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($match['team1_name']); ?></strong> vs
                                                <strong><?php echo htmlspecialchars($match['team2_name']); ?></strong>
                                            </td>
                                            <td><span class="badge <?php echo $type_class; ?>"><?php echo ucfirst($match['match_type']); ?></span></td>
                                            <td><?php echo date('d M Y', strtotime($match['match_date'])); ?><br><small><?php echo date('h:i A', strtotime($match['match_time'])); ?></small></td>
                                            <td><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($match['venue']); ?></td>
                                            <td class="fw-bold text-primary">₹<?php echo number_format($match['price'], 0); ?></td>
                                            <td><?php echo $match['available_slots']; ?>/<?php echo $match['total_slots']; ?></td>
                                            <td><span class="badge-status <?php echo $status_class; ?>"><?php echo ucfirst($match['status']); ?></span></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="turnament_details.php?view_id=<?php echo $match['id']; ?>" class="btn-icon btn-view"><i class="bi bi-eye"></i></a>
                                                    <a href="turnament_details.php?edit_id=<?php echo $match['id']; ?>" class="btn-icon btn-edit"><i class="bi bi-pencil"></i></a>
                                                    <a href="turnament_details.php?delete_id=<?php echo $match['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Are you sure you want to delete this match?')"><i class="bi bi-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-5">
                                            <i class="bi bi-trophy fs-1 d-block mb-3"></i>
                                            No matches found
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                        class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <span class="px-2 text-muted">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== ADD MATCH MODAL ========== -->
    <div class="modal fade" id="addMatchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create New Match</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="turnament_details.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Team 1 Name *</label>
                                <input type="text" class="form-control" name="team1_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Team 2 Name *</label>
                                <input type="text" class="form-control" name="team2_name" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Match Type</label>
                                <select class="form-select" name="match_type">
                                    <option value="premium">Premium</option>
                                    <option value="standard">Standard</option>
                                    <option value="practice">Practice</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Match Date *</label>
                                <input type="date" class="form-control" name="match_date" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Match Time *</label>
                                <input type="time" class="form-control" name="match_time" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Price (₹) *</label>
                                <input type="number" class="form-control" name="price" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Venue</label>
                                <input type="text" class="form-control" name="venue" value="Box Cricket Ground">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Total Slots</label>
                                <input type="number" class="form-control" name="total_slots" value="10">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_match" class="btn btn-primary">Create Match</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========== EDIT MATCH MODAL ========== -->
    <?php if ($edit_match): ?>
        <div class="modal fade show" id="editMatchModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Match</h5>
                        <a href="turnament_details.php" class="btn-close btn-close-white"></a>
                    </div>
                    <form method="POST" action="turnament_details.php">
                        <div class="modal-body">
                            <input type="hidden" name="match_id" value="<?php echo $edit_match['id']; ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Team 1 Name</label>
                                    <input type="text" class="form-control" name="team1_name" value="<?php echo htmlspecialchars($edit_match['team1_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Team 2 Name</label>
                                    <input type="text" class="form-control" name="team2_name" value="<?php echo htmlspecialchars($edit_match['team2_name']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Match Type</label>
                                    <select class="form-select" name="match_type">
                                        <option value="premium" <?php echo $edit_match['match_type'] == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                        <option value="standard" <?php echo $edit_match['match_type'] == 'standard' ? 'selected' : ''; ?>>Standard</option>
                                        <option value="practice" <?php echo $edit_match['match_type'] == 'practice' ? 'selected' : ''; ?>>Practice</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Match Date</label>
                                    <input type="date" class="form-control" name="match_date" value="<?php echo $edit_match['match_date']; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Match Time</label>
                                    <input type="time" class="form-control" name="match_time" value="<?php echo $edit_match['match_time']; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Price (₹)</label>
                                    <input type="number" class="form-control" name="price" value="<?php echo $edit_match['price']; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Venue</label>
                                    <input type="text" class="form-control" name="venue" value="<?php echo htmlspecialchars($edit_match['venue']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Total Slots</label>
                                    <input type="number" class="form-control" name="total_slots" value="<?php echo $edit_match['total_slots']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="upcoming" <?php echo $edit_match['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                        <option value="ongoing" <?php echo $edit_match['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                        <option value="completed" <?php echo $edit_match['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="turnament_details.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="update_match" class="btn btn-primary">Update Match</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- ========== VIEW MATCH MODAL ========== -->
    <?php if ($view_match): ?>
        <div class="modal fade show" id="viewMatchModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-trophy-fill me-2"></i>Match Details</h5>
                        <a href="turnament_details.php" class="btn-close btn-close-white"></a>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12 text-center mb-3">
                                <h3><?php echo htmlspecialchars($view_match['team1_name']); ?> vs <?php echo htmlspecialchars($view_match['team2_name']); ?></h3>
                                <span class="badge bg-<?php echo $view_match['match_type'] == 'premium' ? 'danger' : ($view_match['match_type'] == 'standard' ? 'primary' : 'secondary'); ?>">
                                    <?php echo ucfirst($view_match['match_type']); ?>
                                </span>
                            </div>
                            <hr>
                            <div class="col-md-6">
                                <p><strong><i class="bi bi-calendar"></i> Date:</strong> <?php echo date('l, d F Y', strtotime($view_match['match_date'])); ?></p>
                                <p><strong><i class="bi bi-clock"></i> Time:</strong> <?php echo date('h:i A', strtotime($view_match['match_time'])); ?></p>
                                <p><strong><i class="bi bi-geo-alt"></i> Venue:</strong> <?php echo htmlspecialchars($view_match['venue']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><i class="bi bi-currency-rupee"></i> Price:</strong> ₹<?php echo number_format($view_match['price'], 0); ?> per slot</p>
                                <p><strong><i class="bi bi-people"></i> Slots:</strong> <?php echo $view_match['available_slots']; ?>/<?php echo $view_match['total_slots']; ?> available</p>
                                <p><strong><i class="bi bi-tag"></i> Status:</strong>
                                    <span class="badge-status <?php echo $view_match['status'] == 'upcoming' ? 'badge-info' : ($view_match['status'] == 'ongoing' ? 'badge-warning' : 'badge-success'); ?>">
                                        <?php echo ucfirst($view_match['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="turnament_details.php" class="btn btn-secondary">Close</a>
                        <a href="turnament_details.php?edit_id=<?php echo $view_match['id']; ?>" class="btn btn-primary">Edit Match</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>