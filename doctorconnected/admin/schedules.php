<?php
/**
 * DOCTOR CONNECT - Admin Doctor Schedule Oversight & Editor
 */
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Manage Doctor Schedules';
$pageSubtitle = 'Configure consultation time slots and maximum token capacities for doctors';

$pdo = get_db_connection();

// Fetch doctors list
$doctors = $pdo->query("SELECT d.id, d.specialization, u.name 
    FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.status = 'active' ORDER BY u.name ASC")->fetchAll();

$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : ($doctors[0]['id'] ?? 0);

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$error = '';
$success = '';

// Handle Schedule Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedDoctorId > 0) {
    if (!validate_csrf()) {
        $error = 'Security token invalid.';
    } else {
        try {
            $pdo->beginTransaction();

            $schedStmt = $pdo->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_tokens, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), max_tokens = VALUES(max_tokens), is_active = VALUES(is_active)");

            foreach ($daysOfWeek as $day) {
                $isActive = isset($_POST['active_' . $day]) ? 1 : 0;
                $startTime = sanitize_input($_POST['start_' . $day] ?? '09:00');
                $endTime = sanitize_input($_POST['end_' . $day] ?? '13:00');
                $maxTokens = max(1, (int)($_POST['max_' . $day] ?? 25));

                $schedStmt->execute([$selectedDoctorId, $day, $startTime, $endTime, $maxTokens, $isActive]);
            }

            $pdo->commit();
            $success = 'Doctor consultation schedule updated successfully!';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error updating schedule: ' . $e->getMessage();
        }
    }
}

// Fetch current schedule
$currSched = [];
if ($selectedDoctorId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ?");
    $stmt->execute([$selectedDoctorId]);
    foreach ($stmt->fetchAll() as $s) {
        $currSched[$s['day_of_week']] = $s;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Doctor Selector -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body" style="padding: 1.25rem 1.5rem;">
        <form method="GET" action="schedules.php" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <label for="doctor_id" style="font-weight: 700; color: var(--slate-700);">Select Physician:</label>
            <select name="doctor_id" id="doctor_id" class="form-select" style="max-width: 380px;" onchange="this.form.submit()">
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= $d['id']; ?>" <?= $selectedDoctorId === (int)$d['id'] ? 'selected' : ''; ?>>
                        Dr. <?= e($d['name']); ?> (<?= e($d['specialization']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error); ?></div>
<?php elseif (!empty($success)): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success); ?></div>
<?php endif; ?>

<!-- Schedule Form -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-regular fa-calendar-days" style="color: var(--primary);"></i>
            Weekly Working Hours & Token Limits
        </div>
    </div>

    <form method="POST" action="schedules.php?doctor_id=<?= $selectedDoctorId; ?>">
        <?= csrf_field(); ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 150px;">Day</th>
                        <th style="width: 110px;">Available?</th>
                        <th>Session Start</th>
                        <th>Session End</th>
                        <th>Max Capacity (Tokens)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daysOfWeek as $day): 
                        $s = $currSched[$day] ?? null;
                        $isActive = $s ? (bool)$s['is_active'] : in_array($day, ['Monday','Tuesday','Wednesday','Thursday','Friday']);
                        $startTime = $s ? substr($s['start_time'], 0, 5) : '09:00';
                        $endTime = $s ? substr($s['end_time'], 0, 5) : '13:00';
                        $maxTokens = $s ? (int)$s['max_tokens'] : 25;
                    ?>
                        <tr>
                            <td><strong><?= $day; ?></strong></td>
                            <td>
                                <label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                                    <input type="checkbox" name="active_<?= $day; ?>" value="1" <?= $isActive ? 'checked' : ''; ?>>
                                    <span style="font-size: 0.85rem; font-weight: 600;">Active</span>
                                </label>
                            </td>
                            <td>
                                <input type="time" name="start_<?= $day; ?>" class="form-control" style="max-width: 150px; font-size: 0.85rem;" value="<?= $startTime; ?>">
                            </td>
                            <td>
                                <input type="time" name="end_<?= $day; ?>" class="form-control" style="max-width: 150px; font-size: 0.85rem;" value="<?= $endTime; ?>">
                            </td>
                            <td>
                                <input type="number" name="max_<?= $day; ?>" class="form-control" style="max-width: 110px; font-size: 0.85rem;" min="1" max="100" value="<?= $maxTokens; ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-floppy-disk"></i> Update Doctor Schedule
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

