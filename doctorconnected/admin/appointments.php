<?php
/**
 * DOCTOR CONNECT - Admin Appointment Roster & Queue Monitor
 */
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'All Appointments & Live Queues';
$pageSubtitle = 'Supervise system-wide patient queues, no-shows, and consultation statuses';

$pdo = get_db_connection();

// Filter parameters
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$date = sanitize_input($_GET['date'] ?? '');
$status = sanitize_input($_GET['status'] ?? '');
$search = sanitize_input($_GET['search'] ?? '');

// Handle Status Changes from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        set_flash_message('danger', 'Invalid security token.');
    } else {
        $action = sanitize_input($_POST['action']);
        $aptId = (int)($_POST['appointment_id'] ?? 0);

        try {
            if ($action === 'set_status' && $aptId > 0) {
                $newStatus = sanitize_input($_POST['new_status'] ?? '');
                if (in_array($newStatus, ['waiting', 'serving', 'completed', 'no_show', 'cancelled'])) {
                    $upd = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                    $upd->execute([$newStatus, $aptId]);

                    log_queue_event($pdo, $aptId, 1, 0, null, $newStatus, (int)($currentUser['id'] ?? 1), 'Status overridden by Administrator');
                    set_flash_message('success', "Appointment status updated to {$newStatus}.");
                }
            } elseif ($action === 'delete' && $aptId > 0) {
                $del = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
                $del->execute([$aptId]);
                set_flash_message('success', 'Appointment removed successfully.');
            }
        } catch (Throwable $e) {
            set_flash_message('danger', 'Error updating appointment: ' . $e->getMessage());
        }
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch list of doctors for filter dropdown
$doctorsList = $pdo->query("SELECT d.id, u.name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.name ASC")->fetchAll();

// Construct Query
$sql = "SELECT a.*, d.specialization, d.consultation_fee, doc_u.name AS doctor_name, pat_u.name AS patient_name, pat_u.phone AS patient_phone
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users doc_u ON d.user_id = doc_u.id
        JOIN users pat_u ON a.patient_id = pat_u.id
        WHERE 1=1";
$params = [];

if ($doctorId > 0) {
    $sql .= " AND a.doctor_id = ?";
    $params[] = $doctorId;
}
if ($patientId > 0) {
    $sql .= " AND a.patient_id = ?";
    $params[] = $patientId;
}
if (!empty($date)) {
    $sql .= " AND a.appointment_date = ?";
    $params[] = $date;
}
if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $sql .= " AND (a.appointment_number LIKE ? OR pat_u.name LIKE ? OR doc_u.name LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY a.appointment_date DESC, a.token_number ASC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter Card -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="appointments.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
            <div>
                <label class="form-label" style="font-size: 0.8rem;">Filter Doctor</label>
                <select name="doctor_id" class="form-select" style="font-size: 0.85rem;">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctorsList as $doc): ?>
                        <option value="<?= $doc['id']; ?>" <?= $doctorId === (int)$doc['id'] ? 'selected' : ''; ?>>
                            Dr. <?= e($doc['name']); ?> (<?= e($doc['specialization']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" style="font-size: 0.8rem;">Date</label>
                <input type="date" name="date" class="form-control" style="font-size: 0.85rem;" value="<?= e($date); ?>">
            </div>

            <div>
                <label class="form-label" style="font-size: 0.8rem;">Status</label>
                <select name="status" class="form-select" style="font-size: 0.85rem;">
                    <option value="">All Statuses</option>
                    <option value="waiting" <?= $status === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                    <option value="serving" <?= $status === 'serving' ? 'selected' : ''; ?>>Serving</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="no_show" <?= $status === 'no_show' ? 'selected' : ''; ?>>No-Show</option>
                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div>
                <label class="form-label" style="font-size: 0.8rem;">Search Keyword</label>
                <input type="text" name="search" class="form-control" style="font-size: 0.85rem;" placeholder="Ref # or Name..." value="<?= e($search); ?>">
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-filter"></i> Apply</button>
                <a href="appointments.php" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-list-check" style="color: var(--primary);"></i>
            Appointments Roster (<?= count($appointments); ?> records)
        </div>
        <a href="reports.php" class="btn btn-outline btn-sm">
            <i class="fa-solid fa-file-csv"></i> Export to CSV
        </a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Token</th>
                    <th>Doctor</th>
                    <th>Patient</th>
                    <th>Date & Est. Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--slate-400);">No appointments matched your query.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $apt): ?>
                        <tr <?= $apt['status'] === 'serving' ? 'style="background-color: #f0fdf4;"' : ''; ?>>
                            <td><strong><?= e($apt['appointment_number']); ?></strong></td>
                            <td><strong class="patient-token-badge">#<?= (int)$apt['token_number']; ?></strong></td>
                            <td>
                                <div style="font-weight: 700;">Dr. <?= e($apt['doctor_name']); ?></div>
                                <div style="font-size: 0.775rem; color: var(--slate-500);"><?= e($apt['specialization']); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--slate-900);"><?= e($apt['patient_name']); ?></div>
                                <div style="font-size: 0.775rem; color: var(--slate-500);"><?= e($apt['patient_phone'] ?: 'No phone'); ?></div>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-calendar"></i> <?= format_date($apt['appointment_date']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--slate-500);"><i class="fa-regular fa-clock"></i> <?= format_time($apt['estimated_time']); ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($apt['status']); ?>">
                                    <?php if ($apt['status'] === 'serving'): ?><span class="pulse-dot"></span><?php endif; ?>
                                    <?= ucfirst(e($apt['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem; align-items: center;">
                                    <!-- Change Status Dropdown -->
                                    <form method="POST" action="appointments.php" style="display: inline;">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="appointment_id" value="<?= $apt['id']; ?>">
                                        <select name="new_status" class="form-select" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; width: auto;" onchange="this.form.submit()">
                                            <option value="" disabled selected>Change Status</option>
                                            <option value="waiting">Waiting</option>
                                            <option value="serving">Serving</option>
                                            <option value="completed">Completed</option>
                                            <option value="no_show">No-Show</option>
                                            <option value="cancelled">Cancelled</option>
                                        </select>
                                    </form>

                                    <!-- Direct Meeting Room Link -->
                                    <a href="https://meet.jit.si/<?= urlencode($apt['meeting_room']); ?>" target="_blank" class="btn btn-outline btn-sm" title="Open Video Room">
                                        <i class="fa-solid fa-video"></i>
                                    </a>

                                    <!-- Delete Button -->
                                    <form method="POST" action="appointments.php" style="display: inline;" onsubmit="return confirm('Delete appointment #<?= e($apt['appointment_number']); ?>?')">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="appointment_id" value="<?= $apt['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

