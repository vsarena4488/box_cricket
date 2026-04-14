<?php include 'nevbar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Arena 1 Floodlight | Ground Details</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        
        /* Rating Stars */
        .rating-star {
            color: #ffc107;
            margin-right: 2px;
        }
        
        /* Section Headers */
        .section-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .section-title i {
            color: #0d6efd;
            margin-right: 10px;
        }
        
        
        /* Thumbnail Images */
        .thumbnail-img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .thumbnail-img:hover {
            border-color: #0d6efd;
        }
        
        /* Price Tag */
        .price-tag {
            background: #e8f3e9;
            color: #1e7b4c;
            padding: 6px 14px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
        }
        
        /* Favorite Button */
        
        
        /* Ground Specs */
        .spec-item {
            padding: 8px 0;
            border-bottom: 1px dashed #e9ecef;
        }
        .spec-label {
            font-weight: 600;
            color: #495057;
            width: 140px;
            display: inline-block;
        }

        /* favret button */
        .btn.btn-favorite {
            background-color: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
            transition: all 0.3s ease;
        }

        .btn.btn-favorite:hover {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>

<div class="container my-4 pt-5">

    <!-- ===== 2. GROUND HEADER SECTION ===== -->
    <div class="row align-items-center mb-4">
        <div class="col-md-8">
            <h1 class="display-6 fw-bold mb-2">Basic Ground details</h1>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="text-secondary"><i class="bi bi-geo-alt-fill text-primary"></i> Sector 62, Noida</span>
                <span class="price-tag"><i class="bi bi-tag"></i> Starts from ₹450</span>
            </div>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <button class="btn btn-primary btn-lg rounded-3 me-2"><i class="bi bi-calendar-check"></i><a href="slot_booking.php" class="text-white text-decoration-none"> Book Now</a></button>
        </div>
    </div>

    <!-- ===== 3. IMAGE GALLERY / CAROUSEL SECTION ===== -->
    <div class="row g-3 mb-5">
        <div class="col-md-8">
            <div id="groundCarousel" class="carousel slide rounded-4 overflow-hidden" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#groundCarousel" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#groundCarousel" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#groundCarousel" data-bs-slide-to="2"></button>
                    <button type="button" data-bs-target="#groundCarousel" data-bs-slide-to="3"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="https://images.unsplash.com/photo-1531415074968-036ba1b575da?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" class="d-block w-100" style="height: 400px; object-fit: cover;" alt="Ground Main">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" class="d-block w-100" style="height: 400px; object-fit: cover;" alt="Ground 2">
                    </div>
                    <div class="carousel-item">
                        <img src="../../../Box Cricket/images/box-cricket-setup.jpg" class="d-block w-100" style="height: 400px; object-fit: cover;" alt="Ground 3">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80" class="d-block w-100" style="height: 400px; object-fit: cover;" alt="Ground 4">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#groundCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#groundCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
        </div>
        <div class="col-md-4">
            <div class="row g-2">
                <div class="col-6">
                    <img src="https://images.unsplash.com/photo-1531415074968-036ba1b575da?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" class="thumbnail-img" alt="Thumb 1">
                </div>
                <div class="col-6">
                    <img src="https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" class="thumbnail-img" alt="Thumb 2">
                </div>
                <div class="col-6">
                    <img src="../../../Box Cricket/images/box-cricket-setup.jpg" class="thumbnail-img" alt="Thumb 3">
                </div>
                <div class="col-6">
                    <img src="https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" class="thumbnail-img" alt="Thumb 4">
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 4. GROUND INFORMATION / DESCRIPTION SECTION ===== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h4 class="section-title"><i class="bi bi-info-circle"></i>About This Ground</h4>
            <p class="lead mb-3">Premium floodlit cricket ground perfect for evening matches</p>
            <p class="text-secondary">Experience the best box cricket at Arena 1. This floodlit ground features professional-grade facilities, well-maintained pitch, and quality nets. Ideal for tournaments, practice sessions, and friendly matches. Located in the heart of Sector 62 with easy access and ample parking.</p>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="spec-item">
                        <span class="spec-label">Ground Size:</span>
                        <span class="text-secondary">85 x 65 meters</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Turf Type:</span>
                        <span class="text-secondary">Synthetic (International Quality)</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Pitch Type:</span>
                        <span class="text-secondary">Concrete with matting</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="spec-item">
                        <span class="spec-label">Seating Capacity:</span>
                        <span class="text-secondary">50 spectators</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Lighting Type:</span>
                        <span class="text-secondary">LED Floodlights (1000 lux)</span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Operating Hours:</span>
                        <span class="text-secondary">6:00 AM - 11:00 PM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== 6. PRICING & POLICY SECTION ===== -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h4 class="section-title"><i class="bi bi-currency-rupee"></i>Pricing & Policy</h4>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold mb-3">Price List</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Weekdays (Mon-Thu)</td>
                            <td class="fw-bold text-success">₹450/hr</td>
                        </tr>
                        <tr>
                            <td>Weekends (Fri-Sun)</td>
                            <td class="fw-bold text-success">₹550/hr</td>
                        </tr>
                        <tr>
                            <td>Peak Hours (6PM-10PM)</td>
                            <td class="fw-bold text-success">₹600/hr</td>
                        </tr>
                        <tr>
                            <td>Night Hours (10PM-12AM)</td>
                            <td class="fw-bold text-success">₹700/hr</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold mb-3">Cancellation Policy</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Free cancellation upto 24hrs before</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>50% refund if cancelled within 24hrs</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>No refund on same day cancellation</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Full refund for weather issues</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


<?php include 'footer.php'; ?>
