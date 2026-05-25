<?php
require_once 'config/database.php';
include 'includes/header.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if patient is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient') {
    header("Location: patient_auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ============================================
// GET OR CREATE PATIENT ID
// ============================================
$patient_id = null;

// First check if patient exists
$stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$user_id]);
$patient = $stmt->fetch();

if($patient) {
    $patient_id = $patient['id'];
    $_SESSION['patient_id'] = $patient_id;
} else {
    // Create patient record from user data
    $userStmt = $pdo->prepare("SELECT full_name, phone, email FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $userData = $userStmt->fetch();
    
    $nameParts = explode(' ', $userData['full_name'], 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';
    
    $insert = $pdo->prepare("
        INSERT INTO patients (user_id, first_name, last_name, phone, status, created_at)
        VALUES (?, ?, ?, ?, 'active', NOW())
    ");
    $insert->execute([$user_id, $firstName, $lastName, $userData['phone']]);
    $patient_id = $pdo->lastInsertId();
    $_SESSION['patient_id'] = $patient_id;
}

// ============================================
// GET PATIENT DETAILS
// ============================================
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.email, u.preferred_language 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if(!$patient) {
    // If patient not found, logout
    session_destroy();
    header("Location: patient_auth.php");
    exit();
}

// ============================================
// GET STATISTICS (Fixed - fetch once)
// ============================================
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
$totalStmt->execute([$patient_id]);
$totalAppointments = $totalStmt->fetchColumn();

$completedStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'");
$completedStmt->execute([$patient_id]);
$completedAppointments = $completedStmt->fetchColumn();

$cancelledStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'cancelled'");
$cancelledStmt->execute([$patient_id]);
$cancelledAppointments = $cancelledStmt->fetchColumn();

$upcomingStmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments 
    WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled'
");
$upcomingStmt->execute([$patient_id]);
$upcomingCount = $upcomingStmt->fetchColumn();

// ============================================
// GET NEXT APPOINTMENT
// ============================================
$nextStmt = $pdo->prepare("
    SELECT a.appointment_date, a.start_time, u.full_name as doctor_name 
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled' 
    ORDER BY a.appointment_date LIMIT 1
");
$nextStmt->execute([$patient_id]);
$nextAppointment = $nextStmt->fetch();

// ============================================
// GET UPCOMING APPOINTMENTS
// ============================================
$upcoming = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, u.specialization 
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled', 'completed')
    ORDER BY a.appointment_date, a.start_time LIMIT 5
");
$upcoming->execute([$patient_id]);
$upcomingAppointments = $upcoming->fetchAll();

// ============================================
// GET PAST APPOINTMENTS
// ============================================
$past = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, u.specialization 
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    WHERE a.patient_id = ? AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled'))
    ORDER BY a.appointment_date DESC LIMIT 5
");
$past->execute([$patient_id]);
$pastAppointments = $past->fetchAll();

// ============================================
// GET NOTIFICATIONS
// ============================================
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC LIMIT 5
");
$notifications->execute([$user_id]);
$notificationList = $notifications->fetchAll();
$notificationCount = count($notificationList);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Dashboard - Clinic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            padding: 20px;
            overflow-y: auto;
        }
        
        .logo {
            text-align: center;
            color: #22c55e;
            font-size: 22px;
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
        
        .main-content {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h2 {
            font-size: 2rem;
            margin: 10px 0;
            font-weight: bold;
        }
        
        .stat-card .icon {
            font-size: 2rem;
            opacity: 0.5;
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .table-card-header {
            background: #1a1a2e;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
        }
        
        .badge-scheduled { background: #0d6efd; }
        .badge-confirmed { background: #198754; }
        .badge-completed { background: #198754; }
        .badge-cancelled { background: #dc3545; }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar span { display: none; }
            .sidebar a i { margin-right: 0; }
            .main-content { margin-left: 70px; }
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

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-hospital"></i>
        <span>Clinic System</span>
    </div>
    
    <a href="patient_dashboard.php" class="active">
        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
    </a>
    <a href="book_appointment.php">
        <i class="fas fa-calendar-plus"></i> <span>Book Appointment</span>
    </a>
    <a href="my_appointments.php">
        <i class="fas fa-calendar-check"></i> <span>My Appointments</span>
    </a>
    <a href="medical_records.php">
        <i class="fas fa-notes-medical"></i> <span>Medical Records</span>
    </a>
    <a href="logout.php">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Welcome Card -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><i class="fas fa-hand-wave"></i> Welcome back, <?php echo htmlspecialchars($patient['first_name']); ?>!</h2>
                <p class="mb-0">Welcome to your health dashboard. Manage your appointments and medical records here.</p>
            </div>
            <div class="col-md-4 text-end">
                <i class="fas fa-calendar-check fa-3x"></i>
                <?php if($upcomingCount > 0): ?>
                <p class="mb-0 mt-2">You have <?php echo $upcomingCount; ?> upcoming appointment(s)</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-calendar-alt fa-2x text-primary"></i>
                <h2><?php echo $totalAppointments; ?></h2>
                <p>Total Appointments</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                <h2><?php echo $upcomingCount; ?></h2>
                <p>Upcoming</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-check-circle fa-2x text-success"></i>
                <h2><?php echo $completedAppointments; ?></h2>
                <p>Completed</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-times-circle fa-2x text-danger"></i>
                <h2><?php echo $cancelledAppointments; ?></h2>
                <p>Cancelled</p>
            </div>
        </div>
    </div>
    
    <!-- Next Appointment Info -->
    <?php if($nextAppointment): ?>
    <div class="alert alert-info">
        <i class="fas fa-bell"></i> 
        <strong>Your Next Appointment:</strong> 
        Dr. <?php echo htmlspecialchars($nextAppointment['doctor_name']); ?> on 
        <?php echo date('F j, Y', strtotime($nextAppointment['appointment_date'])); ?> at 
        <?php echo date('g:i A', strtotime($nextAppointment['start_time'])); ?>
    </div>
    <?php endif; ?>
    
    <!-- Notifications Section -->
    <?php if($notificationCount > 0): ?>
    <div class="table-card">
        <div class="table-card-header">
            <i class="fas fa-bell"></i> Notifications (<?php echo $notificationCount; ?>)
        </div>
        <div class="p-3">
            <?php foreach($notificationList as $notif): ?>
            <div class="alert alert-light border-left-info mb-2">
                <strong><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?></strong>
                <p class="mb-0"><?php echo htmlspecialchars($notif['message']); ?></p>
                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Upcoming Appointments -->
    <div class="table-card">
        <div class="table-card-header">
            <i class="fas fa-calendar-alt"></i> Upcoming Appointments
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
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
                    <?php if(count($upcomingAppointments) > 0): ?>
                        <?php foreach($upcomingAppointments as $apt): ?>
                        <tr>
                            <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></td>
                            <td><span class="badge bg-success"><?php echo ucfirst($apt['status']); ?></span></td>
                            <td>
                                <a href="my_appointments.php?cancel=<?php echo $apt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this appointment?')">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4">
                            No upcoming appointments. 
                            <a href="book_appointment.php">Book one now!</a>
                        </td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-calendar-plus fa-3x text-primary mb-3"></i>
                    <h5>Book New Appointment</h5>
                    <p>Schedule a visit with our doctors</p>
                    <a href="book_appointment.php" class="btn btn-primary">Book Now</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-notes-medical fa-3x text-info mb-3"></i>
                    <h5>Medical Records</h5>
                    <p>View your medical history</p>
                    <a href="medical_records.php" class="btn btn-info text-white">View Records</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Patient Profile Card -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-id-card"></i> Your Profile Information</h6>
                    <hr>
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Full Name</small>
                            <p><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Email</small>
                            <p><?php echo htmlspecialchars($patient['email']); ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Phone</small>
                            <p><?php echo htmlspecialchars($patient['phone']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-hide toasts
    document.querySelectorAll('.toast').forEach(el => {
        new bootstrap.Toast(el, { autohide: true, delay: 3000 }).show();
    });
</script>

</body>
</html>