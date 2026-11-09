<?php
/**
 * DOCTOR CONNECT - Dynamic Role-Based Sidebar Navigation
 */
$currentUser = $currentUser ?? current_user() ?? [
    'id' => null,
    'name' => 'Guest',
    'email' => '',
    'role' => 'patient',
    'doctor_id' => null
];
$currentRole = $currentUser['role'] ?? 'patient';
$currentScript = basename($_SERVER['PHP_SELF']);
?>

<aside class="app-sidebar">
    <!-- Brand Logo & Name -->
    <a href="../index.php" class="sidebar-brand">
        <div class="brand-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
        <div class="brand-text">
            <h2>DOCTOR CONNECT</h2>
            <span><?= ucfirst($currentRole); ?> Portal</span>
        </div>
    </a>

    <!-- Navigation Menu Items -->
    <nav class="sidebar-nav">
        <?php if ($currentRole === 'patient'): ?>
            <div class="nav-section-title">Patient Menu</div>
            <a href="dashboard.php" class="nav-item <?= $currentScript === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
            <a href="doctors.php" class="nav-item <?= $currentScript === 'doctors.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-doctor"></i>
                <span>Find Doctors</span>
            </a>
            <a href="book_appointment.php" class="nav-item <?= $currentScript === 'book_appointment.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-ticket"></i>
                <span>Book Token</span>
            </a>
            <a href="queue.php" class="nav-item <?= $currentScript === 'queue.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users-line"></i>
                <span>Live Queue</span>
            </a>
            <a href="appointments.php" class="nav-item <?= $currentScript === 'appointments.php' ? 'active' : ''; ?>">
                <i class="fa-regular fa-calendar-check"></i>
                <span>My Appointments</span>
            </a>
            <a href="profile.php" class="nav-item <?= $currentScript === 'profile.php' ? 'active' : ''; ?>">
                <i class="fa-regular fa-user"></i>
                <span>My Profile</span>
            </a>

        <?php elseif ($currentRole === 'doctor'): ?>
            <div class="nav-section-title">Doctor Workspace</div>
            <a href="dashboard.php" class="nav-item <?= $currentScript === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-stethoscope"></i>
                <span>Consultation Desk</span>
            </a>
            <a href="queue.php" class="nav-item <?= $currentScript === 'queue.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-users-rays"></i>
                <span>Live Token Queue</span>
            </a>
            <a href="schedule.php" class="nav-item <?= $currentScript === 'schedule.php' ? 'active' : ''; ?>">
                <i class="fa-regular fa-clock"></i>
                <span>Weekly Schedule</span>
            </a>
            <a href="patients.php" class="nav-item <?= $currentScript === 'patients.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-notes-medical"></i>
                <span>Patient History</span>
            </a>
            <a href="profile.php" class="nav-item <?= $currentScript === 'profile.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-id-card-clip"></i>
                <span>Doctor Profile</span>
            </a>

        <?php elseif ($currentRole === 'admin'): ?>
            <div class="nav-section-title">Administration</div>
            <a href="dashboard.php" class="nav-item <?= $currentScript === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i>
                <span>Admin Analytics</span>
            </a>
            <a href="doctors.php" class="nav-item <?= $currentScript === 'doctors.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-doctor"></i>
                <span>Manage Doctors</span>
            </a>
            <a href="patients.php" class="nav-item <?= $currentScript === 'patients.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-hospital-user"></i>
                <span>Manage Patients</span>
            </a>
            <a href="schedules.php" class="nav-item <?= $currentScript === 'schedules.php' ? 'active' : ''; ?>">
                <i class="fa-regular fa-calendar-days"></i>
                <span>Doctor Schedules</span>
            </a>
            <a href="appointments.php" class="nav-item <?= $currentScript === 'appointments.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-check"></i>
                <span>All Appointments</span>
            </a>
            <a href="reports.php" class="nav-item <?= $currentScript === 'reports.php' ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-csv"></i>
                <span>Reports & CSV</span>
            </a>
        <?php endif; ?>

        <div class="nav-section-title" style="margin-top: auto;">Quick Actions</div>
        <a href="../index.php" class="nav-item" target="_blank">
            <i class="fa-solid fa-globe"></i>
            <span>Public Site</span>
        </a>
        <a href="../auth/logout.php" class="nav-item" style="color: var(--danger);">
            <i class="fa-solid fa-arrow-right-from-bracket" style="color: var(--danger);"></i>
            <span>Sign Out</span>
        </a>
    </nav>

    <!-- Sidebar Bottom User Card -->
    <div class="sidebar-footer">
        <div class="user-snippet">
            <div class="user-avatar">
                <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-info">
                <div class="name"><?= e($currentUser['name'] ?? 'User'); ?></div>
                <div class="role-badge"><?= ucfirst($currentRole); ?></div>
            </div>
        </div>
    </div>
</aside>

