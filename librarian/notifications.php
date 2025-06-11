<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process notification operations
$message = '';
$messageType = '';

// Send new notification
if (isset($_POST['send_notification'])) {
    $userIds = $_POST['user_ids'];
    $notificationMsg = trim($_POST['message']);
    
    // Basic validation
    if (empty($userIds) || empty($notificationMsg)) {
        $message = "Please select at least one user and enter a message.";
        $messageType = "danger";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Count successful notifications
            $successCount = 0;
            
            // Send notification to each selected user
            foreach ($userIds as $userId) {
                if (sendNotification($conn, $userId, $notificationMsg)) {
                    $successCount++;
                }
            }
            
            $conn->commit();
            
            if ($successCount > 0) {
                $message = "Notification sent successfully to {$successCount} users.";
                $messageType = "success";
            } else {
                $message = "No notifications were sent.";
                $messageType = "warning";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error sending notifications: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Notification marked as read.";
        $messageType = "success";
    } else {
        $message = "Error updating notification: " . $stmt->error;
        $messageType = "danger";
    }
}

// Delete notification
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Notification deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Error deleting notification: " . $stmt->error;
        $messageType = "danger";
    }
}

// Get all users for notification sending
$users = [];
$userSql = "SELECT id, name, email, role FROM users ORDER BY name";
$userResult = $conn->query($userSql);
if ($userResult) {
    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get all notifications
$notifications = [];
$notifSql = "
    SELECT n.*, u.name as user_name, u.email as user_email
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
";
$notifResult = $conn->query($notifSql);
if ($notifResult) {
    while ($row = $notifResult->fetch_assoc()) {
        $notifications[] = $row;
    }
}
?>

<h1 class="page-title">Notifications</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="dashboard-row">
    <!-- Send Notification -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Send New Notification</h3>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="user_ids">Select Recipients <span class="text-danger">*</span></label>
                        <div class="user-selection-container">
                            <div class="selection-options">
                                <a href="#" onclick="selectAllUsers(); return false;">Select All</a> | 
                                <a href="#" onclick="deselectAllUsers(); return false;">Deselect All</a> | 
                                <a href="#" onclick="selectUsersByRole('student'); return false;">All Students</a> | 
                                <a href="#" onclick="selectUsersByRole('faculty'); return false;">All Faculty</a>
                            </div>
                            <select id="user_ids" name="user_ids[]" multiple class="form-control" style="height: 150px;" required>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" data-role="<?php echo $user['role']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>) - <?php echo ucfirst($user['role']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Notification Message <span class="text-danger">*</span></label>
                        <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <div class="form-group text-right">
                        <button type="submit" name="send_notification" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Notification Templates -->
    <div class="dashboard-col">
        <div class="card">
            <div class="card-header">
                <h3>Notification Templates</h3>
            </div>
            <div class="card-body">
                <div class="template-list">
                    <div class="template-item" onclick="useTemplate('Your book is due for return tomorrow. Please return it to avoid fines.')">
                        <h4>Due Date Reminder</h4>
                        <p>Your book is due for return tomorrow. Please return it to avoid fines.</p>
                    </div>
                    
                    <div class="template-item" onclick="useTemplate('The library will be closed for maintenance on [DATE] from [TIME] to [TIME]. We apologize for any inconvenience.')">
                        <h4>Library Closure</h4>
                        <p>The library will be closed for maintenance on [DATE] from [TIME] to [TIME]. We apologize for any inconvenience.</p>
                    </div>
                    
                    <div class="template-item" onclick="useTemplate('New books have arrived in the library. Visit us to check out the latest additions to our collection.')">
                        <h4>New Arrivals</h4>
                        <p>New books have arrived in the library. Visit us to check out the latest additions to our collection.</p>
                    </div>
                    
                    <div class="template-item" onclick="useTemplate('Your fine of $[AMOUNT] is pending payment. Please visit the library to settle your account.')">
                        <h4>Fine Reminder</h4>
                        <p>Your fine of $[AMOUNT] is pending payment. Please visit the library to settle your account.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="section-title mt-4">All Notifications</h2>

<div class="table-container">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>User</th>
                <th>Message</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($notification['user_name']); ?><br>
                            <small><?php echo htmlspecialchars($notification['user_email']); ?></small></td>
                        <td><?php echo htmlspecialchars($notification['message']); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></td>
                        <td>
                            <?php if ($notification['is_read']): ?>
                                <span class="badge badge-success">Read</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Unread</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$notification['is_read']): ?>
                                <a href="?mark_read=1&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-check"></i> Mark as Read
                                </a>
                            <?php endif; ?>
                            
                            <a href="?delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirmDelete('Are you sure you want to delete this notification?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">No notifications found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
// Function to select all users
function selectAllUsers() {
    const select = document.getElementById('user_ids');
    for (let i = 0; i < select.options.length; i++) {
        select.options[i].selected = true;
    }
}

// Function to deselect all users
function deselectAllUsers() {
    const select = document.getElementById('user_ids');
    for (let i = 0; i < select.options.length; i++) {
        select.options[i].selected = false;
    }
}

// Function to select users by role
function selectUsersByRole(role) {
    const select = document.getElementById('user_ids');
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].getAttribute('data-role') === role) {
            select.options[i].selected = true;
        } else {
            select.options[i].selected = false;
        }
    }
}

// Function to use template
function useTemplate(template) {
    document.getElementById('message').value = template;
}
</script>

<style>
.user-selection-container {
    margin-bottom: 15px;
}

.selection-options {
    margin-bottom: 5px;
    font-size: 0.9em;
}

.template-list {
    display: grid;
    gap: 15px;
}

.template-item {
    padding: 15px;
    background-color: var(--gray-100);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.template-item:hover {
    background-color: var(--gray-200);
}

.template-item h4 {
    margin: 0 0 10px 0;
    font-weight: 600;
}

.template-item p {
    margin: 0;
    font-size: 0.9em;
    color: var(--text-light);
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>