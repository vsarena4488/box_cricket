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

// Update Feedback Status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_feedback'])) {
    $feedback_id = (int)$_POST['feedback_id'];
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $admin_reply = mysqli_real_escape_string($con, $_POST['admin_reply']);
    $allowed_status = ['pending', 'read', 'replied', 'resolved'];
    if (!in_array($status, $allowed_status, true)) {
        $status = 'pending';
    }
    
    $replied_at_sql = ($status === 'replied' || $status === 'resolved') ? "NOW()" : "NULL";
    $query = "UPDATE feedback SET status='$status', admin_reply='$admin_reply', replied_at=$replied_at_sql WHERE id=$feedback_id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Feedback updated successfully!";
        header("Location: feadback_details.php?msg=updated");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Delete Feedback
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $query = "DELETE FROM feedback WHERE id = $id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Feedback deleted successfully!";
        header("Location: feadback_details.php?msg=deleted");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Show message from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'updated') $success_msg = "Feedback updated successfully!";
    if ($_GET['msg'] == 'deleted') $success_msg = "Feedback deleted successfully!";
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
$allowed_status_filters = ['all', 'pending', 'read', 'replied', 'resolved'];
if (!in_array($status_filter, $allowed_status_filters, true)) {
    $status_filter = 'all';
}

// Build query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR message LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $where_conditions[] = "status = '$status_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total feedback count
$total_query = "SELECT COUNT(*) as total FROM feedback $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_feedback = $total_result ? mysqli_fetch_assoc($total_result)['total'] : 0;
$total_pages = $total_feedback > 0 ? ceil($total_feedback / $limit) : 1;

// Get feedback with pagination
$query = "SELECT * FROM feedback $where_clause ORDER BY created_at DESC LIMIT $offset, $limit";
$feedback_result = mysqli_query($con, $query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                    SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    AVG(rating) as avg_rating,
                    SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_ratings
                FROM feedback";
$stats_result = mysqli_query($con, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total' => 0, 'read_count' => 0, 'replied' => 0, 'resolved' => 0, 'pending' => 0,
    'avg_rating' => 0, 'positive_ratings' => 0
];

// Rating breakdown
$rating_breakdown = [];
for ($i = 1; $i <= 5; $i++) {
    $rating_query = "SELECT COUNT(*) as count FROM feedback WHERE rating = $i";
    $rating_result = mysqli_query($con, $rating_query);
    $rating_breakdown[$i] = $rating_result ? mysqli_fetch_assoc($rating_result)['count'] : 0;
}

// Get single feedback for edit/view
$edit_feedback = null;
if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $edit_query = "SELECT * FROM feedback WHERE id = $id";
    $edit_result = mysqli_query($con, $edit_query);
    $edit_feedback = $edit_result ? mysqli_fetch_assoc($edit_result) : null;
}

$view_feedback = null;
if (isset($_GET['view_id'])) {
    $id = (int)$_GET['view_id'];
    $view_query = "SELECT * FROM feedback WHERE id = $id";
    $view_result = mysqli_query($con, $view_query);
    $view_feedback = $view_result ? mysqli_fetch_assoc($view_result) : null;
}

$positive_percentage = $stats['total'] > 0 ? round(($stats['positive_ratings'] / $stats['total']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - User Feedback</title>
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
        .content-container { padding: 30px; }
        .feedback-card {
            background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); transition: all 0.3s;
            border-left: 4px solid #ffc107;
        }
        .feedback-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .rating-stars { color: #ffc107; font-size: 16px; letter-spacing: 2px; }
        .user-avatar-sm {
            width: 45px; height: 45px; border-radius: 50%; background: #0d6efd;
            display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 18px;
        }
        .feedback-status {
            padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 500;
        }
        .status-read { background: #d4edda; color: #155724; }
        .status-replied { background: #cce5ff; color: #004085; }
        .status-resolved { background: #d1ecf1; color: #0c5460; }
        .status-pending { background: #fff3cd; color: #856404; }
        .badge-rating {
            background: #e7f1ff; color: #0d6efd; padding: 5px 12px; border-radius: 30px; font-size: 12px;
        }
        .table-container {
            background: white; border-radius: 20px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 30px;
        }
        .section-title {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .section-title h4 { font-size: 20px; font-weight: 600; color: #333; margin: 0; }
        .btn-icon {
            padding: 5px 12px; border-radius: 6px; font-size: 13px;
            border: none; cursor: pointer; transition: all 0.2s;
            display: inline-block; text-decoration: none;
        }
        .btn-view { background: #e3f9e5; color: #198754; }
        .btn-edit { background: #e7f1ff; color: #0d6efd; }
        .btn-delete { background: #f8d7da; color: #dc3545; }
        .btn-icon:hover { transform: scale(1.05); filter: brightness(0.95); }
        .modal-content { border-radius: 15px; border: none; }
        .modal-header { background: #0d6efd; color: white; border-radius: 15px 15px 0 0; }
        .modal-header .btn-close { filter: brightness(0) invert(1); }
        .search-filter-bar {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; gap: 15px; flex-wrap: wrap;
        }
        .search-box { flex: 1; max-width: 300px; position: relative; }
        .search-box input { padding-left: 35px; border-radius: 25px; }
        .search-box i {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); color: #6c757d;
        }
        .filter-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-btn {
            padding: 6px 16px; border-radius: 25px; border: 1px solid #dee2e6;
            background: white; text-decoration: none; color: #6c757d;
        }
        .filter-btn.active { background: #0d6efd; color: white; border-color: #0d6efd; }
        .pagination-container {
            display: flex; justify-content: center; gap: 10px; margin-top: 20px; flex-wrap: wrap;
        }
        .pagination-btn {
            padding: 8px 12px; border: 1px solid #dee2e6; background: white;
            border-radius: 8px; text-decoration: none; color: #333;
        }
        .pagination-btn.active { background: #0d6efd; color: white; }
        .alert { border-radius: 10px; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .search-filter-bar { flex-direction: column; }
            .search-box { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <?php include 'nevbar.php'; ?>

    <div class="main-content">
        <div class="top-navbar">
            <div class="page-title">
                <h2>User Feedback</h2>
                <p>Read and respond to user feedback and ratings</p>
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
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fw-bold mb-0"><?php echo $stats['total']; ?></h3>
                                    <p class="text-secondary mb-0">Total Reviews</p>
                                </div>
                                <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                                    <i class="bi bi-chat-dots text-primary fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fw-bold mb-0"><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                                    <p class="text-secondary mb-0">Average Rating</p>
                                </div>
                                <div class="bg-warning bg-opacity-10 p-3 rounded-3">
                                    <i class="bi bi-star-fill text-warning fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fw-bold mb-0"><?php echo $positive_percentage; ?>%</h3>
                                    <p class="text-secondary mb-0">Positive Rating</p>
                                </div>
                                <div class="bg-success bg-opacity-10 p-3 rounded-3">
                                    <i class="bi bi-graph-up text-success fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3 class="fw-bold mb-0"><?php echo $stats['pending']; ?></h3>
                                    <p class="text-secondary mb-0">Pending Review</p>
                                </div>
                                <div class="bg-info bg-opacity-10 p-3 rounded-3">
                                    <i class="bi bi-clock-history text-info fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rating Breakdown -->
            <div class="table-container">
                <div class="section-title">
                    <h4><i class="bi bi-graph-up me-2"></i>Rating Breakdown</h4>
                    <span class="badge bg-primary">Overall: <?php echo number_format($stats['avg_rating'], 1); ?> ⭐</span>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <?php for($i = 5; $i >= 1; $i--): 
                            $count = $rating_breakdown[$i];
                            $percentage = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
                            $bar_color = $i >= 4 ? 'success' : ($i == 3 ? 'warning' : 'danger');
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo $i; ?> Star</span>
                                <span><?php echo $count; ?> reviews</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-<?php echo $bar_color; ?>" style="width: <?php echo $percentage; ?>%"><?php echo $percentage; ?>%</div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    <div class="col-md-6 text-center">
                        <div class="display-1 fw-bold text-primary"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                        <div class="rating-stars fs-3">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if($i <= round($stats['avg_rating'])): ?>
                                    <i class="bi bi-star-fill"></i>
                                <?php elseif($i - 0.5 <= $stats['avg_rating']): ?>
                                    <i class="bi bi-star-half"></i>
                                <?php else: ?>
                                    <i class="bi bi-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <p class="text-secondary mt-2">Based on <?php echo $stats['total']; ?> reviews</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <form method="GET" action="feadback_details.php">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, or message..." 
                               value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                        <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    </form>
                </div>
                <div class="filter-buttons">
                    <a href="?status=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                    <a href="?status=pending&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                    <a href="?status=read&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'read' ? 'active' : ''; ?>">Read</a>
                    <a href="?status=replied&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'replied' ? 'active' : ''; ?>">Replied</a>
                    <a href="?status=resolved&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">Resolved</a>
                </div>
            </div>

            <!-- Feedback Cards View -->
            <div class="table-container">
                <div class="section-title">
                    <h4><i class="bi bi-chat-dots me-2"></i>Recent Feedback</h4>
                </div>

                <div id="feedbackList">
                    <?php if($feedback_result && mysqli_num_rows($feedback_result) > 0): ?>
                        <?php while($feedback = mysqli_fetch_assoc($feedback_result)): 
                            $initials = strtoupper(substr($feedback['name'], 0, 1));
                            $stars = '';
                            for($i = 1; $i <= 5; $i++) {
                                $stars .= $i <= $feedback['rating'] ? '★' : '☆';
                            }
                            $status_class = $feedback['status'] == 'read' ? 'status-read' : ($feedback['status'] == 'replied' ? 'status-replied' : ($feedback['status'] == 'resolved' ? 'status-resolved' : 'status-pending'));
                            $status_text = ucfirst($feedback['status']);
                        ?>
                        <div class="feedback-card" data-status="<?php echo $feedback['status']; ?>">
                            <div class="row align-items-start">
                                <div class="col-md-1 text-center">
                                    <div class="user-avatar-sm mx-auto"><?php echo $initials; ?></div>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($feedback['name']); ?></h6>
                                    <div class="rating-stars mb-1"><?php echo $stars; ?></div>
                                    <small class="text-secondary"><?php echo date('d M Y', strtotime($feedback['created_at'])); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><?php echo htmlspecialchars(substr($feedback['message'], 0, 150)); ?>...</p>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-primary"><?php echo ucfirst($feedback['feedback_type']); ?></span>
                                        <span class="badge bg-success">Rating: <?php echo $feedback['rating']; ?>/5</span>
                                        <?php if($feedback['recommend'] == 'yes'): ?>
                                            <span class="badge bg-info">Recommended</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <span class="feedback-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    <div class="mt-2">
                                        <a href="feadback_details.php?view_id=<?php echo $feedback['id']; ?>" class="btn-icon btn-view"><i class="bi bi-eye"></i></a>
                                        <a href="feadback_details.php?edit_id=<?php echo $feedback['id']; ?>" class="btn-icon btn-edit"><i class="bi bi-pencil"></i></a>
                                        <a href="feadback_details.php?delete_id=<?php echo $feedback['id']; ?>" class="btn-icon btn-delete"><i class="bi bi-trash"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat-dots fs-1 text-secondary d-block mb-3"></i>
                            <p class="text-secondary">No feedback found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Feedback Table -->
            <div class="table-container">
                <div class="section-title">
                    <h4><i class="bi bi-list-ul me-2"></i>All Feedback</h4>
                    <a href="feadback_details.php?export=1" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download me-1"></i> Export
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Rating</th>
                                <th>Feedback</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $all_feedback_query = "SELECT * FROM feedback $where_clause ORDER BY created_at DESC LIMIT $offset, $limit";
                            $all_feedback_result = mysqli_query($con, $all_feedback_query);
                            if($all_feedback_result && mysqli_num_rows($all_feedback_result) > 0):
                                while($fb = mysqli_fetch_assoc($all_feedback_result)):
                                    $stars = '';
                                    for($i = 1; $i <= 5; $i++) {
                                        $stars .= $i <= $fb['rating'] ? '★' : '☆';
                                    }
                                    $status_class = $fb['status'] == 'read' ? 'status-read' : ($fb['status'] == 'replied' ? 'status-replied' : ($fb['status'] == 'resolved' ? 'status-resolved' : 'status-pending'));
                            ?>
                            <tr>
                                <td><?php echo $fb['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($fb['name']); ?></strong><br><small><?php echo htmlspecialchars($fb['email']); ?></small></td>
                                <td class="rating-stars"><?php echo $stars; ?></td>
                                <td><?php echo htmlspecialchars(substr($fb['message'], 0, 50)); ?>...</td>
                                <td><?php echo date('d M Y', strtotime($fb['created_at'])); ?></td>
                                <td><span class="feedback-status <?php echo $status_class; ?>"><?php echo ucfirst($fb['status']); ?></span></td>
                                <td>
                                    <a href="feadback_details.php?view_id=<?php echo $fb['id']; ?>" class="btn-icon btn-view"><i class="bi bi-eye"></i></a>
                                    <a href="feadback_details.php?edit_id=<?php echo $fb['id']; ?>" class="btn-icon btn-edit"><i class="bi bi-pencil"></i></a>
                                    <a href="feadback_details.php?delete_id=<?php echo $fb['id']; ?>" class="btn-icon btn-delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No feedback found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if($i == 1 || $i == $total_pages || ($i >= $page-1 && $i <= $page+1)): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                           class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php elseif($i == $page-2 || $i == $page+2): ?>
                        <span class="px-2 text-muted">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Feedback Modal -->
<?php if($edit_feedback): ?>
<div class="modal fade show" id="editFeedbackModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil-fill me-2"></i>Edit Feedback</h5>
                <a href="feadback_details.php" class="btn-close btn-close-white"></a>
            </div>
            <form method="POST" action="feadback_details.php">
                <div class="modal-body">
                    <input type="hidden" name="feedback_id" value="<?php echo $edit_feedback['id']; ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_feedback['name']); ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($edit_feedback['email']); ?>" disabled>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Rating</label>
                            <div class="rating-stars fs-3">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <?php if($i <= $edit_feedback['rating']): ?>
                                        <i class="bi bi-star-fill text-warning"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star text-warning"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Feedback Message</label>
                            <textarea class="form-control" rows="3" disabled><?php echo htmlspecialchars($edit_feedback['message']); ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="pending" <?php echo $edit_feedback['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="read" <?php echo $edit_feedback['status'] == 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="replied" <?php echo $edit_feedback['status'] == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="resolved" <?php echo $edit_feedback['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Admin Reply</label>
                            <textarea class="form-control" name="admin_reply" rows="4" placeholder="Write your reply here..."><?php echo htmlspecialchars($edit_feedback['admin_reply']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="feadback_details.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_feedback" class="btn btn-primary">Update Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- View Feedback Modal -->
<?php if($view_feedback): ?>
<div class="modal fade show" id="viewFeedbackModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-chat-dots me-2"></i>Feedback Details</h5>
                <a href="feadback_details.php" class="btn-close btn-close-white"></a>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="user-avatar-sm mx-auto mb-2"><?php echo strtoupper(substr($view_feedback['name'], 0, 1)); ?></div>
                    <h5><?php echo htmlspecialchars($view_feedback['name']); ?></h5>
                    <div class="rating-stars">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <?php if($i <= $view_feedback['rating']): ?>
                                <i class="bi bi-star-fill text-warning"></i>
                            <?php else: ?>
                                <i class="bi bi-star text-warning"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <small class="text-secondary"><?php echo date('d M Y, h:i A', strtotime($view_feedback['created_at'])); ?></small>
                </div>
                <hr>
                <div class="mb-2">
                    <strong>Email:</strong> <?php echo htmlspecialchars($view_feedback['email']); ?>
                </div>
                <div class="mb-2">
                    <strong>Phone:</strong> <?php echo $view_feedback['phone'] ? htmlspecialchars($view_feedback['phone']) : 'Not provided'; ?>
                </div>
                <div class="mb-2">
                    <strong>Feedback Type:</strong> <span class="badge bg-primary"><?php echo ucfirst($view_feedback['feedback_type']); ?></span>
                </div>
                <div class="mb-2">
                    <strong>Recommend:</strong> 
                    <span class="badge bg-<?php echo $view_feedback['recommend'] == 'yes' ? 'success' : 'danger'; ?>">
                        <?php echo strtoupper($view_feedback['recommend']); ?>
                    </span>
                </div>
                <hr>
                <div class="mb-2">
                    <strong>Feedback:</strong>
                    <p class="mt-1"><?php echo nl2br(htmlspecialchars($view_feedback['message'])); ?></p>
                </div>
                <?php if($view_feedback['admin_reply']): ?>
                    <hr>
                    <div class="mb-2">
                        <strong>Admin Reply:</strong>
                        <p class="mt-1 text-success"><?php echo nl2br(htmlspecialchars($view_feedback['admin_reply'])); ?></p>
                        <small class="text-secondary">Replied on: <?php echo date('d M Y', strtotime($view_feedback['replied_at'])); ?></small>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="feadback_details.php" class="btn btn-secondary">Close</a>
                <a href="feadback_details.php?edit_id=<?php echo $view_feedback['id']; ?>" class="btn btn-primary">Reply</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
