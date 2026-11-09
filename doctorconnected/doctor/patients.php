<?php
/**
 * DOCTOR CONNECT - Doctor's Patient Consultation History
 */
$requiredRole = 'doctor';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Patient Medical Records';
$pageSubtitle = 'Consultation archive and medical histories for your patients';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);

$docStmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
$docStmt->execute([$userId]);
$doctorId = (int)$docStmt->fetchColumn();

$search = sanitize_input($_GET['search'] ?? '');

$sql = "SELECT a.*, u.name AS patient_name, u.email AS patient_email, u.phone AS patient_phone, 
        u.gender, u.blood_group, u.date_of_birth
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ?";
$params = [$doctorId];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR a.appointment_number LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY a.appointment_date DESC, a.token_number DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$history = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search Bar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="patients.php" style="display: flex; gap: 1rem; align-items: center;">
            <div style="flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="Search patient by name, phone or appointment number..." value="<?= e($search); ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="patients.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- History Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-notes-medical" style="color: var(--primary);"></i>
            Completed & Past Consultations (<?= count($history); ?> records)
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Blood / Gender</th>
                    <th>Status</th>
                    <th>Clinical Notes & Rx</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--slate-400);">
                            No consultation records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><strong><?= e($h['appointment_number']); ?></strong></td>
                            <td><?= format_date($h['appointment_date']); ?></td>
                            <td>
                                <div style="font-weight: 700; color: var(--slate-900);"><?= e($h['patient_name']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--slate-500);"><?= e($h['patient_phone'] ?: 'No phone'); ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--slate-100); color: var(--slate-700);">
                                    <?= e($h['blood_group'] ?: 'N/A'); ?> &bull; <?= ucfirst(e($h['gender'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($h['status']); ?>">
                                    <?= ucfirst(e($h['status'])); ?>
                                </span>
                            </td>
                            <td style="max-width: 250px; font-size: 0.825rem; color: var(--slate-600);" class="text-truncate">
                                <?= e($h['consultation_notes'] ?: ($h['prescription'] ?: 'No notes added')); ?>
                            </td>
                            <td>
                                <a href="consultation.php?id=<?= $h['id']; ?>" class="btn btn-outline btn-sm">
                                    <i class="fa-solid fa-eye"></i> View / Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

