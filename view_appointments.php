<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$message = "";
$message_type = "";
$user_id = intval($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $updateQuery = "UPDATE appointments SET status = 'cancelled' WHERE id = $appointment_id AND user_id = $user_id";

    if ($conn->query($updateQuery) === TRUE) {
        $message = "Appointment cancelled successfully.";
        $message_type = "success";
    } else {
        $message = "Error cancelling appointment: " . $conn->error;
        $message_type = "error";
    }
}

$appointments = [];
$appointmentQuery = "SELECT a.id, a.appointment_date, a.appointment_time, a.service_type, a.duration, a.notes, a.status, a.created_at, d.name AS doctor_name, d.specialty AS doctor_specialty, d.is_active AS doctor_active
                     FROM appointments a
                     LEFT JOIN doctors d ON a.doctor_id = d.id
                     WHERE a.user_id = $user_id
                     ORDER BY a.appointment_date DESC, a.appointment_time DESC";
$appointmentResult = $conn->query($appointmentQuery);
while ($row = $appointmentResult->fetch_assoc()) {
    $appointments[] = $row;
}

$statusCounts = ['scheduled' => 0, 'completed' => 0, 'cancelled' => 0];
$statusCountQuery = "SELECT status, COUNT(*) AS count FROM appointments WHERE user_id = $user_id GROUP BY status";
$statusCountResult = $conn->query($statusCountQuery);
if ($statusCountResult) {
    while ($statusRow = $statusCountResult->fetch_assoc()) {
        $statusCounts[$statusRow['status']] = intval($statusRow['count']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments</title>
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
                <a href="booking.php" class="nav-link">Book</a>
                <a href="view_appointments.php" class="nav-link active">Appointments</a>
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
                    <h2>Your Appointments</h2>
                    <p>Review all booked appointments and cancel any upcoming visit in just one click.</p>
                </div>
                <div style="display:flex;align-items:center;gap:12px;">
                    <button type="button" id="voiceBtn" class="btn btn-secondary btn-small">🎤 Voice</button>
                    <span id="voiceStatus" class="form-note">Voice commands for appointments.</span>
                    <a href="booking.php" class="btn btn-primary">Book a New Visit</a>
                </div>
            </div>

            <div class="services-grid">
                <div class="status-card">
                    <p>Scheduled</p>
                    <strong><?php echo $statusCounts['scheduled']; ?></strong>
                </div>
                <div class="status-card">
                    <p>Completed</p>
                    <strong><?php echo $statusCounts['completed']; ?></strong>
                </div>
                <div class="status-card">
                    <p>Cancelled</p>
                    <strong><?php echo $statusCounts['cancelled']; ?></strong>
                </div>
                <div class="status-card">
                    <p>Total</p>
                    <strong><?php echo count($appointments); ?></strong>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (count($appointments) > 0): ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Doctor</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appt): ?>
                                <tr data-appt-id="<?php echo intval($appt['id']); ?>">
                                    <td><?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($appt['appointment_time'], 0, 5)); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($appt['doctor_name'] ?: 'Unknown'); ?><?php echo $appt['doctor_specialty'] ? ' (' . htmlspecialchars($appt['doctor_specialty']) . ')' : ''; ?>
                                        <?php if (isset($appt['doctor_active']) && $appt['doctor_active'] == 0): ?>
                                            <br><small class="form-note">(Doctor currently inactive)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($appt['service_type']); ?></td>
                                    <td><span class="status-badge status-<?php echo htmlspecialchars($appt['status']); ?>"><?php echo ucfirst(htmlspecialchars($appt['status'])); ?></span></td>
                                    <td>
                                        <?php if ($appt['status'] === 'scheduled'): ?>
                                            <form method="POST" action="" class="inline-form">
                                                <input type="hidden" name="appointment_id" value="<?php echo intval($appt['id']); ?>">
                                                <button type="submit" name="cancel" class="btn btn-secondary btn-small" onclick="return confirm('Are you sure you want to cancel this appointment?');">Cancel</button>
                                            </form>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="message info">No appointments found yet. Book an appointment to get started.</div>
            <?php endif; ?>
        </section>

        <footer class="site-footer">
            <p>© <?php echo date('Y'); ?> HealthCare Appointment System.</p>
        </footer>
    </div>
    </div>
    <script>
    (function(){
        const voiceBtn = document.getElementById('voiceBtn');
        const voiceStatus = document.getElementById('voiceStatus');

        function setStatus(msg) { if (voiceStatus) voiceStatus.textContent = msg; }

        if (!voiceBtn) return;

        // Require HTTPS or localhost for Web Speech API in most browsers
        if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
            voiceBtn.disabled = true;
            voiceBtn.setAttribute('aria-disabled', 'true');
            setStatus('Voice requires HTTPS or localhost. Serve the site over HTTPS or use localhost to enable voice.');
            return;
        }

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            voiceBtn.disabled = true;
            voiceBtn.setAttribute('aria-disabled', 'true');
            setStatus('Voice commands not supported in this browser (use Chrome).');
            return;
        }

        const rec = new SpeechRecognition();
        rec.lang = 'en-US';
        rec.continuous = false;
        rec.interimResults = false;

        let listening = false;
        let retryNoSpeech = 0;

        voiceBtn.addEventListener('click', () => { if (listening) { rec.stop(); } else { try { rec.start(); } catch(e){} } });

        rec.onstart = () => { listening = true; retryNoSpeech = 0; voiceBtn.setAttribute('aria-pressed','true'); setStatus('Listening...'); };
        rec.onend = () => { listening = false; voiceBtn.setAttribute('aria-pressed','false'); };
        rec.onerror = (e) => {
            listening = false; voiceBtn.setAttribute('aria-pressed','false');
            const err = e && (e.error || e.message) ? (e.error || e.message) : 'unknown';
            if (err === 'no-speech' && retryNoSpeech < 1) {
                retryNoSpeech++;
                setStatus('No speech detected — please speak clearly. Retrying...');
                setTimeout(() => { try { rec.start(); } catch (ex) {} }, 600);
                return;
            }
            setStatus('Voice recognition error: ' + err + '. Try Chrome on HTTPS or localhost.');
            console.error(e);
        };

        rec.onresult = (ev) => {
            const text = ev.results[0][0].transcript.trim();
            setStatus('Heard: ' + text);
            handleVoiceCommand(text.toLowerCase());
        };

        const ORDINALS = { 'first':1,'second':2,'third':3,'fourth':4,'fifth':5,'sixth':6,'seventh':7,'eighth':8,'ninth':9,'tenth':10 };

        function findAppointmentIdByOrdinal(n) {
            const rows = Array.from(document.querySelectorAll('tbody tr'));
            if (n < 1 || n > rows.length) return null;
            return rows[n-1].dataset.apptId || null;
        }

        function handleVoiceCommand(cmd) {
            if (cmd.includes('show') && cmd.includes('appointment')) { setStatus('Opening appointments...'); window.location.href = 'view_appointments.php'; return; }
            if (cmd.includes('book') || cmd.includes('booking')) { setStatus('Opening booking page...'); window.location.href = 'booking.php'; return; }
            if (cmd.match(/logout|log out/)) { setStatus('Logging out...'); window.location.href = 'logout.php'; return; }

            // complete appointment <id|ordinal> (admin only - ignored here)
            // cancel by numeric id
            let m = cmd.match(/cancel appointment (?:number )?(\d+)/);
            if (m) { const id = m[1]; doCancelById(id); return; }

            // cancel by ordinal: "cancel my third appointment" or "cancel the second appointment"
            m = cmd.match(/cancel (?:my |the )?(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth) appointment/);
            if (m) { const ord = ORDINALS[m[1]]; const id = findAppointmentIdByOrdinal(ord); if (!id) { setStatus('Appointment number not found.'); return; } doCancelById(id); return; }

            // show details for ordinal
            m = cmd.match(/show (?:my |the )?(first|second|third|fourth|fifth|sixth|seventh|eighth|ninth|tenth) appointment/);
            if (m) { const ord = ORDINALS[m[1]]; const id = findAppointmentIdByOrdinal(ord); if (!id) { setStatus('Appointment not found.'); return; } const row = document.querySelector('tr[data-appt-id="'+id+'"]'); if (row) { row.scrollIntoView({behavior:'smooth', block:'center'}); row.classList.add('highlight'); setTimeout(()=>row.classList.remove('highlight'),2000); setStatus('Showing appointment '+ord); } return; }

            setStatus('Command not recognized. Try: "book", "show appointments", "cancel appointment 3", or "cancel my third appointment".');
        }

        function doCancelById(id) {
            if (!confirm('Confirm cancel appointment ' + id + '?')) { setStatus('Cancel aborted.'); return; }
            setStatus('Cancelling appointment ' + id + '...');
            fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'cancel=1&appointment_id=' + encodeURIComponent(id) })
            .then(resp => { if (resp.ok) { setStatus('Appointment cancelled. Reloading...'); setTimeout(()=>location.reload(),800); } else setStatus('Failed to cancel appointment.'); })
            .catch(err => { console.error(err); setStatus('Network error cancelling appointment.'); });
        }

    })();
    </script>
    <script>
    // Ajaxify cancel buttons so users can cancel without full form navigation
    (function(){
        document.querySelectorAll('form.inline-form').forEach(function(frm){
            const btn = frm.querySelector('button[name="cancel"]');
            if (!btn) return;
            frm.addEventListener('submit', function(e){
                e.preventDefault();
                if (!confirm('Are you sure you want to cancel this appointment?')) return;
                const formData = new FormData(frm);
                const params = new URLSearchParams(formData);
                // ensure cancel flag is sent when using AJAX
                if (!params.has('cancel')) params.append('cancel', '1');
                fetch(window.location.href, {
                    method: 'POST',
                    body: params
                }).then(function(resp){
                    if (resp.ok) {
                        location.reload();
                    } else {
                        alert('Failed to cancel appointment.');
                    }
                }).catch(function(){ alert('Network error while cancelling.'); });
            });
        });
    })();
    </script>
</body>
</html>
