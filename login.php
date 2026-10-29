<?php
session_start();
include 'config.php';

$message = "";
$message_type = "";

if (isset($_SESSION['user_id'])) {
    header('Location: booking.php');
    exit;
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $prefillEmail = trim($_GET['email'] ?? '');
    if ($prefillEmail !== '') {
        $_POST['email'] = $prefillEmail;
    }
    $message = 'Registration successful. You can now sign in.';
    $message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_login'])) {
    $name = trim($conn->real_escape_string($_POST['name'] ?? ''));
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $phone = trim($conn->real_escape_string($_POST['phone'] ?? ''));

    if (empty($email)) {
        $message = 'Please enter your email to sign in.';
        $message_type = 'error';
    } else {
        $email = strtolower($email);
        $userQuery = "SELECT id, name, email, phone FROM users WHERE email = '$email' LIMIT 1";
        $userResult = $conn->query($userQuery);

        if ($userResult && $userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            // Update name/phone if provided and changed
            if (!empty($name) && ($user['name'] !== $name || $user['phone'] !== $phone)) {
                $updateUser = "UPDATE users SET name = '$name', phone = '$phone' WHERE id = " . intval($user['id']);
                $conn->query($updateUser);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_phone'] = $user['phone'];
            header('Location: booking.php');
            exit;
        } else {
            $message = 'No account found with that email. Please register first.';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Appointment System</title>
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
                <h1>Welcome back</h1>
                <p class="form-note">Sign in with your email to continue to bookings. Don't have an account? <a href="register.php">Register here</a>.</p>
            </div>

            <div class="hero-panel">
                <h3>Patient Sign In</h3>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form class="form-card" method="POST" action="">
                    <input type="hidden" name="user_login" value="1">
                    <div class="form-group">
                        <label for="name">Full Name (optional)</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number (optional)</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>
            </div>
        </section>

        <footer class="site-footer">
            <p>© <?php echo date('Y'); ?> HealthCare Appointment System.</p>
        </footer>
    </div>
</body>
</html>
