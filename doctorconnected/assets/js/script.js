/**
 * DOCTOR CONNECT - Core Client-Side Logic & Live Polling
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.querySelector('.sidebar-toggle-btn');
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Close sidebar if clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !sidebarToggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }

    // 2. Auto-dismiss flash alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

/**
 * Live Queue Polling Engine
 * Automatically polls api/queue_status.php every 3 seconds
 */
function initLiveQueuePoller({ doctorId, date, appointmentId = null, isDoctorView = false }) {
    if (!doctorId || !date) return;

    const pollInterval = 3000; // 3 seconds
    let lastServingToken = null;
    let hasAlertedTurn = false;

    async function fetchQueueStatus() {
        try {
            const url = `../api/queue_status.php?doctor_id=${encodeURIComponent(doctorId)}&date=${encodeURIComponent(date)}${appointmentId ? `&appointment_id=${encodeURIComponent(appointmentId)}` : ''}`;
            const response = await fetch(url);
            if (!response.ok) return;

            const data = await response.json();
            if (!data.success) return;

            // Audio Alert when patient's turn arrives
            if (!isDoctorView && appointmentId && data.patient_status === 'serving' && !hasAlertedTurn) {
                hasAlertedTurn = true;
                playHospitalChime();
            }

            // Update Current Serving Token
            const servingEl = document.getElementById('live-serving-token');
            if (servingEl) {
                const currentServing = data.serving_token ? `#${data.serving_token}` : 'None';
                if (servingEl.textContent !== currentServing) {
                    servingEl.textContent = currentServing;
                    servingEl.classList.add('pulse');
                    setTimeout(() => servingEl.classList.remove('pulse'), 1000);
                }
            }

            // Update Next Token
            const nextEl = document.getElementById('live-next-token');
            if (nextEl) {
                nextEl.textContent = data.next_token ? `#${data.next_token}` : 'None';
            }

            // Update Waiting Count
            const waitingCountEl = document.getElementById('live-waiting-count');
            if (waitingCountEl) {
                waitingCountEl.textContent = data.waiting_count;
            }

            // Update Total Completed Count
            const completedCountEl = document.getElementById('live-completed-count');
            if (completedCountEl) {
                completedCountEl.textContent = data.completed_count;
            }

            // Update Patient Specific View
            if (!isDoctorView && appointmentId) {
                const positionEl = document.getElementById('live-patient-position');
                if (positionEl) {
                    if (data.patient_status === 'serving') {
                        positionEl.innerHTML = '<span class="badge badge-serving"><i class="fa-solid fa-bell"></i> It\'s Your Turn Now!</span>';
                    } else if (data.patient_status === 'completed') {
                        positionEl.innerHTML = '<span class="badge badge-completed"><i class="fa-solid fa-check"></i> Consultation Completed</span>';
                    } else if (data.patient_status === 'no_show') {
                        positionEl.innerHTML = '<span class="badge badge-no_show"><i class="fa-solid fa-circle-xmark"></i> Marked No-Show</span>';
                    } else {
                        const ahead = data.patient_position !== null ? data.patient_position : 0;
                        positionEl.innerHTML = `<span class="badge badge-waiting"><i class="fa-solid fa-user-clock"></i> ${ahead} token(s) ahead of you</span>`;
                    }
                }

                // Video Consultation Button Activation
                const videoBtnContainer = document.getElementById('video-consultation-action');
                if (videoBtnContainer) {
                    if (data.is_eligible_for_video && data.meeting_room) {
                        videoBtnContainer.innerHTML = `
                            <a href="consultation.php?id=${appointmentId}" class="btn btn-success btn-lg" style="animation: bounce 1.5s infinite;">
                                <i class="fa-solid fa-video"></i> Join Video Consultation Now
                            </a>
                        `;
                    } else {
                        videoBtnContainer.innerHTML = `
                            <button class="btn btn-secondary btn-lg" disabled title="Video link activates when your token is next or serving">
                                <i class="fa-solid fa-lock"></i> Video Room Opens When Your Turn Arrives
                            </button>
                        `;
                    }
                }
            }

            // Update Doctor Queue Table if present
            if (isDoctorView && data.queue) {
                updateDoctorQueueTable(data.queue, data.serving_token);
            }

            lastServingToken = data.serving_token;
        } catch (err) {
            console.warn('Queue polling error:', err);
        }
    }

    // Initial fetch and start 3-second recurring interval
    fetchQueueStatus();
    const timerId = setInterval(fetchQueueStatus, pollInterval);

    // Clean up if navigating away
    window.addEventListener('beforeunload', () => clearInterval(timerId));
}

/**
 * Render doctor live queue rows without full page refresh
 */
function updateDoctorQueueTable(queueList, currentServingToken) {
    const tableBody = document.getElementById('doctor-queue-body');
    if (!tableBody) return;

    if (!queueList || queueList.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--slate-400); padding: 2rem;">No appointments booked for this date yet.</td></tr>';
        return;
    }

    let html = '';
    queueList.forEach(apt => {
        const isCurrent = (apt.token_number == currentServingToken);
        const rowClass = isCurrent ? 'style="background-color: #f0fdf4;"' : '';
        
        let statusBadge = '';
        switch(apt.status) {
            case 'serving':
                statusBadge = '<span class="badge badge-serving"><span class="pulse-dot"></span> Serving</span>';
                break;
            case 'waiting':
                statusBadge = '<span class="badge badge-waiting">Waiting</span>';
                break;
            case 'completed':
                statusBadge = '<span class="badge badge-completed">Completed</span>';
                break;
            case 'no_show':
                statusBadge = '<span class="badge badge-no_show">No-Show</span>';
                break;
            default:
                statusBadge = `<span class="badge">${apt.status}</span>`;
        }

        let actionButtons = '';
        if (apt.status === 'serving') {
            actionButtons = `
                <a href="consultation.php?id=${apt.id}" class="btn btn-primary btn-sm"><i class="fa-solid fa-stethoscope"></i> In Consultation</a>
            `;
        } else if (apt.status === 'waiting') {
            actionButtons = `
                <button type="button" class="btn btn-outline btn-sm" onclick="callPatientDirectly(${apt.id}, ${apt.token_number})">
                    <i class="fa-solid fa-bullhorn"></i> Call Now
                </button>
            `;
        } else if (apt.status === 'completed') {
            actionButtons = `<span style="color: var(--success); font-weight: 600; font-size: 0.85rem;"><i class="fa-solid fa-check-circle"></i> Done</span>`;
        } else {
            actionButtons = `<span style="color: var(--slate-400); font-size: 0.85rem;">${apt.status}</span>`;
        }

        html += `
            <tr ${rowClass}>
                <td><strong class="patient-token-badge">#${apt.token_number}</strong></td>
                <td>
                    <div style="font-weight: 700; color: var(--slate-900);">${escapeHtml(apt.patient_name)}</div>
                    <div style="font-size: 0.8rem; color: var(--slate-500);">${escapeHtml(apt.patient_phone || 'No phone')}</div>
                </td>
                <td><span style="font-size: 0.85rem; color: var(--slate-600);"><i class="fa-regular fa-clock"></i> ${apt.estimated_time_formatted || apt.estimated_time}</span></td>
                <td>${statusBadge}</td>
                <td style="max-width: 220px; font-size: 0.85rem; color: var(--slate-600);" class="text-truncate">${escapeHtml(apt.symptoms || 'None reported')}</td>
                <td>${actionButtons}</td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

/**
 * Doctor Action: Call Next Patient Atomically
 */
async function callNextPatient(doctorId, date, csrfToken) {
    if (!confirm('Call the next waiting patient in queue?')) return;

    try {
        const formData = new FormData();
        formData.append('doctor_id', doctorId);
        formData.append('date', date);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('../api/call_next.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            if (result.appointment_id) {
                // Redirect directly to consultation room with video
                window.location.href = `consultation.php?id=${result.appointment_id}`;
            } else {
                location.reload();
            }
        } else {
            alert(result.message || 'Unable to call next patient.');
        }
    } catch (err) {
        alert('Communication error with server.');
    }
}

/**
 * Doctor Action: Call Specific Patient Directly
 */
async function callPatientDirectly(appointmentId, tokenNumber) {
    if (!confirm(`Call Token #${tokenNumber} now? This will set this patient to Serving.`)) return;
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const formData = new FormData();
        formData.append('action', 'set_serving');
        formData.append('appointment_id', appointmentId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('../api/update_appointment.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            window.location.href = `consultation.php?id=${appointmentId}`;
        } else {
            alert(result.message || 'Error updating appointment status.');
        }
    } catch (err) {
        alert('Network request failed.');
    }
}

/**
 * Utility to escape HTML
 */
function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Web Audio API Pleasant Hospital Notification Chime
 * Synthesizes two harmonic bell tones (C5 & E5) without external media files
 */
function playHospitalChime() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const now = ctx.currentTime;

        // Tone 1: C5 (523.25 Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(523.25, now);
        gain1.gain.setValueAtTime(0.18, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.6);

        // Tone 2: E5 (659.25 Hz)
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(659.25, now + 0.15);
        gain2.gain.setValueAtTime(0.22, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.9);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.15);
        osc2.stop(now + 0.9);
    } catch (e) {
        // AudioContext suspended or not permitted before user interaction
    }
}


