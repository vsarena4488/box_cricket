<?php
include_once '../gest/db_config.php';

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin' ||
    !isset($_SESSION['admin']) ||
    $_SESSION['admin'] === ''
) {
    header('Location: ../gest/login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$current_page = basename($_SERVER['PHP_SELF']);
$is_standalone_page = basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME']);

$menu_items = [
    ['page' => 'home.php', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
    ['page' => 'users_details.php', 'icon' => 'bi-people-fill', 'label' => 'Users'],
    ['page' => 'ground_details.php', 'icon' => 'bi-building', 'label' => 'Grounds'],
    ['page' => 'turnament_details.php', 'icon' => 'bi-trophy-fill', 'label' => 'Tournaments'],
    ['page' => 'slot_bookind_details.php', 'icon' => 'bi-calendar-check-fill', 'label' => 'Slot Bookings'],
    ['page' => 'feadback_details.php', 'icon' => 'bi-star-fill', 'label' => 'Feedback'],
    ['page' => 'contect_message.php', 'icon' => 'bi-envelope-fill', 'label' => 'Contact Messages'],
    ['page' => 'profile.php', 'icon' => 'bi-person-circle', 'label' => 'Profile'],
    ];
?>

<?php if ($is_standalone_page): ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Box Cricket - Admin Sidebar</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <?php endif; ?>

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

        .main-content {
            margin-left: 280px;
            min-width: 0;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a2b3c 0%, #0f1a26 100%);
            color: #fff;
            transition: all 0.3s ease-in-out;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }

        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: #ffd700;
            letter-spacing: 0.5px;
        }

        .sidebar-header p {
            margin: 8px 0 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar-menu {
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 96px);
        }

        .sidebar-menu-item {
            padding: 12px 25px;
            margin: 5px 10px;
            border-radius: 12px;
            transition: all 0.3s ease-in-out;
        }

        .sidebar-menu-item a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            width: 100%;
        }

        .sidebar-menu-item:hover {
            background: rgba(255, 215, 0, 0.12);
            transform: translateX(5px);
        }

        .sidebar-menu-item:hover a {
            color: #ffd700;
        }

        .sidebar-menu-item.active {
            background: #0d6efd;
            box-shadow: 0 5px 15px rgba(13, 110, 253, 0.3);
        }

        .sidebar-menu-item.active a {
            color: #fff;
        }

        .sidebar-menu-item i {
            font-size: 18px;
            min-width: 20px;
        }

        .sidebar-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 15px 20px;
        }

        .logout {
            margin-top: auto;
        }

        .logout a {
            color: #ff6767 !important;
        }

        .logout:hover {
            background: rgba(255, 0, 0, 0.12);
        }

        .logout:hover a {
            color: #ff2f2f !important;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 250px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
            }

            .content-container {
                padding: 16px !important;
            }

            .top-navbar {
                padding: 12px 16px !important;
                flex-wrap: wrap;
                gap: 10px;
            }

            .section-title,
            .search-filter-bar,
            .filter-bar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
            }

            .search-box {
                max-width: 100% !important;
            }

            .action-buttons {
                flex-wrap: wrap;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            }

            .sidebar-header {
                padding: 20px 15px;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
                padding: 15px 10px;
                min-height: auto;
            }

            .sidebar-menu-item {
                margin: 0;
            }

            .logout {
                margin-top: 0;
            }
        }
    </style>

    <?php if ($is_standalone_page): ?>
    </head>

    <body style="margin:0; background:#f4f6f9;">
    <?php endif; ?>

    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="bi bi-trophy-fill me-2"></i>Box Cricket</h3>
            <p><?php echo htmlspecialchars($admin_name); ?></p>
        </div>

        <div class="sidebar-menu">
            <?php for ($i = 0; $i < count($menu_items); $i++): ?>
                <div class="sidebar-menu-item <?php echo $current_page === $menu_items[$i]['page'] ? 'active' : ''; ?>">
                    <a href="<?php echo $menu_items[$i]['page']; ?>">
                        <i class="bi <?php echo $menu_items[$i]['icon']; ?>"></i>
                        <span><?php echo $menu_items[$i]['label']; ?></span>
                    </a>
                </div>
            <?php endfor; ?>

            <div class="sidebar-divider"></div>

            <div class="sidebar-menu-item logout">
                <a href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($is_standalone_page): ?>
        <div style="margin-left:280px; padding:30px;">
            <h2 style="margin:0 0 10px;">Admin Sidebar Preview</h2>
            <p style="margin:0; color:#6c757d;">This file is complete and can be included in all admin pages.</p>
        </div>
        <!-- ========== BOOTSTRAP JS ========== -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    </body>

    </html>
<?php endif; ?>
