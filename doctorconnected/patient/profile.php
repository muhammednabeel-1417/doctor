<?php
/**
 * DOCTOR CONNECT - Patient Profile Management
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Patient Profile';
$pageSubtitle = 'Manage your personal medical profile and security settings';

$pdo = get_db_connection();
$userId = (int)($currentUser['id'] ?? 0);
$error = '';
$success = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!validate_csrf()) {
        $error = 'Security token invalid.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? 'male');
        $dob = sanitize_input($_POST['date_of_birth'] ?? '');
        $bloodGroup = sanitize_input($_POST['blood_group'] ?? 'O+');
        $address = sanitize_input($_POST['address'] ?? '');

        if (empty($name)) {
            $error = 'Name cannot be blank.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, gender = ?, date_of_birth = ?, blood_group = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone ?: null, $gender, $dob ?: null, $bloodGroup ?: null, $address ?: null, $userId]);
                $_SESSION['user_name'] = $name;
                $success = 'Profile details updated successfully!';
            } catch (Throwable $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!validate_csrf()) {
        $error = 'Security token invalid.';
    } else {
        $currPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';

        if (empty($currPass) || empty($newPass)) {
            $error = 'Please provide both current and new passwords.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confPass) {
            $error = 'New passwords do not match.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();

                if ($user && verify_user_password($currPass, $user['password'], $userId, $pdo)) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $upd->execute([$newHash, $userId]);
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Current password is incorrect.';
                }
            } catch (Throwable $e) {
                $error = 'Error changing password: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error); ?></div>
    <?php elseif (!empty($success)): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success); ?></div>
    <?php endif; ?>

    <!-- Personal Profile Details -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-user-pen" style="color: var(--primary);"></i>
                Personal Information
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="profile.php">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= e($userData['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address (Read-Only)</label>
                        <input type="email" class="form-control" value="<?= e($userData['email']); ?>" disabled style="background: var(--slate-100); color: var(--slate-500);">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?= e($userData['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender</label>
                        <select id="gender" name="gender" class="form-select">
                            <option value="male" <?= ($userData['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?= ($userData['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?= ($userData['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= e($userData['date_of_birth'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="blood_group" class="form-label">Blood Group</label>
                        <select id="blood_group" name="blood_group" class="form-select">
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                                <option value="<?= $bg ?>" <?= ($userData['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>><?= $bg ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address" class="form-label">Residential Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2"><?= e($userData['address'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Security & Password -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fa-solid fa-lock" style="color: var(--primary);"></i>
                Change Password
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
                    <i class="fa-solid fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

