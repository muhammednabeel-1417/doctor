<?php
/**
 * DOCTOR CONNECT - Administrator Control Center
 */
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Executive Administration';
$pageSubtitle = 'Platform metrics, doctor management, real-time queues, and clinical audit logs';

$pdo = get_db_connection();

// 1. System High-Level Metrics
$doctorCount = (int)$pdo->query("SELECT COUNT(*) FROM doctors WHERE status = 'active'")->fetchColumn();
$patientCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();

$aptStats = $pdo->query("SELECT 
    COUNT(*) AS total_appointments,
    SUM(CASE WHEN appointment_date = CURRENT_DATE() THEN 1 ELSE 0 END) AS today_appointments,
    SUM(CASE WHEN status = 'waiting' AND appointment_date = CURRENT_DATE() THEN 1 ELSE 0 END) AS today_waiting,
    SUM(CASE WHEN status = 'serving' AND appointment_date = CURRENT_DATE() THEN 1 ELSE 0 END) AS today_serving,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS total_completed,
    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS total_no_show
    FROM appointments")->fetch();

// 2. Doctor Queue Overview for Today
$docQueues = $pdo->query("SELECT d.id, d.specialization, d.room_number, u.name AS doctor_name,
    (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND appointment_date = CURRENT_DATE()) AS total_today,
    (SELECT token_number FROM appointments WHERE doctor_id = d.id AND appointment_date = CURRENT_DATE() AND status = 'serving' LIMIT 1) AS serving_token,
    (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id AND appointment_date = CURRENT_DATE() AND status = 'waiting') AS waiting_count
    FROM doctors d
    JOIN users u ON d.user_id = u.id
    WHERE d.status = 'active'
    ORDER BY u.name ASC")->fetchAll();

// 3. Recent Queue Activity Audit Logs
$recentLogs = $pdo->query("SELECT ql.*, a.appointment_number, u.name AS action_user_name, doc_u.name AS doctor_name
    FROM queue_logs ql
    JOIN appointments a ON ql.appointment_id = a.id
    JOIN users u ON ql.action_by = u.id
    JOIN doctors d ON ql.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    ORDER BY ql.created_at DESC LIMIT 6")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--slate-900); margin: 0;">Platform Health & Operations</h2>
        <span style="font-size: 0.85rem; color: var(--slate-500);">System time: <?= date('Y-m-d H:i:s'); ?> (Active Session)</span>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="doctors.php?action=add" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add New Doctor</a>
        <a href="reports.php" class="btn btn-outline"><i class="fa-solid fa-file-csv"></i> Export Reports</a>
    </div>
</div>

<!-- Key Stat Cards -->
<div class="stats-grid">
    <div class="stat-card stat-info">
        <div>
            <div class="stat-label">Active Doctors</div>
            <div class="stat-value"><?= $doctorCount; ?></div>
            <span style="font-size: 0.8rem; color: var(--slate-500);">Registered physicians</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-user-doctor"></i></div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Total Patients</div>
            <div class="stat-value"><?= $patientCount; ?></div>
            <span style="font-size: 0.8rem; color: var(--slate-500);">User accounts</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-hospital-user"></i></div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Today's Bookings</div>
            <div class="stat-value"><?= (int)($aptStats['today_appointments'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--warning-dark);"><?= (int)($aptStats['today_waiting'] ?? 0); ?> waiting in queues</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-ticket"></i></div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Completed Consults</div>
            <div class="stat-value"><?= (int)($aptStats['total_completed'] ?? 0); ?></div>
            <span style="font-size: 0.8rem; color: var(--success-dark);"><?= (int)($aptStats['total_no_show'] ?? 0); ?> total no-shows</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
    </div>
</div>

<!-- Today's Live Queues by Doctor -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <div class="card-title">
            <span class="pulse-dot"></span>
            Today's Doctor Token Queues Overview
        </div>
        <a href="appointments.php" class="btn btn-secondary btn-sm">View All Bookings</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Doctor Name</th>
                    <th>Specialization & Room</th>
                    <th>Tokens Booked Today</th>
                    <th>Serving Token</th>
                    <th>Waiting Tokens</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($docQueues)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem; color: var(--slate-400);">No active doctors found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($docQueues as $dq): ?>
                        <tr>
                            <td>
                                <strong>Dr. <?= e($dq['doctor_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background: var(--primary-light); color: var(--primary-dark);">
                                    <?= e($dq['specialization']); ?>
                                </span>
                                <span style="font-size: 0.8rem; color: var(--slate-500); margin-left: 0.5rem;"><?= e($dq['room_number']); ?></span>
                            </td>
                            <td><strong><?= (int)$dq['total_today']; ?></strong></td>
                            <td>
                                <?php if ($dq['serving_token']): ?>
                                    <span class="badge badge-serving"><span class="pulse-dot"></span> #<?= (int)$dq['serving_token']; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-waiting">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: <?= (int)$dq['waiting_count'] > 0 ? 'var(--warning-dark)' : 'var(--slate-400)'; ?>;">
                                    <?= (int)$dq['waiting_count']; ?> waiting
                                </span>
                            </td>
                            <td>
                                <a href="appointments.php?doctor_id=<?= $dq['id']; ?>" class="btn btn-outline btn-sm">
                                    <i class="fa-solid fa-eye"></i> View Queue
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Real-Time Audit Log Feed -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary);"></i>
            Latest Queue Transition Logs
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Appointment</th>
                    <th>Doctor</th>
                    <th>Token #</th>
                    <th>Transition</th>
                    <th>Triggered By</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentLogs)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: var(--slate-400);">No queue activity logs recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--slate-500);"><?= date('H:i:s M d', strtotime($log['created_at'])); ?></td>
                            <td><strong><?= e($log['appointment_number']); ?></strong></td>
                            <td>Dr. <?= e($log['doctor_name']); ?></td>
                            <td><strong class="patient-token-badge">#<?= (int)$log['token_number']; ?></strong></td>
                            <td>
                                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">
                                    <?= e($log['status_from'] ?: 'NEW'); ?> &rarr; 
                                    <span style="color: var(--primary);"><?= e($log['status_to']); ?></span>
                                </span>
                            </td>
                            <td><?= e($log['action_user_name']); ?></td>
                            <td style="font-size: 0.8rem; color: var(--slate-600);"><?= e($log['notes'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

