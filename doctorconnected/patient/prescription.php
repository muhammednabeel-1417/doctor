<?php
/**
 * DOCTOR CONNECT - Official Electronic Medical Prescription (Print / PDF)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header('Location: ../auth/login.php');
    exit;
}

$currentUser = current_user();
$appointmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$appointmentId) {
    echo "Invalid appointment ID.";
    exit;
}

$pdo = get_db_connection();

// Fetch appointment data with doctor and patient details
$stmt = $pdo->prepare("SELECT a.*, 
    d.specialization, d.qualification, d.room_number,
    doc_u.name AS doctor_name, doc_u.phone AS doctor_phone, doc_u.email AS doctor_email,
    pat_u.name AS patient_name, pat_u.phone AS patient_phone, pat_u.gender, pat_u.date_of_birth, pat_u.blood_group, pat_u.address AS patient_address
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.id
    JOIN users doc_u ON d.user_id = doc_u.id
    JOIN users pat_u ON a.patient_id = pat_u.id
    WHERE a.id = ?");
$stmt->execute([$appointmentId]);
$apt = $stmt->fetch();

if (!$apt) {
    echo "Appointment record not found.";
    exit;
}

// Security: Only the patient, the consulting doctor, or admin can access this prescription
if (has_role('patient') && (int)$apt['patient_id'] !== (int)$currentUser['id']) {
    echo "Access denied.";
    exit;
}
if (has_role('doctor') && (int)$apt['doctor_id'] !== (int)$currentUser['doctor_id']) {
    echo "Access denied.";
    exit;
}

// Calculate patient age if DOB is provided
$patientAge = 'N/A';
if (!empty($apt['date_of_birth'])) {
    $dob = new DateTime($apt['date_of_birth']);
    $today = new DateTime();
    $patientAge = $dob->diff($today)->y . ' years';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription_<?= e($apt['appointment_number']); ?> - DOCTOR CONNECT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: #1e293b;
            padding: 2rem 1rem;
            margin: 0;
        }
        .prescription-container {
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid #cbd5e1;
            padding: 3rem;
            position: relative;
        }
        .clinic-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .brand-title {
            font-size: 1.6rem;
            font-weight: 900;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .brand-title i { color: #0284c7; }
        .patient-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.75rem;
            margin-bottom: 2rem;
            font-size: 0.875rem;
        }
        .rx-symbol {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 2.75rem;
            color: #0284c7;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .content-section {
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 800;
            color: #64748b;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 0.25rem;
        }
        .notes-box {
            font-size: 0.95rem;
            color: #334155;
            line-height: 1.6;
            white-space: pre-line;
        }
        .prescription-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 1.25rem 1.5rem;
            border-radius: 6px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 1rem;
            color: #065f46;
            white-space: pre-line;
            line-height: 1.6;
        }
        .footer-sign {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 3.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        .toolbar {
            max-width: 820px;
            margin: 0 auto 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }
        .btn-print { background: #0284c7; color: #ffffff; }
        .btn-print:hover { background: #0369a1; }
        .btn-back { background: #ffffff; color: #334155; border: 1px solid #cbd5e1; }
        .btn-back:hover { background: #f8fafc; }

        @media print {
            body { background: #ffffff; padding: 0; }
            .toolbar { display: none; }
            .prescription-container { border: none; box-shadow: none; padding: 0; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="appointments.php" class="btn-action btn-back">
        <i class="fa-solid fa-arrow-left"></i> Back to Appointments
    </a>
    <button onclick="window.print()" class="btn-action btn-print">
        <i class="fa-solid fa-print"></i> Print / Save as PDF
    </button>
</div>

<div class="prescription-container">
    <!-- Header -->
    <div class="clinic-header">
        <div>
            <div class="brand-title">
                <i class="fa-solid fa-heart-pulse"></i> DOCTOR CONNECT
            </div>
            <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">
                Official Digital Medical Consultation Network
            </div>
            <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.15rem;">
                Department of Virtual Healthcare & Outpatient Care
            </div>
        </div>

        <div style="text-align: right;">
            <div style="font-size: 1.15rem; font-weight: 800; color: #0f172a;">
                Dr. <?= e($apt['doctor_name']); ?>
            </div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #0284c7;">
                <?= e($apt['specialization']); ?>
            </div>
            <div style="font-size: 0.775rem; color: #64748b; margin-top: 0.15rem;">
                <?= e($apt['qualification']); ?>
            </div>
            <div style="font-size: 0.775rem; color: #94a3b8;">
                Consultation Desk: <?= e($apt['room_number']); ?>
            </div>
        </div>
    </div>

    <!-- Patient Meta Box -->
    <div class="patient-box">
        <div>
            <span style="color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Patient Name:</span><br>
            <strong style="color: #0f172a; font-size: 1rem;"><?= e($apt['patient_name']); ?></strong>
        </div>
        <div>
            <span style="color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Age / Gender:</span><br>
            <strong><?= $patientAge; ?> / <?= ucfirst(e($apt['gender'])); ?></strong>
        </div>
        <div>
            <span style="color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Blood Group:</span><br>
            <strong><?= e($apt['blood_group'] ?: 'N/A'); ?></strong>
        </div>
        <div>
            <span style="color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Date of Session:</span><br>
            <strong><?= format_date($apt['appointment_date']); ?></strong>
        </div>
        <div>
            <span style="color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Token Ref:</span><br>
            <strong>Token #<?= (int)$apt['token_number']; ?> (<?= e($apt['appointment_number']); ?>)</strong>
        </div>
        <div>
            <span style="color: #64748b; font-size: 0.75rem; text-transform: uppercase;">Contact:</span><br>
            <strong><?= e($apt['patient_phone'] ?: 'No phone'); ?></strong>
        </div>
    </div>

    <!-- Patient Symptoms -->
    <?php if (!empty($apt['symptoms'])): ?>
        <div class="content-section">
            <div class="section-title">Reported Presenting Complaints</div>
            <div class="notes-box"><?= nl2br(e($apt['symptoms'])); ?></div>
        </div>
    <?php endif; ?>

    <!-- Clinical Observations -->
    <?php if (!empty($apt['consultation_notes'])): ?>
        <div class="content-section">
            <div class="section-title">Clinical Findings & Diagnosis</div>
            <div class="notes-box"><?= nl2br(e($apt['consultation_notes'])); ?></div>
        </div>
    <?php endif; ?>

    <!-- Prescription Rx -->
    <div class="content-section">
        <div class="rx-symbol">&#8478;</div>
        <div class="section-title">Medical Prescription & Directions</div>
        <div class="prescription-box">
            <?= nl2br(e($apt['prescription'] ?: 'No prescription drugs indicated. Follow general wellness and resting advice.')); ?>
        </div>
    </div>

    <!-- Signature and Stamp -->
    <div class="footer-sign">
        <div>
            <div style="font-size: 0.75rem; color: #94a3b8;">
                <i class="fa-solid fa-lock"></i> Digitally Signed via DOCTOR CONNECT Telehealth Portal<br>
                Security Hash: <?= strtoupper(substr(hash('sha256', $apt['appointment_number'] . $apt['meeting_room']), 0, 16)); ?><br>
                Generated: <?= date('Y-m-d H:i:s'); ?>
            </div>
        </div>

        <div style="text-align: center; width: 220px;">
            <div style="font-family: 'Playfair Display', serif; font-size: 1.35rem; color: #0284c7; margin-bottom: 0.25rem;">
                Dr. <?= e($apt['doctor_name']); ?>
            </div>
            <div style="border-top: 1px solid #0f172a; padding-top: 0.35rem; font-size: 0.8rem; font-weight: 700;">
                Certified Medical Practitioner
            </div>
            <div style="font-size: 0.75rem; color: #64748b;">
                <?= e($apt['qualification']); ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>

