<?php
session_start();
include_once '../gest/db_config.php';

if (function_exists('require_admin_login')) {
    require_admin_login();
} else {
    if (!isset($_SESSION['admin']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../gest/login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Website Settings</title>
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

        /* Admin Layout */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }


        /* ========== MAIN CONTENT AREA ========== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: all 0.3s;
        }

        /* ========== TOP NAVBAR STYLES ========== */
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

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 24px;
            color: #6c757d;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            border-radius: 40px;
            background: #f8f9fa;
            transition: all 0.3s;
            cursor: pointer;
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

        .dropdown-menu-custom {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            padding: 8px;
            margin-top: 10px;
            min-width: 200px;
        }

        .dropdown-item-custom {
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: #333;
        }

        .dropdown-item-custom:hover {
            background: #f8f9fa;
        }

        .dropdown-item-custom.text-danger:hover {
            background: #ffe8e8;
        }

        .content-container {
            padding: 30px;
        }

        /* Settings Styles */
        .settings-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .settings-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .settings-header h4 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .settings-header p {
            font-size: 13px;
            color: #6c757d;
            margin: 5px 0 0;
        }

        .form-label {
            font-weight: 500;
            color: #495057;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
        }

        .btn-save {
            background: #0d6efd;
            color: white;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-save:hover {
            background: #0b5ed7;
            transform: translateY(-2px);
        }

        .btn-reset {
            background: #6c757d;
            color: white;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 500;
            margin-left: 10px;
        }

        .btn-reset:hover {
            background: #5c636a;
        }

        .alert-info {
            border-radius: 12px;
            background: #e7f1ff;
            border: none;
            color: #0d6efd;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                opacity: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <!-- ========== SIDEBAR NAVIGATION ========== -->
    <?php include 'nevbar.php'; ?>

    <!-- ========== MAIN CONTENT AREA ========== -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="page-title">
                <h2>Website Settings</h2>
                <p>Configure website settings, payment details, and system preferences</p>
            </div>
            <div class="navbar-right">
                <div class="notification-icon" onclick="alert('You have 3 new notifications')">
                    <i class="bi bi-bell-fill"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="dropdown">
                    <div class="profile-trigger" data-bs-toggle="dropdown">
                        <div class="profile-avatar">A</div>
                        <div class="profile-info d-none d-sm-block">
                            <div class="profile-name">Admin User</div>
                            <div class="profile-role">Super Administrator</div>
                        </div>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                        <li><a class="dropdown-item dropdown-item-custom" href="#"><i class="bi bi-person-circle"></i> My Profile</a></li>
                        <li><a class="dropdown-item dropdown-item-custom" href="#"><i class="bi bi-key"></i> Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item dropdown-item-custom text-danger" href="#" onclick="logout()"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ========== SETTINGS CONTENT ========== -->
        <div class="content-container">
            
            <!-- Info Alert -->
            <div class="alert alert-info mb-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Note:</strong> Changes made here will affect the entire website. Please review before saving.
            </div>

            <div class="row">
                <!-- Left Column - General Settings -->
                <div class="col-lg-6">
                    <!-- General Settings Card -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h4><i class="bi bi-globe me-2"></i>General Settings</h4>
                            <p>Basic website information and contact details</p>
                        </div>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Website Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-trophy"></i></span>
                                    <input type="text" class="form-control" value="Box Cricket" placeholder="Website Name">
                                </div>
                                <small class="text-secondary">This appears on the navbar and title bar</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Website Logo URL</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-image"></i></span>
                                    <input type="text" class="form-control" value="https://example.com/logo.png" placeholder="Logo URL">
                                </div>
                                <small class="text-secondary">Enter image URL for website logo</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" value="admin@boxcricket.com" placeholder="Admin Email">
                                </div>
                                <small class="text-secondary">All notifications will be sent to this email</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Contact Phone</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control" value="+91 9876543210" placeholder="Contact Number">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                    <textarea class="form-control" rows="2">Sector 62, Noida, Uttar Pradesh, India - 201301</textarea>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Time Zone</label>
                                <select class="form-select">
                                    <option selected>Asia/Kolkata (IST)</option>
                                    <option>Asia/Dubai (GST)</option>
                                    <option>America/New_York (EST)</option>
                                    <option>Europe/London (GMT)</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Social Media Settings Card -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h4><i class="bi bi-share-fill me-2"></i>Social Media Links</h4>
                            <p>Connect your social media profiles</p>
                        </div>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Facebook</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-facebook text-primary"></i></span>
                                    <input type="text" class="form-control" value="https://facebook.com/boxcricket" placeholder="Facebook URL">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Instagram</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-instagram text-danger"></i></span>
                                    <input type="text" class="form-control" value="https://instagram.com/boxcricket" placeholder="Instagram URL">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Twitter</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-twitter text-info"></i></span>
                                    <input type="text" class="form-control" value="https://twitter.com/boxcricket" placeholder="Twitter URL">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">YouTube</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-youtube text-danger"></i></span>
                                    <input type="text" class="form-control" value="https://youtube.com/boxcricket" placeholder="YouTube URL">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Payment & Business Settings -->
                <div class="col-lg-6">
                    <!-- Payment Settings Card -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h4><i class="bi bi-credit-card me-2"></i>Payment Settings</h4>
                            <p>Configure payment gateway and pricing</p>
                        </div>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">UPI ID</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-qr-code"></i></span>
                                    <input type="text" class="form-control" value="boxcricket@okhdfcbank" placeholder="UPI ID">
                                </div>
                                <small class="text-secondary">Users will pay to this UPI ID</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" class="form-control" value="Box Cricket Sports Pvt Ltd" placeholder="Account Name">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Bank Account Number</label>
                                <input type="text" class="form-control" value="1234567890123456" placeholder="Account Number">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">IFSC Code</label>
                                        <input type="text" class="form-control" value="HDFC0001234" placeholder="IFSC Code">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">GST Percentage</label>
                                        <input type="text" class="form-control" value="18%" placeholder="GST %">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Slot Booking Price</label>
                                        <input type="text" class="form-control" value="₹199" placeholder="Slot Price">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tournament Ticket Price</label>
                                        <input type="text" class="form-control" value="₹499" placeholder="Tournament Price">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Business Settings Card -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h4><i class="bi bi-briefcase me-2"></i>Business Settings</h4>
                            <p>Configure operational settings</p>
                        </div>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Operating Hours</label>
                                <select class="form-select">
                                    <option selected>24/7 (Always Open)</option>
                                    <option>6:00 AM - 11:00 PM</option>
                                    <option>8:00 AM - 10:00 PM</option>
                                    <option>Custom Hours</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Default Match Duration</label>
                                <select class="form-select">
                                    <option>1 Hour</option>
                                    <option selected>1.5 Hours</option>
                                    <option>2 Hours</option>
                                    <option>2.5 Hours</option>
                                    <option>3 Hours</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Max Teams per Tournament</label>
                                <select class="form-select">
                                    <option>4 Teams</option>
                                    <option>8 Teams</option>
                                    <option selected>16 Teams</option>
                                    <option>24 Teams</option>
                                    <option>32 Teams</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cancellation Policy</label>
                                <select class="form-select">
                                    <option selected>Free cancellation upto 24 hours</option>
                                    <option>Free cancellation upto 12 hours</option>
                                    <option>No cancellation</option>
                                    <option>Custom policy</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Refund Processing Days</label>
                                <select class="form-select">
                                    <option selected>3-5 Business Days</option>
                                    <option>5-7 Business Days</option>
                                    <option>7-10 Business Days</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <!-- Notification Settings Card -->
                    <div class="settings-card">
                        <div class="settings-header">
                            <h4><i class="bi bi-bell-fill me-2"></i>Notification Settings</h4>
                            <p>Configure email and SMS notifications</p>
                        </div>
                        <form>
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="emailNotif" checked>
                                    <label class="form-check-label" for="emailNotif">
                                        <i class="bi bi-envelope-check me-2"></i>Send email notifications for new bookings
                                    </label>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="smsNotif" checked>
                                    <label class="form-check-label" for="smsNotif">
                                        <i class="bi bi-chat-dots me-2"></i>Send SMS alerts for booking confirmations
                                    </label>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="adminNotif" checked>
                                    <label class="form-check-label" for="adminNotif">
                                        <i class="bi bi-envelope-paper me-2"></i>Notify admin on new registrations
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="feedbackNotif">
                                    <label class="form-check-label" for="feedbackNotif">
                                        <i class="bi bi-star me-2"></i>Notify when new feedback is submitted
                                    </label>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Save Actions -->
            <div class="settings-card">
                <div class="row">
                    <div class="col-12 text-end">
                        <button class="btn btn-reset" onclick="resetSettings()">
                            <i class="bi bi-arrow-counterclockwise me-2"></i>Reset to Default
                        </button>
                        <button class="btn btn-save" onclick="saveSettings()">
                            <i class="bi bi-check-lg me-2"></i>Save All Changes
                        </button>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-light border small text-center">
                            <i class="bi bi-shield-check me-2 text-success"></i>
                            All settings are securely saved. Changes will take effect immediately.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Save settings function
    function saveSettings() {
        // Show success message
        alert('✅ Settings saved successfully!\n\nYour changes have been applied to the website.');
        
        // In real implementation, you would send data to server here
        // fetch('save-settings.php', {
        //     method: 'POST',
        //     body: new FormData(document.querySelector('form'))
        // });
    }

    // Reset settings function
    function resetSettings() {
        if(confirm('⚠️ Are you sure you want to reset all settings to default values?\n\nThis action cannot be undone.')) {
            alert('Settings reset to default values.');
            location.reload();
        }
    }

    // Logout function
    function logout() {
        if(confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
