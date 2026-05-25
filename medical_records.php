<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Get patient_id
$patient_id = null;
if($role == 'patient') {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    if($patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    }
} elseif(isset($_GET['patient_id']) && ($role == 'admin' || $role == 'manager' || $role == 'doctor')) {
    $patient_id = $_GET['patient_id'];
}

// Get patient details
$patientInfo = null;
if($patient_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.email 
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$patient_id]);
    $patientInfo = $stmt->fetch();
}

// ============================================
// CREATE MEDICAL_RECORDS TABLE IF NOT EXISTS
// ============================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS medical_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        record_date DATE NOT NULL,
        record_type ENUM('checkup', 'lab_result', 'prescription', 'vaccination', 'surgery', 'emergency', 'diagnosis') DEFAULT 'checkup',
        title VARCHAR(255) NOT NULL,
        description TEXT,
        diagnosis TEXT,
        prescription TEXT,
        notes TEXT,
        file_path VARCHAR(255),
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// ============================================
// ADD NEW MEDICAL RECORD (for doctors/admins)
// ============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_record']) && ($role == 'admin' || $role == 'manager' || $role == 'doctor')) {
    
    $record_date = $_POST['record_date'];
    $record_type = $_POST['record_type'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $diagnosis = trim($_POST['diagnosis']);
    $prescription = trim($_POST['prescription']);
    $notes = trim($_POST['notes']);
    $doctor_id = $user_id;
    
    $stmt = $pdo->prepare("
        INSERT INTO medical_records (patient_id, doctor_id, record_date, record_type, title, description, diagnosis, prescription, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$patient_id, $doctor_id, $record_date, $record_type, $title, $description, $diagnosis, $prescription, $notes, $user_id]);
    
    $_SESSION['success'] = "Medical record added successfully!";
    header("Location: medical_records.php?patient_id=" . $patient_id);
    exit();
}

// ============================================
// DELETE MEDICAL RECORD
// ============================================
if(isset($_GET['delete']) && ($role == 'admin' || $role == 'manager' || $role == 'doctor')) {
    $record_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM medical_records WHERE id = ?");
    $stmt->execute([$record_id]);
    $_SESSION['success'] = "Medical record deleted!";
    header("Location: medical_records.php?patient_id=" . $patient_id);
    exit();
}

// ============================================
// GET MEDICAL RECORDS
// ============================================
$records = [];
if($patient_id) {
    $stmt = $pdo->prepare("
        SELECT mr.*, u.full_name as doctor_name
        FROM medical_records mr
        JOIN users u ON mr.doctor_id = u.id
        WHERE mr.patient_id = ?
        ORDER BY mr.record_date DESC, mr.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll();
}

// Get statistics
$totalRecords = count($records);
$checkups = 0;
$labResults = 0;
$prescriptions = 0;
$diagnoses = 0;

foreach($records as $r) {
    if($r['record_type'] == 'checkup') $checkups++;
    elseif($r['record_type'] == 'lab_result') $labResults++;
    elseif($r['record_type'] == 'prescription') $prescriptions++;
    elseif($r['record_type'] == 'diagnosis') $diagnoses++;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Medical Records - Clinic System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    
    .sidebar a {
        display: block;
        padding: 12px 15px;
        color: #cbd5e1;
        text-decoration: none;
        margin: 5px 0;
        border-radius: 10px;
    }
    
    .sidebar a:hover, .sidebar a.active {
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
    }
    
    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        color: white;
    }
    
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 15px;
        text-align: center;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .stat-card h3 {
        font-size: 1.8rem;
        margin: 10px 0;
    }
    
    .record-card {
        background: white;
        border-radius: 15px;
        margin-bottom: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .record-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }
    
    .record-body {
        padding: 20px;
        display: none;
        border-top: 1px solid #eee;
    }
    
    .record-body.show {
        display: block;
    }
    
    .badge-checkup { background: #0d6efd; }
    .badge-lab_result { background: #6f42c1; }
    .badge-prescription { background: #198754; }
    .badge-diagnosis { background: #fd7e14; }
    .badge-vaccination { background: #20c997; }
    .badge-surgery { background: #dc3545; }
    .badge-emergency { background: #ffc107; color: #000; }
    
    .btn-add {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 10px 20px;
        color: white;
        border-radius: 25px;
    }
    
    @media (max-width: 768px) {
        .sidebar { width: 70px; }
        .sidebar span { display: none; }
        .main-content { margin-left: 70px; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="logo">
        <i class="fas fa-hospital"></i>
        <span>Clinic System</span>
    </div>
    
    <?php if($role == 'patient'): ?>
    <a href="patient_dashboard.php">
        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
    </a>
    <a href="book_appointment.php">
        <i class="fas fa-calendar-plus"></i> <span>Book Appointment</span>
    </a>
    <a href="my_appointments.php">
        <i class="fas fa-calendar-check"></i> <span>My Appointments</span>
    </a>
    <a href="medical_records.php" class="active">
        <i class="fas fa-notes-medical"></i> <span>Medical Records</span>
    </a>
    <?php else: ?>
    <a href="dashboard.php">
        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
    </a>
    <a href="patients.php">
        <i class="fas fa-users"></i> <span>Patients</span>
    </a>
    <a href="appointments.php">
        <i class="fas fa-calendar-check"></i> <span>Appointments</span>
    </a>
    <a href="medical_records.php" class="active">
        <i class="fas fa-notes-medical"></i> <span>Medical Records</span>
    </a>
    <?php endif; ?>
    
    <a href="logout.php">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
</div>

<div class="main-content">
    
    <div class="header">
        <h2><i class="fas fa-notes-medical"></i> Medical Records</h2>
        <p class="mb-0">
            <?php if($patientInfo): ?>
            Patient: <?php echo htmlspecialchars($patientInfo['first_name'] . ' ' . $patientInfo['last_name']); ?>
            <?php else: ?>
            Select a patient to view records
            <?php endif; ?>
        </p>
    </div>
    
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if($patientInfo): ?>
    
    <!-- Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-folder-open fa-2x text-primary"></i>
                <h3><?php echo $totalRecords; ?></h3>
                <p>Total Records</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-stethoscope fa-2x text-info"></i>
                <h3><?php echo $checkups; ?></h3>
                <p>Checkups</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-flask fa-2x text-purple"></i>
                <h3><?php echo $labResults; ?></h3>
                <p>Lab Results</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-prescription-bottle fa-2x text-success"></i>
                <h3><?php echo $prescriptions; ?></h3>
                <p>Prescriptions</p>
            </div>
        </div>
    </div>
    
    <!-- Add Record Button (for doctors/admins) -->
    <?php if($role == 'admin' || $role == 'manager' || $role == 'doctor'): ?>
    <div class="mb-3 text-end">
        <button class="btn-add btn" data-bs-toggle="modal" data-bs-target="#addRecordModal">
            <i class="fas fa-plus"></i> Add Medical Record
        </button>
    </div>
    <?php endif; ?>
    
    <!-- Medical Records List -->
    <?php if(count($records) > 0): ?>
        <?php foreach($records as $record): ?>
        <div class="record-card">
            <div class="record-header" onclick="toggleRecord(<?php echo $record['id']; ?>)">
                <div class="row align-items-center">
                    <div class="col-md-1">
                        <i class="fas fa-file-medical fa-2x text-primary"></i>
                    </div>
                    <div class="col-md-3">
                        <strong><?php echo htmlspecialchars($record['title']); ?></strong>
                        <br><small class="text-muted"><?php echo date('M d, Y', strtotime($record['record_date'])); ?></small>
                    </div>
                    <div class="col-md-2">
                        <span class="badge badge-<?php echo $record['record_type']; ?>">
                            <?php echo str_replace('_', ' ', ucfirst($record['record_type'])); ?>
                        </span>
                    </div>
                    <div class="col-md-3">
                        <small>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></small>
                    </div>
                    <div class="col-md-3 text-end">
                        <i class="fas fa-chevron-down"></i>
                        <?php if($role == 'admin' || $role == 'manager' || $role == 'doctor'): ?>
                        <a href="?delete=<?php echo $record['id']; ?>&patient_id=<?php echo $patient_id; ?>" 
                           class="btn btn-sm btn-danger ms-2" 
                           onclick="return confirm('Delete this record?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="record-body" id="record-<?php echo $record['id']; ?>">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-stethoscope"></i> Diagnosis:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($record['diagnosis'] ?? 'N/A')); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong><i class="fas fa-prescription"></i> Prescription:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($record['prescription'] ?? 'N/A')); ?></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p><strong><i class="fas fa-align-left"></i> Description:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($record['description'] ?? 'N/A')); ?></p>
                    </div>
                </div>
                <?php if($record['notes']): ?>
                <div class="row">
                    <div class="col-12">
                        <p><strong><i class="fas fa-paperclip"></i> Additional Notes:</strong></p>
                        <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                <hr>
                <small class="text-muted">Created: <?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle fa-2x mb-2 d-block"></i>
            No medical records found for this patient.
            <?php if($role == 'admin' || $role == 'manager' || $role == 'doctor'): ?>
            <br>Click "Add Medical Record" to create one.
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php else: ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
            Please select a patient to view medical records.
        </div>
    <?php endif; ?>
</div>

<!-- Add Record Modal (for doctors/admins) -->
<div class="modal fade" id="addRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Record Date *</label>
                            <input type="date" name="record_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Record Type *</label>
                            <select name="record_type" class="form-select" required>
                                <option value="checkup">Checkup / Consultation</option>
                                <option value="diagnosis">Diagnosis</option>
                                <option value="lab_result">Lab Result</option>
                                <option value="prescription">Prescription</option>
                                <option value="vaccination">Vaccination</option>
                                <option value="surgery">Surgery</option>
                                <option value="emergency">Emergency Visit</option>
                            </select>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Annual Checkup, Blood Test Results, etc." required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Describe the record..."></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Diagnosis</label>
                            <textarea name="diagnosis" class="form-control" rows="3" placeholder="Doctor's diagnosis..."></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Prescription</label>
                            <textarea name="prescription" class="form-control" rows="3" placeholder="Medications prescribed..."></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any additional information..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_record" class="btn btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleRecord(id) {
        let element = document.getElementById('record-' + id);
        element.classList.toggle('show');
    }
</script>

</body>
</html>