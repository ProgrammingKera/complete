<?php
// Include header
include_once '../includes/header.php';

// Check if user is a librarian
checkUserRole('librarian');

// Process feedback deletion
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_feedback'])) {
    $feedbackId = (int)$_POST['feedback_id'];
    
    $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param("i", $feedbackId);
    
    if ($stmt->execute()) {
        $message = "Feedback deleted successfully.";
        $messageType = "success";
    } else {
        $message = "Error deleting feedback: " . $stmt->error;
        $messageType = "danger";
    }
}

// Get all feedback
$sql = "SELECT f.*, u.name as user_name, u.role as user_role 
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        ORDER BY f.created_at DESC";
$result = $conn->query($sql);
$feedbacks = [];
while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
}
?>

<h1 class="page-title">
    <i class="fas fa-comments"></i> View Feedback
</h1>

<?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="feedback-list-container">
    <?php if (count($feedbacks) > 0): ?>
        <?php foreach ($feedbacks as $feedback): ?>
            <div class="feedback-card">
                <div class="feedback-header">
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($feedback['user_name']); ?></h4>
                        <span class="user-role">
                            <?php echo ucfirst($feedback['user_role']); ?>
                        </span>
                    </div>
                    <div class="feedback-date">
                        <?php echo date('M d, Y H:i', strtotime($feedback['created_at'])); ?>
                    </div>
                </div>
                
                <div class="feedback-content">
                    <h3><?php echo htmlspecialchars($feedback['subject']); ?></h3>
                    <div class="feedback-type">
                        Type: <?php echo ucfirst($feedback['type']); ?>
                    </div>
                    <div class="feedback-message">
                        <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                    </div>
                </div>
                
                <div class="feedback-actions">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                        <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                        <button type="submit" name="delete_feedback" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-feedback">
            <i class="fas fa-comments fa-3x"></i>
            <h3>No Feedback Found</h3>
            <p>No feedback has been submitted yet.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.feedback-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative;
    margin-top: 30px 
}

.feedback-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.user-role {
    color: #666;
    font-size: 0.9em;
}

.feedback-date {
    color: #666;
}

.feedback-content h3 {
    margin-top: 0;
    color: #333;
}

.feedback-type {
    color: #666;
    margin: 5px 0;
}

.feedback-message {
    line-height: 1.6;
    margin-bottom: 15px;
}

.feedback-actions {
    text-align: right;
}

.no-feedback {
    text-align: center;
    padding: 40px;
    color: #666;
}

.alert {
    padding: 10px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
}

.btn {
    padding: 5px 10px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
}
</style>

<?php include_once '../includes/footer.php'; ?>