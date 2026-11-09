# DOCTOR CONNECT - Interactive Testing & Evaluation Walkthrough

Follow this step-by-step evaluation guide to test all key features of DOCTOR CONNECT, including **Race-Safe Booking**, **3-Second Live Queue Sync**, **Doctor Call Next**, and **Jitsi Meet Encrypted Video Consultation**.

---

## Part 1: Quick Server Launch (20 Seconds)

1. **Start MySQL**:
   Open XAMPP Control Panel and start **MySQL**.
2. **Launch DOCTOR CONNECT**:
   In PowerShell, navigate to this project folder and run:
   ```powershell
   .\run.ps1
   ```
   *(Or in CMD: `run.bat`)*
3. **Initialize Database**:
   The setup page will open automatically at:
   ```text
   http://localhost:8000/setup.php
   ```
   Click **"Initialize Database & Seed Data Now"**. (All 7 tables and test seed appointments are created with green checkmarks).

---

## Part 2: Dual-Screen Live Queue Test (The "Showcase" Test)

To observe real-time 3-second queue polling and video activation in action:

### Window 1: Doctor View
1. Open a regular browser window and go to:
   ```text
   http://localhost:8000/auth/login.php
   ```
2. Click the quick button **Doctor** (or enter `sarah.jenkins@doctorconnect.com` / `Doctor@123`).
3. You will land on the **Doctor Consultation Desk**.
4. Notice that **Token #1** (`john.doe@example.com`) is currently in consultation.

### Window 2: Patient View
1. Open an **Incognito / Private browser window** (so sessions don't clash).
2. Go to:
   ```text
   http://localhost:8000/auth/login.php
   ```
3. Enter credentials for **Patient Jane Smith** (who holds **Token #2 (Waiting)**):
   - Email: `jane.smith@example.com`
   - Password: `Patient@123`
4. On Jane's dashboard, click **"View Live Queue"** (or open `patient/queue.php`).
5. Notice:
   - **Now Serving**: `#1`
   - **Next in Line**: `#2`
   - **Your Status**: `1 token ahead of you`
   - **Video Button**: Displays *"Video Room Opens When Your Turn Arrives"* (locked).

### The Trigger:
1. Switch back to **Window 1 (Doctor)**.
2. Click the blue button: **"Call Next Patient"** (or confirm the prompt).
3. Switch to **Window 2 (Patient Jane Smith)**.
4. **Within 3 seconds without refreshing the page**:
   - The serving number animates to **`#2`**!
   - A pleasant hospital chime tone plays automatically!
   - Status badge updates to: **"It's Your Turn Now!"**
   - The green button **"Join Video Consultation Now"** pulses and unlocks!
5. Click **"Join Video Consultation Now"** in both windows to join the encrypted Jitsi Meet room simultaneously!

---

## Part 3: Clinical Examination & Digital Prescription Test

1. In the **Doctor Consultation Window**, type in:
   - **Clinical Diagnosis**: *"Mild seasonal allergic rhinitis, lungs clear, vitals normal."*
   - **Medical Prescription**:
     ```text
     1. Tab. Cetirizine 10mg - 1 tab at bedtime x 5 days
     2. Fluticasone Nasal Spray - 1 spray each nostril daily
     3. Drink warm fluids and steam inhalation
     ```
2. Click **"Complete & Sign Consultation"**.
3. Now switch to the **Patient Window**, go to **"My Consultations"** (`patient/appointments.php`), and click:
   👉 **"View / Print Official Prescription Slip"**.
4. A professional, hospital-branded medical prescription opens with doctor credentials, patient age/blood group, Rx symbol, digital signature hash, and a **"Print / Save as PDF"** button!

---

## Part 4: Race-Safe Token Booking Test

1. In the Patient portal, click **"Book Token"** (`patient/book_appointment.php`).
2. Select any doctor and tomorrow's date.
3. Notice the live green slot box:
   - Automatically queries `api/get_doctor_slots.php`.
   - Shows doctor consultation hours (e.g. 9:00 AM - 1:00 PM).
   - Displays Next Available Token (e.g. `#1`) and exact calculated estimated time.
   - Shows remaining tokens for the day.
4. Enter symptoms and click **"Confirm & Book Token Number"**.
5. The database transaction executes an atomic row lock (`SELECT ... FOR UPDATE` on `daily_token_sequences`), ensuring zero duplicate tokens.

---

## Part 5: Administrator Portal & CSV Export Test

1. Log in as Admin at `http://localhost:8000/auth/login.php` (Click **Admin** or use `admin@doctorconnect.com` / `Admin@123`).
2. **Admin Analytics (`admin/dashboard.php`)**:
   - Inspect platform metrics (active doctors, registered patients, today's appointments).
   - Review live audit logs recording every token change and transition.
3. **Manage Doctors (`admin/doctors.php`)**:
   - Click **"Add New Doctor"** to register a new physician (automatically assigns default weekly working schedules).
   - Edit consultation fees, experience, and room numbers.
4. **Manage Schedules (`admin/schedules.php`)**:
   - Select any doctor and toggle their working days, start/end hours, and maximum token capacities.
5. **Reports & CSV Export (`admin/reports.php`)**:
   - Select a date range.
   - Click **"Export CSV"**. A file named `doctor_connect_report_YYYYMMDD_HHMMSS.csv` downloads immediately with full consultation and fee metadata.

---

## Part 6: Summary of All URLs

- **Setup & Diagnostics**: `http://localhost:8000/setup.php`
- **Public Homepage**: `http://localhost:8000/index.php`
- **Login Portal**: `http://localhost:8000/auth/login.php`
- **Patient Dashboard**: `http://localhost:8000/patient/dashboard.php`
- **Doctor Desk**: `http://localhost:8000/doctor/dashboard.php`
- **Admin Center**: `http://localhost:8000/admin/dashboard.php`

