<?php
/**
 * DOCTOR CONNECT - Race-Safe Token Booking System
 * Implements atomic token generation using row locking (SELECT ... FOR UPDATE)
 * and database UNIQUE constraints.
 */
$requiredRole = 'patient';
require_once __DIR__ . '/../includes/auth_check.php';

$pageTitle = 'Book Consultation Token';
$pageSubtitle = 'Reserve your race-safe appointment token';

$pdo = get_db_connection();
$patientId = (int)($currentUser['id'] ?? 0);

$selectedDoctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$selectedDate = isset($_GET['date']) ? sanitize_input($_GET['date']) : date('Y-m-d');

$error = '';
$bookingSuccess = null;

// Fetch all active doctors for dropdown
$doctorsList = $pdo->query("SELECT d.id, d.consultation_fee, d.avg_consultation_time, d.specialization, u.name 
    FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.status = 'active' ORDER BY u.name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $appointmentDate = sanitize_input($_POST['appointment_date'] ?? '');
        $symptoms = sanitize_input($_POST['symptoms'] ?? '');

        // Validations
        if (!$doctorId || empty($appointmentDate)) {
            $error = 'Please select both a doctor and an appointment date.';
        } elseif ($appointmentDate < date('Y-m-d')) {
            $error = 'Appointments cannot be booked for past dates.';
        } else {
            // ==============================================================
            // RACE-SAFE TRANSACTION WITH ROW LOCKING
            // ==============================================================
            try {
                $pdo->beginTransaction();

                // 1. Fetch Doctor Info
                $docStmt = $pdo->prepare("SELECT d.*, u.name AS doctor_name FROM doctors d JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.status = 'active'");
                $docStmt->execute([$doctorId]);
                $doctor = $docStmt->fetch();

                if (!$doctor) {
                    throw new Exception("Selected doctor is not available.");
                }

                // 2. Verify Schedule for Selected Day of Week
                $dayOfWeek = date('l', strtotime($appointmentDate));
                $schedStmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1");
                $schedStmt->execute([$doctorId, $dayOfWeek]);
                $schedule = $schedStmt->fetch();

                if (!$schedule) {
                    throw new Exception("Dr. {$doctor['doctor_name']} does not consult on {$dayOfWeek}s. Please pick another day.");
                }

                // 3. Check for existing active booking for this patient with this doctor on this date
                $dupStmt = $pdo->prepare("SELECT id FROM appointments 
                    WHERE patient_id = ? AND doctor_id = ? AND appointment_date = ? AND status IN ('waiting', 'serving')");
                $dupStmt->execute([$patientId, $doctorId, $appointmentDate]);
                if ($dupStmt->fetch()) {
                    throw new Exception("You already have an active appointment token booked with Dr. {$doctor['doctor_name']} on {$appointmentDate}.");
                }

                // 4. ATOMIC ROW LOCK ON daily_token_sequences
                $seqStmt = $pdo->prepare("SELECT current_token FROM daily_token_sequences 
                    WHERE doctor_id = ? AND sequence_date = ? FOR UPDATE");
                $seqStmt->execute([$doctorId, $appointmentDate]);
                $seq = $seqStmt->fetch();

                if ($seq) {
                    $nextToken = (int)$seq['current_token'] + 1;
                    $updSeq = $pdo->prepare("UPDATE daily_token_sequences SET current_token = ? WHERE doctor_id = ? AND sequence_date = ?");
                    $updSeq->execute([$nextToken, $doctorId, $appointmentDate]);
                } else {
                    $nextToken = 1;
                    $insSeq = $pdo->prepare("INSERT INTO daily_token_sequences (doctor_id, sequence_date, current_token, last_served_token) VALUES (?, ?, 1, 0)");
                    $insSeq->execute([$doctorId, $appointmentDate]);
                }

                // 5. Verify capacity constraint
                $maxCapacity = (int)$schedule['max_tokens'];
                if ($nextToken > $maxCapacity) {
                    throw new Exception("Maximum token capacity ({$maxCapacity} patients) has been reached for Dr. {$doctor['doctor_name']} on {$appointmentDate}.");
                }

                // 6. Calculate Estimated Consultation Time
                // Formula: session_start + (token_number - 1) * average_consultation_minutes
                $avgMinutes = (int)$doctor['avg_consultation_time'];
                $estimatedTime = calculate_estimated_time($schedule['start_time'], $nextToken, $avgMinutes);

                // 7. Generate Unpredictable Jitsi Meeting Room
                $meetingRoom = generate_meeting_room();
                $aptNumber = 'APT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

                // 8. Insert Appointment (protected by DB-level UNIQUE constraint unique_doc_date_token)
                $insApt = $pdo->prepare("INSERT INTO appointments 
                    (appointment_number, patient_id, doctor_id, appointment_date, token_number, estimated_time, status, symptoms, meeting_room)
                    VALUES (?, ?, ?, ?, ?, ?, 'waiting', ?, ?)");
                $insApt->execute([
                    $aptNumber,
                    $patientId,
                    $doctorId,
                    $appointmentDate,
                    $nextToken,
                    $estimatedTime,
                    $symptoms,
                    $meetingRoom
                ]);
                $newAppointmentId = (int)$pdo->lastInsertId();

                // 9. Log Queue Event
                log_queue_event($pdo, $newAppointmentId, $doctorId, $nextToken, null, 'waiting', $patientId, 'Token allocated to patient');

                // 10. Send notification
                add_notification($pdo, $patientId, 'Token Confirmed!', "Your appointment with Dr. {$doctor['doctor_name']} on {$appointmentDate} is booked. Your token is #{$nextToken}.", 'queue');

                $pdo->commit();

                // Store details for success screen
                $bookingSuccess = [
                    'id'             => $newAppointmentId,
                    'appointment_no' => $aptNumber,
                    'doctor_name'    => $doctor['doctor_name'],
                    'specialization' => $doctor['specialization'],
                    'date'           => $appointmentDate,
                    'day_of_week'    => $dayOfWeek,
                    'token_number'   => $nextToken,
                    'estimated_time' => $estimatedTime,
                    'consultation_fee'=> $doctor['consultation_fee'],
                    'room_number'    => $doctor['room_number']
                ];

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($bookingSuccess): ?>
    <!-- BOOKING SUCCESS SCREEN -->
    <div class="card" style="max-width: 650px; margin: 0 auto; box-shadow: var(--shadow-xl); border-top: 5px solid var(--success);">
        <div class="card-body" style="text-align: center; padding: 2.5rem 2rem;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--success-light); color: var(--success); display: inline-flex; align-items: center; justify-content: center; font-size: 2.25rem; margin-bottom: 1.25rem;">
                <i class="fa-solid fa-check"></i>
            </div>

            <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--slate-900); margin: 0 0 0.5rem;">Token Generated Successfully!</h2>
            <p style="color: var(--slate-500); font-size: 0.95rem; margin: 0 0 2rem;">Your appointment has been registered in the live queue.</p>

            <div style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; border-radius: var(--radius-lg); padding: 1.75rem; margin-bottom: 2rem;">
                <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9;">Your Official Token</span>
                <div style="font-size: 4.5rem; font-weight: 900; line-height: 1; margin: 0.5rem 0;">
                    #<?= $bookingSuccess['token_number']; ?>
                </div>
                <div style="font-size: 0.9rem; opacity: 0.95;">
                    Appointment Ref: <strong><?= e($bookingSuccess['appointment_no']); ?></strong>
                </div>
            </div>

            <div style="background: var(--slate-50); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; text-align: left; font-size: 0.9rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem;">
                    <span style="color: var(--slate-500);">Doctor:</span>
                    <strong>Dr. <?= e($bookingSuccess['doctor_name']); ?> (<?= e($bookingSuccess['specialization']); ?>)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem;">
                    <span style="color: var(--slate-500);">Appointment Date:</span>
                    <strong><?= format_date($bookingSuccess['date']); ?> (<?= e($bookingSuccess['day_of_week']); ?>)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.6rem;">
                    <span style="color: var(--slate-500);">Estimated Consultation Time:</span>
                    <strong style="color: var(--primary);"><i class="fa-regular fa-clock"></i> <?= format_time($bookingSuccess['estimated_time']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--slate-500);">Consultation Fee:</span>
                    <strong>$<?= number_format($bookingSuccess['consultation_fee'], 2); ?></strong>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="queue.php?id=<?= $bookingSuccess['id']; ?>" class="btn btn-primary btn-lg">
                    <i class="fa-solid fa-users-line"></i> View Live Queue Status
                </a>
                <a href="appointments.php" class="btn btn-secondary btn-lg">
                    <i class="fa-regular fa-calendar-check"></i> My Appointments
                </a>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- BOOKING FORM -->
    <div style="max-width: 820px; margin: 0 auto;">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div><?= e($error); ?></div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-calendar-plus" style="color: var(--primary);"></i>
                    Select Doctor & Preferred Date
                </div>
            </div>

            <div class="card-body">
                <form method="POST" action="book_appointment.php" id="bookingForm">
                    <?= csrf_field(); ?>

                    <div class="form-row">
                        <!-- Doctor Selection -->
                        <div class="form-group">
                            <label for="doctor_id" class="form-label">Doctor Specialist <span style="color:var(--danger)">*</span></label>
                            <select id="doctor_id" name="doctor_id" class="form-select" required onchange="checkAvailability()">
                                <option value="">-- Choose a Doctor --</option>
                                <?php foreach ($doctorsList as $doc): ?>
                                    <option value="<?= $doc['id']; ?>" <?= $selectedDoctorId === (int)$doc['id'] ? 'selected' : ''; ?>>
                                        Dr. <?= e($doc['name']); ?> - <?= e($doc['specialization']); ?> ($<?= number_format($doc['consultation_fee'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Selection -->
                        <div class="form-group">
                            <label for="appointment_date" class="form-label">Consultation Date <span style="color:var(--danger)">*</span></label>
                            <input type="date" id="appointment_date" name="appointment_date" class="form-control" 
                                min="<?= date('Y-m-d'); ?>" value="<?= e($selectedDate); ?>" required onchange="checkAvailability()">
                        </div>
                    </div>

                    <!-- Live Slot Availability Preview Box -->
                    <div id="availabilityPreview" style="display: none; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                            <div>
                                <span style="font-size: 0.75rem; text-transform: uppercase; color: #166534; font-weight: 700;">Status: Open for Booking</span>
                                <h4 style="margin: 0.2rem 0; color: #14532d; font-size: 1.1rem;">
                                    Consultation Hours: <span id="slotHours">9:00 AM - 1:00 PM</span>
                                </h4>
                                <div style="font-size: 0.85rem; color: #15803d;">
                                    Next Available Token: <strong id="slotNextToken" style="font-size: 1.1rem;">#1</strong> &bull; 
                                    Est. Time: <strong id="slotEstTime">9:00 AM</strong>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 0.75rem; color: #166534; text-transform: uppercase; font-weight: 700;">Tokens Remaining</span>
                                <div id="slotRemaining" style="font-size: 1.75rem; font-weight: 900; color: #166534; line-height: 1;">
                                    25
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Unavailable Warning Box -->
                    <div id="unavailableNotice" style="display: none; background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; color: #991b1b;">
                            <i class="fa-solid fa-circle-exclamation" style="font-size: 1.35rem;"></i>
                            <div id="unavailableMessage" style="font-size: 0.9rem; font-weight: 600;">Doctor is not scheduled on this day.</div>
                        </div>
                    </div>

                    <!-- Symptoms Description -->
                    <div class="form-group">
                        <label for="symptoms" class="form-label">Symptoms & Reason for Consultation</label>
                        <textarea id="symptoms" name="symptoms" class="form-control" placeholder="Briefly describe your health symptoms, duration, or any questions for the doctor..." rows="3"></textarea>
                    </div>

                    <button type="submit" id="bookSubmitBtn" class="btn btn-primary btn-lg" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-ticket"></i> Confirm & Book Token Number
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    async function checkAvailability() {
        const doctorId = document.getElementById('doctor_id').value;
        const date = document.getElementById('appointment_date').value;
        const preview = document.getElementById('availabilityPreview');
        const notice = document.getElementById('unavailableNotice');
        const noticeMsg = document.getElementById('unavailableMessage');
        const submitBtn = document.getElementById('bookSubmitBtn');

        if (!doctorId || !date) {
            preview.style.display = 'none';
            notice.style.display = 'none';
            return;
        }

        try {
            const res = await fetch(`../api/get_doctor_slots.php?doctor_id=${doctorId}&date=${date}`);
            const data = await res.json();

            if (data.available) {
                preview.style.display = 'block';
                notice.style.display = 'none';
                submitBtn.disabled = false;

                document.getElementById('slotHours').textContent = `${data.start_time_fmt} - ${data.end_time_fmt}`;
                document.getElementById('slotNextToken').textContent = `#${data.next_token}`;
                document.getElementById('slotEstTime').textContent = data.estimated_time_fmt;
                document.getElementById('slotRemaining').textContent = `${data.tokens_remaining} / ${data.max_tokens}`;
            } else {
                preview.style.display = 'none';
                notice.style.display = 'block';
                noticeMsg.textContent = data.message || 'Consultations not available for selected date.';
                submitBtn.disabled = true;
            }
        } catch (err) {
            console.error('Slot check error:', err);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('doctor_id').value) {
            checkAvailability();
        }
    });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

