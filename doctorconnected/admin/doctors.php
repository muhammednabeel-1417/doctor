<?php
/**
 * DOCTOR CONNECT - Admin Doctor Management (CRUD)
 */
$requiredRole = 'admin';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Manage Doctors';
$pageSubtitle = 'Create, update, and manage physician credentials and profiles';

$pdo = get_db_connection();
$action = sanitize_input($_GET['action'] ?? 'list');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error = '';
$success = '';

// Handle Delete Doctor
if ($action === 'delete' && $id > 0) {
    if (!validate_csrf()) {
        set_flash_message('danger', 'Security validation failed.');
    } else {
        try {
            // Find user_id
            $stmt = $pdo->prepare("SELECT user_id, u.name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();

            if ($doc) {
                // Deleting user cascades to doctor, schedules, appointments
                $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $del->execute([$doc['user_id']]);
                set_flash_message('success', "Doctor Dr. {$doc['name']} was deleted successfully.");
            }
        } catch (Throwable $e) {
            set_flash_message('danger', 'Error deleting doctor: ' . $e->getMessage());
        }
    }
    header('Location: doctors.php');
    exit;
}

// Handle Add / Edit Doctor Post
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $formAction = sanitize_input($_POST['form_action'] ?? '');
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $specialization = sanitize_input($_POST['specialization'] ?? '');
        $qualification = sanitize_input($_POST['qualification'] ?? '');
        $experience = max(0, (int)($_POST['experience_years'] ?? 0));
        $fee = max(0, (float)($_POST['consultation_fee'] ?? 50.00));
        $avgMinutes = max(5, (int)($_POST['avg_consultation_time'] ?? 15));
        $roomNumber = sanitize_input($_POST['room_number'] ?? 'Room 101');
        $status = sanitize_input($_POST['status'] ?? 'active');
        $bio = sanitize_input($_POST['bio'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($specialization)) {
            $error = 'Name, email, and specialization are mandatory fields.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($formAction === 'add') {
                    // Check email uniqueness
                    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $chk->execute([$email]);
                    if ($chk->fetch()) {
                        throw new Exception("A user with this email address already exists.");
                    }

                    if (empty($password)) {
                        $password = 'Doctor@123'; // Default fallback
                    }
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user
                    $insUser = $pdo->prepare("INSERT INTO users (name, email, password, role, phone) VALUES (?, ?, ?, 'doctor', ?)");
                    $insUser->execute([$name, $email, $hash, $phone ?: null]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Insert doctor
                    $insDoc = $pdo->prepare("INSERT INTO doctors 
                        (user_id, specialization, qualification, experience_years, consultation_fee, avg_consultation_time, bio, room_number, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insDoc->execute([$newUserId, $specialization, $qualification, $experience, $fee, $avgMinutes, $bio, $roomNumber, $status]);
                    $newDocId = (int)$pdo->lastInsertId();

                    // Seed default Monday-Friday schedule
                    $schedIns = $pdo->prepare("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, max_tokens, is_active) VALUES (?, ?, '09:00:00', '13:00:00', 25, 1)");
                    foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
                        $schedIns->execute([$newDocId, $day]);
                    }

                    $pdo->commit();
                    set_flash_message('success', "Doctor Dr. {$name} created successfully with default weekly schedule!");
                    header('Location: doctors.php');
                    exit;

                } elseif ($formAction === 'edit' && $id > 0) {
                    // Lookup existing doctor
                    $docStmt = $pdo->prepare("SELECT user_id FROM doctors WHERE id = ?");
                    $docStmt->execute([$id]);
                    $existingDoc = $docStmt->fetch();

                    if (!$existingDoc) {
                        throw new Exception("Doctor record not found.");
                    }
                    $targetUserId = (int)$existingDoc['user_id'];

                    // Check email uniqueness if changed
                    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $chk->execute([$email, $targetUserId]);
                    if ($chk->fetch()) {
                        throw new Exception("Email is already used by another account.");
                    }

                    // Update user
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $updUser = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, phone = ? WHERE id = ?");
                        $updUser->execute([$name, $email, $hash, $phone ?: null, $targetUserId]);
                    } else {
                        $updUser = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
                        $updUser->execute([$name, $email, $phone ?: null, $targetUserId]);
                    }

                    // Update doctor
                    $updDoc = $pdo->prepare("UPDATE doctors SET 
                        specialization = ?, qualification = ?, experience_years = ?, consultation_fee = ?,
                        avg_consultation_time = ?, bio = ?, room_number = ?, status = ?
                        WHERE id = ?");
                    $updDoc->execute([$specialization, $qualification, $experience, $fee, $avgMinutes, $bio, $roomNumber, $status, $id]);

                    $pdo->commit();
                    set_flash_message('success', "Doctor Dr. {$name} profile updated successfully!");
                    header('Location: doctors.php');
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

// Data fetching for Edit Mode
$editData = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $pdo->prepare("SELECT d.*, u.name, u.email, u.phone FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

// Fetch all doctors for list mode
$search = sanitize_input($_GET['search'] ?? '');
$listSql = "SELECT d.*, u.name, u.email, u.phone,
    (SELECT COUNT(*) FROM appointments WHERE doctor_id = d.id) AS total_consultations
    FROM doctors d
    JOIN users u ON d.user_id = u.id";
$params = [];
if (!empty($search)) {
    $listSql .= " WHERE u.name LIKE ? OR d.specialization LIKE ? OR d.qualification LIKE ?";
    $term = "%{$search}%";
    $params = [$term, $term, $term];
}
$listSql .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$doctorsList = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error); ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- ADD / EDIT DOCTOR FORM -->
    <div style="max-width: 850px; margin: 0 auto;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-user-doctor" style="color: var(--primary);"></i>
                    <?= $action === 'add' ? 'Register New Doctor' : 'Edit Physician Profile: Dr. ' . e($editData['name'] ?? ''); ?>
                </div>
                <a href="doctors.php" class="btn btn-secondary btn-sm">Cancel & Return</a>
            </div>

            <div class="card-body">
                <form method="POST" action="doctors.php?action=<?= $action; ?><?= $id ? '&id=' . $id : ''; ?>">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="form_action" value="<?= $action; ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name" class="form-label">Doctor Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" 
                                value="<?= e($editData['name'] ?? ''); ?>" placeholder="e.g. Sarah Jenkins" required>
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Login Email <span style="color:var(--danger)">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" 
                                value="<?= e($editData['email'] ?? ''); ?>" placeholder="doctor@doctorconnect.com" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                Password <?= $action === 'edit' ? '<small style="color:var(--slate-400)">(Leave blank to keep current)</small>' : '<span style="color:var(--danger)">*</span>'; ?>
                            </label>
                            <input type="password" id="password" name="password" class="form-control" 
                                placeholder="<?= $action === 'edit' ? '••••••••' : 'Minimum 6 chars (default: Doctor@123)'; ?>" <?= $action === 'add' ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="phone" class="form-label">Contact Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                value="<?= e($editData['phone'] ?? ''); ?>" placeholder="+1 555-0123">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="specialization" class="form-label">Specialization <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="specialization" name="specialization" class="form-control" 
                                value="<?= e($editData['specialization'] ?? ''); ?>" placeholder="e.g. Cardiology, Neurology..." required>
                        </div>
                        <div class="form-group">
                            <label for="qualification" class="form-label">Qualifications</label>
                            <input type="text" id="qualification" name="qualification" class="form-control" 
                                value="<?= e($editData['qualification'] ?? ''); ?>" placeholder="e.g. MD, FACC, Harvard Medical" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="experience_years" class="form-label">Years of Experience</label>
                            <input type="number" id="experience_years" name="experience_years" class="form-control" 
                                value="<?= (int)($editData['experience_years'] ?? 5); ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label for="consultation_fee" class="form-label">Consultation Fee ($ USD)</label>
                            <input type="number" step="0.01" id="consultation_fee" name="consultation_fee" class="form-control" 
                                value="<?= number_format($editData['consultation_fee'] ?? 50.00, 2, '.', ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="avg_consultation_time" class="form-label">Avg Duration (Mins)</label>
                            <input type="number" id="avg_consultation_time" name="avg_consultation_time" class="form-control" 
                                value="<?= (int)($editData['avg_consultation_time'] ?? 15); ?>" min="5" max="60" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="room_number" class="form-label">Room / Desk</label>
                            <input type="text" id="room_number" name="room_number" class="form-control" 
                                value="<?= e($editData['room_number'] ?? 'Room 101'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active" <?= ($editData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?= ($editData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio" class="form-label">Physician Biography</label>
                        <textarea id="bio" name="bio" class="form-control" rows="3"><?= e($editData['bio'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-floppy-disk"></i> <?= $action === 'add' ? 'Create Doctor Profile' : 'Save Changes'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- LIST DOCTORS TABLE -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="padding: 1rem 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <form method="GET" action="doctors.php" style="display: flex; gap: 0.5rem; flex: 1; max-width: 450px;">
                    <input type="text" name="search" class="form-control" placeholder="Search by doctor name or specialization..." value="<?= e($search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                    <?php if (!empty($search)): ?>
                        <a href="doctors.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>

                <a href="doctors.php?action=add" class="btn btn-primary">
                    <i class="fa-solid fa-user-plus"></i> Add New Doctor
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-user-doctor" style="color: var(--primary);"></i>
                Physician Directory (<?= count($doctorsList); ?> doctors)
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Doctor Name</th>
                        <th>Specialization & Qualification</th>
                        <th>Consultation Fee</th>
                        <th>Avg Mins</th>
                        <th>Status</th>
                        <th>Consultations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($doctorsList)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--slate-400);">No doctors found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($doctorsList as $doc): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: var(--slate-900);">Dr. <?= e($doc['name']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--slate-500);"><?= e($doc['email']); ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--primary-light); color: var(--primary-dark);">
                                        <?= e($doc['specialization']); ?>
                                    </span>
                                    <div style="font-size: 0.775rem; color: var(--slate-500); margin-top: 0.2rem;"><?= e($doc['qualification']); ?></div>
                                </td>
                                <td><strong>$<?= number_format($doc['consultation_fee'], 2); ?></strong></td>
                                <td><?= (int)$doc['avg_consultation_time']; ?>m</td>
                                <td>
                                    <span class="badge badge-<?= $doc['status'] === 'active' ? 'serving' : 'cancelled'; ?>">
                                        <?= ucfirst(e($doc['status'])); ?>
                                    </span>
                                </td>
                                <td><strong><?= (int)$doc['total_consultations']; ?></strong></td>
                                <td>
                                    <div style="display: flex; gap: 0.35rem;">
                                        <a href="doctors.php?action=edit&id=<?= $doc['id']; ?>" class="btn btn-outline btn-sm" title="Edit Doctor">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="schedules.php?doctor_id=<?= $doc['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Schedule">
                                            <i class="fa-regular fa-clock"></i>
                                        </a>
                                        <form method="POST" action="doctors.php?action=delete&id=<?= $doc['id']; ?>" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete Dr. <?= e($doc['name']); ?>? All associated appointments will be removed.')">
                                            <?= csrf_field(); ?>
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
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

