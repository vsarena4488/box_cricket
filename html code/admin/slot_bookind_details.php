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

// Update Booking Status
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') == 'POST' && isset($_POST['update_booking_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $booking_status = mysqli_real_escape_string($con, $_POST['booking_status']);
    $payment_status = mysqli_real_escape_string($con, $_POST['payment_status']);
    
    $query = "UPDATE ground_bookings SET booking_status='$booking_status', payment_status='$payment_status' WHERE id=$booking_id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Booking status updated successfully!";
        header("Location: slot_bookind_details.php?msg=updated");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Delete Booking
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $query = "DELETE FROM ground_bookings WHERE id = $id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Booking deleted successfully!";
        header("Location: slot_bookind_details.php?msg=deleted");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Confirm Booking
if (isset($_GET['confirm_id'])) {
    $id = (int)$_GET['confirm_id'];
    $query = "UPDATE ground_bookings SET booking_status='confirmed' WHERE id = $id";
    if (mysqli_query($con, $query)) {
        header("Location: slot_bookind_details.php?msg=updated");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Cancel Booking
if (isset($_GET['cancel_id'])) {
    $id = (int)$_GET['cancel_id'];
    $query = "UPDATE ground_bookings SET booking_status='cancelled' WHERE id = $id";
    if (mysqli_query($con, $query)) {
        header("Location: slot_bookind_details.php?msg=updated");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Show message from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $success_msg = "Booking updated successfully!";
    if ($_GET['msg'] == 'deleted') $success_msg = "Booking deleted successfully!";
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
$ground_filter = isset($_GET['ground']) ? (int)$_GET['ground'] : 0;

// Build query
$where_conditions = ["gb.user_id = u.id", "gb.ground_id = gr.id"];
if (!empty($search)) {
    $where_conditions[] = "(u.fullname LIKE '%$search%' OR gr.name LIKE '%$search%' OR gb.id LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $where_conditions[] = "gb.booking_status = '$status_filter'";
}
if ($ground_filter > 0) {
    $where_conditions[] = "gb.ground_id = $ground_filter";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total bookings count
$total_query = "SELECT COUNT(*) as total FROM ground_bookings gb 
                INNER JOIN users u ON gb.user_id = u.id 
                INNER JOIN grounds gr ON gb.ground_id = gr.id 
                $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_bookings = $total_result ? mysqli_fetch_assoc($total_result)['total'] : 0;
$total_pages = $total_bookings > 0 ? ceil($total_bookings / $limit) : 1;

// Get bookings with pagination
$query = "SELECT gb.*, u.fullname as user_name, u.email as user_email, u.phone as user_phone,
          gr.name as ground_name, gr.location as ground_location, gr.price_per_hour
          FROM ground_bookings gb 
          INNER JOIN users u ON gb.user_id = u.id 
          INNER JOIN grounds gr ON gb.ground_id = gr.id 
          $where_clause 
          ORDER BY gb.booking_date DESC, gb.start_time DESC 
          LIMIT $offset, $limit";
$bookings_result = mysqli_query($con, $query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as paid,
                    SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as unpaid
                FROM ground_bookings";
$stats_result = mysqli_query($con, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total' => 0, 'confirmed' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0, 'paid' => 0, 'unpaid' => 0
];

// Get all grounds for filter
$grounds_query = "SELECT id, name FROM grounds WHERE status = 'active' ORDER BY name";
$grounds_result = mysqli_query($con, $grounds_query);

// Get single booking for edit/view
$edit_booking = null;
if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $edit_query = "SELECT * FROM ground_bookings WHERE id = $id";
    $edit_result = mysqli_query($con, $edit_query);
    $edit_booking = $edit_result ? mysqli_fetch_assoc($edit_result) : null;
}

$view_booking = null;
if (isset($_GET['view_id'])) {
    $id = (int)$_GET['view_id'];
    $view_query = "SELECT gb.*, u.fullname as user_name, u.email as user_email, u.phone as user_phone,
                   gr.name as ground_name, gr.location as ground_location, gr.price_per_hour
                   FROM ground_bookings gb 
                   INNER JOIN users u ON gb.user_id = u.id 
                   INNER JOIN grounds gr ON gb.ground_id = gr.id 
                   WHERE gb.id = $id";
    $view_result = mysqli_query($con, $view_query);
    $view_booking = $view_result ? mysqli_fetch_assoc($view_result) : null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Slot Bookings Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; }
        .admin-wrapper { display: flex; min-height: 100vh; }
        .main-content { flex: 1; margin-left: 280px; transition: all 0.3s; }
        .top-navbar {
            background: white; padding: 12px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;
        }
        .page-title h2 { font-size: 22px; font-weight: 600; color: #333; margin: 0; }
        .page-title p { font-size: 13px; color: #6c757d; margin: 0; }
        .navbar-right { display: flex; align-items: center; gap: 25px; }
        .profile-trigger {
            display: flex; align-items: center; gap: 12px; padding: 8px 15px;
            border-radius: 40px; background: #f8f9fa; cursor: pointer; text-decoration: none;
        }
        .profile-trigger:hover { background: #e9ecef; }
        .profile-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: #0d6efd;
            display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;
        }
        .profile-name { font-weight: 600; color: #333; font-size: 14px; }
        .profile-role { font-size: 11px; color: #6c757d; }
        .content-container { padding: 30px; }
        .stats-cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 15px; padding: 20px;
            text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card h3 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-card p { color: #6c757d; margin: 0; font-size: 13px; }
        .text-success { color: #28a745 !important; }
        .text-warning { color: #ffc107 !important; }
        .text-info { color: #17a2b8 !important; }
        .text-danger { color: #dc3545 !important; }
        .table-container {
            background: white; border-radius: 20px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .section-title {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .section-title h4 { font-size: 18px; font-weight: 600; color: #333; margin: 0; }
        .filter-bar {
            display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .filter-select {
            padding: 8px 15px; border-radius: 25px; border: 1px solid #dee2e6;
            background: white; font-size: 14px;
        }
        .search-box { position: relative; flex: 1; max-width: 300px; }
        .search-box input { padding-left: 35px; border-radius: 25px; border: 1px solid #dee2e6; }
        .search-box i {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); color: #6c757d;
        }
        .table thead th {
            background: #f8f9fa; border-bottom: 2px solid #e9ecef;
            color: #495057; font-weight: 600; font-size: 13px; padding: 12px;
        }
        .table tbody td { padding: 12px; vertical-align: middle; font-size: 14px; }
        .badge-status {
            padding: 4px 10px; border-radius: 30px; font-size: 11px; font-weight: 500;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .btn-icon {
            padding: 5px 8px; border-radius: 6px; font-size: 12px;
            border: none; cursor: pointer; transition: all 0.2s;
            display: inline-block; text-decoration: none;
        }
        .btn-edit { background: #e7f1ff; color: #0d6efd; }
        .btn-delete { background: #f8d7da; color: #dc3545; }
        .btn-view { background: #e3f9e5; color: #198754; }
        .btn-confirm { background: #d4edda; color: #155724; }
        .btn-cancel { background: #fff3cd; color: #856404; }
        .btn-icon:hover { transform: scale(1.05); filter: brightness(0.95); }
        .pagination-container {
            display: flex; justify-content: center; gap: 10px; margin-top: 25px; flex-wrap: wrap;
        }
        .pagination-btn {
            padding: 8px 12px; border: 1px solid #dee2e6; background: white;
            border-radius: 8px; text-decoration: none; color: #333;
        }
        .pagination-btn.active { background: #0d6efd; color: white; }
        .modal-content { border-radius: 15px; border: none; }
        .modal-header { border-bottom: 1px solid #e9ecef; padding: 20px 25px; }
        .alert { border-radius: 10px; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .filter-bar { flex-direction: column; }
            .search-box { max-width: 100%; }
            .stats-cards { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <?php include 'nevbar.php'; ?>

    <div class="main-content">
        <div class="top-navbar">
            <div class="page-title">
                <h2>Slot Bookings Management</h2>
                <p>View and manage all slot bookings for regular matches.</p>
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
            <?php if(isset($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3 class="text-primary"><?php echo $stats['total']; ?></h3>
                    <p>Total Bookings</p>
                </div>
                <div class="stat-card">
                    <h3 class="text-success"><?php echo $stats['confirmed']; ?></h3>
                    <p>Confirmed</p>
                </div>
                <div class="stat-card">
                    <h3 class="text-warning"><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <h3 class="text-info"><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="stat-card">
                    <h3 class="text-danger"><?php echo $stats['cancelled']; ?></h3>
                    <p>Cancelled</p>
                </div>
                <div class="stat-card">
                    <h3 class="text-success"><?php echo $stats['paid']; ?></h3>
                    <p>Paid</p>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="table-container">
                <div class="section-title">
                    <h4><i class="bi bi-calendar-check-fill me-2"></i>All Slot Bookings</h4>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <form method="GET" action="slot_bookind_details.php">
                            <input type="text" name="search" class="form-control" placeholder="Search by user, ground, or booking ID..." 
                                   value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="hidden" name="ground" value="<?php echo $ground_filter; ?>">
                        </form>
                    </div>
                    <form method="GET" action="slot_bookind_details.php" class="d-flex gap-2 flex-wrap">
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <select name="ground" class="filter-select" onchange="this.form.submit()">
                            <option value="0" <?php echo $ground_filter == 0 ? 'selected' : ''; ?>>All Grounds</option>
                            <?php while($ground = mysqli_fetch_assoc($grounds_result)): ?>
                                <option value="<?php echo $ground['id']; ?>" <?php echo $ground_filter == $ground['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ground['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Ground</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($bookings_result && mysqli_num_rows($bookings_result) > 0): ?>
                                <?php while($booking = mysqli_fetch_assoc($bookings_result)): 
                                    $duration = $booking['duration_hours'] . ' hr' . ($booking['duration_hours'] > 1 ? 's' : '');
                                    $status_class = $booking['booking_status'] == 'confirmed' ? 'badge-success' : 
                                                   ($booking['booking_status'] == 'pending' ? 'badge-warning' : 
                                                   ($booking['booking_status'] == 'completed' ? 'badge-info' : 'badge-danger'));
                                    $payment_class = $booking['payment_status'] == 'completed' ? 'badge-success' : 'badge-warning';
                                ?>
                                <tr>
                                    <td>#<?php echo $booking['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($booking['user_name']); ?></strong><br><small><?php echo htmlspecialchars($booking['user_email']); ?></small></td>
                                    <td><?php echo htmlspecialchars($booking['ground_name']); ?></td>
                                    <td><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($booking['start_time'])); ?></td>
                                    <td><?php echo $duration; ?></td>
                                    <td><strong>₹<?php echo number_format($booking['total_amount'], 0); ?></strong></td>
                                    <td><span class="badge-status <?php echo $status_class; ?>"><?php echo ucfirst($booking['booking_status']); ?></span></td>
                                    <td><span class="badge-status <?php echo $payment_class; ?>"><?php echo ucfirst($booking['payment_status']); ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="slot_bookind_details.php?view_id=<?php echo $booking['id']; ?>" class="btn-icon btn-view"><i class="bi bi-eye"></i></a>
                                            <a href="slot_bookind_details.php?edit_id=<?php echo $booking['id']; ?>" class="btn-icon btn-edit"><i class="bi bi-pencil"></i></a>
                                            <?php if($booking['booking_status'] == 'pending'): ?>
                                                <a href="slot_bookind_details.php?confirm_id=<?php echo $booking['id']; ?>" class="btn-icon btn-confirm"><i class="bi bi-check-circle"></i></a>
                                            <?php endif; ?>
                                            <?php if($booking['booking_status'] != 'cancelled' && $booking['booking_status'] != 'completed'): ?>
                                                <a href="slot_bookind_details.php?cancel_id=<?php echo $booking['id']; ?>" class="btn-icon btn-cancel"><i class="bi bi-x-circle"></i></a>
                                            <?php endif; ?>
                                            <a href="slot_bookind_details.php?delete_id=<?php echo $booking['id']; ?>" class="btn-icon btn-delete"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-5">
                                        <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                                        No bookings found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="pagination-container">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&ground=<?php echo $ground_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == 1 || $i == $total_pages || ($i >= $page-1 && $i <= $page+1)): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&ground=<?php echo $ground_filter; ?>&search=<?php echo urlencode($search); ?>" 
                               class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php elseif($i == $page-2 || $i == $page+2): ?>
                            <span class="px-2 text-muted">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&ground=<?php echo $ground_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Booking Modal -->
<?php if($edit_booking): ?>
<div class="modal fade show" id="editBookingModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Edit Booking</h5>
                <a href="slot_bookind_details.php" class="btn-close btn-close-white"></a>
            </div>
            <form method="POST" action="slot_bookind_details.php">
                <div class="modal-body">
                    <input type="hidden" name="booking_id" value="<?php echo $edit_booking['id']; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Booking Status</label>
                            <select class="form-select" name="booking_status">
                                <option value="confirmed" <?php echo $edit_booking['booking_status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="pending" <?php echo $edit_booking['booking_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $edit_booking['booking_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $edit_booking['booking_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Status</label>
                            <select class="form-select" name="payment_status">
                                <option value="pending" <?php echo $edit_booking['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $edit_booking['payment_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $edit_booking['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $edit_booking['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Booking Date</label>
                            <input type="date" class="form-control" value="<?php echo $edit_booking['booking_date']; ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" value="<?php echo $edit_booking['start_time']; ?>" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Special Request</label>
                        <textarea class="form-control" rows="2" disabled><?php echo htmlspecialchars($edit_booking['special_request']); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="slot_bookind_details.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_booking_status" class="btn btn-primary">Update Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- View Booking Modal -->
<?php if($view_booking): ?>
<div class="modal fade show" id="viewBookingModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-info-circle-fill me-2"></i>Booking Details</h5>
                <a href="slot_bookind_details.php" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="bi bi-ticket"></i> Booking ID:</strong> #<?php echo $view_booking['id']; ?></p>
                        <p><strong><i class="bi bi-person"></i> User:</strong> <?php echo htmlspecialchars($view_booking['user_name']); ?></p>
                        <p><strong><i class="bi bi-envelope"></i> Email:</strong> <?php echo htmlspecialchars($view_booking['user_email']); ?></p>
                        <p><strong><i class="bi bi-phone"></i> Phone:</strong> <?php echo htmlspecialchars($view_booking['user_phone']); ?></p>
                        <p><strong><i class="bi bi-building"></i> Ground:</strong> <?php echo htmlspecialchars($view_booking['ground_name']); ?></p>
                        <p><strong><i class="bi bi-geo-alt"></i> Location:</strong> <?php echo htmlspecialchars($view_booking['ground_location']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="bi bi-calendar"></i> Date:</strong> <?php echo date('l, d F Y', strtotime($view_booking['booking_date'])); ?></p>
                        <p><strong><i class="bi bi-clock"></i> Time:</strong> <?php echo date('h:i A', strtotime($view_booking['start_time'])); ?></p>
                        <p><strong><i class="bi bi-hourglass-split"></i> Duration:</strong> <?php echo $view_booking['duration_hours']; ?> hour(s)</p>
                        <p><strong><i class="bi bi-cash"></i> Price per Hour:</strong> ₹<?php echo number_format($view_booking['price_per_hour'], 0); ?></p>
                        <p><strong><i class="bi bi-currency-rupee"></i> Total Amount:</strong> <strong class="text-primary fs-5">₹<?php echo number_format($view_booking['total_amount'], 0); ?></strong></p>
                        <p><strong><i class="bi bi-credit-card"></i> Payment Method:</strong> <?php echo strtoupper($view_booking['payment_method']); ?></p>
                        <p><strong><i class="bi bi-check-circle"></i> Payment Status:</strong> 
                            <span class="badge-status <?php echo $view_booking['payment_status'] == 'completed' ? 'badge-success' : 'badge-warning'; ?>">
                                <?php echo ucfirst($view_booking['payment_status']); ?>
                            </span>
                        </p>
                        <p><strong><i class="bi bi-tag"></i> Booking Status:</strong> 
                            <span class="badge-status <?php echo $view_booking['booking_status'] == 'confirmed' ? 'badge-success' : ($view_booking['booking_status'] == 'pending' ? 'badge-warning' : 'badge-danger'); ?>">
                                <?php echo ucfirst($view_booking['booking_status']); ?>
                            </span>
                        </p>
                        <?php if($view_booking['special_request']): ?>
                            <p><strong><i class="bi bi-chat"></i> Special Request:</strong><br><?php echo nl2br(htmlspecialchars($view_booking['special_request'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="slot_bookind_details.php" class="btn btn-secondary">Close</a>
                <a href="slot_bookind_details.php?edit_id=<?php echo $view_booking['id']; ?>" class="btn btn-primary">Edit Booking</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
