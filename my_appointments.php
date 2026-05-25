<?php
session_start();
require_once 'config/database.php';

// Check if patient is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'patient'){
    header("Location: patient_auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$patient_id = $_SESSION['patient_id'];

// Get all appointments
$appointments = $pdo->prepare("
    SELECT a.*, u.full_name as doctor_name, u.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.start_time DESC
");
$appointments->execute([$patient_id]);
$appointmentList = $appointments->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Appointments - Clinic System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); padding: 20px; }
        .sidebar a { display: block; padding: 12px 15px; color: #cbd5e1; text-decoration: none; margin: 5px 0; border-radius: 10px; }
        .sidebar a:hover, .sidebar a.active { background: #22c55e; color: white; }
        .sidebar a i { margin-right: 10px; }
        .main-content { margin-left: 260px; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 15px; margin-bottom: 25px; color: white; }
        .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table-card-header { background: #1a1a2e; color: white; padding: 15px 20px; }
        .badge-scheduled { background: #0d6efd; }
        .badge-completed { background: #198754; }
        .badge-cancelled { background: #dc3545; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar span { display: none; } .main-content { margin-left: 70px; } }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="logo text-center text-white mb-4">🏥 CMS</div>
    <a href="patient_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
    <a href="book_appointment.php"><i class="fas fa-calendar-plus"></i> <span>Book</span></a>
    <a href="my_appointments.php" class="active"><i class="fas fa-calendar-check"></i> <span>My Appointments</span></a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="main-content">
    <div class="header">
        <h2><i class="fas fa-calendar-check"></i> My Appointments</h2>
        <p>View all your appointments</p>
    </div>
    
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <div class="table-card">
        <div class="table-card-header"><i class="fas fa-list"></i> All Appointments</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr><th>Doctor</th><th>Specialization</th><th>Date</th><th>Time</th><th>Symptoms</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if(count($appointmentList) > 0): ?>
                        <?php foreach($appointmentList as $apt): ?>
                        <tr>
                            <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($apt['specialization']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($apt['start_time'])); ?></td>
                            <td><?php echo htmlspecialchars(substr($apt['symptoms'], 0, 30)); ?>...</td>
                            <td><span class="badge badge-<?php echo $apt['status']; ?>"><?php echo ucfirst($apt['status']); ?></span></td>
                            <td>
                                <?php if($apt['status'] == 'scheduled'): ?>
                                <a href="cancel_appointment.php?id=<?php echo $apt['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel?')">Cancel</a>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4">No appointments found. <a href="book_appointment.php">Book one now!</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>