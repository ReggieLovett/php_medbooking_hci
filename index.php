<?php
session_start();
include 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: booking.php');
    exit;
}

$countRes = $conn->query("SELECT COUNT(*) as count FROM users");
$countRow = $countRes ? $countRes->fetch_assoc() : null;
$userCount = intval($countRow['count'] ?? 0);
$firstRun = ($userCount === 0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HealthCare Wellness & Medical Center</title>
    <link rel="stylesheet" href="style.css">
    <script>
        const applyTheme = (theme) => {
            document.body.classList.remove('light', 'dark');
            document.body.classList.add(theme);
            localStorage.setItem('siteTheme', theme);
            const toggle = document.getElementById('themeToggle');
            if (toggle) {
                toggle.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
                toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            }
        };

        window.addEventListener('DOMContentLoaded', () => {
            const stored = localStorage.getItem('siteTheme') || 'light';
            applyTheme(stored);
            const toggle = document.getElementById('themeToggle');
            if (toggle) {
                toggle.addEventListener('click', () => applyTheme(document.body.classList.contains('dark') ? 'light' : 'dark'));
            }
        });
    </script>
</head>
<body class="light">
    <div class="container hospital-page">
        <header class="site-header hospital-header">
            <a href="index.php" class="logo"><span class="logo-mark">HC</span>HealthCare</a>
            <nav class="nav-links">
                <a href="#home" class="nav-link active">Home</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="#news" class="nav-link">News</a>
                <a href="admin_login.php" class="nav-link">Admin</a>
                <button type="button" id="themeToggle" class="btn btn-secondary">Dark Mode</button>
            </nav>
        </header>

        <section id="home" class="hospital-hero">
            <img src="assets/hero-patient-scene.svg" alt="Doctor caring for patient" class="hospital-hero-image">
            <div class="hospital-hero-overlay">
                <h1>We Serve, Because We Care</h1>
                <p>Delivering high-quality, advanced and compassionate healthcare services to all.</p>
                <div class="hero-actions">
                    <a href="register.php" class="btn btn-primary"><?php echo $firstRun ? 'Register First Patient' : 'Register'; ?></a>
                    <a href="login.php" class="btn btn-secondary">User Login</a>
                    <a href="admin_login.php" class="btn btn-secondary">Admin Login</a>
                </div>
            </div>
        </section>

        <section class="hospital-quick-cards">
            <article class="hospital-card">
                <h3>Emergency Service</h3>
                <p>24/7 emergency support for urgent and critical conditions.</p>
            </article>
            <article class="hospital-card hospital-card-accent">
                <h3>Find A Doctor</h3>
                <p>Choose from specialties and schedule appointments online.</p>
            </article>
            <article class="hospital-card">
                <h3>Medical Services</h3>
                <p>Reliable healthcare tailored for comfort, wellness and recovery.</p>
            </article>
        </section>

        <section id="about" class="hospital-about">
            <img src="assets/hospital-building.svg" alt="Hospital building" class="about-image">
            <div>
                <span class="section-pill">Who We Are</span>
                <h2>HealthCare Wellness &amp; Medical Center</h2>
                <p>Welcome to HealthCare. We provide patient-first healthcare services with trusted doctors, modern facilities and efficient appointment scheduling.</p>
                <ul class="hospital-checklist">
                    <li>Primary and specialist consultations</li>
                    <li>Laboratory and diagnostics support</li>
                    <li>Safe and modern care facilities</li>
                </ul>
                <div class="hero-actions">
                    <a href="booking.php" class="btn btn-primary">Book Appointment</a>
                    <a href="view_appointments.php" class="btn btn-secondary">View Appointments</a>
                </div>
            </div>
        </section>

        <section class="section" id="services">
            <div class="section-title">
                <h2>Medical Services &amp; Treatments</h2>
                <p>Comprehensive healthcare services designed to support prevention, diagnosis and treatment.</p>
            </div>
            <div class="services-grid">
                <article class="service-card"><h3>Emergency Services</h3><p>Immediate response for urgent and life-threatening conditions.</p></article>
                <article class="service-card"><h3>Cardiology Unit</h3><p>Heart screening, monitoring and cardiovascular care.</p></article>
                <article class="service-card"><h3>Laboratory Services</h3><p>Reliable testing and diagnostics with accurate reporting.</p></article>
                <article class="service-card"><h3>ENT Care</h3><p>Comprehensive ear, nose and throat consultations.</p></article>
                <article class="service-card"><h3>Pulmonology</h3><p>Respiratory health management and specialist treatment.</p></article>
                <article class="service-card"><h3>Neurology</h3><p>Expert care for neurological symptoms and conditions.</p></article>
            </div>
        </section>

        <section id="news" class="section hospital-news">
            <div class="section-title">
                <h2>Latest News &amp; Events</h2>
                <p>Stay informed with upcoming medical missions, announcements and patient advisories.</p>
            </div>
            <div class="card-grid">
                <article class="section-card">
                    <h3>New Outpatient Schedule</h3>
                    <p>Weekday consultation slots are now open from 8:00 AM to 6:00 PM.</p>
                </article>
                <article class="section-card">
                    <h3>Expanded Diagnostics Wing</h3>
                    <p>Our imaging and laboratory facilities have been upgraded for faster service.</p>
                </article>
                <article class="section-card">
                    <h3>Community Health Day</h3>
                    <p>Free blood pressure and glucose screening for walk-in patients this month.</p>
                </article>
            </div>
        </section>

        <footer class="site-footer">
            <p>Copyright <?php echo date('Y'); ?> HealthCare Wellness &amp; Medical Center.</p>
        </footer>
    </div>
</body>
</html>
