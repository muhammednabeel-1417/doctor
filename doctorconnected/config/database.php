<?php
/**
 * DOCTOR CONNECT - Database Configuration & Connection Handler
 * Uses PHP Data Objects (PDO) for secure, prepared SQL execution.
 */

// Database credentials (Standard XAMPP default settings)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'doctor_connect');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Application Constants
define('APP_NAME', 'DOCTOR CONNECT');
define('APP_TAGLINE', 'Token-Based Online Doctor Consultation Platform');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'));

/**
 * Get or create the singleton PDO connection instance
 * 
 * @param bool $selectDatabase Whether to select the doctor_connect database (false during setup)
 * @return PDO
 * @throws PDOException
 */
function get_db_connection(bool $selectDatabase = true): PDO {
    static $pdo = null;

    if ($pdo !== null && $selectDatabase) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
    if ($selectDatabase) {
        $dsn .= ";dbname=" . DB_NAME;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        if ($selectDatabase) {
            $pdo = $instance;
        }
        return $instance;
    } catch (PDOException $e) {
        // If the database doesn't exist yet, provide helpful guidance or redirect to setup
        if ($selectDatabase && ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database'))) {
            // Database not found: check if setup file exists
            $scriptPath = $_SERVER['PHP_SELF'] ?? '';
            if (!str_contains($scriptPath, 'setup.php')) {
                header("Location: " . dirname($scriptPath) . "/setup.php?error=db_missing");
                exit;
            }
        }
        throw new PDOException("Database connection error: " . $e->getMessage(), (int)$e->getCode());
    }
}

