<?php
// Database Configuration
$servername = "localhost";
$username = "root";
$password = ""; // Change this to your MySQL password if you have one
$dbname = "appointment_booking";

// Create connection using MySQLi
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

/**
 * Ensure baseline doctors exist so booking/admin pages always have options.
 * Existing doctor rows are preserved; only missing defaults are inserted.
 * If no doctors are active, default doctors are re-activated.
 */
function ensure_default_doctors(mysqli $conn): void {
    $defaults = [
        ['Rhys Suyu', 'Neurologist', '10:00:00', '16:00:00', 'Mon,Tue,Thu,Fri'],
        ['Reggie Lovett', 'Cardiologist', '09:00:00', '13:00:00', 'Mon,Wed,Fri'],
        ['Vince Avena', 'Pediatrician', '09:00:00', '17:00:00', 'Mon,Tue,Wed,Thu,Fri'],
        ['Yohann Tomas', 'Dermatologist', '11:00:00', '17:00:00', 'Tue,Wed,Thu,Sat'],
        ['Xerg Magpantay', 'Gastroenterologist', '08:30:00', '14:30:00', 'Mon,Tue,Thu,Fri']
    ];

    foreach ($defaults as $doctor) {
        [$name, $specialty, $from, $to, $days] = $doctor;
        $nameEsc = $conn->real_escape_string($name);
        $specialtyEsc = $conn->real_escape_string($specialty);
        $fromEsc = $conn->real_escape_string($from);
        $toEsc = $conn->real_escape_string($to);
        $daysEsc = $conn->real_escape_string($days);

        $exists = $conn->query("SELECT id FROM doctors WHERE name = '$nameEsc' LIMIT 1");
        if ($exists && $exists->num_rows === 0) {
            $conn->query("INSERT INTO doctors (name, specialty, available_from, available_to, work_days, is_active) VALUES ('$nameEsc', '$specialtyEsc', '$fromEsc', '$toEsc', '$daysEsc', 1)");
        }
    }

    $activeCountResult = $conn->query("SELECT COUNT(*) AS count FROM doctors WHERE is_active = 1");
    $activeCountRow = $activeCountResult ? $activeCountResult->fetch_assoc() : ['count' => 0];
    $activeCount = intval($activeCountRow['count'] ?? 0);

    if ($activeCount === 0) {
        foreach ($defaults as $doctor) {
            $nameEsc = $conn->real_escape_string($doctor[0]);
            $conn->query("UPDATE doctors SET is_active = 1 WHERE name = '$nameEsc'");
        }
    }
}

/**
 * Ensure a default admin account exists with known credentials.
 */
function ensure_default_admin(mysqli $conn): void {
    $username = 'rlovett';
    $plainPassword = 'docreg07';
    $fullName = 'Dr. Reggie Lovett';

    $usernameEsc = $conn->real_escape_string($username);
    $fullNameEsc = $conn->real_escape_string($fullName);
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $check = $conn->query("SELECT id FROM admins WHERE username = '$usernameEsc' LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $conn->query("UPDATE admins SET password_hash = '$passwordHash', full_name = '$fullNameEsc' WHERE username = '$usernameEsc'");
    } else {
        $conn->query("INSERT INTO admins (username, password_hash, full_name) VALUES ('$usernameEsc', '$passwordHash', '$fullNameEsc')");
    }
}

?>
