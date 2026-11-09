<?php
/**
 * DOCTOR CONNECT - Administrator Patient Management
 */
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Patient Directory';
$pageSubtitle = 'Monitor patient registrations, personal medical data, and consultation histories';

$pdo = get_db_connection();
$search = sanitize_input($_GET['search'] ?? '');
$deleteId = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;

// Handle Delete Patient
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $deleteId > 0) {
    if (!validate_csrf()) {
        set_flash_message('danger', 'Security validation failed.');
    } else {
        try {
            $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'");
            $del->execute([$deleteId]);
            set_flash_message('success', 'Patient account deleted successfully.');
        } catch (Throwable $e) {
            set_flash_message('danger', 'Error deleting patient: ' . $e->getMessage());
        }
    }
    header('Location: patients.php');
    exit;
}

$sql = "SELECT u.*, 
        COUNT(a.id) AS total_appointments,
        SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_appointments
        FROM users u
        LEFT JOIN appointments a ON u.id = a.patient_id
        WHERE u.role = 'patient'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term];
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search & Stats Bar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body" style="padding: 1rem 1.5rem;">
        <form method="GET" action="patients.php" style="display: flex; gap: 0.75rem; align-items: center; max-width: 500px;">
            <input type="text" name="search" class="form-control" placeholder="Search by patient name, email, or phone..." value="<?= e($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
            <?php if (!empty($search)): ?>
                <a href="patients.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Patients Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-hospital-user" style="color: var(--primary);"></i>
            Registered Patients (<?= count($patients); ?> total)
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Patient Name</th>
                    <th>Email & Phone</th>
                    <th>Gender & Blood</th>
                    <th>Address</th>
                    <th>Consultations</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--slate-400);">No patients found.</td></tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['name']); ?></strong>
                            </td>
                            <td>
                                <div><?= e($p['email']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--slate-500);"><?= e($p['phone'] ?: 'No phone'); ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--slate-100); color: var(--slate-700);">
                                    <?= ucfirst(e($p['gender'])); ?> &bull; Blood: <?= e($p['blood_group'] ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td style="max-width: 200px; font-size: 0.825rem; color: var(--slate-600);" class="text-truncate">
                                <?= e($p['address'] ?: 'Not provided'); ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary);"><?= (int)$p['total_appointments']; ?></strong>
                                <span style="font-size: 0.8rem; color: var(--slate-500);">(<?= (int)$p['completed_appointments']; ?> done)</span>
                            </td>
                            <td style="font-size: 0.825rem; color: var(--slate-500);"><?= format_date($p['created_at']); ?></td>
                            <td>
                                <div style="display: flex; gap: 0.35rem;">
                                    <a href="appointments.php?patient_id=<?= $p['id']; ?>" class="btn btn-outline btn-sm" title="View Appointments">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </a>
                                    <form method="POST" action="patients.php" style="display: inline;" onsubmit="return confirm('Delete patient <?= e($p['name']); ?>? All their appointment bookings will be removed.')">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="delete_id" value="<?= $p['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Patient">
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

