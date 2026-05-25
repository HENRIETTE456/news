<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'patient';
$page = basename($_SERVER['PHP_SELF']);

// Get patient_id if user is patient
$patient_id = null;
if($role == 'patient') {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    if($patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    } else {
        // Create patient record if not exists
        $userStmt = $pdo->prepare("SELECT full_name, phone, email FROM users WHERE id = ?");
        $userStmt->execute([$user_id]);
        $userData = $userStmt->fetch();
        
        $nameParts = explode(' ', $userData['full_name'], 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';
        
        $insert = $pdo->prepare("
            INSERT INTO patients (user_id, first_name, last_name, phone, status) 
            VALUES (?, ?, ?, ?, 'active')
        ");
        $insert->execute([$user_id, $firstName, $lastName, $userData['phone']]);
        $patient_id = $pdo->lastInsertId();
        $_SESSION['patient_id'] = $patient_id;
    }
}

// Get doctor_id if user is doctor
$doctor_id = null;
if($role == 'doctor') {
    $doctor_id = $user_id;
}

/* =========================
   STATISTICS BASED ON ROLE - FIXED STATUS COUNTS
   Note: Appointment status can be: 'scheduled', 'confirmed', 'completed', 'cancelled', 'pending'
========================= */

if($role == 'admin' || $role == 'manager') {
    // Admin/Manager stats - all data
    $patientCount = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $doctorCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
    $appointmentCount = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
    
    // FIXED: Count all scheduled + confirmed as "Scheduled/Active"
    $scheduledCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status IN ('scheduled', 'confirmed')")->fetchColumn();
    $completedCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();
    $cancelledCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'")->fetchColumn();
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $todayCount = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
    
    // Latest appointments
    $appointments = $pdo->query("
        SELECT a.*, p.first_name, p.last_name, u.full_name AS doctor_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        ORDER BY a.appointment_date DESC, a.start_time DESC
        LIMIT 10
    ")->fetchAll();
    
    // Recent patients
    $recentPatients = $pdo->query("
        SELECT * FROM patients 
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Recent doctors
    $recentDoctors = $pdo->query("
        SELECT * FROM users 
        WHERE role='doctor' 
        ORDER BY id DESC 
        LIMIT 5
    ")->fetchAll();
    
} elseif($role == 'doctor') {
    // Doctor stats - only their own data
    $myPatients = $pdo->prepare("
        SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?
    ");
    $myPatients->execute([$doctor_id]);
    $patientCount = $myPatients->fetchColumn();
    
    $appointmentCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ?");
    $appointmentCount->execute([$doctor_id]);
    $appointmentCount = $appointmentCount->fetchColumn();
    
    // FIXED: Count scheduled + confirmed as "Scheduled/Active"
    $scheduledCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('scheduled', 'confirmed')");
    $scheduledCount->execute([$doctor_id]);
    $scheduledCount = $scheduledCount->fetchColumn();
    
    $completedCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'completed'");
    $completedCount->execute([$doctor_id]);
    $completedCount = $completedCount->fetchColumn();
    
    $cancelledCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'cancelled'");
    $cancelledCount->execute([$doctor_id]);
    $cancelledCount = $cancelledCount->fetchColumn();
    
    $todayCount = $pdo->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE doctor_id = ? AND appointment_date = CURDATE()
    ");
    $todayCount->execute([$doctor_id]);
    $todayCount = $todayCount->fetchColumn();
    
    // Today's appointments for doctor
    $todayAppointments = $pdo->prepare("
        SELECT a.*, p.first_name, p.last_name, p.phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
        ORDER BY a.start_time
    ");
    $todayAppointments->execute([$doctor_id]);
    $todayAppointments = $todayAppointments->fetchAll();
    
    // Upcoming appointments for doctor
    $upcomingAppointments = $pdo->prepare("
        SELECT a.*, p.first_name, p.last_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND a.appointment_date > CURDATE() AND a.status IN ('scheduled', 'confirmed')
        ORDER BY a.appointment_date, a.start_time
        LIMIT 5
    ");
    $upcomingAppointments->execute([$doctor_id]);
    $upcomingAppointments = $upcomingAppointments->fetchAll();
    
} else {
    // Patient stats
    $appointmentCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $appointmentCount->execute([$patient_id]);
    $appointmentCount = $appointmentCount->fetchColumn();
    
    // FIXED: Count scheduled + confirmed as "Upcoming"
    $scheduledCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('scheduled', 'confirmed') AND appointment_date >= CURDATE()");
    $scheduledCount->execute([$patient_id]);
    $scheduledCount = $scheduledCount->fetchColumn();
    
    $completedCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'");
    $completedCount->execute([$patient_id]);
    $completedCount = $completedCount->fetchColumn();
    
    $cancelledCount = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'cancelled'");
    $cancelledCount->execute([$patient_id]);
    $cancelledCount = $cancelledCount->fetchColumn();
    
    // Upcoming appointments for patient - includes both scheduled and confirmed
    $upcomingAppointments = $pdo->prepare("
        SELECT a.*, u.full_name as doctor_name, u.specialization
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('scheduled', 'confirmed')
        ORDER BY a.appointment_date, a.start_time
        LIMIT 5
    ");
    $upcomingAppointments->execute([$patient_id]);
    $upcomingAppointments = $upcomingAppointments->fetchAll();
    
    // Past appointments for patient
    $pastAppointments = $pdo->prepare("
        SELECT a.*, u.full_name as doctor_name
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ? AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled'))
        ORDER BY a.appointment_date DESC
        LIMIT 5
    ");
    $pastAppointments->execute([$patient_id]);
    $pastAppointments = $pastAppointments->fetchAll();
    
    // Get patient details
    $patientInfo = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patientInfo->execute([$patient_id]);
    $patientInfo = $patientInfo->fetch();
    
    // Get notifications
    $notifications = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC LIMIT 5
    ");
    $notifications->execute([$user_id]);
    $notifications = $notifications->fetchAll();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Clinic Dashboard - <?php echo ucfirst($role); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        margin: 0;
        background: #f0f2f5;
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    /* SIDEBAR */
    .sidebar {
        position: fixed;
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        padding: 20px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    .logo {
        text-align: center;
        color: #22c55e;
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #333;
    }
    
    .logo i {
        font-size: 32px;
        display: block;
        margin-bottom: 10px;
    }
    
    /* MENU */
    .sidebar a {
        display: block;
        padding: 12px 15px;
        color: #cbd5e1;
        text-decoration: none;
        margin: 5px 0;
        border-radius: 10px;
        transition: all 0.3s;
    }
    
    .sidebar a:hover {
        background: #1f2937;
        transform: translateX(5px);
    }
    
    .sidebar a.active {
        background: #22c55e;
        color: white;
    }
    
    .sidebar a i {
        margin-right: 10px;
        width: 25px;
    }
    
    /* MAIN */
    .main-content {
        margin-left: 260px;
        padding: 20px;
    }
    
    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        color: white;
    }
    
    /* CARDS */
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        color: #333;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.3s;
        border-left: 4px solid;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
    }
    
    .stat-card h3 {
        font-size: 2rem;
        margin: 10px 0;
        font-weight: bold;
    }
    
    .stat-card .icon {
        float: right;
        font-size: 3rem;
        opacity: 0.3;
    }
    
    .card-blue { border-left-color: #2563eb; }
    .card-green { border-left-color: #16a34a; }
    .card-orange { border-left-color: #f59e0b; }
    .card-red { border-left-color: #ef4444; }
    .card-purple { border-left-color: #8b5cf6; }
    
    /* TABLES */
    .custom-table {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .custom-table thead {
        background: #1a1a2e;
        color: white;
    }
    
    .custom-table th, .custom-table td {
        padding: 12px 15px;
    }
    
    /* FIXED BADGE STYLES */
    .badge-scheduled, .badge-confirmed { background: #0d6efd; }
    .badge-completed { background: #198754; }
    .badge-cancelled { background: #dc3545; }
    .badge-pending { background: #ffc107; color: #000; }
    
    /* RESPONSIVE */
    @media (max-width: 768px) {
        .sidebar { width: 70px; }
        .sidebar span { display: none; }
        .sidebar a i { margin-right: 0; }
        .main-content { margin-left: 70px; }
    }
    
    .welcome-text {
        font-size: 1.1rem;
        margin-top: 5px;
    }
    
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1100;
    }
</style>
</head>
<body>

<!-- Toast Notifications -->
<div class="toast-container">
    <?php if(isset($_SESSION['success'])): ?>
    <div class="toast show" data-bs-autohide="true" data-bs-delay="3000">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto"><i class="fas fa-check-circle"></i> Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- =========================
   SIDEBAR
========================= -->
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-hospital"></i>
        <span>Clinic System</span>
    </div>
    
    <a href="dashboard.php" class="active">
        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
    </a>
    
    <?php if($role == 'admin' || $role == 'manager'): ?>
    <a href="patients.php">
        <i class="fas fa-users"></i> <span>Patients</span>
    </a>
    <a href="doctors.php">
        <i class="fas fa-user-md"></i> <span>Doctors</span>
    </a>
    <a href="appointments.php">
        <i class="fas fa-calendar-check"></i> <span>Appointments</span>
    </a>
    <?php endif; ?>
    
    <?php if($role == 'doctor'): ?>
    <a href="my_schedule.php">
        <i class="fas fa-calendar-alt"></i> <span>My Schedule</span>
    </a>
    <a href="set_working_hours.php">
        <i class="fas fa-clock"></i> <span>Working Hours</span>
    </a>
    <?php endif; ?>
    
    <?php if($role == 'patient'): ?>
    <a href="book_appointment.php">
        <i class="fas fa-calendar-plus"></i> <span>Book Appointment</span>
    </a>
    <a href="my_appointments.php">
        <i class="fas fa-list"></i> <span>My Appointments</span>
    </a>
    <a href="medical_records.php">
        <i class="fas fa-notes-medical"></i> <span>Medical Records</span>
    </a>
    <?php endif; ?>
    
    <a href="profile.php">
        <i class="fas fa-user-circle"></i> <span>Profile</span>
    </a>
    
    <a href="logout.php">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
</div>

<!-- =========================
   MAIN CONTENT
========================= -->
<div class="main-content">
    
    <div class="header">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <div class="welcome-text">
            <i class="fas fa-hand-wave"></i> Welcome back, <strong><?php echo htmlspecialchars($user_name); ?></strong>! 
            <br><small>Role: <?php echo ucfirst($role); ?></small>
        </div>
    </div>
    
    <!-- =========================
         STATISTICS CARDS - FIXED DISPLAY
    ========================= -->
    <div class="row">
        <?php if($role == 'admin' || $role == 'manager'): ?>
        <div class="col-md-3">
            <div class="stat-card card-blue">
                <i class="fas fa-users icon"></i>
                <small>Total Patients</small>
                <h3><?php echo $patientCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-green">
                <i class="fas fa-user-md icon"></i>
                <small>Total Doctors</small>
                <h3><?php echo $doctorCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-orange">
                <i class="fas fa-calendar icon"></i>
                <small>Total Appointments</small>
                <h3><?php echo $appointmentCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-purple">
                <i class="fas fa-calendar-day icon"></i>
                <small>Today's Appointments</small>
                <h3><?php echo $todayCount; ?></h3>
            </div>
        </div>
        <?php elseif($role == 'doctor'): ?>
        <div class="col-md-3">
            <div class="stat-card card-blue">
                <i class="fas fa-users icon"></i>
                <small>My Patients</small>
                <h3><?php echo $patientCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-green">
                <i class="fas fa-calendar-check icon"></i>
                <small>Total Appointments</small>
                <h3><?php echo $appointmentCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-orange">
                <i class="fas fa-hourglass-half icon"></i>
                <small>Scheduled/Active</small>
                <h3><?php echo $scheduledCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-purple">
                <i class="fas fa-calendar-day icon"></i>
                <small>Today's Appointments</small>
                <h3><?php echo $todayCount; ?></h3>
            </div>
        </div>
        <?php else: ?>
        <div class="col-md-3">
            <div class="stat-card card-blue">
                <i class="fas fa-calendar icon"></i>
                <small>Total Appointments</small>
                <h3><?php echo $appointmentCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-green">
                <i class="fas fa-hourglass-half icon"></i>
                <small>Upcoming</small>
                <h3><?php echo $scheduledCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-orange">
                <i class="fas fa-check-circle icon"></i>
                <small>Completed</small>
                <h3><?php echo $completedCount; ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card-red">
                <i class="fas fa-times-circle icon"></i>
                <small>Cancelled</small>
                <h3><?php echo $cancelledCount; ?></h3>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- =========================
         APPOINTMENT DETAILS BY ROLE
    ========================= -->
    
    <?php if($role == 'admin' || $role == 'manager'): ?>
    <!-- ADMIN: Latest Appointments -->
    <div class="custom-table mt-4">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach($appointments as $a): ?>
                <tr class="<?php echo ($a['status'] == 'cancelled') ? 'table-danger' : (($a['status'] == 'completed') ? 'table-success' : ''); ?>">
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                    <td>Dr. <?php echo htmlspecialchars($a['doctor_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($a['start_time'])); ?></td>
                    <td>
                        <?php 
                        $statusClass = '';
                        $statusText = ucfirst($a['status']);
                        if($a['status'] == 'scheduled' || $a['status'] == 'confirmed') {
                            $statusClass = 'badge-scheduled';
                        } elseif($a['status'] == 'completed') {
                            $statusClass = 'badge-completed';
                        } elseif($a['status'] == 'cancelled') {
                            $statusClass = 'badge-cancelled';
                        } else {
                            $statusClass = 'badge-pending';
                        }
                        ?>
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </span>
                </tr>
                <?php endforeach; ?>
                <?php if(count($appointments) == 0): ?>
                <tr><td colspan="6" class="text-center">No appointments found</td>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Quick Stats Row for Admin -->
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="stat-card card-green" style="border-left-color: #16a34a;">
                <i class="fas fa-check-circle icon"></i>
                <small>Completed Appointments</small>
                <h3><?php echo $completedCount; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card-red" style="border-left-color: #ef4444;">
                <i class="fas fa-times-circle icon"></i>
                <small>Cancelled Appointments</small>
                <h3><?php echo $cancelledCount; ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card-orange" style="border-left-color: #f59e0b;">
                <i class="fas fa-hourglass-half icon"></i>
                <small>Active (Scheduled/Confirmed)</small>
                <h3><?php echo $scheduledCount; ?></h3>
            </div>
        </div>
    </div>
    
    <?php elseif($role == 'doctor'): ?>
    <!-- DOCTOR: Today's Appointments -->
    <div class="custom-table mt-4">
        <div class="table-header p-3 bg-white border-bottom">
            <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Today's Appointments</h5>
        </div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Patient Name</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($todayAppointments as $apt): ?>
                <tr class="<?php echo ($apt['status'] == 'cancelled') ? 'table-danger' : (($apt['status'] == 'completed') ? 'table-success' : ''); ?>">
                    <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></td>
                    <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($apt['phone']); ?></td>
                    <td>
                        <?php 
                        $statusClass = ($apt['status'] == 'scheduled' || $apt['status'] == 'confirmed') ? 'badge-scheduled' : (($apt['status'] == 'completed') ? 'badge-completed' : 'badge-cancelled');
                        ?>
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo ucfirst($apt['status']); ?>
                        </span>
                    </span>
                    <td>
                        <?php if($apt['status'] == 'scheduled' || $apt['status'] == 'confirmed'): ?>
                        <button class="btn btn-sm btn-primary" onclick="startConsultation(<?php echo $apt['id']; ?>)">
                            <i class="fas fa-stethoscope"></i> Start
                        </button>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </span>
                </tr>
                <?php endforeach; ?>
                <?php if(count($todayAppointments) == 0): ?>
                <tr><td colspan="5" class="text-center">No appointments scheduled for today</td>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Upcoming Appointments -->
    <div class="custom-table mt-4">
        <div class="table-header p-3 bg-white border-bottom">
            <h5 class="mb-0"><i class="fas fa-calendar-week"></i> Upcoming Appointments</h5>
        </div>
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($upcomingAppointments as $apt): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></td>
                    <td><?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?></span>
                    <td><span class="badge badge-scheduled"><?php echo ucfirst($apt['status']); ?></span>
                </tr>
                <?php endforeach; ?>
                <?php if(count($upcomingAppointments) == 0): ?>
                <tr><td colspan="4" class="text-center">No upcoming appointments</td>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php else: ?>
    <!-- PATIENT: Upcoming Appointments -->
    <div class="custom-table mt-4">
        <div class="table-header p-3 bg-white border-bottom">
            <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($upcomingAppointments as $apt): ?>
                    <tr class="<?php echo ($apt['status'] == 'cancelled') ? 'table-danger' : ''; ?>">
                        <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                        <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></span>
                        <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></span>
                        <td>
                            <span class="badge badge-scheduled">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </span>
                        <td>
                            <a href="my_appointments.php?cancel=<?php echo $apt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this appointment?')">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </span>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($upcomingAppointments) == 0): ?>
                    <tr class="text-center">
                        <td colspan="6" class="py-4">
                            No upcoming appointments. 
                            <a href="book_appointment.php">Book one now!</a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Past Appointments -->
    <div class="custom-table mt-4">
        <div class="table-header p-3 bg-white border-bottom">
            <h5 class="mb-0"><i class="fas fa-history"></i> Past Appointments</h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pastAppointments as $apt): ?>
                    <tr class="<?php echo ($apt['status'] == 'cancelled') ? 'table-danger' : (($apt['status'] == 'completed') ? 'table-success' : ''); ?>">
                        <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></span>
                        <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></span>
                        <td>
                            <?php 
                            $statusClass = ($apt['status'] == 'completed') ? 'badge-completed' : (($apt['status'] == 'cancelled') ? 'badge-cancelled' : 'badge-pending');
                            ?>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($apt['status']); ?>
                            </span>
                        </span>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($pastAppointments) == 0): ?>
                    <tr class="text-center">
                        <td colspan="4" class="py-4">No past appointments</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Patient Profile Card -->
    <?php if($patientInfo): ?>
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="stat-card" style="border-left-color: #8b5cf6;">
                <i class="fas fa-id-card icon"></i>
                <small>My Profile</small>
                <p class="mb-0"><strong>Name:</strong> <?php echo htmlspecialchars($patientInfo['first_name'] . ' ' . $patientInfo['last_name']); ?></p>
                <p class="mb-0"><strong>Phone:</strong> <?php echo htmlspecialchars($patientInfo['phone']); ?></p>
                <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($patientInfo['address'] ?? 'Not set'); ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card" style="border-left-color: #22c55e;">
                <i class="fas fa-bell icon"></i>
                <small>Recent Notifications (<?php echo count($notifications); ?>)</small>
                <?php foreach(array_slice($notifications, 0, 3) as $notif): ?>
                <p class="mb-1 small"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(substr($notif['message'], 0, 50)); ?></p>
                <?php endforeach; ?>
                <?php if(count($notifications) == 0): ?>
                <p class="mb-0">No new notifications</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
    
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide toasts
    document.querySelectorAll('.toast').forEach(el => {
        new bootstrap.Toast(el, { autohide: true, delay: 3000 }).show();
    });
    
    <?php if($role == 'doctor'): ?>
    function startConsultation(appointmentId) {
        if(confirm('Start consultation for this patient?')) {
            window.location.href = 'consultation.php?id=' + appointmentId;
        }
    }
    <?php endif; ?>
</script>

</body>
</html>