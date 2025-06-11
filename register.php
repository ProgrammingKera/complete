<?php
session_start();
include 'includes/config.php';

$message = '';
$messageType = '';
$generatedId = '';

// Password validation function
function validatePassword($password) {
    $errors = [];
    
    // Check minimum length (8 characters)
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    // Check for at least one special character (@, #, $)
    if (!preg_match('/[@#$]/', $password)) {
        $errors[] = "Password must contain at least one special character (@, #, $)";
    }
    
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $role = $_POST['role']; // Get role from form
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $message = "All required fields must be filled out";
        $messageType = "danger";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match";
        $messageType = "danger";
    } else {
        // Validate password strength
        $passwordErrors = validatePassword($password);
        if (!empty($passwordErrors)) {
            $message = implode(". ", $passwordErrors);
            $messageType = "danger";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $message = "Email already registered";
                $messageType = "danger";
            } else {
                // Generate unique ID
                $uniqueId = generateUniqueId($conn, $role);
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (unique_id, name, email, password, role, department, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $uniqueId, $name, $email, $hashedPassword, $role, $department, $phone);
                
                if ($stmt->execute()) {
                    $generatedId = $uniqueId;
                    $message = "Registration successful! Your unique ID is: <strong>$uniqueId</strong><br>Please save this ID for login. You can now login using either your email or unique ID.";
                    $messageType = "success";
                    
                    // Don't redirect immediately, show the ID first
                } else {
                    $message = "Error registering user: " . $stmt->error;
                    $messageType = "danger";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'%3E%3Cpath fill='white' d='M542.2 32.01c-54.5 3.5-113.1 15.6-170.2 35.6c-13.1 4.6-26.1 9.5-39 14.7c-12.9-5.2-25.9-10.1-39-14.7C237.8 47.61 179.2 35.51 124.8 32.01C111.1 31.11 96 41.41 96 56.01v384c0 14.6 15.1 24.9 28.8 23.9c54.5-3.5 113.1-15.6 170.2-35.6c13.1-4.6 26.1-9.5 39-14.7c12.9 5.2 25.9 10.1 39 14.7c57.1 20 115.7 32.1 170.2 35.6c13.7 1 28.8-9.3 28.8-23.9v-384C576 41.41 560.9 31.11 542.2 32.01zM528 432c-48.6-3.1-100.8-13.7-153.1-31.7c-13.7-4.7-27.2-9.8-40.9-15.2V96.89c13.7 5.4 27.2 10.5 40.9 15.2C427.2 129.2 479.4 139.8 528 143.9V432zM48 56.01c0-14.6 15.1-24.9 28.8-23.9c54.5 3.5 113.1 15.6 170.2 35.6c13.1 4.6 26.1 9.5 39 14.7v288.2c-12.9 5.2-25.9 10.1-39 14.7c-57.1 20-115.7 32.1-170.2 35.6C63.1 480.9 48 470.6 48 456V56.01z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .register-page {
             min-height: 100vh;
            background: linear-gradient(rgba(13, 71, 161, 0.9), rgba(21, 101, 192, 0.9)),
                        url('https://images.pexels.com/photos/2041540/pexels-photo-2041540.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        .register-header {
            background: #0d47a1;
            color: white;
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            transform: rotate(45deg);
        }

        .register-header h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 600;
            position: relative;
        }

        .register-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 1em;
            position: relative;
        }

        .register-form {
            padding: 40px 30px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col {
            flex: 1;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
            outline: none;
        }

        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            font-size: 0.9em;
            display: none;
        }

        .password-requirements.show {
            display: block;
        }

        .password-requirements h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 1em;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            color: #6c757d;
        }

        .requirement i {
            margin-right: 8px;
            width: 16px;
        }

        .requirement.valid {
            color: #28a745;
        }

        .requirement.invalid {
            color: #dc3545;
        }

        .btn-primary {
            background: #0d47a1;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            width: 100%;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1565c0;
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .login-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #0d47a1;
            text-decoration: none;
            font-weight: 500;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            animation: fadeIn 0.3s ease-out;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .unique-id-display {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .unique-id-display h3 {
            color: #1976d2;
            margin: 0 0 10px 0;
        }

        .unique-id-display .id-value {
            font-size: 2em;
            font-weight: bold;
            color: #0d47a1;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            letter-spacing: 2px;
        }

        .unique-id-display .copy-btn {
            background: #2196f3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px;
        }

        .unique-id-display .copy-btn:hover {
            background: #1976d2;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body class="register-page">
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> Register</h1>
            <p>Create your library account</p>
        </div>
        
        <div class="register-form">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
                
                <?php if ($messageType == 'success' && !empty($generatedId)): ?>
                    <div class="unique-id-display">
                        <h3><i class="fas fa-id-card"></i> Your Unique ID</h3>
                        <div class="id-value" id="uniqueId"><?php echo $generatedId; ?></div>
                        <button type="button" class="copy-btn" onclick="copyToClipboard()">
                            <i class="fas fa-copy"></i> Copy ID
                        </button>
                        <p><strong>Important:</strong> Save this ID safely. You can use either your email or this unique ID to login.</p>
                        <a href="index.php" class="btn-primary" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                            <i class="fas fa-sign-in-alt"></i> Go to Login
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($messageType != 'success'): ?>
            <form method="POST" action="" id="registerForm">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="role"><i class="fas fa-user-tag"></i> Role *</label>
                    <select id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password *</label>
                            <input type="password" id="password" name="password" required>
                            <div class="password-requirements" id="passwordRequirements">
                                <h4>Password Requirements:</h4>
                                <div class="requirement" id="length-req">
                                    <i class="fas fa-times"></i>
                                    <span>At least 8 characters long</span>
                                </div>
                                <div class="requirement" id="uppercase-req">
                                    <i class="fas fa-times"></i>
                                    <span>At least one uppercase letter (A-Z)</span>
                                </div>
                                <div class="requirement" id="special-req">
                                    <i class="fas fa-times"></i>
                                    <span>At least one special character (@, #, $)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="department"><i class="fas fa-building"></i> Department</label>
                            <input type="text" id="department" name="department">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-user-plus"></i> Register
                </button>
                
                <a href="index.php" class="login-link">
                    <i class="fas fa-sign-in-alt"></i> Already have an account? Login here
                </a>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const uniqueId = document.getElementById('uniqueId').textContent;
            navigator.clipboard.writeText(uniqueId).then(function() {
                const btn = document.querySelector('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                btn.style.background = '#4caf50';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '#2196f3';
                }, 2000);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const lengthReq = document.getElementById('length-req');
            const uppercaseReq = document.getElementById('uppercase-req');
            const specialReq = document.getElementById('special-req');
            const passwordRequirements = document.getElementById('passwordRequirements');

            if (passwordInput) {
                // Show password requirements when password field is focused
                passwordInput.addEventListener('focus', function() {
                    passwordRequirements.classList.add('show');
                });

                // Hide password requirements when password field loses focus (with delay)
                passwordInput.addEventListener('blur', function() {
                    setTimeout(() => {
                        if (document.activeElement !== confirmPasswordInput) {
                            passwordRequirements.classList.remove('show');
                        }
                    }, 200);
                });

                function validatePassword() {
                    const password = passwordInput.value;
                    let isValid = true;

                    // Check length
                    if (password.length >= 8) {
                        lengthReq.classList.add('valid');
                        lengthReq.classList.remove('invalid');
                        lengthReq.querySelector('i').className = 'fas fa-check';
                    } else {
                        lengthReq.classList.add('invalid');
                        lengthReq.classList.remove('valid');
                        lengthReq.querySelector('i').className = 'fas fa-times';
                        isValid = false;
                    }

                    // Check uppercase
                    if (/[A-Z]/.test(password)) {
                        uppercaseReq.classList.add('valid');
                        uppercaseReq.classList.remove('invalid');
                        uppercaseReq.querySelector('i').className = 'fas fa-check';
                    } else {
                        uppercaseReq.classList.add('invalid');
                        uppercaseReq.classList.remove('valid');
                        uppercaseReq.querySelector('i').className = 'fas fa-times';
                        isValid = false;
                    }

                    // Check special characters
                    if (/[@#$]/.test(password)) {
                        specialReq.classList.add('valid');
                        specialReq.classList.remove('invalid');
                        specialReq.querySelector('i').className = 'fas fa-check';
                    } else {
                        specialReq.classList.add('invalid');
                        specialReq.classList.remove('valid');
                        specialReq.querySelector('i').className = 'fas fa-times';
                        isValid = false;
                    }

                    // Check if passwords match
                    const passwordsMatch = password === confirmPasswordInput.value && password.length > 0;

                    // Enable/disable submit button
                    if (submitBtn) {
                        submitBtn.disabled = !(isValid && passwordsMatch);
                    }

                    return isValid;
                }

                passwordInput.addEventListener('input', function() {
                    validatePassword();
                    // Show requirements when typing
                    if (this.value.length > 0) {
                        passwordRequirements.classList.add('show');
                    }
                });

                if (confirmPasswordInput) {
                    confirmPasswordInput.addEventListener('input', validatePassword);
                }

                // Form submission validation
                const form = document.getElementById('registerForm');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!validatePassword()) {
                            e.preventDefault();
                            alert('Please ensure your password meets all requirements.');
                        }

                        if (passwordInput.value !== confirmPasswordInput.value) {
                            e.preventDefault();
                            alert('Passwords do not match.');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>