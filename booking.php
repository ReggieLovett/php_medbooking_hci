<?php
session_start();
include 'config.php';

ensure_default_doctors($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = "";
$message_type = "";

$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$userPhone = $_SESSION['user_phone'] ?? '';

$today = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+30 days'));
$selectedDoctorId = 0;
$selectedDate = $today;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['doctor_id']) && intval($_POST['doctor_id']) > 0) {
        $selectedDoctorId = intval($_POST['doctor_id']);
    } elseif (isset($_GET['doctor_id'])) {
        $selectedDoctorId = intval($_GET['doctor_id']);
    }

    if (!empty($_POST['appointment_date'])) {
        $selectedDate = $_POST['appointment_date'];
    } elseif (isset($_GET['appointment_date'])) {
        $selectedDate = $_GET['appointment_date'];
    }
} else {
    if (isset($_GET['doctor_id'])) {
        $selectedDoctorId = intval($_GET['doctor_id']);
    }
    if (isset($_GET['appointment_date'])) {
        $selectedDate = $_GET['appointment_date'];
    }
}

// Get available doctors
$doctors = array();
$doctorResult = $conn->query("SELECT * FROM doctors WHERE is_active = 1 ORDER BY name");
while ($row = $doctorResult->fetch_assoc()) {
    $doctors[] = $row;
}

$hasDoctors = count($doctors) > 0;

if (!$selectedDoctorId && count($doctors) > 0) {
    $selectedDoctorId = $doctors[0]['id'];
}

$selectedDoctor = null;
foreach ($doctors as $doctor) {
    if ($doctor['id'] == $selectedDoctorId) {
        $selectedDoctor = $doctor;
        break;
    }
}

if (!$selectedDoctor && count($doctors) > 0) {
    $selectedDoctor = $doctors[0];
    $selectedDoctorId = $selectedDoctor['id'];
}

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

    if (!$hasDoctors) {
        $message = "No active doctors are available right now. Please ask admin to enable doctors.";
        $message_type = "error";
    } elseif (empty($name) || empty($email) || empty($appointment_date) || empty($appointment_time) || empty($service_type) || !$doctor_id) {
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_appointment'])) {
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    if ($appointment_id > 0) {
        $user_id = intval($_SESSION['user_id']);
        $cancelQuery = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id AND user_id = $user_id AND status != 'cancelled'";
        if ($conn->query($cancelQuery) === TRUE) {
            if ($conn->affected_rows > 0) {
                $message = "Appointment cancelled successfully.";
                $message_type = "success";
            } else {
                $message = "Unable to cancel that appointment. It may already be cancelled.";
                $message_type = "error";
            }
        } else {
            $message = "Error cancelling appointment: " . $conn->error;
            $message_type = "error";
        }
    }
}

$user_id = intval($_SESSION['user_id']);
$upcomingAppointments = array();
$appointmentQuery = "SELECT a.id, a.appointment_date, a.appointment_time, a.service_type, a.status, d.name AS doctor_name, d.specialty AS doctor_specialty
                     FROM appointments a
                     LEFT JOIN doctors d ON a.doctor_id = d.id
                     WHERE a.user_id = $user_id AND a.status = 'scheduled' AND a.appointment_date >= '$today'
                     ORDER BY a.appointment_date ASC, a.appointment_time ASC";
$appointmentResult = $conn->query($appointmentQuery);
while ($row = $appointmentResult->fetch_assoc()) {
    $upcomingAppointments[] = $row;
}

$bookedSlots = array();
$availableSlots = array();
$doctorAvailabilityMessage = '';
$doctorAvailable = true;

if ($selectedDoctor) {
    $isValidDate = strtotime($selectedDate) !== false;
    $dayName = $isValidDate ? date('D', strtotime($selectedDate)) : '';
    $availableDays = explode(',', $selectedDoctor['work_days']);
    $doctorAvailable = $isValidDate && in_array($dayName, $availableDays);
    $doctorAvailabilityMessage = "{$selectedDoctor['name']} is available on {$selectedDoctor['work_days']} from " . substr($selectedDoctor['available_from'], 0, 5) . " to " . substr($selectedDoctor['available_to'], 0, 5) . ".";

    if ($selectedDate && $doctorAvailable) {
        $bookedResult = $conn->query("SELECT appointment_time FROM appointments WHERE appointment_date = '$selectedDate' AND doctor_id = {$selectedDoctor['id']} AND status != 'cancelled'");
        while ($row = $bookedResult->fetch_assoc()) {
            $bookedSlots[] = $row['appointment_time'];
        }
    }

    $timeSlotQuery = "SELECT slot_time FROM time_slots WHERE slot_time BETWEEN '{$selectedDoctor['available_from']}' AND '{$selectedDoctor['available_to']}' ORDER BY slot_time";
    $slotResult = $conn->query($timeSlotQuery);
    while ($row = $slotResult->fetch_assoc()) {
        $availableSlots[] = $row['slot_time'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Booking System</title>
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
<script>
    function updateAvailability() {
        const doctorId = document.getElementById('doctor_id').value;
        const appointmentDate = document.getElementById('appointment_date').value;
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;
        const serviceType = document.getElementById('service_type').value;
        const notes = document.getElementById('notes').value;

        const params = new URLSearchParams();
        if (doctorId) params.set('doctor_id', doctorId);
        if (appointmentDate) params.set('appointment_date', appointmentDate);
        if (name) params.set('name', name);
        if (email) params.set('email', email);
        if (phone) params.set('phone', phone);
        if (serviceType) params.set('service_type', serviceType);
        if (notes) params.set('notes', notes);

        window.location.href = 'booking.php?' + params.toString();
    }
</script>
</head>
<body>
    <div class="container">
        <header class="site-header">
            <a href="index.php" class="logo"><span class="logo-mark">HM</span>HealthCare</a>
            <nav class="nav-links">
                <a href="booking.php" class="nav-link active">Book</a>
                <a href="view_appointments.php" class="nav-link">Appointments</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="nav-link">Hi, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                    <a href="logout.php" class="nav-link">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="nav-link">Login</a>
                <?php endif; ?>
                <button type="button" id="themeToggle" class="btn btn-secondary">🌙 Dark</button>
            </nav>
        </header>

        <section class="section">
            <div class="section-title">
                <div>
                    <h2>Appointment Booking</h2>
                    <p>Schedule a visit with your chosen doctor quickly and securely.</p>
                </div>
                <a href="view_appointments.php" class="btn btn-secondary">View My Appointments</a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$hasDoctors): ?>
                <div class="message error">No doctors are currently active. Default doctors were synced automatically. Please reload this page, or ask admin to activate doctors.</div>
            <?php endif; ?>

            <div class="info-row">
                <div class="form-card">
                    <h3>Book Your Appointment</h3>
                    <div class="voice-controls" style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
                        <button type="button" id="bookingVoiceBtn" class="btn btn-secondary btn-small" title="Click to start voice command">🎤 Voice</button>
                        <div id="voiceVisualizer" style="display:none;align-items:center;gap:5px;">
                            <span id="voiceBars" style="display:flex;gap:2px;align-items:flex-end;">
                                <span style="width:3px;height:8px;background:#007bff;border-radius:2px;animation:pulse 0.5s infinite;"></span>
                                <span style="width:3px;height:12px;background:#007bff;border-radius:2px;animation:pulse 0.5s infinite 0.1s;"></span>
                                <span style="width:3px;height:10px;background:#007bff;border-radius:2px;animation:pulse 0.5s infinite 0.2s;"></span>
                                <span style="width:3px;height:14px;background:#007bff;border-radius:2px;animation:pulse 0.5s infinite 0.3s;"></span>
                            </span>
                        </div>
                        <span id="bookingVoiceStatus" class="form-note">Use voice to set date, time, or doctor.</span>
                    </div>
                    <style>
                        @keyframes pulse {
                            0%, 100% { height: 8px; opacity: 0.6; }
                            50% { height: 16px; opacity: 1; }
                        }
                    </style>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $_GET['name'] ?? $userName); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? $userEmail); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $_GET['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="doctor_id">Doctor *</label>
                            <select id="doctor_id" name="doctor_id" required onchange="updateAvailability();" <?php echo !$hasDoctors ? 'disabled' : ''; ?>>
                                <option value="">-- Select a doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>" <?php echo ($selectedDoctor && $selectedDoctor['id'] == $doctor['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($doctor['name'] . ' - ' . $doctor['specialty']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($selectedDoctor): ?>
                            <div class="form-group">
                                <label>Availability</label>
                                <p class="form-note"><?php echo htmlspecialchars($doctorAvailabilityMessage); ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="service_type">Service Type *</label>
                            <select id="service_type" name="service_type" required>
                                <option value="">-- Select a service --</option>
                                <option value="Consultation" <?php echo ($_POST['service_type'] ?? $_GET['service_type'] ?? '') == 'Consultation' ? 'selected' : ''; ?>>Consultation</option>
                                <option value="Checkup" <?php echo ($_POST['service_type'] ?? $_GET['service_type'] ?? '') == 'Checkup' ? 'selected' : ''; ?>>Checkup</option>
                                <option value="Follow-up" <?php echo ($_POST['service_type'] ?? $_GET['service_type'] ?? '') == 'Follow-up' ? 'selected' : ''; ?>>Follow-up</option>
                                <option value="Treatment" <?php echo ($_POST['service_type'] ?? $_GET['service_type'] ?? '') == 'Treatment' ? 'selected' : ''; ?>>Treatment</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="appointment_date">Appointment Date *</label>
                            <input type="date" id="appointment_date" name="appointment_date" min="<?php echo $today; ?>" max="<?php echo $max_date; ?>" required value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? $_GET['appointment_date'] ?? $today); ?>" onchange="updateAvailability();">
                        </div>
                        <div class="form-group">
                            <label for="appointment_time">Appointment Time *</label>
                            <select id="appointment_time" name="appointment_time" required>
                                <option value="">-- Select a time --</option>
                                <?php foreach ($availableSlots as $slot):
                                    $isBooked = in_array($slot, $bookedSlots);
                                    $disabled = ($isBooked || !$doctorAvailable) ? 'disabled' : '';
                                    $selected = ($_POST['appointment_time'] ?? $_GET['appointment_time'] ?? '') == $slot ? 'selected' : '';
                                    $label = substr($slot, 0, 5);
                                    echo "<option value=\"$slot\" $disabled $selected>" . ($isBooked ? "❌ " : "") . "$label</option>";
                                endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes" rows="4" placeholder="Any additional information..."><?php echo htmlspecialchars($_POST['notes'] ?? $_GET['notes'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="book_appointment" class="btn btn-primary" <?php echo !$hasDoctors ? 'disabled' : ''; ?>>Book Appointment</button>
                    </form>
                </div>

                <div>
                    <div class="status-card">
                        <p>Current user</p>
                        <strong><?php echo htmlspecialchars($userName ?: $userEmail); ?></strong>
                    </div>
                    <div class="section-card">
                        <h3>Next steps</h3>
                        <p>Use the appointment form to select a doctor, date, and time. Then check your upcoming schedule and cancel when needed.</p>
                        <a href="view_appointments.php" class="btn btn-secondary">Review Appointments</a>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer">
            <p>© <?php echo date('Y'); ?> HealthCare Appointment System.</p>
        </footer>
    </div>
    <script>
    /**
     * HEY MED VOICE ASSISTANT - OPTIMIZED FOR ALL DEVICES
     * 
     * Cross-Device Compatibility:
     * - Desktop: Chrome, Firefox, Edge, Safari
     * - Mobile: iOS Safari, Android Chrome, Firefox
     * - Tablets: iPad, Android tablets
     * 
     * Optimizations:
     * - Responsive button sizing and layout
     * - Touch-friendly interface (48px+ touch targets)
     * - Device detection for feature availability
     * - Battery-conscious resource management
     * - Microphone permission handling
     * - iOS-specific handling
     * - Network-aware performance tuning
     */
    
    (function(){
        // ==================== DEVICE DETECTION ====================
        
        /**
         * Detect device type and capabilities
         */
        const deviceInfo = {
            isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
            isIOS: /iPhone|iPad|iPod/i.test(navigator.userAgent),
            isAndroid: /Android/i.test(navigator.userAgent),
            isTablet: /iPad|Android(?!.*Mobile)|Tablet/i.test(navigator.userAgent),
            isDesktop: !(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)),
            hasVibration: 'vibrate' in navigator,
            hasSpeechRecognition: 'SpeechRecognition' in window || 'webkitSpeechRecognition' in window,
            hasTouchSupport: () => {
                return (('ontouchstart' in window) ||
                        (navigator.maxTouchPoints > 0) ||
                        (navigator.msMaxTouchPoints > 0));
            }
        };
        
        function log(message, data = null) {
            const DEBUG = false;
            if (DEBUG) {
                console.log(`[HeyMed] ${message}`, data || '');
            }
        }
        
        log('Device Info:', deviceInfo);
        
        // ==================== CONFIGURATION ====================
        
        const btn = document.getElementById('bookingVoiceBtn');
        const status = document.getElementById('bookingVoiceStatus');
        const visualizer = document.getElementById('voiceVisualizer');
        const synth = window.speechSynthesis;
        const WAKE_WORDS = ['hey med', 'hey medic', 'hey doctor', 'hey doc', 'okay med'];
        
        // Device-specific settings
        const settings = {
            isMobile: deviceInfo.isMobile,
            isIOS: deviceInfo.isIOS,
            mobileTimeout: 30000, // Restart recognition after 30s on mobile
            desktopTimeout: 60000, // Restart after 60s on desktop
            wakeWordGap: deviceInfo.isMobile ? 2000 : 1000, // Longer gap on mobile to prevent false triggers
            microphoneRequested: false
        };
        
        // State management
        let currentMode = 'WAKE_WORD';
        let isListening = false;
        let wakeWordRecognizer = null;
        let commandRecognizer = null;
        let lastWakeWordDetection = 0;
        let retryCount = 0;
        let restartTimeout = null;
        let audioContextPool = []; // Cache audio contexts for performance
        
        // ==================== UTILITY FUNCTIONS ====================
        
        /**
         * Updates status with device-aware text sizing
         */
        function setStatus(message, shouldSpeak = false) {
            if (status) {
                status.textContent = message;
                // Adjust font size for mobile devices
                if (deviceInfo.isMobile) {
                    status.style.fontSize = '14px';
                    status.style.lineHeight = '1.4';
                }
            }
            if (shouldSpeak && synth) {
                try {
                    synth.cancel();
                    const utterance = new SpeechSynthesisUtterance(message);
                    utterance.rate = deviceInfo.isMobile ? 0.9 : 0.95;
                    utterance.pitch = 1.1;
                    utterance.volume = 0.8;
                    synth.speak(utterance);
                } catch (e) {
                    log('TTS error:', e);
                }
            }
        }
        
        /**
         * Optimized audio context creation with pooling
         */
        function getAudioContext() {
            try {
                // Reuse cached context if available and running
                if (audioContextPool.length > 0) {
                    const ctx = audioContextPool[0];
                    if (ctx.state === 'running') {
                        return ctx;
                    } else {
                        audioContextPool.shift();
                    }
                }
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                audioContextPool.push(ctx);
                // Keep pool limited to prevent memory issues
                if (audioContextPool.length > 3) {
                    audioContextPool.shift();
                }
                return ctx;
            } catch (e) {
                log('AudioContext error:', e);
                return null;
            }
        }
        
        /**
         * Plays device-optimized audio feedback
         */
        function playSound(type) {
            // Skip audio on some iOS versions due to restrictions
            if (deviceInfo.isIOS && document.hidden) {
                return;
            }
            
            try {
                const audioContext = getAudioContext();
                if (!audioContext) return;
                
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                
                // Resume audio context if suspended (iOS requirement)
                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }
                
                const now = audioContext.currentTime;
                const duration = 0.15;
                
                if (type === 'start') {
                    oscillator.frequency.value = 400;
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + duration);
                    oscillator.start(now);
                    oscillator.stop(now + duration);
                } else if (type === 'end') {
                    oscillator.frequency.value = 600;
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.1);
                    oscillator.start(now);
                    oscillator.stop(now + 0.1);
                } else if (type === 'error') {
                    oscillator.frequency.value = 200;
                    gain.gain.setValueAtTime(0.1, now);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.3);
                    oscillator.start(now);
                    oscillator.stop(now + 0.3);
                } else if (type === 'wake') {
                    oscillator.frequency.value = 300;
                    gain.gain.setValueAtTime(0.15, now);
                    gain.gain.exponentialRampToValueAtTime(0.01, now + 0.2);
                    oscillator.start(now);
                    oscillator.stop(now + 0.2);
                }
            } catch (e) {
                log('Audio playback error:', e);
            }
        }
        
        /**
         * Device-aware vibration with fallback
         */
        function vibrate(pattern) {
            if (deviceInfo.hasVibration) {
                try {
                    navigator.vibrate(pattern);
                } catch (e) {
                    log('Vibration error:', e);
                }
            }
        }
        
        /**
         * Request microphone permission on iOS
         */
        async function requestMicrophonePermission() {
            if (settings.microphoneRequested) return true;
            
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                stream.getTracks().forEach(track => track.stop());
                settings.microphoneRequested = true;
                return true;
            } catch (e) {
                log('Microphone permission denied:', e);
                setStatus('Microphone permission denied. Please enable in settings.', true);
                return false;
            }
        }
        
        // ==================== INITIALIZATION CHECKS ====================
        
        if (!btn) {
            console.error('Voice button not found');
            return;
        }
        
        // Check security context
        if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
            setStatus('Voice requires HTTPS or localhost.');
            return;
        }
        
        // Check browser support
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
            setStatus('Voice not supported in this browser.');
            return;
        }
        
        // ==================== SPEECH RECOGNIZERS SETUP ====================
        
        function initWakeWordRecognizer() {
            wakeWordRecognizer = new SpeechRecognition();
            wakeWordRecognizer.lang = 'en-US';
            wakeWordRecognizer.continuous = false;
            wakeWordRecognizer.interimResults = true;
            wakeWordRecognizer.maxAlternatives = 1;
            
            // Mobile-specific settings
            if (deviceInfo.isMobile) {
                // Shorter timeout on mobile for battery efficiency
                wakeWordRecognizer.timeout = 5000;
            }
            
            log('Wake word recognizer initialized for', deviceInfo.isMobile ? 'mobile' : 'desktop');
        }
        
        function initCommandRecognizer() {
            commandRecognizer = new SpeechRecognition();
            commandRecognizer.lang = 'en-US';
            commandRecognizer.continuous = false;
            commandRecognizer.interimResults = true;
            commandRecognizer.maxAlternatives = 1;
            
            if (deviceInfo.isMobile) {
                commandRecognizer.timeout = 8000;
            }
            
            log('Command recognizer initialized for', deviceInfo.isMobile ? 'mobile' : 'desktop');
        }
        
        initWakeWordRecognizer();
        initCommandRecognizer();
        
        // ==================== MODE SWITCHING ====================
        
        function enterCommandMode() {
            log('Entering COMMAND mode');
            currentMode = 'COMMAND';
            isListening = true;
            retryCount = 0;
            
            // Clear any pending restarts
            if (restartTimeout) clearTimeout(restartTimeout);
            
            try {
                wakeWordRecognizer.stop();
            } catch (e) {}
            
            playSound('wake');
            vibrate([50, 30, 50]);
            btn.setAttribute('aria-pressed', 'true');
            btn.textContent = deviceInfo.isMobile ? '🎤 Listening' : '🎤 Listening...';
            
            if (visualizer) visualizer.style.display = 'flex';
            setStatus('Go ahead, I\'m listening!', true);
            
            try {
                commandRecognizer.start();
            } catch (e) {
                log('Error starting command recognizer:', e);
            }
        }
        
        function exitCommandMode() {
            log('Exiting COMMAND mode');
            currentMode = 'WAKE_WORD';
            isListening = false;
            
            if (restartTimeout) clearTimeout(restartTimeout);
            
            try {
                commandRecognizer.stop();
            } catch (e) {}
            
            btn.setAttribute('aria-pressed', 'false');
            btn.textContent = '🎤 Voice';
            if (visualizer) visualizer.style.display = 'none';
            
            setTimeout(() => {
                log('Restarting wake word listening');
                startWakeWordListening();
            }, 300);
        }
        
        function startWakeWordListening() {
            if (currentMode !== 'WAKE_WORD') return;
            
            if (restartTimeout) clearTimeout(restartTimeout);
            
            try {
                log('Starting wake word listening');
                wakeWordRecognizer.start();
                
                // Auto-restart for mobile devices after timeout
                const timeout = deviceInfo.isMobile ? settings.mobileTimeout : settings.desktopTimeout;
                restartTimeout = setTimeout(() => {
                    if (currentMode === 'WAKE_WORD' && !isListening) {
                        log('Auto-restarting wake word recognition');
                        try {
                            wakeWordRecognizer.stop();
                        } catch (e) {}
                        setTimeout(() => startWakeWordListening(), 300);
                    }
                }, timeout);
                
            } catch (e) {
                log('Error starting wake word recognizer:', e);
                if (e.message && e.message.includes('already started')) {
                    setTimeout(() => startWakeWordListening(), 500);
                }
            }
        }
        
        // ==================== WAKE WORD RECOGNIZER EVENTS ====================
        
        wakeWordRecognizer.onresult = (event) => {
            let transcript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript + ' ';
            }
            
            const normalized = transcript.trim().toLowerCase();
            log('Wake word result:', normalized);
            
            let wakeWordDetected = false;
            for (const wakeWord of WAKE_WORDS) {
                if (normalized.includes(wakeWord)) {
                    wakeWordDetected = true;
                    log('Wake word detected:', wakeWord);
                    break;
                }
            }
            
            const now = Date.now();
            if (wakeWordDetected && !isListening && (now - lastWakeWordDetection) > settings.wakeWordGap) {
                log('Activating command mode');
                lastWakeWordDetection = now;
                enterCommandMode();
            }
        };
        
        wakeWordRecognizer.onerror = (event) => {
            log('Wake word error:', event.error);
            
            if (event.error !== 'aborted') {
                setTimeout(() => {
                    if (currentMode === 'WAKE_WORD') {
                        log('Restarting after error');
                        startWakeWordListening();
                    }
                }, 500);
            }
        };
        
        wakeWordRecognizer.onend = () => {
            log('Wake word recognizer ended');
            
            if (currentMode === 'WAKE_WORD' && !isListening) {
                log('Auto-restarting wake word listener');
                setTimeout(() => {
                    startWakeWordListening();
                }, 300);
            }
        };
        
        // ==================== COMMAND RECOGNIZER EVENTS ====================
        
        commandRecognizer.onresult = (event) => {
            let transcript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            
            const normalized = transcript.trim().toLowerCase();
            log('Command result:', normalized);
            
            if (normalized) {
                setStatus('Heard: "' + normalized + '"');
                
                if (event.results[event.results.length - 1].isFinal) {
                    playSound('end');
                    vibrate(50);
                    handleCommand(normalized);
                }
            }
        };
        
        commandRecognizer.onerror = (event) => {
            log('Command error:', event.error);
            const errorMessage = event.error;
            
            if (errorMessage === 'no-speech') {
                if (retryCount < 1) {
                    retryCount++;
                    playSound('error');
                    vibrate(100);
                    setStatus('No speech detected. Please try again.', true);
                    
                    setTimeout(() => {
                        if (currentMode === 'COMMAND') {
                            try {
                                commandRecognizer.start();
                            } catch (e) {}
                        }
                    }, 800);
                    return;
                }
            }
            
            playSound('error');
            vibrate([50, 50, 50]);
            setStatus('Voice error: ' + errorMessage + '. Say "Hey Med" again.', true);
            exitCommandMode();
        };
        
        commandRecognizer.onend = () => {
            log('Command recognizer ended');
            if (currentMode === 'COMMAND') {
                exitCommandMode();
            }
        };
        
        // ==================== BUTTON INTERACTION ====================
        
        /**
         * Responsive button click handler
         */
        btn.addEventListener('click', () => {
            log('Button clicked, current mode:', currentMode);
            
            if (currentMode === 'COMMAND' && isListening) {
                try {
                    commandRecognizer.stop();
                } catch (e) {}
                playSound('end');
                vibrate(50);
                setStatus('Voice command cancelled.', true);
                exitCommandMode();
            } else if (currentMode === 'WAKE_WORD') {
                // Request permission on iOS first
                if (deviceInfo.isIOS) {
                    requestMicrophonePermission().then(permitted => {
                        if (permitted) {
                            synth.cancel();
                            enterCommandMode();
                            vibrate([30, 10, 30]);
                        }
                    });
                } else {
                    synth.cancel();
                    enterCommandMode();
                    vibrate([30, 10, 30]);
                }
            }
        });
        
        // Prevent button interaction on touch for immediate feedback
        if (deviceInfo.hasTouchSupport()) {
            btn.style.touchAction = 'manipulation';
            btn.addEventListener('touchstart', function(e) {
                e.preventDefault();
                this.click();
            });
        }
        
        // ==================== VISIBILITY CHANGE HANDLER ====================
        
        /**
         * Handle page visibility changes to save battery
         */
        document.addEventListener('visibilitychange', () => {
            log('Visibility changed:', document.hidden);
            if (document.hidden) {
                if (currentMode === 'COMMAND') {
                    exitCommandMode();
                } else {
                    try {
                        wakeWordRecognizer.stop();
                    } catch (e) {}
                }
            } else {
                if (currentMode === 'WAKE_WORD') {
                    startWakeWordListening();
                }
            }
        });
        
        // ==================== RESPONSIVE LAYOUT ====================
        
        /**
         * Adjust UI for device size
         */
        function optimizeForDevice() {
            const isSmallScreen = window.innerWidth < 600;
            
            if (btn) {
                if (isSmallScreen) {
                    // Larger touch target for mobile
                    btn.style.padding = '16px 24px';
                    btn.style.fontSize = '18px';
                    btn.style.minHeight = '48px';
                } else {
                    btn.style.padding = '10px 20px';
                    btn.style.fontSize = '16px';
                }
            }
            
            if (visualizer) {
                // Scale visualizer for device
                const bars = visualizer.querySelectorAll('span');
                bars.forEach(bar => {
                    bar.style.width = isSmallScreen ? '2px' : '3px';
                });
            }
            
            if (status) {
                status.style.fontSize = isSmallScreen ? '13px' : '14px';
                status.style.marginTop = isSmallScreen ? '8px' : '10px';
            }
        }
        
        optimizeForDevice();
        window.addEventListener('resize', optimizeForDevice);
        
        // ==================== COMMAND HANDLING ====================
        
        /**
         * Data structures for command parsing
         */
        const ORD = { 'first':1,'second':2,'third':3,'fourth':4,'fifth':5,'sixth':6,'seventh':7,'eighth':8,'ninth':9,'tenth':10 };
        const CARDINAL = { 'one':1,'two':2,'three':3,'four':4,'five':5,'six':6,'seven':7,'eight':8,'nine':9,'ten':10 };
        const WORD_NUMBER = Object.assign({}, ORD, CARDINAL);
        const MONTHS = {
            january:1, jan:1, february:2, feb:2, march:3, mar:3,
            april:4, apr:4, may:5, june:6, jun:6, july:7, jul:7,
            august:8, aug:8, september:9, sept:9, septmber:9, october:10, oct:10,
            november:11, nov:11, december:12, dec:12
        };
        const DOCTORS = <?php echo json_encode(array_map(function($d){ return ['id'=>$d['id'],'name'=>$d['name']]; }, $doctors)); ?>;
        const DOCTOR_NUMBERS = DOCTORS.map((d, index) => ({ number: index + 1, name: d.name }));
        
        /**
         * Normalizes doctor names for matching
         */
        function normalizeDoctorName(text) {
            return String(text || '').toLowerCase()
                .replace(/dr\.\?\s*/g,'')
                .replace(/[^a-z0-9\s]/g,' ')
                .replace(/\s+/g,' ')
                .trim();
        }
        
        /**
         * Displays initial status message with available commands
         */
        function prefillDoctorStatus(){
            if (!status || !DOCTOR_NUMBERS.length) return;
            setStatus('👂 Listening for "Hey Med"... Then say: "doctor one", "consultation", "set date", "set time", etc.');
        }
        prefillDoctorStatus();

        /**
         * Finds doctor by name with multiple matching strategies
         */
        function findDoctorByNameBooking(q) {
            if(!q) return null;
            const query = normalizeDoctorName(q);
            const exact = DOCTORS.find(d => normalizeDoctorName(d.name) === query);
            if(exact) return exact;
            const partial = DOCTORS.find(d => normalizeDoctorName(d.name).includes(query));
            if(partial) return partial;
            const tokens = query.split(/\s+/).filter(Boolean);
            for(const d of DOCTORS){ 
                const name = normalizeDoctorName(d.name); 
                if(tokens.every(t => name.includes(t))) return d; 
            }
            return null;
        }

        /**
         * Parses natural language dates (e.g., "September 9", "9/15", "9 September 2025")
         */
        function parseNaturalDate(text) {
            const cleaned = String(text || '').toLowerCase().replace(/[^a-z0-9\/\-\.\s]/g,' ').replace(/\s+/g,' ').trim();
            const tokens = cleaned.split(' ').filter(Boolean);
            const candidates = tokens.filter(token => !['set','change','select','choose','change','appointment','date','on','for','the','to','my','at','next'].includes(token));
            let month = null;
            let day = null;
            let year = null;

            // Try month + day format
            for (let i = 0; i < candidates.length; i++) {
                if (MONTHS[candidates[i]]) {
                    month = MONTHS[candidates[i]];
                    if (candidates[i+1] && /^(\d{1,2})(st|nd|rd|th)?$/.test(candidates[i+1])) {
                        day = parseInt(candidates[i+1], 10);
                        if (candidates[i+2] && /^\d{4}$/.test(candidates[i+2])) {
                            year = parseInt(candidates[i+2], 10);
                        }
                    }
                    break;
                }
            }

            // Try day + month format
            if (!month) {
                for (let i = 0; i < candidates.length - 1; i++) {
                    if (/^(\d{1,2})(st|nd|rd|th)?$/.test(candidates[i]) && MONTHS[candidates[i+1]]) {
                        day = parseInt(candidates[i], 10);
                        month = MONTHS[candidates[i+1]];
                        if (candidates[i+2] && /^\d{4}$/.test(candidates[i+2])) {
                            year = parseInt(candidates[i+2], 10);
                        }
                        break;
                    }
                }
            }

            // Try numeric format (MM/DD or DD/MM)
            if (!month || !day) {
                const numeric = cleaned.match(/^(\d{1,2})[\/\-\.](\d{1,2})(?:[\/\-\.](\d{2,4}))?$/);
                if (numeric) {
                    let first = parseInt(numeric[1], 10);
                    let second = parseInt(numeric[2], 10);
                    year = numeric[3] ? parseInt(numeric[3], 10) : null;
                    if (first > 12 && second <= 12) {
                        day = first;
                        month = second;
                    } else {
                        month = first;
                        day = second;
                    }
                }
            }

            if (!month || !day) return null;
            if (!year) year = new Date().getFullYear();
            const date = new Date(year, month - 1, day);
            if (isNaN(date.getTime()) || date.getFullYear() !== year || date.getMonth() + 1 !== month || date.getDate() !== day) {
                return null;
            }
            if (!/\d{4}/.test(cleaned)) {
                const today = new Date();
                if (date < today) {
                    date.setFullYear(date.getFullYear() + 1);
                }
            }
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }

        /**
         * Parses natural language times (e.g., "10:30", "10 am", "three thirty pm")
         */
        function parseNaturalTime(text) {
            const cleaned = String(text || '').toLowerCase().replace(/[^a-z0-9:\s]/g,' ').replace(/\s+/g,' ').trim();
            const numberWords = {
                zero:0, one:1, two:2, three:3, four:4, five:5, six:6, seven:7, eight:8, nine:9,
                ten:10, eleven:11, twelve:12, thirteen:13, fourteen:14, fifteen:15, sixteen:16,
                seventeen:17, eighteen:18, nineteen:19, twenty:20, thirty:30, forty:40, fortyfive:45
            };
            const parts = cleaned.split(' ').map(token => numberWords[token] !== undefined ? String(numberWords[token]) : token);
            const normalized = parts.join(' ');
            const match = normalized.match(/(\d{1,2})(?::|\s+)?(\d{1,2})?\s*(am|pm)?$/);
            if (!match) return null;
            let hour = parseInt(match[1], 10);
            let minute = match[2] ? parseInt(match[2], 10) : 0;
            const ampm = match[3];
            if (minute >= 60) return null;
            if (ampm) {
                if (ampm === 'pm' && hour < 12) hour += 12;
                if (ampm === 'am' && hour === 12) hour = 0;
            }
            if (hour < 0 || hour > 23) return null;
            return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        }

        /**
         * Sets appointment date based on natural language input
         */
        function chooseDateByNatural(text) {
            const dateValue = parseNaturalDate(text);
            if (!dateValue) { 
                setStatus('Could not parse the date. Try "set date September 9".', true); 
                return; 
            }
            const el = document.getElementById('appointment_date');
            if (!el) { 
                setStatus('Date field not found.', true); 
                return; 
            }
            el.value = dateValue;
            el.dispatchEvent(new Event('change'));
            playSound('end');
            vibrate(50);
            setStatus('Date set to ' + dateValue + '.', true);
        }

        /**
         * Sets appointment time based on natural language input
         */
        function chooseTimeByNatural(text) {
            const timeValue = parseNaturalTime(text);
            if (!timeValue) { 
                setStatus('Could not parse the time. Try "set time 10:30" or "set time 10 am".', true); 
                return; 
            }
            const sel = document.getElementById('appointment_time');
            if (!sel) { 
                setStatus('Time field not found.', true); 
                return; 
            }
            const opt = Array.from(sel.options).find(o => o.value.startsWith(timeValue));
            if (opt) { 
                sel.value = opt.value; 
                playSound('end');
                vibrate(50);
                setStatus('Time set to ' + opt.textContent.replace(/^❌\s*/,'') + '.', true); 
            }
            else { 
                setStatus('Time ' + timeValue + ' not available for the current doctor.', true); 
            }
        }

        /**
         * Sets service type based on natural language input
         */
        function chooseServiceType(text) {
            const normalized = text.toLowerCase().replace(/[-]/g,' ').trim();
            const lookup = {
                'consultation':'Consultation',
                'consulting':'Consultation',
                'checkup':'Checkup',
                'check up':'Checkup',
                'follow up':'Follow-up',
                'follow-up':'Follow-up',
                'followup':'Follow-up',
                'treatment':'Treatment',
                'therapy':'Treatment',
                'check':'Checkup',
                'follow':'Follow-up'
            };
            
            // Try exact match first
            let service = lookup[normalized];
            
            // If no exact match, try partial matching
            if (!service) {
                for (const [key, val] of Object.entries(lookup)) {
                    if (normalized.includes(key) || key.includes(normalized)) {
                        service = val;
                        break;
                    }
                }
            }
            
            if (!service) { 
                setStatus('Service not recognized. Try: Consultation, Checkup, Follow-up, or Treatment.', true); 
                return; 
            }
            
            const sel = document.getElementById('service_type');
            if (!sel) { 
                setStatus('Service selector not found.', true); 
                return; 
            }
            sel.value = service;
            playSound('end');
            vibrate(50);
            setStatus('Service set to ' + service + '.', true);
        }

        /**
         * Main command processor - parses and executes user commands
         */
        function handleCommand(cmd) {
            log('Processing command:', cmd);
            
            // Show appointments
            if(cmd.includes('show') && cmd.includes('appointment')) { 
                playSound('end');
                vibrate(50);
                setStatus('Opening your appointments...', true); 
                setTimeout(() => { window.location.href='view_appointments.php'; }, 500);
                return; 
            }
            
            // Logout
            if(cmd.match(/logout|log out/)) { 
                playSound('end');
                vibrate(50);
                setStatus('Logging out now...', true); 
                setTimeout(() => { window.location.href='logout.php'; }, 500);
                return; 
            }
            
            // Clear/Reset form
            if(cmd.includes('reset') || cmd.includes('clear')) { 
                const f = document.querySelector('form'); 
                if(f) { 
                    f.reset(); 
                    playSound('end');
                    vibrate(50);
                    setStatus('Form cleared.', true); 
                } 
                return; 
            }
            
            // Submit booking
            if(cmd.includes('submit booking') || cmd.includes('book appointment') || cmd.includes('submit appointment')) { 
                const submit = document.querySelector('button[name="book_appointment"]'); 
                if(submit) { 
                    playSound('end');
                    vibrate(50);
                    setStatus('Submitting your appointment...', true); 
                    submit.click(); 
                } else {
                    playSound('error');
                    vibrate([50, 50, 50]);
                    setStatus('Submit button not found.', true);
                }
                return; 
            }

            // Service type command - flexible matching
            let m = cmd.match(/(?:set|select|choose|change)?\s*(?:service|type)?\s*(?:to)?\s*(consultation|checkup|check-up|check up|follow-up|follow up|followup|follow|treatment|therapy|check)/i);
            if(m) { 
                chooseServiceType(m[1]); 
                return; 
            }
            m = cmd.match(/^(consultation|checkup|check-up|check up|follow-up|follow up|followup|follow|treatment|therapy|check)(?:\s|$)/i);
            if(m) { 
                chooseServiceType(m[1]); 
                return; 
            }

            // Date command
            m = cmd.match(/(?:set|change|select|choose)(?: appointment)? date(?: to)? (.+)$/);
            if(m) { 
                chooseDateByNatural(m[1]); 
                return; 
            }

            // Time command
            m = cmd.match(/(?:set|select|choose|change) time(?: to)? (.+)$/);
            if(m) { 
                chooseTimeByNatural(m[1]); 
                return; 
            }

            // Doctor by number
            m = cmd.match(/(?:select|choose|set|change|use|pick)?\s*(?:doctor|dr)\s*(?:number\s*)?(\d+)/);
            if(m) { 
                chooseDoctorByNumber(parseInt(m[1],10)); 
                return; 
            }
            
            // Doctor by ordinal/cardinal
            m = cmd.match(/(?:set|select|choose|change|use|pick)?\s*(?:doctor|dr)\s*(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth|one|two|three|four|five|six|seven|eight|nine|ten)\b/);
            if(m) { 
                const n = WORD_NUMBER[m[1]]; 
                if(n) { 
                    chooseDoctorByNumber(n); 
                    return; 
                } 
            }

            // Doctor by name
            let nameMatch = cmd.match(/(?:select|choose|pick|set|use|with) doctor (.+?)(?: as the doctor)?$/) || cmd.match(/^doctor (.+?)(?: as the doctor)?$/);
            if(nameMatch) { 
                chooseDoctorByName(nameMatch[1].trim()); 
                return; 
            }

            // Command not recognized
            playSound('error');
            vibrate([50, 50, 50]);
            setStatus('Sorry, I did not understand that. Try: "doctor one", "consultation", "set date September 9", "set time 10:30", or "submit booking".', true);
        }

        /**
         * Selects doctor by number (1-based index)
         */
        function chooseDoctorByNumber(n) { 
            const sel = document.getElementById('doctor_id'); 
            if(!sel) { 
                playSound('error');
                vibrate([50, 50, 50]);
                setStatus('Doctor selector not found.', true); 
                return; 
            } 
            const opts = Array.from(sel.options).filter(o => o.value !== ''); 
            if(n < 1 || n > opts.length) { 
                playSound('error');
                vibrate([50, 50, 50]);
                setStatus('Doctor number ' + n + ' is out of range. Try 1 to ' + opts.length + '.', true); 
                return; 
            } 
            sel.value = opts[n-1].value; 
            sel.dispatchEvent(new Event('change')); 
            playSound('end');
            vibrate(50);
            setStatus('Selected ' + opts[n-1].text + '.', true);
        }
        
        /**
         * Selects doctor by name
         */
        function chooseDoctorByName(name) { 
            const doc = findDoctorByNameBooking(name); 
            if(!doc) { 
                playSound('error');
                vibrate([50, 50, 50]);
                setStatus('Doctor "' + name + '" not found.', true); 
                return; 
            } 
            const sel = document.getElementById('doctor_id'); 
            if(!sel) { 
                playSound('error');
                vibrate([50, 50, 50]);
                setStatus('Doctor selector not found.', true); 
                return; 
            } 
            const opt = Array.from(sel.options).find(o => o.value === String(doc.id)); 
            if(opt) { 
                sel.value = opt.value; 
                sel.dispatchEvent(new Event('change')); 
                playSound('end');
                vibrate(50);
                setStatus('Selected ' + doc.name + '.', true);
            } else { 
                playSound('error');
                vibrate([50, 50, 50]);
                setStatus('Doctor "' + doc.name + '" is not currently available.', true); 
            } 
        }

        // ==================== INITIALIZATION ====================
        
        /**
         * Start the voice assistant - begin listening for wake word
         */
        log('Voice assistant initialized and ready');
        setStatus('👂 Listening for "Hey Med"... Say it to activate!');
        startWakeWordListening();

    })();
    </script>
    </script>
</body>
</html>
