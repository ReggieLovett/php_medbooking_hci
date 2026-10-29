<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $name = trim($conn->real_escape_string($_POST['name'] ?? ''));
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $phone = trim($conn->real_escape_string($_POST['phone'] ?? ''));
    $appointment_date = $conn->real_escape_string($_POST['appointment_date'] ?? '');
    $appointment_time = $conn->real_escape_string($_POST['appointment_time'] ?? '');
    $service_type = $conn->real_escape_string($_POST['service_type'] ?? '');
    $notes = trim($conn->real_escape_string($_POST['notes'] ?? ''));
    $doctor_id = intval($_POST['doctor_id'] ?? 0);

    $selectedDoctorId = $doctor_id;
    foreach ($doctors as $doctor) {
        if ($doctor['id'] == $selectedDoctorId) {
            $selectedDoctor = $doctor;
            break;
        }
    }

    if (empty($name) || empty($email) || empty($appointment_date) || empty($appointment_time) || empty($service_type) || !$doctor_id) {
        $message = "Please fill in all required fields, including selecting a doctor.";
        $message_type = "error";
    } elseif (!$selectedDoctor) {
        $message = "Please select a valid doctor.";
        $message_type = "error";
    } else {
        $dayName = date('D', strtotime($appointment_date));
        $availableDays = explode(',', $selectedDoctor['work_days']);
        $withinSchedule = ($appointment_time >= $selectedDoctor['available_from'] && $appointment_time <= $selectedDoctor['available_to']);

        if (!in_array($dayName, $availableDays)) {
            $message = "{$selectedDoctor['name']} is not available on " . date('l', strtotime($appointment_date)) . ". Please choose another date or doctor.";
            $message_type = "error";
        } elseif (!$withinSchedule) {
            $message = "{$selectedDoctor['name']} is available only between " . substr($selectedDoctor['available_from'], 0, 5) . " and " . substr($selectedDoctor['available_to'], 0, 5) . ".";
            $message_type = "error";
        } else {
            $user_id = intval($_SESSION['user_id']);
            $name = empty($name) ? $userName : $name;
            $email = empty($email) ? $userEmail : $email;
            $phone = empty($phone) ? $userPhone : $phone;

            if ($email !== $userEmail) {
                $emailCheck = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
                $emailResult = $conn->query($emailCheck);
                if ($emailResult && $emailResult->num_rows > 0) {
                    $message = 'That email is already linked to another account. Please use a different email.';
                    $message_type = 'error';
                }
            }

            if (empty($message)) {
                if ($userName !== $name || $userPhone !== $phone || $email !== $userEmail) {
                    $updateUser = "UPDATE users SET name = '$name', phone = '$phone', email = '$email' WHERE id = $user_id";
                    if ($conn->query($updateUser) === TRUE) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_phone'] = $phone;
                        $_SESSION['user_email'] = $email;
                    }
                }

                if ($user_id) {
                    $slotCheck = "SELECT id FROM appointments WHERE appointment_date = '$appointment_date' AND appointment_time = '$appointment_time' AND doctor_id = $doctor_id AND status != 'cancelled'";
                    $slotResult = $conn->query($slotCheck);

                    if ($slotResult->num_rows > 0) {
                        $message = "Sorry, that time slot is already booked for {$selectedDoctor['name']}. Please select another time or doctor.";
                        $message_type = "error";
                    } else {
                        $appointmentInsert = "INSERT INTO appointments (user_id, doctor_id, appointment_date, appointment_time, service_type, notes) VALUES ($user_id, $doctor_id, '$appointment_date', '$appointment_time', '$service_type', '$notes')";

                        if ($conn->query($appointmentInsert) === TRUE) {
                            $message = "Appointment booked successfully! Confirmation details have been recorded.";
                            $message_type = "success";
                            $_POST = array();
                        } else {
                            $message = "Error booking appointment: " . $conn->error;
                            $message_type = "error";
                        }
                    }
                }
            }
        }
    }
}
?>