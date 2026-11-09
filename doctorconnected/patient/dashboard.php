<?php
/**
 * DOCTOR CONNECT - Patient Dashboard
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

if (!$currentUser) {
    header('Location: ../auth/login.php');
    exit;
}

$pageTitle = 'Patient Dashboard';
$pageSubtitle = 'Welcome back, ' . htmlspecialchars($currentUser['name'] ?? 'Patient');

$pdo = get_db_connection();
$patientId = (int)($currentUser['id'] ?? 0);

// 1. Fetch patient's active appointment for today (if any)
$activeStmt = $pdo->prepare("SELECT a.*, d.room_number, d.avg_consultation_time, u.name AS doctor_name, d.specialization 
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ? AND a.appointment_date = CURRENT_DATE() AND a.status IN ('waiting', 'serving')
    ORDER BY a.id DESC LIMIT 1");
$activeStmt->execute([$patientId]);
$activeAppointment = $activeStmt->fetch();

// 2. Fetch statistics
$statStmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_appointments,
    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
    FROM appointments WHERE patient_id = ?");
$statStmt->execute([$patientId]);
$stats = $statStmt->fetch();

// 3. Fetch top 4 available doctors
$docStmt = $pdo->query("SELECT d.*, u.name, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.status = 'active' LIMIT 4");
$doctors = $docStmt->fetchAll();

// 4. Fetch recent 5 appointments
$recentStmt = $pdo->prepare("SELECT a.*, u.name AS doctor_name, d.specialization 
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.token_number DESC LIMIT 5");
$recentStmt->execute([$patientId]);
$recentAppointments = $recentStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Active Appointment Banner (if patient has a consultation today) -->
<?php if ($activeAppointment): ?>
    <div class="card" style="margin-bottom: 2rem; border-left: 5px solid var(--primary); background: linear-gradient(to right, #ffffff, #f0f9ff);">
        <div class="card-body">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <span class="pulse-dot"></span>
                        <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary);">Active Consultation Today</span>
                        <span class="badge badge-<?= e($activeAppointment['status']); ?>"><?= ucfirst(e($activeAppointment['status'])); ?></span>
                    </div>
                    <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--slate-900); margin: 0;">
                        Dr. <?= e($activeAppointment['doctor_name']); ?>
                    </h2>
                    <p style="color: var(--slate-600); font-size: 0.9rem; margin: 0.2rem 0 0.5rem;">
                        <i class="fa-solid fa-stethoscope" style="color: var(--primary);"></i> <?= e($activeAppointment['specialization']); ?> &bull; 
                        <i class="fa-regular fa-clock"></i> Est. Time: <?= format_time($activeAppointment['estimated_time']); ?>
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <div style="text-align: right;">
                        <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--slate-500); font-weight: 700;">Your Token Number</span>
                        <div style="font-size: 2.25rem; font-weight: 900; color: var(--primary); line-height: 1;">
                            #<?= (int)$activeAppointment['token_number']; ?>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="queue.php?id=<?= $activeAppointment['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-users-line"></i> View Live Queue
                        </a>
                        <a href="consultation.php?id=<?= $activeAppointment['id']; ?>" class="btn btn-outline btn-sm">
                            <i class="fa-solid fa-video"></i> Video Consultation
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="stats-grid">
    <div class="stat-card stat-info">
        <div>
            <div class="stat-label">Total Bookings</div>
            <div class="stat-value"><?= (int)($stats['total_appointments'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--slate-500);">All-time consultations</span>
        </div>
        <div class="stat-icon">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Upcoming / Waiting</div>
            <div class="stat-value"><?= (int)($stats['waiting_count'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--warning-dark);">Queued tokens</span>
        </div>
        <div class="stat-icon">
            <i class="fa-solid fa-user-clock"></i>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Completed Consults</div>
            <div class="stat-value"><?= (int)($stats['completed_count'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--success-dark);">Prescriptions ready</span>
        </div>
        <div class="stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>
</div>

<!-- Available Doctors Section -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
    <div>
        <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--slate-900); margin: 0;">Available Medical Specialists</h3>
        <p style="font-size: 0.85rem; color: var(--slate-500); margin: 0.15rem 0 0;">Book a real-time token for an online video consultation</p>
    </div>
    <a href="doctors.php" class="btn btn-outline btn-sm">View All Doctors &rarr;</a>
</div>

<div class="doctor-cards-grid" style="margin-bottom: 2.5rem;">
    <?php foreach ($doctors as $doc): ?>
        <div class="doctor-card">
            <div>
                <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                    <div class="doctor-avatar-large" style="margin-bottom: 0;">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <div>
                        <h4 style="font-size: 1.05rem; font-weight: 800; margin: 0; color: var(--slate-900);">
                            Dr. <?= e($doc['name']); ?>
                        </h4>
                        <div style="color: var(--primary); font-size: 0.85rem; font-weight: 700;">
                            <?= e($doc['specialization']); ?>
                        </div>
                    </div>
                </div>

                <div style="font-size: 0.825rem; color: var(--slate-600); margin-bottom: 0.75rem;">
                    <i class="fa-solid fa-graduation-cap" style="color: var(--slate-400);"></i> <?= e($doc['qualification']); ?>
                </div>
                <div style="font-size: 0.825rem; color: var(--slate-600); margin-bottom: 1rem;">
                    <i class="fa-solid fa-business-time" style="color: var(--slate-400);"></i> <?= (int)$doc['experience_years']; ?> years experience
                </div>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <span style="font-size: 0.75rem; color: var(--slate-400); text-transform: uppercase;">Fee</span>
                    <div style="font-weight: 800; color: var(--slate-900); font-size: 1.15rem;">$<?= number_format($doc['consultation_fee'], 2); ?></div>
                </div>
                <a href="book_appointment.php?doctor_id=<?= $doc['id']; ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-ticket"></i> Book Token
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Recent Appointments Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-regular fa-calendar-check" style="color: var(--primary);"></i>
            Your Recent Consultations
        </div>
        <a href="appointments.php" class="btn btn-secondary btn-sm">Full History</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Token</th>
                    <th>Doctor</th>
                    <th>Specialization</th>
                    <th>Date</th>
                    <th>Est. Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAppointments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--slate-400);">
                            You have no appointments yet. <a href="doctors.php">Book your first doctor appointment now!</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentAppointments as $apt): ?>
                        <tr>
                            <td><strong class="patient-token-badge">#<?= (int)$apt['token_number']; ?></strong></td>
                            <td><strong>Dr. <?= e($apt['doctor_name']); ?></strong></td>
                            <td><?= e($apt['specialization']); ?></td>
                            <td><?= format_date($apt['appointment_date']); ?></td>
                            <td><?= format_time($apt['estimated_time']); ?></td>
                            <td>
                                <span class="badge badge-<?= e($apt['status']); ?>">
                                    <?= ucfirst(e($apt['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.4rem;">
                                    <a href="queue.php?id=<?= $apt['id']; ?>" class="btn btn-outline btn-sm" title="Live Queue">
                                        <i class="fa-solid fa-users-line"></i>
                                    </a>
                                    <?php if (in_array($apt['status'], ['serving', 'waiting'])): ?>
                                        <a href="consultation.php?id=<?= $apt['id']; ?>" class="btn btn-primary btn-sm" title="Video Room">
                                            <i class="fa-solid fa-video"></i>
                                        </a>
                                    <?php elseif ($apt['status'] === 'completed' && !empty($apt['prescription'])): ?>
                                        <a href="appointments.php#apt-<?= $apt['id']; ?>" class="btn btn-secondary btn-sm" title="View Prescription">
                                            <i class="fa-solid fa-file-prescription"></i>
                                        </a>
                                    <?php endif; ?>
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

