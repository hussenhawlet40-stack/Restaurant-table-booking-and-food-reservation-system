<?php
require_once "connection.php";

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $phone = trim($_POST["phone"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $confirm_password = trim($_POST["confirm_password"] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif (strlen($name) < 4) {
        $error = "Name must be at least 4 characters";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (!preg_match('/^(\+251[79]|09|07)\d{8}$/', $phone)) {
        $error = "Please enter a valid Ethiopian phone number (09xxxxxxxx, 07xxxxxxxx, +2519xxxxxxxx, or +2517xxxxxxxx)";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            
            if ($check->rowCount() > 0) {
                $error = "Email address is already registered";
            } else {
                // Create user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
                
                if ($stmt->execute([$name, $email, $phone, $hashedPassword])) {
                    $success = "Registration successful! Redirecting to login...";
                    header("refresh:2;url=login.php");
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        } catch (Exception $e) {
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Restaurant Register</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="js/ethiopian-phone-validation.js"></script>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: Arial; 
    background: url('https://images.unsplash.com/photo-1555396273-367ea4eb4db5?ixlib=rb-4.0.3&auto=format&fit=crop&w=2074&q=80') center/cover fixed,
                linear-gradient(135deg, rgba(0,0,0,0.7), rgba(0,0,0,0.5));
    min-height: 100vh; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
}
.container { 
    background: rgba(255,255,255,0.95); 
    backdrop-filter: blur(10px); 
    padding: 40px; 
    border-radius: 20px; 
    box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
    width: 100%; 
    max-width: 450px; 
    animation: slideUp 0.6s ease; 
}
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.logo { text-align: center; margin-bottom: 30px; }
.logo h1 { font-size: 2.5rem; background: linear-gradient(45deg, #ff6b6b, #4ecdc4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 5px; }
.logo p { color: #666; }
.form-group { margin-bottom: 20px; }
.form-input { 
    width: 100%; 
    padding: 15px; 
    border: 2px solid #eee; 
    border-radius: 10px; 
    font-size: 16px; 
    transition: 0.3s; 
    background: white; 
}
.form-input:focus { outline: none; border-color: #4ecdc4; box-shadow: 0 0 10px rgba(78,205,196,0.3); }
.form-input.error { border-color: #ff6b6b; }
.btn { 
    width: 100%; 
    padding: 15px; 
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4); 
    color: white; 
    border: none; 
    border-radius: 10px; 
    font-size: 16px; 
    font-weight: bold; 
    cursor: pointer; 
    transition: 0.3s; 
    margin-bottom: 20px; 
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.btn:disabled { opacity: 0.7; cursor: not-allowed; }
.message { 
    padding: 12px; 
    border-radius: 8px; 
    margin-bottom: 20px; 
    text-align: center; 
    font-weight: 500; 
}
.error { background: rgba(255,107,107,0.1); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.3); }
.success { background: rgba(78,205,196,0.1); color: #4ecdc4; border: 1px solid rgba(78,205,196,0.3); }
.login { text-align: center; padding-top: 20px; border-top: 1px solid #eee; }
.login a { color: #4ecdc4; text-decoration: none; font-weight: 500; }
.login a:hover { text-decoration: underline; }
.password-toggle { 
    position: absolute; 
    right: 15px; 
    top: 50%; 
    transform: translateY(-50%); 
    cursor: pointer; 
    color: #999; 
    user-select: none; 
}
.password-field { position: relative; }
.strength-meter { 
    height: 4px; 
    background: #eee; 
    border-radius: 2px; 
    margin-top: 5px; 
    overflow: hidden; 
}
.strength-bar { 
    height: 100%; 
    width: 0%; 
    transition: 0.3s; 
    border-radius: 2px; 
}
.strength-weak { background: #ff6b6b; }
.strength-medium { background: #ffa726; }
.strength-strong { background: #4ecdc4; }
@media (max-width: 768px) { 
    .container { margin: 20px; padding: 30px; } 
    .logo h1 { font-size: 2rem; } 
}
</style>
</head>
<body>
<div class="container">
    <div class="logo">
        <h1>🍽️ Restaurant</h1>
        <p>Create your account to get started</p>
    </div>

    <?php if ($error): ?>
        <div class="message error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="message success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>
        <div class="form-group">
            <input type="text" name="name" id="name" class="form-input" placeholder="Full Name" 
                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <input type="email" name="email" id="email" class="form-input" placeholder="Email Address" 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <input type="tel" name="phone" id="phone" class="form-input" placeholder="09xxxxxxxx or +2519xxxxxxxx " 
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
            
        </div>

        <div class="form-group">
            <div class="password-field">
                <input type="password" name="password" id="password" class="form-input" placeholder="Password" required>
                <span class="password-toggle" onclick="togglePassword('password', this)">👁️</span>
            </div>
            <div class="strength-meter">
                <div class="strength-bar" id="strengthBar"></div>
            </div>
        </div>

        <div class="form-group">
            <div class="password-field">
                <input type="password" name="confirm_password" id="confirmPassword" class="form-input" placeholder="Confirm Password" required>
                <span class="password-toggle" onclick="togglePassword('confirmPassword', this)">👁️</span>
            </div>
        </div>

        <button type="submit" class="btn" id="submitBtn">
            <span id="btnText">Create Account</span>
            <span id="loading" style="display:none;">Creating account...</span>
        </button>
    </form>

    <div class="login">
        <p>Already have an account? <a href="login.php">Sign in here</a></p>
    </div>
</div>

<script>
function togglePassword(fieldId, toggle) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
        toggle.textContent = '🙈';
    } else {
        field.type = 'password';
        toggle.textContent = '👁️';
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    
    // Check password strength criteria
    if (password.length >= 6) strength++;
    if (/[a-zA-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    
    // Update strength bar
    const strengthBar = document.getElementById('strengthBar');
    const percentage = (strength / 3) * 100;
    strengthBar.style.width = percentage + '%';
    
    if (strength === 1) {
        strengthBar.className = 'strength-bar strength-weak';
    } else if (strength === 2) {
        strengthBar.className = 'strength-bar strength-medium';
    } else if (strength === 3) {
        strengthBar.className = 'strength-bar strength-strong';
    }
    
    return strength;
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const phone = document.getElementById('phone');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const loading = document.getElementById('loading');
    
    // Reset previous errors
    [name, email, phone, password, confirmPassword].forEach(field => {
        field.classList.remove('error');
    });
    
    let hasError = false;
    
    // Validate name
    if (!name.value.trim() || name.value.trim().length < 4) {
        name.classList.add('error');
        name.focus();
        hasError = true;
    }
    
    // Validate email
    if (!email.value.trim()) {
        email.classList.add('error');
        if (!hasError) email.focus();
        hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        email.classList.add('error');
        if (!hasError) email.focus();
        hasError = true;
    }
    
    // Validate phone (Ethiopian format)
    if (!phone.value.trim()) {
        phone.classList.add('error');
        if (!hasError) phone.focus();
        hasError = true;
    } else if (!/^(\+251[79]|09|07)\d{8}$/.test(phone.value.replace(/[\s\-\(\)]/g, ''))) {
        phone.classList.add('error');
        if (!hasError) phone.focus();
        hasError = true;
    }
    
    // Validate password
    if (!password.value.trim() || password.value.length < 6) {
        password.classList.add('error');
        if (!hasError) password.focus();
        hasError = true;
    }
    
    // Validate confirm password
    if (!confirmPassword.value.trim()) {
        confirmPassword.classList.add('error');
        if (!hasError) confirmPassword.focus();
        hasError = true;
    } else if (password.value !== confirmPassword.value) {
        confirmPassword.classList.add('error');
        if (!hasError) confirmPassword.focus();
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        return false;
    }
    
    // Show loading
    btnText.style.display = 'none';
    loading.style.display = 'inline';
    btn.disabled = true;
});

// Real-time validation
document.getElementById('name').addEventListener('input', function() {
    this.classList.remove('error');
});

document.getElementById('email').addEventListener('input', function() {
    this.classList.remove('error');
});

// Ethiopian phone validation is handled by the external library

document.getElementById('password').addEventListener('input', function() {
    this.classList.remove('error');
    checkPasswordStrength(this.value);
});

document.getElementById('confirmPassword').addEventListener('input', function() {
    this.classList.remove('error');
    const password = document.getElementById('password').value;
    if (this.value && this.value !== password) {
        this.classList.add('error');
    } else {
        this.classList.remove('error');
    }
});
</script>
</body>
</html>
