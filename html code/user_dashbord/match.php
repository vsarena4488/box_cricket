<?php
session_start();
include_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

// Get user details
$user_name = $_SESSION['user_name'] ?? 'User';
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// Get filter type from URL (default: all)
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$allowed_filter_types = ['all', 'premium', 'standard', 'practice'];
if (!in_array($filter_type, $allowed_filter_types, true)) {
    $filter_type = 'all';
}

// Build query based on filter
if ($filter_type != 'all') {
    $matches_query = "SELECT * FROM matches 
                      WHERE match_date >= CURDATE() 
                      AND status = 'upcoming' 
                      AND match_type = '$filter_type'
                      ORDER BY match_date ASC";
} else {
    $matches_query = "SELECT * FROM matches 
                      WHERE match_date >= CURDATE() 
                      AND status = 'upcoming' 
                      ORDER BY match_date ASC";
}

$matches_result = mysqli_query($con, $matches_query);
$total_matches = $matches_result ? mysqli_num_rows($matches_result) : 0;
$all_matches_result = mysqli_query($con, "SELECT COUNT(*) as count FROM matches WHERE match_date >= CURDATE() AND status = 'upcoming'");
$all_matches_row = $all_matches_result ? mysqli_fetch_assoc($all_matches_result) : null;
$all_matches_count = isset($all_matches_row['count']) ? (int) $all_matches_row['count'] : 0;

// Get match counts by type for filter badges
function getMatchCountByType($con, $type) {
    $safe_type = mysqli_real_escape_string($con, $type);
    $count_result = mysqli_query($con, "SELECT COUNT(*) as count FROM matches WHERE match_date >= CURDATE() AND status = 'upcoming' AND match_type = '$safe_type'");

    if (!$count_result) {
        return 0;
    }

    $count_row = mysqli_fetch_assoc($count_result);

    return isset($count_row['count']) ? (int) $count_row['count'] : 0;
}

$premium_count = getMatchCountByType($con, 'premium');
$standard_count = getMatchCountByType($con, 'standard');
$practice_count = getMatchCountByType($con, 'practice');
?>

<?php include 'nevbar.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Upcoming Matches</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        .match-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .match-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15) !important;
        }
        
        .match-type-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 1;
        }
        
        .match-type-premium {
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            color: #856404;
        }
        
        .match-type-standard {
            background: linear-gradient(135deg, #a8edea, #fed6e3);
            color: #0c5460;
        }
        
        .match-type-practice {
            background: linear-gradient(135deg, #d4fc79, #96e6a1);
            color: #155724;
        }
        
        .team-vs {
            font-size: 14px;
            font-weight: 600;
            color: #667eea;
            margin: 0 10px;
        }
        
        .team-name {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .match-detail-icon {
            width: 30px;
            color: #667eea;
        }
        
        .price-tag {
            font-size: 1.5rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .slot-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .slot-available {
            background-color: #d4edda;
            color: #155724;
        }
        
        .slot-limited {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .slot-critical {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .filter-btn {
            border-radius: 25px;
            padding: 8px 20px;
            margin: 0 5px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .filter-btn:not(.active) {
            background: white;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }
        
        .filter-btn:not(.active):hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            border-color: #667eea;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .match-stats {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .progress {
            height: 6px;
            border-radius: 3px;
        }
    </style>
</head>

<body>
    <!-- Main Content -->
    <div class="container my-5 pt-5">

        <!-- Page Title with Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h2 class="fw-bold">
                            <i class="bi bi-calendar-check text-primary me-2"></i>Upcoming Matches
                        </h2>
                        <p class="text-secondary mb-0">Find and book your next cricket match, <?php echo htmlspecialchars($user_name); ?>!</p>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <span class="badge bg-primary fs-6">
                            <i class="bi bi-trophy me-1"></i><?php echo $total_matches; ?> Matches Available
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a href="match.php?type=all" class="filter-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> All Matches (<?php echo $all_matches_count; ?>)
                    </a>
                    <a href="match.php?type=premium" class="filter-btn <?php echo $filter_type === 'premium' ? 'active' : ''; ?>">
                        <i class="bi bi-star-fill text-warning me-1"></i> Premium (<?php echo $premium_count; ?>)
                    </a>
                    <a href="match.php?type=standard" class="filter-btn <?php echo $filter_type === 'standard' ? 'active' : ''; ?>">
                        <i class="bi bi-trophy me-1"></i> Standard (<?php echo $standard_count; ?>)
                    </a>
                    <a href="match.php?type=practice" class="filter-btn <?php echo $filter_type === 'practice' ? 'active' : ''; ?>">
                        <i class="bi bi-people me-1"></i> Practice (<?php echo $practice_count; ?>)
                    </a>
                </div>
            </div>
        </div>

        <!-- Matches Grid -->
        <?php if($matches_result && $total_matches > 0): ?>
            <div class="row g-4">
                <?php while($match = mysqli_fetch_assoc($matches_result)): 
                    // Calculate slot status
                    $total_slots = max(0, (int) $match['total_slots']);
                    $available_slots = max(0, (int) $match['available_slots']);
                    $booked_slots = max(0, $total_slots - $available_slots);
                    $slot_percentage = $total_slots > 0 ? ($available_slots / $total_slots) * 100 : 0;
                    
                    if($slot_percentage <= 20) {
                        $slot_class = 'slot-critical';
                        $slot_text = '⚠️ Only ' . $match['available_slots'] . ' spots left!';
                        $progress_class = 'danger';
                    } elseif($slot_percentage <= 50) {
                        $slot_class = 'slot-limited';
                        $slot_text = '📌 ' . $match['available_slots'] . ' spots remaining';
                        $progress_class = 'warning';
                    } else {
                        $slot_class = 'slot-available';
                        $slot_text = '✅ ' . $match['available_slots'] . ' spots available';
                        $progress_class = 'success';
                    }
                    
                    // Format match time
                    $match_time = date('h:i A', strtotime($match['match_time']));
                    $match_date_formatted = date('l, d F Y', strtotime($match['match_date']));
                    
                    // Get match type icon and color
                    $match_type_icon = $match['match_type'] == 'premium' ? 'star-fill' : ($match['match_type'] == 'standard' ? 'trophy' : 'people');
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card match-card shadow-sm h-100 position-relative">
                            <!-- Match Type Badge -->
                            <div class="match-type-badge match-type-<?php echo $match['match_type']; ?>">
                                <i class="bi bi-<?php echo $match_type_icon; ?> me-1"></i>
                                <?php echo ucfirst($match['match_type']); ?>
                            </div>
                            
                            <div class="card-body p-4">
                                <!-- Teams -->
                                <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                                    <div class="text-center flex-grow-1">
                                        <div class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></div>
                                    </div>
                                    <div class="team-vs">VS</div>
                                    <div class="text-center flex-grow-1">
                                        <div class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></div>
                                    </div>
                                </div>

                                <!-- Match Details -->
                                <div class="mb-3">
                                    <div class="match-stats">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-calendar3 match-detail-icon"></i>
                                            <span class="ms-2"><?php echo $match_date_formatted; ?></span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-clock match-detail-icon"></i>
                                            <span class="ms-2"><?php echo $match_time; ?> (2 Hours)</span>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-geo-alt match-detail-icon"></i>
                                            <span class="ms-2"><?php echo htmlspecialchars($match['venue']); ?></span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-people match-detail-icon"></i>
                                            <span class="ms-2">5 vs 5 • <?php echo $match['total_slots']; ?> Total Slots</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Price and Availability -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <span class="price-tag">₹<?php echo number_format($match['price'], 0); ?></span>
                                        <small class="text-secondary d-block">per slot</small>
                                    </div>
                                    <span class="slot-badge <?php echo $slot_class; ?>">
                                        <?php echo $slot_text; ?>
                                    </span>
                                </div>

                                <!-- Booking Progress -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-secondary">Booked: <?php echo $booked_slots; ?> slots</small>
                                        <small class="text-secondary">Available: <?php echo $available_slots; ?> slots</small>
                                    </div>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo $progress_class; ?>" 
                                             style="width: <?php echo $total_slots > 0 ? ($booked_slots / $total_slots) * 100 : 0; ?>%">
                                        </div>
                                    </div>
                                </div>

                                <!-- Book Button -->
                                <?php if($available_slots > 0): ?>
                                    <a href="booking_turnament.php?match_id=<?php echo $match['id']; ?>" class="btn btn-primary w-100">
                                        <i class="bi bi-calendar-plus me-2"></i>Book Now
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="bi bi-calendar-x me-2"></i>Sold Out
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <h4>No Matches Available</h4>
                <p class="text-secondary">There are no upcoming matches at the moment. Please check back later!</p>
                <a href="home.php" class="btn btn-primary mt-3">
                    <i class="bi bi-house me-2"></i>Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Back to Dashboard Button -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="home.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php include 'footer.php'; ?>
