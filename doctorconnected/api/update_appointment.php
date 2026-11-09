<?php
/**
 * DOCTOR CONNECT - API: Update Appointment State, Clinical Notes & Status
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role(['doctor', 'admin'])) {
    json_response(['success' => false, 'message' => 'Unauthorized operation.'], 403);
}

if (!validate_csrf()) {
    json_response(['success' => false, 'message' => 'Invalid security token.'], 400);
}

$currentUser = current_user();
$action = sanitize_input($_POST['action'] ?? '');
$appointmentId = isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;
$notes = sanitize_input($_POST['consultation_notes'] ?? '');
$prescription = sanitize_input($_POST['prescription'] ?? '');

if (!$appointmentId || empty($action)) {
    json_response(['success' => false, 'message' => 'Missing appointment ID or action.'], 400);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Fetch target appointment
    $stmt = $pdo->prepare("SELECT a.*, d.user_id AS doctor_user_id 
        FROM appointments a 
        JOIN doctors d ON a.doctor_id = d.id 
        WHERE a.id = ? FOR UPDATE");
    $stmt->execute([$appointmentId]);
    $apt = $stmt->fetch();

    if (!$apt) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Appointment not found.'], 404);
    }

    // Role verification: Doctor can only alter their own appointments
    if (has_role('doctor') && (int)$apt['doctor_user_id'] !== (int)$currentUser['id']) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Access denied to this appointment.'], 403);
    }

    $oldStatus = $apt['status'];

    switch ($action) {
        case 'set_serving':
            // If another appointment is serving for this doctor and date, mark it completed or concluded
            $pdo->prepare("UPDATE appointments SET status = 'completed', completed_at = NOW() 
                WHERE doctor_id = ? AND appointment_date = ? AND status = 'serving' AND id != ?")
                ->execute([$apt['doctor_id'], $apt['appointment_date'], $appointmentId]);

            $upd = $pdo->prepare("UPDATE appointments SET status = 'serving', called_at = NOW(), started_at = NOW() WHERE id = ?");
            $upd->execute([$appointmentId]);

            // Update daily sequence table
            $pdo->prepare("UPDATE daily_token_sequences SET last_served_token = ? WHERE doctor_id = ? AND sequence_date = ?")
                ->execute([$apt['token_number'], $apt['doctor_id'], $apt['appointment_date']]);

            log_queue_event($pdo, $appointmentId, (int)$apt['doctor_id'], (int)$apt['token_number'], $oldStatus, 'serving', (int)$currentUser['id'], 'Doctor called patient directly to serving');
            add_notification($pdo, (int)$apt['patient_id'], 'Doctor is Calling You!', "Your Token #{$apt['token_number']} is now serving. Please join the video consultation.", 'queue');
            $msg = "Token #{$apt['token_number']} is now serving.";
            break;

        case 'complete':
            $upd = $pdo->prepare("UPDATE appointments SET status = 'completed', consultation_notes = ?, prescription = ?, completed_at = NOW() WHERE id = ?");
            $upd->execute([$notes, $prescription, $appointmentId]);

            log_queue_event($pdo, $appointmentId, (int)$apt['doctor_id'], (int)$apt['token_number'], $oldStatus, 'completed', (int)$currentUser['id'], 'Consultation completed by doctor');
            add_notification($pdo, (int)$apt['patient_id'], 'Consultation Completed', "Your consultation for Token #{$apt['token_number']} has finished. Your prescription is now available in your appointment history.", 'success');
            $msg = "Consultation successfully completed.";
            break;

        case 'no_show':
            $upd = $pdo->prepare("UPDATE appointments SET status = 'no_show', completed_at = NOW() WHERE id = ?");
            $upd->execute([$appointmentId]);

            log_queue_event($pdo, $appointmentId, (int)$apt['doctor_id'], (int)$apt['token_number'], $oldStatus, 'no_show', (int)$currentUser['id'], 'Patient marked as no-show');
            add_notification($pdo, (int)$apt['patient_id'], 'Marked as No-Show', "Your Token #{$apt['token_number']} was marked as no-show by the doctor because you were absent when called.", 'alert');
            $msg = "Patient marked as no-show.";
            break;

        case 'save_notes':
            $upd = $pdo->prepare("UPDATE appointments SET consultation_notes = ?, prescription = ? WHERE id = ?");
            $upd->execute([$notes, $prescription, $appointmentId]);
            $msg = "Consultation notes and prescription updated.";
            break;

        default:
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Unknown action requested.'], 400);
    }

    $pdo->commit();
    json_response(['success' => true, 'message' => $msg]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Error updating appointment: ' . $e->getMessage()], 500);
}

