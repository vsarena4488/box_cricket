<?php
include_once 'db_config.php';
$guest_nav_target = 'login.php';
$guest_nav_label = 'Login';

if (is_admin_logged_in()) {
    $guest_nav_target = '../admin/home.php';
    $guest_nav_label = 'Admin Dashboard';
} elseif (is_user_logged_in()) {
    $guest_nav_target = '../user_dashbord/home.php';
    $guest_nav_label = 'My Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket Navbar</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        main,
        section,
        .container,
        .container-fluid {
            min-width: 0;
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

            .navbar .d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: 8px;
                width: 100%;
                margin-top: 10px;
            }

            .navbar .d-flex .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <!-- ===== NAVBAR WITH LOGO LEFT - LINKS CENTER - BUTTONS RIGHT ===== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success fixed-top">
        <div class="container">
            <!-- Logo - LEFT SIDE -->
            <a class="navbar-brand" href="#">
                <i class="fas fa-trophy"></i> &nbsp; Box Cricket
            </a>

            <!-- Mobile Menu Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Links - CENTER -->
                <ul class="navbar-nav mx-auto"> <!-- mx-auto centers the links -->
                    <li class="nav-item">
                        <a class="nav-link fon" href="gest.php#section1">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gest.php#section2">feachers</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gest.php#section3">tournamet</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gest.php#section4">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gest.php#section5">gallary</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="gest.php#section6">Contact</a>
                    </li>
                </ul>

                <!-- Buttons - RIGHT SIDE -->
                <div class="d-flex">
                    <a href="<?php echo h($guest_nav_target); ?>" class="btn btn-outline-light text-bolder me-2"><?php echo h($guest_nav_label); ?></a>
                    <a href="register.php" class="btn btn-outline-light text-bolder">register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
