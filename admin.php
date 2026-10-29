<?php
session_start();
include 'config.php';

ensure_default_doctors($conn);

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$message = "";
$message_type = "";
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

function sanitize($conn, $value) {
    return $conn->real_escape_string(trim($value));
}

// Handle admin actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Server-side handler for AJAX or form-based doctor removal
    if (isset($_POST['remove_doctor'])) {
        $doctor_id = intval($_POST['doctor_id']);
        if ($doctor_id > 0) {
            $removeQuery = "UPDATE doctors SET is_active = 0 WHERE id = $doctor_id";
            if ($conn->query($removeQuery) === TRUE) {
                $message = 'Doctor removed (set inactive) successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error removing doctor: ' . $conn->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid doctor id.';
            $message_type = 'error';
        }
    }
    // Toggle doctor active/inactive
    if (isset($_POST['toggle_doctor'])) {
        $doctor_id = intval($_POST['doctor_id']);
        $new_state = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
        if ($doctor_id > 0) {
            $toggleQuery = "UPDATE doctors SET is_active = $new_state WHERE id = $doctor_id";
            if ($conn->query($toggleQuery) === TRUE) {
                $message = 'Doctor status updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error updating doctor status: ' . $conn->error;
                $message_type = 'error';
            }
        }
    }
    if (isset($_POST['update_status'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $new_status = sanitize($conn, $_POST['status']);
        
        $updateQuery = "UPDATE appointments SET status = '$new_status' WHERE id = $appointment_id";
        if ($conn->query($updateQuery) === TRUE) {
            $message = "Appointment status updated successfully.";
            $message_type = "success";
        } else {
            $message = "Error updating appointment: " . $conn->error;
            $message_type = "error";
        }
    } elseif (isset($_POST['delete_appointment'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $deleteQuery = "DELETE FROM appointments WHERE id = $appointment_id";
        
        if ($conn->query($deleteQuery) === TRUE) {
            $message = "Appointment deleted successfully.";
            $message_type = "success";
        } else {
            $message = "Error deleting appointment: " . $conn->error;
            $message_type = "error";
        }
    } elseif (isset($_POST['save_doctor'])) {
        $doctor_id = intval($_POST['doctor_id']);
        $doctor_name = sanitize($conn, $_POST['doctor_name']);
        $doctor_specialty = sanitize($conn, $_POST['doctor_specialty']);
        $available_from = sanitize($conn, $_POST['available_from']);
        $available_to = sanitize($conn, $_POST['available_to']);
        $work_days = sanitize($conn, $_POST['work_days']);
        $is_active = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;

        if ($doctor_name === '' || $doctor_specialty === '' || $available_from === '' || $available_to === '' || $work_days === '') {
            $message = 'Please complete all doctor fields before saving.';
            $message_type = 'error';
        } else {
            $updateDoctor = "UPDATE doctors SET name = '$doctor_name', specialty = '$doctor_specialty', available_from = '$available_from', available_to = '$available_to', work_days = '$work_days', is_active = $is_active WHERE id = $doctor_id";
            if ($conn->query($updateDoctor) === TRUE) {
                $message = 'Doctor availability updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error updating doctor: ' . $conn->error;
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['create_doctor'])) {
        $new_doctor_name = sanitize($conn, $_POST['new_doctor_name']);
        $new_doctor_specialty = sanitize($conn, $_POST['new_doctor_specialty']);
        $new_available_from = sanitize($conn, $_POST['new_available_from']);
        $new_available_to = sanitize($conn, $_POST['new_available_to']);
        $new_work_days = sanitize($conn, $_POST['new_work_days']);

        if ($new_doctor_name === '' || $new_doctor_specialty === '' || $new_available_from === '' || $new_available_to === '' || $new_work_days === '') {
            $message = 'Please complete all fields to add a new doctor.';
            $message_type = 'error';
        } else {
            $insertDoctor = "INSERT INTO doctors (name, specialty, available_from, available_to, work_days, is_active) VALUES ('$new_doctor_name', '$new_doctor_specialty', '$new_available_from', '$new_available_to', '$new_work_days', 1)";
            if ($conn->query($insertDoctor) === TRUE) {
                $message = 'New doctor added successfully.';
                $message_type = 'success';
            } else {
                $message = 'Error adding doctor: ' . $conn->error;
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['schedule_appointment'])) {
        $patient_name = sanitize($conn, $_POST['patient_name']);
        $patient_email = sanitize($conn, $_POST['patient_email']);
        $patient_phone = sanitize($conn, $_POST['patient_phone']);
        $doctor_id = intval($_POST['doctor_id']);
        $appointment_date = sanitize($conn, $_POST['appointment_date']);
        $appointment_time = sanitize($conn, $_POST['appointment_time']);
        $service_type = sanitize($conn, $_POST['service_type']);
        $duration = intval($_POST['duration']) > 0 ? intval($_POST['duration']) : 30;
        $notes = sanitize($conn, $_POST['notes']);

        if ($patient_name === '' || $patient_email === '' || $doctor_id <= 0 || $appointment_date === '' || $appointment_time === '' || $service_type === '') {
            $message = 'Please complete all required fields to schedule the appointment.';
            $message_type = 'error';
        } else {
            $doctorQuery = "SELECT * FROM doctors WHERE id = $doctor_id AND is_active = 1 LIMIT 1";
            $doctorResult = $conn->query($doctorQuery);
            if (!$doctorResult || $doctorResult->num_rows === 0) {
                $message = 'Please select a valid active doctor.';
                $message_type = 'error';
            } else {
                $doctor = $doctorResult->fetch_assoc();
                $dayName = date('D', strtotime($appointment_date));
                $availableDays = array_map('trim', explode(',', $doctor['work_days']));
                if (!in_array($dayName, $availableDays)) {
                    $message = "{$doctor['name']} is not available on " . date('l', strtotime($appointment_date)) . ".";
                    $message_type = 'error';
                } elseif ($appointment_time < $doctor['available_from'] || $appointment_time > $doctor['available_to']) {
                    $message = "{$doctor['name']} is only available between " . substr($doctor['available_from'], 0, 5) . " and " . substr($doctor['available_to'], 0, 5) . ".";
                    $message_type = 'error';
                } else {
                    $userQuery = "SELECT id FROM users WHERE email = '$patient_email' LIMIT 1";
                    $userResult = $conn->query($userQuery);
                    if ($userResult && $userResult->num_rows > 0) {
                        $user = $userResult->fetch_assoc();
                        $user_id = intval($user['id']);
                        $updateUser = "UPDATE users SET name = '$patient_name', phone = '$patient_phone' WHERE id = $user_id";
                        $conn->query($updateUser);
                    } else {
                        $insertUser = "INSERT INTO users (name, email, phone) VALUES ('$patient_name', '$patient_email', '$patient_phone')";
                        if ($conn->query($insertUser) === TRUE) {
                            $user_id = intval($conn->insert_id);
                        } else {
                            $message = 'Error creating patient record: ' . $conn->error;
                            $message_type = 'error';
                        }
                    }

                    if (empty($message)) {
                        $slotCheck = "SELECT id FROM appointments WHERE appointment_date = '$appointment_date' AND appointment_time = '$appointment_time' AND doctor_id = $doctor_id AND status != 'cancelled'";
                        $slotResult = $conn->query($slotCheck);
                        if ($slotResult && $slotResult->num_rows > 0) {
                            $message = "That time slot is already booked for {$doctor['name']}.";
                            $message_type = 'error';
                        } else {
                            $insertAppointment = "INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, service_type, duration, notes) VALUES ($user_id, $doctor_id, '$appointment_date', '$appointment_time', '$service_type', $duration, '$notes')";
                            if ($conn->query($insertAppointment) === TRUE) {
                                $message = 'Appointment scheduled successfully.';
                                $message_type = 'success';
                            } else {
                                $message = 'Error scheduling appointment: ' . $conn->error;
                                $message_type = 'error';
                            }
                        }
                    }
                }
            }
        }
    }
}

// Get doctor and appointment data
$query = "SELECT a.id, a.appointment_date, a.appointment_time, a.service_type, a.duration, a.status, a.created_at,
                 u.id as user_id, u.name, u.email, u.phone,
                 d.name as doctor_name, d.specialty as doctor_specialty
          FROM appointments a
          JOIN users u ON a.user_id = u.id
          LEFT JOIN doctors d ON a.doctor_id = d.id";

if ($filter_status !== 'all') {
    $query .= " WHERE a.status = '" . $conn->real_escape_string($filter_status) . "'";
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$result = $conn->query($query);
$appointments = array();

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

// Get statistics
$statsQuery = "SELECT 
               COUNT(*) as total,
               SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
               FROM appointments";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get recent users
$usersQuery = "SELECT COUNT(*) as total_users FROM users";
$usersResult = $conn->query($usersQuery);
$usersStats = $usersResult->fetch_assoc();

// Get doctors and available time slots for admin scheduling
$doctors = [];
$doctorResult = $conn->query("SELECT * FROM doctors ORDER BY is_active DESC, name");
while ($row = $doctorResult->fetch_assoc()) {
    $doctors[] = $row;
}

$activeDoctors = array_values(array_filter($doctors, function($d) {
    return intval($d['is_active']) === 1;
}));

$timeSlots = [];
$slotResult = $conn->query("SELECT slot_time FROM time_slots ORDER BY slot_time");
while ($row = $slotResult->fetch_assoc()) {
    $timeSlots[] = $row;
}

$adminName = $_SESSION['admin_name'] ?? 'Administrator';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Appointment System</title>
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
        <div class="admin-header">
            <div>
                <h1>⚙️ Admin Panel</h1>
                <p>Welcome, <?php echo htmlspecialchars($adminName); ?>. Manage appointments and view system statistics.</p>
            </div>
            <button type="button" id="themeToggle" class="btn btn-secondary" aria-label="Toggle site theme">🌙 Dark</button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <section class="section">
            <div class="section-title">
                <div>
                    <h2>Admin Dashboard</h2>
                    <p>Monitor bookings and manage schedules in one polished workspace.</p>
                </div>
                <div class="hero-actions">
                    <a href="booking.php" class="btn btn-secondary">Back to Booking</a>
                    <a href="logout_admin.php" class="btn btn-primary">Logout</a>
                </div>
            </div>

            <div class="services-grid">
                <div class="status-card">
                    <p>Total Appointments</p>
                    <strong><?php echo $stats['total']; ?></strong>
                </div>
                <div class="status-card">
                    <p>Scheduled</p>
                    <strong><?php echo $stats['scheduled'] ?? 0; ?></strong>
                </div>
                <div class="status-card">
                    <p>Completed</p>
                    <strong><?php echo $stats['completed'] ?? 0; ?></strong>
                </div>
                <div class="status-card">
                    <p>Cancelled</p>
                    <strong><?php echo $stats['cancelled'] ?? 0; ?></strong>
                </div>
            </div>

            <div class="info-row">
                <div class="form-card">
                    <h3>Schedule Appointment</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="patient_name">Patient Name *</label>
                            <input type="text" id="patient_name" name="patient_name" required>
                        </div>
                        <div class="form-group">
                            <label for="patient_email">Patient Email *</label>
                            <input type="email" id="patient_email" name="patient_email" required>
                        </div>
                        <div class="form-group">
                            <label for="patient_phone">Patient Phone</label>
                            <input type="tel" id="patient_phone" name="patient_phone">
                        </div>
                        <div class="form-group">
                            <label for="doctor_id">Doctor *</label>
                            <select id="doctor_id" name="doctor_id" required>
                                <option value="">-- Select doctor --</option>
                                <?php foreach ($activeDoctors as $doctor): ?>
                                    <option value="<?php echo intval($doctor['id']); ?>"><?php echo htmlspecialchars($doctor['name'] . ' - ' . $doctor['specialty'] . ' (' . $doctor['work_days'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="appointment_date">Appointment Date *</label>
                            <input type="date" id="appointment_date" name="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="appointment_time">Appointment Time *</label>
                            <select id="appointment_time" name="appointment_time" required>
                                <option value="">-- Select time --</option>
                                <?php foreach ($timeSlots as $slot): ?>
                                    <option value="<?php echo $slot['slot_time']; ?>"><?php echo substr($slot['slot_time'], 0, 5); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="service_type">Service Type *</label>
                            <input type="text" id="service_type" name="service_type" value="In-person Consultation" required>
                        </div>
                        <div class="form-group">
                            <label for="duration">Duration (minutes)</label>
                            <input type="number" id="duration" name="duration" value="30" min="10">
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="notes"></textarea>
                        </div>
                        <button type="submit" name="schedule_appointment" class="btn btn-primary">Schedule Appointment</button>
                    </form>
                </div>

                <div class="form-card">
                    <h3>Doctor Availability</h3>
                    <?php if (count($doctors) > 0): ?>
                        <div class="table-wrapper" style="margin-bottom: 24px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Doctor</th>
                                        <th>Schedule</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($doctor['name'] . ' (' . $doctor['specialty'] . ')'); ?></td>
                                            <td><?php echo substr($doctor['available_from'], 0, 5); ?> - <?php echo substr($doctor['available_to'], 0, 5); ?><br><?php echo htmlspecialchars($doctor['work_days']); ?></td>
                                            <td>
                                                <form method="POST" style="display:inline-block; margin-right:8px;">
                                                    <input type="hidden" name="doctor_id" value="<?php echo intval($doctor['id']); ?>">
                                                    <input type="hidden" name="toggle_doctor" value="1">
                                                    <input type="hidden" name="is_active" value="<?php echo $doctor['is_active'] ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-small"><?php echo $doctor['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                                                </form>
                                                <span><?php echo $doctor['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No doctors found yet. Add one below.</p>
                    <?php endif; ?>

                    <h3>Add New Doctor</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="new_doctor_name">Name *</label>
                            <input type="text" id="new_doctor_name" name="new_doctor_name" required>
                        </div>
                        <div class="form-group">
                            <label for="new_doctor_specialty">Specialty *</label>
                            <input type="text" id="new_doctor_specialty" name="new_doctor_specialty" required>
                        </div>
                        <div class="form-group">
                            <label for="new_available_from">Available From *</label>
                            <input type="time" id="new_available_from" name="new_available_from" required>
                        </div>
                        <div class="form-group">
                            <label for="new_available_to">Available To *</label>
                            <input type="time" id="new_available_to" name="new_available_to" required>
                        </div>
                        <div class="form-group">
                            <label for="new_work_days">Work Days *</label>
                            <input type="text" id="new_work_days" name="new_work_days" placeholder="Mon,Tue,Wed,Thu,Fri" required>
                        </div>
                        <button type="submit" name="create_doctor" class="btn btn-primary">Add Doctor</button>
                    </form>
                </div>
            </div>

            <div class="section-title" style="margin-top: 44px;">
                <div>
                    <h2>Manage Appointments</h2>
                    <p>Update statuses and remove old records from the system.</p>
                </div>
            </div>

            <?php if (count($appointments) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $idx => $appt): ?>
                                <tr>
                                    <td><?php echo intval($idx + 1); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?> <br><small><?php echo substr($appt['appointment_time'], 0, 5); ?></small></td>
                                    <td><?php echo htmlspecialchars($appt['name']); ?><br><small><?php echo htmlspecialchars($appt['email']); ?></small></td>
                                    <td><?php echo htmlspecialchars($appt['doctor_name'] ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($appt['service_type']); ?></td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars($appt['status']); ?>"><?php echo ucfirst(htmlspecialchars($appt['status'])); ?></span></td>
                                    <td>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Update appointment status?');">
                                            <input type="hidden" name="appointment_id" value="<?php echo intval($appt['id']); ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" style="min-width: 140px;">
                                                <option value="scheduled" <?php echo $appt['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="completed" <?php echo $appt['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $appt['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-small" style="margin-left:8px;">Update</button>
                                        </form>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to cancel this appointment?');" style="margin-left:8px;">
                                            <input type="hidden" name="appointment_id" value="<?php echo intval($appt['id']); ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <input type="hidden" name="status" value="cancelled">
                                            <?php if ($appt['status'] !== 'cancelled'): ?>
                                                <button type="submit" class="btn btn-secondary btn-small">Cancel</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-small" disabled>Cancelled</button>
                                            <?php endif; ?>
                                        </form>
                                        <form method="POST" class="inline-form" style="margin-left:8px;">
                                            <input type="hidden" name="appointment_id" value="<?php echo intval($appt['id']); ?>">
                                            <button type="submit" name="delete_appointment" class="btn btn-secondary btn-small" onclick="return confirm('Are you sure you want to delete this appointment?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="message info">No appointments found for the selected filter.</div>
            <?php endif; ?>
        </section>

        <footer class="site-footer">
            <p>© <?php echo date('Y'); ?> HealthCare Appointment System.</p>
        </footer>
    </div>
    </div>
