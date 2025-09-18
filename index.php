<?php
// Check if user is already logged in
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if ($_SESSION['user_role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Marketing Agent - RFM Analysis Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --accent-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .hero-section {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="150" fill="url(%23a)"/><circle cx="800" cy="300" r="100" fill="url(%23a)"/><circle cx="400" cy="700" r="120" fill="url(%23a)"/><circle cx="900" cy="800" r="80" fill="url(%23a)"/></svg>');
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 1.5rem;
        }
        
        .icon-analytics { background: var(--secondary-gradient); }
        .icon-automation { background: var(--accent-gradient); }
        .icon-insights { background: var(--primary-gradient); }
        
        .btn-gradient {
            background: var(--secondary-gradient);
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 172, 254, 0.3);
            color: white;
        }
        
        .btn-outline-gradient {
            border: 2px solid;
            border-image: var(--secondary-gradient) 1;
            background: transparent;
            color: #4facfe;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline-gradient:hover {
            background: var(--secondary-gradient);
            color: white;
            border-image: none;
            border-color: transparent;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 2rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }
        
        .demo-accounts {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
        }
        
        .account-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #4facfe;
        }
        
        .navbar-brand-custom {
            font-size: 1.5rem;
            font-weight: bold;
            background: var(--secondary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        
        .floating-elements::before,
        .floating-elements::after {
            content: '';
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-elements::before {
            width: 100px;
            height: 100px;
            top: 20%;
            right: 10%;
            animation-delay: -2s;
        }
        
        .floating-elements::after {
            width: 150px;
            height: 150px;
            bottom: 20%;
            left: 10%;
            animation-delay: -4s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        footer {
            background: #2c3e50;
            color: white;
            padding: 3rem 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: rgba(44, 62, 80, 0.95); backdrop-filter: blur(10px);">
        <div class="container">
            <a class="navbar-brand navbar-brand-custom" href="#">
                <i class="fas fa-chart-line me-2"></i>
                Smart Marketing Agent
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#demo">Demo</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-light btn-sm ms-2" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="floating-elements"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content" data-aos="fade-right">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Smart Marketing Agent
                        <span class="d-block text-warning">RFM Analysis Platform</span>
                    </h1>
                    <p class="lead text-white-50 mb-4">
                        Platform analisis pelanggan berbasis RFM (Recency, Frequency, Monetary) yang membantu UMKM memahami perilaku pelanggan dan meningkatkan strategi pemasaran dengan insights data yang akurat.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="login.php" class="btn-gradient">
                            <i class="fas fa-rocket me-2"></i>
                            Mulai Sekarang
                        </a>
                        <a href="#demo" class="btn-outline-gradient">
                            <i class="fas fa-play me-2"></i>
                            Lihat Demo
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center" data-aos="fade-left">
                    <div class="position-relative">
                        <i class="fas fa-chart-line text-white" style="font-size: 15rem; opacity: 0.1;"></i>
                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <i class="fas fa-users text-warning" style="font-size: 4rem;"></i>
                                <p class="text-white mt-3 mb-0">Customer Analytics</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="section-title">Fitur Unggulan Platform</h2>
                <p class="lead text-muted">Solusi lengkap untuk analisis pelanggan dan strategi pemasaran UMKM</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon icon-analytics">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4 class="fw-bold mb-3">RFM Analysis</h4>
                        <p class="text-muted">Analisis mendalam tentang Recency, Frequency, dan Monetary value pelanggan untuk segmentasi yang akurat dan strategi pemasaran yang tepat sasaran.</p>
                        <ul class="list-unstyled mt-3">
                            <li><i class="fas fa-check text-success me-2"></i> Customer Segmentation</li>
                            <li><i class="fas fa-check text-success me-2"></i> Behavioral Analysis</li>
                            <li><i class="fas fa-check text-success me-2"></i> Churn Prediction</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon icon-automation">
                            <i class="fas fa-magic"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Smart Content Generation</h4>
                        <p class="text-muted">Generate konten pemasaran otomatis berdasarkan segmen pelanggan dengan AI yang memahami karakteristik bisnis UMKM Anda.</p>
                        <ul class="list-unstyled mt-3">
                            <li><i class="fas fa-check text-success me-2"></i> Auto Campaign Generation</li>
                            <li><i class="fas fa-check text-success me-2"></i> Personalized Messaging</li>
                            <li><i class="fas fa-check text-success me-2"></i> Multi-channel Content</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon icon-insights">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Business Insights</h4>
                        <p class="text-muted">Dashboard interaktif dengan insights bisnis real-time, laporan komprehensif, dan rekomendasi strategis untuk pertumbuhan UMKM.</p>
                        <ul class="list-unstyled mt-3">
                            <li><i class="fas fa-check text-success me-2"></i> Interactive Dashboard</li>
                            <li><i class="fas fa-check text-success me-2"></i> PDF Export Reports</li>
                            <li><i class="fas fa-check text-success me-2"></i> Growth Recommendations</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-item">
                        <div class="stat-number">53+</div>
                        <h5 class="fw-bold">Sample Customers</h5>
                        <p class="text-muted">Data pelanggan lengkap untuk analisis</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-item">
                        <div class="stat-number">121+</div>
                        <h5 class="fw-bold">Transactions</h5>
                        <p class="text-muted">Transaksi historis untuk insights</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-item">
                        <div class="stat-number">5</div>
                        <h5 class="fw-bold">RFM Segments</h5>
                        <p class="text-muted">Segmentasi pelanggan otomatis</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <h5 class="fw-bold">Analytics</h5>
                        <p class="text-muted">Monitoring bisnis real-time</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Demo Section -->
    <section id="demo" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h2 class="section-title">Coba Demo Platform</h2>
                    <p class="lead text-muted mb-4">
                        Explore semua fitur platform dengan akun demo yang telah disiapkan. Tidak perlu registrasi, langsung login dan rasakan pengalaman analisis RFM yang powerful.
                    </p>
                    
                    <div class="demo-accounts">
                        <h5 class="fw-bold mb-3"><i class="fas fa-key me-2"></i> Demo Accounts</h5>
                        
                        <div class="account-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold text-primary mb-1">Super Admin</h6>
                                    <small class="text-muted">Platform management & analytics</small>
                                </div>
                                <span class="badge bg-danger">Admin</span>
                            </div>
                            <div class="mt-2">
                                <small class="d-block"><strong>Email:</strong> admin@smartmarketing.local</small>
                                <small class="d-block"><strong>Password:</strong> password123</small>
                            </div>
                        </div>
                        
                        <div class="account-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold text-success mb-1">UMKM Owner</h6>
                                    <small class="text-muted">Business analytics & RFM insights</small>
                                </div>
                                <span class="badge bg-primary">Owner</span>
                            </div>
                            <div class="mt-2">
                                <small class="d-block"><strong>Email:</strong> budi@batiksemarang.com</small>
                                <small class="d-block"><strong>Password:</strong> password123</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="login.php" class="btn-gradient me-3">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Login Demo
                        </a>
                        <a href="#about" class="btn-outline-gradient">
                            <i class="fas fa-info-circle me-2"></i>
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-6 text-center" data-aos="fade-left">
                    <div class="position-relative">
                        <div class="bg-light rounded-3 p-4" style="box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="bg-primary text-white rounded p-3">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <h5>Champions</h5>
                                        <p class="mb-0 small">Best customers</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-success text-white rounded p-3">
                                        <i class="fas fa-heart fa-2x mb-2"></i>
                                        <h5>Loyal</h5>
                                        <p class="mb-0 small">Regular buyers</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-warning text-white rounded p-3">
                                        <i class="fas fa-star fa-2x mb-2"></i>
                                        <h5>Potential</h5>
                                        <p class="mb-0 small">Can be loyal</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-danger text-white rounded p-3">
                                        <i class="fas fa-exclamation fa-2x mb-2"></i>
                                        <h5>At Risk</h5>
                                        <p class="mb-0 small">Need attention</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                    <h2 class="section-title">Tentang Smart Marketing Agent</h2>
                    <p class="lead text-muted mb-4">
                        Platform ini dirancang khusus untuk membantu UMKM Indonesia dalam memahami perilaku pelanggan melalui analisis RFM (Recency, Frequency, Monetary). Dengan teknologi modern dan interface yang user-friendly, kami memberikan insights yang actionable untuk pertumbuhan bisnis Anda.
                    </p>
                    
                    <div class="row g-4 mt-4">
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <h5 class="mt-3 fw-bold">Easy to Use</h5>
                                <p class="text-muted small">Interface sederhana yang mudah dipahami</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h5 class="mt-3 fw-bold">Secure & Reliable</h5>
                                <p class="text-muted small">Data aman dengan sistem keamanan berlapis</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <h5 class="mt-3 fw-bold">Actionable Insights</h5>
                                <p class="text-muted small">Rekomendasi bisnis berdasarkan data</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-chart-line me-2"></i>
                        Smart Marketing Agent
                    </h5>
                    <p class="text-light mb-3">
                        Platform analisis pelanggan berbasis RFM untuk UMKM Indonesia. Membantu bisnis memahami pelanggan dan meningkatkan strategi pemasaran dengan data-driven insights.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-light"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-lg-3">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#features" class="text-light text-decoration-none">Features</a></li>
                        <li class="mb-2"><a href="#demo" class="text-light text-decoration-none">Demo</a></li>
                        <li class="mb-2"><a href="#about" class="text-light text-decoration-none">About</a></li>
                        <li class="mb-2"><a href="login.php" class="text-light text-decoration-none">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h6 class="fw-bold mb-3">Contact Info</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2"></i>
                            info@smartmarketing.local
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-phone me-2"></i>
                            +62 123 456 7890
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            Indonesia
                        </li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2025 Smart Marketing Agent. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <small class="text-light">
                        <i class="fas fa-heart text-danger me-1"></i>
                        Made for Indonesian UMKM
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offsetTop = target.offsetTop - 80; // Account for fixed navbar
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(44, 62, 80, 0.95)';
            } else {
                navbar.style.background = 'rgba(44, 62, 80, 0.95)';
            }
        });

        // Copy demo credentials on click
        document.querySelectorAll('.account-item').forEach(item => {
            item.addEventListener('click', function() {
                const email = this.querySelector('small:nth-child(1)').textContent.split(': ')[1];
                const password = this.querySelector('small:nth-child(2)').textContent.split(': ')[1];
                
                // Copy to clipboard
                navigator.clipboard.writeText(`Email: ${email}\nPassword: ${password}`).then(() => {
                    // Show toast notification
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed top-0 end-0 m-3 alert alert-success alert-dismissible fade show';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <i class="fas fa-check me-2"></i>
                        Demo credentials copied to clipboard!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                });
            });
        });
    </script>
</body>
</html>
