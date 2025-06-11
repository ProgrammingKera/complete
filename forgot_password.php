<?php
date_default_timezone_set('Asia/Karachi');
session_start();
include 'includes/config.php';
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Generate reset token
$token = bin2hex(random_bytes(32));
$createdAt = date('Y-m-d H:i:s');
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Delete any existing reset tokens for this user
$stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();

// Store reset token
$stmt = $conn->prepare("
    INSERT INTO password_resets (user_id, token, created_at, expires_at)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("isss", $user['id'], $token, $createdAt, $expiresAt);
        
        if ($stmt->execute()) {
            // Create reset link
            $resetLink = "http://{$_SERVER['HTTP_HOST']}/reset_password.php?token=" . $token;
            
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mmoizrashad@gmail.com'; // Your Gmail address
                $mail->Password = 'zyen yedp tcsg drok'; // Your Gmail app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Recipients
                $mail->setFrom('mmoizrashad@gmail.com', 'Library Management System');
                $mail->addAddress($email, $user['name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "
    <div style='background-color: #F7F9FC; padding: 30px; font-family: Arial, sans-serif; color: #333;'>
        <div style='max-width: 600px; margin: auto; background-color: #ffffff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
            <h2 style='color: #0A74DA;'>Password Reset Request</h2>
            <p>Dear {$user['name']},</p>
            <p>We received a request to reset your password. Please click the button below to proceed:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetLink}' 
                   style='background-color: #0A74DA; color: white; text-decoration: none; padding: 12px 24px; font-size: 16px; border-radius: 5px; display: inline-block;'>
                    Reset Password
                </a>
            </div>
            <p>This link will expire in <strong>1 hour</strong>.</p>
            <p>If you didn't request a password reset, you can safely ignore this email.</p>
            <p style='margin-top: 30px;'>Best regards,<br><strong>Library Management System</strong></p>
        </div>
    </div>
";

                
                $mail->send();
                $message = "Password reset instructions have been sent to your email address.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Error sending email: " . $mail->ErrorInfo;
                $messageType = "danger";
            }
        } else {
            $message = "Error generating reset token. Please try again.";
            $messageType = "danger";
        }
    } else {
        $message = "No account found with this email address.";
        $messageType = "danger";
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
    <title>Forgot Password - Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 576 512'%3E%3Cpath fill='white' d='M542.2 32.01c-54.5 3.5-113.1 15.6-170.2 35.6c-13.1 4.6-26.1 9.5-39 14.7c-12.9-5.2-25.9-10.1-39-14.7C237.8 47.61 179.2 35.51 124.8 32.01C111.1 31.11 96 41.41 96 56.01v384c0 14.6 15.1 24.9 28.8 23.9c54.5-3.5 113.1-15.6 170.2-35.6c13.1-4.6 26.1-9.5 39-14.7c12.9 5.2 25.9 10.1 39 14.7c57.1 20 115.7 32.1 170.2 35.6c13.7 1 28.8-9.3 28.8-23.9v-384C576 41.41 560.9 31.11 542.2 32.01zM528 432c-48.6-3.1-100.8-13.7-153.1-31.7c-13.7-4.7-27.2-9.8-40.9-15.2V96.89c13.7 5.4 27.2 10.5 40.9 15.2C427.2 129.2 479.4 139.8 528 143.9V432zM48 56.01c0-14.6 15.1-24.9 28.8-23.9c54.5 3.5 113.1 15.6 170.2 35.6c13.1 4.6 26.1 9.5 39 14.7v288.2c-12.9 5.2-25.9 10.1-39 14.7c-57.1 20-115.7 32.1-170.2 35.6C63.1 480.9 48 470.6 48 456V56.01z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .forgot-password-page {
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

        .forgot-password-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
            backdrop-filter: blur(10px);
        }

        .forgot-password-header {
            background: #0d47a1;
            color: white;
            padding: 40px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .forgot-password-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 60%);
            transform: rotate(45deg);
        }

        .forgot-password-header h1 {
            margin: 0;
            font-size: 2.2em;
            font-weight: 600;
            position: relative;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .forgot-password-header p {
            margin: 15px 0 0;
            opacity: 0.9;
            font-size: 1.1em;
            position: relative;
        }

        .forgot-password-form {
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
        }

        .form-group input:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
            outline: none;
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

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 71, 161, 0.3);
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
            .forgot-password-container {
                margin: 10px;
            }
            
            .forgot-password-header {
                padding: 30px 20px;
            }
            
            .forgot-password-form {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body class="forgot-password-page">
    <div class="forgot-password-container">
        <div class="forgot-password-header">
            <h1><i class="fas fa-key"></i> Forgot Password</h1>
            <p>Enter your email to reset your password</p>
        </div>
        
        <div class="forgot-password-form">
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your registered email" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Instructions
                </button>
                
                <div class="text-center">
                    <a href="index.php" class="btn btn-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>