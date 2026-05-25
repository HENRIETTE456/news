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

$patient_id = $_SESSION['patient_id'];
$user_id = $_SESSION['user_id'];

// Get patient details
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.email, u.preferred_language 
    FROM patients p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

// Get upcoming appointments
$upcoming = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, u.specialization 
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled', 'completed')
    ORDER BY a.appointment_date, a.start_time LIMIT 5
");
$upcoming->execute([$patient_id]);

// Get past appointments
$past = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, u.specialization 
    FROM appointments a 
    JOIN users u ON a.doctor_id = u.id 
    WHERE a.patient_id = ? AND (a.appointment_date < CURDATE() OR a.status IN ('completed', 'cancelled'))
    ORDER BY a.appointment_date DESC LIMIT 5
");
$past->execute([$patient_id]);

// Get notifications
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC LIMIT 5
");
$notifications->execute([$user_id]);

// Count statistics
$totalAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
$totalAppointments->execute([$patient_id]);

$completedAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed'");
$completedAppointments->execute([$patient_id]);

$cancelledAppointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'cancelled'");
$cancelledAppointments->execute([$patient_id]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Dashboard - Clinic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        .stat-card h2 {
            font-size: 2.5rem;
            margin: 0;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border-radius: 50%;
            padding: 5px 8px;
            font-size: 10px;
        }
        .sidebar-link {
            padding: 12px 20px;
            transition: all 0.3s;
            border-radius: 10px;
        }
        .sidebar-link:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="patient_dashboard.php">
            <i class="fas fa-hospital"></i> Clinic Management System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($patient['first_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="patient_profile.php"><i class="fas fa-id-card"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="patient_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="patient_auth.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card dashboard-card">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                        <h5 class="mt-2"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h5>
                        <small class="text-muted"><?php echo htmlspecialchars($patient['email']); ?></small>
                    </div>
                    <hr>
                    <div class="nav flex-column">
                        <a href="patient_dashboard.php" class="sidebar-link active d-block mb-2">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a href="book_appointment.php" class="sidebar-link d-block mb-2">
                            <i class="fas fa-calendar-plus"></i> Book Appointment
                        </a>
                        <a href="my_appointments.php" class="sidebar-link d-block mb-2">
                            <i class="fas fa-calendar-check"></i> My Appointments
                        </a>
                        <a href="my_medical_records.php" class="sidebar-link d-block mb-2">
                            <i class="fas fa-notes-medical"></i> Medical Records
                        </a>
                        <a href="billing_history.php" class="sidebar-link d-block mb-2">
                            <i class="fas fa-receipt"></i> Billing History
                        </a>
                        <a href="patient_settings.php" class="sidebar-link d-block">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Welcome Banner -->
            <div class="alert alert-primary mb-4">
                <i class="fas fa-hand-wave"></i> Welcome back, <strong><?php echo htmlspecialchars($patient['first_name']); ?></strong>! 
                Your next appointment is on <?php 
                    $next = $pdo->prepare("SELECT appointment_date, start_time FROM appointments WHERE patient_id = ? AND appointment_date >= CURDATE() AND status = 'scheduled' ORDER BY appointment_date LIMIT 1");
                    $next->execute([$patient_id]);
                    $nextApt = $next->fetch();
                    echo $nextApt ? date('F j, Y', strtotime($nextApt['appointment_date'])) . ' at ' . $nextApt['start_time'] : 'No upcoming appointments';
                ?>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-calendar fa-2x mb-2"></i>
                        <h2><?php echo $totalAppointments->fetchColumn(); ?></h2>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h2><?php echo $completedAppointments->fetchColumn(); ?></h2>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <i class="fas fa-times-circle fa-2x mb-2"></i>
                        <h2><?php echo $cancelledAppointments->fetchColumn(); ?></h2>
                        <p>Cancelled</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <i class="fas fa-bell fa-2x mb-2"></i>
                        <h2><?php echo $notifications->rowCount(); ?></h2>
                        <p>Notifications</p>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Section -->
            <?php 
            $notifications->execute([$user_id]);
            if($notifications->rowCount() > 0): 
            ?>
            <div class="card dashboard-card mb-4">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-bell"></i> Recent Notifications
                </div>
                <div class="card-body">
                    <?php while($notif = $notifications->fetch()): ?>
                        <div class="alert alert-light border-left-info">
                            <strong><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($notif['title'] ?? 'Notification'); ?></strong>
                            <p class="mb-0"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Upcoming Appointments -->
            <div class="card dashboard-card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-calendar-alt"></i> Upcoming Appointments
                </div>
                <div class="card-body">
                    <?php if($upcoming->rowCount() > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
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
                                    <?php while($apt = $upcoming->fetch()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></td>
                                        <td><span class="badge bg-success"><?php echo $apt['status']; ?></span></td>
                                        <td>
                                            <a href="appointment_details.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                            <a href="cancel_appointment.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No upcoming appointments. <a href="book_appointment.php">Book one now!</a></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card dashboard-card text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-plus fa-3x text-primary mb-3"></i>
                            <h5>Book New Appointment</h5>
                            <p>Schedule a visit with our doctors</p>
                            <a href="book_appointment.php" class="btn btn-primary">Book Now</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card dashboard-card text-center">
                        <div class="card-body">
                            <i class="fas fa-notes-medical fa-3x text-info mb-3"></i>
                            <h5>Medical Records</h5>
                            <p>View your medical history</p>
                            <a href="my_medical_records.php" class="btn btn-info text-white">View Records</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('.toast').forEach(el => {
        new bootstrap.Toast(el, { autohide: true, delay: 3000 }).show();
    });
</script>

<?php include 'includes/footer.php'; ?>