<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NestSync – Smart Hall & Roommate Matching Management System for universities. Find your perfect hall seat and ideal roommate.">
    <title>NestSync – Smart Hall & Roommate Matching System</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏠</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body class="landing-body">

<!-- ===== NAVBAR ===== -->
<nav class="landing-navbar">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <a href="index.php" class="landing-nav-brand text-decoration-none">
                🏠 Nest<span style="color:#4361ee">Sync</span>
            </a>
            <div class="d-none d-md-flex align-items-center gap-4">
                <a href="#features"  class="landing-nav-link">Features</a>
                <a href="#howitworks" class="landing-nav-link">How It Works</a>
                <a href="#stats"     class="landing-nav-link">Stats</a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="login.php" class="btn btn-sm btn-outline-light rounded-pill px-3">Login</a>
                <a href="pages/auth/register.php" class="btn btn-sm btn-primary rounded-pill px-3">Register Free</a>
            </div>
        </div>
    </div>
</nav>

<!-- ===== HERO ===== -->
<section class="landing-hero">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 hero-content fade-in-up">
                <div class="hero-badge">
                    <i class="fas fa-graduation-cap"></i>
                    University Hall Management System
                </div>
                <h1 class="hero-title">
                    Find Your Perfect<br>
                    <span class="highlight">Hall & Roommate</span>
                </h1>
                <p class="hero-subtitle">
                    NestSync intelligently matches you with compatible roommates and streamlines university hall bookings — all in one modern platform.
                </p>
                <div class="hero-cta">
                    <a href="pages/auth/register.php" class="btn-hero-primary">
                        <i class="fas fa-rocket"></i> Get Started Free
                    </a>
                    <a href="login.php" class="btn-hero-outline">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-card-float">
                    <div class="row g-0 text-center">
                        <div class="col-6 border-end border-bottom" style="border-color:rgba(255,255,255,0.1) !important">
                            <div class="hero-stat-item">
                                <div class="hero-stat-num">3</div>
                                <div class="hero-stat-lbl">Residential Halls</div>
                            </div>
                        </div>
                        <div class="col-6 border-bottom" style="border-color:rgba(255,255,255,0.1) !important">
                            <div class="hero-stat-item">
                                <div class="hero-stat-num">530</div>
                                <div class="hero-stat-lbl">Total Capacity</div>
                            </div>
                        </div>
                        <div class="col-6 border-end" style="border-color:rgba(255,255,255,0.1) !important">
                            <div class="hero-stat-item">
                                <div class="hero-stat-num">98%</div>
                                <div class="hero-stat-lbl">Match Accuracy</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-stat-item">
                                <div class="hero-stat-num">10+</div>
                                <div class="hero-stat-lbl">Active Students</div>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color:rgba(255,255,255,0.1); margin: 16px 0;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#4361ee,#7209b7);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:14px">R</div>
                        <div>
                            <div style="color:#fff;font-size:13px;font-weight:600">Rahim Uddin just booked a seat</div>
                            <div style="color:rgba(255,255,255,0.55);font-size:11px">North Hall · Room A-101 · 2 min ago</div>
                        </div>
                        <span class="badge bg-success ms-auto">Approved</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FEATURES ===== -->
<section class="landing-features" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-label">Everything You Need</span>
            <h2 class="section-title">A Complete Hall Management Platform</h2>
            <p class="section-subtitle">From booking to roommate matching — NestSync handles it all with a beautiful, easy-to-use interface.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-blue"><i class="fas fa-building"></i></div>
                    <h5 class="feature-title">Hall Management</h5>
                    <p class="feature-desc">Manage multiple residential halls, rooms and seats with full CRUD operations. Track occupancy in real time.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-green"><i class="fas fa-calendar-check"></i></div>
                    <h5 class="feature-title">Seat Booking</h5>
                    <p class="feature-desc">Students can browse available seats, submit booking requests, and track their approval status instantly.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-purple"><i class="fas fa-user-friends"></i></div>
                    <h5 class="feature-title">Roommate Matching</h5>
                    <p class="feature-desc">Our algorithm scores compatibility based on department, budget and lifestyle preferences for the best pairings.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-orange"><i class="fas fa-shield-alt"></i></div>
                    <h5 class="feature-title">Role-Based Access</h5>
                    <p class="feature-desc">Three distinct roles — System Admin, Hall Admin, and Student — each with tailored dashboards and permissions.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-cyan"><i class="fas fa-chart-bar"></i></div>
                    <h5 class="feature-title">Reports & Analytics</h5>
                    <p class="feature-desc">Comprehensive reports on hall occupancy, booking trends, student statistics and revenue summaries.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card">
                    <div class="feature-icon fi-pink"><i class="fas fa-bell"></i></div>
                    <h5 class="feature-title">Instant Notifications</h5>
                    <p class="feature-desc">Students receive real-time in-app notifications when bookings are approved, rejected or updated.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section class="py-5 bg-white" id="howitworks">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="section-label">Simple Process</span>
            <h2 class="section-title">Get Settled in 3 Easy Steps</h2>
        </div>
        <div class="row g-5 align-items-center">
            <div class="col-md-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center mb-4"
                     style="width:72px;height:72px;border-radius:50%;background:var(--primary-light);font-size:28px;font-weight:800;color:var(--primary)">1</div>
                <h5 class="fw-700 mb-2" style="font-weight:700">Register Your Account</h5>
                <p class="text-muted">Sign up as a student, fill in your department, budget range and lifestyle preferences.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center mb-4"
                     style="width:72px;height:72px;border-radius:50%;background:#f5f3ff;font-size:28px;font-weight:800;color:#7c3aed">2</div>
                <h5 style="font-weight:700" class="mb-2">Browse & Book a Seat</h5>
                <p class="text-muted">Explore available halls and rooms, then submit a booking request for your preferred seat.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center mb-4"
                     style="width:72px;height:72px;border-radius:50%;background:#ecfdf5;font-size:28px;font-weight:800;color:var(--success)">3</div>
                <h5 style="font-weight:700" class="mb-2">Meet Your Roommate</h5>
                <p class="text-muted">NestSync instantly generates your top roommate matches so you move in with someone compatible.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== STATS BAR ===== -->
<section class="landing-stats" id="stats">
    <div class="container">
        <div class="row g-4">
            <div class="col-6 col-md-3">
                <div class="landing-stat-item">
                    <div class="landing-stat-num">530+</div>
                    <div class="landing-stat-lbl">Hall Seats Available</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="landing-stat-item">
                    <div class="landing-stat-num">10+</div>
                    <div class="landing-stat-lbl">Active Students</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="landing-stat-item">
                    <div class="landing-stat-num">3</div>
                    <div class="landing-stat-lbl">Residential Halls</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="landing-stat-item">
                    <div class="landing-stat-num">100%</div>
                    <div class="landing-stat-lbl">Secure Platform</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CTA ===== -->
<section class="landing-cta">
    <div class="container">
        <h2 class="fw-800 mb-3">Ready to Find Your Hall?</h2>
        <p class="mb-5">Join NestSync today and let us match you with the perfect hall seat and roommate.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="pages/auth/register.php" class="btn-hero-primary">
                <i class="fas fa-user-plus"></i> Register as Student
            </a>
            <a href="login.php" class="btn-hero-outline">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="landing-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="footer-brand">🏠 NestSync</div>
                <p class="footer-desc">Smart Hall & Roommate Matching Management System for modern universities.</p>
            </div>
            <div class="col-md-2">
                <div class="fw-600 text-white mb-3" style="font-size:14px">Platform</div>
                <a href="login.php" class="footer-link">Login</a>
                <a href="pages/auth/register.php" class="footer-link">Register</a>
                <a href="seed.php" class="footer-link">Setup DB</a>
            </div>
            <div class="col-md-2">
                <div class="fw-600 text-white mb-3" style="font-size:14px">Features</div>
                <a href="#features" class="footer-link">Hall Management</a>
                <a href="#features" class="footer-link">Seat Booking</a>
                <a href="#features" class="footer-link">Room Matching</a>
            </div>
            <div class="col-md-4">
                <div class="fw-600 text-white mb-3" style="font-size:14px">Default Logins</div>
                <div style="background:rgba(255,255,255,0.05);border-radius:10px;padding:14px;font-size:12.5px">
                    <div class="mb-1"><span style="color:#93c5fd">Admin:</span> admin@nestsync.edu / Admin@123</div>
                    <div class="mb-1"><span style="color:#86efac">Hall Admin:</span> halladmin1@nestsync.edu / Admin@123</div>
                    <div><span style="color:#fcd34d">Student:</span> rahim@nestsync.edu / Student@123</div>
                </div>
            </div>
        </div>
        <hr>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <p class="footer-copy mb-0">© 2026 NestSync. Built as a University Database Management System project.</p>
            <p class="footer-copy mb-0">PHP 8 · MySQL · Bootstrap 5</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="public/js/main.js"></script>
</body>
</html>