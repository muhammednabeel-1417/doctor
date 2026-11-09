<?php
/**
 * DOCTOR CONNECT - API: Call Next Patient Atomically
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role(['doctor', 'admin'])) {
    json_response(['success' => false, 'message' => 'Unauthorized action.'], 403);
}

if (!validate_csrf()) {
    json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 400);
}

$currentUser = current_user();
$doctorId = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$date = isset($_POST['date']) ? sanitize_input($_POST['date']) : date('Y-m-d');

// If doctor is logged in, ensure they can only call for their own schedule
if (has_role('doctor') && $currentUser['doctor_id'] !== $doctorId) {
    $doctorId = (int)$currentUser['doctor_id'];
}

if (!$doctorId) {
    json_response(['success' => false, 'message' => 'Invalid doctor specified.'], 400);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // 1. Check if there is an existing 'serving' appointment and mark it 'completed'
    $currServingStmt = $pdo->prepare("SELECT id, token_number, patient_id FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'serving' FOR UPDATE");
    $currServingStmt->execute([$doctorId, $date]);
    $currServing = $currServingStmt->fetch();

    if ($currServing) {
        $updatePrev = $pdo->prepare("UPDATE appointments SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $updatePrev->execute([$currServing['id']]);

        log_queue_event($pdo, (int)$currServing['id'], $doctorId, (int)$currServing['token_number'], 'serving', 'completed', (int)$currentUser['id'], 'Automatically concluded on calling next token');
    }

    // 2. Select next waiting patient row lock
    $nextStmt = $pdo->prepare("SELECT id, token_number, patient_id, meeting_room FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? AND status = 'waiting' 
        ORDER BY token_number ASC LIMIT 1 FOR UPDATE");
    $nextStmt->execute([$doctorId, $date]);
    $nextApt = $nextStmt->fetch();

    if (!$nextApt) {
        $pdo->commit();
        json_response([
            'success' => false, 
            'message' => 'There are no waiting patients remaining in queue for today.'
        ]);
    }

    $appointmentId = (int)$nextApt['id'];
    $tokenNumber = (int)$nextApt['token_number'];
    $patientId = (int)$nextApt['patient_id'];
    $meetingRoom = $nextApt['meeting_room'];

    // 3. Update appointment to 'serving'
    $updateApt = $pdo->prepare("UPDATE appointments SET status = 'serving', called_at = NOW(), started_at = NOW() WHERE id = ?");
    $updateApt->execute([$appointmentId]);

    // 4. Update daily sequence table last_served_token
    $updateSeq = $pdo->prepare("UPDATE daily_token_sequences SET last_served_token = ? WHERE doctor_id = ? AND sequence_date = ?");
    $updateSeq->execute([$tokenNumber, $doctorId, $date]);

    // 5. Log queue transition
    log_queue_event($pdo, $appointmentId, $doctorId, $tokenNumber, 'waiting', 'serving', (int)$currentUser['id'], 'Doctor called next patient');

    // 6. Notify patient
    add_notification(
        $pdo, 
        $patientId, 
        'Your Consultation is Starting!', 
        "Doctor has called Token #{$tokenNumber}. Please enter your video consultation room immediately.", 
        'queue'
    );

    $pdo->commit();

    json_response([
        'success'        => true,
        'appointment_id' => $appointmentId,
        'token_number'   => $tokenNumber,
        'meeting_room'   => $meetingRoom,
        'message'        => "Token #{$tokenNumber} is now serving."
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Error calling next patient: ' . $e->getMessage()], 500);
}

