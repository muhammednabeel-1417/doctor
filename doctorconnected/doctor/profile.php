<?php
/**
 * DOCTOR CONNECT - Doctor Profile Editor
 */
$requiredRole = 'doctor';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Doctor Profile & Credentials';
$pageSubtitle = 'Manage your public professional profile, credentials, and account password';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);

$error = '';
$success = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!validate_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $specialization = sanitize_input($_POST['specialization'] ?? '');
        $qualification = sanitize_input($_POST['qualification'] ?? '');
        $experience = max(0, (int)($_POST['experience_years'] ?? 0));
        $roomNumber = sanitize_input($_POST['room_number'] ?? 'Room 101');
        $bio = sanitize_input($_POST['bio'] ?? '');

        if (empty($name) || empty($specialization)) {
            $error = 'Doctor name and specialization are mandatory.';
        } else {
            try {
                $pdo->beginTransaction();

                $stmt1 = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt1->execute([$name, $phone ?: null, $userId]);

                $stmt2 = $pdo->prepare("UPDATE doctors SET specialization = ?, qualification = ?, experience_years = ?, room_number = ?, bio = ? WHERE user_id = ?");
                $stmt2->execute([$specialization, $qualification, $experience, $roomNumber, $bio, $userId]);

                $_SESSION['user_name'] = $name;
                $pdo->commit();
                $success = 'Professional profile updated successfully!';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error saving profile: ' . $e->getMessage();
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!validate_csrf()) {
        $error = 'Invalid security token.';
    } else {
        $curr = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if (empty($curr) || empty($new)) {
            $error = 'Please enter both current and new passwords.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $conf) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $check = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $check->execute([$userId]);
                $user = $check->fetch();

                if ($user && verify_user_password($curr, $user['password'], $userId, $pdo)) {
                    $hash = password_hash($new, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
                    $success = 'Password successfully updated!';
                } else {
                    $error = 'Current password is incorrect.';
                }
            } catch (Throwable $e) {
                $error = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
}

// Fetch doctor details
$docStmt = $pdo->prepare("SELECT d.*, u.name, u.email, u.phone FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.user_id = ?");
$docStmt->execute([$userId]);
$docData = $docStmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 850px; margin: 0 auto;">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error); ?></div>
    <?php elseif (!empty($success)): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success); ?></div>
    <?php endif; ?>

    <!-- Profile Details Form -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-id-card-clip" style="color: var(--primary);"></i>
                Doctor Public Profile & Credentials
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Doctor Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($docData['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Login Email</label>
                        <input type="email" class="form-control" value="<?= e($docData['email']); ?>" disabled style="background: var(--slate-100); color: var(--slate-500);">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="specialization" class="form-label">Medical Specialization</label>
                        <input type="text" id="specialization" name="specialization" class="form-control" value="<?= e($docData['specialization']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="qualification" class="form-label">Qualifications / Degrees</label>
                        <input type="text" id="qualification" name="qualification" class="form-control" value="<?= e($docData['qualification']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="experience_years" class="form-label">Years of Experience</label>
                        <input type="number" id="experience_years" name="experience_years" class="form-control" value="<?= (int)$docData['experience_years']; ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label for="room_number" class="form-label">Physical Consultation Room / Wing</label>
                        <input type="text" id="room_number" name="room_number" class="form-control" value="<?= e($docData['room_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Contact Phone</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= e($docData['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio" class="form-label">Physician Biography & Special Interests</label>
                    <textarea id="bio" name="bio" class="form-control" rows="3"><?= e($docData['bio'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Update Profile Information
                </button>
            </form>
        </div>
    </div>

    <!-- Password Change -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-lock" style="color: var(--primary);"></i>
                Update Doctor Password
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Minimum 6 characters">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-key"></i> Save New Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

