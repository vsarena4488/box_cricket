<?php
include_once '../gest/db_config.php';
require_user_login();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        html,
        body {
            overflow-x: hidden;
        }

        img,
        video,
        iframe {
            max-width: 100%;
            height: auto;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Small adjustment for center alignment */
        .navbar .navbar-nav {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .navbar .nav-item {
            margin: 0 10px;
        }

        .profile-icon {
            font-size: 24px;
            color: white;
            margin-right: 15px;
        }

        @media (max-width: 991.98px) {
            .navbar .navbar-nav {
                width: auto;
                justify-content: flex-start;
                align-items: flex-start;
            }

            .navbar .nav-item {
                margin: 4px 0;
            }

            .navbar .d-flex.align-items-center {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
                margin-top: 10px;
                width: 100%;
            }

            .navbar .d-flex.align-items-center .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <!-- ===== NAVBAR WITH LOGO LEFT - LINKS CENTER - LOGOUT RIGHT ===== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success fixed-top">
        <div class="container">
            <!-- Logo and Site Name (LEFT SIDE) -->
            <a class="navbar-brand" href="home.php">
                <i class="bi bi-trophy-fill"></i> Box Cricket
            </a>

            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Center Links -->
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Ground_details.php">Ground Details</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="match.php">tournament</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="slot_booking.php">Slot Booking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking_details.php">Booked Slots</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="feedback.php">Feedback</a>
                    </li>
                </ul>

                <!-- Profile Icon and Logout Button (RIGHT SIDE) -->
                <div class="d-flex align-items-center">
                    <a href="profile.php" class="text-white me-3 small fw-semibold text-decoration-none" aria-label="Open user profile">
                        <?php echo h($_SESSION['user_name'] ?? 'Player'); ?>
                    </a>
                    <a href="profile.php" aria-label="Open user profile"><i class="bi bi-person-circle profile-icon"></i></a>

                    <!-- Logout Button -->
                    <a href="logout.php" class="btn btn-outline-light d-inline-flex align-items-center gap-1">
                        Logout <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
