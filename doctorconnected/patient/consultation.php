<?php
/**
 * DOCTOR CONNECT - Patient Video Consultation Room (Jitsi Meet Integration)
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Video Consultation';
$pageSubtitle = 'Encrypted doctor-patient virtual consultation room';

$pdo = get_db_connection();
$patientId = (int)($currentUser['id'] ?? 0);
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointmentId) {
    set_flash_message('danger', 'No appointment specified.');
    header('Location: dashboard.php');
    exit;
}

// Fetch appointment details
$stmt = $pdo->prepare("SELECT a.*, d.specialization, d.room_number, d.avg_consultation_time, u.name AS doctor_name, u.email AS doctor_email 
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.id = ? AND a.patient_id = ?");
$stmt->execute([$appointmentId, $patientId]);
$appointment = $stmt->fetch();

if (!$appointment) {
    set_flash_message('danger', 'Appointment not found or unauthorized access.');
    header('Location: dashboard.php');
    exit;
}

// If already completed, redirect to appointment history
if ($appointment['status'] === 'completed') {
    set_flash_message('info', 'This consultation has already concluded. You can review your prescription below.');
    header('Location: appointments.php#apt-' . $appointmentId);
    exit;
}

// Verify eligibility: Must be 'serving' OR 'next' in line
$doctorId = (int)$appointment['doctor_id'];
$date = $appointment['appointment_date'];
$tokenNumber = (int)$appointment['token_number'];

// Find next token for this doctor and date
$nextStmt = $pdo->prepare("SELECT token_number FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = 'waiting' ORDER BY token_number ASC LIMIT 1");
$nextStmt->execute([$doctorId, $date]);
$nextWaitingToken = (int)$nextStmt->fetchColumn();

$isServing = ($appointment['status'] === 'serving');
$isNext = ($appointment['status'] === 'waiting' && $tokenNumber === $nextWaitingToken);

if (!$isServing && !$isNext) {
    set_flash_message('warning', 'Your video consultation room will activate when your token is Next or Currently Serving in the queue.');
    header('Location: queue.php?id=' . $appointmentId);
    exit;
}

$roomName = $appointment['meeting_room'];
$patientDisplayName = $currentUser['name'] ?? 'Patient';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="consultation-layout">
    <!-- Main Video Frame Container -->
    <div class="jitsi-frame-container">
        <div id="jitsi-meeting-container" style="width: 100%; height: 100%;">
            <!-- Jitsi Meet iframe embedded directly -->
            <iframe 
                src="https://meet.jit.si/<?= urlencode($roomName); ?>#userInfo.displayName=<?= urlencode($patientDisplayName . ' (Patient)'); ?>&config.prejoinPageEnabled=false&config.startWithAudioMuted=false&config.startWithVideoMuted=false" 
                allow="camera; microphone; fullscreen; display-capture; autoplay; clipboard-write"
                style="width: 100%; height: 100%; border: none;">
            </iframe>
        </div>
    </div>

    <!-- Consultation Info Sidebar -->
    <div class="consultation-sidebar">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--slate-50);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                <span class="badge badge-<?= e($appointment['status']); ?>">
                    <span class="pulse-dot"></span> <?= ucfirst(e($appointment['status'])); ?>
                </span>
                <span class="patient-token-badge">#<?= (int)$appointment['token_number']; ?></span>
            </div>
            <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--slate-900); margin: 0;">
                Dr. <?= e($appointment['doctor_name']); ?>
            </h3>
            <div style="font-size: 0.85rem; color: var(--primary); font-weight: 700;">
                <?= e($appointment['specialization']); ?>
            </div>
        </div>

        <div style="padding: 1.5rem; flex: 1; overflow-y: auto;">
            <div style="margin-bottom: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--slate-400); font-weight: 700;">Appointment Reference</div>
                <div style="font-weight: 700; color: var(--slate-800); font-size: 0.9rem;"><?= e($appointment['appointment_number']); ?></div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--slate-400); font-weight: 700;">Reported Symptoms</div>
                <div style="font-size: 0.85rem; color: var(--slate-600); background: #f8fafc; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); margin-top: 0.25rem;">
                    <?= nl2br(e($appointment['symptoms'] ?: 'No specific symptoms noted.')); ?>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--slate-400); font-weight: 700; margin-bottom: 0.35rem;">Connection Tips</div>
                <ul style="font-size: 0.8rem; color: var(--slate-600); padding-left: 1.2rem; line-height: 1.5;">
                    <li>Ensure your browser has granted microphone and camera permissions.</li>
                    <li>If the video fails to load due to browser blocking, you can launch the meeting in an external tab below.</li>
                </ul>
            </div>

            <a href="https://meet.jit.si/<?= urlencode($roomName); ?>" target="_blank" class="btn btn-outline btn-sm" style="width: 100%; justify-content: center; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open in Full Browser Tab
            </a>
            <a href="queue.php?id=<?= $appointmentId; ?>" class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center;">
                <i class="fa-solid fa-arrow-left"></i> Return to Live Queue
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

