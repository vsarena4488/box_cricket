<?php
session_start();
include_once '../gest/db_config.php';
require_admin_login();

// Check if connection exists - fix for variable name
global $con;
if (!isset($con) || !$con) {
    // Try alternative connection variable names
    if (isset($conn) && $conn) {
        $con = $conn;
    } elseif (isset($connection) && $connection) {
        $con = $connection;
    } else {
        die("Database connection not established. Please check your configuration.");
    }
}

// Get admin details
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$admin_email = $_SESSION['admin'] ?? 'admin@boxcricket.com';
$admin_id = $_SESSION['user_id'] ?? 0;

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : 'all';
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($con, $_GET['role']) : 'all';

// Build query
$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(fullname LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $where_conditions[] = "status = '$status_filter'";
}
if ($role_filter != 'all') {
    $where_conditions[] = "role = '$role_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total users count
$total_query = "SELECT COUNT(*) as total FROM users $where_clause";
$total_result = mysqli_query($con, $total_query);
if ($total_result) {
    $total_users = mysqli_fetch_assoc($total_result)['total'];
} else {
    $total_users = 0;
}
$total_pages = ceil($total_users / $limit);

// Get users with pagination
$query = "SELECT * FROM users $where_clause ORDER BY id DESC LIMIT $offset, $limit";
$users_result = mysqli_query($con, $query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as users,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins
                FROM users";
$stats_result = mysqli_query($con, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Initialize stats with default values if query fails
if (!$stats) {
    $stats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'pending' => 0,
        'users' => 0,
        'admins' => 0
    ];
}

// Function to get user's booking count
function get_user_bookings($user_id, $con) {
    $match_bookings = 0;
    $ground_bookings = 0;
    
    $match_query = "SELECT COUNT(*) as count FROM bookings WHERE user_id = $user_id";
    $match_result = mysqli_query($con, $match_query);
    if ($match_result) {
        $match_bookings = mysqli_fetch_assoc($match_result)['count'];
    }
    
    $ground_query = "SELECT COUNT(*) as count FROM ground_bookings WHERE user_id = $user_id";
    $ground_result = mysqli_query($con, $ground_query);
    if ($ground_result) {
        $ground_bookings = mysqli_fetch_assoc($ground_result)['count'];
    }
    
    return $match_bookings + $ground_bookings;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - User Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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

        .profile-info {
            line-height: 1.3;
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

        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
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

        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0d6efd;
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
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #e9ecef;
        }

        .filter-btn.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-icon {
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit { background: #e7f1ff; color: #0d6efd; }
        .btn-delete { background: #f8d7da; color: #dc3545; }
        .btn-view { background: #e3f9e5; color: #198754; }
        .btn-suspend { background: #fff3cd; color: #ffc107; }

        .btn-icon:hover {
            transform: scale(1.05);
            filter: brightness(0.95);
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
            transition: all 0.2s;
        }

        .pagination-btn:hover {
            background: #0d6efd;
            color: white;
        }

        .pagination-btn.active {
            background: #0d6efd;
            color: white;
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
        
        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
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
                <h2>User Management</h2>
                <p>Manage all registered users, edit profiles, and control user access.</p>
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
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="text-muted">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['active']; ?></div>
                    <div class="text-muted">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['inactive']; ?></div>
                    <div class="text-muted">Inactive Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $stats['users']; ?></div>
                    <div class="text-muted">Regular Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-danger"><?php echo $stats['admins']; ?></div>
                    <div class="text-muted">Admins</div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php
                    $success = $_GET['success'];
                    if ($success === 'user_added') {
                        echo 'User added successfully.';
                    } elseif ($success === 'user_updated') {
                        echo 'User updated successfully.';
                    } elseif ($success === 'status_updated') {
                        echo 'User status updated successfully.';
                    } elseif ($success === 'user_deleted') {
                        echo 'User deleted successfully.';
                    } else {
                        echo 'Action completed successfully.';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php
                    $error = $_GET['error'];
                    if ($error === 'missing_fields') {
                        echo 'Please fill all required fields.';
                    } elseif ($error === 'invalid_email') {
                        echo 'Please enter a valid email address.';
                    } elseif ($error === 'email_exists') {
                        echo 'This email already exists.';
                    } elseif ($error === 'cannot_suspend_self') {
                        echo 'You cannot suspend your own account.';
                    } elseif ($error === 'cannot_delete_self') {
                        echo 'You cannot delete your own account.';
                    } else {
                        echo 'Action failed. Please try again.';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="section-title">
                    <h4><i class="bi bi-people-fill me-2"></i>Manage Users</h4>
                    <button class="btn btn-primary btn-sm" onclick="openAddUserModal()">
                        <i class="bi bi-plus-circle"></i> Add New User
                    </button>
                </div>

                <!-- Search and Filter Bar -->
                <div class="search-filter-bar">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <form method="GET" action="" id="searchForm">
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="hidden" name="role" value="<?php echo $role_filter; ?>">
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                    <div class="filter-buttons">
                        <a href="?status=all&role=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=active&role=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'active' ? 'active' : ''; ?>">Active</a>
                        <a href="?status=inactive&role=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'inactive' ? 'active' : ''; ?>">Inactive</a>
                        <a href="?status=pending&role=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                    </div>
                    <div class="filter-buttons">
                        <a href="?role=all&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $role_filter == 'all' ? 'active' : ''; ?>">All Roles</a>
                        <a href="?role=user&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $role_filter == 'user' ? 'active' : ''; ?>">Users</a>
                        <a href="?role=admin&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $role_filter == 'admin' ? 'active' : ''; ?>">Admins</a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($users_result && mysqli_num_rows($users_result) > 0): ?>
                                <?php while($user = mysqli_fetch_assoc($users_result)): 
                                    $bookings_count = get_user_bookings($user['id'], $con);
                                ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="profile-avatar me-2" style="width: 35px; height: 35px; font-size: 14px;">
                                                <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                                            </div>
                                            <strong><?php echo htmlspecialchars($user['fullname']); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '-'; ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : 'bg-info'; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $bookings_count; ?></span></td>
                                    <td>
                                        <span class="badge-status <?php echo $user['status'] == 'active' ? 'badge-success' : ($user['status'] == 'inactive' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-icon btn-view" onclick="viewUser(<?php echo $user['id']; ?>)" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button type="button" class="btn-icon btn-edit" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit User">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn-icon btn-suspend" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')" title="Toggle Status">
                                                <i class="bi bi-<?php echo $user['status'] == 'active' ? 'ban' : 'check-circle'; ?>"></i>
                                            </button>
                                            <button type="button" class="btn-icon btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Delete User">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="bi bi-people-fill fs-1 d-block mb-3"></i>
                                        No users found
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
                        <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == 1 || $i == $total_pages || ($i >= $page-1 && $i <= $page+1)): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" 
                               class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php elseif($i == $page-2 || $i == $page+2): ?>
                            <span class="px-2 text-muted">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&role=<?php echo $role_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_user.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="fullname" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_user.php">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="fullname" id="editFullName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editEmail" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" id="editPhone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" id="editRole">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="editStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-badge me-2"></i>User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewUserContent">
                <!-- User details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openAddUserModal() {
        new bootstrap.Modal(document.getElementById('addUserModal')).show();
    }

    function viewUser(userId) {
        fetch(`get_user.php?id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch user details');
                }
                return response.json();
            })
            .then(user => {
                const statusClass = user.status === 'active' ? 'badge-success' : (user.status === 'inactive' ? 'badge-warning' : 'badge-danger');
                const modalContent = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="profile-avatar mx-auto mb-3" style="width: 100px; height: 100px; font-size: 40px; background: #0d6efd;">
                                ${user.fullname.charAt(0).toUpperCase()}
                            </div>
                            <h5>${user.fullname}</h5>
                            <p class="text-secondary">${user.role}</p>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr><td><strong>Email:</strong></td><td>${user.email}</td></tr>
                                <tr><td><strong>Phone:</strong></td><td>${user.phone || '-'}</td></tr>
                                <tr><td><strong>Joined Date:</strong></td><td>${user.created_at}</td></tr>
                                <tr><td><strong>Status:</strong></td><td><span class="badge-status ${statusClass}">${user.status}</span></td></tr>
                            </table>
                        </div>
                    </div>
                `;
                document.getElementById('viewUserContent').innerHTML = modalContent;
                new bootstrap.Modal(document.getElementById('viewUserModal')).show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load user details');
            });
    }

    function editUser(userId) {
        fetch(`get_user.php?id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch user details');
                }
                return response.json();
            })
            .then(user => {
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editFullName').value = user.fullname;
                document.getElementById('editEmail').value = user.email;
                document.getElementById('editPhone').value = user.phone || '';
                document.getElementById('editRole').value = user.role;
                document.getElementById('editStatus').value = user.status;
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load user details');
            });
    }

    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activate' : 'suspend';
        if (confirm(`Are you sure you want to ${action} this user?`)) {
            window.location.href = `toggle_user_status.php?id=${userId}&status=${newStatus}`;
        }
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            window.location.href = `delete_user.php?id=${userId}`;
        }
    }
</script>

</body>
</html>
