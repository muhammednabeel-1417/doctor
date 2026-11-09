<?php
/**
 * DOCTOR CONNECT - 1-Click Database Setup & Diagnostics Tool
 * Run this page in your browser (e.g. http://localhost/doctorconnected/setup.php)
 * to automatically initialize the database, tables, and test accounts.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$stepLogs = [];
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_setup') {
    try {
        // Step 1: Connect to MySQL server without selecting database
        $pdoServer = get_db_connection(false);
        $stepLogs[] = ['status' => 'ok', 'msg' => 'Connected to MySQL server successfully at ' . DB_HOST . ':' . DB_PORT];

        // Step 2: Create database if not exists
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $stepLogs[] = ['status' => 'ok', 'msg' => "Database `" . DB_NAME . "` created or verified."];

        // Step 3: Connect to specific database
        $pdo = get_db_connection(true);
        $stepLogs[] = ['status' => 'ok', 'msg' => "Connected to `" . DB_NAME . "` database."];

        // Step 4: Load and execute SQL schema
        $sqlFile = __DIR__ . '/database/doctor_connect.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found at " . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);

        // Execute schema in blocks
        // Temporarily disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        // Execute the entire SQL script
        $pdo->exec($sql);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // Step 5: Upgrade seed passwords to true native PHP bcrypt hashes
        $adminHash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $docHash = password_hash('Doctor@123', PASSWORD_DEFAULT);
        $patHash = password_hash('Patient@123', PASSWORD_DEFAULT);

        $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@doctorconnect.com'")->execute([$adminHash]);
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'doctor'")->execute([$docHash]);
        $pdo->prepare("UPDATE users SET password = ? WHERE role = 'patient'")->execute([$patHash]);

        $stepLogs[] = ['status' => 'ok', 'msg' => "Executed tables, constraints, sequences, and updated password hashes using PHP password_hash()."];
        $stepLogs[] = ['status' => 'ok', 'msg' => "4 Doctors, 7-day consultation schedules, and sample patient appointments seeded successfully."];

        $success = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $stepLogs[] = ['status' => 'error', 'msg' => $e->getMessage()];
    }
}

// Check current installation state
$isInstalled = false;
$tableCount = 0;
try {
    $testPdo = get_db_connection(true);
    $stmt = $testPdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->fetch()) {
        $stmtAll = $testPdo->query("SHOW TABLES");
        $tableCount = count($stmtAll->fetchAll());
        $isInstalled = ($tableCount >= 7);
    }
} catch (Throwable $e) {
    // Database or connection not ready yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup & Diagnostics - DOCTOR CONNECT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f0f7ff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .setup-card { background: #ffffff; border-radius: 1rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04); max-width: 680px; width: 100%; border: 1px solid #e2e8f0; overflow: hidden; }
        .setup-header { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; padding: 2rem; text-align: center; }
        .setup-body { padding: 2rem; }
        .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .log-box { background: #0f172a; color: #f8fafc; border-radius: 0.5rem; padding: 1rem; font-family: monospace; font-size: 0.85rem; margin: 1rem 0; max-height: 200px; overflow-y: auto; }
        .account-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .account-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-hospital-user"></i></div>
        <h1 style="font-size: 1.6rem; font-weight: 800; margin: 0; color: #fff;">DOCTOR CONNECT</h1>
        <p style="margin: 0.25rem 0 0; opacity: 0.9; font-size: 0.95rem;">Database Installation & Environment Diagnostics</p>
    </div>

    <div class="setup-body">
        <?php if ($success): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 1.5rem;"></i>
                <div>
                    <h3 style="margin: 0; color: #065f46; font-size: 1rem;">Setup Completed Successfully!</h3>
                    <p style="margin: 0.25rem 0 0; color: #047857; font-size: 0.875rem;">Database, tables, and credentials are initialized and ready for XAMPP.</p>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-circle-exclamation" style="color: #dc2626; font-size: 1.5rem;"></i>
                <div>
                    <h3 style="margin: 0; color: #991b1b; font-size: 1rem;">Setup Encountered an Issue</h3>
                    <p style="margin: 0.25rem 0 0; color: #b91c1c; font-size: 0.875rem;"><?= e($error); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e293b; display: flex; align-items: center; justify-content: space-between;">
            Environment Status
            <?php if ($isInstalled): ?>
                <span class="status-badge badge-success"><i class="fa-solid fa-check"></i> Database Installed (<?= $tableCount ?> tables)</span>
            <?php else: ?>
                <span class="status-badge badge-warning"><i class="fa-solid fa-clock"></i> Not Installed Yet</span>
            <?php endif; ?>
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
            <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                <span style="color: #64748b;">PHP Version:</span> <strong><?= phpversion(); ?></strong>
            </div>
            <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                <span style="color: #64748b;">PDO MySQL:</span> 
                <strong><?= extension_loaded('pdo_mysql') ? '<span style="color:#16a34a;">Available</span>' : '<span style="color:#dc2626;">Missing</span>'; ?></strong>
            </div>
            <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                <span style="color: #64748b;">MySQL Host:</span> <strong><?= DB_HOST . ':' . DB_PORT; ?></strong>
            </div>
            <div style="background: #f8fafc; padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid #e2e8f0; font-size: 0.875rem;">
                <span style="color: #64748b;">Database Name:</span> <strong><?= DB_NAME; ?></strong>
            </div>
        </div>

        <?php if (!empty($stepLogs)): ?>
            <div class="log-box">
                <?php foreach ($stepLogs as $log): ?>
                    <div style="color: <?= $log['status'] === 'ok' ? '#4ade80' : '#f87171'; ?>; margin-bottom: 0.25rem;">
                        [<?= $log['status'] === 'ok' ? 'SUCCESS' : 'ERROR'; ?>] <?= e($log['msg']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="run_setup">
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.875rem; font-size: 1rem; justify-content: center;">
                <i class="fa-solid fa-bolt"></i> <?= $isInstalled ? 'Re-Initialize / Reset Database' : 'Initialize Database & Seed Data Now'; ?>
            </button>
        </form>

        <div style="margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
            <h4 style="margin: 0 0 0.5rem; font-size: 0.95rem; color: #334155;">Default Login Credentials:</h4>
            <div class="account-grid">
                <div class="account-card">
                    <strong style="color: #0284c7;"><i class="fa-solid fa-shield-halved"></i> Admin</strong><br>
                    <span style="font-size: 0.8rem; color: #64748b;">admin@doctorconnect.com</span><br>
                    <code style="background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 0.2rem;">Admin@123</code>
                </div>
                <div class="account-card">
                    <strong style="color: #0284c7;"><i class="fa-solid fa-user-doctor"></i> Doctor</strong><br>
                    <span style="font-size: 0.8rem; color: #64748b;">sarah.jenkins@doctorconnect.com</span><br>
                    <code style="background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 0.2rem;">Doctor@123</code>
                </div>
                <div class="account-card">
                    <strong style="color: #0284c7;"><i class="fa-solid fa-hospital-user"></i> Patient</strong><br>
                    <span style="font-size: 0.8rem; color: #64748b;">john.doe@example.com</span><br>
                    <code style="background: #e2e8f0; padding: 0.1rem 0.3rem; border-radius: 0.2rem;">Patient@123</code>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: center;">
            <a href="index.php" class="btn btn-outline" style="text-decoration: none;"><i class="fa-solid fa-house"></i> Home Page</a>
            <a href="auth/login.php" class="btn btn-primary" style="text-decoration: none;"><i class="fa-solid fa-arrow-right-to-bracket"></i> Go to Login</a>
        </div>
    </div>
</div>

</body>
</html>

