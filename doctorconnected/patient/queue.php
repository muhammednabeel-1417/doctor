<?php
/**
 * DOCTOR CONNECT - Patient Live Queue Dashboard
 * Features real-time 3-second polling, token countdown, and conditional Jitsi Meet unlocking
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Live Queue Tracker';
$pageSubtitle = 'Real-time queue monitoring and video consultation status';

$pdo = get_db_connection();
$patientId = (int)($currentUser['id'] ?? 0);

$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? sanitize_input($_GET['date']) : date('Y-m-d');

// If no appointmentId specified, look for patient's latest appointment for today or upcoming
if (!$appointmentId) {
    $findApt = $pdo->prepare("SELECT id, doctor_id, appointment_date FROM appointments 
        WHERE patient_id = ? AND appointment_date >= CURRENT_DATE() AND status IN ('waiting', 'serving') 
        ORDER BY appointment_date ASC, token_number ASC LIMIT 1");
    $findApt->execute([$patientId]);
    $defaultApt = $findApt->fetch();
    if ($defaultApt) {
        $appointmentId = (int)$defaultApt['id'];
        $doctorId = (int)$defaultApt['doctor_id'];
        $date = $defaultApt['appointment_date'];
    }
}

// Fetch appointment details
$appointment = null;
if ($appointmentId) {
    $aptStmt = $pdo->prepare("SELECT a.*, d.specialization, d.room_number, d.avg_consultation_time, u.name AS doctor_name, u.phone AS doctor_phone
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE a.id = ? AND a.patient_id = ?");
    $aptStmt->execute([$appointmentId, $patientId]);
    $appointment = $aptStmt->fetch();

    if ($appointment) {
        $doctorId = (int)$appointment['doctor_id'];
        $date = $appointment['appointment_date'];
    }
}

// Fetch doctor details if doctorId is known
$doctor = null;
if ($doctorId) {
    $docStmt = $pdo->prepare("SELECT d.*, u.name AS doctor_name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
    $docStmt->execute([$doctorId]);
    $doctor = $docStmt->fetch();
}

// All patient appointments for the quick switcher dropdown
$patientAptsStmt = $pdo->prepare("SELECT a.id, a.token_number, a.appointment_date, a.status, u.name AS doctor_name 
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ? AND a.status IN ('waiting', 'serving')
    ORDER BY a.appointment_date ASC, a.token_number ASC");
$patientAptsStmt->execute([$patientId]);
$activeTokensList = $patientAptsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Queue Selector / Switcher -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body" style="padding: 1rem 1.5rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="pulse-dot"></div>
                <span style="font-size: 0.9rem; font-weight: 700; color: var(--slate-800);">
                    Live Queue Auto-Sync Active (Refreshes every 3s)
                </span>
            </div>

            <?php if (!empty($activeTokensList)): ?>
                <form method="GET" action="queue.php" style="display: flex; align-items: center; gap: 0.5rem;">
                    <label for="id" style="font-size: 0.825rem; font-weight: 600; color: var(--slate-600);">Switch Token:</label>
                    <select name="id" id="id" class="form-select" style="padding: 0.4rem 0.75rem; font-size: 0.85rem;" onchange="this.form.submit()">
                        <?php foreach ($activeTokensList as $at): ?>
                            <option value="<?= $at['id']; ?>" <?= $appointmentId === (int)$at['id'] ? 'selected' : ''; ?>>
                                Token #<?= $at['token_number']; ?> - Dr. <?= e($at['doctor_name']); ?> (<?= format_date($at['appointment_date']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$appointment && !$doctor): ?>
    <div style="text-align: center; padding: 4rem 2rem; background: #ffffff; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
        <i class="fa-solid fa-users-line" style="font-size: 3rem; color: var(--slate-300); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--slate-800); margin: 0 0 0.5rem;">No Active Queue Selected</h3>
        <p style="color: var(--slate-500); margin: 0 0 1.5rem;">You do not currently have any queued consultation tokens for today.</p>
        <a href="book_appointment.php" class="btn btn-primary">
            <i class="fa-solid fa-ticket"></i> Book a Consultation Token
        </a>
    </div>
<?php else: ?>

    <!-- Doctor & Session Header Banner -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1.25rem;">
                    <div class="doctor-avatar-large" style="margin-bottom: 0;">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <div>
                        <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--slate-900); margin: 0;">
                            Dr. <?= e($appointment['doctor_name'] ?? $doctor['doctor_name']); ?>
                        </h2>
                        <div style="color: var(--primary); font-size: 0.875rem; font-weight: 700;">
                            <?= e($appointment['specialization'] ?? $doctor['specialization']); ?> &bull; <?= e($appointment['room_number'] ?? $doctor['room_number']); ?>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.2rem;">
                            Session Date: <strong><?= format_date($date); ?></strong>
                        </div>
                    </div>
                </div>

                <?php if ($appointment): ?>
                    <div style="text-align: right; background: var(--primary-subtle); padding: 0.75rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--primary-light);">
                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--slate-500);">Your Assigned Token</span>
                        <div style="font-size: 2.5rem; font-weight: 900; color: var(--primary); line-height: 1.1;">
                            #<?= (int)$appointment['token_number']; ?>
                        </div>
                        <div id="live-patient-position" style="margin-top: 0.25rem;">
                            <!-- Dynamically updated by script.js -->
                            <span class="badge badge-<?= e($appointment['status']); ?>">
                                <?= ucfirst(e($appointment['status'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Live Queue Hero Metrics -->
    <div class="live-queue-board">
        <!-- Currently Serving -->
        <div class="serving-hero-card">
            <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; opacity: 0.9;">
                <span class="pulse-dot" style="background-color: #ffffff;"></span>
                <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;">NOW SERVING</span>
            </div>
            <div class="token-number" id="live-serving-token">
                Loading...
            </div>
            <div style="font-size: 0.85rem; opacity: 0.85;">
                In Consultation Room
            </div>
        </div>

        <!-- Next Token in Line -->
        <div class="next-hero-card">
            <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--slate-500);">
                NEXT IN LINE
            </span>
            <div class="token-number" id="live-next-token">
                Loading...
            </div>
            <div style="font-size: 0.85rem; color: var(--slate-500);">
                Please stay prepared with your camera & mic
            </div>
        </div>

        <!-- Waiting Patients Stats -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 2rem; text-align: center;">
            <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--slate-500);">
                PATIENTS WAITING
            </span>
            <div style="font-size: 3.5rem; font-weight: 800; color: var(--warning-dark); line-height: 1; margin: 0.75rem 0;" id="live-waiting-count">
                --
            </div>
            <div style="font-size: 0.85rem; color: var(--slate-500);">
                Total remaining in queue today
            </div>
        </div>
    </div>

    <!-- Conditional Video Consultation Action Banner -->
    <?php if ($appointment): ?>
        <div class="card" style="margin-bottom: 2rem; border: 2px solid var(--primary-accent); background: #ffffff;">
            <div class="card-body" style="padding: 1.75rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.25rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-video" style="color: var(--primary); font-size: 1.2rem;"></i>
                            <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--slate-900);">
                                Secure Video Consultation Room
                            </h3>
                        </div>
                        <p style="color: var(--slate-600); font-size: 0.875rem; margin: 0.35rem 0 0;">
                            Powered by encrypted Jitsi Meet. The video link activates when your token is <strong>Next</strong> or <strong>Serving</strong>.
                        </p>
                    </div>

                    <div id="video-consultation-action">
                        <button class="btn btn-secondary btn-lg" disabled>
                            <i class="fa-solid fa-lock"></i> Awaiting Your Turn
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Full Queue Order Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-list-ol" style="color: var(--primary);"></i>
                Today's Complete Token Queue Sequence
            </div>
            <span style="font-size: 0.8rem; color: var(--slate-500);">
                Dr. <?= e($appointment['doctor_name'] ?? $doctor['doctor_name']); ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Token #</th>
                        <th>Estimated Time</th>
                        <th>Status</th>
                        <th>Position</th>
                    </tr>
                </thead>
                <tbody id="patient-queue-table-body">
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem; color: var(--slate-400);">
                            <i class="fa-solid fa-circle-notch fa-spin"></i> Synchronizing live queue status...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Polling JavaScript Initializer -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        initLiveQueuePoller({
            doctorId: <?= (int)$doctorId; ?>,
            date: '<?= e($date); ?>',
            appointmentId: <?= $appointment ? (int)$appointment['id'] : 'null'; ?>,
            isDoctorView: false
        });

        // Also populate initial patient queue order table via API
        async function loadQueueTable() {
            try {
                const res = await fetch(`../api/queue_status.php?doctor_id=<?= (int)$doctorId; ?>&date=<?= e($date); ?>`);
                const data = await res.json();
                if (data.success && data.queue) {
                    const tbody = document.getElementById('patient-queue-table-body');
                    if (!tbody) return;

                    if (data.queue.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 2rem; color: var(--slate-400);">No tokens booked yet for this date.</td></tr>';
                        return;
                    }

                    let html = '';
                    const myToken = <?= $appointment ? (int)$appointment['token_number'] : 0; ?>;

                    data.queue.forEach(item => {
                        const isMine = (item.token_number == myToken);
                        const rowStyle = isMine ? 'style="background-color: #f0f9ff; font-weight: 700;"' : '';
                        
                        let badge = `<span class="badge badge-${item.status}">${item.status.toUpperCase()}</span>`;
                        let tag = isMine ? '<span class="badge" style="background:var(--primary); color:#fff; margin-left:0.5rem;">YOU</span>' : '';

                        html += `
                            <tr ${rowStyle}>
                                <td>
                                    <strong class="patient-token-badge">#${item.token_number}</strong>
                                    ${tag}
                                </td>
                                <td><i class="fa-regular fa-clock"></i> ${item.estimated_time_formatted || item.estimated_time}</td>
                                <td>${badge}</td>
                                <td>${item.status === 'serving' ? '<span style="color:#16a34a; font-weight:700;"><span class="pulse-dot"></span> In Room</span>' : (item.status === 'completed' ? 'Finished' : 'Queued')}</td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                }
            } catch(e) {
                console.error(e);
            }
        }

        loadQueueTable();
        setInterval(loadQueueTable, 6000);
    });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

