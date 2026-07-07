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
<nav class="navbar navbar-expand-lg navbar-dark bg-success fixed-top">
    <div class="container">
        <a class="navbar-brand" href="gest.php#section1">
            <i class="fas fa-trophy"></i> &nbsp; Box Cricket
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="gest.php#section1">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="gest.php#section2">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="gest.php#section3">Tournament</a></li>
                <li class="nav-item"><a class="nav-link" href="gest.php#section4">About</a></li>
                <li class="nav-item"><a class="nav-link" href="gest.php#section5">Gallery</a></li>
                <li class="nav-item"><a class="nav-link" href="gest.php#section6">Contact</a></li>
            </ul>

            <div class="d-flex">
                <a href="<?php echo h($guest_nav_target); ?>" class="btn btn-outline-light text-bolder me-2"><?php echo h($guest_nav_label); ?></a>
                <a href="register.php" class="btn btn-outline-light text-bolder">Register</a>
            </div>
        </div>
    </div>
</nav>