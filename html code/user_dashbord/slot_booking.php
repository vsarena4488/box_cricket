<?php
include_once 'db_config.php';

if (!isset($_SESSION['user'], $_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../gest/login.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'User';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$price_range = isset($_GET['price_range']) ? trim($_GET['price_range']) : '';
$ground_type = isset($_GET['ground_type']) ? trim($_GET['ground_type']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$allowed_price_ranges = ['under_500', '500_1000', 'above_1000'];
$allowed_ground_types = ['floodlight', 'covered', 'premium', 'practice', 'indoor', 'vip'];

if (!in_array($price_range, $allowed_price_ranges, true)) {
    $price_range = '';
}

if ($ground_type !== '' && !in_array($ground_type, $allowed_ground_types, true)) {
    $ground_type = '';
}

$location_db = mysqli_real_escape_string($con, $location);
$ground_type_db = mysqli_real_escape_string($con, $ground_type);
$search_db = mysqli_real_escape_string($con, $search);

$query = "SELECT * FROM grounds WHERE status = 'active'";

if ($location !== '') {
    $query .= " AND location = '$location_db'";
}

if ($ground_type !== '') {
    $query .= " AND ground_type = '$ground_type_db'";
}

if ($search !== '') {
    $query .= " AND (name LIKE '%$search_db%' OR location LIKE '%$search_db%' OR description LIKE '%$search_db%')";
}

switch ($price_range) {
    case 'under_500':
        $query .= " AND price_per_hour < 500";
        break;
    case '500_1000':
        $query .= " AND price_per_hour BETWEEN 500 AND 1000";
        break;
    case 'above_1000':
        $query .= " AND price_per_hour > 1000";
        break;
}

$query .= " ORDER BY rating DESC, price_per_hour ASC";
$grounds_result = mysqli_query($con, $query);
$grounds_count = $grounds_result ? mysqli_num_rows($grounds_result) : 0;

$locations_query = "SELECT DISTINCT location FROM grounds WHERE status = 'active' ORDER BY location ASC";
$locations_result = mysqli_query($con, $locations_query);
?>

<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Select Ground</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .ground-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            height: 100%;
        }

        .ground-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .ground-img {
            height: 220px;
            width: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .ground-card:hover .ground-img {
            transform: scale(1.05);
        }

        .ground-card .card-body {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .rating {
            color: #ffc107;
        }

        .price {
            color: #28a745;
            font-weight: bold;
            font-size: 20px;
        }

        .amenity-badge {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }

        .location-badge {
            background: #e7f1ff;
            color: #0d6efd;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .stats-badge {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
        }

        .btn-book {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s;
        }

        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .ground-description {
            min-height: 48px;
        }

        .amenities-wrap {
            min-height: 58px;
        }

        .ground-price-row {
            margin-top: auto;
        }
    </style>
</head>
<body>

<div class="container my-5 pt-5">
    <div class="page-header text-center">
        <h1 class="display-6 fw-bold">
            <i class="bi bi-building text-primary me-2"></i> Select a Ground
        </h1>
        <p class="text-muted">Choose from our premium cricket grounds for your match, <?php echo htmlspecialchars($user_name); ?>!</p>
    </div>

    <div class="filter-section">
        <form method="GET" action="slot_booking.php" id="filterForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-geo-alt"></i> Location</label>
                    <select name="location" class="form-select">
                        <option value="">All Locations</option>
                        <?php if ($locations_result): ?>
                            <?php while ($loc = mysqli_fetch_assoc($locations_result)): ?>
                                <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['location']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-tag"></i> Price Range</label>
                    <select name="price_range" class="form-select">
                        <option value="">All Prices</option>
                        <option value="under_500" <?php echo $price_range === 'under_500' ? 'selected' : ''; ?>>Under Rs. 500</option>
                        <option value="500_1000" <?php echo $price_range === '500_1000' ? 'selected' : ''; ?>>Rs. 500 - Rs. 1000</option>
                        <option value="above_1000" <?php echo $price_range === 'above_1000' ? 'selected' : ''; ?>>Above Rs. 1000</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-building"></i> Ground Type</label>
                    <select name="ground_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="floodlight" <?php echo $ground_type === 'floodlight' ? 'selected' : ''; ?>>Floodlight</option>
                        <option value="covered" <?php echo $ground_type === 'covered' ? 'selected' : ''; ?>>Covered</option>
                        <option value="premium" <?php echo $ground_type === 'premium' ? 'selected' : ''; ?>>Premium</option>
                        <option value="practice" <?php echo $ground_type === 'practice' ? 'selected' : ''; ?>>Practice</option>
                        <option value="indoor" <?php echo $ground_type === 'indoor' ? 'selected' : ''; ?>>Indoor</option>
                        <option value="vip" <?php echo $ground_type === 'vip' ? 'selected' : ''; ?>>VIP</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold"><i class="bi bi-search"></i> Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search ground..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="row g-4">
        <?php if ($grounds_result && $grounds_count > 0): ?>
            <?php while ($ground = mysqli_fetch_assoc($grounds_result)): ?>
                <?php
                $type_icon = '';
                switch ($ground['ground_type']) {
                    case 'floodlight':
                        $type_icon = 'bi-brightness-high';
                        break;
                    case 'covered':
                        $type_icon = 'bi-building';
                        break;
                    case 'premium':
                        $type_icon = 'bi-star-fill';
                        break;
                    case 'practice':
                        $type_icon = 'bi-trophy';
                        break;
                    case 'indoor':
                        $type_icon = 'bi-house-door';
                        break;
                    case 'vip':
                        $type_icon = 'bi-gem';
                        break;
                }

                $amenities = !empty($ground['amenities']) ? explode(',', $ground['amenities']) : [];
                $full_stars = floor((float) $ground['rating']);
                $half_star = (((float) $ground['rating']) - $full_stars) >= 0.5;
                ?>
                <div class="col-md-6 col-lg-4 d-flex">
                    <div class="card ground-card w-100">
                        <div class="position-relative">
                            <img src="<?php echo htmlspecialchars($ground['image_url']); ?>" class="card-img-top ground-img" alt="<?php echo htmlspecialchars($ground['name']); ?>">
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge bg-primary bg-opacity-75">
                                    <i class="bi <?php echo $type_icon; ?>"></i> <?php echo ucfirst($ground['ground_type']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($ground['name']); ?></h5>
                                <span class="location-badge">
                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($ground['location']); ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-2 ground-description">
                                <?php echo htmlspecialchars(substr((string) $ground['description'], 0, 80)); ?>...
                            </p>

                            <div class="rating mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $full_stars): ?>
                                        <i class="bi bi-star-fill"></i>
                                    <?php elseif ($i === $full_stars + 1 && $half_star): ?>
                                        <i class="bi bi-star-half"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="text-secondary ms-1">(<?php echo (int) $ground['total_reviews']; ?> reviews)</span>
                            </div>

                            <div class="mb-2 amenities-wrap">
                                <?php foreach (array_slice($amenities, 0, 3) as $amenity): ?>
                                    <span class="amenity-badge">
                                        <i class="bi bi-check-circle-fill text-success small"></i> <?php echo htmlspecialchars(trim($amenity)); ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($amenities) > 3): ?>
                                    <span class="amenity-badge">+<?php echo count($amenities) - 3; ?> more</span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <span class="stats-badge">
                                    <i class="bi bi-people"></i> Capacity: <?php echo (int) $ground['capacity']; ?>
                                </span>
                                <span class="stats-badge">
                                    <i class="bi bi-clock"></i> Per Hour
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center ground-price-row">
                                <div>
                                    <span class="price">Rs. <?php echo number_format((float) $ground['price_per_hour'], 0); ?>/hr</span>
                                    <br>
                                    <small class="text-muted">+ GST</small>
                                </div>
                                <a href="booking_form.php?id=<?php echo (int) $ground['id']; ?>" class="btn btn-book btn-primary">
                                    <i class="bi bi-calendar-plus me-1"></i> Book Now
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center py-5">
                    <i class="bi bi-building fs-1 d-block mb-3"></i>
                    <h4>No Grounds Found</h4>
                    <p class="mb-0">No cricket grounds match your search criteria. Please try different filters.</p>
                    <a href="slot_booking.php" class="btn btn-primary mt-3">
                        <i class="bi bi-arrow-repeat me-2"></i>Clear Filters
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($grounds_result && $grounds_count > 0): ?>
        <div class="text-center mt-4">
            <p class="text-muted">
                <i class="bi bi-info-circle"></i>
                Showing <?php echo $grounds_count; ?> grounds
            </p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('#filterForm .form-select').forEach(function (select) {
        select.addEventListener('change', function () {
            document.getElementById('filterForm').submit();
        });
    });
</script>

</body>
</html>

<?php include 'footer.php'; ?>
