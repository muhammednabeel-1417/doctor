<?php
/**
 * DOCTOR CONNECT - Patient Appointment History & Prescriptions
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'My Consultations';
$pageSubtitle = 'Review your appointment tokens, consultation history and medical prescriptions';

$pdo = get_db_connection();
$patientId = (int)($currentUser['id'] ?? 0);

// Filter by status if requested
$statusFilter = sanitize_input($_GET['status'] ?? 'all');

$sql = "SELECT a.*, d.specialization, d.consultation_fee, u.name AS doctor_name, u.phone AS doctor_phone
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users u ON d.user_id = u.id
        WHERE a.patient_id = ?";
$params = [$patientId];

if (!empty($statusFilter) && $statusFilter !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.token_number DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter Tabs -->
<div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <a href="appointments.php?status=all" class="btn <?= $statusFilter === 'all' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
        All Appointments
    </a>
    <a href="appointments.php?status=waiting" class="btn <?= $statusFilter === 'waiting' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
        Waiting
    </a>
    <a href="appointments.php?status=serving" class="btn <?= $statusFilter === 'serving' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
        Serving Now
    </a>
    <a href="appointments.php?status=completed" class="btn <?= $statusFilter === 'completed' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
        Completed
    </a>
    <a href="appointments.php?status=no_show" class="btn <?= $statusFilter === 'no_show' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
        No-Show
    </a>
</div>

<!-- Appointments List -->
<?php if (empty($appointments)): ?>
    <div style="text-align: center; padding: 4rem 2rem; background: #ffffff; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
        <i class="fa-regular fa-calendar-xmark" style="font-size: 3rem; color: var(--slate-300); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--slate-800); margin: 0 0 0.5rem;">No Appointments Found</h3>
        <p style="color: var(--slate-500); margin: 0 0 1.5rem;">No appointments matched the selected filter.</p>
        <a href="book_appointment.php" class="btn btn-primary">
            <i class="fa-solid fa-ticket"></i> Book a New Token
        </a>
    </div>
<?php else: ?>
    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
        <?php foreach ($appointments as $apt): ?>
            <div class="card" id="apt-<?= $apt['id']; ?>">
                <div class="card-header">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <strong class="patient-token-badge">#<?= (int)$apt['token_number']; ?></strong>
                        <div>
                            <h4 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--slate-900);">
                                Dr. <?= e($apt['doctor_name']); ?>
                            </h4>
                            <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700;">
                                <?= e($apt['specialization']); ?> &bull; Ref: <?= e($apt['appointment_number']); ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="badge badge-<?= e($apt['status']); ?>">
                            <?= ucfirst(e($apt['status'])); ?>
                        </span>
                        <?php if (in_array($apt['status'], ['waiting', 'serving'])): ?>
                            <a href="queue.php?id=<?= $apt['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fa-solid fa-users-line"></i> Live Queue
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; font-size: 0.875rem;">
                        <div>
                            <span style="color: var(--slate-400); text-transform: uppercase; font-size: 0.75rem; font-weight: 700;">Date & Time</span>
                            <div style="font-weight: 600; color: var(--slate-800); margin-top: 0.2rem;">
                                <i class="fa-regular fa-calendar"></i> <?= format_date($apt['appointment_date']); ?> at <?= format_time($apt['estimated_time']); ?>
                            </div>
                        </div>

                        <div>
                            <span style="color: var(--slate-400); text-transform: uppercase; font-size: 0.75rem; font-weight: 700;">Consultation Fee</span>
                            <div style="font-weight: 700; color: var(--slate-800); margin-top: 0.2rem;">
                                $<?= number_format($apt['consultation_fee'], 2); ?>
                            </div>
                        </div>

                        <div>
                            <span style="color: var(--slate-400); text-transform: uppercase; font-size: 0.75rem; font-weight: 700;">Booked On</span>
                            <div style="color: var(--slate-600); margin-top: 0.2rem;">
                                <?= format_date($apt['created_at']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Symptoms -->
                    <div style="background: var(--slate-50); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.75rem 1rem; font-size: 0.85rem; margin-bottom: 1rem;">
                        <strong style="color: var(--slate-700);">Reported Symptoms:</strong>
                        <span style="color: var(--slate-600);"><?= e($apt['symptoms'] ?: 'None specified during booking.'); ?></span>
                    </div>

                    <!-- Prescription & Clinical Notes (if completed) -->
                    <?php if ($apt['status'] === 'completed' && (!empty($apt['consultation_notes']) || !empty($apt['prescription']))): ?>
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 1.25rem; margin-top: 1rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; color: #166534; font-weight: 700; font-size: 0.95rem; margin-bottom: 0.75rem;">
                                <i class="fa-solid fa-file-prescription"></i> Doctor's Clinical Prescription & Notes
                            </div>

                            <?php if (!empty($apt['consultation_notes'])): ?>
                                <div style="margin-bottom: 0.75rem;">
                                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #15803d; font-weight: 700;">Doctor Notes:</div>
                                    <div style="font-size: 0.9rem; color: #14532d; white-space: pre-line;"><?= e($apt['consultation_notes']); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($apt['prescription'])): ?>
                                <div>
                                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #15803d; font-weight: 700;">Rx Prescription:</div>
                                    <div style="font-size: 0.9rem; color: #14532d; background: #ffffff; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px dashed #86efac; font-family: monospace; white-space: pre-line;"><?= e($apt['prescription']); ?></div>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: 1rem; text-align: right;">
                                <a href="prescription.php?id=<?= $apt['id']; ?>" class="btn btn-primary btn-sm" target="_blank">
                                    <i class="fa-solid fa-print"></i> View / Print Official Prescription Slip
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

