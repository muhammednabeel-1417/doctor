# DOCTOR CONNECT
> **Token-Based Online Doctor Consultation Platform**  
> Built with HTML5, CSS3, JavaScript, PHP 8.1+ (PDO), MySQL, and Jitsi Meet.

---

## Table of Contents
1. [Overview & Highlights](#overview--highlights)
2. [Key Architecture & Features](#key-architecture--features)
   - [Race-Safe Token Generation](#1-race-safe-token-generation)
   - [Real-Time 3-Second Live Queue Engine](#2-real-time-3-second-live-queue-engine)
   - [Jitsi Meet Encrypted Video Consultations](#3-jitsi-meet-encrypted-video-consultations)
   - [Role-Based Access Control (RBAC)](#4-role-based-access-control-rbac)
3. [XAMPP Setup & Installation Guide](#xampp-setup--installation-guide)
   - [Step 1: Move Project to XAMPP htdocs](#step-1-move-project-to-xampp-htdocs)
   - [Step 2: Start Apache & MySQL](#step-2-start-apache--mysql)
   - [Step 3: Database Setup (Two Easy Methods)](#step-3-database-setup-two-easy-methods)
     - [Option A: 1-Click Browser Setup (Recommended)](#option-a-1-click-browser-setup-recommended)
     - [Option B: Manual Import via phpMyAdmin](#option-b-manual-import-via-phpmyadmin)
   - [Step 4: Database Credentials Configuration](#step-4-database-credentials-configuration)
   - [Step 5: Launch in Browser](#step-5-launch-in-browser)
4. [Default Seed Credentials for Evaluation](#default-seed-credentials-for-evaluation)
5. [Role-by-Role Feature Walkthrough & Testing](#role-by-role-feature-walkthrough--testing)
   - [A. Patient Module](#a-patient-module)
   - [B. Doctor Module](#b-doctor-module)
   - [C. Admin Module](#c-admin-module)
6. [API Endpoints Reference](#api-endpoints-reference)
7. [Project Directory Structure](#project-directory-structure)
8. [Security Implementation](#security-implementation)

---

## Overview & Highlights

**DOCTOR CONNECT** eliminates long, crowded hospital waiting rooms by providing a serialized, race-safe token management queue system. Patients book doctor appointment tokens, monitor queue movement in real-time through 3-second live polling, and enter encrypted video consultation rooms powered by **Jitsi Meet** the moment their turn arrives.

- **Clean Medical Aesthetic**: White & light-blue theme (`#0284c7`, `#0369a1`, `#f0f9ff`), responsive sidebar, pulse status indicators, and clean typography.
- **Three Distinct Portals**: Full-featured dashboards for **Patient**, **Doctor**, and **Administrator**.
- **Concurrency Protection**: Race-safe row locking (`SELECT ... FOR UPDATE`) prevents duplicate tokens even under simultaneous patient bookings.
- **Live Queue Polling**: Asynchronous JavaScript polling refreshes serving and next tokens every 3 seconds without full page refreshes.

---

## Key Architecture & Features

### 1. Race-Safe Token Generation
To prevent race conditions when two or more patients book the same doctor on the same date at the exact same millisecond:
1. Operations execute within a **MySQL PDO Transaction** (`beginTransaction()` / `commit()`).
2. Row-level lock on `daily_token_sequences` using `SELECT current_token FROM daily_token_sequences WHERE doctor_id = ? AND sequence_date = ? FOR UPDATE`.
3. Token numbers are strictly sequential: Token #1, Token #2, Token #3...
4. Second database-layer safeguard enforces `UNIQUE KEY unique_doc_date_token (doctor_id, appointment_date, token_number)`.
5. Dynamic Estimated Time Calculation:
   $$\text{Estimated Time} = \text{Session Start Time} + (\text{Token Number} - 1) \times \text{Average Consultation Minutes}$$

### 2. Real-Time 3-Second Live Queue Engine
- The client-side poller in `assets/js/script.js` queries `api/queue_status.php` every 3,000 milliseconds.
- Visual pulse dot and color-coded status badges:
  - `waiting`: Amber badge
  - `serving`: Pulsing green badge
  - `completed`: Blue badge
  - `no_show`: Red badge
- Video consultation buttons dynamically unlock when the patient's token is **Next** or **Currently Serving**.

### 3. Jitsi Meet Encrypted Video Consultations
- Each appointment receives an unpredictable, cryptographically generated room name:
  `dc_room_` + 20-byte random hex + timestamp.
- Video rooms are embedded directly using Jitsi's External iframe API with camera, microphone, and screen-sharing permissions.
- Integrated doctor sidebar allows the physician to review symptoms, type live diagnostic notes, write prescriptions, and finalize consultations with one click.

### 4. Role-Based Access Control (RBAC)
- Enforced on all dashboard pages via `includes/auth_check.php` and `require_role()`.
- Unauthenticated or unauthorized visitors are immediately redirected to `auth/login.php` with informative flash warnings.

---

## XAMPP Setup & Installation Guide

### Step 1: Move Project to XAMPP htdocs
Copy or clone the project folder into your XAMPP web root directory:
```text
C:\xampp\htdocs\doctorconnected\
```

### Step 2: Start Apache & MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Ensure both services show green status indicators.

### Step 3: Database Setup (Two Easy Methods)

#### Option A: 1-Click Browser Setup (Recommended)
1. Open your web browser and navigate to:
   ```text
   http://localhost/doctorconnected/setup.php
   ```
2. The diagnostic tool will verify your PHP version, check the PDO MySQL driver, and test MySQL connectivity.
3. Click the **"Initialize Database & Seed Data Now"** button.
4. The system will automatically create the `doctor_connect` database, construct all 7 relational tables, create foreign keys, configure token sequences, and seed test accounts with native PHP bcrypt password hashes!

#### Option B: Manual Import via phpMyAdmin
1. Open phpMyAdmin in your browser:
   ```text
   http://localhost/phpmyadmin/
   ```
2. Click the **Import** tab in the top navigation bar.
3. Click **Choose File** and select:
   ```text
   C:\xampp\htdocs\doctorconnected\database\doctor_connect.sql
   ```
4. Click **Go** at the bottom of the page.
5. phpMyAdmin will execute the script, create the `doctor_connect` database, all tables, and insert default test data.

### Step 4: Database Credentials Configuration
By default, XAMPP uses user `root` with no password. If your local MySQL setup uses custom credentials, edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'doctor_connect');
define('DB_USER', 'root');
define('DB_PASS', ''); // Enter your password if set
```

### Step 5: Launch in Browser
Open the public homepage in your browser:
```text
http://localhost/doctorconnected/index.php
```

---

## Default Seed Credentials for Evaluation

The database is pre-seeded with accounts for immediate testing across all 3 roles:

| Role | Email Address | Password | Permissions & Features |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@doctorconnect.com` | `Admin@123` | System metrics, Add/Edit/Delete doctors, Patient management, Schedules, All appointments, CSV export |
| **Doctor** | `sarah.jenkins@doctorconnect.com` | `Doctor@123` | Cardiology queue, Call Next, Consultation notes, Prescription writer, Jitsi video room |
| **Doctor** | `marcus.chen@doctorconnect.com` | `Doctor@123` | Neurology desk, weekly availability slots, daily queue |
| **Doctor** | `emily.taylor@doctorconnect.com` | `Doctor@123` | Pediatrics clinic, patient medical history |
| **Doctor** | `alexander.patel@doctorconnect.com` | `Doctor@123` | Dermatology clinic, token capacity limits |
| **Patient** | `john.doe@example.com` | `Patient@123` | Has active demo appointment **Token #1 (Serving)** today |
| **Patient** | `jane.smith@example.com` | `Patient@123` | Has active demo appointment **Token #2 (Waiting/Next)** today |
| **Patient** | `robert.brown@example.com` | `Patient@123` | Has demo appointment **Token #3 (Waiting)** today |

> **Tip**: The login page (`auth/login.php`) contains one-click test buttons that prefill credentials automatically.

---

## Role-by-Role Feature Walkthrough & Testing

### A. Patient Module
1. **Registration & Login**:
   - Navigate to `auth/register.php` to create a new patient account with name, email, phone, blood group, and password.
   - Or log in with `john.doe@example.com` / `Patient@123`.
2. **Browse Doctors (`patient/doctors.php`)**:
   - Filter by medical specialization (Cardiology, Neurology, Pediatrics, Dermatology) or search by doctor name.
   - Review consultation fees, qualifications, experience, and available consultation days.
3. **Book Appointment Token (`patient/book_appointment.php`)**:
   - Select a doctor and date.
   - Watch the dynamic slot preview check capacity and display the next available token number and estimated consultation time.
   - Enter symptoms and confirm booking.
   - The transaction locks the sequence and assigns the next sequential token without gaps or duplicates.
4. **Live Queue Tracking (`patient/queue.php`)**:
   - Displays **Now Serving Token**, **Next in Line**, **Patients Waiting**, and your current position.
   - Polling updates every 3 seconds.
   - As soon as your token is Next or Serving, the **"Join Video Consultation Now"** button lights up.
5. **Video Consultation (`patient/consultation.php`)**:
   - Connects to Jitsi Meet with audio/video.
   - Displays doctor details, reported symptoms, and appointment reference.
6. **Appointment History & Prescriptions (`patient/appointments.php`)**:
   - Access completed consultations to read clinical notes and digital prescriptions written by the doctor.

### B. Doctor Module
1. **Consultation Desk (`doctor/dashboard.php`)**:
   - Log in as `sarah.jenkins@doctorconnect.com` / `Doctor@123`.
   - View today's total booked tokens, waiting patients, and currently serving patient.
   - Click **"Call Next Patient"** to automatically transition the next waiting token to `serving`.
2. **Clinical Workspace (`doctor/consultation.php`)**:
   - View embedded Jitsi video call on the left.
   - View patient vitals, blood group, and symptoms on the right.
   - Write clinical observations and prescription details.
   - Click **"Complete & Sign Consultation"** to save notes, finalize the session, and notify the patient.
   - Option to mark absent patients as **No-Show**.
3. **Weekly Schedule Management (`doctor/schedule.php`)**:
   - Enable or disable availability for each day of the week (Monday to Sunday).
   - Configure session start time, session end time, and maximum daily token capacity.
   - Set average consultation duration (e.g. 15 minutes), which updates future token estimated times.
4. **Patient Medical History (`doctor/patients.php`)**:
   - Search past patient records, previous diagnoses, and prescriptions.

### C. Admin Module
1. **Admin Analytics (`admin/dashboard.php`)**:
   - Log in as `admin@doctorconnect.com` / `Admin@123`.
   - Monitor total doctors, registered patients, today's appointments, and live queue status across all clinics.
   - Real-time audit feed logs every queue event (token creation, call next, completed, no-show).
2. **Manage Doctors (`admin/doctors.php`)**:
   - Register new doctors (automatically seeds default Monday-Friday working schedules).
   - Edit doctor credentials, consultation fees, experience, and active/inactive status.
   - Safely delete doctors.
3. **Manage Patients (`admin/patients.php`)**:
   - Search and inspect patient profiles and consultation counts.
4. **Doctor Schedules (`admin/schedules.php`)**:
   - Select any physician to inspect and override their working hours and daily capacity limits.
5. **All Appointments & Queue Oversight (`admin/appointments.php`)**:
   - View all platform appointments with multi-criteria filters (doctor, date, status, search).
   - Override appointment status (waiting, serving, completed, no-show, cancelled).
   - Direct link to join or inspect any video room.
6. **Reports & CSV Export (`admin/reports.php`)**:
   - Select date ranges and filter by doctor or status.
   - View revenue summaries and absenteeism rates.
   - Click **"Export CSV"** to stream and download a standard CSV spreadsheet formatted with complete consultation metadata.

---

## API Endpoints Reference

| Endpoint | Method | Parameters | Description |
| :--- | :--- | :--- | :--- |
| `api/queue_status.php` | `GET` | `doctor_id`, `date`, `appointment_id` (opt) | Returns live queue metrics: serving token, next token, waiting count, patient position, and video eligibility. Polled every 3s. |
| `api/call_next.php` | `POST` | `doctor_id`, `date`, `csrf_token` | Atomically concludes current serving token, sets next waiting patient to serving, updates last served token, and triggers notification. |
| `api/get_doctor_slots.php` | `GET` | `doctor_id`, `date` | Returns schedule hours, remaining capacity, next token number, and calculated estimated consultation time. |
| `api/update_appointment.php` | `POST` | `action`, `appointment_id`, `csrf_token`, `notes`, `prescription` | Updates appointment status (`set_serving`, `complete`, `no_show`, `save_notes`). |

---

## Project Directory Structure

```text
doctorconnected/
├── admin/
│   ├── appointments.php      # Monitor all appointments & override statuses
│   ├── dashboard.php         # Admin metrics, queue summaries, audit logs
│   ├── doctors.php           # Add, edit, delete & list physicians (CRUD)
│   ├── patients.php          # Patient directory & booking counts
│   ├── reports.php           # Analytics & downloadable CSV exporter
│   └── schedules.php         # Manage doctor schedules & capacity
├── api/
│   ├── call_next.php         # Atomic call-next token transition
│   ├── get_doctor_slots.php  # Doctor availability & estimated time calculation
│   ├── queue_status.php      # 3-second live polling JSON endpoint
│   └── update_appointment.php# Update status, save notes & prescription
├── assets/
│   ├── css/
│   │   └── style.css         # Modern medical theme (white & sky-blue)
│   ├── js/
│   │   └── script.js         # Live polling engine, timers, queue updates
│   └── images/
├── auth/
│   ├── login.php             # Unified login with demo account pre-fillers
│   ├── logout.php            # Secure session destruction
│   └── register.php          # Patient registration with input validation
├── config/
│   └── database.php          # PDO connection handler & constants
├── database/
│   └── doctor_connect.sql    # Full schema, constraints, sequences & seed data
├── doctor/
│   ├── consultation.php      # Jitsi video stream + live clinical notes & Rx
│   ├── dashboard.php         # Active desk, call next button, today's queue
│   ├── patients.php          # Patient medical consultation archive
│   ├── profile.php           # Doctor credentials & password editor
│   ├── queue.php             # Dedicated full-screen live queue monitor
│   └── schedule.php          # Weekly schedule & consultation duration editor
├── includes/
│   ├── auth_check.php        # Role-based access control guard
│   ├── footer.php            # Common dashboard footer
│   ├── functions.php         # CSRF, XSS escaping, estimated time formulas
│   ├── header.php            # Common navigation header & alerts
│   └── sidebar.php           # Role-specific sidebar navigation
├── patient/
│   ├── appointments.php      # Patient consultation history & prescriptions
│   ├── book_appointment.php # Race-safe token booking with row locking
│   ├── consultation.php      # Patient Jitsi Meet video consultation room
│   ├── dashboard.php         # Active token indicator, stats, recent visits
│   ├── doctors.php           # Browse and filter doctors by specialization
│   ├── profile.php           # Patient profile & password change
│   └── queue.php             # Live queue tracker with conditional video button
├── index.php                 # Public landing page with features & doctor showcase
├── setup.php                 # 1-Click browser installation & diagnostic tool
└── README.md                 # Complete documentation & setup manual
```

---

## Security Implementation

- **Password Hashing**: Stored with PHP's native `password_hash($password, PASSWORD_DEFAULT)` and verified using `password_verify()`.
- **Prepared Statements**: All database operations execute through PDO prepared statements with parameterized inputs to eliminate SQL Injection.
- **CSRF Defense**: Forms generate a cryptographically random token (`csrf_token()`) validated via `validate_csrf()` before processing state changes.
- **XSS Prevention**: User-supplied values are escaped with `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`.
- **Session Protection**: Sessions enforce `cookie_httponly`, `use_only_cookies`, `cookie_samesite = 'Lax'`, and regenerate session IDs on authentication.
- **Video Privacy**: Jitsi rooms use cryptographically random 20-character nonces (`dc_room_...`) preventing guessing or eavesdropping.
- **Double Booking Guard**: Checked programmatically and enforced by `UNIQUE KEY unique_doc_date_token`.

---

## License & Credits
Developed for **DOCTOR CONNECT** — Token-Based Online Doctor Consultation Platform.  
Built for seamless local execution on **XAMPP (Apache + MySQL + PHP 8.1+)**.

