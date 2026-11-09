<?php
/**
 * DOCTOR CONNECT - Doctor Video Consultation & Clinical Workspace
 * Includes embedded Jitsi Meet video room + live clinical notes & prescription writer
 */
$requiredRole = 'doctor';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Clinical Consultation Desk';
$pageSubtitle = 'Virtual examination room and prescription record';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointmentId) {
    set_flash_message('danger', 'No appointment selected.');
    header('Location: dashboard.php');
    exit;
}

// Fetch appointment and patient data, verifying doctor ownership
$stmt = $pdo->prepare("SELECT a.*, d.id AS doc_id, u.name AS patient_name, u.email AS patient_email, 
    u.phone AS patient_phone, u.gender, u.date_of_birth, u.blood_group, u.address AS patient_address
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON a.patient_id = u.id
    WHERE a.id = ? AND d.user_id = ?");
$stmt->execute([$appointmentId, $userId]);
$apt = $stmt->fetch();

if (!$apt) {
    set_flash_message('danger', 'Appointment not found or you do not have permission.');
    header('Location: dashboard.php');
    exit;
}

$doctorId = (int)$apt['doc_id'];
$error = '';
$success = '';

// Handle Consultation Submission (Complete / Save Notes / No-Show)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $action = sanitize_input($_POST['form_action'] ?? '');
        $notes = sanitize_input($_POST['consultation_notes'] ?? '');
        $prescription = sanitize_input($_POST['prescription'] ?? '');

        try {
            if ($action === 'complete') {
                $upd = $pdo->prepare("UPDATE appointments SET status = 'completed', consultation_notes = ?, prescription = ?, completed_at = NOW() WHERE id = ?");
                $upd->execute([$notes, $prescription, $appointmentId]);

                log_queue_event($pdo, $appointmentId, $doctorId, (int)$apt['token_number'], $apt['status'], 'completed', $userId, 'Doctor finalized consultation');
                add_notification($pdo, (int)$apt['patient_id'], 'Consultation Completed', 'Doctor has concluded your consultation. Your medical prescription is ready in your portal.', 'success');

                set_flash_message('success', "Consultation for Token #{$apt['token_number']} ({$apt['patient_name']}) successfully completed!");
                header('Location: dashboard.php');
                exit;
            } elseif ($action === 'save') {
                $upd = $pdo->prepare("UPDATE appointments SET consultation_notes = ?, prescription = ? WHERE id = ?");
                $upd->execute([$notes, $prescription, $appointmentId]);
                $apt['consultation_notes'] = $notes;
                $apt['prescription'] = $prescription;
                $success = 'Clinical notes and prescription saved successfully!';
            } elseif ($action === 'no_show') {
                $upd = $pdo->prepare("UPDATE appointments SET status = 'no_show', completed_at = NOW() WHERE id = ?");
                $upd->execute([$appointmentId]);

                log_queue_event($pdo, $appointmentId, $doctorId, (int)$apt['token_number'], $apt['status'], 'no_show', $userId, 'Marked as no-show by doctor');
                add_notification($pdo, (int)$apt['patient_id'], 'Marked as No-Show', "Your Token #{$apt['token_number']} was marked as no-show by the physician.", 'alert');

                set_flash_message('warning', "Token #{$apt['token_number']} marked as No-Show.");
                header('Location: dashboard.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Automatically ensure status is 'serving' if was 'waiting'
if ($apt['status'] === 'waiting') {
    $pdo->prepare("UPDATE appointments SET status = 'serving', called_at = NOW(), started_at = NOW() WHERE id = ?")->execute([$appointmentId]);
    $apt['status'] = 'serving';
    log_queue_event($pdo, $appointmentId, $doctorId, (int)$apt['token_number'], 'waiting', 'serving', $userId, 'Doctor entered consultation room');
}

$roomName = $apt['meeting_room'];
$doctorDisplayName = 'Dr. ' . $currentUser['name'];

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error); ?></div>
<?php elseif (!empty($success)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success); ?></div>
<?php endif; ?>

<div class="consultation-layout">
    <!-- Embedded Jitsi Meet Video Stream -->
    <div class="jitsi-frame-container">
        <iframe 
            src="https://meet.jit.si/<?= urlencode($roomName); ?>#userInfo.displayName=<?= urlencode($doctorDisplayName); ?>&config.prejoinPageEnabled=false&config.startWithAudioMuted=false&config.startWithVideoMuted=false" 
            allow="camera; microphone; fullscreen; display-capture; autoplay; clipboard-write"
            style="width: 100%; height: 100%; border: none;">
        </iframe>
    </div>

    <!-- Doctor's Patient Chart & Prescription Workspace -->
    <div class="consultation-sidebar" style="display: flex; flex-direction: column;">
        <!-- Header -->
        <div style="padding: 1.25rem; border-bottom: 1px solid var(--border-color); background: var(--slate-50);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <span class="patient-token-badge">#<?= (int)$apt['token_number']; ?></span>
                <span class="badge badge-<?= e($apt['status']); ?>">
                    <span class="pulse-dot"></span> In Consultation
                </span>
            </div>
            <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--slate-900); margin: 0;">
                <?= e($apt['patient_name']); ?>
            </h3>
            <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.25rem;">
                <i class="fa-solid fa-phone"></i> <?= e($apt['patient_phone'] ?: 'No phone'); ?> &bull; 
                <i class="fa-solid fa-droplet" style="color: var(--danger);"></i> <?= e($apt['blood_group'] ?: 'Blood: N/A'); ?>
            </div>
        </div>

        <!-- Symptoms & Clinical Form -->
        <div style="padding: 1.25rem; flex: 1; overflow-y: auto;">
            <div style="background: var(--slate-100); border-radius: var(--radius-sm); padding: 0.75rem; font-size: 0.85rem; margin-bottom: 1.25rem;">
                <strong style="color: var(--slate-700);">Patient Symptoms:</strong>
                <p style="margin: 0.25rem 0 0; color: var(--slate-600);"><?= nl2br(e($apt['symptoms'] ?: 'None specified.')); ?></p>
            </div>

            <form method="POST" action="consultation.php?id=<?= $appointmentId; ?>" id="consultationForm">
                <?= csrf_field(); ?>
                <input type="hidden" name="form_action" id="form_action" value="save">

                <!-- Clinical Notes -->
                <div class="form-group">
                    <label for="consultation_notes" class="form-label">
                        <i class="fa-solid fa-notes-medical" style="color: var(--primary);"></i> Doctor's Clinical Diagnosis
                    </label>
                    <textarea id="consultation_notes" name="consultation_notes" class="form-control" rows="4" 
                        placeholder="Enter diagnosis findings, patient vitals, observations..."><?= e($apt['consultation_notes'] ?? ''); ?></textarea>
                </div>

                <!-- Prescription (Rx) -->
                <div class="form-group">
                    <label for="prescription" class="form-label">
                        <i class="fa-solid fa-prescription" style="color: var(--primary);"></i> Medical Prescription (Rx)
                    </label>
                    <textarea id="prescription" name="prescription" class="form-control" rows="5" 
                        style="font-family: monospace; font-size: 0.85rem;"
                        placeholder="e.g.&#10;1. Paracetamol 500mg - 1 tab TDS x 3 days&#10;2. Cetirizine 10mg - 1 tab HS x 5 days"><?= e($apt['prescription'] ?? ''); ?></textarea>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-top: 1rem;">
                    <button type="button" class="btn btn-success" style="justify-content: center; padding: 0.75rem;" onclick="submitForm('complete')">
                        <i class="fa-solid fa-circle-check"></i> Complete & Sign Consultation
                    </button>
                    <button type="button" class="btn btn-outline" style="justify-content: center;" onclick="submitForm('save')">
                        <i class="fa-solid fa-floppy-disk"></i> Save Notes (Keep in Room)
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" style="justify-content: center;" onclick="submitForm('no_show')">
                        <i class="fa-solid fa-user-xmark"></i> Patient Did Not Show Up
                    </button>
                    <?php if (!empty($apt['prescription'])): ?>
                        <a href="../patient/prescription.php?id=<?= $appointmentId; ?>" target="_blank" class="btn btn-secondary btn-sm" style="justify-content: center; margin-top: 0.25rem;">
                            <i class="fa-solid fa-print"></i> Preview Printable Prescription Slip
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div style="margin-top: 1rem; text-align: center;">
                <a href="https://meet.jit.si/<?= urlencode($roomName); ?>" target="_blank" style="font-size: 0.8rem; color: var(--primary);">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Open Meeting in External Browser Tab
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function submitForm(action) {
    if (action === 'complete') {
        if (!confirm('Finalize and complete this consultation? A prescription record will be saved for the patient.')) return;
    } else if (action === 'no_show') {
        if (!confirm('Mark this patient as No-Show?')) return;
    }
    document.getElementById('form_action').value = action;
    document.getElementById('consultationForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

