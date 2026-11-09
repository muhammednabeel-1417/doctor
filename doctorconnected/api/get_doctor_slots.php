<?php
/**
 * DOCTOR CONNECT - API: Fetch Doctor Availability & Estimated Time for a Given Date
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$date = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';

if (!$doctorId || !$date) {
    json_response(['available' => false, 'message' => 'Please select both doctor and date.'], 400);
}

// Validate date format YYYY-MM-DD
$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date) {
    json_response(['available' => false, 'message' => 'Invalid date format.'], 400);
}

// Prevent past dates
if ($date < date('Y-m-d')) {
    json_response(['available' => false, 'message' => 'Cannot book appointments for past dates.'], 400);
}

try {
    $pdo = get_db_connection();

    // 1. Get Doctor Info
    $docStmt = $pdo->prepare("SELECT d.*, u.name, u.email FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.status = 'active'");
    $docStmt->execute([$doctorId]);
    $doctor = $docStmt->fetch();

    if (!$doctor) {
        json_response(['available' => false, 'message' => 'Doctor not found or currently inactive.'], 404);
    }

    // 2. Determine Day of Week
    $dayOfWeek = date('l', strtotime($date));

    // 3. Check schedule for that day
    $schedStmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1");
    $schedStmt->execute([$doctorId, $dayOfWeek]);
    $schedule = $schedStmt->fetch();

    if (!$schedule) {
        json_response([
            'available' => false,
            'message'   => "Dr. {$doctor['name']} does not have scheduled consultations on {$dayOfWeek}s."
        ]);
    }

    // 4. Check current sequence tokens booked so far
    $seqStmt = $pdo->prepare("SELECT current_token FROM daily_token_sequences WHERE doctor_id = ? AND sequence_date = ?");
    $seqStmt->execute([$doctorId, $date]);
    $seq = $seqStmt->fetch();

    $tokensIssued = $seq ? (int)$seq['current_token'] : 0;
    $maxTokens = (int)$schedule['max_tokens'];
    $remaining = max(0, $maxTokens - $tokensIssued);
    $nextToken = $tokensIssued + 1;

    if ($remaining <= 0) {
        json_response([
            'available' => false,
            'message'   => "All {$maxTokens} tokens for {$dayOfWeek}, {$date} are fully booked."
        ]);
    }

    // 5. Calculate estimated time for the next token
    // Formula: session_start + (token_number - 1) * average_consultation_minutes
    $avgMinutes = (int)$doctor['avg_consultation_time'];
    $estimatedTime = calculate_estimated_time($schedule['start_time'], $nextToken, $avgMinutes);

    json_response([
        'available'         => true,
        'doctor_name'       => $doctor['name'],
        'specialization'    => $doctor['specialization'],
        'consultation_fee'  => (float)$doctor['consultation_fee'],
        'day_of_week'       => $dayOfWeek,
        'start_time'        => $schedule['start_time'],
        'start_time_fmt'    => format_time($schedule['start_time']),
        'end_time'          => $schedule['end_time'],
        'end_time_fmt'      => format_time($schedule['end_time']),
        'max_tokens'        => $maxTokens,
        'tokens_issued'     => $tokensIssued,
        'tokens_remaining'  => $remaining,
        'next_token'        => $nextToken,
        'avg_minutes'       => $avgMinutes,
        'estimated_time'    => $estimatedTime,
        'estimated_time_fmt'=> format_time($estimatedTime)
    ]);

} catch (Throwable $e) {
    json_response(['available' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}

