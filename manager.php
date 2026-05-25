<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'doctor';
$page = basename($_SERVER['PHP_SELF']);

/* =========================
   SAVE APPOINTMENT (MANAGER ONLY)
========================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_appointment'])){

    if($role != 'manager'){
        die("Unauthorized access");
    }

    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $symptoms = trim($_POST['symptoms']);
    $datetime = $_POST['appointment_datetime'];

    // SYMPTOM LOGIC
    $priority = "General Case";

    if(stripos($symptoms,'fever') !== false){
        $priority = "High Fever Case";
    }elseif(stripos($symptoms,'teeth') !== false){
        $priority = "Dental Case";
    }

    $reason = $symptoms . " - " . $priority;

    // FIXED INSERT (correct order)
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_datetime, reason, status)
        VALUES (?,?,?,?,?)
    ");

    $stmt->execute([
        $patient_id,
        $doctor_id,
        $datetime,
        $reason,
        'scheduled'
    ]);

    header("Location: dashboard.php?view=manager");
    exit();
}

/* =========================
   DATA
========================= */

$patients = $pdo->query("SELECT * FROM patients")->fetchAll();
$doctors = $pdo->query("SELECT * FROM users WHERE role='doctor'")->fetchAll();

$appointments = $pdo->query("
SELECT a.*, p.first_name, p.last_name, u.full_name AS doctor_name
FROM appointments a
JOIN patients p ON a.patient_id=p.id
JOIN users u ON a.doctor_id=u.id
ORDER BY a.appointment_datetime DESC
LIMIT 10
")->fetchAll();

$patientCount = count($patients);
$doctorCount = count($doctors);
$appointmentCount = count($appointments);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Clinic System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    margin:0;
    background:#0a0f1c;
    color:white;
    font-family:Arial;
}

/* SIDEBAR */
.sidebar{
    position:fixed;
    width:240px;
    height:100vh;
    background:#111827;
    padding:15px;
}

.logo{
    text-align:center;
    color:#22c55e;
    font-size:22px;
    font-weight:bold;
    margin-bottom:20px;
}

.sidebar a{
    display:block;
    padding:10px;
    color:#cbd5e1;
    text-decoration:none;
    margin:5px 0;
    border-radius:8px;
}

.sidebar a:hover{background:#1f2937;}
.sidebar a.active{background:#22c55e;color:white;}

/* MAIN */
.main-content{
    margin-left:240px;
    padding:20px;
}

.header{
    background:#16a34a;
    padding:15px;
    border-radius:10px;
    margin-bottom:15px;
}

/* CARDS */
.card-box{
    padding:15px;
    border-radius:10px;
    color:white;
}

.blue{background:#2563eb;}
.green{background:#16a34a;}
.orange{background:#f59e0b;}

/* TABLE */
.table-dark{
    background:#111827;
    color:white;
}

/* FORM */
input, select, textarea{
    margin-bottom:10px;
}

/* RESPONSIVE */
@media(max-width:768px){
    .sidebar{width:70px;}
    .main-content{margin-left:70px;}
}

</style>

</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">

<div class="logo">🏥 Clinic</div>

<a href="dashboard.php" class="<?= $page=='dashboard.php'?'active':'' ?>">📊 Dashboard</a>
<a href="patients.php">👨‍⚕️ Patients</a>
<a href="doctors.php">🩺 Doctors</a>
<a href="appointments.php">📅 Appointments</a>

<?php if($role=='manager'): ?>
<a href="dashboard.php?view=manager" class="<?= isset($_GET['view'])?'active':'' ?>">
🧑‍💼 Manager Panel
</a>
<?php endif; ?>

<a href="logout.php">🚪 Logout</a>

</div>

<!-- MAIN -->
<div class="main-content">

<div class="header">
<h3>Welcome <?= htmlspecialchars($user_name) ?> 👋</h3>
<p>Role: <?= ucfirst($role) ?></p>
</div>

<!-- STATS -->
<div class="row g-3">

<div class="col-md-4">
<div class="card card-box blue">
Patients <h3><?= $patientCount ?></h3>
</div>
</div>

<div class="col-md-4">
<div class="card card-box green">
Doctors <h3><?= $doctorCount ?></h3>
</div>
</div>

<div class="col-md-4">
<div class="card card-box orange">
Appointments <h3><?= $appointmentCount ?></h3>
</div>
</div>

</div>

<!-- MANAGER PANEL -->
<?php if($role=='manager' && isset($_GET['view']) && $_GET['view']=='manager'): ?>

<br>
<h4>🧑‍💼 Manager Panel - Create Appointment</h4>

<form method="post">

<select name="patient_id" class="form-control" required>
<option value="">Select Patient</option>
<?php foreach($patients as $p): ?>
<option value="<?= $p['id'] ?>">
<?= $p['first_name'].' '.$p['last_name'] ?>
</option>
<?php endforeach; ?>
</select>

<select name="doctor_id" class="form-control" required>
<option value="">Select Doctor</option>
<?php foreach($doctors as $d): ?>
<option value="<?= $d['id'] ?>">
<?= $d['full_name'] ?>
</option>
<?php endforeach; ?>
</select>

<textarea name="symptoms" class="form-control" placeholder="Enter symptoms..." required></textarea>

<input type="datetime-local" name="appointment_datetime" class="form-control" required>

<button class="btn btn-success" name="create_appointment">
Create Appointment
</button>

</form>

<?php endif; ?>

<!-- APPOINTMENTS -->
<br>
<h5>Latest Appointments</h5>

<table class="table table-dark table-hover">

<tr>
<th>#</th>
<th>Patient</th>
<th>Doctor</th>
<th>Date</th>
<th>Status</th>
</tr>

<?php $i=1; foreach($appointments as $a): ?>

<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($a['first_name'].' '.$a['last_name']) ?></td>
<td><?= htmlspecialchars($a['doctor_name']) ?></td>
<td><?= $a['appointment_datetime'] ?></td>
<td><?= ucfirst($a['status']) ?></td>
</tr>

<?php endforeach; ?>

</table>

</div>

</body>
</html>