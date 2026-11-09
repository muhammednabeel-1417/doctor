<?php
/**
 * DOCTOR CONNECT - Doctor Consultation Dashboard
 */
$requiredRole = 'doctor';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Doctor Consultation Desk';
$pageSubtitle = 'Manage your daily token queue, video consultations, and clinical records';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);

// 1. Fetch doctor details
$docStmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$docStmt->execute([$userId]);
$doctor = $docStmt->fetch();

if (!$doctor) {
    echo "Doctor record not found. Please contact administration.";
    exit;
}
$doctorId = (int)$doctor['id'];

// 2. Fetch today's statistics
$today = date('Y-m-d');
$statStmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_today,
    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_today,
    SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) AS serving_today,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_today,
    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS noshow_today
    FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
$statStmt->execute([$doctorId, $today]);
$stats = $statStmt->fetch();

// 3. Fetch currently serving appointment (if any)
$servingStmt = $pdo->prepare("SELECT a.*, u.name AS patient_name, u.phone AS patient_phone, u.gender, u.date_of_birth, u.blood_group
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status = 'serving'
    LIMIT 1");
$servingStmt->execute([$doctorId, $today]);
$servingApt = $servingStmt->fetch();

// 4. Fetch next waiting appointment
$nextStmt = $pdo->prepare("SELECT a.*, u.name AS patient_name 
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status = 'waiting'
    ORDER BY a.token_number ASC LIMIT 1");
$nextStmt->execute([$doctorId, $today]);
$nextApt = $nextStmt->fetch();

// 5. Fetch full appointments queue for today
$queueStmt = $pdo->prepare("SELECT a.*, u.name AS patient_name, u.phone AS patient_phone 
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ? AND a.appointment_date = ?
    ORDER BY a.token_number ASC");
$queueStmt->execute([$doctorId, $today]);
$queueList = $queueStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
    <div>
        <span style="font-size: 0.85rem; color: var(--slate-500);"><i class="fa-regular fa-calendar-days"></i> Today: <strong><?= date('l, F j, Y'); ?></strong></span>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--slate-900); margin: 0.15rem 0 0;">
            Dr. <?= e($currentUser['name']); ?> &bull; <?= e($doctor['specialization']); ?>
        </h2>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <button type="button" class="btn btn-primary btn-lg" onclick="callNextPatient(<?= $doctorId; ?>, '<?= $today; ?>', '<?= csrf_token(); ?>')">
            <i class="fa-solid fa-bullhorn"></i> Call Next Patient
        </button>
        <a href="queue.php" class="btn btn-outline btn-lg">
            <i class="fa-solid fa-users-rays"></i> Full Live Queue
        </a>
    </div>
</div>

<!-- Today's Stat Cards -->
<div class="stats-grid">
    <div class="stat-card stat-info">
        <div>
            <div class="stat-label">Total Booked Today</div>
            <div class="stat-value"><?= (int)($stats['total_today'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--slate-500);">Tokens reserved</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-hospital-user"></i></div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Waiting in Queue</div>
            <div class="stat-value" id="live-waiting-count"><?= (int)($stats['waiting_today'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--warning-dark);">Tokens in line</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-user-clock"></i></div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Currently Serving</div>
            <div class="stat-value" id="live-serving-token"><?= $servingApt ? '#' . (int)$servingApt['token_number'] : 'None'; ?></div>
            <span style="font-size: 0.8rem; color: var(--success-dark);"><?= $servingApt ? e($servingApt['patient_name']) : 'Waiting to call'; ?></span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-stethoscope"></i></div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Completed Today</div>
            <div class="stat-value" id="live-completed-count"><?= (int)($stats['completed_today'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--success-dark);">Concluded consultations</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
    </div>
</div>

<!-- Active Serving Patient Workspace Card -->
<div class="card" style="margin-bottom: 2rem; border-top: 5px solid var(--primary);">
    <div class="card-header">
        <div class="card-title">
            <span class="pulse-dot"></span>
            Active Consultation Desk
        </div>
        <?php if ($servingApt): ?>
            <span class="badge badge-serving">Token #<?= (int)$servingApt['token_number']; ?> In Room</span>
        <?php else: ?>
            <span class="badge badge-waiting">No Active Patient In Room</span>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php if ($servingApt): ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: center;">
                <div>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <span class="patient-token-badge" style="width: 54px; height: 54px; font-size: 1.5rem;">
                            #<?= (int)$servingApt['token_number']; ?>
                        </span>
                        <div>
                            <h3 style="margin: 0; font-size: 1.35rem; font-weight: 800; color: var(--slate-900);">
                                <?= e($servingApt['patient_name']); ?>
                            </h3>
                            <div style="font-size: 0.85rem; color: var(--slate-500); margin-top: 0.2rem;">
                                <i class="fa-solid fa-phone"></i> <?= e($servingApt['patient_phone'] ?: 'No phone'); ?> &bull; 
                                <i class="fa-solid fa-venus-mars"></i> <?= ucfirst(e($servingApt['gender'])); ?> &bull; 
                                <i class="fa-solid fa-droplet" style="color: var(--danger);"></i> Blood: <?= e($servingApt['blood_group'] ?: 'N/A'); ?>
                            </div>
                        </div>
                    </div>

                    <div style="background: var(--slate-50); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
                        <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--slate-500);">Reported Symptoms:</span>
                        <p style="margin: 0.25rem 0 0; font-size: 0.9rem; color: var(--slate-700);">
                            <?= nl2br(e($servingApt['symptoms'] ?: 'Patient did not note any specific symptoms.')); ?>
                        </p>
                    </div>

                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <a href="consultation.php?id=<?= $servingApt['id']; ?>" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-video"></i> Open Video & Clinical Workspace
                        </a>
                        <button type="button" class="btn btn-danger" onclick="markNoShow(<?= $servingApt['id']; ?>)">
                            <i class="fa-solid fa-user-xmark"></i> Mark No-Show
                        </button>
                    </div>
                </div>

                <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; border-radius: var(--radius-lg); padding: 1.75rem; text-align: center;">
                    <div style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9;">Session In Progress</div>
                    <div style="font-size: 2rem; font-weight: 900; margin: 0.5rem 0;" id="consultation-timer">
                        00:00
                    </div>
                    <p style="font-size: 0.8rem; opacity: 0.9; margin: 0;">
                        Target time: <?= (int)$doctor['avg_consultation_time']; ?> mins
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem 1rem;">
                <i class="fa-solid fa-user-clock" style="font-size: 2.5rem; color: var(--slate-300); margin-bottom: 0.75rem;"></i>
                <h4 style="margin: 0 0 0.5rem; color: var(--slate-700);">No patient currently in consultation.</h4>
                <p style="color: var(--slate-500); font-size: 0.9rem; margin: 0 0 1.25rem;">
                    <?php if ($nextApt): ?>
                        Next waiting patient is <strong><?= e($nextApt['patient_name']); ?></strong> (Token #<?= (int)$nextApt['token_number']; ?>).
                    <?php else: ?>
                        There are no waiting patients right now.
                    <?php endif; ?>
                </p>
                <?php if ($nextApt): ?>
                    <button type="button" class="btn btn-primary" onclick="callNextPatient(<?= $doctorId; ?>, '<?= $today; ?>', '<?= csrf_token(); ?>')">
                        <i class="fa-solid fa-bullhorn"></i> Call Token #<?= (int)$nextApt['token_number']; ?> Now
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Today's Live Queue Sequence Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-list-ol" style="color: var(--primary);"></i>
            Today's Patient Queue (<?= count($queueList); ?> registered)
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--slate-500);">
            <span class="pulse-dot"></span> Live polling every 3 seconds
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Patient Name & Phone</th>
                    <th>Est. Time</th>
                    <th>Status</th>
                    <th>Symptoms</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="doctor-queue-body">
                <?php if (empty($queueList)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--slate-400); padding: 2.5rem;">
                            No appointments booked for today yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queueList as $apt): ?>
                        <tr <?= $apt['status'] === 'serving' ? 'style="background-color: #f0fdf4;"' : ''; ?>>
                            <td><strong class="patient-token-badge">#<?= (int)$apt['token_number']; ?></strong></td>
                            <td>
                                <div style="font-weight: 700; color: var(--slate-900);"><?= e($apt['patient_name']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--slate-500);"><?= e($apt['patient_phone'] ?: 'No phone'); ?></div>
                            </td>
                            <td><i class="fa-regular fa-clock"></i> <?= format_time($apt['estimated_time']); ?></td>
                            <td>
                                <span class="badge badge-<?= e($apt['status']); ?>">
                                    <?php if ($apt['status'] === 'serving'): ?><span class="pulse-dot"></span><?php endif; ?>
                                    <?= ucfirst(e($apt['status'])); ?>
                                </span>
                            </td>
                            <td style="max-width: 200px; font-size: 0.85rem; color: var(--slate-600);" class="text-truncate">
                                <?= e($apt['symptoms'] ?: 'None reported'); ?>
                            </td>
                            <td>
                                <?php if ($apt['status'] === 'serving'): ?>
                                    <a href="consultation.php?id=<?= $apt['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-stethoscope"></i> In Consultation
                                    </a>
                                <?php elseif ($apt['status'] === 'waiting'): ?>
                                    <button type="button" class="btn btn-outline btn-sm" onclick="callPatientDirectly(<?= $apt['id']; ?>, <?= $apt['token_number']; ?>)">
                                        <i class="fa-solid fa-bullhorn"></i> Call Now
                                    </button>
                                <?php elseif ($apt['status'] === 'completed'): ?>
                                    <span style="color: var(--success); font-weight: 700; font-size: 0.85rem;"><i class="fa-solid fa-check-circle"></i> Done</span>
                                <?php else: ?>
                                    <span style="color: var(--slate-400); font-size: 0.85rem;"><?= e($apt['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Simple consultation timer
let seconds = 0;
setInterval(() => {
    seconds++;
    const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
    const secs = (seconds % 60).toString().padStart(2, '0');
    const timerEl = document.getElementById('consultation-timer');
    if (timerEl) timerEl.textContent = `${mins}:${secs}`;
}, 1000);

async function markNoShow(appointmentId) {
    if (!confirm('Are you sure you want to mark this patient as No-Show?')) return;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData();
        formData.append('action', 'no_show');
        formData.append('appointment_id', appointmentId);
        formData.append('csrf_token', csrfToken);

        const res = await fetch('../api/update_appointment.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error marking no-show.');
        }
    } catch(err) {
        alert('Network request failed.');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initLiveQueuePoller({
        doctorId: <?= (int)$doctorId; ?>,
        date: '<?= e($today); ?>',
        isDoctorView: true
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

