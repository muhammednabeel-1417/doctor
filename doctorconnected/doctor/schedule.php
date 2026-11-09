<?php
/**
 * DOCTOR CONNECT - Doctor Weekly Availability & Consultation Timing Schedule
 */
$requiredRole = 'doctor';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Weekly Consultation Schedule';
$pageSubtitle = 'Configure your weekly availability hours, maximum daily tokens and duration';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);

// Fetch doctor record
$docStmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$docStmt->execute([$userId]);
$doctor = $docStmt->fetch();

if (!$doctor) {
    echo "Doctor record not found.";
    exit;
}
$doctorId = (int)$doctor['id'];

$error = '';
$success = '';

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Handle Schedule Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $avgMinutes = max(5, (int)($_POST['avg_consultation_time'] ?? 15));
        $consultationFee = max(0, (float)($_POST['consultation_fee'] ?? 50.00));

        try {
            $pdo->beginTransaction();

            // 1. Update doctor parameters
            $updDoc = $pdo->prepare("UPDATE doctors SET avg_consultation_time = ?, consultation_fee = ? WHERE id = ?");
            $updDoc->execute([$avgMinutes, $consultationFee, $doctorId]);

            // 2. Update weekly schedules
            $schedStmt = $pdo->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_tokens, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), max_tokens = VALUES(max_tokens), is_active = VALUES(is_active)");

            foreach ($daysOfWeek as $day) {
                $isActive = isset($_POST['active_' . $day]) ? 1 : 0;
                $startTime = sanitize_input($_POST['start_' . $day] ?? '09:00');
                $endTime = sanitize_input($_POST['end_' . $day] ?? '13:00');
                $maxTokens = max(1, (int)($_POST['max_' . $day] ?? 25));

                $schedStmt->execute([$doctorId, $day, $startTime, $endTime, $maxTokens, $isActive]);
            }

            $pdo->commit();
            $success = 'Weekly availability schedule and consultation settings saved successfully!';

            // Refresh doctor data
            $doctor['avg_consultation_time'] = $avgMinutes;
            $doctor['consultation_fee'] = $consultationFee;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error saving schedule: ' . $e->getMessage();
        }
    }
}

// Fetch current schedules
$schedFetch = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ?");
$schedFetch->execute([$doctorId]);
$rawSchedules = $schedFetch->fetchAll();
$currentSchedules = [];
foreach ($rawSchedules as $s) {
    $currentSchedules[$s['day_of_week']] = $s;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 900px; margin: 0 auto;">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error); ?></div>
    <?php elseif (!empty($success)): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="schedule.php">
        <?= csrf_field(); ?>

        <!-- Consultation Parameters -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-sliders" style="color: var(--primary);"></i>
                    General Consultation Parameters
                </div>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="avg_consultation_time" class="form-label">Average Consultation Duration (Minutes)</label>
                        <input type="number" id="avg_consultation_time" name="avg_consultation_time" class="form-control" 
                            min="5" max="60" value="<?= (int)$doctor['avg_consultation_time']; ?>" required>
                        <small style="color: var(--slate-500);">Used to calculate patients' estimated consultation appointment times.</small>
                    </div>

                    <div class="form-group">
                        <label for="consultation_fee" class="form-label">Consultation Fee ($ USD)</label>
                        <input type="number" step="0.01" id="consultation_fee" name="consultation_fee" class="form-control" 
                            min="0" value="<?= number_format($doctor['consultation_fee'], 2, '.', ''); ?>" required>
                        <small style="color: var(--slate-500);">Visible to patients during token booking.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Days Table -->
        <div class="card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i>
                    Weekly Day-by-Day Availability
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 150px;">Day of Week</th>
                            <th style="width: 100px;">Status</th>
                            <th>Consultation Start</th>
                            <th>Consultation End</th>
                            <th>Max Tokens / Day</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daysOfWeek as $day): 
                            $sched = $currentSchedules[$day] ?? null;
                            $isActive = $sched ? (bool)$sched['is_active'] : in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday']);
                            $startTime = $sched ? substr($sched['start_time'], 0, 5) : '09:00';
                            $endTime = $sched ? substr($sched['end_time'], 0, 5) : '13:00';
                            $maxTokens = $sched ? (int)$sched['max_tokens'] : 25;
                        ?>
                            <tr>
                                <td>
                                    <strong><?= $day; ?></strong>
                                </td>
                                <td>
                                    <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                                        <input type="checkbox" name="active_<?= $day; ?>" value="1" <?= $isActive ? 'checked' : ''; ?>>
                                        <span style="font-size: 0.85rem; font-weight: 600;">Active</span>
                                    </label>
                                </td>
                                <td>
                                    <input type="time" name="start_<?= $day; ?>" class="form-control" style="padding: 0.35rem 0.5rem; font-size: 0.85rem;" value="<?= $startTime; ?>">
                                </td>
                                <td>
                                    <input type="time" name="end_<?= $day; ?>" class="form-control" style="padding: 0.35rem 0.5rem; font-size: 0.85rem;" value="<?= $endTime; ?>">
                                </td>
                                <td>
                                    <input type="number" name="max_<?= $day; ?>" class="form-control" style="padding: 0.35rem 0.5rem; font-size: 0.85rem; max-width: 100px;" min="1" max="100" value="<?= $maxTokens; ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
            <i class="fa-solid fa-floppy-disk"></i> Save Weekly Schedule & Availability Settings
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

