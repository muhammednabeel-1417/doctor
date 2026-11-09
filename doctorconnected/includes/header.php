<?php
/**
 * DOCTOR CONNECT - Common Dashboard Header
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$currentUser = $currentUser ?? current_user() ?? [
    'id' => null,
    'name' => 'Guest',
    'email' => '',
    'role' => 'patient',
    'doctor_id' => null
];
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$unreadNotifications = 0;

if ($currentUser && !empty($currentUser['id'])) {
    try {
        $pdoNotif = get_db_connection();
        $notifStmt = $pdoNotif->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $notifStmt->execute([$currentUser['id']]);
        $unreadNotifications = (int)$notifStmt->fetchColumn();
    } catch (Throwable $e) {
        $unreadNotifications = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()); ?>">
    <title><?= e($pageTitle); ?> - <?= APP_NAME; ?></title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="app-layout">
    <!-- Sidebar Navigation -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="app-main">
        <!-- Top Sticky Header -->
        <header class="app-header">
            <div class="header-left">
                <button type="button" class="sidebar-toggle-btn" aria-label="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1><?= e($pageTitle); ?></h1>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p><?= e($pageSubtitle); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="header-actions">
                <!-- User Profile Pill -->
                <div class="user-snippet" style="border: 1px solid var(--border-color); background: #ffffff;">
                    <div class="user-avatar" style="width: 34px; height: 34px; font-size: 0.85rem;">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="user-info" style="line-height: 1.2;">
                        <div class="name" style="font-size: 0.8rem;"><?= e($currentUser['name'] ?? 'Guest'); ?></div>
                        <div class="role-badge" style="font-size: 0.7rem; color: var(--primary);"><?= ucfirst(e($currentUser['role'] ?? 'User')); ?></div>
                    </div>
                    <a href="../auth/logout.php" title="Sign Out" style="color: var(--danger); margin-left: 0.5rem; font-size: 0.95rem;">
                        <i class="fa-solid fa-power-off"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Body Container -->
        <div class="app-content">
            <?php 
            $flash = get_flash_message();
            if ($flash): 
            ?>
                <div class="alert alert-<?= e($flash['type']); ?>">
                    <i class="fa-solid fa-circle-info"></i>
                    <div><?= e($flash['message']); ?></div>
                </div>
            <?php endif; ?>

