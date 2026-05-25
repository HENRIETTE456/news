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

// Get patient_id if role is patient
$patient_id = null;
if($role == 'patient') {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $pat = $stmt->fetch();
    if($pat) {
        $patient_id = $pat['id'];
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

/* =========================
   GET LIST OF DOCTORS
========================= */
$doctors = $pdo->query("
    SELECT u.id, u.full_name, u.specialization, u.phone
    FROM users u
    WHERE u.role = 'doctor'
    ORDER BY u.full_name
")->fetchAll();

/* =========================
   GET LIST OF PATIENTS (for admin/manager)
========================= */
$patients = [];
if($role == 'admin' || $role == 'manager') {
    $patients = $pdo->query("
        SELECT p.id, p.first_name, p.last_name, p.phone
        FROM patients p
        ORDER BY p.first_name
    ")->fetchAll();
}

/* =========================
   PROCESS BOOK APPOINTMENT
========================= */
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    
    // Get form data
    if($role == 'admin' || $role == 'manager') {
        $patient_id = $_POST['patient_id'];
    }
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $start_time = $_POST['start_time'];
    $symptoms = trim($_POST['symptoms']);
    $reason = trim($_POST['reason'] ?? $symptoms);
    
    // Calculate end time (30 minutes later)
    $end_time = date('H:i:s', strtotime($start_time) + 1800);
    
    // Validation
    $errors = [];
    if(empty($doctor_id)) $errors[] = "Please select a doctor";
    if(empty($appointment_date)) $errors[] = "Please select a date";
    if(empty($start_time)) $errors[] = "Please select a time";
    if(empty($symptoms)) $errors[] = "Please describe your symptoms";
    
    // Check if date is not in past
    if(strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Cannot book appointment on a past date";
    }
    
    // Check if doctor works on that day and time
    if(empty($errors)) {
        $day_of_week = date('w', strtotime($appointment_date));
        $checkWork = $pdo->prepare("
            SELECT * FROM working_hours 
            WHERE doctor_id = ? AND day_of_week = ? 
            AND start_time <= ? AND end_time >= ?
        ");
        $checkWork->execute([$doctor_id, $day_of_week, $start_time, $start_time]);
        
        if($checkWork->rowCount() == 0) {
            $errors[] = "Doctor is not available at this time. Please check doctor's working hours.";
        }
    }
    
    // Check if slot is already booked
    if(empty($errors)) {
        $checkSlot = $pdo->prepare("
            SELECT id FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND start_time = ? 
            AND status NOT IN ('cancelled', 'completed')
        ");
        $checkSlot->execute([$doctor_id, $appointment_date, $start_time]);
        
        if($checkSlot->rowCount() > 0) {
            $errors[] = "This time slot is already booked. Please select another time.";
        }
    }
    
    // Create appointment if no errors
    if(empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, symptoms, reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
            ");
            $stmt->execute([$patient_id, $doctor_id, $appointment_date, $start_time, $end_time, $symptoms, $reason]);
            
            $appointment_id = $pdo->lastInsertId();
            
            // Create notification for patient (if they have user account)
            if($role == 'patient') {
                $notify = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, link)
                    VALUES (?, ?, ?, 'appointment', ?)
                ");
                $doctorName = getDoctorName($pdo, $doctor_id);
                $message = "Your appointment with Dr. $doctorName on $appointment_date at $start_time has been scheduled.";
                $notify->execute([$user_id, 'Appointment Scheduled', $message, "appointments.php"]);
            }
            
            $_SESSION['success'] = "Appointment booked successfully!";
            header("Location: appointments.php");
            exit();
            
        } catch(PDOException $e) {
            $error = "Failed to book appointment: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Helper function to get doctor name
function getDoctorName($pdo, $doctor_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doc = $stmt->fetch();
    return $doc ? $doc['full_name'] : 'Doctor';
}

// Get available time slots via AJAX
if(isset($_GET['ajax_slots'])) {
    header('Content-Type: application/json');
    $doctor_id = $_GET['doctor_id'];
    $date = $_GET['date'];
    $day_of_week = date('w', strtotime($date));
    
    // Get doctor's working hours
    $workStmt = $pdo->prepare("
        SELECT start_time, end_time FROM working_hours 
        WHERE doctor_id = ? AND day_of_week = ?
    ");
    $workStmt->execute([$doctor_id, $day_of_week]);
    $hours = $workStmt->fetch();
    
    if(!$hours) {
        echo json_encode([]);
        exit();
    }
    
    // Get already booked slots
    $bookedStmt = $pdo->prepare("
        SELECT start_time FROM appointments 
        WHERE doctor_id = ? AND appointment_date = ? 
        AND status NOT IN ('cancelled', 'completed')
    ");
    $bookedStmt->execute([$doctor_id, $date]);
    $bookedTimes = $bookedStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generate available slots (30 minute intervals)
    $slots = [];
    $start = strtotime($hours['start_time']);
    $end = strtotime($hours['end_time']);
    $now = time();
    $todayStart = strtotime($date . ' 00:00:00');
    
    while($start < $end) {
        $slotTime = date('H:i:s', $start);
        $slotTimestamp = $todayStart + ($start - strtotime('00:00:00'));
        
        // Only show future slots for today
        if($date == date('Y-m-d')) {
            if($slotTimestamp > $now && !in_array($slotTime, $bookedTimes)) {
                $slots[] = $slotTime;
            }
        } else {
            if(!in_array($slotTime, $bookedTimes)) {
                $slots[] = $slotTime;
            }
        }
        $start += 1800; // 30 minutes
    }
    
    echo json_encode($slots);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Book Appointment - Clinic System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        background: #f0f2f5;
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        padding: 20px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        z-index: 100;
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
    
    /* Main Content */
    .main-content {
        margin-left: 260px;
        padding: 20px;
        min-height: 100vh;
    }
    
    /* Header */
    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        color: white;
    }
    
    /* Form Card */
    .form-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .form-card-header {
        background: #1a1a2e;
        color: white;
        padding: 15px 20px;
        font-weight: bold;
    }
    
    .form-card-body {
        padding: 25px;
    }
    
    .form-label {
        font-weight: 500;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        padding: 10px 15px;
        border: 1px solid #ddd;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 12px 30px;
        font-weight: bold;
        border-radius: 25px;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }
    
    .doctor-card {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .doctor-card:hover {
        border-color: #667eea;
        background: #f8f9ff;
    }
    
    .doctor-card.selected {
        border-color: #22c55e;
        background: #f0fff4;
    }
    
    .doctor-avatar {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
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

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-hospital"></i>
        <span>Clinic System</span>
    </div>
    
    <a href="dashboard.php">
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
    <a href="book_appointment.php" class="active">
        <i class="fas fa-calendar-plus"></i> <span>Book Appointment</span>
    </a>
    <a href="my_appointments.php">
        <i class="fas fa-list"></i> <span>My Appointments</span>
    </a>
    <?php endif; ?>
    
    <a href="profile.php">
        <i class="fas fa-user-circle"></i> <span>Profile</span>
    </a>
    
    <a href="logout.php">
        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    
    <!-- Header -->
    <div class="header">
        <h2><i class="fas fa-calendar-plus"></i> Book New Appointment</h2>
        <p><i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($full_name); ?></p>
    </div>
    
    <!-- Alert Messages -->
    <?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Booking Form -->
    <div class="form-card">
        <div class="form-card-header">
            <i class="fas fa-calendar-alt"></i> Appointment Information
        </div>
        <div class="form-card-body">
            <form method="POST" id="bookingForm">
                
                <!-- For Admin/Manager: Select Patient -->
                <?php if($role == 'admin' || $role == 'manager'): ?>
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-user"></i> Select Patient *</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach($patients as $pat): ?>
                        <option value="<?php echo $pat['id']; ?>">
                            <?php echo htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']); ?> 
                            (<?php echo htmlspecialchars($pat['phone']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Select Doctor -->
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-user-md"></i> Select Doctor *</label>
                    <div class="row" id="doctorList">
                        <?php foreach($doctors as $doc): ?>
                        <div class="col-md-4">
                            <div class="doctor-card" onclick="selectDoctor(<?php echo $doc['id']; ?>, this)">
                                <div class="d-flex align-items-center">
                                    <div class="doctor-avatar me-3">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div>
                                        <strong>Dr. <?php echo htmlspecialchars($doc['full_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($doc['specialization'] ?? 'General Doctor'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="doctor_id" id="selected_doctor_id" required>
                </div>
                
                <!-- Date and Time -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label"><i class="fas fa-calendar-day"></i> Appointment Date *</label>
                        <input type="date" name="appointment_date" id="appointment_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label"><i class="fas fa-clock"></i> Appointment Time *</label>
                        <select name="start_time" id="time_slots" class="form-select" required disabled>
                            <option value="">First select a doctor and date</option>
                        </select>
                    </div>
                </div>
                
                <!-- Symptoms / Reason -->
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-stethoscope"></i> Symptoms / Reason for visit *</label>
                    <textarea name="symptoms" id="symptoms" class="form-control" rows="4" 
                              placeholder="Please describe your symptoms, pain location, duration, etc." required></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-notes-medical"></i> Additional Notes (Optional)</label>
                    <textarea name="reason" class="form-control" rows="2" 
                              placeholder="Any additional information for the doctor..."></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="book_appointment" class="btn btn-submit btn-primary">
                        <i class="fas fa-check-circle"></i> Book Appointment
                    </button>
                    <a href="appointments.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
                
            </form>
        </div>
    </div>
    
    <!-- Working Hours Info -->
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle"></i> 
        <strong>Working Hours:</strong> Doctors are available Monday to Friday, 8:00 AM - 5:00 PM. 
        Each appointment takes 30 minutes. Please arrive 10 minutes before your appointment time.
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let selectedDoctorId = null;
    let selectedCard = null;
    
    // Select doctor
    function selectDoctor(doctorId, element) {
        // Remove selected class from all cards
        document.querySelectorAll('.doctor-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        // Add selected class to clicked card
        element.classList.add('selected');
        
        // Set selected doctor ID
        selectedDoctorId = doctorId;
        document.getElementById('selected_doctor_id').value = doctorId;
        
        // Trigger date change to load time slots
        if(document.getElementById('appointment_date').value) {
            loadTimeSlots();
        }
    }
    
    // Load time slots when date changes
    document.getElementById('appointment_date').addEventListener('change', function() {
        if(selectedDoctorId) {
            loadTimeSlots();
        } else {
            alert('Please select a doctor first');
        }
    });
    
    // Load available time slots via AJAX
    function loadTimeSlots() {
        const doctorId = selectedDoctorId;
        const date = document.getElementById('appointment_date').value;
        
        if(!doctorId || !date) return;
        
        const timeSelect = document.getElementById('time_slots');
        timeSelect.innerHTML = '<option value="">Loading available times...</option>';
        timeSelect.disabled = true;
        
        fetch(`?ajax_slots=1&doctor_id=${doctorId}&date=${date}`)
            .then(response => response.json())
            .then(slots => {
                timeSelect.innerHTML = '<option value="">Select a time</option>';
                
                if(slots.length === 0) {
                    timeSelect.innerHTML = '<option value="">No available slots for this date</option>';
                } else {
                    slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot;
                        option.textContent = formatTime(slot);
                        timeSelect.appendChild(option);
                    });
                    timeSelect.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                timeSelect.innerHTML = '<option value="">Error loading slots</option>';
            });
    }
    
    // Format time from 14:30:00 to 2:30 PM
    function formatTime(time) {
        let [hours, minutes] = time.split(':');
        let period = 'AM';
        let hour = parseInt(hours);
        
        if(hour >= 12) {
            period = 'PM';
            if(hour > 12) hour -= 12;
        }
        if(hour === 0) hour = 12;
        
        return `${hour}:${minutes} ${period}`;
    }
    
    // Validate form before submit
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        if(!document.getElementById('selected_doctor_id').value) {
            e.preventDefault();
            alert('Please select a doctor');
            return false;
        }
        if(!document.getElementById('appointment_date').value) {
            e.preventDefault();
            alert('Please select a date');
            return false;
        }
        if(!document.getElementById('time_slots').value) {
            e.preventDefault();
            alert('Please select a time slot');
            return false;
        }
        if(!document.getElementById('symptoms').value.trim()) {
            e.preventDefault();
            alert('Please describe your symptoms');
            return false;
        }
        return true;
    });
</script>

</body>
</html>