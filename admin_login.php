<?php
session_start();
include 'config.php';

ensure_default_admin($conn);

$message = '';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($conn->real_escape_string($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = 'Please enter both username and password.';
    } else {
        $sql = "SELECT id, password_hash, full_name FROM admins WHERE username = '$username' LIMIT 1";
        $result = $conn->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_name'] = $row['full_name'];
                header('Location: admin.php');
                exit;
            }
            $message = 'Invalid admin credentials.';
        } else {
            $message = 'Invalid admin credentials.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
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
                <a href="admin_login.php" class="nav-link active">Admin</a>
                <button type="button" id="themeToggle" class="btn btn-secondary">🌙 Dark</button>
            </nav>
        </header>
        <section class="section">
            <div class="hero-copy">
                <span>Administrator Access</span>
                <h1>Sign in to manage appointments.</h1>
                <p>Use your credentials to access the admin dashboard for scheduling, status updates, and doctor availability control.</p>
            </div>
            <div class="hero-panel">
                <?php if (!empty($message)): ?>
                    <div class="message error"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form method="POST" action="" class="form-card">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Log In</button>
                </form>
                <p class="form-note">Default admin login: <strong>rlovett</strong> / <strong>docreg07</strong>.</p>
                <div class="hero-actions">
                    <a href="index.php" class="btn btn-secondary">← Back to Home</a>
                </div>
            </div>
        </section>
    </div>
</body>
</html>
