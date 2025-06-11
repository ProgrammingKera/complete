<?php
session_start();
include 'includes/config.php';

$message = '';
$messageType = '';
$validToken = false;
$token = isset($_GET['token']) ? $_GET['token'] : '';

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

// Verify token
if (!empty($token)) {
    $stmt = $conn->prepare("
        SELECT pr.*, u.email, u.id as user_id
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $reset = $result->fetch_assoc();
        $validToken = true;
    } else {
        $message = "Invalid or expired reset token. Please request a new password reset.";
        $messageType = "danger";
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate password strength
    $passwordErrors = validatePassword($password);
    if (!empty($passwordErrors)) {
        $message = implode(". ", $passwordErrors);
        $messageType = "danger";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "danger";
    } else {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $reset['user_id']);
        
        if ($stmt->execute()) {
            // Delete all reset tokens for this user
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->bind_param("i", $reset['user_id']);
            $stmt->execute();
            
            $message = "Password has been reset successfully. You can now login with your new password.";
            $messageType = "success";
            
            // Redirect to login page after 3 seconds
            header("refresh:3;url=index.php");
        } else {
            $message = "Error resetting password. Please try again.";
            $messageType = "danger";
        }
    }
}

// Create password_resets table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_token (token)
)";
$conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'%3E%3Cpath fill='white' d='M542.2 32.01c-54.5 3.5-113.1 15.6-170.2 35.6c-13.1 4.6-26.1 9.5-39 14.7c-12.9-5.2-25.9-10.1-39-14.7C237.8 47.61 179.2 35.51 124.8 32.01C111.1 31.11 96 41.41 96 56.01v384c0 14.6 15.1 24.9 28.8 23.9c54.5-3.5 113.1-15.6 170.2-35.6c-13.1-4.6-26.1-9.5-39-14.7c12.9 5.2 25.9 10.1 39 14.7c57.1 20 115.7 32.1 170.2 35.6c13.7 1 28.8-9.3 28.8-23.9v-384C576 41.41 560.9 31.11 542.2 32.01zM528 432c-48.6-3.1-100.8-13.7-153.1-31.7c-13.7-4.7-27.2-9.8-40.9-15.2V96.89c13.7 5.4 27.2 10.5 40.9 15.2C427.2 129.2 479.4 139.8 528 143.9V432zM48 56.01c0-14.6 15.1-24.9 28.8-23.9c54.5 3.5 113.1 15.6 170.2 35.6c13.1 4.6 26.1 9.5 39 14.7v288.2c-12.9 5.2-25.9 10.1-39 14.7c-57.1 20-115.7 32.1-170.2 35.6C63.1 480.9 48 470.6 48 456V56.01z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .reset-password-page {
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                        url('https://images.pexels.com/photos/590493/pexels-photo-590493.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-password-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        .reset-password-header {
            background: #0d47a1;
            color: white;
            padding: 40px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .reset-password-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 60%);
            transform: rotate(45deg);
        }

        .reset-password-header h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 600;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .reset-password-header p {
            margin: 15px 0 0;
            opacity: 0.9;
            font-size: 1.1em;
            position: relative;
        }

        .reset-password-form {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 500;
            font-size: 0.95em;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e1e1;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
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

        .form-group .text-muted {
            display: block;
            margin-top: 5px;
            font-size: 0.85em;
            color: #666;
        }

        .btn-primary {
            background: #0d47a1;
            color: white;
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            width: 100%;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(13, 71, 161, 0.2);
            margin-bottom: 20px;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 71, 161, 0.3);
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-link {
            color: #0d47a1;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-link:hover {
            color: #1565c0;
            transform: translateX(-5px);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            animation: fadeIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .text-center {
            text-align: center;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
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

        @media (max-width: 480px) {
            .reset-password-container {
                margin: 10px;
            }
            
            .reset-password-header {
                padding: 30px 20px;
            }
            
            .reset-password-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="reset-password-page">
    <div class="reset-password-container">
        <div class="reset-password-header">
            <h1><i class="fas fa-key"></i> Reset Password</h1>
            <p>Enter your new password</p>
        </div>
        
        <div class="reset-password-form">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($validToken): ?>
                <form method="POST" action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" id="resetForm">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your new password" required>
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
                    
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="forgot_password.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="text-center">
                <a href="index.php" class="btn btn-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const lengthReq = document.getElementById('length-req');
            const uppercaseReq = document.getElementById('uppercase-req');
            const specialReq = document.getElementById('special-req');
            const passwordRequirements = document.getElementById('passwordRequirements');

            if (passwordInput && confirmPasswordInput) {
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
                    submitBtn.disabled = !(isValid && passwordsMatch);

                    return isValid;
                }

                passwordInput.addEventListener('input', function() {
                    validatePassword();
                    // Show requirements when typing
                    if (this.value.length > 0) {
                        passwordRequirements.classList.add('show');
                    }
                });

                confirmPasswordInput.addEventListener('input', validatePassword);

                // Form submission validation
                document.getElementById('resetForm').addEventListener('submit', function(e) {
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
        });
    </script>
</body>
</html>