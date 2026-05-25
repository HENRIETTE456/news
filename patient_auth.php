<?php
session_start();
require_once 'config/database.php';

// If already logged in, redirect to dashboard
if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'patient'){
    header("Location: patient_dashboard.php");
    exit();
}

$error = '';
$success = '';

// ============================================
// PATIENT REGISTRATION
// ============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    
    $errors = [];
    
    // Validation
    if(empty($username)) $errors[] = "Username is required";
    if(empty($password)) $errors[] = "Password is required";
    if($password !== $confirm_password) $errors[] = "Passwords do not match";
    if(empty($full_name)) $errors[] = "Full name is required";
    if(empty($email)) $errors[] = "Email is required";
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if(strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    // Check if username or email already exists
    if(empty($errors)) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        if($check->rowCount() > 0) {
            $errors[] = "Username or email already exists";
        }
    }
    
    if(empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Split full name into first and last name
            $nameParts = explode(' ', $full_name, 2);
            $first_name = $nameParts[0];
            $last_name = $nameParts[1] ?? '';
            
            // Insert into users table
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, role, phone, preferred_language, created_at)
                VALUES (?, ?, ?, ?, 'patient', ?, 'en', NOW())
            ");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $phone]);
            $user_id = $pdo->lastInsertId();
            
            // Insert into patients table
            $stmt2 = $pdo->prepare("
                INSERT INTO patients (user_id, first_name, last_name, dob, gender, phone, address, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt2->execute([$user_id, $first_name, $last_name, $dob, $gender, $phone, $address]);
            $patient_id = $pdo->lastInsertId();
            
            $pdo->commit();
            
            // Auto login after registration
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'patient';
            $_SESSION['full_name'] = $full_name;
            $_SESSION['patient_id'] = $patient_id;
            $_SESSION['username'] = $username;
            
            $_SESSION['success'] = "Registration successful! Welcome $full_name";
            header("Location: patient_dashboard.php");
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
    
    if(!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

// ============================================
// PATIENT LOGIN
// ============================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("
        SELECT u.*, p.id as patient_id 
        FROM users u 
        LEFT JOIN patients p ON u.id = p.user_id 
        WHERE (u.username = ? OR u.email = ?) AND u.role = 'patient'
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['patient_id'] = $user['patient_id'];
        $_SESSION['username'] = $user['username'];
        
        $_SESSION['success'] = "Welcome back, " . $user['full_name'];
        header("Location: patient_dashboard.php");
        exit();
    } else {
        $error = "Invalid username/email or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Patient Portal - Clinic System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Arial, sans-serif;
    }
    
    .auth-card {
        max-width: 500px;
        margin: 50px auto;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        overflow: hidden;
    }
    
    .auth-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .auth-header i {
        font-size: 50px;
        margin-bottom: 10px;
    }
    
    .auth-header h3 {
        margin: 0;
        font-weight: bold;
    }
    
    .auth-body {
        padding: 30px;
    }
    
    .form-control {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #e0e0e0;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
    }
    
    .btn-auth {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        padding: 12px;
        font-weight: bold;
        border-radius: 25px;
        width: 100%;
        color: white;
    }
    
    .btn-auth:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }
    
    .toggle-link {
        color: #667eea;
        cursor: pointer;
        text-decoration: none;
    }
    
    .toggle-link:hover {
        text-decoration: underline;
    }
    
    .alert {
        border-radius: 10px;
    }
</style>
</head>
<body>

<div class="container">
    <div class="auth-card">
        
        <!-- Login Form -->
        <div id="loginForm">
            <div class="auth-header">
                <i class="fas fa-hospital-user"></i>
                <h3>Patient Login</h3>
                <p>Welcome back! Please login to your account</p>
            </div>
            <div class="auth-body">
                <?php if($error && !isset($_POST['register'])): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-user"></i> Username or Email</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter your username or email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-auth">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                <div class="text-center mt-4">
                    <p>Don't have an account? 
                        <a href="#" class="toggle-link" onclick="showRegister()">
                            <i class="fas fa-user-plus"></i> Register here
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Registration Form -->
        <div id="registerForm" style="display: none;">
            <div class="auth-header">
                <i class="fas fa-user-plus"></i>
                <h3>Patient Registration</h3>
                <p>Create your account to book appointments online</p>
            </div>
            <div class="auth-body">
                <?php if($error && isset($_POST['register'])): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" onsubmit="return validateForm()">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" placeholder="Choose username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" placeholder="Your full name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="Phone number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" name="dob" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Your home address"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Min 6 characters" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Confirm password" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terms" required>
                        <label class="form-check-label">I agree to the Terms and Conditions</label>
                    </div>
                    <button type="submit" name="register" class="btn btn-auth">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
                <div class="text-center mt-4">
                    <p>Already have an account? 
                        <a href="#" class="toggle-link" onclick="showLogin()">
                            <i class="fas fa-sign-in-alt"></i> Login here
                        </a>
                    </p>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
    function showRegister() {
        document.getElementById('loginForm').style.display = 'none';
        document.getElementById('registerForm').style.display = 'block';
    }
    
    function showLogin() {
        document.getElementById('registerForm').style.display = 'none';
        document.getElementById('loginForm').style.display = 'block';
    }
    
    function validateForm() {
        let password = document.getElementById('password').value;
        let confirm = document.getElementById('confirmPassword').value;
        
        if(password !== confirm) {
            alert('Passwords do not match!');
            return false;
        }
        if(password.length < 6) {
            alert('Password must be at least 6 characters!');
            return false;
        }
        return true;
    }
    
    // Show register form if URL has ?register
    <?php if(isset($_GET['register'])): ?>
    showRegister();
    <?php endif; ?>
</script>

</body>
</html>