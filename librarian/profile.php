<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process profile update
$message = '';
$messageType = '';

// Get current user data
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Update profile
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        $message = "Name and email are required fields.";
        $messageType = "danger";
    } else {
        // Check if email already exists (and it's not the current user's email)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = "This email is already in use by another user.";
            $messageType = "danger";
        } else {
            // Update user profile
            $stmt = $conn->prepare("
                UPDATE users 
                SET name = ?, email = ?, department = ?, phone = ?, address = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param(
                "sssssi",
                $name, $email, $department, $phone, $address, $userId
            );
            
            if ($stmt->execute()) {
                // Update session data
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

// Change password
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All password fields are required.";
        $messageType = "danger";
    } elseif ($newPassword != $confirmPassword) {
        $message = "New password and confirmation do not match.";
        $messageType = "danger";
    } elseif (strlen($newPassword) < 6) {
        $message = "New password must be at least 6 characters long.";
        $messageType = "danger";
    } else {
        // Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
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

<h1 class="page-title">My Profile</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="dashboard-row">
    <!-- Profile Info -->
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
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" class="form-control" value="<?php echo htmlspecialchars($user['department']); ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Created</label>
                        <input type="text" class="form-control" value="<?php echo date('F j, Y', strtotime($user['created_at'])); ?>" readonly>
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
                        <label for="current_password">Current Password <span class="text-danger">*</span></label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password <span class="text-danger">*</span></label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required>
                        <small class="text-muted">Password must be at least 6 characters long.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Activity -->
        <div class="card mt-4">
            <div class="card-header">
                <h3>Recent Activity</h3>
            </div>
            <div class="card-body">
                <?php
                // Get recent activity for this user
                $activitySql = "
                    (SELECT 'issued_book' as type, b.title as item, ib.issue_date as date
                    FROM issued_books ib
                    JOIN books b ON ib.book_id = b.id
                    WHERE ib.user_id = ?
                    ORDER BY ib.issue_date DESC
                    LIMIT 3)
                    
                    UNION
                    
                    (SELECT 'returned_book' as type, b.title as item, ib.actual_return_date as date
                    FROM issued_books ib
                    JOIN books b ON ib.book_id = b.id
                    WHERE ib.user_id = ? AND ib.status = 'returned'
                    ORDER BY ib.actual_return_date DESC
                    LIMIT 3)
                    
                    UNION
                    
                    (SELECT 'notification' as type, LEFT(message, 50) as item, created_at as date
                    FROM notifications
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 3)
                    
                    ORDER BY date DESC
                    LIMIT 5
                ";
                
                $stmt = $conn->prepare($activitySql);
                $stmt->bind_param("iii", $userId, $userId, $userId);
                $stmt->execute();
                $activityResult = $stmt->get_result();
                $activities = [];
                
                if ($activityResult) {
                    while ($row = $activityResult->fetch_assoc()) {
                        $activities[] = $row;
                    }
                }
                ?>
                
                <?php if (count($activities) > 0): ?>
                    <ul class="activity-list">
                        <?php foreach ($activities as $activity): ?>
                            <li class="activity-item">
                                <div class="activity-icon">
                                    <?php if ($activity['type'] == 'issued_book'): ?>
                                        <i class="fas fa-book"></i>
                                    <?php elseif ($activity['type'] == 'returned_book'): ?>
                                        <i class="fas fa-undo"></i>
                                    <?php elseif ($activity['type'] == 'notification'): ?>
                                        <i class="fas fa-bell"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-info">
                                    <h4 class="activity-title">
                                        <?php 
                                        if ($activity['type'] == 'issued_book') {
                                            echo 'Issued Book: ' . htmlspecialchars($activity['item']);
                                        } elseif ($activity['type'] == 'returned_book') {
                                            echo 'Returned Book: ' . htmlspecialchars($activity['item']);
                                        } elseif ($activity['type'] == 'notification') {
                                            echo 'Notification: ' . htmlspecialchars($activity['item']) . '...';
                                        }
                                        ?>
                                    </h4>
                                    <div class="activity-meta">
                                        <span class="activity-time"><?php echo date('M d, Y H:i', strtotime($activity['date'])); ?></span>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-center">No recent activity found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>