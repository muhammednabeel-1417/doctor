<?php
/**
 * DOCTOR CONNECT - Patient Registration
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    header('Location: ../patient/dashboard.php');
    exit;
}

$error = '';
$success = '';

$name = '';
$email = '';
$phone = '';
$gender = 'male';
$dob = '';
$bloodGroup = 'O+';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $gender = sanitize_input($_POST['gender'] ?? 'male');
        $dob = sanitize_input($_POST['date_of_birth'] ?? '');
        $bloodGroup = sanitize_input($_POST['blood_group'] ?? 'O+');
        $address = sanitize_input($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Please fill in all mandatory fields (Name, Email, and Password).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo = get_db_connection();

                // Check if email already registered
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $error = 'An account with this email address already exists. Please sign in instead.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    $insertStmt = $pdo->prepare("INSERT INTO users 
                        (name, email, password, role, phone, gender, date_of_birth, blood_group, address) 
                        VALUES (?, ?, ?, 'patient', ?, ?, ?, ?, ?)");
                    $insertStmt->execute([
                        $name,
                        $email,
                        $hashedPassword,
                        $phone ?: null,
                        $gender,
                        $dob ?: null,
                        $bloodGroup ?: null,
                        $address ?: null
                    ]);

                    $newUserId = (int)$pdo->lastInsertId();

                    // Insert welcome notification
                    add_notification(
                        $pdo, 
                        $newUserId, 
                        'Welcome to DOCTOR CONNECT!', 
                        'Your account has been created. You can now browse our certified doctors, book race-safe consultation tokens, and join secure video meetings.', 
                        'success'
                    );

                    set_flash_message('success', 'Account registered successfully! You can now log in.');
                    header('Location: login.php');
                    exit;
                }
            } catch (Throwable $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Patient Account - <?= APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f7ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }
        .auth-container {
            width: 100%;
            max-width: 620px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .auth-header {
            padding: 2rem 2rem 1.25rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        .auth-body {
            padding: 2rem;
        }
        .auth-footer {
            padding: 1.25rem 2rem;
            background: var(--slate-50);
            border-top: 1px solid var(--border-color);
            text-align: center;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <a href="../index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div class="brand-icon" style="width: 42px; height: 42px; font-size: 1.35rem;">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
        </a>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--slate-900); margin: 0;">Create Patient Account</h2>
        <p style="font-size: 0.875rem; color: var(--slate-500); margin-top: 0.25rem;">Join DOCTOR CONNECT for token-based online medical consultations</p>
    </div>

    <div class="auth-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <?= csrf_field(); ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="form-label">Full Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="<?= e($name); ?>" placeholder="e.g. John Doe" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email Address <span style="color:var(--danger)">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= e($email); ?>" placeholder="john@example.com" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Password <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password <span style="color:var(--danger)">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-type password" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone" class="form-label">Contact Phone</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?= e($phone); ?>" placeholder="+1 555-0199">
                </div>
                <div class="form-group">
                    <label for="gender" class="form-label">Gender</label>
                    <select id="gender" name="gender" class="form-select">
                        <option value="male" <?= $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?= $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?= $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?= e($dob); ?>">
                </div>
                <div class="form-group">
                    <label for="blood_group" class="form-label">Blood Group</label>
                    <select id="blood_group" name="blood_group" class="form-select">
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= $bloodGroup === $bg ? 'selected' : ''; ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="address" class="form-label">Residential Address</label>
                <textarea id="address" name="address" class="form-control" placeholder="Street, City, State, ZIP"><?= e($address); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 1rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-user-plus"></i> Complete Patient Registration
            </button>
        </form>
    </div>

    <div class="auth-footer">
        Already registered? <a href="login.php" style="font-weight: 700;">Sign In Here</a>
    </div>
</div>

</body>
</html>

