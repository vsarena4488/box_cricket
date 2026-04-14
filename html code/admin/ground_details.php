<?php
session_start();
include_once '../gest/db_config.php';

// Check if connection exists and assign to $con if needed (for backward compatibility)
if (!isset($con) && isset($conn)) {
    $con = $conn;
}
if (!isset($con) && isset($connection)) {
    $con = $connection;
}

// Verify connection
if (!isset($con) || !$con) {
    die("Database connection failed. Please check your configuration.");
}

// Use the existing require_admin_login function from db_config.php (don't redeclare it)
// require_admin_login(); // This function is already defined in db_config.php

// Call the function to check admin login
if (function_exists('require_admin_login')) {
    require_admin_login();
} else {
    // Fallback if function doesn't exist
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

// Add Ground
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_ground'])) {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $location = mysqli_real_escape_string($con, $_POST['location']);
    $ground_type = mysqli_real_escape_string($con, $_POST['ground_type']);
    $price_per_hour = (float)$_POST['price_per_hour'];
    $capacity = (int)$_POST['capacity'];
    $image_url = '';
    $amenities = mysqli_real_escape_string($con, $_POST['amenities']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $status = mysqli_real_escape_string($con, $_POST['status']);

    if (!isset($_FILES['ground_image']) || $_FILES['ground_image']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Please upload a valid ground image.";
    } else {
        $upload_dir = __DIR__ . '/../uploads/grounds/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = $_FILES['ground_image']['name'];
        $tmp_name = $_FILES['ground_image']['tmp_name'];
        $file_size = (int) $_FILES['ground_image']['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $allowed_extensions, true)) {
            $error_msg = "Only JPG, JPEG, PNG and WEBP images are allowed.";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $error_msg = "Image size must be 5MB or less.";
        } else {
            $new_filename = 'ground_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
            $destination = $upload_dir . $new_filename;

            if (!move_uploaded_file($tmp_name, $destination)) {
                $error_msg = "Failed to upload image file.";
            } else {
                $image_url = '../uploads/grounds/' . $new_filename;
            }
        }
    }
    
    if (!isset($error_msg)) {
        $query = "INSERT INTO grounds (name, location, ground_type, price_per_hour, capacity, image_url, amenities, description, status) 
                  VALUES ('$name', '$location', '$ground_type', $price_per_hour, $capacity, '$image_url', '$amenities', '$description', '$status')";
        
        if (mysqli_query($con, $query)) {
            $success_msg = "Ground added successfully!";
            // Redirect to clear POST data
            header("Location: ground_details.php?msg=added");
            exit();
        } else {
            $error_msg = "Error: " . mysqli_error($con);
        }
    }
}

// Update Ground
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ground'])) {
    $id = (int)$_POST['ground_id'];
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $location = mysqli_real_escape_string($con, $_POST['location']);
    $ground_type = mysqli_real_escape_string($con, $_POST['ground_type']);
    $price_per_hour = (float)$_POST['price_per_hour'];
    $capacity = (int)$_POST['capacity'];
    $existing_image = mysqli_real_escape_string($con, $_POST['existing_image'] ?? '');
    $image_url = $existing_image;
    $amenities = mysqli_real_escape_string($con, $_POST['amenities']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $uploaded_new_image = false;

    if (isset($_FILES['ground_image']) && $_FILES['ground_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/grounds/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $original_name = $_FILES['ground_image']['name'];
        $tmp_name = $_FILES['ground_image']['tmp_name'];
        $file_size = (int) $_FILES['ground_image']['size'];
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $allowed_extensions, true)) {
            $error_msg = "Only JPG, JPEG, PNG and WEBP images are allowed.";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $error_msg = "Image size must be 5MB or less.";
        } else {
            $new_filename = 'ground_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
            $destination = $upload_dir . $new_filename;

            if (!move_uploaded_file($tmp_name, $destination)) {
                $error_msg = "Failed to upload image file.";
            } else {
                $image_url = '../uploads/grounds/' . $new_filename;
                $uploaded_new_image = true;
            }
        }
    }
    
    if (!isset($error_msg)) {
        $query = "UPDATE grounds SET 
                  name='$name', 
                  location='$location', 
                  ground_type='$ground_type', 
                  price_per_hour=$price_per_hour, 
                  capacity=$capacity, 
                  image_url='$image_url', 
                  amenities='$amenities', 
                  description='$description', 
                  status='$status' 
                  WHERE id=$id";
        
        if (mysqli_query($con, $query)) {
            if ($uploaded_new_image) {
                $old_file_name = basename($existing_image);
                $old_file_path = __DIR__ . '/../uploads/grounds/' . $old_file_name;
                if (
                    $old_file_name !== '' &&
                    $existing_image !== '' &&
                    strpos(str_replace('\\', '/', $existing_image), '../uploads/grounds/') === 0 &&
                    file_exists($old_file_path) &&
                    is_file($old_file_path)
                ) {
                    @unlink($old_file_path);
                }
            }
            $success_msg = "Ground updated successfully!";
            header("Location: ground_details.php?msg=updated");
            exit();
        } else {
            if ($uploaded_new_image) {
                $new_file_name = basename($image_url);
                $new_file_path = __DIR__ . '/../uploads/grounds/' . $new_file_name;
                if (file_exists($new_file_path) && is_file($new_file_path)) {
                    @unlink($new_file_path);
                }
            }
            $error_msg = "Error: " . mysqli_error($con);
        }
    }
}

// Delete Ground
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $query = "DELETE FROM grounds WHERE id = $id";
    if (mysqli_query($con, $query)) {
        $success_msg = "Ground deleted successfully!";
        header("Location: ground_details.php?msg=deleted");
        exit();
    } else {
        $error_msg = "Error: " . mysqli_error($con);
    }
}

// Show message from redirect
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added') $success_msg = "Ground added successfully!";
    if ($_GET['msg'] == 'updated') $success_msg = "Ground updated successfully!";
    if ($_GET['msg'] == 'deleted') $success_msg = "Ground deleted successfully!";
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
    $where_conditions[] = "(name LIKE '%$search%' OR location LIKE '%$search%')";
}
if ($status_filter != 'all') {
    $where_conditions[] = "status = '$status_filter'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total grounds count
$total_query = "SELECT COUNT(*) as total FROM grounds $where_clause";
$total_result = mysqli_query($con, $total_query);
$total_grounds = $total_result ? mysqli_fetch_assoc($total_result)['total'] : 0;
$total_pages = $total_grounds > 0 ? ceil($total_grounds / $limit) : 1;

// Get grounds with pagination
$query = "SELECT * FROM grounds $where_clause ORDER BY id DESC LIMIT $offset, $limit";
$grounds_result = mysqli_query($con, $query);

// Get statistics
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN ground_type = 'floodlight' THEN 1 ELSE 0 END) as floodlight,
                    SUM(CASE WHEN ground_type = 'premium' THEN 1 ELSE 0 END) as premium,
                    SUM(CASE WHEN ground_type = 'covered' THEN 1 ELSE 0 END) as covered,
                    SUM(CASE WHEN ground_type = 'practice' THEN 1 ELSE 0 END) as practice
                FROM grounds";
$stats_result = mysqli_query($con, $stats_query);
$stats = $stats_result ? mysqli_fetch_assoc($stats_result) : [
    'total' => 0, 'active' => 0, 'inactive' => 0, 'maintenance' => 0,
    'floodlight' => 0, 'premium' => 0, 'covered' => 0, 'practice' => 0
];

// Get single ground for edit/view
$edit_ground = null;
if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $edit_query = "SELECT * FROM grounds WHERE id = $id";
    $edit_result = mysqli_query($con, $edit_query);
    $edit_ground = $edit_result ? mysqli_fetch_assoc($edit_result) : null;
}

$view_ground = null;
if (isset($_GET['view_id'])) {
    $id = (int)$_GET['view_id'];
    $view_query = "SELECT * FROM grounds WHERE id = $id";
    $view_result = mysqli_query($con, $view_query);
    $view_ground = $view_result ? mysqli_fetch_assoc($view_result) : null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Manage Grounds</title>
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
            display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px; margin-bottom: 30px;
        }
        .stat-card {
            background: white; border-radius: 15px; padding: 20px;
            text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-number { font-size: 28px; font-weight: bold; color: #0d6efd; }
        .table-container {
            background: white; border-radius: 20px; padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .section-title {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .section-title h4 { font-size: 20px; font-weight: 600; color: #333; margin: 0; }
        .table thead th {
            background: #f8f9fa; border-bottom: 2px solid #e9ecef;
            color: #495057; font-weight: 600; font-size: 14px; padding: 12px;
        }
        .table tbody td { padding: 12px; vertical-align: middle; font-size: 14px; }
        .badge-status { padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 500; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .action-buttons { display: flex; gap: 8px; }
        .btn-icon {
            padding: 6px 12px; border-radius: 6px; font-size: 13px;
            border: none; cursor: pointer; transition: all 0.2s;
            display: inline-block; text-decoration: none;
        }
        .btn-edit { background: #e7f1ff; color: #0d6efd; }
        .btn-delete { background: #f8d7da; color: #dc3545; }
        .btn-view { background: #e3f9e5; color: #198754; }
        .btn-icon:hover { transform: scale(1.05); filter: brightness(0.95); }
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
        .image-preview { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
        .alert { border-radius: 10px; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .search-filter-bar { flex-direction: column; }
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
                <h2>Manage Cricket Grounds</h2>
                <p>Add, edit, view, or remove cricket grounds</p>
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
                <div class="stat-card"><div class="stat-number"><?php echo $stats['total']; ?></div><div class="text-muted">Total Grounds</div></div>
                <div class="stat-card"><div class="stat-number text-success"><?php echo $stats['active']; ?></div><div class="text-muted">Active</div></div>
                <div class="stat-card"><div class="stat-number text-warning"><?php echo $stats['maintenance']; ?></div><div class="text-muted">Maintenance</div></div>
                <div class="stat-card"><div class="stat-number text-info"><?php echo $stats['floodlight']; ?></div><div class="text-muted">Floodlight</div></div>
                <div class="stat-card"><div class="stat-number text-danger"><?php echo $stats['premium']; ?></div><div class="text-muted">Premium</div></div>
                <div class="stat-card"><div class="stat-number text-secondary"><?php echo $stats['covered']; ?></div><div class="text-muted">Covered</div></div>
            </div>

            <!-- View Ground Modal -->
            <?php if($view_ground): ?>
            <div class="modal fade show" id="viewGroundModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="bi bi-building me-2"></i>Ground Details</h5>
                            <a href="ground_details.php" class="btn-close btn-close-white"></a>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-12 mb-3 text-center">
                                    <img src="<?php echo htmlspecialchars($view_ground['image_url']); ?>" class="img-fluid rounded" style="max-height: 300px; width: auto;" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                                </div>
                                <div class="col-md-12">
                                    <h4><?php echo htmlspecialchars($view_ground['name']); ?></h4>
                                    <p><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($view_ground['location']); ?></p>
                                    <hr>
                                    <div class="row">
                                        <div class="col-4"><strong>Price:</strong><br><span class="fs-4 text-primary fw-bold">₹<?php echo number_format($view_ground['price_per_hour'], 0); ?></span>/hr</div>
                                        <div class="col-4"><strong>Type:</strong><br><span class="badge bg-secondary"><?php echo ucfirst($view_ground['ground_type']); ?></span></div>
                                        <div class="col-4"><strong>Capacity:</strong><br><?php echo $view_ground['capacity']; ?> players</div>
                                    </div>
                                    <hr>
                                    <div><strong>Amenities:</strong><br>
                                        <?php if($view_ground['amenities']): ?>
                                            <?php foreach(explode(',', $view_ground['amenities']) as $amenity): ?>
                                                <span class="badge bg-light text-dark me-1 mb-1"><?php echo trim($amenity); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No amenities listed</span>
                                        <?php endif; ?>
                                    </div>
                                    <hr>
                                    <div><strong>Rating:</strong><br>
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= round($view_ground['rating']) ? '-fill text-warning' : ''; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-secondary">(<?php echo $view_ground['total_reviews']; ?> reviews)</span>
                                    </div>
                                    <hr>
                                    <div><strong>Description:</strong><br><p class="text-secondary"><?php echo nl2br(htmlspecialchars($view_ground['description'])); ?></p></div>
                                    <div><strong>Status:</strong><br>
                                        <span class="badge-status <?php echo $view_ground['status'] == 'active' ? 'badge-success' : ($view_ground['status'] == 'maintenance' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($view_ground['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="ground_details.php" class="btn btn-secondary">Close</a>
                            <a href="ground_details.php?edit_id=<?php echo $view_ground['id']; ?>" class="btn btn-primary">Edit Ground</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit Ground Modal -->
            <?php if($edit_ground): ?>
            <div class="modal fade show" id="editGroundModal" style="display: block; background: rgba(0,0,0,0.5);" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Ground</h5>
                            <a href="ground_details.php" class="btn-close btn-close-white"></a>
                        </div>
                        <form method="POST" action="ground_details.php" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="ground_id" value="<?php echo $edit_ground['id']; ?>">
                                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_ground['image_url']); ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ground Name *</label>
                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($edit_ground['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Location *</label>
                                        <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($edit_ground['location']); ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Ground Type</label>
                                        <select class="form-select" name="ground_type">
                                            <option value="floodlight" <?php echo $edit_ground['ground_type'] == 'floodlight' ? 'selected' : ''; ?>>Floodlight</option>
                                            <option value="covered" <?php echo $edit_ground['ground_type'] == 'covered' ? 'selected' : ''; ?>>Covered</option>
                                            <option value="premium" <?php echo $edit_ground['ground_type'] == 'premium' ? 'selected' : ''; ?>>Premium</option>
                                            <option value="practice" <?php echo $edit_ground['ground_type'] == 'practice' ? 'selected' : ''; ?>>Practice</option>
                                            <option value="indoor" <?php echo $edit_ground['ground_type'] == 'indoor' ? 'selected' : ''; ?>>Indoor</option>
                                            <option value="vip" <?php echo $edit_ground['ground_type'] == 'vip' ? 'selected' : ''; ?>>VIP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Price per Hour (₹) *</label>
                                        <input type="number" class="form-control" name="price_per_hour" value="<?php echo $edit_ground['price_per_hour']; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Capacity</label>
                                        <input type="number" class="form-control" name="capacity" value="<?php echo $edit_ground['capacity']; ?>">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Ground Image</label>
                                        <input type="file" class="form-control" name="ground_image" accept=".jpg,.jpeg,.png,.webp">
                                        <small class="text-muted">Leave empty to keep current image.</small>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Amenities (comma separated)</label>
                                        <input type="text" class="form-control" name="amenities" value="<?php echo htmlspecialchars($edit_ground['amenities']); ?>" placeholder="Floodlights, Changing Rooms, Parking">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($edit_ground['description']); ?></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active" <?php echo $edit_ground['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $edit_ground['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="maintenance" <?php echo $edit_ground['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="ground_details.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" name="update_ground" class="btn btn-primary">Update Ground</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Add Ground Modal -->
            <div class="modal fade" id="addGroundModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Ground</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="ground_details.php" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ground Name *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Location *</label>
                                        <input type="text" class="form-control" name="location" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Ground Type</label>
                                        <select class="form-select" name="ground_type">
                                            <option value="floodlight">Floodlight</option>
                                            <option value="covered">Covered</option>
                                            <option value="premium">Premium</option>
                                            <option value="practice">Practice</option>
                                            <option value="indoor">Indoor</option>
                                            <option value="vip">VIP</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Price per Hour (₹) *</label>
                                        <input type="number" class="form-control" name="price_per_hour" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Capacity</label>
                                        <input type="number" class="form-control" name="capacity" value="10">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Ground Image *</label>
                                        <input type="file" class="form-control" name="ground_image" accept=".jpg,.jpeg,.png,.webp" required>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Amenities (comma separated)</label>
                                        <input type="text" class="form-control" name="amenities" placeholder="Floodlights, Changing Rooms, Parking">
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="maintenance">Maintenance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="add_ground" class="btn btn-primary">Save Ground</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Grounds List Table -->
            <div class="table-container">
                <div class="section-title">
                    <h4><i class="bi bi-building me-2"></i>Grounds List</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroundModal">
                        <i class="bi bi-plus-circle me-2"></i>Add New Ground
                    </button>
                </div>

                <!-- Search and Filter Bar -->
                <div class="search-filter-bar">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <form method="GET" action="ground_details.php">
                            <input type="text" name="search" class="form-control" placeholder="Search by name or location..." 
                                   value="<?php echo htmlspecialchars($search); ?>" onchange="this.form.submit()">
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                            <input type="hidden" name="page" value="1">
                        </form>
                    </div>
                    <div class="filter-buttons">
                        <a href="ground_details.php?status=all&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                        <a href="ground_details.php?status=active&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'active' ? 'active' : ''; ?>">Active</a>
                        <a href="ground_details.php?status=inactive&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'inactive' ? 'active' : ''; ?>">Inactive</a>
                        <a href="ground_details.php?status=maintenance&search=<?php echo urlencode($search); ?>" class="filter-btn <?php echo $status_filter == 'maintenance' ? 'active' : ''; ?>">Maintenance</a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ground Name</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Price/Hour</th>
                                <th>Capacity</th>
                                <th>Rating</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($grounds_result && mysqli_num_rows($grounds_result) > 0): ?>
                                <?php while($ground = mysqli_fetch_assoc($grounds_result)): ?>
                                <tr>
                                    <td><?php echo $ground['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($ground['image_url']); ?>" class="image-preview me-2" onerror="this.src='https://via.placeholder.com/60'">
                                            <div>
                                                <strong><?php echo htmlspecialchars($ground['name']); ?></strong><br>
                                                <small class="text-secondary"><?php echo htmlspecialchars(substr($ground['description'], 0, 40)); ?>...</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ground['location']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($ground['ground_type']); ?></span></td>
                                    <td><span class="fw-bold text-primary">₹<?php echo number_format($ground['price_per_hour'], 0); ?></span>/hr</td>
                                    <td><?php echo $ground['capacity']; ?></td>
                                    <td>
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= round($ground['rating']) ? '-fill text-warning' : ''; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-secondary">(<?php echo $ground['total_reviews']; ?>)</span>
                                     </td>
                                    <td>
                                        <span class="badge-status <?php echo $ground['status'] == 'active' ? 'badge-success' : ($ground['status'] == 'maintenance' ? 'badge-warning' : 'badge-danger'); ?>">
                                            <?php echo ucfirst($ground['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="ground_details.php?view_id=<?php echo $ground['id']; ?>" class="btn-icon btn-view"><i class="bi bi-eye"></i></a>
                                            <a href="ground_details.php?edit_id=<?php echo $ground['id']; ?>" class="btn-icon btn-edit"><i class="bi bi-pencil"></i></a>
                                            <a href="ground_details.php?delete_id=<?php echo $ground['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Are you sure you want to delete this ground?')"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="bi bi-building fs-1 d-block mb-3"></i>
                                        No grounds found
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
                        <a href="ground_details.php?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if($i == 1 || $i == $total_pages || ($i >= $page-1 && $i <= $page+1)): ?>
                            <a href="ground_details.php?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                               class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php elseif($i == $page-2 || $i == $page+2): ?>
                            <span class="px-2 text-muted">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="ground_details.php?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" class="pagination-btn">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
