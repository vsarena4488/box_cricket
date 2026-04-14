<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Box Cricket - Footer</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>

        .footer {
            background-color: #1a2b3c;
            color: #fff;
            padding: 30px 0 15px;
            margin-top: auto;
        }
        .footer a {
            color: #fff;
            text-decoration: none;
            transition: color 3s;
        }

        .social-icons a {
            display: inline-block;
            width: 36px;
            height: 36px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 36px;
            margin-left: 8px;
            transition: all 0.3s;
        }
        .social-icons a:hover {
            background-color: #06ff27;
            color: #1a2b3c;
        }

        .copyright-section {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<!-- ===== FOOTER WITH YOUR REQUESTED STRUCTURE ===== -->
<footer class="footer d-flex flex-column">
    <div class="container w-100">
        
        <!-- First Line: Copyright and Social Media Icons (Right Side) -->
        <div class="justify-content-between text-center">
            <div class="copyright-text">
                <i class="bi bi-c-circle"></i> 2024 Box Cricket. All rights reserved. | Designed for cricket lovers
            </div>
           
        <!-- Second Line: Some Text (Description) -->
        <div class="row mb-3">
            <div class="col-12">
                <p class="text-center text-white-50 small mb-0">
                    Experience the thrill of box cricket with state-of-the-art facilities, professional coaching, and exciting tournaments. 
                    Join our community of cricket lovers and enjoy the game like never before.
                </p>
            </div>
        </div>

        <hr>

        <!-- Extra bottom line (optional) -->
         <div class="social-icons">
                <a href="#"><i class="bi bi-facebook"></i></a>
                <a href="#"><i class="bi bi-twitter"></i></a>
                <a href="#"><i class="bi bi-instagram"></i></a>
                <a href="#"><i class="bi bi-whatsapp"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>