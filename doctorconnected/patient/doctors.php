<?php
/**
 * DOCTOR CONNECT - Browse Available Doctors
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Medical Specialists';
$pageSubtitle = 'Find qualified physicians and book consultation tokens';

$pdo = get_db_connection();

// Search and filter parameters
$search = sanitize_input($_GET['search'] ?? '');
$specialization = sanitize_input($_GET['specialization'] ?? '');

$sql = "SELECT d.*, u.name, u.email, u.phone 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE d.status = 'active'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($specialization)) {
    $sql .= " AND d.specialization = ?";
    $params[] = $specialization;
}

$sql .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// Get unique specializations for filter dropdown
$specStmt = $pdo->query("SELECT DISTINCT specialization FROM doctors WHERE status = 'active' ORDER BY specialization ASC");
$specializations = $specStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch weekly schedules mapped by doctor_id
$schedStmt = $pdo->query("SELECT doctor_id, day_of_week, start_time, end_time FROM doctor_schedules WHERE is_active = 1 ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$allSchedules = $schedStmt->fetchAll();
$doctorSchedules = [];
foreach ($allSchedules as $s) {
    $doctorSchedules[$s['doctor_id']][] = $s['day_of_week'];
}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter & Search Bar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="doctors.php" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 240px;">
                <input type="text" name="search" class="form-control" placeholder="Search by doctor name or credentials..." value="<?= e($search); ?>">
            </div>
            <div style="flex: 1; min-width: 180px;">
                <select name="specialization" class="form-select">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializations as $spec): ?>
                        <option value="<?= e($spec); ?>" <?= $specialization === $spec ? 'selected' : ''; ?>>
                            <?= e($spec); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Filter Doctors
                </button>
                <?php if (!empty($search) || !empty($specialization)): ?>
                    <a href="doctors.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Doctors Grid -->
<div class="doctor-cards-grid">
    <?php if (empty($doctors)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: #ffffff; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
            <i class="fa-solid fa-user-doctor" style="font-size: 3rem; color: var(--slate-300); margin-bottom: 1rem;"></i>
            <h3 style="color: var(--slate-700); margin: 0 0 0.5rem;">No Doctors Found</h3>
            <p style="color: var(--slate-500); margin: 0;">Try adjusting your search criteria or clearing filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($doctors as $doc): ?>
            <div class="doctor-card">
                <div>
                    <div style="display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;">
                        <div class="doctor-avatar-large">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <div>
                            <span class="badge" style="background: var(--primary-light); color: var(--primary-dark); margin-bottom: 0.25rem;">
                                <?= e($doc['specialization']); ?>
                            </span>
                            <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--slate-900);">
                                Dr. <?= e($doc['name']); ?>
                            </h3>
                            <div style="font-size: 0.8rem; color: var(--slate-500); margin-top: 0.2rem;">
                                <?= e($doc['qualification']); ?>
                            </div>
                        </div>
                    </div>

                    <p style="font-size: 0.85rem; color: var(--slate-600); margin-bottom: 1rem; line-height: 1.4;">
                        <?= e($doc['bio'] ?: 'Experienced physician dedicated to comprehensive patient health, preventative care, and specialized medical guidance.'); ?>
                    </p>

                    <div style="background: var(--slate-50); border-radius: var(--radius-md); padding: 0.75rem; font-size: 0.8rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                            <span style="color: var(--slate-500);">Experience:</span>
                            <strong><?= (int)$doc['experience_years']; ?> Years</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                            <span style="color: var(--slate-500);">Avg. Consultation:</span>
                            <strong><?= (int)$doc['avg_consultation_time']; ?> Minutes</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--slate-500);">Available Days:</span>
                            <strong style="color: var(--primary);">
                                <?= isset($doctorSchedules[$doc['id']]) ? implode(', ', array_slice($doctorSchedules[$doc['id']], 0, 4)) . (count($doctorSchedules[$doc['id']]) > 4 ? '...' : '') : 'Mon-Fri'; ?>
                            </strong>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <span style="font-size: 0.75rem; color: var(--slate-400); text-transform: uppercase;">Consultation Fee</span>
                        <div style="font-size: 1.25rem; font-weight: 800; color: var(--slate-900);">$<?= number_format($doc['consultation_fee'], 2); ?></div>
                    </div>
                    <a href="book_appointment.php?doctor_id=<?= $doc['id']; ?>" class="btn btn-primary">
                        <i class="fa-solid fa-ticket"></i> Book Token
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

