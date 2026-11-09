<?php
/**
 * DOCTOR CONNECT - Common Helper Functions & Security Utilities
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configure session security parameters before starting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Sanitize text input
 */
function sanitize_input(?string $data): string
{
    if ($data === null)
        return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Output escaping for XSS prevention
 */
function e(?string $data): string
{
    return htmlspecialchars((string) ($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden HTML CSRF input field
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate CSRF token from POST request
 */
function validate_csrf(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if a user is currently authenticated
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get current authenticated user session data
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? '',
        'doctor_id' => $_SESSION['doctor_id'] ?? null
    ];
}

/**
 * Check if the user has a specific role
 */
function has_role(string|array $role): bool
{
    if (!is_logged_in()) {
        return false;
    }
    $currentRole = $_SESSION['user_role'] ?? '';
    if (is_array($role)) {
        return in_array($currentRole, $role, true);
    }
    return $currentRole === $role;
}

/**
 * Enforce role-based access control (RBAC)
 */
function require_role(string|array $allowedRoles): void
{
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please log in to access this page.');
        header('Location: ../auth/login.php');
        exit;
    }

    if (!has_role($allowedRoles)) {
        set_flash_message('danger', 'Unauthorized access! You do not have permission for that area.');
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
}

/**
 * Set a flash notification message
 */
function set_flash_message(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message
 */
function get_flash_message(): ?array
{
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}

/**
 * Generate a cryptographically secure, unpredictable Jitsi Meet room name
 */
function generate_meeting_room(): string
{
    $prefix = 'dc_room_';
    $randomHex = bin2hex(random_bytes(10));
    $timestamp = time();
    return $prefix . $randomHex . '_' . $timestamp;
}

/**
 * Calculate estimated appointment time
 * Formula: session_start + (token_number - 1) * average_consultation_minutes
 */
function calculate_estimated_time(string $sessionStartTime, int $tokenNumber, int $avgMinutes): string
{
    $startSeconds = strtotime("1970-01-01 " . $sessionStartTime);
    $offsetSeconds = max(0, $tokenNumber - 1) * ($avgMinutes * 60);
    $estimatedTimestamp = $startSeconds + $offsetSeconds;
    return date('H:i:s', $estimatedTimestamp);
}

/**
 * Format time to 12-hour AM/PM string
 */
function format_time(?string $timeStr): string
{
    if (empty($timeStr))
        return 'N/A';
    return date('h:i A', strtotime($timeStr));
}

/**
 * Format date to human friendly representation
 */
function format_date(?string $dateStr): string
{
    if (empty($dateStr))
        return 'N/A';
    return date('M d, Y', strtotime($dateStr));
}

/**
 * Send JSON response and exit
 */
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Insert a user notification
 */
function add_notification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): bool
{
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$userId, $title, $message, $type]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log a queue transition event
 */
function log_queue_event(PDO $pdo, int $appointmentId, int $doctorId, int $tokenNumber, ?string $statusFrom, string $statusTo, int $actionBy, ?string $notes = null): bool
{
    try {
        $stmt = $pdo->prepare("INSERT INTO queue_logs (appointment_id, doctor_id, token_number, status_from, status_to, action_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$appointmentId, $doctorId, $tokenNumber, $statusFrom, $statusTo, $actionBy, $notes]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Verify a user's password securely, with transparent upgrade for seeded accounts
 */
function verify_user_password(string $inputPassword, string $storedHash, ?int $userId = null, ?PDO $pdo = null): bool
{
    // 1. Standard PHP password_verify (always used for all users registered or updated)
    if (password_verify($inputPassword, $storedHash)) {
        return true;
    }

    // 2. Fallback for seed data imported directly from SQL dump
    if (str_starts_with($storedHash, '$SEED$')) {
        $expected = substr($storedHash, 6);
        if ($inputPassword === $expected) {
            // Automatically upgrade to native bcrypt hash
            if ($userId !== null && $pdo !== null) {
                try {
                    $newHash = password_hash($inputPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $userId]);
                } catch (Throwable $e) {
                    // Silently continue
                }
            }
            return true;
        }
    }

    return false;
}

