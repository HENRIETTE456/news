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

// ============================================
// GET OR CREATE PATIENT ID
// ============================================
$patient_id = null;

if($role == 'patient') {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch();
    
    if($patient) {
        $patient_id = $patient['id'];
        $_SESSION['patient_id'] = $patient_id;
    } else {
        $userStmt = $pdo->prepare("SELECT full_name, phone FROM users WHERE id = ?");
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
}

// For admin
if(($role == 'admin' || $role == 'manager') && isset($_POST['patient_id'])) {
    $patient_id = $_POST['patient_id'];
}

// ============================================
// GET DOCTORS WITH THEIR WORKING HOURS
// ============================================
$doctors = $pdo->query("
    SELECT u.id, u.full_name, u.specialization, u.phone
    FROM users u
    WHERE u.role = 'doctor'
    ORDER BY u.full_name
")->fetchAll();

// For admin
$all_patients = [];
if($role == 'admin' || $role == 'manager') {
    $all_patients = $pdo->query("
        SELECT id, first_name, last_name, phone 
        FROM patients 
        ORDER BY first_name
    ")->fetchAll();
}

// ============================================
// PROCESS BOOKING WITH AVAILABILITY CHECK
// ============================================
$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    
    $doctor_id = $_POST['doctor_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $symptoms = trim($_POST['symptoms'] ?? '');
    $reason = trim($_POST['reason'] ?? $symptoms);
    
    $errors = [];
    $doctor_name = '';
    
    // 1. Basic validation
    if(empty($doctor_id)) $errors[] = "Please select a doctor";
    if(empty($appointment_date)) $errors[] = "Please select a date";
    if(empty($start_time)) $errors[] = "Please select a time";
    if(empty($symptoms)) $errors[] = "Please describe your symptoms";
    if(empty($patient_id)) $errors[] = "Patient information missing";
    
    // 2. Check if date is not in past
    if(!empty($appointment_date) && strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Cannot book appointment on a past date";
    }
    
    // 3. Check if doctor exists and get name
    if(!empty($doctor_id)) {
        $checkDoc = $pdo->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'doctor'");
        $checkDoc->execute([$doctor_id]);
        $docData = $checkDoc->fetch();
        if($docData) {
            $doctor_name = $docData['full_name'];
        } else {
            $errors[] = "Selected doctor does not exist";
        }
    }
    
    // 4. Check if doctor is available on that day and time (WORKING HOURS)
    if(empty($errors) && !empty($doctor_id) && !empty($appointment_date) && !empty($start_time)) {
        $day_of_week = date('w', strtotime($appointment_date)); // 0=Sunday, 1=Monday, etc.
        
        // Check if doctor works on that day
        $checkWorkingDay = $pdo->prepare("
            SELECT * FROM working_hours 
            WHERE doctor_id = ? AND day_of_week = ?
        ");
        $checkWorkingDay->execute([$doctor_id, $day_of_week]);
        
        if($checkWorkingDay->rowCount() == 0) {
            // Doctor doesn't work on this day
            $day_name = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day_of_week];
            $errors[] = "Dr. $doctor_name is not available on $day_name. Please select another day (Monday-Friday).";
        } else {
            // Check if the specific time is within working hours
            $workingHours = $checkWorkingDay->fetch();
            $workStart = $workingHours['start_time'];
            $workEnd = $workingHours['end_time'];
            
            if($start_time < $workStart || $start_time >= $workEnd) {
                $errors[] = "Dr. $doctor_name works from " . date('h:i A', strtotime($workStart)) . " to " . date('h:i A', strtotime($workEnd)) . ". Please select a time within working hours.";
            }
        }
    }
    
    // 5. Check if slot is already booked
    if(empty($errors)) {
        $checkSlot = $pdo->prepare("
            SELECT id, status FROM appointments 
            WHERE doctor_id = ? AND appointment_date = ? AND start_time = ? 
            AND status NOT IN ('cancelled', 'completed')
        ");
        $checkSlot->execute([$doctor_id, $appointment_date, $start_time]);
        
        if($checkSlot->rowCount() > 0) {
            $errors[] = "This time slot is already booked. Please select another time.";
        }
    }
    
    // 6. Create appointment if no errors
    if(empty($errors)) {
        $end_time = date('H:i:s', strtotime($start_time) + 1800);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, symptoms, reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
            ");
            $stmt->execute([$patient_id, $doctor_id, $appointment_date, $start_time, $end_time, $symptoms, $reason]);
            
            $formatted_date = date('F j, Y', strtotime($appointment_date));
            $formatted_time = date('g:i A', strtotime($start_time));
            
            $_SESSION['success'] = "Appointment booked successfully with Dr. $doctor_name on $formatted_date at $formatted_time";
            
            if($role == 'patient') {
                header("Location: my_appointments.php");
            } else {
                header("Location: appointments.php");
            }
            exit();
            
        } catch(PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// ============================================
// AJAX: GET AVAILABLE TIME SLOTS
// ============================================
if(isset($_GET['ajax_slots'])) {
    header('Content-Type: application/json');
    
    $doctor_id = $_GET['doctor_id'] ?? 0;
    $date = $_GET['date'] ?? '';
    
    if(empty($doctor_id) || empty($date)) {
        echo json_encode([]);
        exit();
    }
    
    $day_of_week = date('w', strtotime($date));
    $result = ['slots' => [], 'working_hours' => null, 'is_working_day' => false];
    
    // Check if doctor works on this day
    $workStmt = $pdo->prepare("
        SELECT start_time, end_time FROM working_hours 
        WHERE doctor_id = ? AND day_of_week = ?
    ");
    $workStmt->execute([$doctor_id, $day_of_week]);
    $hours = $workStmt->fetch();
    
    if(!$hours) {
        // Doctor doesn't work on this day
        echo json_encode(['slots' => [], 'is_working_day' => false, 'message' => 'Doctor not available on this day']);
        exit();
    }
    
    $result['is_working_day'] = true;
    $result['working_hours'] = [
        'start' => date('h:i A', strtotime($hours['start_time'])),
        'end' => date('h:i A', strtotime($hours['end_time']))
    ];
    
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
        $isAvailable = !in_array($slotTime, $bookedTimes);
        
        if($date == date('Y-m-d')) {
            if($slotTimestamp > $now && $isAvailable) {
                $slots[] = $slotTime;
            }
        } else {
            if($isAvailable) {
                $slots[] = $slotTime;
            }
        }
        $start += 1800;
    }
    
    $result['slots'] = $slots;
    echo json_encode($result);
    exit();
}

// ============================================
// AJAX: CHECK DOCTOR AVAILABILITY FOR A DAY
// ============================================
if(isset($_GET['check_availability'])) {
    header('Content-Type: application/json');
    
    $doctor_id = $_GET['doctor_id'] ?? 0;
    $date = $_GET['date'] ?? '';
    
    if(empty($doctor_id) || empty($date)) {
        echo json_encode(['available' => false, 'message' => 'Missing information']);
        exit();
    }
    
    $day_of_week = date('w', strtotime($date));
    $day_name = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day_of_week];
    
    // Check working hours
    $workStmt = $pdo->prepare("
        SELECT start_time, end_time FROM working_hours 
        WHERE doctor_id = ? AND day_of_week = ?
    ");
    $workStmt->execute([$doctor_id, $day_of_week]);
    $hours = $workStmt->fetch();
    
    if(!$hours) {
        echo json_encode([
            'available' => false, 
            'message' => "Doctor is not available on $day_name. Available days: Monday-Friday"
        ]);
        exit();
    }
    
    echo json_encode([
        'available' => true,
        'message' => "Doctor works on $day_name from " . date('h:i A', strtotime($hours['start_time'])) . " to " . date('h:i A', strtotime($hours['end_time'])),
        'working_hours' => [
            'start' => $hours['start_time'],
            'end' => $hours['end_time']
        ]
    ]);
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
    
    .header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 15px;
        margin-bottom: 25px;
        color: white;
    }
    
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
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border-radius: 10px;
        padding: 10px 15px;
        border: 1px solid #ddd;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 12px 30px;
        font-weight: bold;
        border-radius: 25px;
        color: white;
        width: 100%;
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
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }
    
    .availability-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 20px;
        font-size: 11px;
        margin-left: 10px;
    }
    
    .availability-available {
        background: #d4edda;
        color: #155724;
    }
    
    .availability-unavailable {
        background: #f8d7da;
        color: #721c24;
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }
    
    .alert-danger {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }
    
    .alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border-left: 4px solid #17a2b8;
    }
    
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
        .sidebar { width: 70px; }
        .sidebar span { display: none; }
        .main-content { margin-left: 70px; }
    }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-hospital"></i>
        <span>Clinic</span>
    </div>
    
    <?php if($role == 'admin' || $role == 'manager'): ?>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
    <a href="patients.php"><i class="fas fa-users"></i> <span>Patients</span></a>
    <a href="doctors.php"><i class="fas fa-user-md"></i> <span>Doctors</span></a>
    <a href="appointments.php"><i class="fas fa-calendar-check"></i> <span>Appointments</span></a>
    <a href="book_appointment.php" class="active"><i class="fas fa-calendar-plus"></i> <span>Book</span></a>
    <?php else: ?>
    <a href="patient_dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
    <a href="book_appointment.php" class="active"><i class="fas fa-calendar-plus"></i> <span>Book</span></a>
    <a href="my_appointments.php"><i class="fas fa-calendar-check"></i> <span>My Appointments</span></a>
    <?php endif; ?>
    
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="main-content">
    
    <div class="header">
        <h2><i class="fas fa-calendar-plus"></i> Book New Appointment</h2>
        <p><i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($full_name); ?></p>
    </div>
    
    <!-- Success Message -->
    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success mb-3">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
    <?php endif; ?>
    
    <!-- Error Message -->
    <?php if($error): ?>
    <div class="alert alert-danger mb-3">
        <i class="fas fa-exclamation-circle"></i> 
        <strong>Booking Failed!</strong><br>
        <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <!-- Booking Form -->
    <div class="form-card">
        <div class="form-card-header">
            <i class="fas fa-info-circle"></i> Appointment Information
        </div>
        <div class="form-card-body">
            <form method="POST" id="bookingForm">
                
                <!-- For Admin -->
                <?php if($role == 'admin' || $role == 'manager'): ?>
                <div class="mb-3">
                    <label class="form-label">Select Patient *</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach($all_patients as $pat): ?>
                        <option value="<?php echo $pat['id']; ?>">
                            <?php echo htmlspecialchars($pat['first_name'] . ' ' . $pat['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Select Doctor -->
                <div class="mb-3">
                    <label class="form-label">Select Doctor *</label>
                    <div class="row" id="doctorList">
                        <?php if(count($doctors) > 0): ?>
                            <?php foreach($doctors as $doc): ?>
                            <div class="col-md-6">
                                <div class="doctor-card" data-doctor-id="<?php echo $doc['id']; ?>" data-doctor-name="<?php echo htmlspecialchars($doc['full_name']); ?>" onclick="selectDoctor(this)">
                                    <div class="d-flex align-items-center">
                                        <div class="doctor-avatar me-3">
                                            <i class="fas fa-user-md"></i>
                                        </div>
                                        <div>
                                            <strong>Dr. <?php echo htmlspecialchars($doc['full_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($doc['specialization'] ?? 'General Doctor'); ?></small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-warning">No doctors available</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="doctor_id" id="selected_doctor_id" required>
                    <div id="availabilityMessage" class="mt-2"></div>
                </div>
                
                <!-- Date and Time -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Appointment Date *</label>
                        <input type="date" name="appointment_date" id="appointment_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Appointment Time *</label>
                        <select name="start_time" id="time_slots" class="form-select" required disabled>
                            <option value="">Select doctor and date first</option>
                        </select>
                        <div id="workingHoursInfo" class="small text-muted mt-1"></div>
                    </div>
                </div>
                
                <!-- Symptoms -->
                <div class="mb-3">
                    <label class="form-label">Symptoms / Reason *</label>
                    <textarea name="symptoms" id="symptoms" class="form-control" rows="4" 
                              placeholder="Describe your symptoms (e.g., fever, headache, tooth pain, etc.)" required></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Additional Notes (Optional)</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Any extra information..."></textarea>
                </div>
                
                <button type="submit" name="book_appointment" class="btn-submit">
                    <i class="fas fa-save"></i> Book Appointment
                </button>
                
            </form>
        </div>
    </div>
    
    <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle"></i> 
        <strong>Information:</strong> 
        <ul class="mb-0 mt-1">
            <li>Working hours: Monday - Friday, 8:00 AM to 5:00 PM</li>
            <li>Each appointment is 30 minutes long</li>
            <li>Please arrive 10 minutes before your scheduled time</li>
        </ul>
    </div>
</div>

<script>
    let selectedDoctorId = null;
    let selectedDoctorName = null;
    
    function selectDoctor(card) {
        // Remove selected class from all cards
        document.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        
        // Get doctor info
        selectedDoctorId = card.getAttribute('data-doctor-id');
        selectedDoctorName = card.getAttribute('data-doctor-name');
        document.getElementById('selected_doctor_id').value = selectedDoctorId;
        
        // Clear previous messages
        document.getElementById('availabilityMessage').innerHTML = '';
        document.getElementById('workingHoursInfo').innerHTML = '';
        
        // If date is selected, check availability
        let dateInput = document.getElementById('appointment_date');
        if(dateInput.value) {
            checkDoctorAvailability(selectedDoctorId, dateInput.value);
            loadTimeSlots(selectedDoctorId, dateInput.value);
        } else {
            document.getElementById('availabilityMessage').innerHTML = '<div class="alert alert-info">Please select a date to check doctor availability</div>';
        }
    }
    
    document.getElementById('appointment_date').addEventListener('change', function() {
        let date = this.value;
        if(!selectedDoctorId) {
            alert('Please select a doctor first');
            this.value = '';
            return;
        }
        checkDoctorAvailability(selectedDoctorId, date);
        loadTimeSlots(selectedDoctorId, date);
    });
    
    function checkDoctorAvailability(doctorId, date) {
        let availabilityDiv = document.getElementById('availabilityMessage');
        availabilityDiv.innerHTML = '<div class="alert alert-info"><span class="loading"></span> Checking availability...</div>';
        
        fetch(`?check_availability=1&doctor_id=${doctorId}&date=${date}`)
            .then(res => res.json())
            .then(data => {
                if(data.available) {
                    availabilityDiv.innerHTML = `<div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ${data.message}
                    </div>`;
                } else {
                    availabilityDiv.innerHTML = `<div class="alert alert-danger">
                        <i class="fas fa-times-circle"></i> ${data.message}
                    </div>`;
                    // Disable time slots if doctor not available
                    document.getElementById('time_slots').innerHTML = '<option value="">Doctor not available on this day</option>';
                    document.getElementById('time_slots').disabled = true;
                }
            })
            .catch(error => {
                availabilityDiv.innerHTML = '<div class="alert alert-warning">Could not check availability</div>';
            });
    }
    
    function loadTimeSlots(doctorId, date) {
        let timeSelect = document.getElementById('time_slots');
        let workingHoursInfo = document.getElementById('workingHoursInfo');
        
        timeSelect.innerHTML = '<option value=""><span class="loading"></span> Loading...</option>';
        timeSelect.disabled = true;
        workingHoursInfo.innerHTML = '';
        
        fetch(`?ajax_slots=1&doctor_id=${doctorId}&date=${date}`)
            .then(res => res.json())
            .then(data => {
                if(data.is_working_day === false) {
                    timeSelect.innerHTML = '<option value="">Doctor not available on this day</option>';
                    timeSelect.disabled = true;
                    if(data.message) {
                        workingHoursInfo.innerHTML = `<span class="text-danger">${data.message}</span>`;
                    }
                    return;
                }
                
                if(data.working_hours) {
                    workingHoursInfo.innerHTML = `<i class="fas fa-clock"></i> Working hours: ${data.working_hours.start} - ${data.working_hours.end}`;
                }
                
                timeSelect.innerHTML = '<option value="">Select a time</option>';
                
                if(data.slots.length === 0) {
                    timeSelect.innerHTML = '<option value="">No available slots for this date</option>';
                } else {
                    data.slots.forEach(slot => {
                        let option = document.createElement('option');
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
    
    function formatTime(time) {
        let [h, m] = time.split(':');
        let period = 'AM';
        let hour = parseInt(h);
        if(hour >= 12) { period = 'PM'; if(hour > 12) hour -= 12; }
        if(hour === 0) hour = 12;
        return `${hour}:${m} ${period}`;
    }
    
    // Form validation
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
            alert('Please select a time');
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