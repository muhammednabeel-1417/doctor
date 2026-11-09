<?php
/**
 * DOCTOR CONNECT - Analytics & CSV Reports Generator
 */
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pdo = get_db_connection();

// Filters
$startDate = sanitize_input($_GET['start_date'] ?? date('Y-m-01')); // start of current month
$endDate = sanitize_input($_GET['end_date'] ?? date('Y-m-d'));
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$status = sanitize_input($_GET['status'] ?? '');
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

// Base Query
$sql = "SELECT a.appointment_number, a.appointment_date, a.token_number, a.estimated_time, a.status, 
        d.consultation_fee, d.specialization,
        doc_u.name AS doctor_name,
        pat_u.name AS patient_name, pat_u.email AS patient_email, pat_u.phone AS patient_phone,
        a.created_at, a.completed_at
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.id
        JOIN users doc_u ON d.user_id = doc_u.id
        JOIN users pat_u ON a.patient_id = pat_u.id
        WHERE a.appointment_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($doctorId > 0) {
    $sql .= " AND a.doctor_id = ?";
    $params[] = $doctorId;
}
if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY a.appointment_date DESC, a.token_number ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll();

// ==============================================================
// CSV EXPORT STREAMING
// ==============================================================
if ($export) {
    $filename = "doctor_connect_report_" . date('Ymd_His') . ".csv";

    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // CSV Header row
    fputcsv($output, [
        'Appointment Number',
        'Date',
        'Token Number',
        'Doctor Name',
        'Specialization',
        'Patient Name',
        'Patient Email',
        'Patient Phone',
        'Estimated Time',
        'Status',
        'Consultation Fee ($)',
        'Created Timestamp',
        'Completed Timestamp'
    ]);

    foreach ($reportData as $row) {
        fputcsv($output, [
            $row['appointment_number'],
            $row['appointment_date'],
            $row['token_number'],
            $row['doctor_name'],
            $row['specialization'],
            $row['patient_name'],
            $row['patient_email'],
            $row['patient_phone'],
            $row['estimated_time'],
            strtoupper($row['status']),
            number_format($row['consultation_fee'], 2, '.', ''),
            $row['created_at'],
            $row['completed_at'] ?: 'N/A'
        ]);
    }

    fclose($output);
    exit;
}

// Compute Aggregates for Dashboard
$totalCount = count($reportData);
$totalRevenue = 0.0;
$completedCount = 0;
$noShowCount = 0;
$waitingCount = 0;

foreach ($reportData as $r) {
    if ($r['status'] === 'completed') {
        $completedCount++;
        $totalRevenue += (float)$r['consultation_fee'];
    } elseif ($r['status'] === 'no_show') {
        $noShowCount++;
    } elseif ($r['status'] === 'waiting') {
        $waitingCount++;
    }
}

// Fetch doctors list for filter
$doctorsList = $pdo->query("SELECT d.id, u.name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.name ASC")->fetchAll();

$pageTitle = 'Reports & Data Export';
$pageSubtitle = 'Generate statistical audit summaries and download CSV records';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter & Export Toolbar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body">
        <form method="GET" action="reports.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end;">
            <div>
                <label class="form-label" style="font-size: 0.8rem;">From Date</label>
                <input type="date" name="start_date" class="form-control" style="font-size: 0.85rem;" value="<?= e($startDate); ?>" required>
            </div>

            <div>
                <label class="form-label" style="font-size: 0.8rem;">To Date</label>
                <input type="date" name="end_date" class="form-control" style="font-size: 0.85rem;" value="<?= e($endDate); ?>" required>
            </div>

            <div>
                <label class="form-label" style="font-size: 0.8rem;">Doctor</label>
                <select name="doctor_id" class="form-select" style="font-size: 0.85rem;">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctorsList as $doc): ?>
                        <option value="<?= $doc['id']; ?>" <?= $doctorId === (int)$doc['id'] ? 'selected' : ''; ?>>
                            Dr. <?= e($doc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" style="font-size: 0.8rem;">Status</label>
                <select name="status" class="form-select" style="font-size: 0.85rem;">
                    <option value="">All Statuses</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="no_show" <?= $status === 'no_show' ? 'selected' : ''; ?>>No-Show</option>
                    <option value="waiting" <?= $status === 'waiting' ? 'selected' : ''; ?>>Waiting</option>
                    <option value="serving" <?= $status === 'serving' ? 'selected' : ''; ?>>Serving</option>
                </select>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-chart-column"></i> Filter</button>
                <button type="submit" name="export" value="csv" class="btn btn-success" style="flex: 1;">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Aggregated KPI Summary -->
<div class="stats-grid">
    <div class="stat-card stat-info">
        <div>
            <div class="stat-label">Total Selected Bookings</div>
            <div class="stat-value"><?= $totalCount; ?></div>
            <span style="font-size: 0.8rem; color: var(--slate-500);">Tokens in range</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-list-ol"></i></div>
    </div>

    <div class="stat-card stat-success">
        <div>
            <div class="stat-label">Concluded Revenue</div>
            <div class="stat-value">$<?= number_format($totalRevenue, 2); ?></div>
            <span style="font-size: 0.8rem; color: var(--success-dark);"><?= $completedCount; ?> completed sessions</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
    </div>

    <div class="stat-card stat-warning">
        <div>
            <div class="stat-label">Waiting in Queues</div>
            <div class="stat-value"><?= $waitingCount; ?></div>
            <span style="font-size: 0.8rem; color: var(--warning-dark);">Unserved tokens</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-user-clock"></i></div>
    </div>

    <div class="stat-card stat-danger">
        <div>
            <div class="stat-label">No-Show Rate</div>
            <div class="stat-value"><?= $noShowCount; ?></div>
            <span style="font-size: 0.8rem; color: var(--danger-dark);"><?= $totalCount > 0 ? round(($noShowCount / $totalCount) * 100, 1) : 0; ?>% absenteeism</span>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-user-xmark"></i></div>
    </div>
</div>

<!-- Report Table Preview -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-table" style="color: var(--primary);"></i>
            Report Preview (<?= count($reportData); ?> rows)
        </div>
        <a href="<?= $_SERVER['REQUEST_URI'] . (str_contains($_SERVER['REQUEST_URI'], '?') ? '&' : '?') . 'export=csv'; ?>" class="btn btn-success btn-sm">
            <i class="fa-solid fa-download"></i> Download CSV File
        </a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Date</th>
                    <th>Token #</th>
                    <th>Doctor</th>
                    <th>Patient</th>
                    <th>Fee</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportData)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--slate-400);">No records for this timeframe.</td></tr>
                <?php else: ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><strong><?= e($row['appointment_number']); ?></strong></td>
                            <td><?= format_date($row['appointment_date']); ?></td>
                            <td><strong class="patient-token-badge">#<?= (int)$row['token_number']; ?></strong></td>
                            <td>Dr. <?= e($row['doctor_name']); ?></td>
                            <td>
                                <div><?= e($row['patient_name']); ?></div>
                                <div style="font-size: 0.775rem; color: var(--slate-500);"><?= e($row['patient_phone'] ?: $row['patient_email']); ?></div>
                            </td>
                            <td>$<?= number_format($row['consultation_fee'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?= e($row['status']); ?>">
                                    <?= ucfirst(e($row['status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

