<?php
// Database Initialization Script
// Run this once to create the database and tables

$servername = "localhost";
$username = "root";
$password = ""; // Change this to your MySQL password if you have one

// Create connection without selecting a database
$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS appointment_booking";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db("appointment_booking");

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully or already exists<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create doctors table
$sql = "CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    available_from TIME NOT NULL,
    available_to TIME NOT NULL,
    work_days VARCHAR(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri',
    is_active BOOLEAN DEFAULT TRUE
)";

if ($conn->query($sql) === TRUE) {
    echo "Doctors table created successfully or already exists<br>";
} else {
    echo "Error creating doctors table: " . $conn->error . "<br>";
}

// Create appointments table
$sql = "CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    service_type VARCHAR(100) NOT NULL,
    duration INT DEFAULT 30,
    notes TEXT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_appointment (appointment_date, appointment_time, doctor_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Appointments table created successfully or already exists<br>";
} else {
    echo "Error creating appointments table: " . $conn->error . "<br>";
}

// Create admin users table
$sql = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Admin users table created successfully or already exists<br>";
} else {
    echo "Error creating admins table: " . $conn->error . "<br>";
}

// Ensure default doctors exist with updated specialties and schedules
$doctors = [
    ['Rhys Suyu', 'Neurologist', '10:00:00', '16:00:00', 'Mon,Tue,Thu,Fri'],
    ['Reggie Lovett', 'Cardiologist', '09:00:00', '13:00:00', 'Mon,Wed,Fri'],
    ['Vince Avena', 'Pediatrician', '09:00:00', '17:00:00', 'Mon,Tue,Wed,Thu,Fri'],
    ['Yohann Tomas', 'Dermatologist', '11:00:00', '17:00:00', 'Tue,Wed,Thu,Sat'],
    ['Xerg Magpantay', 'Gastroenterologist', '08:30:00', '14:30:00', 'Mon,Tue,Thu,Fri']
];

foreach ($doctors as $doctor) {
    $name = $doctor[0];
    $specialty = $doctor[1];
    $available_from = $doctor[2];
    $available_to = $doctor[3];
    $work_days = $doctor[4];

    $checkDoctor = "SELECT id FROM doctors WHERE name = '$name' LIMIT 1";
    $doctorResult = $conn->query($checkDoctor);

    if ($doctorResult && $doctorResult->num_rows > 0) {
        $sql = "UPDATE doctors SET specialty = '$specialty', available_from = '$available_from', available_to = '$available_to', work_days = '$work_days', is_active = 1 WHERE name = '$name'";
        $conn->query($sql);
    } else {
        $sql = "INSERT INTO doctors (name, specialty, available_from, available_to, work_days, is_active) VALUES ('$name', '$specialty', '$available_from', '$available_to', '$work_days', 1)";
        $conn->query($sql);
    }
}
echo "Default doctors synced successfully<br>";

// Insert or update default admin user with known credentials
$defaultUsername = 'rlovett';
$defaultPassword = 'docreg07';
$passwordHash = password_hash($defaultPassword, PASSWORD_DEFAULT);

$checkAdminUser = "SELECT id FROM admins WHERE username = '$defaultUsername' LIMIT 1";
$result = $conn->query($checkAdminUser);

if ($result && $result->num_rows > 0) {
    $sql = "UPDATE admins SET password_hash = '$passwordHash', full_name = 'Dr. Reggie Lovett' WHERE username = '$defaultUsername'";
    if ($conn->query($sql) === TRUE) {
        echo "Default admin user updated: username=rlovett password=docreg07<br>";
    } else {
        echo "Error updating default admin user: " . $conn->error . "<br>";
    }
} else {
    $sql = "INSERT INTO admins (username, password_hash, full_name) VALUES ('$defaultUsername', '$passwordHash', 'Dr. Reggie Lovett')";
    if ($conn->query($sql) === TRUE) {
        echo "Default admin user added: username=rlovett password=docreg07<br>";
    } else {
        echo "Error creating default admin user: " . $conn->error . "<br>";
    }
}

// Create available time slots table
$sql = "CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE
)";

if ($conn->query($sql) === TRUE) {
    echo "Time slots table created successfully or already exists<br>";
} else {
    echo "Error creating time slots table: " . $conn->error . "<br>";
}

// Insert default time slots if they don't exist
$checkSlots = "SELECT COUNT(*) as count FROM time_slots";
$result = $conn->query($checkSlots);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $times = ['09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '11:30:00', 
              '13:00:00', '13:30:00', '14:00:00', '14:30:00', '15:00:00', '15:30:00', 
              '16:00:00', '16:30:00', '17:00:00'];
    
    foreach ($times as $time) {
        $sql = "INSERT INTO time_slots (slot_time) VALUES ('$time')";
        $conn->query($sql);
    }
    echo "Default time slots added successfully<br>";
}

$conn->close();
echo "<br>Setup completed! You can now delete this file and navigate to index.php to log in or booking.php to book an appointment.<br>";
?>
