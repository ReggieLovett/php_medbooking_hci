<?php
session_start();
include 'config.php';

$message = "";
$message_type = "";

if (isset($_SESSION['user_id'])) {
    header('Location: booking.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($conn->real_escape_string($_POST['name'] ?? ''));
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $phone = trim($conn->real_escape_string($_POST['phone'] ?? ''));

    if (empty($name) || empty($email)) {
        $message = 'Please enter both name and email.';
        $message_type = 'error';
    } else {
        $email = strtolower($email);
        $check = $conn->query("SELECT id FROM users WHERE email = '$email' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $message = 'An account with this email already exists. Please sign in.';
            $message_type = 'error';
        } else {
            $insertUser = "INSERT INTO users (name, email, phone) VALUES ('$name', '$email', '$phone')";
            if ($conn->query($insertUser) === TRUE) {
                header('Location: login.php?registered=1&email=' . urlencode($email));
                exit;
            } else {
                $message = 'Unable to create user account: ' . $conn->error;
                $message_type = 'error';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Appointment System</title>
    <link rel="stylesheet" href="style.css">
    <script>
        const applyTheme = (theme) => {
            document.body.classList.remove('light', 'dark');
            document.body.classList.add(theme);
            localStorage.setItem('siteTheme', theme);
            const toggle = document.getElementById('themeToggle');
            if (toggle) {
                toggle.textContent = theme === 'dark' ? '☀️ Light' : '🌙 Dark';
                toggle.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            }
        };
        window.addEventListener('DOMContentLoaded', () => {
            const stored = localStorage.getItem('siteTheme') || 'dark';
            applyTheme(stored);
            const toggle = document.getElementById('themeToggle');
            if (toggle) {
                toggle.addEventListener('click', () => applyTheme(document.body.classList.contains('dark') ? 'light' : 'dark'));
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <header class="site-header">
            <a href="index.php" class="logo"><span class="logo-mark">HM</span>HealthCare</a>
            <nav class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="booking.php" class="nav-link">Book</a>
                <a href="view_appointments.php" class="nav-link">Appointments</a>
                <a href="admin_login.php" class="nav-link">Admin</a>
                <button type="button" id="themeToggle" class="btn btn-secondary">🌙 Dark</button>
            </nav>
        </header>

        <section class="hero">
            <div class="hero-copy">
                <h1>Create your patient account</h1>
                <p class="form-note">Register to book appointments and manage your visits. If you already have an account, please <a href="login.php">sign in</a>.</p>
            </div>
            <div class="hero-panel">
                <h3>Register</h3>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form class="form-card" method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Register</button>
                </form>
            </div>
        </section>

        <footer class="site-footer">
            <p>© <?php echo date('Y'); ?> HealthCare Appointment System.</p>
        </footer>
    </div>
</body>
</html>
