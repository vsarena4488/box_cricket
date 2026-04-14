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

// Delete Message
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $query = "DELETE FROM contact_messages WHERE id = $id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Message deleted successfully!";
        header("Location: contect_message.php?msg=deleted");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Show message from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'deleted') $success_msg = "Message deleted successfully!";
}

// ============================================
// GET DATA FOR DISPLAY
// ============================================

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$date_filter = isset($_GET['date_filter']) ? mysqli_real_escape_string($con, $_GET['date_filter']) : 'all';

// Build query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR message LIKE '%$search%')";
}
if ($date_filter == 'today') {
    $where_conditions[] = "DATE(created_at) = CURDATE()";
} elseif ($date_filter == 'week') {
    $where_conditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_filter == 'month') {
    $where_conditions[] = "created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total messages count
$total_query = "SELECT COUNT(*) as total FROM contact_messages $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_messages = $total_result ? mysqli_fetch_assoc($total_result)['total'] : 0;
$total_pages = $total_messages > 0 ? ceil($total_messages / $limit) : 1;

// Get messages with pagination
$query = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC LIMIT $offset, $limit";
$messages_result = mysqli_query($con, $query);

// Get statistics (based on date, not status since status doesn't exist)
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week,
                    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as this_month
                FROM contact_messages";
$stats_result = mysqli_query($con, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total' => 0,
    'today' => 0,
    'this_week' => 0,
    'this_month' => 0
];

// Get single message for view
$view_message = null;
if (isset($_GET['view_id'])) {
    $id = (int)$_GET['view_id'];
    $view_query = "SELECT * FROM contact_messages WHERE id = $id";
    $view_result = mysqli_query($con, $view_query);
    $view_message = $view_result ? mysqli_fetch_assoc($view_result) : null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Contact Messages Management</title>
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

        .content-container {
            padding: 30px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #0d6efd;
        }

        .stat-card p {
            color: #6c757d;
            margin: 0;
            font-size: 13px;
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
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 300px;
        }

        .search-box input {
            padding-left: 35px;
            border-radius: 25px;
            border: 1px solid #dee2e6;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-select {
            padding: 8px 15px;
            border-radius: 25px;
            border: 1px solid #dee2e6;
            background: white;
            font-size: 14px;
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

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-icon {
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
            text-decoration: none;
        }

        .btn-view {
            background: #e3f9e5;
            color: #198754;
        }

        .btn-reply {
            background: #e7f1ff;
            color: #0d6efd;
        }

        .btn-delete {
            background: #f8d7da;
            color: #dc3545;
        }

        .btn-icon:hover {
            transform: scale(1.05);
            filter: brightness(0.95);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 20px 25px;
        }

        .modal-body {
            padding: 25px;
        }

        .message-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
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

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .filter-bar {
                flex-direction: column;
            }

            .search-box {
                max-width: 100%;
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
                    <h2>Contact Messages Management</h2>
                    <p>View and manage contact form messages from users.</p>
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
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Messages</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['today']; ?></h3>
                        <p>Today</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['this_week']; ?></h3>
                        <p>This Week</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['this_month']; ?></h3>
                        <p>This Month</p>
                    </div>
                </div>

                <div class="table-container">
                    <div class="section-title">
                        <h4><i class="bi bi-envelope-fill me-2"></i>All Contact Messages</h4>
                    </div>

                    <!-- Filter Bar -->
                    <div class="filter-bar">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <form method="GET" action="contect_message.php">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, email, or message..."
                                    value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                                <input type="hidden" name="date_filter" value="<?php echo $date_filter; ?>">
                            </form>
                        </div>
                        <form method="GET" action="contect_message.php" class="d-flex gap-2 flex-wrap">
                            <select name="date_filter" class="filter-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $date_filter == 'all' ? 'selected' : ''; ?>>All Dates</option>
                                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                            </select>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>

                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </thead>
                            <tbody>
                                <?php if ($messages_result && mysqli_num_rows($messages_result) > 0): ?>
                                    <?php while ($message = mysqli_fetch_assoc($messages_result)): ?>
                                        <tr>
                                            <td><?php echo $message['id']; ?>;</td>
                                            <td><strong><?php echo htmlspecialchars($message['name']); ?></strong>;</td>
                                            <td><?php echo htmlspecialchars($message['email']); ?>;</td>
                                            <td><?php echo htmlspecialchars(substr($message['message'], 0, 80)); ?><?php echo strlen($message['message']) > 80 ? '...' : ''; ?>;</td>
                                            <td><?php echo date('d M Y, h:i A', strtotime($message['created_at'])); ?>;</td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="contect_message.php?view_id=<?php echo $message['id']; ?>" class="btn-icon btn-view"><i class="bi bi-eye"></i> View</a>
                                                    <a href="mailto:<?php echo $message['email']; ?>?subject=Re: Contact Inquiry from Box Cricket" class="btn-icon btn-reply"><i class="bi bi-reply"></i> Reply</a>
                                                    <a href="contect_message.php?delete_id=<?php echo $message['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this message? This action cannot be undone.')"><i class="bi bi-trash"></i> Delete</a>
                                                </div>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-envelope fs-1 d-block mb-3"></i>
                                            No messages found
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&date_filter=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                                    <i class="bi bi-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                                    <a href="?page=<?php echo $i; ?>&date_filter=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>"
                                        class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                    <span class="px-2 text-muted">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&date_filter=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                                    Next <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Message Modal -->
    <?php if ($view_message): ?>
        <div class="modal fade show" id="viewMessageModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-envelope-open-fill me-2"></i>Message Details</h5>
                        <a href="contect_message.php" class="btn-close"></a>
                    </div>
                    <div class="modal-body">
                        <div class="message-detail">
                            <p><strong><i class="bi bi-person"></i> From:</strong> <?php echo htmlspecialchars($view_message['name']); ?></p>
                            <p><strong><i class="bi bi-envelope"></i> Email:</strong> <?php echo htmlspecialchars($view_message['email']); ?></p>
                            <p><strong><i class="bi bi-calendar"></i> Date:</strong> <?php echo date('l, d F Y, h:i A', strtotime($view_message['created_at'])); ?></p>
                        </div>
                        <div class="message-detail">
                            <p><strong><i class="bi bi-chat"></i> Message:</strong></p>
                            <p style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($view_message['message'])); ?></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="contect_message.php" class="btn btn-secondary">Close</a>
                        <a href="mailto:<?php echo $view_message['email']; ?>?subject=Re: Contact Inquiry from Box Cricket" class="btn btn-primary">
                            <i class="bi bi-reply"></i> Reply
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>