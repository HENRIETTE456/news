<?php
// ============================================
// CLINIC MANAGEMENT SYSTEM - COMPLETE
// One file does everything!
// ============================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$dbname = 'mis'; // Change to your database name
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================
// CREATE ALL TABLES IF NOT EXISTS
// ============================================
try {
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            role ENUM('admin', 'doctor', 'patient') DEFAULT 'patient',
            specialization VARCHAR(100),
            phone VARCHAR(20),
            location_lat DECIMAL(10,8) NULL,
            location_lng DECIMAL(11,8) NULL,
            preferred_language VARCHAR(10) DEFAULT 'en',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Working hours table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS working_hours (
            id INT PRIMARY KEY AUTO_INCREMENT,
            doctor_id INT NOT NULL,
            day_of_week TINYINT,
            start_time TIME,
            end_time TIME,
            FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Appointments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS appointments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            patient_id INT NOT NULL,
            doctor_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            symptoms TEXT,
            status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
            message_sent BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES users(id),
            FOREIGN KEY (doctor_id) REFERENCES users(id)
        )
    ");
    
    // Notifications table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create default admin if not exists
    $checkAdmin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    if($checkAdmin->rowCount() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, 'admin')")
            ->execute(['admin', $adminPass, 'System Administrator', 'admin@clinic.com']);
    }
    
} catch(PDOException $e) {
    // Tables might already exist
}

// ============================================
// LANGUAGE SYSTEM
// ============================================
if(isset($_POST['set_language'])) {
    $_SESSION['preferred_language'] = $_POST['language'];
    setcookie('user_language', $_POST['language'], time() + (86400 * 30), "/");
}

$lang = $_SESSION['preferred_language'] ?? $_COOKIE['user_language'] ?? 'en';

$translations = [
    'en' => [
        'app_title' => 'Clinic Management System',
        'dashboard' => 'Dashboard',
        'doctors' => 'Doctors',
        'patients' => 'Patients',
        'appointments' => 'Appointments',
        'profile' => 'Profile',
        'logout' => 'Logout',
        'login' => 'Login',
        'username' => 'Username',
        'password' => 'Password',
        'full_name' => 'Full Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'specialization' => 'Specialization',
        'location' => 'Location',
        'actions' => 'Actions',
        'add' => 'Add',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'working_hours' => 'Working Hours',
        'book_appointment' => 'Book Appointment',
        'my_appointments' => 'My Appointments',
        'symptoms' => 'Symptoms',
        'date' => 'Date',
        'time' => 'Time',
        'status' => 'Status',
        'confirmed' => 'Confirmed',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'select_doctor' => 'Select Doctor',
        'select_date' => 'Select Date',
        'select_time' => 'Select Time',
        'available_doctors' => 'Available Doctors Near You',
        'no_doctors' => 'No doctors found',
        'no_appointments' => 'No appointments found',
        'welcome' => 'Welcome',
        'invalid_credentials' => 'Invalid username or password',
        'access_denied' => 'Access denied',
        'appointment_booked' => 'Appointment booked successfully!',
        'appointment_cancelled' => 'Appointment cancelled!',
        'doctor_not_available' => 'Doctor not available at this time',
        'slot_taken' => 'Time slot already taken',
        'enter_symptoms' => 'Describe your symptoms',
        'clinic_location' => 'Clinic Location',
        'day' => 'Day',
        'start_time' => 'Start Time',
        'end_time' => 'End Time',
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'role' => 'Role',
        'admin' => 'Admin',
        'doctor' => 'Doctor',
        'patient' => 'Patient',
        'register' => 'Register',
        'already_have_account' => 'Already have an account?',
        'dont_have_account' => 'Don\'t have an account?',
    ],
    'rw' => [
        'app_title' => 'Sisitemu y\'Ibiro by\'Abaganga',
        'dashboard' => 'Dashbord',
        'doctors' => 'Abaganga',
        'patients' => 'Abarwayi',
        'appointments' => 'Appointement',
        'profile' => 'Ibiranga',
        'logout' => 'Sohora',
        'login' => 'Injira',
        'username' => 'Izina ry\'ukoresha',
        'password' => 'Ijambo ryibanga',
        'full_name' => 'Izina Ryuzuye',
        'email' => 'Imeli',
        'phone' => 'Telefone',
        'specialization' => 'Ubuhanga',
        'location' => 'Aho ari',
        'actions' => 'Ibikorwa',
        'add' => 'Ongeraho',
        'edit' => 'Hindura',
        'delete' => 'Siba',
        'save' => 'Bika',
        'cancel' => 'Gahagarika',
        'working_hours' => 'Amasaha akorera',
        'book_appointment' => 'Guhitamo appointement',
        'my_appointments' => 'Appointement zanjye',
        'symptoms' => 'Ibimenyetso',
        'date' => 'Itariki',
        'time' => 'Igihe',
        'status' => 'Ihame',
        'confirmed' => 'Byemejwe',
        'pending' => 'Bitegereje',
        'cancelled' => 'Byahagaritswe',
        'completed' => 'Byarangiye',
        'select_doctor' => 'Hitamo muganga',
        'select_date' => 'Hitamo itariki',
        'select_time' => 'Hitamo igihe',
        'available_doctors' => 'Abaganga bahari hafi yawe',
        'no_doctors' => 'Nta muganga uboneka',
        'no_appointments' => 'Nta appointement iboneka',
        'welcome' => 'Murakaza neza',
        'invalid_credentials' => 'Izina cyangwa ijambo ryibanga si byo',
        'access_denied' => 'Ntaburenganzira',
        'appointment_booked' => 'Appointement yemejwe!',
        'appointment_cancelled' => 'Appointement yahagaritswe!',
        'doctor_not_available' => 'Muganga ntabwo akorera muri iryo gihe',
        'slot_taken' => 'Icyo gihe kimaze gufatwa',
        'enter_symptoms' => 'Andika ibimenyetso ubona',
        'clinic_location' => 'Aho ivaro riherereye',
        'day' => 'Umunsi',
        'start_time' => 'Igihe gitangira',
        'end_time' => 'Igihe kirangirira',
        'sunday' => 'Ku Cyumweru',
        'monday' => 'Kuwa Mbere',
        'tuesday' => 'Kuwa Kabiri',
        'wednesday' => 'Kuwa Gatatu',
        'thursday' => 'Kuwa Kane',
        'friday' => 'Kuwa Gatanu',
        'saturday' => 'Kuwa Gatandatu',
        'role' => 'Uruhushya',
        'admin' => 'Muyobozi',
        'doctor' => 'Umuganga',
        'patient' => 'Umurwayi',
        'register' => 'Iyandikishe',
        'already_have_account' => 'Ufite konti?',
        'dont_have_account' => 'Nta konti ufite?',
    ],
    'fr' => [
        'app_title' => 'Système de Gestion de Clinique',
        'dashboard' => 'Tableau de bord',
        'doctors' => 'Médecins',
        'patients' => 'Patients',
        'appointments' => 'Rendez-vous',
        'profile' => 'Profil',
        'logout' => 'Déconnexion',
        'login' => 'Connexion',
        'username' => 'Nom d\'utilisateur',
        'password' => 'Mot de passe',
        'full_name' => 'Nom complet',
        'email' => 'Email',
        'phone' => 'Téléphone',
        'specialization' => 'Spécialisation',
        'location' => 'Emplacement',
        'actions' => 'Actions',
        'add' => 'Ajouter',
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'working_hours' => 'Heures de travail',
        'book_appointment' => 'Prendre rendez-vous',
        'my_appointments' => 'Mes rendez-vous',
        'symptoms' => 'Symptômes',
        'date' => 'Date',
        'time' => 'Heure',
        'status' => 'Statut',
        'confirmed' => 'Confirmé',
        'pending' => 'En attente',
        'cancelled' => 'Annulé',
        'completed' => 'Terminé',
        'select_doctor' => 'Choisir médecin',
        'select_date' => 'Choisir date',
        'select_time' => 'Choisir heure',
        'available_doctors' => 'Médecins disponibles près de vous',
        'no_doctors' => 'Aucun médecin trouvé',
        'no_appointments' => 'Aucun rendez-vous trouvé',
        'welcome' => 'Bienvenue',
        'invalid_credentials' => 'Nom d\'utilisateur ou mot de passe incorrect',
        'access_denied' => 'Accès refusé',
        'appointment_booked' => 'Rendez-vous confirmé!',
        'appointment_cancelled' => 'Rendez-vous annulé!',
        'doctor_not_available' => 'Médecin non disponible',
        'slot_taken' => 'Créneau déjà pris',
        'enter_symptoms' => 'Décrivez vos symptômes',
        'clinic_location' => 'Emplacement de la clinique',
        'day' => 'Jour',
        'start_time' => 'Heure début',
        'end_time' => 'Heure fin',
        'sunday' => 'Dimanche',
        'monday' => 'Lundi',
        'tuesday' => 'Mardi',
        'wednesday' => 'Mercredi',
        'thursday' => 'Jeudi',
        'friday' => 'Vendredi',
        'saturday' => 'Samedi',
        'role' => 'Rôle',
        'admin' => 'Admin',
        'doctor' => 'Médecin',
        'patient' => 'Patient',
        'register' => 'S\'inscrire',
        'already_have_account' => 'Déjà un compte?',
        'dont_have_account' => 'Pas de compte?',
    ]
];

function __($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}

// ============================================
// AUTHENTICATION
// ============================================
$current_user = null;
if(isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
}

// Handle login
if(isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $error = __("invalid_credentials");
    }
}

// Handle registration
if(isset($_POST['register'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? '';
    $role = 'patient';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $password, $full_name, $email, $phone, $role]);
        $_SESSION['success'] = "Account created! Please login.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch(PDOException $e) {
        $error = "Username or email already exists!";
    }
}

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// DOCTOR CRUD
// ============================================
if(isset($_GET['delete_doctor']) && $current_user && $current_user['role'] == 'admin') {
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'")->execute([$_GET['delete_doctor']]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_doctor']) && $current_user && $current_user['role'] == 'admin') {
    if(!empty($_POST['doctor_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, username=?, email=?, specialization=?, phone=?, location_lat=?, location_lng=? WHERE id=? AND role='doctor'");
        $stmt->execute([$_POST['full_name'], $_POST['username'], $_POST['email'], $_POST['specialization'], $_POST['phone'], $_POST['location_lat'] ?? null, $_POST['location_lng'] ?? null, $_POST['doctor_id']]);
    } else {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, specialization, phone, location_lat, location_lng) VALUES (?, ?, ?, ?, 'doctor', ?, ?, ?, ?)");
        $stmt->execute([$_POST['username'], $password, $_POST['full_name'], $_POST['email'], $_POST['specialization'], $_POST['phone'], $_POST['location_lat'] ?? null, $_POST['location_lng'] ?? null]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// WORKING HOURS
// ============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_hours'])) {
    $pdo->prepare("DELETE FROM working_hours WHERE doctor_id = ?")->execute([$_POST['doctor_id']]);
    foreach($_POST['start_time'] as $day => $start) {
        if(!empty($start) && !empty($_POST['end_time'][$day])) {
            $stmt = $pdo->prepare("INSERT INTO working_hours (doctor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['doctor_id'], $day, $start, $_POST['end_time'][$day]]);
        }
    }
    $_SESSION['success'] = __("working_hours") . " updated!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// BOOK APPOINTMENT
// ============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment']) && $current_user && $current_user['role'] == 'patient') {
    $doctor_id = $_POST['doctor_id'];
    $appointment_date = $_POST['appointment_date'];
    $start_time = $_POST['start_time'];
    $symptoms = $_POST['symptoms'];
    $end_time = date('H:i:s', strtotime($start_time) + 1800);
    
    // Check if slot is available
    $check = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND start_time = ? AND status != 'cancelled'");
    $check->execute([$doctor_id, $appointment_date, $start_time]);
    
    if($check->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, symptoms, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')");
        $stmt->execute([$current_user['id'], $doctor_id, $appointment_date, $start_time, $end_time, $symptoms]);
        
        // Send notification
        $doctor = $pdo->prepare("SELECT full_name FROM users WHERE id = ?")->execute([$doctor_id]);
        $message = "Appointment confirmed with Dr. " . ($doctor ?: 'Doctor') . " on $appointment_date at $start_time";
        $notify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notify->execute([$current_user['id'], $message]);
        
        $_SESSION['success'] = __("appointment_booked");
    } else {
        $_SESSION['error'] = __("slot_taken");
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// CANCEL APPOINTMENT
// ============================================
if(isset($_GET['cancel_appointment']) && $current_user) {
    $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND patient_id = ?")->execute([$_GET['cancel_appointment'], $current_user['id']]);
    $_SESSION['success'] = __("appointment_cancelled");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// GET AVAILABLE TIME SLOTS (AJAX)
// ============================================
if(isset($_GET['ajax_slots'])) {
    header('Content-Type: application/json');
    $doctor_id = $_GET['doctor_id'];
    $date = $_GET['date'];
    $day_of_week = date('w', strtotime($date));
    
    $work = $pdo->prepare("SELECT start_time, end_time FROM working_hours WHERE doctor_id = ? AND day_of_week = ?");
    $work->execute([$doctor_id, $day_of_week]);
    $hours = $work->fetch();
    
    if(!$hours) {
        echo json_encode([]);
        exit();
    }
    
    $booked = $pdo->prepare("SELECT start_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status != 'cancelled'");
    $booked->execute([$doctor_id, $date]);
    $bookedTimes = $booked->fetchAll(PDO::FETCH_COLUMN);
    
    $slots = [];
    $start = strtotime($hours['start_time']);
    $end = strtotime($hours['end_time']);
    $now = time();
    $todayStart = strtotime($date . ' 00:00:00');
    
    while($start < $end) {
        $slotTime = date('H:i:s', $start);
        $slotTimestamp = $todayStart + $start;
        if($slotTimestamp > $now && !in_array($slotTime, $bookedTimes)) {
            $slots[] = $slotTime;
        }
        $start += 1800;
    }
    
    echo json_encode($slots);
    exit();
}

// ============================================
// GET WORKING HOURS (AJAX)
// ============================================
if(isset($_GET['ajax_working_hours'])) {
    header('Content-Type: application/json');
    $doctor_id = $_GET['doctor_id'];
    $hours = $pdo->prepare("SELECT * FROM working_hours WHERE doctor_id = ?");
    $hours->execute([$doctor_id]);
    $result = [];
    while($row = $hours->fetch()) {
        $result[$row['day_of_week']] = ['start_time' => $row['start_time'], 'end_time' => $row['end_time']];
    }
    echo json_encode($result);
    exit();
}

// ============================================
// GET NEARBY DOCTORS (AJAX)
// ============================================
if(isset($_GET['ajax_nearby_doctors']) && $current_user) {
    header('Content-Type: application/json');
    $lat = $_GET['lat'];
    $lng = $_GET['lng'];
    
    $doctors = $pdo->query("
        SELECT u.*, 
        (6371 * acos(cos(radians($lat)) * cos(radians(u.location_lat)) 
        * cos(radians(u.location_lng) - radians($lng)) + sin(radians($lat)) 
        * sin(radians(u.location_lat)))) AS distance
        FROM users u
        WHERE u.role = 'doctor' AND u.location_lat IS NOT NULL
        HAVING distance < 20
        ORDER BY distance
    ")->fetchAll();
    
    echo json_encode($doctors);
    exit();
}

// ============================================
// DETERMINE PAGE CONTENT BASED ON LOGIN & ROLE
// ============================================
$page = $_GET['page'] ?? 'dashboard';
$edit_doctor = null;
if(isset($_GET['edit_doctor']) && $current_user && $current_user['role'] == 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'doctor'");
    $stmt->execute([$_GET['edit_doctor']]);
    $edit_doctor = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo __("app_title"); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f0f2f5; }
        .navbar-brand { font-weight: bold; }
        .sidebar { min-height: calc(100vh - 56px); background: white; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar .nav-link { color: #333; padding: 12px 20px; }
        .sidebar .nav-link:hover { background: #e9ecef; }
        .sidebar .nav-link.active { background: #0d6efd; color: white; }
        .main-content { padding: 20px; }
        .card { border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .language-switcher { position: fixed; bottom: 20px; right: 20px; z-index: 1000; background: white; padding: 8px 15px; border-radius: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .map-container { height: 300px; border-radius: 8px; border: 1px solid #ddd; }
        .toast-container { position: fixed; top: 70px; right: 20px; z-index: 1100; }
        .stat-card { text-align: center; padding: 20px; background: white; border-radius: 10px; }
        .stat-card h3 { font-size: 2rem; margin: 0; color: #0d6efd; }
    </style>
</head>
<body>

<!-- Language Switcher -->
<div class="language-switcher">
    <form method="POST" class="d-flex gap-2">
        <select name="language" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <option value="en" <?php echo $lang == 'en' ? 'selected' : ''; ?>>English</option>
            <option value="rw" <?php echo $lang == 'rw' ? 'selected' : ''; ?>>Kinyarwanda</option>
            <option value="fr" <?php echo $lang == 'fr' ? 'selected' : ''; ?>>Français</option>
        </select>
        <input type="hidden" name="set_language" value="1">
    </form>
</div>

<!-- Toast Notifications -->
<div class="toast-container">
    <?php if(isset($_SESSION['success'])): ?>
    <div class="toast show" data-bs-autohide="true" data-bs-delay="3000">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto">✅ Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    </div>
    <?php endif; ?>
    <?php if(isset($_SESSION['error'])): ?>
    <div class="toast show" data-bs-autohide="true" data-bs-delay="5000">
        <div class="toast-header bg-danger text-white">
            <strong class="me-auto">❌ Error</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="?page=dashboard">🏥 <?php echo __("app_title"); ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if($current_user): ?>
                    <li class="nav-item"><span class="nav-link text-white">👋 <?php echo __("welcome"); ?>, <?php echo htmlspecialchars($current_user['full_name']); ?></span></li>
                    <li class="nav-item"><a class="nav-link" href="?logout=1">🚪 <?php echo __("logout"); ?></a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="?page=login">🔐 <?php echo __("login"); ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="?page=register">📝 <?php echo __("register"); ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php if($current_user): ?>
        <div class="col-md-2 p-0 sidebar">
            <nav class="nav flex-column">
                <a class="nav-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>" href="?page=dashboard">📊 <?php echo __("dashboard"); ?></a>
                <?php if($current_user['role'] == 'admin'): ?>
                    <a class="nav-link <?php echo $page == 'doctors' ? 'active' : ''; ?>" href="?page=doctors">👨‍⚕️ <?php echo __("doctors"); ?></a>
                    <a class="nav-link <?php echo $page == 'patients' ? 'active' : ''; ?>" href="?page=patients">👤 <?php echo __("patients"); ?></a>
                <?php endif; ?>
                <?php if($current_user['role'] == 'patient'): ?>
                    <a class="nav-link <?php echo $page == 'book' ? 'active' : ''; ?>" href="?page=book">📅 <?php echo __("book_appointment"); ?></a>
                    <a class="nav-link <?php echo $page == 'my_appointments' ? 'active' : ''; ?>" href="?page=my_appointments">📋 <?php echo __("my_appointments"); ?></a>
                <?php endif; ?>
                <?php if($current_user['role'] == 'doctor'): ?>
                    <a class="nav-link <?php echo $page == 'my_schedule' ? 'active' : ''; ?>" href="?page=my_schedule">📅 <?php echo __("my_appointments"); ?></a>
                    <a class="nav-link <?php echo $page == 'set_hours' ? 'active' : ''; ?>" href="?page=set_hours">🕒 <?php echo __("working_hours"); ?></a>
                <?php endif; ?>
                <a class="nav-link" href="?page=profile">👤 <?php echo __("profile"); ?></a>
            </nav>
        </div>
        <div class="col-md-10 main-content">
        <?php else: ?>
        <div class="col-md-12 main-content">
        <?php endif; ?>

        <!-- ============================================ -->
        <!-- PAGE CONTENT -->
        <!-- ============================================ -->

        <?php if(!$current_user && $page == 'register'): ?>
        <!-- REGISTER PAGE -->
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h4>📝 <?php echo __("register"); ?></h4></div>
                    <div class="card-body">
                        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                        <form method="post">
                            <div class="mb-3"><input type="text" name="full_name" class="form-control" placeholder="<?php echo __("full_name"); ?>" required></div>
                            <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="<?php echo __("username"); ?>" required></div>
                            <div class="mb-3"><input type="email" name="email" class="form-control" placeholder="<?php echo __("email"); ?>" required></div>
                            <div class="mb-3"><input type="text" name="phone" class="form-control" placeholder="<?php echo __("phone"); ?>"></div>
                            <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="<?php echo __("password"); ?>" required></div>
                            <button type="submit" name="register" class="btn btn-primary w-100"><?php echo __("register"); ?></button>
                        </form>
                        <div class="mt-3 text-center"><a href="?page=login"><?php echo __("already_have_account"); ?></a></div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif(!$current_user || $page == 'login'): ?>
        <!-- LOGIN PAGE -->
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><h4>🔐 <?php echo __("login"); ?></h4></div>
                    <div class="card-body">
                        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                        <form method="post">
                            <div class="mb-3"><input type="text" name="username" class="form-control" placeholder="<?php echo __("username"); ?> / Email" required></div>
                            <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="<?php echo __("password"); ?>" required></div>
                            <button type="submit" name="login" class="btn btn-primary w-100"><?php echo __("login"); ?></button>
                        </form>
                        <div class="mt-3 text-center"><a href="?page=register"><?php echo __("dont_have_account"); ?></a></div>
                        <hr><small class="text-muted">Demo: admin / admin123 | doctor / doctor123 | patient / patient123</small>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif($current_user && $page == 'dashboard'): ?>
        <!-- DASHBOARD -->
        <h2>📊 <?php echo __("dashboard"); ?></h2>
        <div class="row">
            <?php 
            $totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role='doctor'")->fetchColumn();
            $totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role='patient'")->fetchColumn();
            $totalAppointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
            ?>
            <div class="col-md-4"><div class="stat-card"><h3><?php echo $totalDoctors; ?></h3><p><?php echo __("doctors"); ?></p></div></div>
            <div class="col-md-4"><div class="stat-card"><h3><?php echo $totalPatients; ?></h3><p><?php echo __("patients"); ?></p></div></div>
            <div class="col-md-4"><div class="stat-card"><h3><?php echo $totalAppointments; ?></h3><p><?php echo __("appointments"); ?></p></div></div>
        </div>

        <?php elseif($current_user && $current_user['role'] == 'admin' && $page == 'doctors'): ?>
        <!-- MANAGE DOCTORS -->
        <h2>👨‍⚕️ <?php echo __("doctors"); ?></h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#doctorModal">+ <?php echo __("add"); ?></button>
        
        <table class="table table-bordered">
            <thead class="table-dark"><tr><th>ID</th><th><?php echo __("full_name"); ?></th><th><?php echo __("username"); ?></th><th><?php echo __("email"); ?></th><th><?php echo __("specialization"); ?></th><th><?php echo __("phone"); ?></th><th><?php echo __("actions"); ?></th></tr></thead>
            <tbody>
            <?php 
            $doctors = $pdo->query("SELECT * FROM users WHERE role='doctor' ORDER BY id DESC")->fetchAll();
            foreach($doctors as $doc): ?>
            <tr>
                <td><?= $doc['id'] ?></td>
                <td><?= htmlspecialchars($doc['full_name']) ?></td>
                <td><?= htmlspecialchars($doc['username']) ?></td>
                <td><?= htmlspecialchars($doc['email']) ?></td>
                <td><?= htmlspecialchars($doc['specialization']) ?></td>
                <td><?= htmlspecialchars($doc['phone']) ?></td>
                <td>
                    <a href="?edit_doctor=<?= $doc['id'] ?>&page=doctors" class="btn btn-warning btn-sm">✏️</a>
                    <button class="btn btn-info btn-sm" onclick="showWorkingHoursModal(<?= $doc['id'] ?>, '<?= htmlspecialchars($doc['full_name']) ?>')">🕒</button>
                    <a href="?delete_doctor=<?= $doc['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Doctor Modal -->
        <div class="modal fade" id="doctorModal" tabindex="-1">
            <div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
                <div class="modal-header"><h5 class="modal-title"><?php echo $edit_doctor ? __("edit") : __("add"); ?> <?php echo __("doctor"); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?php if($edit_doctor): ?><input type="hidden" name="doctor_id" value="<?= $edit_doctor['id'] ?>"><?php endif; ?>
                    <div class="row"><div class="col-md-6 mb-2"><input type="text" name="full_name" class="form-control" placeholder="<?php echo __("full_name"); ?>" value="<?= htmlspecialchars($edit_doctor['full_name'] ?? '') ?>" required></div>
                    <div class="col-md-6 mb-2"><input type="text" name="username" class="form-control" placeholder="<?php echo __("username"); ?>" value="<?= htmlspecialchars($edit_doctor['username'] ?? '') ?>" required></div>
                    <div class="col-md-6 mb-2"><input type="email" name="email" class="form-control" placeholder="<?php echo __("email"); ?>" value="<?= htmlspecialchars($edit_doctor['email'] ?? '') ?>" required></div>
                    <div class="col-md-6 mb-2"><input type="text" name="specialization" class="form-control" placeholder="<?php echo __("specialization"); ?>" value="<?= htmlspecialchars($edit_doctor['specialization'] ?? '') ?>"></div>
                    <div class="col-md-6 mb-2"><input type="text" name="phone" class="form-control" placeholder="<?php echo __("phone"); ?>" value="<?= htmlspecialchars($edit_doctor['phone'] ?? '') ?>"></div>
                    <?php if(!$edit_doctor): ?><div class="col-md-6 mb-2"><input type="password" name="password" class="form-control" placeholder="<?php echo __("password"); ?>" required></div><?php endif; ?>
                    <div class="col-md-6 mb-2"><input type="text" name="location_lat" id="loc_lat" class="form-control" placeholder="Latitude" value="<?= htmlspecialchars($edit_doctor['location_lat'] ?? '') ?>"></div>
                    <div class="col-md-6 mb-2"><input type="text" name="location_lng" id="loc_lng" class="form-control" placeholder="Longitude" value="<?= htmlspecialchars($edit_doctor['location_lng'] ?? '') ?>"></div></div>
                    <div id="doctorMap" class="map-container"></div>
                </div>
                <div class="modal-footer"><button type="submit" name="save_doctor" class="btn btn-success">💾 <?php echo __("save"); ?></button></div>
            </form></div></div>
        </div>

        <?php elseif($current_user && $current_user['role'] == 'patient' && $page == 'book'): ?>
        <!-- BOOK APPOINTMENT -->
        <h2>📅 <?php echo __("book_appointment"); ?></h2>
        
        <!-- Get user location -->
        <div class="card mb-3"><div class="card-body">
            <button class="btn btn-info" onclick="getLocation()">📍 Use my current location to find nearby doctors</button>
            <div id="locationStatus" class="mt-2"></div>
        </div></div>
        
        <div id="doctorsList"></div>
        
        <form method="post" id="bookingForm" style="display:none;">
            <input type="hidden" name="doctor_id" id="selected_doctor">
            <div class="card"><div class="card-body">
                <div class="mb-3"><label><?php echo __("symptoms"); ?></label><textarea name="symptoms" class="form-control" rows="3" required></textarea></div>
                <div class="mb-3"><label><?php echo __("date"); ?></label><input type="date" name="appointment_date" id="appointment_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>"></div>
                <div class="mb-3"><label><?php echo __("time"); ?></label><select name="start_time" id="time_slots" class="form-control" required><option value="">Select doctor and date first</option></select></div>
                <button type="submit" name="book_appointment" class="btn btn-success">✅ <?php echo __("book_appointment"); ?></button>
            </div></div>
        </form>

        <?php elseif($current_user && $current_user['role'] == 'patient' && $page == 'my_appointments'): ?>
        <!-- MY APPOINTMENTS -->
        <h2>📋 <?php echo __("my_appointments"); ?></h2>
        <table class="table table-bordered">
            <thead class="table-dark"><tr><th><?php echo __("doctor"); ?></th><th><?php echo __("date"); ?></th><th><?php echo __("time"); ?></th><th><?php echo __("symptoms"); ?></th><th><?php echo __("status"); ?></th><th><?php echo __("actions"); ?></th></tr></thead>
            <tbody>
            <?php 
            $appointments = $pdo->prepare("
                SELECT a.*, u.full_name as doctor_name FROM appointments a 
                JOIN users u ON a.doctor_id = u.id 
                WHERE a.patient_id = ? ORDER BY a.appointment_date DESC
            ");
            $appointments->execute([$current_user['id']]);
            foreach($appointments as $apt): ?>
            <tr>
                <td><?= htmlspecialchars($apt['doctor_name']) ?></td>
                <td><?= $apt['appointment_date'] ?></td>
                <td><?= $apt['start_time'] ?></td>
                <td><?= htmlspecialchars(substr($apt['symptoms'], 0, 50)) ?></td>
                <td><span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : ($apt['status'] == 'cancelled' ? 'danger' : 'warning'); ?>"><?php echo __($apt['status']); ?></span></td>
                <td><?php if($apt['status'] == 'confirmed' && strtotime($apt['appointment_date']) > time()): ?><a href="?cancel_appointment=<?= $apt['id'] ?>&page=my_appointments" class="btn btn-danger btn-sm"><?php echo __("cancel"); ?></a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif($current_user && $current_user['role'] == 'doctor' && $page == 'my_schedule'): ?>
        <!-- DOCTOR SCHEDULE -->
        <h2>📅 <?php echo __("my_appointments"); ?></h2>
        <table class="table table-bordered">
            <thead class="table-dark"><tr><th><?php echo __("patient"); ?></th><th><?php echo __("date"); ?></th><th><?php echo __("time"); ?></th><th><?php echo __("symptoms"); ?></th><th><?php echo __("status"); ?></th></tr></thead>
            <tbody>
            <?php 
            $appointments = $pdo->prepare("
                SELECT a.*, u.full_name as patient_name FROM appointments a 
                JOIN users u ON a.patient_id = u.id 
                WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC
            ");
            $appointments->execute([$current_user['id']]);
            foreach($appointments as $apt): ?>
            <tr>
                <td><?= htmlspecialchars($apt['patient_name']) ?></td>
                <td><?= $apt['appointment_date'] ?></td>
                <td><?= $apt['start_time'] ?></td>
                <td><?= htmlspecialchars($apt['symptoms']) ?></td>
                <td><span class="badge bg-<?php echo $apt['status'] == 'confirmed' ? 'success' : 'warning'; ?>"><?php echo __($apt['status']); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php elseif($current_user && $current_user['role'] == 'doctor' && $page == 'set_hours'): ?>
        <!-- SET WORKING HOURS -->
        <h2>🕒 <?php echo __("working_hours"); ?></h2>
        <form method="post">
            <input type="hidden" name="doctor_id" value="<?= $current_user['id'] ?>">
            <table class="table table-bordered">
                <thead class="table-dark"><tr><th><?php echo __("day"); ?></th><th><?php echo __("start_time"); ?></th><th><?php echo __("end_time"); ?></th></tr></thead>
                <tbody>
                <?php 
                $daysList = [__('sunday'), __('monday'), __('tuesday'), __('wednesday'), __('thursday'), __('friday'), __('saturday')];
                $existing = [];
                $wh = $pdo->prepare("SELECT * FROM working_hours WHERE doctor_id = ?");
                $wh->execute([$current_user['id']]);
                while($row = $wh->fetch()) { $existing[$row['day_of_week']] = $row; }
                for($i=0;$i<7;$i++): ?>
                <tr>
                    <td><?php echo $daysList[$i]; ?></td>
                    <td><input type="time" name="start_time[<?= $i ?>]" class="form-control" value="<?= $existing[$i]['start_time'] ?? '' ?>"></td>
                    <td><input type="time" name="end_time[<?= $i ?>]" class="form-control" value="<?= $existing[$i]['end_time'] ?? '' ?>"></td>
                </tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <button type="submit" name="save_hours" class="btn btn-primary">💾 <?php echo __("save"); ?></button>
        </form>

        <?php elseif($current_user && $page == 'profile'): ?>
        <!-- PROFILE -->
        <h2>👤 <?php echo __("profile"); ?></h2>
        <div class="card"><div class="card-body">
            <p><strong><?php echo __("full_name"); ?>:</strong> <?php echo htmlspecialchars($current_user['full_name']); ?></p>
            <p><strong><?php echo __("username"); ?>:</strong> <?php echo htmlspecialchars($current_user['username']); ?></p>
            <p><strong><?php echo __("email"); ?>:</strong> <?php echo htmlspecialchars($current_user['email']); ?></p>
            <p><strong><?php echo __("phone"); ?>:</strong> <?php echo htmlspecialchars($current_user['phone']); ?></p>
            <p><strong><?php echo __("role"); ?>:</strong> <?php echo __($current_user['role']); ?></p>
        </div></div>

        <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Auto-hide toasts
document.querySelectorAll('.toast').forEach(el => new bootstrap.Toast(el, { autohide: true, delay: 3000 }).show());

// Working Hours Modal
function showWorkingHoursModal(doctorId, doctorName) {
    $('#wh_doctor_id').val(doctorId);
    $('#wh_doctor_name').text(doctorName);
    for(let i=0;i<7;i++) { $(`#start_${i}, #end_${i}`).val(''); }
    $.getJSON(`?ajax_working_hours=1&doctor_id=${doctorId}`, function(data) {
        for(let day in data) { $(`#start_${day}`).val(data[day].start_time); $(`#end_${day}`).val(data[day].end_time); }
    });
    new bootstrap.Modal(document.getElementById('workingHoursModal')).show();
}

// Working Hours Modal HTML (add after doctor modal)
if(!document.getElementById('workingHoursModal')) {
    $('body').append(`
        <div class="modal fade" id="workingHoursModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
            <input type="hidden" name="doctor_id" id="wh_doctor_id">
            <div class="modal-header"><h5 class="modal-title">🕒 <?php echo __("working_hours_for"); ?>: <span id="wh_doctor_name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><table class="table"><thead class="table-secondary"><tr><th><?php echo __("day"); ?></th><th><?php echo __("start_time"); ?></th><th><?php echo __("end_time"); ?></th></tr></thead><tbody>
            <?php for($i=0;$i<7;$i++): ?><tr><td><?php echo $daysList[$i] ?? 'Day '.$i; ?></td><td><input type="time" name="start_time[<?= $i ?>]" id="start_<?= $i ?>" class="form-control"></td><td><input type="time" name="end_time[<?= $i ?>]" id="end_<?= $i ?>" class="form-control"></td></tr><?php endfor; ?>
            </tbody></table></div><div class="modal-footer"><button type="submit" name="save_hours" class="btn btn-primary">💾 <?php echo __("save"); ?></button></div>
        </form></div></div></div>
    `);
}

// Location and booking functions
function getLocation() {
    if(navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showNearbyDoctors, showLocationError);
    } else {
        $('#locationStatus').html('<div class="alert alert-warning">Geolocation not supported</div>');
    }
}

function showLocationError() {
    $('#locationStatus').html('<div class="alert alert-danger">Please enable location access</div>');
}

function showNearbyDoctors(position) {
    const lat = position.coords.latitude;
    const lng = position.coords.longitude;
    $('#locationStatus').html('<div class="alert alert-info">Loading nearby doctors...</div>');
    
    $.getJSON(`?ajax_nearby_doctors=1&lat=${lat}&lng=${lng}`, function(doctors) {
        if(doctors.length === 0) {
            $('#doctorsList').html('<div class="alert alert-warning"><?php echo __("no_doctors"); ?></div>');
            return;
        }
        let html = '<div class="row">';
        doctors.forEach(doc => {
            html += `<div class="col-md-4 mb-3"><div class="card"><div class="card-body">
                <h5>Dr. ${doc.full_name}</h5>
                <p>${doc.specialization || 'General'}<br>📞 ${doc.phone || 'N/A'}<br>📍 ${doc.distance ? doc.distance.toFixed(1) : '?'} km away</p>
                <button class="btn btn-primary btn-sm" onclick="selectDoctor(${doc.id})">Select This Doctor</button>
            </div></div></div>`;
        });
        html += '</div>';
        $('#doctorsList').html(html);
        $('#locationStatus').html('<div class="alert alert-success">Found ' + doctors.length + ' doctors near you!</div>');
    });
}

function selectDoctor(doctorId) {
    $('#selected_doctor').val(doctorId);
    $('#bookingForm').show();
    $('#appointment_date').trigger('change');
    $('html, body').animate({ scrollTop: $('#bookingForm').offset().top }, 500);
}

// Load time slots when date changes
$('#appointment_date').on('change', function() {
    const doctorId = $('#selected_doctor').val();
    const date = $(this).val();
    if(doctorId && date) {
        $.getJSON(`?ajax_slots=1&doctor_id=${doctorId}&date=${date}`, function(slots) {
            let select = $('#time_slots');
            select.empty().append('<option value="">Select time</option>');
            slots.forEach(slot => select.append(`<option value="${slot}">${slot}</option>`));
        });
    }
});

// Doctor map for admin
let doctorMap, doctorMarker;
function initDoctorMap(lat=-1.9441, lng=30.0619) {
    if(doctorMap) doctorMap.remove();
    doctorMap = L.map('doctorMap').setView([lat, lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(doctorMap);
    if(doctorMarker) doctorMap.removeLayer(doctorMarker);
    doctorMarker = L.marker([lat, lng]).addTo(doctorMap);
    doctorMap.on('click', function(e) {
        if(doctorMarker) doctorMap.removeLayer(doctorMarker);
        doctorMarker = L.marker(e.latlng).addTo(doctorMap);
        $('#loc_lat').val(e.latlng.lat);
        $('#loc_lng').val(e.latlng.lng);
    });
}

<?php if($edit_doctor): ?>
setTimeout(() => initDoctorMap(<?= $edit_doctor['location_lat'] ?? '-1.9441' ?>, <?= $edit_doctor['location_lng'] ?? '30.0619' ?>), 500);
<?php endif; ?>

$('#doctorModal').on('shown.bs.modal', function() {
    let lat = $('#loc_lat').val() || -1.9441;
    let lng = $('#loc_lng').val() || 30.0619;
    initDoctorMap(parseFloat(lat), parseFloat(lng));
});
</script>

</body>
</html>