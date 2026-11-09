<?php
/**
 * DOCTOR CONNECT - Doctor Live Queue Management Screen
 */
$requiredRole = 'doctor';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Live Token Queue Monitor';
$pageSubtitle = 'Real-time patient queue flow with 3-second live synchronization';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);

$docStmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
$docStmt->execute([$userId]);
$doctor = $docStmt->fetch();

if (!$doctor) {
    echo "Doctor record not found.";
    exit;
}
$doctorId = (int)$doctor['id'];
$date = isset($_GET['date']) ? sanitize_input($_GET['date']) : date('Y-m-d');

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Toolbar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body" style="padding: 1.25rem 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <form method="GET" action="queue.php" style="display: flex; align-items: center; gap: 0.75rem;">
                <label for="date" style="font-size: 0.875rem; font-weight: 700; color: var(--slate-700);">Queue Date:</label>
                <input type="date" id="date" name="date" class="form-control" style="width: auto; padding: 0.4rem 0.75rem;" value="<?= e($date); ?>" onchange="this.form.submit()">
            </form>

            <div style="display: flex; gap: 0.75rem;">
                <button type="button" class="btn btn-primary" onclick="callNextPatient(<?= $doctorId; ?>, '<?= $date; ?>', '<?= csrf_token(); ?>')">
                    <i class="fa-solid fa-bullhorn"></i> Call Next Waiting Patient
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Live Metric Cards -->
<div class="live-queue-board">
    <div class="serving-hero-card">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; opacity: 0.9;">
            <span class="pulse-dot" style="background-color: #ffffff;"></span>
            <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase;">NOW SERVING</span>
        </div>
        <div class="token-number" id="live-serving-token">
            --
        </div>
        <div style="font-size: 0.85rem; opacity: 0.85;">
            Active In Consultation
        </div>
    </div>

    <div class="next-hero-card">
        <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--slate-500);">
            NEXT IN QUEUE
        </span>
        <div class="token-number" id="live-next-token">
            --
        </div>
        <div style="font-size: 0.85rem; color: var(--slate-500);">
            Ready to be called
        </div>
    </div>

    <div class="card" style="display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 2rem; text-align: center;">
        <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--slate-500);">
            PATIENTS WAITING
        </span>
        <div style="font-size: 3.5rem; font-weight: 800; color: var(--warning-dark); line-height: 1; margin: 0.75rem 0;" id="live-waiting-count">
            --
        </div>
        <div style="font-size: 0.85rem; color: var(--slate-500);">
            Remaining tokens for <?= format_date($date); ?>
        </div>
    </div>
</div>

<!-- Queue Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fa-solid fa-list-check" style="color: var(--primary);"></i>
            Token Roster for <?= format_date($date); ?>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--slate-500);">
            <span class="pulse-dot"></span> Live updates every 3s
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Token #</th>
                    <th>Patient Name & Phone</th>
                    <th>Est. Time</th>
                    <th>Status</th>
                    <th>Reported Symptoms</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="doctor-queue-body">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--slate-400);">
                        <i class="fa-solid fa-circle-notch fa-spin"></i> Loading live queue records...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initLiveQueuePoller({
        doctorId: <?= (int)$doctorId; ?>,
        date: '<?= e($date); ?>',
        isDoctorView: true
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

