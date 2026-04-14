<?php include 'db_config.php'; ?>
<?php

// Initialize variables
$name = $email = $message = "";
$name_err = $email_err = $message_err = "";
$success_msg = $error_msg = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate Name
    if (empty($_POST['fullname'])) {
        $name_err = "Name is required";
    } else {
        $name = trim($_POST['fullname']);
        // Check if name contains only letters and spaces
        if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
            $name_err = "Name can only contain letters and spaces";
        } elseif (strlen($name) < 3) {
            $name_err = "Name must be at least 3 characters";
        }
    }
    
    // Validate Email
    if (empty($_POST['email'])) {
        $email_err = "Email is required";
    } else {
        $email = trim($_POST['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_err = "Invalid email format";
        }
    }
    
    // Validate Message
    if (empty($_POST['message'])) {
        $message_err = "Message is required";
    } else {
        $message = trim($_POST['message']);
        if (strlen($message) < 10) {
            $message_err = "Message must be at least 10 characters";
        }
    }
    
    // If no errors, insert into database
    if (empty($name_err) && empty($email_err) && empty($message_err)) {
        
        $contact_stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");

        if ($contact_stmt) {
            mysqli_stmt_bind_param($contact_stmt, "sss", $name, $email, $message);
        }

        if ($contact_stmt && mysqli_stmt_execute($contact_stmt)) {
            $success_msg = "Message sent successfully! We'll get back to you soon.";
            // Clear form fields
            $name = $email = $message = "";
        } else {
            $error_msg = "Something went wrong. Please try again later.";
        }

        if ($contact_stmt) {
            mysqli_stmt_close($contact_stmt);
        }
    }
}
?>
<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket Navbar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">


    <style>
        .feature-card {
            transition: 0.3s;
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .cricket-icon {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 15px;
        }

        .match-card {
            transition: 0.3s;
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .match-card:hover {
            transform: translateY(-5px);
        }

        .images {
            height: 250px;
            object-fit: cover;
            border-radius: 25px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <!-- this is carousel section  -->

    <section id="section1" class="py-5">
        <div id="carouselExampleCaptions" class="carousel slide" data-bs-ride="carousel">

            <!-- Indicators -->

            <div class="carousel-indicators">
                <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="0" class="active"></button>
                <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="1"></button>
                <button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="2"></button>
            </div>

            <!-- here the silders  -->

            <div class="carousel-inner ">

                <!-- Slide 1 -->

                <div class="carousel-item active">

                    <img src="../../../Box Cricket/images/box-cricket.jpg" class="d-block w-100" alt="Cricket Stadium"
                        style="height: 690px; object-fit: cover;">
                    <!-- Bootstrap have no height properties properly, so we use inline style -->

                    <div class="carousel-caption d-none d-md-block">
                        <h5>Welcome to Box Cricket</h5>
                        <p>Experience the thrill of indoor cricket matches</p>
                    </div>
                </div>

                <!-- Slide 2 -->

                <div class="carousel-item">

                    <img src="../../../Box Cricket/images/box-cricket.jpg" class="d-block w-100" alt="Cricket Ground"
                        style="height: 690px; object-fit: cover;">
                    <!-- Bootstrap have no height properties properly, so we use inline style -->

                    <div class="carousel-caption d-none d-md-block">
                        <h5>Join Tournaments</h5>
                        <p>Play with friends and win exciting prizes</p>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="carousel-item">

                    <img src="../../../Box Cricket/images/box-cricket.jpg" class="d-block w-100" alt="Cricket Match" style="height: 690px; object-fit: cover;">
                    <!-- Bootstrap have no height properties properly, so we use inline style -->

                    <div class="carousel-caption d-none d-md-block">
                        <h5>Book Your Slot</h5>
                        <p>24/7 availability for box cricket matches</p>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="prev">
                <span class="carousel-control-prev-icon "></span>
                <span class="visually-hidden bg-black">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="next">
                <span class="carousel-control-next-icon "></span>
                <span class="visually-hidden bg-black">Next</span>
            </button>
        </div>
    </section>

    <!-- ================================================================ -->

    <!-- === BOX AREA ===== -->
    <section id="section2">
        <div class="container pt-5 pb-5">
            <h2 class="text-center mb-5 mt-5 pt-5 pb-3">Why Choose Box Cricket?</h2>
            <div class="row g-5">

                <!-- Feature 1 -->
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="cricket-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Team Matches</h3>
                        <p class="text-muted">Play with your friends in exciting 5 vs 5 box cricket matches</p>
                    </div>
                </div>

                <!-- Feature 2 -->
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="cricket-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h3>Tournaments</h3>
                        <p class="text-muted">Participate in weekly tournaments and win exciting prizes</p>
                    </div>
                </div>

                <!-- Feature 3 -->
                <div class="col-md-4">
                    <div class="card feature-card h-100 text-center p-4">
                        <div class="cricket-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3>Flexible Timing</h3>
                        <p class="text-muted">Book slots according to your convenience, open 24/7</p>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- ================================================================ -->


    <!-- ==== Upcoming Matches section ===== -->
    <section id="section3" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5 mt-5">Upcoming Matches</h2>

            <div class="row">
                <?php
                // Query to get upcoming matches only (date >= today) and limit to 3
                $today = date('Y-m-d');
                $query = "SELECT id, team1_name, team2_name, match_date, match_time, venue, available_slots, total_slots, status
                      FROM matches
                      WHERE match_date >= '$today'
                      AND status = 'upcoming'
                      ORDER BY match_date ASC
                      LIMIT 3";
                $result = mysqli_query($conn, $query);

                // Check if there are any upcoming matches
                if ($result && mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $available_slots = isset($row['available_slots']) ? (int) $row['available_slots'] : 0;
                        $is_sold_out = $available_slots <= 0;
                ?>
                        <div class="col-md-4 mb-4">
                            <div class="card match-card">
                                <div class="card-body">
                                    <div class="justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">
                                                <?php echo h($row['team1_name']) . " vs " . h($row['team2_name']); ?>
                                            </h5>

                                            <p class="card-text text-muted">
                                                <i class="fas fa-calendar me-2"></i>
                                                <?php echo date('F d, Y', strtotime($row['match_date'])); ?><br>

                                                <i class="fas fa-clock me-2"></i>
                                                <?php echo date('h:i A', strtotime($row['match_time'])); ?><br>

                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?php echo h($row['venue']); ?>
                                            </p>
                                        </div>

                                        <!-- Status Badge -->
                                        <?php if (!$is_sold_out) { ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php } else { ?>
                                            <span class="badge bg-danger">Full</span>
                                        <?php } ?>
                                    </div>

                                    <!-- Slots Badge -->
                                    <?php if ($available_slots > 0) { ?>
                                        <span class="badge bg-warning text-dark mt-2">
                                            <?php echo $available_slots; ?> spots left
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary mt-2">No slots available</span>
                                    <?php } ?>

                                    <a href="login.php" class="btn btn-primary mt-3 w-100 <?php echo $is_sold_out ? 'disabled' : ''; ?>" <?php echo $is_sold_out ? 'aria-disabled="true"' : ''; ?>>
                                        <?php if ($is_sold_out) { ?>
                                            <i class="fas fa-ban me-1"></i> Sold Out
                                        <?php } else { ?>
                                            <i class="fas fa-ticket-alt me-1"></i> Book Now
                                        <?php } ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php
                    } // end while
                } else {
                    // No upcoming matches message
                    ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No upcoming matches at the moment. Please check back later!
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- View All Matches Button -->
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i> View All Matches
                </a>
            </div>
        </div>
    </section>

    <!-- ================================================================ -->

    <!-- ===== ABOUT SETION ==== -->

    <section id="section4" class="py-5">
        <div class="container mt-5">
            <div class="row g-5 align-items-center">
                <div class="col-md-5">

                    <img
                        src="https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80"
                        alt="Cricket Ground" class="img-fluid rounded shadow-lg">

                </div>
                <div class="col-md-7">
                    <h2>About Box Cricket</h2>
                    <p class="lead">Experience the thrill of cricket in a compact format!</p>
                    <p>Box cricket is an exciting indoor version of traditional cricket, played in a enclosed space. It's faster,
                        more engaging, and perfect for year-round play. Whether you're a beginner or a pro, our facility offers the
                        perfect environment to enjoy this wonderful sport.</p>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Professional indoor facilities</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Floodlit courts for evening play</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Equipment available on rent</li>
                        <li class="mb-2"><i class="fas fa-check-circle text-primary me-2"></i>Expert coaches for training</li>
                    </ul>
                </div>

            </div>
        </div>
    </section>

    <!-- ================================================================ -->

    <!-- === gallary section ==== -->
    <!-- Gallery - Clean & Simple -->
    <section id="section5" class="py-5">
        <div class="container my-5">
            <!-- Header -->
            <div class="text-center mb-5">
                <h2> Box Cricket Gallery</h2>
                <p class="text-muted">Moments from our matches</p>
            </div>

            <!-- All 3 Rows with Gaps -->
            <div class="row g-4"> <!-- g-4 adds gap between all columns -->
                <!-- Row 1 - Images 1-3 -->
                <div class="col-md-4">
                    <img
                        src="https://images.unsplash.com/photo-1531415074968-036ba1b575da?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                        class="images img-fluid w-100" alt="Gallery 1">
                </div>
                <div class="col-md-4">
                    <img
                        src="https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                        class="images img-fluid w-100" alt="Gallery 2">
                </div>
                <div class="col-md-4">
                    <img
                        src="https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                        class="images img-fluid w-100" alt="Gallery 3">
                </div>

                <!-- Row 2 - Images 4-6 -->
                <div class="col-md-4">
                    <img
                        src="https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                        class="images img-fluid w-100" alt="Gallery 4">
                </div>
                <div class="col-md-4">
                    <img
                        src="https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                        class="images img-fluid w-100" alt="Gallery 5">
                </div>
                <div class="col-md-4">
                    <img
                        src="https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80"
                        class="images img-fluid w-100" alt="Gallery 6">
                </div>
            </div>
        </div>
    </section>


    <!-- ================================================================ -->

    <!-- === contect us ==== -->

    <!-- ==== Contact Us Section ===== -->
<section id="section6" class="py-5">
    <div class="container mb-5">
        <h2 class="text-center mb-5">Contact Us</h2>
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Side - Contact Info -->
            <div class="col-md-6">
                <div class="p-4">
                    <h5>Get in Touch</h5>
                    <p><i class="fas fa-phone me-2"></i>+91 98765 43210</p>
                    <p><i class="fas fa-envelope me-2"></i>info@boxcricket.com</p>
                    <p><i class="fas fa-map-marker-alt me-2"></i>123 Sports Complex, Sector 62, Noida</p>

                    <!-- Social Media Links -->
                    <div class="mt-4">
                        <a href="#" class="btn btn-outline-primary me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="btn btn-outline-primary me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="btn btn-outline-primary me-2"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <!-- Right Side - Contact Form -->
            <div class="col-md-6">
                <form id="contactForm" action="<?php echo h($_SERVER['PHP_SELF']); ?>" method="post">
                    <!-- Name Field -->
                    <div class="mb-3">
                        <input type="text" class="form-control <?php echo !empty($name_err) ? 'is-invalid' : ''; ?>" 
                               name="fullname" id="fullname" 
                               placeholder="Your Name" 
                               value="<?php echo htmlspecialchars($name); ?>"
                               data-validation="required alphabetic min" data-min="3">
                        <div class="invalid-feedback">
                            <?php echo $name_err; ?>
                        </div>
                    </div>
                    
                    <!-- Email Field -->
                    <div class="mb-3">
                        <input type="email" class="form-control <?php echo !empty($email_err) ? 'is-invalid' : ''; ?>" 
                               name="email" id="email" 
                               placeholder="Your Email" 
                               value="<?php echo htmlspecialchars($email); ?>"
                               data-validation="required email">
                        <div class="invalid-feedback">
                            <?php echo $email_err; ?>
                        </div>
                    </div>
                    
                    <!-- Message Field -->
                    <div class="mb-3">
                        <textarea class="form-control <?php echo !empty($message_err) ? 'is-invalid' : ''; ?>" 
                                  name="message" id="message" 
                                  rows="4" placeholder="Your Message"
                                  data-validation="required min" data-min="10"><?php echo htmlspecialchars($message); ?></textarea>
                        <div class="invalid-feedback">
                            <?php echo $message_err; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- JavaScript for Client-side Validation -->
<!-- <script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Validate Name
    let name = document.getElementById('fullname').value.trim();
    let nameError = document.getElementById('fullname').nextElementSibling;
    if (name === '') {
        nameError.textContent = 'Name is required';
        document.getElementById('fullname').classList.add('is-invalid');
        isValid = false;
    } else if (!/^[a-zA-Z\s]+$/.test(name)) {
        nameError.textContent = 'Name can only contain letters and spaces';
        document.getElementById('fullname').classList.add('is-invalid');
        isValid = false;
    } else if (name.length < 3) {
        nameError.textContent = 'Name must be at least 3 characters';
        document.getElementById('fullname').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('fullname').classList.remove('is-invalid');
    }
    
    // Validate Email
    let email = document.getElementById('email').value.trim();
    let emailError = document.getElementById('email').nextElementSibling;
    let emailPattern = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
    if (email === '') {
        emailError.textContent = 'Email is required';
        document.getElementById('email').classList.add('is-invalid');
        isValid = false;
    } else if (!emailPattern.test(email)) {
        emailError.textContent = 'Invalid email format';
        document.getElementById('email').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }
    
    // Validate Message
    let message = document.getElementById('message').value.trim();
    let messageError = document.getElementById('message').nextElementSibling;
    if (message === '') {
        messageError.textContent = 'Message is required';
        document.getElementById('message').classList.add('is-invalid');
        isValid = false;
    } else if (message.length < 10) {
        messageError.textContent = 'Message must be at least 10 characters';
        document.getElementById('message').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('message').classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
    }
});
</script> -->

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- validation link -->
    <script src="../javascript/jquery-4.0.0.js"></script>
    <script src="../javascript/validation.js"></script>

</body>

</html>

<?php include 'footer.php'; ?>
