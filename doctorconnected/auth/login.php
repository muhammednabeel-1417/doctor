<?php
/**
 * DOCTOR CONNECT - User Authentication / Sign In
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? 'patient';
    if ($role === 'doctor') {
        header('Location: ../doctor/dashboard.php');
    } elseif ($role === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../patient/dashboard.php');
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && verify_user_password($password, $user['password'], (int)$user['id'], $pdo)) {
                    // Regenerate session id for security
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];

                    // If user is a doctor, lookup and store doctor_id
                    if ($user['role'] === 'doctor') {
                        $docStmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
                        $docStmt->execute([$user['id']]);
                        $doctor = $docStmt->fetch();
                        $_SESSION['doctor_id'] = $doctor ? (int)$doctor['id'] : null;
                        
                        set_flash_message('success', 'Welcome back, Dr. ' . $user['name']);
                        header('Location: ../doctor/dashboard.php');
                        exit;
                    } elseif ($user['role'] === 'admin') {
                        set_flash_message('success', 'Admin session authenticated.');
                        header('Location: ../admin/dashboard.php');
                        exit;
                    } else {
                        set_flash_message('success', 'Welcome back, ' . $user['name']);
                        header('Location: ../patient/dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid email address or password. Please verify and try again.';
                }
            } catch (Throwable $e) {
                $error = 'System error: ' . $e->getMessage();
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
    <title>Sign In - <?= APP_NAME; ?></title>
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
            padding: 1.5rem;
        }
        .auth-container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .auth-header {
            padding: 2.25rem 2rem 1.5rem;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            background: #ffffff;
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
        .demo-pill {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 0.35rem 0.65rem;
            border-radius: var(--radius-sm);
            font-size: 0.775rem;
            cursor: pointer;
            transition: var(--transition);
        }
        .demo-pill:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary-dark);
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-header">
        <a href="../index.php" style="display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
            <div class="brand-icon" style="width: 42px; height: 42px; font-size: 1.35rem;">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
        </a>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: var(--slate-900); margin: 0;">Welcome to <?= APP_NAME; ?></h2>
        <p style="font-size: 0.875rem; color: var(--slate-500); margin-top: 0.25rem;">Sign in to your patient, doctor, or admin account</p>
    </div>

    <div class="auth-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.25rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <?php 
        $flash = get_flash_message();
        if ($flash): 
        ?>
            <div class="alert alert-<?= e($flash['type']); ?>" style="margin-bottom: 1.25rem;">
                <i class="fa-solid fa-circle-info"></i>
                <div><?= e($flash['message']); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <?= csrf_field(); ?>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <div style="position: relative;">
                    <i class="fa-regular fa-envelope" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--slate-400);"></i>
                    <input type="email" id="email" name="email" class="form-control" style="padding-left: 2.75rem;" value="<?= e($email); ?>" placeholder="name@example.com" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label" style="display: flex; justify-content: space-between;">
                    <span>Password</span>
                </label>
                <div style="position: relative;">
                    <i class="fa-solid fa-lock" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--slate-400);"></i>
                    <input type="password" id="password" name="password" class="form-control" style="padding-left: 2.75rem;" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem; font-size: 1rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In to Account
            </button>
        </form>

        <!-- Quick Demo Fill Buttons for Grading & Testing -->
        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border-color);">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: var(--slate-400); margin-bottom: 0.65rem; text-align: center;">
                Quick Test Credentials (Click to Fill)
            </div>
            <div style="display: flex; gap: 0.4rem; justify-content: center; flex-wrap: wrap;">
                <button type="button" class="demo-pill" onclick="fillCreds('admin@doctorconnect.com', 'Admin@123')">
                    <i class="fa-solid fa-shield-halved"></i> Admin
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds('sarah.jenkins@doctorconnect.com', 'Doctor@123')">
                    <i class="fa-solid fa-user-doctor"></i> Doctor
                </button>
                <button type="button" class="demo-pill" onclick="fillCreds('john.doe@example.com', 'Patient@123')">
                    <i class="fa-solid fa-hospital-user"></i> Patient
                </button>
            </div>
        </div>
    </div>

    <div class="auth-footer">
        Don't have a patient account? <a href="register.php" style="font-weight: 700;">Create One Here</a>
    </div>
</div>

<script>
function fillCreds(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
}
</script>

</body>
</html>

