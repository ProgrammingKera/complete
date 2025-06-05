<?php
include_once '../includes/header.php';

// Check if user is student or faculty
if ($_SESSION['role'] != 'student' && $_SESSION['role'] != 'faculty') {
    header('Location: ../index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Process profile update
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    if (empty($name) || empty($email)) {
        $message = "Name and email are required fields.";
        $messageType = "danger";
    } else {
        // Check if email exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "This email is already in use.";
            $messageType = "danger";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, department = ?, phone = ?, address = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $email, $department, $phone, $address, $userId);
            
            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $message = "Profile updated successfully.";
                $messageType = "success";
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $message = "Error updating profile: " . $stmt->error;
                $messageType = "danger";
            }
        }
    }
}

// Process password change
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All password fields are required.";
        $messageType = "danger";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match.";
        $messageType = "danger";
    } elseif (strlen($newPassword) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "danger";
    } else {
        if (password_verify($currentPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                $message = "Password changed successfully.";
                $messageType = "success";
            } else {
                $message = "Error changing password: " . $stmt->error;
                $messageType = "danger";
            }
        } else {
            $message = "Current password is incorrect.";
            $messageType = "danger";
        }
    }
}
?>

<div class="container">
    <h1 class="page-title">My Profile</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-row">
        <!-- Profile Information -->
        <div class="dashboard-col">
            <div class="card">
                <div class="card-header">
                    <h3>Profile Information</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="department">Department</label>
                                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user['department']); ?>">
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>

                        <div class="form-group text-right">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="dashboard-col">
            <div class="card">
                <div class="card-header">
                    <h3>Change Password</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <small class="text-muted">Password must be at least 6 characters long</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="form-group text-right">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>