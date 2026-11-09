<?php
/**
 * DOCTOR CONNECT - Authentication and Role Guard
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (!is_logged_in()) {
    set_flash_message('warning', 'Please sign in to access your dashboard.');
    header('Location: ../auth/login.php');
    exit;
}

if (isset($requiredRole)) {
    require_role($requiredRole);
}

// Make current authenticated user details universally available to all guarded pages
$currentUser = current_user();


