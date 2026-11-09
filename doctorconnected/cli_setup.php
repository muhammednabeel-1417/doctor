<?php
/**
 * DOCTOR CONNECT - CLI Database Setup & Diagnostics Runner
 * Run directly in Terminal / PowerShell:
 * php cli_setup.php  OR  C:\xampp\php\php.exe cli_setup.php
 */

require_once __DIR__ . '/config/database.php';

echo "\n";
echo "============================================================\n";
echo "  DOCTOR CONNECT - Terminal / PowerShell Database Setup     \n";
echo "============================================================\n\n";

try {
    // 1. Connect to MySQL server
    echo "[1/5] Connecting to MySQL server at " . DB_HOST . ":" . DB_PORT . " (User: " . DB_USER . ")...\n";
    $pdoServer = get_db_connection(false);
    echo "      -> Connected successfully!\n\n";

    // 2. Create database
    echo "[2/5] Creating database `" . DB_NAME . "` if not exists...\n";
    $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "      -> Database `" . DB_NAME . "` ready!\n\n";

    // 3. Connect to specific database
    echo "[3/5] Connecting to `" . DB_NAME . "` database...\n";
    $pdo = get_db_connection(true);
    echo "      -> Database selected!\n\n";

    // 4. Load schema and execute
    $sqlFile = __DIR__ . '/database/doctor_connect.sql';
    echo "[4/5] Loading schema and seeds from " . basename($sqlFile) . "...\n";
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at " . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec($sql);
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "      -> All 7 tables and initial constraints executed!\n\n";

    // 5. Update password hashes
    echo "[5/5] Encrypting passwords using PHP native password_hash()...\n";
    $adminHash = password_hash('Admin@123', PASSWORD_DEFAULT);
    $docHash = password_hash('Doctor@123', PASSWORD_DEFAULT);
    $patHash = password_hash('Patient@123', PASSWORD_DEFAULT);

    $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@doctorconnect.com'")->execute([$adminHash]);
    $pdo->prepare("UPDATE users SET password = ? WHERE role = 'doctor'")->execute([$docHash]);
    $pdo->prepare("UPDATE users SET password = ? WHERE role = 'patient'")->execute([$patHash]);
    echo "      -> Admin, Doctor, and Patient passwords secured!\n\n";

    echo "============================================================\n";
    echo "  SUCCESS: DOCTOR CONNECT Database is completely initialized!\n";
    echo "============================================================\n\n";
    echo "Default Login Credentials:\n";
    echo "  - Administrator : admin@doctorconnect.com       / Admin@123\n";
    echo "  - Doctor        : sarah.jenkins@doctorconnect.com / Doctor@123\n";
    echo "  - Patient       : john.doe@example.com          / Patient@123\n\n";
    echo "Next: Start the web server with:\n";
    echo "  C:\\xampp\\php\\php.exe -S localhost:8000\n";
    echo "  Or run: .\\run.ps1\n\n";

} catch (Throwable $e) {
    echo "\n[ERROR] Setup failed: " . $e->getMessage() . "\n";
    echo "Troubleshooting Tip:\n";
    echo "  - Make sure MySQL is running in XAMPP Control Panel.\n";
    echo "  - Check credentials in config/database.php if your MySQL uses a password.\n\n";
    exit(1);
}

