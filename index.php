<?php
session_start();
require_once 'config/database.php';

/* Redirect if already logged in */
if(isset($_SESSION['user_id'])){
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle language selection
if(isset($_POST['set_language'])) {
    $_SESSION['preferred_language'] = $_POST['language'];
    setcookie('user_language', $_POST['language'], time() + (86400 * 30), "/");
}

$lang = $_SESSION['preferred_language'] ?? $_COOKIE['user_language'] ?? 'en';

// Simple translations
$translations = [
    'en' => [
        'title' => 'Smart Clinic System',
        'subtitle' => 'Login to your account',
        'username' => 'Username or Email',
        'password' => 'Password',
        'login' => 'Login',
        'remember' => 'Remember me',
        'forgot' => 'Forgot password?',
        'no_account' => "Don't have an account?",
        'contact_admin' => 'Contact admin to create one',
        'invalid_credentials' => 'Invalid username/email or password!',
        'account_locked' => 'Your account is locked. Please contact admin.',
        'welcome_back' => 'Welcome back!',
    ],
    'rw' => [
        'title' => 'Smart Clinic System',
        'subtitle' => 'Injira muri konti yawe',
        'username' => 'Izina cyangwa Imeli',
        'password' => 'Ijambo ryibanga',
        'login' => 'Injira',
        'remember' => 'Unibukire',
        'forgot' => 'Wibagiwe ijambo ryibanga?',
        'no_account' => "Nta konti ufite?",
        'contact_admin' => 'Vugana na admin kugirango akurembere',
        'invalid_credentials' => 'Izina cyangwa ijambo ryibanga si byo!',
        'account_locked' => 'Konti yawe yarahagaritse. Vugana na admin.',
        'welcome_back' => 'Murakaza neza!',
    ],
    'fr' => [
        'title' => 'Smart Clinic System',
        'subtitle' => 'Connectez-vous à votre compte',
        'username' => 'Nom d\'utilisateur ou Email',
        'password' => 'Mot de passe',
        'login' => 'Connexion',
        'remember' => 'Se souvenir de moi',
        'forgot' => 'Mot de passe oublié?',
        'no_account' => "Vous n'avez pas de compte?",
        'contact_admin' => 'Contactez l\'administrateur',
        'invalid_credentials' => 'Nom d\'utilisateur/email ou mot de passe incorrect!',
        'account_locked' => 'Votre compte est verrouillé.',
        'welcome_back' => 'Bon retour!',
    ]
];

function __($key) {
    global $translations, $lang;
    return $translations[$lang][$key] ?? $key;
}

if(isset($_POST['login'])){

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    // Check by username OR email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if($user && password_verify($password, $user['password'])){

        // Check if account is active (if you have status column)
        // if($user['status'] == 'inactive') {
        //     $error = __('account_locked');
        // } else {
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Set patient_id if role is patient
            if($user['role'] == 'patient') {
                $patStmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
                $patStmt->execute([$user['id']]);
                $patient = $patStmt->fetch();
                if($patient) {
                    $_SESSION['patient_id'] = $patient['id'];
                }
            }
            
            // Remember me - set cookie for 30 days
            if($remember) {
                setcookie('user_id', $user['id'], time() + (86400 * 30), "/");
                setcookie('username', $user['username'], time() + (86400 * 30), "/");
            }
            
            $_SESSION['success'] = __("welcome_back") . " " . $user['full_name'];
            
            header("Location: dashboard.php");
            exit();
        // }
        
    } else {
        $error = __("invalid_credentials");
        
        // Log failed attempt (optional)
        // $ip = $_SERVER['REMOTE_ADDR'];
        // $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (?, ?, NOW())");
        // $stmt->execute([$username, $ip]);
    }
}

// Check for remember me cookie
if(!isset($_SESSION['user_id']) && isset($_COOKIE['user_id']) && isset($_COOKIE['username'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND username = ?");
    $stmt->execute([$_COOKIE['user_id'], $_COOKIE['username']]);
    $user = $stmt->fetch();
    
    if($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        header("Location: dashboard.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo __("title"); ?> - Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
        position: relative;
    }
    
    /* Animated background */
    .bg-animation {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        overflow: hidden;
    }
    
    .bg-animation span {
        position: absolute;
        display: block;
        width: 20px;
        height: 20px;
        background: rgba(255,255,255,0.1);
        bottom: -150px;
        animation: floatUp 15s infinite;
    }
    
    @keyframes floatUp {
        0% {
            transform: translateY(0) rotate(0deg);
            opacity: 1;
        }
        100% {
            transform: translateY(-1000px) rotate(720deg);
            opacity: 0;
        }
    }
    
    .login-card {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 440px;
        background: rgba(255,255,255,0.98);
        padding: 40px 35px;
        border-radius: 24px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        backdrop-filter: blur(10px);
        transition: transform 0.3s;
    }
    
    .login-card:hover {
        transform: translateY(-5px);
    }
    
    .logo {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .logo i {
        font-size: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    
    .login-title {
        font-size: 28px;
        font-weight: 700;
        text-align: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-bottom: 8px;
    }
    
    .subtitle {
        text-align: center;
        color: #6c757d;
        font-size: 14px;
        margin-bottom: 30px;
    }
    
    .input-group {
        margin-bottom: 20px;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .input-group-text {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-right: none;
        padding: 0 18px;
    }
    
    .input-group-text i {
        color: #667eea;
        font-size: 16px;
    }
    
    .form-control {
        height: 50px;
        border: 1px solid #e0e0e0;
        border-left: none;
        padding: 0 15px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-control:focus {
        box-shadow: none;
        border-color: #667eea;
    }
    
    .btn-login {
        height: 50px;
        font-weight: 600;
        border-radius: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        font-size: 16px;
        transition: all 0.3s;
    }
    
    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102,126,234,0.4);
    }
    
    .form-check-label {
        font-size: 13px;
        color: #6c757d;
    }
    
    .footer-text {
        text-align: center;
        margin-top: 25px;
        font-size: 12px;
        color: #adb5bd;
    }
    
    .demo-credentials {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 12px;
        margin-top: 25px;
        font-size: 12px;
    }
    
    .demo-credentials p {
        margin: 0;
        color: #6c757d;
    }
    
    .demo-credentials code {
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 11px;
    }
    
    .alert {
        border-radius: 12px;
        font-size: 13px;
        margin-bottom: 20px;
    }
    
    .language-selector {
        position: absolute;
        top: 20px;
        right: 20px;
        z-index: 10;
    }
    
    .language-selector select {
        background: rgba(255,255,255,0.9);
        border: none;
        border-radius: 20px;
        padding: 6px 12px;
        font-size: 12px;
        cursor: pointer;
    }
    
    .toggle-password {
        cursor: pointer;
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: #adb5bd;
    }
    
    .password-wrapper {
        position: relative;
    }
    
    .password-wrapper .form-control {
        padding-right: 45px;
    }
</style>
</head>
<body>

<!-- Language Selector -->
<div class="language-selector">
    <form method="POST">
        <select name="language" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 100px;">
            <option value="en" <?php echo $lang == 'en' ? 'selected' : ''; ?>>🇬🇧 English</option>
            <option value="rw" <?php echo $lang == 'rw' ? 'selected' : ''; ?>>🇷🇼 Kinyarwanda</option>
            <option value="fr" <?php echo $lang == 'fr' ? 'selected' : ''; ?>>🇫🇷 Français</option>
        </select>
        <input type="hidden" name="set_language" value="1">
    </form>
</div>

<!-- Animated Background -->
<div class="bg-animation">
    <?php for($i = 0; $i < 30; $i++): ?>
    <span style="left: <?php echo rand(0, 100); ?>%; width: <?php echo rand(10, 30); ?>px; height: <?php echo rand(10, 30); ?>px; animation-delay: <?php echo rand(0, 10); ?>s; animation-duration: <?php echo rand(8, 20); ?>s;"></span>
    <?php endfor; ?>
</div>

<div class="login-card">
    
    <div class="logo">
        <i class="fas fa-hospital-user"></i>
    </div>
    
    <div class="login-title"><?php echo __("title"); ?></div>
    <div class="subtitle"><?php echo __("subtitle"); ?></div>
    
    <?php if($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-user"></i>
            </span>
            <input type="text" name="username" class="form-control" 
                   placeholder="<?php echo __("username"); ?>" 
                   value="<?php echo isset($_COOKIE['username']) ? htmlspecialchars($_COOKIE['username']) : ''; ?>"
                   required autofocus>
        </div>
        
        <div class="input-group password-wrapper">
            <span class="input-group-text">
                <i class="fas fa-lock"></i>
            </span>
            <input type="password" name="password" id="password" class="form-control" 
                   placeholder="<?php echo __("password"); ?>" required>
            <span class="toggle-password" onclick="togglePassword()">
                <i class="fas fa-eye" id="toggleIcon"></i>
            </span>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember" 
                       <?php echo isset($_COOKIE['username']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="remember">
                    <i class="fas fa-memory"></i> <?php echo __("remember"); ?>
                </label>
            </div>
            <a href="forgot_password.php" class="text-decoration-none small">
                <i class="fas fa-question-circle"></i> <?php echo __("forgot"); ?>
            </a>
        </div>
        
        <button type="submit" name="login" class="btn btn-primary w-100 btn-login">
            <i class="fas fa-sign-in-alt"></i> <?php echo __("login"); ?>
        </button>
    </form>
    
    <div class="demo-credentials">
        <p class="text-center mb-2">
            <i class="fas fa-info-circle"></i> Demo Credentials:
        </p>
        <div class="row text-center">
            <div class="col">
                <code>Admin: admin / admin123</code>
            </div>
            <div class="col">
                <code>Doctor: dr.karekezi / admin123</code>
            </div>
            <div class="col">
                <code>Patient: (register first)</code>
            </div>
        </div>
    </div>
    
    <div class="footer-text">
        <i class="far fa-copyright"></i> <?php echo date('Y'); ?> <?php echo __("title"); ?> | v2.0
    </div>
    
    <div class="text-center mt-3">
        <a href="patient_auth.php?register" class="text-decoration-none small">
            <i class="fas fa-user-plus"></i> <?php echo __("no_account"); ?> <?php echo __("contact_admin"); ?>
        </a>
    </div>
</div>

<script>
    function togglePassword() {
        const password = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        
        if(password.type === 'password') {
            password.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            password.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // Generate random background positions
    const spans = document.querySelectorAll('.bg-animation span');
    spans.forEach(span => {
        span.style.left = Math.random() * 100 + '%';
        span.style.width = Math.random() * 30 + 10 + 'px';
        span.style.height = span.style.width;
        span.style.animationDelay = Math.random() * 10 + 's';
        span.style.animationDuration = Math.random() * 12 + 8 + 's';
    });
</script>

</body>
</html>