<?php
/**
 * DOCTOR CONNECT - Live Queue Status JSON API
 * Polled every 3 seconds by patient and doctor dashboards
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? sanitize_input($_GET['date']) : date('Y-m-d');
$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : null;

if (!$doctorId && !$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing doctor_id or appointment_id']);
    exit;
}

try {
    $pdo = get_db_connection();

    // If appointmentId given without doctorId, lookup doctor & date
    if ($appointmentId && !$doctorId) {
        $aptLookup = $pdo->prepare("SELECT doctor_id, appointment_date FROM appointments WHERE id = ?");
        $aptLookup->execute([$appointmentId]);
        $found = $aptLookup->fetch();
        if ($found) {
            $doctorId = (int)$found['doctor_id'];
            $date = $found['appointment_date'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Appointment not found']);
            exit;
        }
    }

    // 1. Get currently serving token
    $servingStmt = $pdo->prepare("SELECT id, token_number, patient_id, meeting_room FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'serving' 
        ORDER BY token_number ASC LIMIT 1");
    $servingStmt->execute([$doctorId, $date]);
    $serving = $servingStmt->fetch();
    $servingToken = $serving ? (int)$serving['token_number'] : null;

    // 2. Get next waiting token
    $nextStmt = $pdo->prepare("SELECT id, token_number, patient_id FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'waiting' 
        ORDER BY token_number ASC LIMIT 1");
    $nextStmt->execute([$doctorId, $date]);
    $next = $nextStmt->fetch();
    $nextToken = $next ? (int)$next['token_number'] : null;

    // 3. Counts by status
    $countsStmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) AS waiting_count,
        SUM(CASE WHEN status = 'serving' THEN 1 ELSE 0 END) AS serving_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) AS no_show_count,
        COUNT(*) AS total_count
        FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
    $countsStmt->execute([$doctorId, $date]);
    $counts = $countsStmt->fetch();

    $waitingCount = (int)($counts['waiting_count'] ?? 0);
    $completedCount = (int)($counts['completed_count'] ?? 0);
    $noShowCount = (int)($counts['no_show_count'] ?? 0);
    $totalCount = (int)($counts['total_count'] ?? 0);

    // 4. Fetch full queue list for this doctor and date
    $queueStmt = $pdo->prepare("SELECT a.id, a.token_number, a.status, a.estimated_time, a.symptoms, 
        a.meeting_room, u.name AS patient_name, u.phone AS patient_phone
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.doctor_id = ? AND a.appointment_date = ?
        ORDER BY a.token_number ASC");
    $queueStmt->execute([$doctorId, $date]);
    $queue = $queueStmt->fetchAll();

    foreach ($queue as &$row) {
        $row['estimated_time_formatted'] = format_time($row['estimated_time']);
    }

    // 5. Patient-specific details if appointment_id is requested
    $patientData = [
        'patient_token'        => null,
        'patient_status'       => null,
        'patient_position'     => null,
        'estimated_wait_time'  => 0,
        'is_eligible_for_video'=> false,
        'meeting_room'         => null
    ];

    if ($appointmentId) {
        $targetStmt = $pdo->prepare("SELECT id, token_number, status, meeting_room, estimated_time 
            FROM appointments WHERE id = ?");
        $targetStmt->execute([$appointmentId]);
        $targetApt = $targetStmt->fetch();

        if ($targetApt) {
            $targetToken = (int)$targetApt['token_number'];
            $targetStatus = $targetApt['status'];
            $patientData['patient_token'] = $targetToken;
            $patientData['patient_status'] = $targetStatus;

            // Calculate tokens ahead: count how many 'waiting' tokens with token_number < targetToken
            $aheadStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments 
                WHERE doctor_id = ? AND appointment_date = ? AND status = 'waiting' AND token_number < ?");
            $aheadStmt->execute([$doctorId, $date, $targetToken]);
            $tokensAhead = (int)$aheadStmt->fetchColumn();

            // Add +1 if someone is currently serving
            if ($servingToken !== null && $targetStatus === 'waiting') {
                $patientData['patient_position'] = $tokensAhead + 1;
            } else {
                $patientData['patient_position'] = $tokensAhead;
            }

            // Doctor avg time
            $docAvgStmt = $pdo->prepare("SELECT avg_consultation_time FROM doctors WHERE id = ?");
            $docAvgStmt->execute([$doctorId]);
            $avgMinutes = (int)($docAvgStmt->fetchColumn() ?: 15);
            $patientData['estimated_wait_time'] = $patientData['patient_position'] * $avgMinutes;

            // Eligibility for video consultation:
            // Must be 'serving' OR 'next' in line!
            $isServing = ($targetStatus === 'serving');
            $isNext = ($targetStatus === 'waiting' && $targetToken === $nextToken);

            if ($isServing || $isNext) {
                $patientData['is_eligible_for_video'] = true;
                $patientData['meeting_room'] = $targetApt['meeting_room'];
            }
        }
    }

    echo json_encode([
        'success'         => true,
        'doctor_id'       => $doctorId,
        'date'            => $date,
        'serving_token'   => $servingToken,
        'next_token'      => $nextToken,
        'waiting_count'   => $waitingCount,
        'completed_count' => $completedCount,
        'no_show_count'   => $noShowCount,
        'total_count'     => $totalCount,
        'queue'           => $queue,
        ...$patientData
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

