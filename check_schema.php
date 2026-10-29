<?php
$conn = new mysqli("localhost", "root", "", "appointment_booking");
if ($conn->connect_error) {
    echo "CONNECT_ERR:" . $conn->connect_error;
    exit;
}
$queries = [
    "ALTER TABLE appointments ADD COLUMN doctor_id INT(11) NULL AFTER user_id",
    "ALTER TABLE appointments DROP INDEX unique_appointment",
    "ALTER TABLE appointments ADD UNIQUE KEY unique_appointment (appointment_date, appointment_time, doctor_id)",
    "ALTER TABLE appointments ADD KEY idx_doctor (doctor_id)",
    "ALTER TABLE appointments ADD CONSTRAINT appointments_ibfk_2 FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT"
];
foreach ($queries as $q) {
    if (!$conn->query($q)) {
        echo "ERR: " . $conn->error . "\n";
        exit;
    }
    echo "OK: " . $q . "\n";
}
?>